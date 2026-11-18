@props([
    'post',
])

@php
    $activities = \App\Models\ActivityLog::query()
        ->where('subject_type', \App\Models\Post::class)
        ->where('subject_id', $post->id)
        ->with('causer')
        ->latest('id')
        ->get();
@endphp

@include('filament.components.post-view-styles')

<div style="display: flex; flex-direction: column; gap: 1rem;">
    {{-- Summary Bar --}}
    <div class="doc-card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.25rem; background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="display: flex; width: 2.25rem; height: 2.25rem; align-items: center; justify-content: center; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); font-weight: 700; flex-shrink: 0;">
                <x-heroicon-o-shield-check style="width: 1.25rem; height: 1.25rem;" />
            </span>
            <div>
                <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem;">
                    Jejak Rekam Aktivitas & Moderasi Humas (CMS Audit Trail)
                </h4>
                <p style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                    Mencatat setiap tindakan pengajuan, kurasi, perubahan status, penyuntingan naskah, dan alamat IP.
                </p>
            </div>
        </div>

        <span class="doc-badge doc-badge-gray" style="background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); font-weight: 700; font-size: 0.75rem; padding: 0.35rem 0.625rem;">
            Total {{ $activities->count() }} Aktivitas
        </span>
    </div>

    @if($activities->isEmpty())
        <div class="doc-empty-state" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); padding: 3rem 1.5rem; text-align: center; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
            <div style="margin: 0 auto; display: flex; width: 3.5rem; height: 3.5rem; align-items: center; justify-content: center; border-radius: 9999px; background-color: var(--doc-surface-muted); color: var(--doc-text-muted); border: 1px solid var(--doc-border);">
                <x-heroicon-o-clock style="width: 1.75rem; height: 1.75rem;" />
            </div>
            <h4 style="margin-top: 1rem; font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary);">
                Belum Ada Catatan Moderasi
            </h4>
            <p style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 28rem; margin-left: auto; margin-right: auto; line-height: 1.375rem;">
                Setiap aksi editorial (pengajuan draf, persetujuan humas, revisi redaksional, perubahan data) akan terekam secara otomatis di sini.
            </p>
        </div>
    @else
        <div class="doc-card" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); box-shadow: var(--doc-shadow-sm); overflow: hidden; padding: 0;">
            <div style="overflow-x: auto; width: 100%;">
                <table style="width: 100%; text-align: left; font-size: 0.75rem; border-collapse: collapse;">
                    <thead style="background-color: var(--doc-surface-muted); color: var(--doc-text-muted); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--doc-border);">
                        <tr>
                            <th style="padding: 0.75rem 1rem;">Waktu</th>
                            <th style="padding: 0.75rem 1rem;">Aksi / Peristiwa</th>
                            <th style="padding: 0.75rem 1rem;">Pelaku (User)</th>
                            <th style="padding: 0.75rem 1rem;">Keterangan & Perubahan</th>
                        </tr>
                    </thead>
                    <tbody style="divide-y divide-gray-200; color: var(--doc-text-secondary);">
                        @foreach($activities as $act)
                            @php
                                $event = $act->event ?? 'action';
                                $eventClass = match ($event) {
                                    'created' => 'post-badge-published',
                                    'submitted' => 'post-badge-pending',
                                    'published' => 'post-badge-published',
                                    'revision_requested' => 'post-badge-revision',
                                    'archived' => 'post-badge-archived',
                                    default => 'post-badge-draft',
                                };
                            @endphp
                            <tr style="border-bottom: 1px solid var(--doc-border);">
                                <td style="padding: 0.75rem 1rem; white-space: nowrap; font-family: ui-monospace, monospace; color: var(--doc-text-muted);">
                                    <div style="font-weight: 700; color: var(--doc-text-primary);">
                                        {{ $act->created_at->format('d/m/Y') }}
                                    </div>
                                    <div style="font-size: 0.6875rem;">
                                        {{ $act->created_at->format('H:i:s') }} WIB
                                    </div>
                                    <div style="font-size: 0.625rem; color: var(--doc-text-muted);">
                                        ({{ $act->created_at->diffForHumans() }})
                                    </div>
                                </td>

                                <td style="padding: 0.75rem 1rem;">
                                    <span class="post-badge {{ $eventClass }}" style="font-size: 0.6875rem; padding: 0.15rem 0.45rem;">
                                        {{ strtoupper($event) }}
                                    </span>
                                </td>

                                <td style="padding: 0.75rem 1rem;">
                                    @if($act->causer)
                                        <div style="font-weight: 700; color: var(--doc-text-primary);">
                                            {{ $act->causer->name }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: var(--doc-text-muted);">
                                            {{ $act->causer->email }}
                                        </div>
                                    @else
                                        <span style="color: var(--doc-text-muted); font-style: italic;">Sistem Otomatis</span>
                                    @endif
                                </td>

                                <td style="padding: 0.75rem 1rem; line-height: 1.35rem;">
                                    <div style="font-weight: 600; color: var(--doc-text-primary);">
                                        {{ $act->description ?: 'Pembaruan data artikel' }}
                                    </div>

                                    @if(!empty($act->properties['attributes']) || !empty($act->properties['old']))
                                        <div style="margin-top: 0.375rem; font-size: 0.6875rem; color: var(--doc-text-muted); background-color: var(--doc-surface-muted); padding: 0.35rem 0.5rem; border-radius: 0.25rem; border: 1px solid var(--doc-border); font-family: ui-monospace, monospace;">
                                            @foreach($act->properties['attributes'] ?? [] as $key => $val)
                                                @if(is_scalar($val))
                                                    <span>{{ $key }}: <strong style="color: var(--doc-text-primary);">{{ \Illuminate\Support\Str::limit((string) $val, 40) }}</strong></span>@if(!$loop->last) &bull; @endif
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

