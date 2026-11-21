<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingLetters\Pages;

use App\Enums\OutgoingLetterStatus;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Jobs\GenerateOutgoingLetterExportJob;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\Unit;
use App\Services\OutgoingLetterQueuedExportService;
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
use Illuminate\Support\Str;

class ListOutgoingLetters extends ListRecords
{
    protected static string $resource = OutgoingLetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeExportExcelAction(),
            CreateAction::make()
                ->label('Buat Surat Keluar Baru')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function makeExportExcelAction(): Action
    {
        return Action::make('exportExcel')
            ->label('Ekspor Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->modalHeading('Ekspor Data Surat Keluar ke Excel (.xlsx)')
            ->modalDescription('Tentukan rentang tanggal surat (wajib) dan filter opsional di bawah ini. Proses penyusunan Excel dijalankan di latar belakang dan tautan unduhan akan dikirim ke notifikasi lonceng Anda.')
            ->modalSubmitActionLabel('Ekspor di Latar Belakang')
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (): bool => auth()->user()?->can('outgoing_letter.export_excel') ?? false)
            ->authorize(fn (): bool => auth()->user()?->can('outgoing_letter.export_excel') ?? false)
            ->form([
                Section::make('Rentang Tanggal Surat (Wajib)')
                    ->description('Wajib diisi untuk membatasi periode surat keluar yang diekspor.')
                    ->schema([
                        DatePicker::make('letter_from')
                            ->label('Tanggal Surat Dari')
                            ->required()
                            ->native(false),

                        DatePicker::make('letter_until')
                            ->label('Tanggal Surat Sampai')
                            ->required()
                            ->afterOrEqual('letter_from')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Filter Tambahan (Opsional)')
                    ->description('Kosongkan bila ingin menyertakan seluruh unit pengaju dan status penomoran sesuai hak akses Anda.')
                    ->schema([
                        Select::make('unit_id')
                            ->label('Unit Pengaju')
                            ->options(function (): array {
                                $user = auth()->user();
                                $query = Unit::query()->orderBy('name');
                                if ($user && ! $user->can('letter.manage_humas') && ! $user->can('document.bypass_unit_scope')) {
                                    $accessibleUnitIds = $user->accessibleUnitIds();
                                    if ($accessibleUnitIds !== null) {
                                        $query->whereIn('id', $accessibleUnitIds);
                                    }
                                }

                                return $query->pluck('name', 'id')->all();
                            })
                            ->placeholder('Semua Unit Pengaju')
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
                            ->label('Status Penomoran')
                            ->options(OutgoingLetterStatus::class)
                            ->placeholder('Semua Status Penomoran')
                            ->multiple()
                            ->nullable()
                            ->native(false),

                        Select::make('requires_wet_signature')
                            ->label('Sifat TTD Basah')
                            ->options([
                                '1' => 'Wajib TTD Basah & Scan',
                                '0' => 'Tanpa TTD Basah',
                            ])
                            ->placeholder('Semua Jenis Surat Keluar')
                            ->nullable()
                            ->native(false),
                    ])
                    ->columns(['sm' => 2, 'lg' => 2]),
            ])
            ->action(function (array $data): void {
                $user = auth()->user();
                if (! $user || ! $user->can('outgoing_letter.export_excel')) {
                    return;
                }

                $payload = [
                    'letter_from' => $data['letter_from'] ?? null,
                    'letter_until' => $data['letter_until'] ?? null,
                    'unit_id' => filled($data['unit_id'] ?? null) ? (int) $data['unit_id'] : null,
                    'academic_year_id' => filled($data['academic_year_id'] ?? null) ? (int) $data['academic_year_id'] : null,
                    'status' => array_values(array_filter((array) ($data['status'] ?? []), fn (mixed $v): bool => filled($v))),
                    'requires_wet_signature' => $data['requires_wet_signature'] ?? null,
                ];

                $exportService = app(OutgoingLetterQueuedExportService::class);
                $totalCount = $exportService->buildFilteredQuery($payload, $user)->count();

                if ($totalCount === 0) {
                    Notification::make()
                        ->warning()
                        ->title('Tidak Ada Surat Keluar yang Cocok')
                        ->body('Tidak ditemukan surat keluar pada rentang tanggal surat dan filter yang dipilih.')
                        ->send();

                    return;
                }

                $filename = 'Rekapitulasi-Surat-Keluar-'.now()->format('Ymd-His').'.xlsx';

                $export = BulkExport::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'module_type' => 'outgoing_letters_excel',
                    'record_ids' => $payload,
                    'total_files' => $totalCount,
                    'zip_file_name' => $filename,
                    'disk' => 'private',
                    'status' => 'pending',
                    'expires_at' => now()->addHours(24),
                ]);

                GenerateOutgoingLetterExportJob::dispatch($export);

                Notification::make()
                    ->info()
                    ->title('Ekspor Excel Surat Keluar Diproses di Latar Belakang')
                    ->body("Permintaan ekspor berisi {$totalCount} surat keluar sedang diproses di antrean. Anda akan menerima notifikasi lonceng berisi tautan unduhan (.xlsx) setelah selesai.")
                    ->send();
            });
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Surat Keluar'),

            'pending_humas' => Tab::make('Menunggu Tinjauan Humas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OutgoingLetterStatus::PendingHumas)),

            'waiting_signature' => Tab::make('Menunggu TTD Basah')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OutgoingLetterStatus::WaitingSignature)),

            'published' => Tab::make('Nomor Terbit (Selesai)')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', OutgoingLetterStatus::Published)),

            'revision_or_rejected' => Tab::make('Perlu Revisi / Ditolak')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    OutgoingLetterStatus::NeedsRevision,
                    OutgoingLetterStatus::Rejected,
                ])),
        ];
    }
}
