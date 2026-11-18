<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->paginationMode(PaginationMode::Simple)
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->searchDebounce('500ms')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu Kejadian')
                    ->dateTime('d M Y, H:i:s')
                    ->description(fn (ActivityLog $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('Pelaku (User)')
                    ->weight(FontWeight::SemiBold)
                    ->default('Sistem / Otomatisasi')
                    ->description(fn (ActivityLog $record): ?string => $record->causer?->email ?: ($record->causer_type ? class_basename($record->causer_type) : null)),

                TextColumn::make('event')
                    ->label('Aksi / Peristiwa')
                    ->badge()
                    ->color(fn (ActivityLog $record): string => $record->getEventColor())
                    ->formatStateUsing(fn (ActivityLog $record): string => $record->getEventLabel())
                    ->sortable(),

                TextColumn::make('log_name')
                    ->label('Modul / Kategori')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (ActivityLog $record): string => $record->log_name ? $record->getHumanLogCategory() : '-')
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Objek Terkait')
                    ->state(fn (ActivityLog $record): string => $record->getFormattedSubjectTitle())
                    ->wrap(),

                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(45)
                    ->tooltip(fn (ActivityLog $record): ?string => $record->description)
                    ->searchable(),

                TextColumn::make('ip_address')
                    ->label('Alamat IP & Perangkat')
                    ->copyable()
                    ->placeholder('-')
                    ->description(fn (ActivityLog $record): ?string => $record->user_agent ? Str::limit($record->user_agent, 28) : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Jenis Aksi')
                    ->options([
                        'login' => 'Masuk (Login)',
                        'logout' => 'Keluar (Logout)',
                        'failed_login' => 'Gagal Login',
                        'created' => 'Pembuatan Baru',
                        'updated' => 'Pembaruan Data',
                        'deleted' => 'Penghapusan',
                        'approved' => 'Pengesahan Naskah',
                        'rejected' => 'Penolakan Naskah',
                        'revision_requested' => 'Permintaan Revisi',
                        'archived' => 'Pengarsipan',
                        'downloaded' => 'Unduh Berkas',
                        'completed' => 'Ekspor Selesai',
                        'backup_created' => 'Backup Dibuat',
                        'backup_restored' => 'Sistem Dipulihkan',
                        'reminder_sent' => 'Pengingat Kedaluwarsa',
                    ]),

                SelectFilter::make('log_name')
                    ->label('Kategori Modul')
                    ->options([
                        'auth_audit' => 'Autentikasi & Sesi',
                        'documents' => 'E-Arsip & Dokumen',
                        'incoming_letters' => 'Surat Masuk',
                        'outgoing_letters' => 'Surat Keluar',
                        'borang_export' => 'Ekspor Borang Akreditasi',
                        'cms_moderation' => 'Moderasi Berita',
                        'cms_export' => 'Ekspor Rekap Berita',
                        'bulk_zip_export' => 'Ekspor Arsip Massal',
                        'system_backup' => 'Backup & Restore Sistem',
                        'users' => 'Manajemen Pengguna',
                        'system' => 'Sistem & Konfigurasi',
                        'default' => 'Umum',
                    ]),

                SelectFilter::make('causer_id')
                    ->label('Aktor Pengguna')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        $keyword = trim($search);
                        if ($keyword === '') {
                            return [];
                        }

                        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

                        return User::query()
                            ->where(function (Builder $q) use ($keyword, $operator): void {
                                $q->where('name', $operator, "%{$keyword}%")
                                    ->orWhere('email', $operator, "%{$keyword}%");
                            })
                            ->orderBy('name')
                            ->limit(25)
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->getOptionLabelUsing(fn (mixed $value): ?string => filled($value) ? User::query()->whereKey($value)->value('name') : null)
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query
                            ->where('causer_type', User::class)
                            ->where('causer_id', (int) $data['value']);
                    }),

                Filter::make('created_at')
                    ->label('Rentang Waktu')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari Tanggal')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Sampai Tanggal')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date): Builder => $q->where('created_at', '>=', Carbon::parse((string) $date)->startOfDay()),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date): Builder => $q->where('created_at', '<=', Carbon::parse((string) $date)->endOfDay()),
                            );
                    }),
            ], layout: FiltersLayout::Modal)
            ->recordActions([
                Action::make('view_diff')
                    ->label('Rincian & Diff')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (ActivityLog $record): string => "Inspeksi Log Audit: #{$record->id}")
                    ->modalDescription(fn (ActivityLog $record): string => "Detail aktivitas keamanan dan rekam jejak integritas sistem [{$record->getHumanLogCategory()}].")
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalContent(fn (ActivityLog $record): View => view('filament.components.audit-log-diff-modal', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->toolbarActions([]);
    }
}
