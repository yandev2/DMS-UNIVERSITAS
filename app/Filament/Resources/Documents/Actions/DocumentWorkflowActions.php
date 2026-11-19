<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Actions;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Rules\PdfDocumentFileRule;
use App\Services\DocumentApprovalService;
use App\Services\DocumentNamingService;
use App\Services\DocumentStampingService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

class DocumentWorkflowActions
{
    public static function makeSubmitAction(?Document $fixedRecord = null): Action
    {
        return Action::make('submitApproval')
            ->label('Ajukan Persetujuan')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Ajukan Dokumen ke Alur Persetujuan?')
            ->modalDescription('Dokumen berstatus Draf akan dikirimkan ke pejabat berwenang sesuai alur persetujuan kategori.')
            ->modalSubmitActionLabel('Ya, Ajukan Sekarang')
            ->action(function (?Document $record = null) use ($fixedRecord): void {
                $target = $fixedRecord ?? $record;
                if (! $target) {
                    return;
                }

                self::runSafe($target, function (Document $doc): void {
                    if (! (auth()->user()?->can('update', $doc) ?? false)) {
                        throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengajukan dokumen ini.');
                    }

                    app(DocumentApprovalService::class)->initializeApprovalFlow($doc);
                    $doc->refresh();

                    Notification::make()
                        ->success()
                        ->title(match ($doc->status) {
                            DocumentStatus::Approved => 'Dokumen Berhasil Disahkan',
                            DocumentStatus::WaitingSignature => 'Nomor Terbit — Menunggu TTD Basah',
                            default => 'Dokumen Berhasil Diajukan',
                        })
                        ->body(match ($doc->status) {
                            DocumentStatus::Approved => "Dokumen resmi disahkan! Nomor resmi {$doc->document_number} telah terbit.",
                            DocumentStatus::WaitingSignature => "Nomor {$doc->document_number} telah terbit! Silakan unduh berkas untuk TTD basah & cap, lalu unggah scan PDF final.",
                            default => 'Dokumen telah masuk ke antrean persetujuan tingkat 1.',
                        })
                        ->send();
                });
            })
            ->visible(function (?Document $record = null) use ($fixedRecord): bool {
                $target = $fixedRecord ?? $record;
                if (! $target) {
                    return false;
                }

                return $target->status === DocumentStatus::Draft
                    && ! $target->is_manual_number
                    && (auth()->user()?->can('update', $target) ?? false)
                    && self::hasPhysicalFile($target);
            });
    }

    public static function makeApproveAction(?Document $fixedRecord = null, bool $useLoadedRelations = false): Action
    {
        return Action::make('approve')
            ->label('Setujui Dokumen')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->form([
                Textarea::make('note')
                    ->label('Catatan Persetujuan (Opsional)')
                    ->placeholder('Contoh: Disetujui tanpa catatan...')
                    ->rows(3),
            ])
            ->action(function (array $data, ?Document $record = null) use ($fixedRecord): void {
                $target = $fixedRecord ?? $record;
                if (! $target || ! auth()->user()) {
                    return;
                }

                self::runSafe($target, function (Document $doc) use ($data): void {
                    $processed = app(DocumentApprovalService::class)->approve($doc, auth()->user(), $data['note'] ?? null);
                    $doc->refresh();

                    if (! $processed) {
                        Notification::make()
                            ->warning()
                            ->title('Persetujuan Tidak Dapat Diproses')
                            ->body('Tahapan persetujuan dokumen ini sudah berubah atau telah diproses sebelumnya.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title($doc->status === DocumentStatus::WaitingSignature
                            ? 'Substansi Disetujui — Menunggu TTD Basah'
                            : 'Dokumen Berhasil Disetujui')
                        ->body(match ($doc->status) {
                            DocumentStatus::Approved => "Dokumen resmi disahkan! Nomor resmi {$doc->document_number} telah terbit.",
                            DocumentStatus::WaitingSignature => "Substansi disetujui & nomor {$doc->document_number} telah terbit! Berkas siap diunduh untuk TTD basah, cap Humas, dan unggah scan PDF final.",
                            default => 'Persetujuan Anda telah dicatat dan diteruskan ke tahapan berikutnya.',
                        })
                        ->send();
                });
            })
            ->visible(function (?Document $record = null) use ($fixedRecord, $useLoadedRelations): bool {
                $target = $fixedRecord ?? $record;
                $user = auth()->user();

                if (! $target || ! $user) {
                    return false;
                }

                return app(DocumentApprovalService::class)->canUserApprove($user, $target, $useLoadedRelations);
            });
    }

    public static function makeRequestRevisionAction(?Document $fixedRecord = null, bool $useLoadedRelations = false): Action
    {
        return Action::make('requestRevision')
            ->label('Minta Revisi')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->form([
                Textarea::make('note')
                    ->label('Catatan Revisi / Perbaikan Wajib')
                    ->placeholder('Jelaskan bagian yang perlu diperbaiki...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, ?Document $record = null) use ($fixedRecord): void {
                $target = $fixedRecord ?? $record;
                if (! $target || ! auth()->user()) {
                    return;
                }

                self::runSafe($target, function (Document $doc) use ($data): void {
                    $processed = app(DocumentApprovalService::class)->requestRevision($doc, auth()->user(), (string) ($data['note'] ?? ''));
                    $doc->refresh();

                    if (! $processed) {
                        Notification::make()
                            ->warning()
                            ->title('Permintaan Revisi Dilewati')
                            ->body('Status persetujuan dokumen ini sudah berubah sebelumnya.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->warning()
                        ->title('Permintaan Revisi Dikirim')
                        ->body('Catatan perbaikan telah dicatat dan status dokumen berubah menjadi Revisi.')
                        ->send();
                });
            })
            ->visible(function (?Document $record = null) use ($fixedRecord, $useLoadedRelations): bool {
                $target = $fixedRecord ?? $record;
                $user = auth()->user();

                if (! $target || ! $user) {
                    return false;
                }

                return app(DocumentApprovalService::class)->canUserApprove($user, $target, $useLoadedRelations);
            });
    }

    public static function makeRejectAction(?Document $fixedRecord = null, bool $useLoadedRelations = false): Action
    {
        return Action::make('reject')
            ->label('Tolak Dokumen')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form([
                Textarea::make('note')
                    ->label('Alasan Penolakan Wajib')
                    ->placeholder('Jelaskan alasan penolakan naskah dinas...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, ?Document $record = null) use ($fixedRecord): void {
                $target = $fixedRecord ?? $record;
                if (! $target || ! auth()->user()) {
                    return;
                }

                self::runSafe($target, function (Document $doc) use ($data): void {
                    $processed = app(DocumentApprovalService::class)->reject($doc, auth()->user(), (string) ($data['note'] ?? ''));
                    $doc->refresh();

                    if (! $processed) {
                        Notification::make()
                            ->warning()
                            ->title('Penolakan Dilewati')
                            ->body('Status persetujuan dokumen ini sudah berubah sebelumnya.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Dokumen Ditolak')
                        ->body('Keputusan penolakan telah dicatat secara resmi.')
                        ->send();
                });
            })
            ->visible(function (?Document $record = null) use ($fixedRecord, $useLoadedRelations): bool {
                $target = $fixedRecord ?? $record;
                $user = auth()->user();

                if (! $target || ! $user) {
                    return false;
                }

                return app(DocumentApprovalService::class)->canUserApprove($user, $target, $useLoadedRelations);
            });
    }

    public static function makeResubmitAction(?Document $fixedRecord = null): Action
    {
        return Action::make('resubmit')
            ->label('Ajukan Kembali')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Ajukan Kembali Dokumen?')
            ->modalDescription('Dokumen yang telah direvisi akan diajukan kembali ke alur persetujuan tingkat 1.')
            ->modalSubmitActionLabel('Ya, Ajukan Kembali')
            ->action(function (?Document $record = null) use ($fixedRecord): void {
                $target = $fixedRecord ?? $record;
                if (! $target) {
                    return;
                }

                self::runSafe($target, function (Document $doc): void {
                    app(DocumentApprovalService::class)->resubmit($doc, auth()->user());
                    $doc->refresh();

                    Notification::make()
                        ->success()
                        ->title('Dokumen Berhasil Diajukan Kembali')
                        ->body(match ($doc->status) {
                            DocumentStatus::Approved => "Dokumen resmi disahkan! Nomor resmi {$doc->document_number} telah terbit.",
                            DocumentStatus::WaitingSignature => "Nomor {$doc->document_number} telah terbit! Silakan cetak untuk TTD basah & cap lalu unggah scan PDF final.",
                            default => 'Dokumen telah masuk kembali ke antrean persetujuan tingkat 1.',
                        })
                        ->send();
                });
            })
            ->visible(function (?Document $record = null) use ($fixedRecord): bool {
                $target = $fixedRecord ?? $record;
                if (! $target) {
                    return false;
                }

                return $target->status === DocumentStatus::Revision
                    && (auth()->user()?->can('update', $target) ?? false);
            });
    }

    public static function makeStampAction(?Document $fixedRecord = null): Action
    {
        return Action::make('stamp')
            ->label(function (?Document $record = null) use ($fixedRecord): string {
                $target = $fixedRecord ?? $record;

                return $target?->primaryFile?->stamped_file_path ? 'Perbarui Stempel' : 'Bubuhi Stempel';
            })
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Bubuhi Stempel Resmi & QR Code?')
            ->modalDescription('Sistem akan membubuhi kotak penomoran naskah dinas, tanggal pengesahan, pejabat penandatangan, dan stempel QR-Code verifikasi ke berkas dokumen.')
            ->modalSubmitActionLabel('Ya, Bubuhi Stempel')
            ->action(function (?Document $record = null) use ($fixedRecord): void {
                $target = $fixedRecord ?? $record;
                if (! $target) {
                    return;
                }

                self::runSafe($target, function (Document $doc): void {
                    if (! (auth()->user()?->can('update', $doc) ?? false)) {
                        throw new AuthorizationException('Anda tidak memiliki wewenang untuk membubuhi stempel pada dokumen ini.');
                    }

                    $stampedFile = app(DocumentStampingService::class)->stamp($doc);
                    if ($stampedFile && $stampedFile->stamped_file_path) {
                        Notification::make()
                            ->success()
                            ->title('Stempel Berhasil Dibubuhkan')
                            ->body('Berkas fisik dokumen kini telah resmi berstempel dan memiliki QR Code keabsahan.')
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('Stempel Dilewati')
                            ->body('Dokumen tidak memiliki berkas fisik atau diatur tanpa stempel visual.')
                            ->send();
                    }
                });
            })
            ->visible(function (?Document $record = null) use ($fixedRecord): bool {
                $target = $fixedRecord ?? $record;
                if (! $target) {
                    return false;
                }

                return $target->status === DocumentStatus::Approved
                    && (auth()->user()?->can('update', $target) ?? false)
                    && self::hasPhysicalFile($target);
            });
    }

    public static function makeUploadFinalScanAction(?Document $fixedRecord = null): Action
    {
        return Action::make('uploadFinalScan')
            ->label('Unggah Scan TTD Basah')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->modalHeading('Unggah Berkas Scan Final (TTD Basah & Cap)')
            ->modalDescription('Unggah berkas PDF hasil pemindaian dokumen fisik yang sudah bertanda tangan basah pimpinan dan berstempel/cap resmi kampus.')
            ->modalSubmitActionLabel('Arsipkan & Sahkan Dokumen')
            ->form([
                Placeholder::make('wet_signature_summary')
                    ->label('Informasi Penomoran & Unduhan')
                    ->content(function (?Document $record = null) use ($fixedRecord): HtmlString {
                        $target = $fixedRecord ?? $record;
                        $number = e($target?->document_number ?: '-');
                        $downloadUrl = $target ? e(route('admin.documents.download', $target)) : '#';

                        return new HtmlString(
                            '<div style="padding: 0.75rem 1rem; border-radius: 0.5rem; background: rgba(37, 99, 235, 0.08); border: 1px solid rgba(37, 99, 235, 0.25); font-size: 0.8125rem;">'
                            .'<div><strong>Nomor Resmi:</strong> <code style="font-family: ui-monospace, monospace; font-weight: 700;">'.$number.'</code></div>'
                            .'<div style="margin-top: 0.35rem;">Belum dicetak? <a href="'.$downloadUrl.'" target="_blank" style="text-decoration: underline; font-weight: 600;">Unduh Berkas Bernomor untuk Dicetak</a></div>'
                            .'</div>'
                        );
                    }),

                FileUpload::make('scan_file')
                    ->label('Berkas Scan PDF Final (Ber-TTD Basah & Cap)')
                    ->helperText('Wajib format PDF (.pdf), maks. 25 MB. Berkas ini akan menjadi arsip resmi terkunci dan acuan verifikasi SHA-256 publik.')
                    ->disk('private')
                    ->directory(function (?Document $record = null) use ($fixedRecord): string {
                        $target = $fixedRecord ?? $record;

                        return app(DocumentNamingService::class)->generateDirectory($target?->unit_id);
                    })
                    ->acceptedFileTypes(['application/pdf'])
                    ->rules(['mimes:pdf', new PdfDocumentFileRule])
                    ->maxSize(25600)
                    ->storeFileNamesIn('scan_file_name')
                    ->required(),

                Textarea::make('note')
                    ->label('Catatan Pengarsipan (Opsional)')
                    ->placeholder('Contoh: Sudah ditandatangani basah Rektor dan dicap resmi Humas...')
                    ->rows(2),
            ])
            ->action(function (array $data, ?Document $record = null) use ($fixedRecord): void {
                $target = $fixedRecord ?? $record;
                $user = auth()->user();

                if (! $target || ! $user) {
                    return;
                }

                self::runSafe($target, function (Document $doc) use ($data, $user): void {
                    $rawPath = $data['scan_file'] ?? null;
                    $storedPath = is_array($rawPath) ? Arr::first($rawPath) : $rawPath;

                    if (! is_string($storedPath) || $storedPath === '') {
                        throw new InvalidArgumentException('Berkas PDF scan final wajib diunggah.');
                    }

                    $originalName = is_string($data['scan_file_name'] ?? null) ? $data['scan_file_name'] : null;
                    $note = is_string($data['note'] ?? null) ? $data['note'] : null;

                    $finalDoc = app(DocumentApprovalService::class)->uploadFinalSignedScan(
                        $doc,
                        $user,
                        $storedPath,
                        $originalName,
                        $note
                    );

                    Notification::make()
                        ->success()
                        ->title('Scan Final Diarsipkan & Dokumen Sah')
                        ->body("Berkas TTD basah & cap untuk nomor {$finalDoc->document_number} telah tersimpan dan status resmi Disahkan.")
                        ->send();
                });
            })
            ->visible(function (?Document $record = null) use ($fixedRecord): bool {
                $target = $fixedRecord ?? $record;
                $user = auth()->user();

                if (! $target || ! $user) {
                    return false;
                }

                return app(DocumentApprovalService::class)->canUploadFinalScan($user, $target);
            });
    }

    public static function makeReturnFromWaitingSignatureAction(?Document $fixedRecord = null): Action
    {
        return Action::make('returnToRevisionFromSignature')
            ->label('Kembalikan ke Revisi')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->modalHeading('Kembalikan ke Perlu Revisi?')
            ->modalDescription('Gunakan tindakan ini jika penandatanganan fisik dibatalkan atau ditemukan kesalahan redaksi sebelum diarsipkan.')
            ->modalSubmitActionLabel('Kembalikan ke Revisi')
            ->form([
                Textarea::make('reason')
                    ->label('Alasan Pengembalian Wajib')
                    ->placeholder('Jelaskan alasan pembatalan/revisi...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, ?Document $record = null) use ($fixedRecord): void {
                $target = $fixedRecord ?? $record;
                $user = auth()->user();

                if (! $target || ! $user) {
                    return;
                }

                self::runSafe($target, function (Document $doc) use ($data, $user): void {
                    app(DocumentApprovalService::class)->returnWaitingSignatureToRevision(
                        $doc,
                        $user,
                        (string) ($data['reason'] ?? '')
                    );

                    Notification::make()
                        ->warning()
                        ->title('Dikembalikan ke Perlu Revisi')
                        ->body('Status dokumen diubah menjadi Perlu Revisi dan pengaju telah diberitahu.')
                        ->send();
                });
            })
            ->visible(function (?Document $record = null) use ($fixedRecord): bool {
                $target = $fixedRecord ?? $record;
                $user = auth()->user();

                if (! $target || ! $user) {
                    return false;
                }

                return app(DocumentApprovalService::class)->canReturnWaitingSignatureToRevision($user, $target);
            });
    }

    private static function hasPhysicalFile(Document $record): bool
    {
        return $record->primaryFile !== null || (bool) ($record->files_exists ?? $record->files()->exists());
    }

    private static function runSafe(Document $record, Closure $callback): void
    {
        try {
            $callback($record);
        } catch (AuthorizationException $e) {
            $record->refresh();
            Notification::make()
                ->danger()
                ->title('Akses Ditolak')
                ->body($e->getMessage())
                ->send();
        } catch (InvalidArgumentException $e) {
            $record->refresh();
            Notification::make()
                ->warning()
                ->title('Tindakan Tidak Valid')
                ->body($e->getMessage())
                ->send();
        } catch (Throwable $e) {
            report($e);
            $record->refresh();
            Notification::make()
                ->danger()
                ->title('Gagal Memproses Dokumen')
                ->body('Terjadi kesalahan sistem saat memproses status dokumen. Silakan muat ulang halaman dan coba lagi.')
                ->send();
        }
    }
}
