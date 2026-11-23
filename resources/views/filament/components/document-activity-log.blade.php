@props([
    'document',
])

@php
    $activities = \App\Models\ActivityLog::query()
        ->where('subject_type', \App\Models\Document::class)
        ->where('subject_id', $document->id)
        ->with('causer')
        ->latest('id')
        ->get();
@endphp

@include('filament.components.document-timeline-styles')

<div style="display: flex; flex-direction: column; gap: 1rem;">
    {{-- Summary Bar --}}
    <div class="doc-card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.25rem; margin-bottom: 0; color: var(--doc-text-primary);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span class="doc-card-title-icon">
                <x-heroicon-o-shield-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </span>
            <div>
                <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem;">
                    Catatan Keamanan & Rekam Jejak Sistem (Activity Audit Trail)
                </h4>
                <p style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                    Mencatat seluruh tindakan teknis (unduhan berkas, pembaruan data, stempel, alamat IP, dan perangkat).
                </p>
            </div>
        </div>

        <span class="doc-badge doc-badge-gray" style="background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); font-weight: 700;">
            Total {{ $activities->count() }} Aktivitas
        </span>
    </div>

    @if($activities->isEmpty())
        <div class="doc-empty-state" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); padding: 2.5rem 1.5rem; text-align: center; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
            <div class="doc-empty-icon" style="margin: 0 auto; display: flex; width: 3rem; height: 3rem; align-items: center; justify-content: center; border-radius: 9999px; background-color: var(--doc-surface-muted); color: var(--doc-text-muted); border: 1px solid var(--doc-border);">
                <x-heroicon-o-clock class="doc-icon-md" style="width: 1.5rem; height: 1.5rem;" />
            </div>
            <h4 class="doc-empty-title" style="margin-top: 0.75rem; font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary);">
                Belum Ada Catatan Aktivitas
            </h4>
            <p class="doc-empty-text" style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 28rem; margin-left: auto; margin-right: auto; line-height: 1.375rem;">
                Aktivitas terkait pengubahan, persetujuan, dan pengunduhan berkas dokumen ini akan dicatat secara otomatis di sini.
            </p>
        </div>
    @else
        <div class="doc-card" style="overflow: hidden; padding: 0; margin-bottom: 0;">
            <div class="doc-table-wrapper" style="overflow-x: auto; width: 100%; border: 0; border-radius: 0;">
                <table class="doc-table" style="width: 100%; text-align: left; font-size: 0.75rem; border-collapse: collapse;">
                    <thead style="background-color: var(--doc-surface-muted); color: var(--doc-text-muted); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--doc-border);">
                        <tr>
                            <th style="padding: 0.75rem 1rem;">Waktu</th>
                            <th style="padding: 0.75rem 1rem;">Aksi / Peristiwa</th>
                            <th style="padding: 0.75rem 1rem;">Pelaku (User)</th>
                            <th style="padding: 0.75rem 1rem;">Audit Keamanan (IP & Perangkat)</th>
                            <th style="padding: 0.75rem 1rem;">Keterangan & Rincian Perubahan</th>
                        </tr>
                    </thead>
                    <tbody style="font-family: inherit;">
                        @foreach($activities as $activity)
                            @php
                                $event = $activity->event ?? 'custom';
                                $properties = $activity->properties?->toArray() ?? [];
                                $hasDiff = isset($properties['attributes']) && isset($properties['old']);
                                $ip = $activity->ip_address ?? $properties['ip_address'] ?? null;
                                $ua = $activity->user_agent ?? $properties['user_agent'] ?? null;

                                $eventLabels = [
                                    'created' => 'Dokumen Dibuat',
                                    'updated' => 'Dokumen Diperbarui',
                                    'approved' => 'Disetujui',
                                    'revision_requested' => 'Diminta Revisi',
                                    'rejected' => 'Ditolak',
                                    'resubmitted' => 'Diajukan Kembali',
                                    'stamped' => 'Dibubuhi Stempel',
                                    'downloaded' => 'Berkas Diunduh',
                                    'deleted' => 'Dihapus',
                                    'restored' => 'Dipulihkan',
                                ];
                                $eventLabel = $eventLabels[$event] ?? str($event)->headline();
                            @endphp
                            <tr style="border-bottom: 1px solid var(--doc-border); background-color: var(--doc-surface); transition: background-color 0.15s ease;">
                                {{-- Waktu --}}
                                <td style="padding: 0.875rem 1rem; white-space: nowrap; vertical-align: top;">
                                    <div style="font-weight: 700; color: var(--doc-text-primary); font-size: 0.8125rem;">
                                        {{ $activity->created_at->translatedFormat('d M Y') }}
                                    </div>
                                    <div style="font-size: 0.6875rem; color: var(--doc-text-muted); margin-top: 0.125rem;">
                                        {{ $activity->created_at->translatedFormat('H:i:s') }} WIB
                                    </div>
                                    <div style="font-size: 0.625rem; color: var(--doc-text-subtle); margin-top: 0.125rem;">
                                        {{ $activity->created_at->diffForHumans() }}
                                    </div>
                                </td>

                                {{-- Aksi / Event --}}
                                <td style="padding: 0.875rem 1rem; white-space: nowrap; vertical-align: top;">
                                    <span class="doc-badge
                                        @if(in_array($event, ['approved', 'restored'])) doc-badge-success
                                        @elseif($event === 'stamped') doc-badge-teal
                                        @elseif(in_array($event, ['revision_requested', 'updated'])) doc-badge-warning
                                        @elseif(in_array($event, ['rejected', 'deleted'])) doc-badge-danger
                                        @elseif($event === 'downloaded') doc-badge-purple
                                        @elseif(in_array($event, ['created', 'resubmitted'])) doc-badge-info
                                        @else doc-badge-gray
                                        @endif
                                    " style="
                                        @if(in_array($event, ['approved', 'restored'])) background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);
                                        @elseif($event === 'stamped') background-color: var(--doc-badge-teal-bg); color: var(--doc-badge-teal-text); border: 1px solid var(--doc-badge-teal-border);
                                        @elseif(in_array($event, ['revision_requested', 'updated'])) background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);
                                        @elseif(in_array($event, ['rejected', 'deleted'])) background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-text); border: 1px solid var(--doc-badge-danger-border);
                                        @elseif($event === 'downloaded') background-color: var(--doc-badge-purple-bg); color: var(--doc-badge-purple-text); border: 1px solid var(--doc-badge-purple-border);
                                        @elseif(in_array($event, ['created', 'resubmitted'])) background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);
                                        @else background-color: var(--doc-badge-gray-bg); color: var(--doc-badge-gray-text); border: 1px solid var(--doc-badge-gray-border);
                                        @endif
                                    ">
                                        {{ $eventLabel }}
                                    </span>
                                </td>

                                {{-- User --}}
                                <td style="padding: 0.875rem 1rem; vertical-align: top;">
                                    @if($activity->causer)
                                        <div style="font-weight: 700; color: var(--doc-text-primary);">
                                            {{ $activity->causer->name }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: var(--doc-text-muted);">
                                            {{ $activity->causer->email }}
                                        </div>
                                    @else
                                        <span style="font-style: italic; color: var(--doc-text-muted);">Sistem Otomatis</span>
                                    @endif
                                </td>

                                {{-- Audit IP & Agent --}}
                                <td style="padding: 0.875rem 1rem; vertical-align: top; font-family: ui-monospace, monospace; font-size: 0.6875rem; color: var(--doc-text-muted);">
                                    @if($ip)
                                        <div style="font-weight: 600; color: var(--doc-text-primary);">
                                            IP: {{ $ip }}
                                        </div>
                                    @endif
                                    @if($ua)
                                        <div style="max-width: 14rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 0.125rem;" title="{{ $ua }}">
                                            UA: {{ Str::limit($ua, 30) }}
                                        </div>
                                    @endif
                                    @if(!$ip && !$ua)
                                        <span style="font-style: italic; color: var(--doc-text-subtle);">Internal Dispatch</span>
                                    @endif
                                </td>

                                {{-- Keterangan & Diff --}}
                                <td style="padding: 0.875rem 1rem; vertical-align: top;">
                                    <div style="color: var(--doc-text-primary); font-size: 0.75rem;">
                                        {{ $activity->description }}
                                    </div>

                                    @if(isset($properties['note']))
                                        <div style="margin-top: 0.375rem; font-style: italic; color: var(--doc-text-secondary); background-color: var(--doc-surface-muted); padding: 0.375rem 0.5rem; border-radius: 0.25rem; border: 1px solid var(--doc-border);">
                                            "{{ $properties['note'] }}"
                                        </div>
                                    @endif

                                    @if($hasDiff)
                                        <div class="doc-diff-box" style="margin-top: 0.5rem; padding: 0.5rem 0.75rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); border-radius: 0.375rem; font-size: 0.6875rem;">
                                            <div style="font-weight: 700; color: var(--doc-text-primary); margin-bottom: 0.25rem;">
                                                Rincian Perubahan Kolom:
                                            </div>
                                            @foreach($properties['attributes'] as $key => $newValue)
                                                @php
                                                    $oldValue = $properties['old'][$key] ?? null;
                                                @endphp
                                                @if($oldValue != $newValue)
                                                    <div class="doc-diff-line" style="display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; margin-top: 0.25rem;">
                                                        <span class="doc-diff-key" style="font-family: ui-monospace, monospace; color: var(--doc-text-muted); font-weight: 600;">
                                                            {{ $key }}:
                                                        </span>
                                                        <span class="doc-diff-old" style="text-decoration: line-through; color: #ef4444;">
                                                            {{ is_array($oldValue) ? json_encode($oldValue) : ($oldValue ?? 'null') }}
                                                        </span>
                                                        <span style="color: var(--doc-text-muted);">→</span>
                                                        <span class="doc-diff-new" style="color: #10b981; font-weight: 600;">
                                                            {{ is_array($newValue) ? json_encode($newValue) : ($newValue ?? 'null') }}
                                                        </span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
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
