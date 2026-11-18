<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentCategoryTemplateController extends Controller
{
    /**
     * Mengunduh berkas template resmi naskah dinas (.docx / .doc) secara aman.
     */
    public function download(Request $request, DocumentCategory $category): StreamedResponse
    {
        $user = auth()->user();
        $canDownloadTemplate = $user !== null && (
            $user->can('view', $category)
            || $user->can('create', Document::class)
            || $user->can('viewAny', Document::class)
        );

        if (! $canDownloadTemplate) {
            abort(403, 'Akses terbatas untuk civitas akademika terautentikasi yang memiliki izin mengakses dokumen atau kategori dokumen.');
        }

        if (! $category->hasTemplate()) {
            abort(404, 'Kategori dokumen ini belum memiliki berkas template naskah dinas yang diunggah.');
        }

        $disk = $category->resolveTemplateDisk();
        $path = $category->template_file_path;

        if ($disk === null) {
            abort(404, 'Berkas fisik template tidak ditemukan pada sistem penyimpanan.');
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'docx';
        $fileName = $category->template_file_name;

        if (blank($fileName)) {
            $fileName = 'Template_'.Str::slug($category->name).'.'.$extension;
        } elseif (! str_ends_with(strtolower($fileName), '.'.strtolower($extension))) {
            $fileName .= '.'.$extension;
        }

        activity('master_data')
            ->performedOn($category)
            ->causedBy(auth()->user())
            ->event('template_downloaded')
            ->withProperties([
                'category_code' => $category->code,
                'file_name' => $fileName,
            ])
            ->log("Mengunduh template naskah dinas kategori: {$category->name} ({$category->code})");

        return Storage::disk($disk)->download(
            $path,
            $fileName,
            [
                'Cache-Control' => 'private, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
