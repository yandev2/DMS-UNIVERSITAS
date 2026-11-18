<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\DeletionGuard;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Services\DocumentApprovalService;
use App\Services\DocumentStampingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * @return array<Action|ActionGroup>
     */
    public function getCachedHeaderActions(): array
    {
        return array_values(array_filter(
            $this->cachedHeaderActions,
            fn ($action): bool => ($action instanceof Action ? $action->getName() : '') !== 'preview',
        ));
    }

    protected function getHeaderActions(): array
    {
        /** @var Document $record */
        $record = $this->record;

        return [
            Action::make('preview')
                ->label('Pratinjau Berkas')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->modalHeading("Pratinjau: {$record->title}")
                ->modalWidth(Width::SixExtraLarge)
                ->modalContent(fn (): View => view('filament.components.document-preview-modal', [
                    'streamUrl' => route('admin.documents.stream', $record),
                    'downloadUrl' => route('admin.documents.download', $record),
                    'document' => $record,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->visible(fn (): bool => auth()->user()?->can('view', $record) && ($record->primaryFile !== null || $record->files()->exists())),

            Action::make('download')
                ->label('Unduh Berkas')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('admin.documents.download', $record))
                ->visible(fn (): bool => auth()->user()?->can('downloadPrivate', $record) && ($record->primaryFile !== null || $record->files()->exists())),

            Action::make('approve')
                ->label('Setujui Dokumen')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->form([
                    Textarea::make('note')
                        ->label('Catatan Persetujuan (Opsional)')
                        ->placeholder('Contoh: Disetujui tanpa catatan...')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
                    app(DocumentApprovalService::class)->approve($record, auth()->user(), $data['note'] ?? null);
                    $record->refresh();
                    Notification::make()
                        ->success()
                        ->title('Dokumen Berhasil Disetujui')
                        ->body($record->status === DocumentStatus::Approved
                            ? "Dokumen resmi disahkan! Nomor resmi {$record->document_number} telah terbit."
                            : 'Persetujuan Anda telah dicatat dan diteruskan ke tahapan berikutnya.')
                        ->send();
                })
                ->visible(fn (): bool => app(DocumentApprovalService::class)->canUserApprove(auth()->user(), $record)),

            Action::make('requestRevision')
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
                ->action(function (array $data) use ($record): void {
                    app(DocumentApprovalService::class)->requestRevision($record, auth()->user(), $data['note']);
                    Notification::make()
                        ->warning()
                        ->title('Permintaan Revisi Dikirim')
                        ->body('Catatan perbaikan telah dicatat dan status dokumen berubah menjadi Revisi.')
                        ->send();
                })
                ->visible(fn (): bool => app(DocumentApprovalService::class)->canUserApprove(auth()->user(), $record)),

            Action::make('reject')
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
                ->action(function (array $data) use ($record): void {
                    app(DocumentApprovalService::class)->reject($record, auth()->user(), $data['note']);
                    Notification::make()
                        ->danger()
                        ->title('Dokumen Ditolak')
                        ->body('Keputusan penolakan telah dicatat secara resmi.')
                        ->send();
                })
                ->visible(fn (): bool => app(DocumentApprovalService::class)->canUserApprove(auth()->user(), $record)),

            Action::make('resubmit')
                ->label('Ajukan Kembali')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Ajukan Kembali Dokumen?')
                ->modalDescription('Dokumen yang telah direvisi akan diajukan kembali ke alur persetujuan tingkat 1.')
                ->modalSubmitActionLabel('Ya, Ajukan Kembali')
                ->action(function () use ($record): void {
                    app(DocumentApprovalService::class)->resubmit($record);
                    Notification::make()
                        ->success()
                        ->title('Dokumen Berhasil Diajukan Kembali')
                        ->body('Dokumen telah masuk kembali ke antrean persetujuan tingkat 1.')
                        ->send();
                })
                ->visible(fn (): bool => $record->status === DocumentStatus::Revision && auth()->user()?->can('update', $record)),

            Action::make('stamp')
                ->label(fn (): string => $record->primaryFile?->stamped_file_path ? 'Perbarui Stempel' : 'Bubuhi Stempel')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Bubuhi Stempel Resmi & QR Code?')
                ->modalDescription('Sistem akan membubuhi kotak penomoran naskah dinas, tanggal pengesahan, pejabat penandatangan, dan stempel QR-Code verifikasi ke berkas dokumen.')
                ->modalSubmitActionLabel('Ya, Bubuhi Stempel')
                ->action(function () use ($record): void {
                    $stampedFile = app(DocumentStampingService::class)->stamp($record);
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
                })
                ->visible(fn (): bool => $record->status === DocumentStatus::Approved && auth()->user()?->can('update', $record) && ($record->primaryFile !== null || $record->files()->exists())),

            EditAction::make(),
            DeletionGuard::preventIfRestricted(DeleteAction::make()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var Document $record */
        $record = $this->getRecord();

        return $schema
            ->components([
                SchemaView::make('filament.components.document-approval-stepper')
                    ->viewData([
                        'document' => $record,
                    ]),

                Tabs::make('document_details_tabs')
                    ->tabs([
                        Tab::make('Informasi Naskah Dinas & Berkas')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                SchemaView::make('filament.components.document-metadata-view')
                                    ->viewData([
                                        'document' => $record,
                                    ]),
                            ]),

                        Tab::make('Riwayat Alur Persetujuan')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->badge(fn (): ?int => $record->approvals()->count() ?: null)
                            ->schema([
                                SchemaView::make('filament.components.document-approval-history')
                                    ->viewData([
                                        'document' => $record,
                                    ]),
                            ]),

                        Tab::make('Catatan Keamanan & Aktivitas')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->badge(function () use ($record): ?int {
                                $count = ActivityLog::where('subject_type', Document::class)
                                    ->where('subject_id', $record->id)
                                    ->count();

                                return $count ?: null;
                            })
                            ->schema([
                                SchemaView::make('filament.components.document-activity-log')
                                    ->viewData([
                                        'document' => $record,
                                    ]),
                            ]),

                        Tab::make('Pengingat Masa Berlaku')
                            ->icon(Heroicon::OutlinedBellAlert)
                            ->visible(fn (): bool => (bool) $record->has_expiry)
                            ->badge(fn (): ?int => $record->reminderLogs()->count() ?: null)
                            ->schema([
                                SchemaView::make('filament.components.document-reminder-history')
                                    ->viewData([
                                        'document' => $record,
                                    ]),
                            ]),
                    ]),

                $this->getRelationManagersContentComponent(),
            ]);
    }
}
