<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PostPeriod;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\PostPhotoZipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostPublicViewerController extends Controller
{
    /**
     * Tampilkan laman indeks berita, pengumuman, dan agenda kegiatan kampus.
     */
    public function index(Request $request): View
    {
        $categories = PostCategory::query()
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();

        $selectedCategorySlug = $request->query('kategori');
        $selectedPeriodParam = trim((string) $request->query('edisi', ''));
        $selectedPeriod = PostPeriod::tryFrom($selectedPeriodParam);
        $searchQuery = trim((string) $request->query('q', ''));

        // Ambil headline berita utama (Featured) jika pengguna tidak sedang memfilter atau mencari spesifik
        $featuredPost = null;
        if (empty($searchQuery) && empty($selectedCategorySlug) && $selectedPeriod === null) {
            $featuredPost = Post::published()
                ->with([
                    'category',
                    'unit',
                    'author:id,name,avatar_url',
                    'photos' => fn ($q) => $q->orderBy('order_index')->limit(4),
                ])
                ->withCount(['photos', 'publicDocuments'])
                ->featured()
                ->latest('published_at')
                ->first();
        }

        $posts = Post::published()
            ->with(['category', 'unit'])
            ->withCount('photos')
            ->when($selectedCategorySlug, function (Builder $q) use ($selectedCategorySlug): void {
                $q->whereHas('category', fn ($c) => $c->where('slug', $selectedCategorySlug));
            })
            ->when($selectedPeriod, fn (Builder $q): Builder => $q->where('period', $selectedPeriod->value))
            ->when(! empty($searchQuery), function (Builder $q) use ($searchQuery): void {
                $keyword = '%'.$searchQuery.'%';
                $q->where(function (Builder $sub) use ($keyword): void {
                    $sub->where('title', 'like', $keyword)
                        ->orWhere('excerpt', 'like', $keyword)
                        ->orWhere('content', 'like', $keyword)
                        ->orWhere('event_location', 'like', $keyword);
                });
            })
            ->when($featuredPost, fn (Builder $q) => $q->where('id', '!=', $featuredPost->id))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('public.posts.index', [
            'categories' => $categories,
            'periods' => PostPeriod::cases(),
            'selectedCategorySlug' => $selectedCategorySlug,
            'selectedPeriod' => $selectedPeriod,
            'searchQuery' => $searchQuery,
            'featuredPost' => $featuredPost,
            'posts' => $posts,
        ]);
    }

    /**
     * Tampilkan laman detail berita, dokumentasi foto galeri, dan dokumen sah terkait.
     */
    public function show(string $slug): View
    {
        $post = Post::published()
            ->with([
                'category',
                'unit',
                'academicYear',
                'author',
                'photos' => fn ($q) => $q->orderBy('order_index'),
                'publicDocuments.unit',
                'publicDocuments.primaryFile',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        // Inkrementasi statistik kunjungan pembaca
        $post->increment('views_count');

        // Artikel rekomendasi terkait (utamakan kategori sama, lalu berita terbaru lainnya agar sidebar tetap terisi)
        $relatedPosts = Post::published()
            ->with(['category', 'unit'])
            ->withCount('photos')
            ->where('id', '!=', $post->id)
            ->orderByRaw('CASE WHEN post_category_id = ? THEN 0 ELSE 1 END', [(int) ($post->post_category_id ?? 0)])
            ->latest('published_at')
            ->limit(4)
            ->get();

        return view('public.posts.show', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
        ]);
    }

    /**
     * Unduh seluruh foto dokumentasi kegiatan dalam paket file ZIP berstruktur.
     */
    public function downloadZipPhotos(string $slug): StreamedResponse
    {
        $post = Post::published()
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $post->allow_public_zip_download) {
            abort(403, 'Akses Ditolak: Pengunduhan paket dokumentasi foto untuk kegiatan ini telah dinonaktifkan oleh institusi.');
        }

        if ($post->photos()->count() === 0) {
            abort(404, 'Tidak ditemukan berkas foto dokumentasi pada kegiatan ini.');
        }

        $post->increment('zip_downloads_count');

        return app(PostPhotoZipService::class)->downloadPostPhotosZip($post);
    }
}
