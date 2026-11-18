@props([
    'document',
])

@php
    $logs = $document->reminderLogs()
        ->with('recipientUser')
        ->latest('sent_at')
        ->get();

    $today = now()->startOfDay();
    $daysRemaining = $document->expired_date ? (int) $today->diffInDays($document->expired_date->copy()->startOfDay(), false) : null;
    $isPast = $daysRemaining !== null && $daysRemaining < 0;

    $recipients = $document->getReminderRecipientUsers();
@endphp

@include('filament.components.document-timeline-styles')

<div style="display: flex; flex-direction: column; gap: 1rem;">
    {{-- Header Summary Bar --}}
    <div class="doc-card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.25rem; background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; box-shadow: var(--doc-shadow-sm); margin-bottom: 0; color: var(--doc-text-primary);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="display: flex; width: 2.25rem; height: 2.25rem; align-items: center; justify-content: center; border-radius: 0.375rem; background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-solid); border: 1px solid var(--doc-badge-warning-border); font-weight: 700; flex-shrink: 0;">
                <x-heroicon-o-bell-alert class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </span>
            <div>
                <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem;">
                    Status Masa Berlaku & Jadwal Pengingat Kedaluwarsa
                </h4>
                <p style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                    Sistem otomatis memindai dan mengirimkan email serta notifikasi lonceng panel kepada para penerima terjadwal.
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            @if(! $document->has_expiry)
                <span class="doc-badge doc-badge-gray" style="background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); font-weight: 700;">
                    Berlaku Selamanya (Tanpa Kedaluwarsa)
                </span>
            @elseif($isPast || $document->status === \App\Enums\DocumentStatus::Expired)
                <span class="doc-badge doc-badge-danger" style="background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-solid); border: 1px solid var(--doc-badge-danger-border); font-weight: 700;">
                    Masa Berlaku Berakhir (Kedaluwarsa)
                </span>
            @elseif($daysRemaining <= 7)
                <span class="doc-badge doc-badge-danger" style="background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-solid); border: 1px solid var(--doc-badge-danger-border); font-weight: 700;">
                    KRITIS: Tersisa {{ $daysRemaining }} Hari
                </span>
            @elseif($daysRemaining <= 30)
                <span class="doc-badge doc-badge-warning" style="background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-solid); border: 1px solid var(--doc-badge-warning-border); font-weight: 700;">
                    PERINGATAN: Tersisa {{ $daysRemaining }} Hari
                </span>
            @else
                <span class="doc-badge doc-badge-info" style="background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); font-weight: 700;">
                    Aktif: Tersisa {{ $daysRemaining }} Hari
                </span>
            @endif
        </div>
    </div>

    {{-- Detail Konfigurasi Pengingat --}}
    @if($document->has_expiry)
        <div class="doc-card" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; padding: 1.25rem; background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; box-shadow: var(--doc-shadow-sm); margin-bottom: 0;">
            <div>
                <span style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--doc-text-muted); letter-spacing: 0.05em;">Tanggal Ditetapkan / Terbit</span>
                <p style="font-size: 0.875rem; font-weight: 600; color: var(--doc-text-primary); margin: 0.25rem 0 0 0;">
                    {{ $document->issued_date ? $document->issued_date->format('d F Y') : '-' }}
                </p>
            </div>

            <div>
                <span style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--doc-text-muted); letter-spacing: 0.05em;">Tanggal Kedaluwarsa (Tenggat)</span>
                <p style="font-size: 0.875rem; font-weight: 700; color: {{ $isPast ? 'var(--doc-badge-danger-solid)' : 'var(--doc-text-primary)' }}; margin: 0.25rem 0 0 0;">
                    {{ $document->expired_date ? $document->expired_date->format('d F Y') : '-' }}
                </p>
            </div>

            <div>
                <span style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--doc-text-muted); letter-spacing: 0.05em;">Jadwal Interval Notifikasi</span>
                <div style="display: flex; gap: 0.375rem; flex-wrap: wrap; margin-top: 0.25rem;">
                    @forelse((array)($document->reminder_intervals ?? []) as $intVal)
                        @php $intervalEnum = \App\Enums\ReminderInterval::tryFrom($intVal); @endphp
                        @if($intervalEnum)
                            <span class="doc-badge doc-badge-info" style="font-size: 0.6875rem; padding: 0.15rem 0.5rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); font-weight: 700;">
                                {{ strtoupper($intervalEnum->value) }} ({{ $intervalEnum->daysBeforeExpiry() }} Hari)
                            </span>
                        @endif
                    @empty
                        <span style="font-size: 0.75rem; color: var(--doc-text-muted);">Tidak ada interval dipilih</span>
                    @endforelse
                </div>
            </div>

            <div>
                <span style="font-size: 0.6875rem; text-transform: uppercase; font-weight: 700; color: var(--doc-text-muted); letter-spacing: 0.05em;">Daftar Akun Penerima</span>
                <div style="margin-top: 0.25rem;">
                    @if($recipients->isNotEmpty())
                        <ul style="margin: 0; padding-left: 1.125rem; font-size: 0.75rem; color: var(--doc-text-primary); line-height: 1.35rem;">
                            @foreach($recipients as $u)
                                <li><strong>{{ $u->name }}</strong> ({{ $u->email }})</li>
                            @endforeach
                        </ul>
                    @else
                        <span style="font-size: 0.75rem; color: var(--doc-text-muted);">
                            Otomatis dialihkan ke pengunggah dokumen: <em>{{ $document->uploader?->name ?: 'Pengunggah' }}</em>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Tabel Riwayat Log Notifikasi Pengingat --}}
    @if($logs->isEmpty())
        <div class="doc-empty-state" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); padding: 2.5rem 1.5rem; text-align: center; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
            <div class="doc-empty-icon" style="margin: 0 auto; display: flex; width: 3rem; height: 3rem; align-items: center; justify-content: center; border-radius: 9999px; background-color: var(--doc-surface-muted); color: var(--doc-text-muted); border: 1px solid var(--doc-border);">
                <x-heroicon-o-check-circle class="doc-icon-md" style="width: 1.5rem; height: 1.5rem;" />
            </div>
            <h4 class="doc-empty-title" style="margin-top: 0.75rem; font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary);">
                Belum Ada Riwayat Notifikasi Pengingat
            </h4>
            <p class="doc-empty-text" style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 28rem; margin-left: auto; margin-right: auto; line-height: 1.375rem;">
                Notifikasi pengingat harian akan dicatat secara otomatis di sini saat naskah dinas mencapai interval H-60, H-30, atau H-7 sebelum tanggal kedaluwarsa.
            </p>
        </div>
    @else
        <div class="doc-card" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); box-shadow: var(--doc-shadow-sm); overflow: hidden; padding: 0; margin-bottom: 0;">
            <div class="doc-table-wrapper" style="overflow-x: auto; width: 100%; border: 0; border-radius: 0;">
                <table class="doc-table" style="width: 100%; text-align: left; font-size: 0.75rem; border-collapse: collapse;">
                    <thead style="background-color: var(--doc-surface-muted); color: var(--doc-text-muted); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--doc-border);">
                        <tr>
                            <th style="padding: 0.75rem 1rem;">Waktu Kirim</th>
                            <th style="padding: 0.75rem 1rem;">Interval</th>
                            <th style="padding: 0.75rem 1rem;">Penerima (Akun & Email)</th>
                            <th style="padding: 0.75rem 1rem;">Siklus Masa Berlaku</th>
                            <th style="padding: 0.75rem 1rem;">Status Pengiriman</th>
                        </tr>
                    </thead>
                    <tbody style="divide-y: 1px solid var(--doc-border);">
                        @foreach($logs as $log)
                            <tr style="border-bottom: 1px solid var(--doc-border);">
                                <td style="padding: 0.75rem 1rem; white-space: nowrap; font-weight: 600; color: var(--doc-text-primary);">
                                    {{ $log->sent_at ? $log->sent_at->format('d/m/Y H:i:s') : '-' }}
                                    <div style="font-size: 0.6875rem; font-weight: normal; color: var(--doc-text-muted);">
                                        {{ $log->sent_at ? $log->sent_at->diffForHumans() : '' }}
                                    </div>
                                </td>
                                <td style="padding: 0.75rem 1rem; white-space: nowrap;">
                                    <span class="doc-badge doc-badge-info" style="font-size: 0.6875rem; padding: 0.15rem 0.5rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); font-weight: 700;">
                                        {{ $log->interval_type?->getLabel() ?: strtoupper($log->interval_type?->value ?? '-') }}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: var(--doc-text-primary);">
                                    <div style="font-weight: 600;">
                                        {{ $log->recipientUser?->name ?: 'Akun Sivitas' }}
                                    </div>
                                    <div style="font-size: 0.6875rem; color: var(--doc-text-muted);">
                                        {{ $log->recipient_email }}
                                    </div>
                                </td>
                                <td style="padding: 0.75rem 1rem; white-space: nowrap; color: var(--doc-text-primary);">
                                    {{ $log->expired_date_snapshot ? $log->expired_date_snapshot->format('d F Y') : '-' }}
                                </td>
                                <td style="padding: 0.75rem 1rem; white-space: nowrap;">
                                    @if($log->is_success)
                                        <span class="doc-badge doc-badge-success" style="font-size: 0.6875rem; padding: 0.15rem 0.5rem; background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-solid); border: 1px solid var(--doc-badge-success-border); font-weight: 700; display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <x-heroicon-s-check-circle style="width: 0.875rem; height: 0.875rem;" />
                                            Terkirim Sukses
                                        </span>
                                    @else
                                        <span class="doc-badge doc-badge-danger" style="font-size: 0.6875rem; padding: 0.15rem 0.5rem; background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-solid); border: 1px solid var(--doc-badge-danger-border); font-weight: 700; display: inline-flex; align-items: center; gap: 0.25rem;" title="{{ $log->error_message }}">
                                            <x-heroicon-s-x-circle style="width: 0.875rem; height: 0.875rem;" />
                                            Gagal Kirim
                                        </span>
                                        @if($log->error_message)
                                            <div style="font-size: 0.6875rem; color: var(--doc-badge-danger-solid); margin-top: 0.125rem; max-width: 14rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $log->error_message }}">
                                                {{ $log->error_message }}
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

