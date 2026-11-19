<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentApprovalService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PendingApprovalsWidget extends TableWidget
{
    use HasWidgetShield;

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Antrean Pengesahan & Naskah Butuh Tindak Lanjut')
            ->description('Daftar dokumen naskah dinas yang sedang menunggu persetujuan pejabat atau memerlukan perbaikan.')
            ->query(fn (): Builder => $this->getTableQuery())
            ->paginationMode(PaginationMode::Simple)
            ->columns([
                TextColumn::make('document_number')
                    ->label('Nomor Naskah')
                    ->placeholder('(Belum Terbit)')
                    ->weight(FontWeight::Bold)
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Judul / Perihal')
                    ->limit(45)
                    ->tooltip(fn (Document $record): string => $record->title)
                    ->searchable(),

                TextColumn::make('unit.name')
                    ->label('Unit Pengaju')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('current_step')
                    ->label('Tahap Alur')
                    ->badge()
                    ->formatStateUsing(fn ($state, Document $record): string => $record->getCurrentStepLabel())
                    ->color(fn (Document $record): string => $record->status === DocumentStatus::Revision ? 'danger' : 'warning'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Tinjau')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->url(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Document $record): string => DocumentResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Semua Naskah Tuntas Diproses')
            ->emptyStateDescription('Saat ini tidak ada naskah dinas yang sedang menunggu persetujuan atau revisi.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    /**
     * Membangun query antrean naskah dinas yang berhak diakses oleh pengguna.
     * Mengamankan agar pengguna hanya melihat naskah sesuai hak otorisasi peran dan unitnya.
     */
    public static function getPendingQueryForUser(?User $user, bool $withEagerLoads = true): Builder
    {
        if (! $user) {
            return Document::query()->whereRaw('1 = 0');
        }

        $relations = ['unit', 'category.approvalFlows', 'approvals'];
        $approvalService = app(DocumentApprovalService::class);

        // Jika super admin, tampilkan seluruh antrean pending dan revisi
        if ($user->hasRole('super_admin')) {
            return Document::query()
                ->when($withEagerLoads, static fn (Builder $q): Builder => $q->with($relations))
                ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Revision])
                ->latest('updated_at');
        }

        $pendingApprovalsSubquery = $approvalService->getPendingApprovalsQueryForUser($user);
        $hasApproverDocs = (clone $pendingApprovalsSubquery)->exists();

        // Query dasar dokumen yang berhak diakses (terlindungi oleh scopeAccessibleFor & confidentiality)
        $query = Document::query()
            ->when($withEagerLoads, static fn (Builder $q): Builder => $q->with($relations))
            ->accessibleFor($user)
            ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Revision]);

        // Jika pengguna memiliki tugas approval (approver) dan terdapat naskah yang menunggu tindakannya:
        // Tampilkan naskah yang butuh persetujuannya ATAU naskah unit/unggahannya sendiri yang sedang pending/revisi.
        // Jika bukan approver (staf unit biasa):
        // Batasi ketat hanya pada naskah yang diajukan oleh unitnya atau diunggah oleh dirinya.
        if ($hasApproverDocs) {
            $query->where(function (Builder $sub) use ($user, $approvalService): void {
                $sub->whereIn('documents.id', $approvalService->getPendingApprovalsQueryForUser($user)->select('documents.id'))
                    ->orWhere(function (Builder $unitSub) use ($user): void {
                        $unitSub->where('documents.uploaded_by', $user->id);

                        if ($user->unit_id) {
                            $unitSub->orWhere('documents.unit_id', $user->unit_id);
                        }

                        if ($user->hasRole('dekan') && $user->unit_id) {
                            $unitSub->orWhereIn('documents.unit_id', function ($facSub) use ($user): void {
                                $facSub->select('id')->from('units')->where('parent_id', $user->unit_id);
                            });
                        }
                    });
            });
        } elseif (! $user->can('document.bypass_unit_scope')) {
            $query->where(function (Builder $sub) use ($user): void {
                $sub->where('documents.uploaded_by', $user->id);

                if ($user->unit_id) {
                    $sub->orWhere('documents.unit_id', $user->unit_id);
                }

                if ($user->hasRole('dekan') && $user->unit_id) {
                    $sub->orWhereIn('documents.unit_id', function ($facSub) use ($user): void {
                        $facSub->select('id')->from('units')->where('parent_id', $user->unit_id);
                    });
                }
            });
        }

        return $query->latest('updated_at');
    }

    protected function getTableQuery(): Builder
    {
        return static::getPendingQueryForUser(Auth::user());
    }
}
