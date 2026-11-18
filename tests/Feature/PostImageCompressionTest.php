<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostPeriod;
use App\Enums\PostStatus;
use App\Enums\StorageDisk;
use App\Exceptions\ImageCompressionException;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Jobs\CompressPostPhotoJob;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostPhoto;
use App\Models\Unit;
use App\Models\User;
use App\Services\PostImageCompressionService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class PostImageCompressionTest extends TestCase
{
    use RefreshDatabase;

    private PostImageCompressionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(MasterDataSeeder::class);
        $this->service = app(PostImageCompressionService::class);
    }

    public function test_large_high_resolution_image_is_compressed_below_one_megabyte_with_thumbnail(): void
    {
        $sourcePath = $this->createLargeNoisyPng(2400, 1600);

        try {
            $originalSize = filesize($sourcePath);
            $this->assertGreaterThan(PostImageCompressionService::MAX_OUTPUT_BYTES, $originalSize);

            $result = $this->service->compressLocalFileToTarget($sourcePath, true);

            $this->assertLessThanOrEqual(PostImageCompressionService::MAX_OUTPUT_BYTES, $result['size']);
            $this->assertLessThanOrEqual(2048, $result['width']);
            $this->assertLessThanOrEqual(2048, $result['height']);
            $this->assertSame('webp', $result['extension']);
            $this->assertSame('image/webp', $result['mime']);
            $this->assertNotNull($result['thumbnail_binary']);
            $this->assertLessThan($result['size'], strlen($result['thumbnail_binary']));
        } finally {
            @unlink($sourcePath);
        }
    }

    public function test_small_uploaded_image_uses_fast_sync_path_and_generates_thumbnail_immediately(): void
    {
        $uploadedFile = TemporaryUploadedFile::fake()->image('kegiatan.jpg', 1200, 800);

        $prepared = $this->service->prepareUploadForPostPhoto($uploadedFile, 'public', 'posts/photos');

        $this->assertFalse($prepared['is_queued']);
        $this->assertStringStartsWith('posts/photos/', $prepared['path']);
        $this->assertStringNotContainsString('/pending/', $prepared['path']);
        $this->assertNotNull($prepared['thumbnail_path']);
        $this->assertLessThanOrEqual(PostImageCompressionService::MAX_OUTPUT_BYTES, $prepared['file_size']);
        $this->assertSame('image/webp', $prepared['mime_type']);
        $this->assertSame('webp', $prepared['file_extension']);
        $this->assertSame(1200, $prepared['width']);
        $this->assertSame(800, $prepared['height']);

        Storage::disk('public')->assertExists($prepared['path']);
        Storage::disk('public')->assertExists($prepared['thumbnail_path']);
    }

    public function test_oversized_uploaded_image_routes_to_pending_and_compresses_via_queued_job(): void
    {
        Queue::fake();

        $user = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $post = $this->createSamplePost($user);

        $largeFilePath = $this->createLargeNoisyPng(2400, 1600);

        try {
            $largeBytes = file_get_contents($largeFilePath);
            $this->assertIsString($largeBytes);

            $tempUpload = TemporaryUploadedFile::fake()->createWithContent('foto-mentah-kamera.png', $largeBytes);

            $prepared = $this->service->prepareUploadForPostPhoto($tempUpload, 'public', 'posts/photos');

            $this->assertTrue($prepared['is_queued']);
            $this->assertStringStartsWith(PostImageCompressionService::PENDING_DIRECTORY.'/', $prepared['path']);
            Storage::disk('public')->assertExists($prepared['path']);

            $photo = PostPhoto::create([
                'post_id' => $post->id,
                'uploaded_by' => $user->id,
                'file_path' => $prepared['path'],
                'disk' => StorageDisk::Public,
                'caption' => 'Dokumentasi Wisuda',
                'is_cover' => true,
                'order_index' => 1,
            ]);

            Queue::assertPushed(CompressPostPhotoJob::class, function (CompressPostPhotoJob $job) use ($photo, $user): bool {
                return $job->postPhotoId === $photo->id && $job->uploaderId === $user->id;
            });

            // Eksekusi job secara langsung untuk memverifikasi hasil kompresi & pembersihan file mentah
            (new CompressPostPhotoJob($photo->id, $user->id))->handle($this->service);

            $photo->refresh();
            $post->refresh();

            $this->assertStringStartsWith('posts/photos/', $photo->file_path);
            $this->assertStringNotContainsString('/pending/', $photo->file_path);
            $this->assertNotNull($photo->thumbnail_path);
            $this->assertSame('webp', $photo->file_extension);
            $this->assertSame('image/webp', $photo->mime_type);
            $this->assertLessThanOrEqual(PostImageCompressionService::MAX_OUTPUT_BYTES, $photo->file_size);
            $this->assertNotNull($photo->width);
            $this->assertNotNull($photo->height);

            Storage::disk('public')->assertMissing($prepared['path']);
            Storage::disk('public')->assertExists($photo->file_path);
            Storage::disk('public')->assertExists($photo->thumbnail_path);
            $this->assertSame($photo->thumbnail_path, $post->thumbnail_url);
        } finally {
            @unlink($largeFilePath);
        }
    }

    public function test_invalid_fake_non_image_file_throws_clear_exception(): void
    {
        $fakeImagePath = tempnam(sys_get_temp_dir(), 'fake_img_').'.jpg';
        file_put_contents($fakeImagePath, '<?php echo "ini bukan gambar"; ?>');

        try {
            $this->expectException(ImageCompressionException::class);
            $this->expectExceptionMessage('Format berkas tidak didukung');

            $this->service->validateSourceFile($fakeImagePath);
        } finally {
            @unlink($fakeImagePath);
        }
    }

    public function test_empty_file_throws_clear_exception(): void
    {
        $emptyPath = tempnam(sys_get_temp_dir(), 'empty_img_');

        try {
            $this->expectException(ImageCompressionException::class);
            $this->expectExceptionMessage('Berkas gambar kosong (0 byte)');

            $this->service->validateSourceFile($emptyPath);
        } finally {
            @unlink($emptyPath);
        }
    }

    public function test_corrupt_binary_image_header_throws_clear_exception(): void
    {
        $corruptPngPath = tempnam(sys_get_temp_dir(), 'corrupt_png_');
        // Header magic PNG tetapi data IHDR korup/terpotong
        file_put_contents($corruptPngPath, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDRcorrupt");

        try {
            $this->expectException(ImageCompressionException::class);
            $this->expectExceptionMessage('File tidak dapat dibaca sebagai gambar yang valid');

            $this->service->validateSourceFile($corruptPngPath);
        } finally {
            @unlink($corruptPngPath);
        }
    }

    public function test_pixel_bomb_dimensions_are_blocked_before_gd_decode(): void
    {
        $bombPath = $this->createPngWithCustomDimensionsInHeader(20000, 20000);

        try {
            $this->expectException(ImageCompressionException::class);
            $this->expectExceptionMessage('Resolusi dimensi foto terlalu ekstrem (20000 × 20000 piksel');

            $this->service->validateSourceFile($bombPath);
        } finally {
            @unlink($bombPath);
        }
    }

    public function test_deleting_and_replacing_post_photo_cleans_up_stored_files(): void
    {
        $user = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $post = $this->createSamplePost($user);

        $firstUpload = $this->service->prepareUploadForPostPhoto(
            TemporaryUploadedFile::fake()->image('foto1.jpg', 800, 600),
            'public',
            'posts/photos',
        );

        $photo = PostPhoto::create([
            'post_id' => $post->id,
            'uploaded_by' => $user->id,
            'file_path' => $firstUpload['path'],
            'thumbnail_path' => $firstUpload['thumbnail_path'],
            'disk' => StorageDisk::Public,
            'is_cover' => true,
            'order_index' => 1,
        ]);

        Storage::disk('public')->assertExists($firstUpload['path']);
        Storage::disk('public')->assertExists($firstUpload['thumbnail_path']);

        $secondUpload = $this->service->prepareUploadForPostPhoto(
            TemporaryUploadedFile::fake()->image('foto2.jpg', 900, 700),
            'public',
            'posts/photos',
        );

        $photo->update([
            'file_path' => $secondUpload['path'],
            'thumbnail_path' => $secondUpload['thumbnail_path'],
        ]);

        Storage::disk('public')->assertMissing($firstUpload['path']);
        Storage::disk('public')->assertMissing($firstUpload['thumbnail_path']);
        Storage::disk('public')->assertExists($secondUpload['path']);
        Storage::disk('public')->assertExists($secondUpload['thumbnail_path']);

        $photo->delete();

        Storage::disk('public')->assertMissing($secondUpload['path']);
        Storage::disk('public')->assertMissing($secondUpload['thumbnail_path']);
        $this->assertSame(0, $post->fresh()->photos_count);
    }

    public function test_failed_background_job_sends_clear_database_notification_to_uploader(): void
    {
        Queue::fake([CompressPostPhotoJob::class]);

        $user = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $post = $this->createSamplePost($user);

        $photo = PostPhoto::create([
            'post_id' => $post->id,
            'uploaded_by' => $user->id,
            'file_path' => PostImageCompressionService::PENDING_DIRECTORY.'/tidak-ada.jpg',
            'disk' => StorageDisk::Public,
            'order_index' => 1,
        ]);

        $exception = ImageCompressionException::corruptOrInvalidImage();
        (new CompressPostPhotoJob($photo->id, $user->id))->failed($exception);

        $notification = $user->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Kompresi Foto Berita Gagal', $notification->data['title'] ?? null);
        $this->assertStringContainsString('korup', (string) ($notification->data['body'] ?? ''));
    }

    public function test_rich_editor_attachment_sync_compression_stores_under_one_megabyte(): void
    {
        $largeFilePath = $this->createLargeNoisyPng(2400, 1600);

        try {
            $largeBytes = file_get_contents($largeFilePath);
            $this->assertIsString($largeBytes);

            $attachment = TemporaryUploadedFile::fake()->createWithContent('lampiran-konten.png', $largeBytes);

            $stored = $this->service->compressUploadedFileSync($attachment, 'public', 'posts/attachments', false);

            $this->assertStringStartsWith('posts/attachments/', $stored['path']);
            $this->assertNull($stored['thumbnail_path']);
            $this->assertSame('webp', $stored['file_extension']);
            $this->assertLessThanOrEqual(PostImageCompressionService::MAX_OUTPUT_BYTES, $stored['file_size']);
            Storage::disk('public')->assertExists($stored['path']);
        } finally {
            @unlink($largeFilePath);
        }
    }

    public function test_create_post_filament_form_uploads_and_compresses_gallery_photo_end_to_end(): void
    {
        $user = User::where('email', 'admin@univbi.ac.id')->firstOrFail();
        $category = PostCategory::firstOrCreate(
            ['slug' => 'kegiatan-kampus'],
            ['name' => 'Kegiatan Kampus', 'is_active' => true],
        );
        $unit = $user->unit_id ? Unit::findOrFail($user->unit_id) : Unit::firstOrFail();
        $photoUpload = UploadedFile::fake()->image('seminar-nasional.jpg', 1200, 900);

        Livewire::actingAs($user)
            ->test(CreatePost::class)
            ->assertOk()
            ->fillForm([
                'title' => 'Seminar Nasional Digitalisasi Arsip',
                'slug' => 'seminar-nasional-digitalisasi-arsip',
                'content' => '<p>Liputan resmi seminar nasional kampus.</p>',
                'post_category_id' => $category->id,
                'period' => PostPeriod::Daily->value,
                'unit_id' => $unit->id,
                'status' => PostStatus::Draft->value,
                'postDocuments' => [],
                'photos' => [
                    [
                        'file_path' => [$photoUpload],
                        'caption' => 'Pembukaan Seminar',
                        'is_cover' => true,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'seminar-nasional-digitalisasi-arsip')->firstOrFail();
        $photo = $post->photos()->firstOrFail();

        $this->assertSame('webp', $photo->file_extension);
        $this->assertSame('image/webp', $photo->mime_type);
        $this->assertLessThanOrEqual(PostImageCompressionService::MAX_OUTPUT_BYTES, $photo->file_size);
        $this->assertNotNull($photo->thumbnail_path);
        $this->assertSame(1200, $photo->width);
        $this->assertSame(900, $photo->height);
        Storage::disk('public')->assertExists($photo->file_path);
        Storage::disk('public')->assertExists($photo->thumbnail_path);
    }

    private function createSamplePost(User $author): Post
    {
        $category = PostCategory::firstOrCreate(
            ['slug' => 'kegiatan-kampus'],
            ['name' => 'Kegiatan Kampus', 'is_active' => true],
        );

        $unit = $author->unit_id
            ? Unit::findOrFail($author->unit_id)
            : Unit::firstOrFail();

        return Post::create([
            'title' => 'Berita Dokumentasi '.Str::random(6),
            'slug' => 'berita-dokumentasi-'.Str::lower(Str::random(8)),
            'post_category_id' => $category->id,
            'unit_id' => $unit->id,
            'author_id' => $author->id,
            'content' => '<p>Konten berita resmi kampus.</p>',
            'status' => PostStatus::Draft,
        ]);
    }

    /**
     * Buat berkas PNG beresolusi tinggi dengan pola acak berwarna agar ukurannya > 1 MB
     * sebelum dikompresi secara adaptif.
     */
    private function createLargeNoisyPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $step = 16;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                imagefilledrectangle($image, $x, $y, min($width - 1, $x + $step - 1), min($height - 1, $y + $step - 1), $color);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'noisy_').'.png';
        // Level kompresi zlib 0 (uncompressed PNG) memastikan ukuran > 1 MB meskipun blok teratur
        imagepng($image, $path, 0);
        imagedestroy($image);

        return $path;
    }

    /**
     * Buat file PNG minimal (hanya header IHDR) yang mengklaim dimensi raksasa
     * untuk menguji proteksi decompression bomb tanpa menghabiskan RAM test runner.
     */
    private function createPngWithCustomDimensionsInHeader(int $width, int $height): string
    {
        $ihdrData = pack('NNccccc', $width, $height, 8, 2, 0, 0, 0);
        $ihdrChunk = pack('N', 13).'IHDR'.$ihdrData.pack('N', crc32('IHDR'.$ihdrData));
        $iendChunk = pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        $path = tempnam(sys_get_temp_dir(), 'bomb_').'.png';
        file_put_contents($path, "\x89PNG\r\n\x1a\n".$ihdrChunk.$iendChunk);

        return $path;
    }
}
