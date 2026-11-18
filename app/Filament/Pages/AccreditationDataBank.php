<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\AccreditationInstrument;
use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Jobs\GenerateBorangExportJob;
use App\Models\AcademicYear;
use App\Models\AccreditationCriterion;
use App\Models\BulkExport;
use App\Models\Document;
use App\Models\Unit;
use App\Services\BorangExportService;
use App\Services\BorangZipExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class AccreditationDataBank extends Page implements HasTable
{
    use InteractsWithTable;

    public const HEAVY_EXCEL_THRESHOLD = 250;

    public const HEAVY_ZIP_THRESHOLD = 30;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Borang Akreditasi';

    protected static ?string $navigationLabel = 'Bank Data Borang';

    protected static ?string $title = 'Bank Data Borang Akreditasi (BAN-PT / LAM)';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.accreditation-data-bank';

    /** @var array<string, mixed>|null */
    protected ?array $memoizedCriteriaStatistics = null;

    protected ?string $memoizedStatisticsKey = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->can('borang.export_excel')
            || $user->can('borang.download_bulk_zip')
            || $user->can('borang.configure_assessor');
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginationMode(PaginationMode::Simple)
            ->paginated([5, 10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->searchDebounce('500ms')
            ->query(function (): Builder {
                $user = auth()->user();

                $query = Document::query()
                    ->where('is_borang', true)
                    ->where('status', DocumentStatus::Approved)
                    ->with(['unit', 'category', 'academicYear', 'accreditationCriteria', 'primaryFile']);

                // Unit Tenancy Scope
                if ($user && ! $user->can('document.bypass_unit_scope')) {
                    $query->where('unit_id', $user->unit_id);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('accreditationCriteria.code')
                    ->label('Kriteria')
                    ->badge()
                    ->color('primary')
                    ->separator(', '),

                TextColumn::make('document_number')
                    ->label('Nomor Dokumen')
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Nomor dokumen disalin ke clipboard'),

                TextColumn::make('title')
                    ->label('Judul Naskah Bukti')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2),

                TextColumn::make('unit.name')
                    ->label('Program Studi / Unit')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('academicYear.name')
                    ->label('Tahun Akademik')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('issued_date')
                    ->label('Tanggal Sah')
                    ->date('d M Y')
                    ->sortable(),

                IconColumn::make('unit.is_assessor_access_active')
                    ->label('Akses Asesor')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-open')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (Document $record): string => $record->unit?->isAssessorAccessOpen()
                        ? 'Akses Terbuka untuk Asesor Eksternal'
                        : 'Akses Terkunci / Ditutup LPM'
                    ),
            ])
            ->defaultSort('issued_date', 'desc')
            ->filters([
                SelectFilter::make('unit_id')
                    ->label('Program Studi / Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('instrument_source')
                    ->label('Instrumen Akreditasi')
                    ->options(function (): array {
                        $presets = AccreditationInstrument::options();
                        $db = AccreditationCriterion::query()
                            ->whereNotNull('instrument_source')
                            ->distinct()
                            ->pluck('instrument_source', 'instrument_source')
                            ->toArray();

                        return array_merge($presets, $db);
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('accreditationCriteria', function (Builder $q) use ($data): void {
                            $q->where('instrument_source', $data['value']);
                        });
                    })
                    ->native(false),

                SelectFilter::make('academic_year_id')
                    ->label('Tahun Akademik')
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('accreditationCriteria')
                    ->label('Kriteria Borang')
                    ->relationship('accreditationCriteria', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns([
                'sm' => 1,
                'md' => 4,
            ])
            ->filtersFormWidth(Width::FourExtraLarge)
            ->recordActions([
                ActionGroup::make([
                    Action::make('copyAssessorUrl')
                        ->label('Salin Tautan Asesor')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('success')
                        ->extraAttributes(fn (Document $record): array => [
                            'x-on:click' => 'window.copyBorangUrl ? window.copyBorangUrl("'.$record->getAssessorUrl().'") : ((function(t){var ta=document.createElement("textarea");ta.value=t;ta.style.position="fixed";ta.style.left="-999999px";document.body.appendChild(ta);ta.focus();ta.select();try{document.execCommand("copy");}catch(e){}document.body.removeChild(ta);})("'.$record->getAssessorUrl().'"))',
                        ])
                        ->action(function (Document $record): void {
                            Notification::make()
                                ->success()
                                ->title('Tautan Asesor Berhasil Disalin')
                                ->body($record->getAssessorUrl())
                                ->send();
                        }),

                    Action::make('viewDetail')
                        ->label('Detail Dokumen')
                        ->icon('heroicon-o-eye')
                        ->color('warning')
                        ->url(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record])),

                    Action::make('preview')
                        ->label('Pratinjau')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->color('info')
                        ->modalHeading(fn (Document $record): string => "Pratinjau Bukti Borang: {$record->title}")
                        ->modalWidth(Width::SixExtraLarge)
                        ->modalContent(fn (Document $record): View => view('filament.components.document-preview-modal', [
                            'streamUrl' => route('admin.documents.stream', $record),
                            'downloadUrl' => route('admin.documents.download', $record),
                            'document' => $record,
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup'),

                    Action::make('download')
                        ->label('Unduh Berkas')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn (Document $record): string => route('admin.documents.download', $record))
                        ->openUrlInNewTab(),
                ])
                    ->tooltip('Aksi Rekaman')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->bulkActions([
                BulkAction::make('downloadSelectedZip')
                    ->label('Unduh Bundel Terpilih (.ZIP)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('primary')
                    ->action(function (Collection $records): ?StreamedResponse {
                        if ($records->count() > self::HEAVY_ZIP_THRESHOLD) {
                            $this->dispatchQueuedBorangExport(
                                moduleType: 'borang_zip',
                                filename: 'Bundel-Dokumen-Bukti-Borang-Terpilih-'.now()->format('Ymd-His').'.zip',
                                totalDocuments: $records->count(),
                                documentIds: $records->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                            );

                            return null;
                        }

                        return app(BorangZipExportService::class)->downloadZipResponse($records);
                    })
                    ->visible(fn (): bool => auth()->user()?->can('borang.download_bulk_zip') ?? false),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configureAssessorAccess')
                ->label('Atur Akses Asesor')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->extraAttributes(['class' => 'borang-action-configure'])
                ->modalHeading('Konfigurasi Masa Asesmen & Akses Asesor')
                ->modalDescription('Atur pembukaan akses publik berkas bukti borang untuk Asesor BAN-PT / LAM.')
                ->modalWidth(Width::Large)
                ->form([
                    Select::make('unit_id')
                        ->label('Program Studi / Unit Kerja')
                        ->options(function (): array {
                            $user = auth()->user();
                            $query = Unit::query()->orderBy('name');
                            if ($user && ! $user->can('document.bypass_unit_scope')) {
                                $query->where('id', $user->unit_id);
                            }

                            return $query->pluck('name', 'id')->all();
                        })
                        ->default(fn (): ?int => auth()->user()?->unit_id)
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set): void {
                            if ($state) {
                                $unit = Unit::find($state);
                                if ($unit) {
                                    $set('is_assessor_access_active', (bool) $unit->is_assessor_access_active);
                                    $set('assessor_access_start', $unit->assessor_access_start?->format('Y-m-d'));
                                    $set('assessor_access_end', $unit->assessor_access_end?->format('Y-m-d'));
                                    $set('assessor_passcode', $unit->assessor_passcode);
                                    $set('assessor_notes', $unit->assessor_notes);
                                }
                            }
                        }),

                    Toggle::make('is_assessor_access_active')
                        ->label('Buka Akses Dokumen untuk Asesor Eksternal')
                        ->helperText('Matikan untuk mengunci seluruh tautan bukti borang setelah masa penilaian selesai.')
                        ->default(false)
                        ->live(),

                    DatePicker::make('assessor_access_start')
                        ->label('Tanggal Mulai Masa Asesmen')
                        ->native(false)
                        ->visible(fn (callable $get): bool => (bool) $get('is_assessor_access_active')),

                    DatePicker::make('assessor_access_end')
                        ->label('Tanggal Selesai Masa Asesmen')
                        ->native(false)
                        ->visible(fn (callable $get): bool => (bool) $get('is_assessor_access_active')),

                    TextInput::make('assessor_passcode')
                        ->label('PIN / Sandi Akses Asesor (Opsional)')
                        ->placeholder('Contoh: TI2026')
                        ->maxLength(50)
                        ->visible(fn (callable $get): bool => (bool) $get('is_assessor_access_active')),

                    Textarea::make('assessor_notes')
                        ->label('Catatan Petunjuk Asesor')
                        ->rows(2)
                        ->placeholder('Pesan petunjuk atau pemberitahuan resmi untuk asesor...')
                        ->visible(fn (callable $get): bool => (bool) $get('is_assessor_access_active')),
                ])
                ->action(function (array $data): void {
                    $unit = Unit::findOrFail($data['unit_id']);
                    $unit->update([
                        'is_assessor_access_active' => (bool) ($data['is_assessor_access_active'] ?? false),
                        'assessor_access_start' => $data['assessor_access_start'] ?? null,
                        'assessor_access_end' => $data['assessor_access_end'] ?? null,
                        'assessor_passcode' => $data['assessor_passcode'] ?? null,
                        'assessor_notes' => $data['assessor_notes'] ?? null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Akses Asesor Diperbarui')
                        ->body("Konfigurasi masa asesmen untuk {$unit->name} berhasil disimpan.")
                        ->send();
                })
                ->visible(fn (): bool => auth()->user()?->can('borang.configure_assessor') ?? false),

            Action::make('exportExcel')
                ->label('Ekspor Rekapitulasi (.xlsx)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->extraAttributes(['class' => 'borang-action-export'])
                ->action(function (): ?StreamedResponse {
                    $user = auth()->user();
                    $query = $this->getFilteredTableQuery();
                    $totalCount = (clone $query)->count();
                    $filename = 'Rekapitulasi-Borang-Akreditasi-UNIVBI-'.now()->format('Ymd').'.xlsx';

                    if ($totalCount > self::HEAVY_EXCEL_THRESHOLD) {
                        $this->dispatchQueuedBorangExport(
                            moduleType: 'borang_excel',
                            filename: $filename,
                            totalDocuments: $totalCount
                        );

                        return null;
                    }

                    $selectedUnitRaw = $this->tableFilters['unit_id']['value'] ?? null;
                    $unit = filled($selectedUnitRaw) ? Unit::find((int) $selectedUnitRaw) : null;
                    if (! $unit && $user && ! $user->can('document.bypass_unit_scope') && $user->unit_id) {
                        $unit = $user->unit;
                    }

                    $selectedYearRaw = $this->tableFilters['academic_year_id']['value'] ?? null;
                    $academicYear = filled($selectedYearRaw) ? AcademicYear::find((int) $selectedYearRaw) : null;

                    $selectedCriterionRaw = $this->tableFilters['accreditationCriteria']['value'] ?? null;
                    $criterion = filled($selectedCriterionRaw) ? AccreditationCriterion::find((int) $selectedCriterionRaw) : null;

                    $instrumentRaw = $this->tableFilters['instrument_source']['value'] ?? null;
                    $instrumentSource = filled($instrumentRaw)
                        ? (string) $instrumentRaw
                        : ($criterion?->instrument_source ?? $unit?->effective_accreditation_instrument);

                    return app(BorangExportService::class)->downloadResponseFromQuery(
                        $query,
                        $filename,
                        $unit,
                        $academicYear,
                        $criterion,
                        $instrumentSource
                    );
                })
                ->visible(fn (): bool => auth()->user()?->can('borang.export_excel') ?? false),

            Action::make('downloadAllZip')
                ->label('Unduh Paket Bundel (.ZIP)')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('primary')
                ->extraAttributes(['class' => 'borang-action-zip'])
                ->action(function (): ?StreamedResponse {
                    $query = $this->getFilteredTableQuery();
                    $totalCount = (clone $query)->count();
                    $filename = 'Bundel-Dokumen-Bukti-Borang-'.now()->format('Ymd').'.zip';

                    if ($totalCount > self::HEAVY_ZIP_THRESHOLD) {
                        $this->dispatchQueuedBorangExport(
                            moduleType: 'borang_zip',
                            filename: $filename,
                            totalDocuments: $totalCount
                        );

                        return null;
                    }

                    return app(BorangZipExportService::class)->downloadZipFromQuery(
                        $query,
                        $filename
                    );
                })
                ->visible(fn (): bool => auth()->user()?->can('borang.download_bulk_zip') ?? false),
        ];
    }

    protected function getActions(): array
    {
        return [
            ...$this->getHeaderActions(),
            $this->closeAssessorAccessAction(),
        ];
    }

    public function closeAssessorAccessAction(): Action
    {
        return Action::make('closeAssessorAccess')
            ->label('Tutup Akses Asesor')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Konfirmasi Tutup Akses Portal Asesor')
            ->modalDescription(function (array $arguments): string {
                $unit = isset($arguments['unit_id']) ? Unit::find($arguments['unit_id']) : null;
                $unitName = $unit ? $unit->name : 'Program Studi';

                return "Apakah Anda yakin ingin menutup akses publik bukti fisik borang untuk {$unitName}? Seluruh tautan publik asesor untuk unit ini akan segera dikunci dan tidak dapat diakses.";
            })
            ->modalSubmitActionLabel('Ya, Tutup Akses Sekarang')
            ->action(function (array $arguments): void {
                $unitId = $arguments['unit_id'] ?? null;
                if (! $unitId) {
                    return;
                }

                $user = auth()->user();
                if ($user && ! $user->can('document.bypass_unit_scope') && (int) $user->unit_id !== (int) $unitId) {
                    Notification::make()
                        ->danger()
                        ->title('Akses Ditolak')
                        ->body('Anda tidak memiliki izin menutup akses asesor untuk unit ini.')
                        ->send();

                    return;
                }

                $unit = Unit::findOrFail($unitId);
                $unit->update([
                    'is_assessor_access_active' => false,
                ]);

                Notification::make()
                    ->success()
                    ->title('Akses Asesor Ditutup')
                    ->body("Akses publik bukti borang untuk {$unit->name} telah berhasil ditutup.")
                    ->send();
            })
            ->visible(fn (): bool => auth()->user()?->can('borang.configure_assessor') ?? false);
    }

    /**
     * @return Collection<int, Unit>
     */
    public function getActiveAssessorUnits(): Collection
    {
        $user = auth()->user();

        $query = Unit::query()
            ->where('is_assessor_access_active', true)
            ->orderBy('name');

        if ($user && ! $user->can('document.bypass_unit_scope')) {
            $query->where('id', $user->unit_id);
        }

        return $query->get();
    }

    public function filterByCriterionId(?int $criterionId = null): void
    {
        $current = $this->tableFilters['accreditationCriteria']['value'] ?? null;

        if ($current !== null && (int) $current === $criterionId) {
            $this->tableFilters['accreditationCriteria'] = ['value' => null];
        } else {
            $this->tableFilters['accreditationCriteria'] = [
                'value' => $criterionId !== null ? (string) $criterionId : null,
            ];
        }

        $this->resetPage();
    }

    public function resetCriteriaFilter(): void
    {
        $this->tableFilters['accreditationCriteria'] = ['value' => null];
        $this->resetPage();
    }

    /**
     * @return array{
     *     total: int,
     *     criteria: array<int, array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         short_name: string,
     *         count: int,
     *         is_selected: bool
     *     }>,
     *     selected_id: ?int,
     *     selected_code: ?string,
     *     active_unit_name: ?string,
     *     active_year_name: ?string
     * }
     */
    public function getCriteriaStatistics(): array
    {
        $stateKey = md5((string) json_encode([$this->tableFilters, $this->tableSearch]));
        if ($this->memoizedCriteriaStatistics !== null && $this->memoizedStatisticsKey === $stateKey) {
            return $this->memoizedCriteriaStatistics;
        }

        $user = auth()->user();

        $selectedCriterionRaw = $this->tableFilters['accreditationCriteria']['value'] ?? null;
        $selectedCriterionId = filled($selectedCriterionRaw) ? (int) $selectedCriterionRaw : null;

        $selectedUnitRaw = $this->tableFilters['unit_id']['value'] ?? null;
        $selectedUnitId = filled($selectedUnitRaw) ? (int) $selectedUnitRaw : null;

        $selectedYearRaw = $this->tableFilters['academic_year_id']['value'] ?? null;
        $selectedYearId = filled($selectedYearRaw) ? (int) $selectedYearRaw : null;

        $selectedUnit = $selectedUnitId ? Unit::find($selectedUnitId) : null;
        $activeUnitName = $selectedUnit?->name;

        $activeYearName = $selectedYearId ? AcademicYear::find($selectedYearId)?->name : null;

        // Determine target accreditation instrument for statistics & cards
        $selectedInstrument = null;
        if (isset($this->tableFilters['instrument_source']['value']) && filled($this->tableFilters['instrument_source']['value'])) {
            $selectedInstrument = (string) $this->tableFilters['instrument_source']['value'];
        } elseif ($selectedUnit) {
            $selectedInstrument = $selectedUnit->effective_accreditation_instrument;
        } elseif ($user && ! $user->can('document.bypass_unit_scope') && $user->unit) {
            $selectedInstrument = $user->unit->effective_accreditation_instrument;
        }

        if (! $selectedInstrument) {
            $mostUsed = Cache::remember('borang_default_most_used_instrument', 300, function (): ?string {
                return DB::table('criterion_document')
                    ->join('accreditation_criteria', 'criterion_document.accreditation_criterion_id', '=', 'accreditation_criteria.id')
                    ->groupBy('accreditation_criteria.instrument_source')
                    ->orderByRaw('COUNT(*) DESC')
                    ->value('accreditation_criteria.instrument_source');
            });

            $selectedInstrument = $mostUsed ?: AccreditationInstrument::LamInfokom->value;
        }

        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // Base document filter closure respecting tenancy & active filters
        $applyDocFilters = function (Builder $docQuery) use ($user, $selectedUnitId, $selectedYearId, $likeOperator): void {
            $docQuery
                ->where('documents.is_borang', true)
                ->where('documents.status', DocumentStatus::Approved);

            if ($user && ! $user->can('document.bypass_unit_scope')) {
                $docQuery->where('documents.unit_id', $user->unit_id);
            }

            if ($selectedUnitId) {
                $docQuery->where('documents.unit_id', $selectedUnitId);
            }

            if ($selectedYearId) {
                $docQuery->where('documents.academic_year_id', $selectedYearId);
            }

            if (filled($this->tableSearch)) {
                $search = trim((string) $this->tableSearch);
                $docQuery->where(function (Builder $q) use ($search, $likeOperator): void {
                    $q->where('documents.title', $likeOperator, "%{$search}%")
                        ->orWhere('documents.document_number', $likeOperator, "%{$search}%");
                });
            }
        };

        $baseDocQuery = Document::query();
        $applyDocFilters($baseDocQuery);
        $totalDocuments = (clone $baseDocQuery)->count();

        $criteriaRecords = AccreditationCriterion::query()
            ->where('is_active', true)
            ->when($selectedInstrument, fn (Builder $q): Builder => $q->where('instrument_source', $selectedInstrument))
            ->orderBy('order_index')
            ->get(['id', 'code', 'name', 'instrument_source']);

        $criteriaIds = $criteriaRecords->pluck('id')->all();
        $criterionCounts = [];

        if ($criteriaIds !== []) {
            $countQuery = DB::table('criterion_document')
                ->join('documents', 'documents.id', '=', 'criterion_document.document_id')
                ->whereIn('criterion_document.accreditation_criterion_id', $criteriaIds)
                ->whereNull('documents.deleted_at')
                ->where('documents.is_borang', true)
                ->where('documents.status', DocumentStatus::Approved->value);

            if ($user && ! $user->can('document.bypass_unit_scope')) {
                $countQuery->where('documents.unit_id', $user->unit_id);
            }

            if ($selectedUnitId) {
                $countQuery->where('documents.unit_id', $selectedUnitId);
            }

            if ($selectedYearId) {
                $countQuery->where('documents.academic_year_id', $selectedYearId);
            }

            if (filled($this->tableSearch)) {
                $search = trim((string) $this->tableSearch);
                $countQuery->where(function ($q) use ($search, $likeOperator): void {
                    $q->where('documents.title', $likeOperator, "%{$search}%")
                        ->orWhere('documents.document_number', $likeOperator, "%{$search}%");
                });
            }

            $criterionCounts = $countQuery
                ->groupBy('criterion_document.accreditation_criterion_id')
                ->selectRaw('criterion_document.accreditation_criterion_id as criterion_id, COUNT(DISTINCT criterion_document.document_id) as total_documents')
                ->pluck('total_documents', 'criterion_id')
                ->all();
        }

        $shortNames = [
            'K1' => 'Visi, Misi & Strategi',
            'K2' => 'Tata Pamong & Kerjasama',
            'K3' => 'Mahasiswa',
            'K4' => 'Sumber Daya Manusia',
            'K5' => 'Keuangan & Sarpras',
            'K6' => 'Pendidikan',
            'K7' => 'Penelitian',
            'K8' => 'Pengabdian (PkM)',
            'K9' => 'Luaran & Capaian',
        ];

        $criteriaList = [];
        $selectedCriterionCode = null;
        $filledCount = 0;
        $maxCount = 1;

        foreach ($criteriaRecords as $item) {
            $count = (int) ($criterionCounts[$item->id] ?? 0);
            $isSelected = ($selectedCriterionId === (int) $item->id);

            if ($isSelected) {
                $selectedCriterionCode = (string) $item->code;
            }

            if ($count > 0) {
                $filledCount++;
                if ($count > $maxCount) {
                    $maxCount = $count;
                }
            }

            $criteriaList[] = [
                'id' => (int) $item->id,
                'code' => (string) $item->code,
                'name' => (string) $item->name,
                'short_name' => $shortNames[$item->code] ?? $item->name,
                'count' => $count,
                'is_selected' => $isSelected,
            ];
        }

        $totalCriteria = max(1, count($criteriaList));
        $readinessPercent = (int) round(($filledCount / $totalCriteria) * 100);

        $result = [
            'total' => $totalDocuments,
            'criteria' => $criteriaList,
            'filled_count' => $filledCount,
            'total_criteria' => $totalCriteria,
            'readiness_percent' => $readinessPercent,
            'max_count' => $maxCount,
            'selected_id' => $selectedCriterionId,
            'selected_code' => $selectedCriterionCode,
            'active_unit_name' => $activeUnitName,
            'active_year_name' => $activeYearName,
            'active_instrument_name' => $selectedInstrument,
        ];

        $this->memoizedStatisticsKey = $stateKey;
        $this->memoizedCriteriaStatistics = $result;

        return $result;
    }

    /**
     * Memasukkan proses ekspor berat Borang ke antrean latar belakang dan memberi notifikasi kepada pengguna.
     *
     * @param  array<int>|null  $documentIds
     */
    protected function dispatchQueuedBorangExport(
        string $moduleType,
        string $filename,
        int $totalDocuments,
        ?array $documentIds = null
    ): void {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = [
            'bypass_unit_scope' => $user->can('document.bypass_unit_scope'),
            'scope_unit_id' => $user->unit_id,
            'unit_id' => filled($this->tableFilters['unit_id']['value'] ?? null) ? (int) $this->tableFilters['unit_id']['value'] : null,
            'academic_year_id' => filled($this->tableFilters['academic_year_id']['value'] ?? null) ? (int) $this->tableFilters['academic_year_id']['value'] : null,
            'criterion_id' => filled($this->tableFilters['accreditationCriteria']['value'] ?? null) ? (int) $this->tableFilters['accreditationCriteria']['value'] : null,
            'instrument_source' => filled($this->tableFilters['instrument_source']['value'] ?? null) ? (string) $this->tableFilters['instrument_source']['value'] : null,
            'search' => filled($this->tableSearch) ? trim((string) $this->tableSearch) : null,
        ];

        if ($documentIds !== null) {
            $payload['document_ids'] = $documentIds;
        }

        $export = BulkExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'module_type' => $moduleType,
            'record_ids' => $payload,
            'total_files' => $totalDocuments,
            'zip_file_name' => $filename,
            'disk' => 'private',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        GenerateBorangExportJob::dispatch($export);

        $formatLabel = $moduleType === 'borang_excel' ? 'Rekapitulasi Excel (.xlsx)' : 'Paket Bundel ZIP';

        Notification::make()
            ->info()
            ->title("Ekspor {$formatLabel} Diproses di Latar Belakang")
            ->body("Permintaan ekspor berisi {$totalDocuments} dokumen sedang diproses di antrean agar tidak membebani browser. Anda akan menerima notifikasi lonceng berisi tautan unduhan (berlaku 24 jam) setelah selesai.")
            ->send();
    }
}
