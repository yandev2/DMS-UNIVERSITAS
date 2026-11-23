<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Filament\Resources\Documents\DocumentResource;
use App\Jobs\GenerateDocumentExportJob;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\DocumentCategory;
use App\Models\Unit;
use App\Services\DocumentApprovalService;
use App\Services\DocumentQueuedExportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocumentResource::trashAction(),
            $this->makeExportExcelAction(),
            CreateAction::make(),
        ];
    }

    protected function makeExportExcelAction(): Action
    {
        return Action::make('exportExcel')
            ->label('Ekspor Excel')
            ->icon(new HtmlString('<svg class="fi-icon fi-size-md" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/></svg>'))
            ->color('gray')
            ->extraAttributes(['class' => 'ui-header-action-export'])
            ->modalHeading('Ekspor Data Dokumen Arsip ke Excel (.xlsx)')
            ->modalDescription('Tentukan rentang tanggal terbit (wajib) dan filter opsional di bawah ini. Proses penyusunan Excel dijalankan di latar belakang dan tautan unduhan akan dikirim ke notifikasi lonceng Anda.')
            ->modalSubmitActionLabel('Ekspor di Latar Belakang')
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (): bool => auth()->user()?->can('document.export_excel') ?? false)
            ->authorize(fn (): bool => auth()->user()?->can('document.export_excel') ?? false)
            ->form([
                Section::make('Rentang Tanggal Terbit (Wajib)')
                    ->description('Wajib diisi untuk membatasi periode naskah dinas yang diekspor.')
                    ->schema([
                        DatePicker::make('issued_from')
                            ->label('Terbit Dari Tanggal')
                            ->required()
                            ->native(false),

                        DatePicker::make('issued_until')
                            ->label('Terbit Sampai Tanggal')
                            ->required()
                            ->afterOrEqual('issued_from')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Filter Tambahan (Opsional)')
                    ->description('Kosongkan bila ingin menyertakan seluruh kategori, unit, dan status sesuai hak akses Anda.')
                    ->schema([
                        Select::make('unit_id')
                            ->label('Unit Kerja / Program Studi')
                            ->options(function (): array {
                                $user = auth()->user();
                                $query = Unit::query()->orderBy('name');
                                $accessibleUnitIds = $user?->accessibleUnitIds();
                                if ($accessibleUnitIds !== null) {
                                    $query->whereIn('id', $accessibleUnitIds);
                                }

                                return $query->pluck('name', 'id')->all();
                            })
                            ->placeholder('Semua Unit Kerja / Prodi')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->native(false),

                        Select::make('document_category_id')
                            ->label('Kategori Dokumen')
                            ->options(fn (): array => DocumentCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Semua Kategori Dokumen')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->native(false),

                        Select::make('academic_year_id')
                            ->label('Tahun Akademik')
                            ->options(fn (): array => AcademicYear::query()->orderByDesc('name')->pluck('name', 'id')->all())
                            ->placeholder('Semua Tahun Akademik')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->native(false),

                        Select::make('status')
                            ->label('Status Dokumen')
                            ->options(DocumentStatus::class)
                            ->placeholder('Semua Status')
                            ->multiple()
                            ->nullable()
                            ->native(false),

                        Select::make('visibility')
                            ->label('Tingkat Aksesibilitas')
                            ->options(VisibilityLevel::class)
                            ->placeholder('Semua Tingkat Akses')
                            ->nullable()
                            ->native(false),

                        Select::make('is_borang')
                            ->label('Bukti Borang Akreditasi')
                            ->options([
                                '1' => 'Hanya Bukti Borang',
                                '0' => 'Bukan Bukti Borang',
                            ])
                            ->placeholder('Semua Dokumen')
                            ->nullable()
                            ->native(false),

                        Select::make('has_expiry')
                            ->label('Sifat Masa Berlaku')
                            ->options([
                                '1' => 'Bermasa Berlaku (Ada Kedaluwarsa)',
                                '0' => 'Dokumen Tetap / Permanen',
                            ])
                            ->placeholder('Semua Jenis Dokumen')
                            ->nullable()
                            ->native(false),
                    ])
                    ->columns(['sm' => 2, 'lg' => 3]),
            ])
            ->action(function (array $data): void {
                $user = auth()->user();
                if (! $user || ! $user->can('document.export_excel')) {
                    return;
                }

                $payload = [
                    'issued_from' => $data['issued_from'] ?? null,
                    'issued_until' => $data['issued_until'] ?? null,
                    'unit_id' => filled($data['unit_id'] ?? null) ? (int) $data['unit_id'] : null,
                    'document_category_id' => filled($data['document_category_id'] ?? null) ? (int) $data['document_category_id'] : null,
                    'academic_year_id' => filled($data['academic_year_id'] ?? null) ? (int) $data['academic_year_id'] : null,
                    'status' => array_values(array_filter((array) ($data['status'] ?? []), fn (mixed $v): bool => filled($v))),
                    'visibility' => filled($data['visibility'] ?? null) ? (string) $data['visibility'] : null,
                    'is_borang' => $data['is_borang'] ?? null,
                    'has_expiry' => $data['has_expiry'] ?? null,
                ];

                $exportService = app(DocumentQueuedExportService::class);
                $totalCount = $exportService->buildFilteredQuery($payload, $user)->count();

                if ($totalCount === 0) {
                    Notification::make()
                        ->warning()
                        ->title('Tidak Ada Dokumen yang Cocok')
                        ->body('Tidak ditemukan dokumen pada rentang tanggal terbit dan filter yang dipilih.')
                        ->send();

                    return;
                }

                $filename = 'Rekapitulasi-Dokumen-Arsip-'.now()->format('Ymd-His').'.xlsx';

                $export = BulkExport::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'module_type' => 'documents_excel',
                    'record_ids' => $payload,
                    'total_files' => $totalCount,
                    'zip_file_name' => $filename,
                    'disk' => 'private',
                    'status' => 'pending',
                    'expires_at' => now()->addHours(24),
                ]);

                GenerateDocumentExportJob::dispatch($export);

                Notification::make()
                    ->info()
                    ->title('Ekspor Excel Dokumen Diproses di Latar Belakang')
                    ->body("Permintaan ekspor berisi {$totalCount} dokumen sedang diproses di antrean. Anda akan menerima notifikasi lonceng berisi tautan unduhan (.xlsx) setelah selesai.")
                    ->send();
            });
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $approvalService = app(DocumentApprovalService::class);
        $totalCount = $user
            ? DocumentResource::getEloquentQuery()->count()
            : 0;
        $pendingCount = $user ? $approvalService->countPendingApprovalsForUser($user) : 0;
        $waitingSignatureCount = $user
            ? DocumentResource::getEloquentQuery()
                ->where('documents.status', DocumentStatus::WaitingSignature)
                ->count()
            : 0;

        return [
            'all' => Tab::make('Semua')
                ->icon('heroicon-o-square-3-stack-3d')
                ->badge($totalCount > 0 ? (string) $totalCount : null),

            'my_pending_approval' => Tab::make('Persetujuan')
                ->icon('heroicon-o-clock')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(function (Builder $query) use ($user, $approvalService): Builder {
                    if (! $user) {
                        return $query->whereRaw('1 = 0');
                    }

                    $pendingSubquery = $approvalService->getPendingApprovalsQueryForUser($user)->select('documents.id');

                    return $query->whereIn('documents.id', $pendingSubquery);
                }),

            'waiting_signature' => Tab::make('TTD Basah')
                ->icon('heroicon-o-pencil')
                ->badge($waitingSignatureCount > 0 ? (string) $waitingSignatureCount : null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('documents.status', DocumentStatus::WaitingSignature)),

            'approved' => Tab::make('Disahkan')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('documents.status', DocumentStatus::Approved)),

            'draft_revision' => Tab::make('Draf & Revisi')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('documents.status', [
                    DocumentStatus::Draft,
                    DocumentStatus::Revision,
                ])),

            'archived_expired' => Tab::make('Arsip')
                ->icon('heroicon-o-archive-box')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('documents.status', [
                    DocumentStatus::Archived,
                    DocumentStatus::Expired,
                ])),
        ];
    }
}
