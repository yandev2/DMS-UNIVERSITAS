<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\LandingPageSetting;
use App\Models\Post;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    /**
     * Tampilkan Halaman Utama (Landing Page) Resmi Sistem.
     */
    public function index(Request $request): View
    {
        // 1. Ambil Pengaturan Beranda dari CMS (Singleton Cache)
        $settings = LandingPageSetting::current();

        // 2. Query Daftar Dokumen Publik Sah
        $searchQuery = trim((string) $request->input('q', ''));
        $selectedCategory = $request->input('category');
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $documentsQuery = Document::query()
            ->where('visibility', VisibilityLevel::Public)
            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::Archived])
            ->whereNotNull('document_number')
            ->with(['unit', 'category', 'primaryFile']);

        if ($searchQuery !== '') {
            $documentsQuery->where(function ($q) use ($searchQuery, $likeOperator): void {
                $q->where('title', $likeOperator, "%{$searchQuery}%")
                    ->orWhere('document_number', $likeOperator, "%{$searchQuery}%")
                    ->orWhereHas('unit', function ($uq) use ($searchQuery, $likeOperator): void {
                        $uq->where('name', $likeOperator, "%{$searchQuery}%")
                            ->orWhere('code', $likeOperator, "%{$searchQuery}%");
                    });
            });
        }

        if (! empty($selectedCategory)) {
            $documentsQuery->where('document_category_id', $selectedCategory);
        }

        $perPage = max(5, min(50, (int) ($settings->docs_per_page ?? 10)));
        $documents = $documentsQuery
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = DocumentCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // 3. Query Maksimal 5 Warta/Berita Kampus Terbaru
        $maxNews = max(1, min(5, (int) ($settings->news_max_items ?? 5)));
        $latestPosts = Post::published()
            ->with(['category', 'unit', 'author'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit($maxNews)
            ->get();

        // 4. Ringkasan Statistik Cepat (Quick Glance Metrics)
        $stats = [
            'total_public_docs' => Document::query()
                ->where('visibility', VisibilityLevel::Public)
                ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::Archived])
                ->whereNotNull('document_number')
                ->count(),
            'total_published_posts' => Post::published()->count(),
            'total_units' => Unit::query()->where('is_active', true)->count(),
        ];

        return view('public.home', [
            'settings' => $settings,
            'documents' => $documents,
            'categories' => $categories,
            'latestPosts' => $latestPosts,
            'searchQuery' => $searchQuery,
            'selectedCategory' => $selectedCategory,
            'stats' => $stats,
        ]);
    }
}
