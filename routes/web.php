<?php

declare(strict_types=1);

use App\Http\Controllers\BorangPublicViewerController;
use App\Http\Controllers\BulkExportDownloadController;
use App\Http\Controllers\DocumentCategoryTemplateController;
use App\Http\Controllers\DocumentStreamController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LetterFileController;
use App\Http\Controllers\PostPublicViewerController;
use App\Http\Controllers\SystemBackupDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'index'])->name('landing.home');

Route::redirect('/login', '/admin/login')->name('login');

// Public QR-Code Document Verification Routes
Route::middleware(['throttle:60,1'])->prefix('verify')->name('document.verify.')->group(function (): void {
    Route::get('{uuid}', [DocumentVerificationController::class, 'show'])->name('show');
    Route::get('{uuid}/stream', [DocumentVerificationController::class, 'streamFile'])->name('stream');
    Route::get('{uuid}/download', [DocumentVerificationController::class, 'downloadFile'])->name('download');
});

// Public Campus News, Announcements & Activities Gallery Routes
Route::middleware(['throttle:60,1'])->prefix('berita')->name('public.posts.')->group(function (): void {
    Route::get('/', [PostPublicViewerController::class, 'index'])->name('index');
    Route::get('{slug}', [PostPublicViewerController::class, 'show'])->name('show');
    Route::get('{slug}/unduh-foto', [PostPublicViewerController::class, 'downloadZipPhotos'])->name('download_photos');
});

// Public Assessor Accreditation Routes
Route::prefix('bukti-borang')->name('borang.public.')->group(function (): void {
    Route::match(['get', 'post'], 'unit/{unitCode}', [BorangPublicViewerController::class, 'showUnitPortal'])->name('unit');
    Route::post('unit/{unitCode}/track/{uuid}', [BorangPublicViewerController::class, 'trackUnitView'])->name('unit.track');
    Route::match(['get', 'post'], '{uuid}', [BorangPublicViewerController::class, 'show'])->name('show');
    Route::get('{uuid}/stream', [BorangPublicViewerController::class, 'streamFile'])->name('stream');
    Route::get('{uuid}/download', [BorangPublicViewerController::class, 'downloadFile'])->name('download');
});

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::prefix('documents')->name('documents.')->group(function (): void {
        Route::get('{document}/stream', [DocumentStreamController::class, 'stream'])->name('stream');
        Route::get('{document}/download', [DocumentStreamController::class, 'download'])->name('download');
    });

    Route::prefix('document-categories')->name('document-categories.')->group(function (): void {
        Route::get('{category}/template-download', [DocumentCategoryTemplateController::class, 'download'])->name('download-template');
    });

    Route::prefix('bulk-exports')->name('bulk-exports.')->group(function (): void {
        Route::get('{uuid}/download', [BulkExportDownloadController::class, 'download'])->name('download');
    });

    Route::prefix('system-backups')->name('system-backups.')->group(function (): void {
        Route::get('download/{filename}', [SystemBackupDownloadController::class, 'download'])
            ->where('filename', '[A-Za-z0-9._-]+')
            ->name('download');
    });
});

Route::middleware(['auth'])->prefix('admin')->group(function (): void {
    Route::prefix('incoming-letters-file')->name('incoming-letters.file.')->group(function (): void {
        Route::get('{incomingLetter:uuid}/stream', [LetterFileController::class, 'streamIncoming'])->name('stream');
        Route::get('{incomingLetter:uuid}/download', [LetterFileController::class, 'downloadIncoming'])->name('download');
    });

    Route::prefix('outgoing-letters-file')->name('outgoing-letters.file.')->group(function (): void {
        Route::get('{outgoingLetter:uuid}/stream', [LetterFileController::class, 'streamOutgoing'])->name('stream');
        Route::get('{outgoingLetter:uuid}/download', [LetterFileController::class, 'downloadOutgoing'])->name('download');
    });
});
