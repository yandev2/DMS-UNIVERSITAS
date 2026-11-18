<?php

namespace Tests\Feature\Seeders;

use App\Enums\UnitType;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\Asset;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PostPhoto;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Unit;
use Database\Seeders\DummyTestingSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_master_data_seeder_populates_official_units_roles_criteria_and_settings(): void
    {
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(19, Unit::count());
        $this->assertSame(9, Unit::where('type', UnitType::Prodi)->count());
        $this->assertSame(12, Role::count());
        $this->assertSame(168, Permission::count());
        $this->assertSame(27, AccreditationCriterion::count());
        $this->assertSame(5, AcademicYear::count());
        $this->assertSame(4, Setting::count());
    }

    public function test_dummy_testing_seeder_does_not_create_records_outside_local_or_testing_environment(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->seed(MasterDataSeeder::class);

        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            app(DummyTestingSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }

        $this->assertSame(0, Document::count());
        $this->assertSame(0, DocumentFile::count());
        $this->assertSame(0, Post::count());
        $this->assertSame(0, Asset::count());
    }

    public function test_dummy_testing_seeder_creates_sample_records_and_physical_files_in_testing_environment(): void
    {
        Storage::fake('private');
        Storage::fake('public');

        $this->seed([
            MasterDataSeeder::class,
            DummyTestingSeeder::class,
        ]);

        $this->assertSame(5, Document::count());
        $this->assertSame(5, DocumentFile::count());
        $this->assertSame(3, Post::count());
        $this->assertSame(9, PostPhoto::count());
        $this->assertSame(3, Asset::count());

        $syncedFilesCount = DocumentFile::where('is_synced', true)->count();
        $this->assertSame(4, $syncedFilesCount);

        foreach (DocumentFile::all() as $documentFile) {
            Storage::disk($documentFile->disk->value)->assertExists($documentFile->original_file_path);

            if ($documentFile->stamped_file_path !== null) {
                Storage::disk($documentFile->disk->value)->assertExists($documentFile->stamped_file_path);
            }
        }

        foreach (PostPhoto::all() as $photo) {
            Storage::disk($photo->disk->value)->assertExists($photo->file_path);

            if ($photo->thumbnail_path !== null) {
                Storage::disk($photo->disk->value)->assertExists($photo->thumbnail_path);
            }
        }
    }
}
