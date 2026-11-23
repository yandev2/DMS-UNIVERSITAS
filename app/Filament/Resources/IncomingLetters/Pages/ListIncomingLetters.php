<?php

declare(strict_types=1);

namespace App\Filament\Resources\IncomingLetters\Pages;

use App\Enums\IncomingLetterStatus;
use App\Enums\LetterPriority;
use App\Filament\Resources\IncomingLetters\IncomingLetterResource;
use App\Jobs\GenerateIncomingLetterExportJob;
use App\Models\AcademicYear;
use App\Models\BulkExport;
use App\Models\Unit;
use App\Services\IncomingLetterQueuedExportService;
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

class ListIncomingLetters extends ListRecords
{
    protected static string $resource = IncomingLetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeExportExcelAction(),
            CreateAction::make()
                ->label('Registrasi Surat Masuk Baru')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => IncomingLetterResource::canCreate()),
        ];
    }

    protected function makeExportExcelAction(): Action
    {
        return Action::make('exportExcel')
            ->label('Ekspor Excel')
            ->icon(new HtmlString('<svg class="fi-icon fi-size-md" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/></svg>'))
            ->color('gray')
            ->extraAttributes(['class' => 'ui-header-action-export'])
            ->modalHeading('Ekspor Data Surat Masuk ke Excel (.xlsx)')
            ->modalDescription('Tentukan rentang tanggal diterima (wajib) dan filter opsional di bawah ini. Proses penyusunan Excel dijalankan di latar belakang dan tautan unduhan akan dikirim ke notifikasi lonceng Anda.')
            ->modalSubmitActionLabel('Ekspor di Latar Belakang')
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (): bool => auth()->user()?->can('incoming_letter.export_excel') ?? false)
            ->authorize(fn (): bool => auth()->user()?->can('incoming_letter.export_excel') ?? false)
            ->form([
                Section::make('Rentang Tanggal Diterima (Wajib)')
                    ->description('Wajib diisi untuk membatasi periode surat masuk yang diekspor.')
                    ->schema([
                        DatePicker::make('received_from')
                            ->label('Diterima Dari Tanggal')
                            ->required()
                            ->native(false),

                        DatePicker::make('received_until')
                            ->label('Diterima Sampai Tanggal')
                            ->required()
                            ->afterOrEqual('received_from')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Filter Tambahan (Opsional)')
                    ->description('Kosongkan bila ingin menyertakan seluruh unit tujuan, sifat, dan status sesuai hak akses Anda.')
                    ->schema([
                        Select::make('unit_id')
                            ->label('Unit Tujuan')
                            ->options(fn (): array => Unit::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Semua Unit Tujuan')
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
                            ->label('Status Distribusi')
                            ->options(IncomingLetterStatus::class)
                            ->placeholder('Semua Status Distribusi')
                            ->multiple()
                            ->nullable()
                            ->native(false),

                        Select::make('priority')
                            ->label('Sifat Surat')
                            ->options(LetterPriority::class)
                            ->placeholder('Semua Sifat Surat')
                            ->multiple()
                            ->nullable()
                            ->native(false),
                    ])
                    ->columns(['sm' => 2, 'lg' => 2]),
            ])
            ->action(function (array $data): void {
                $user = auth()->user();
                if (! $user || ! $user->can('incoming_letter.export_excel')) {
                    return;
                }

                $payload = [
                    'received_from' => $data['received_from'] ?? null,
                    'received_until' => $data['received_until'] ?? null,
                    'unit_id' => filled($data['unit_id'] ?? null) ? (int) $data['unit_id'] : null,
                    'academic_year_id' => filled($data['academic_year_id'] ?? null) ? (int) $data['academic_year_id'] : null,
                    'status' => array_values(array_filter((array) ($data['status'] ?? []), fn (mixed $v): bool => filled($v))),
                    'priority' => array_values(array_filter((array) ($data['priority'] ?? []), fn (mixed $v): bool => filled($v))),
                ];

                $exportService = app(IncomingLetterQueuedExportService::class);
                $totalCount = $exportService->buildFilteredQuery($payload, $user)->count();

                if ($totalCount === 0) {
                    Notification::make()
                        ->warning()
                        ->title('Tidak Ada Surat Masuk yang Cocok')
                        ->body('Tidak ditemukan surat masuk pada rentang tanggal diterima dan filter yang dipilih.')
                        ->send();

                    return;
                }

                $filename = 'Rekapitulasi-Surat-Masuk-'.now()->format('Ymd-His').'.xlsx';

                $export = BulkExport::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'module_type' => 'incoming_letters_excel',
                    'record_ids' => $payload,
                    'total_files' => $totalCount,
                    'zip_file_name' => $filename,
                    'disk' => 'private',
                    'status' => 'pending',
                    'expires_at' => now()->addHours(24),
                ]);

                GenerateIncomingLetterExportJob::dispatch($export);

                Notification::make()
                    ->info()
                    ->title('Ekspor Excel Surat Masuk Diproses di Latar Belakang')
                    ->body("Permintaan ekspor berisi {$totalCount} surat masuk sedang diproses di antrean. Anda akan menerima notifikasi lonceng berisi tautan unduhan (.xlsx) setelah selesai.")
                    ->send();
            });
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        return [
            'all' => Tab::make('Semua')
                ->icon('heroicon-o-square-3-stack-3d'),

            'my_turn' => Tab::make('Giliran Saya')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->pendingTurnForUser($user)),

            'in_progress' => Tab::make('Distribusi')
                ->icon('heroicon-o-paper-airplane')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', IncomingLetterStatus::InProgress)),

            'completed' => Tab::make('Selesai')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', IncomingLetterStatus::Completed)),
        ];
    }
}
