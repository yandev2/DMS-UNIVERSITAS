@props([
    'record',
])

@php
    $activity = $record;
    $properties = $activity->properties ? (is_array($activity->properties) ? $activity->properties : $activity->properties->toArray()) : [];

    $newAttributes = $properties['attributes'] ?? $activity->attribute_changes['attributes'] ?? [];
    $oldAttributes = $properties['old'] ?? $activity->attribute_changes['old'] ?? [];

    $allKeys = array_unique(array_merge(array_keys($oldAttributes), array_keys($newAttributes)));

    // Extra custom properties outside attributes/old
    $extraProperties = array_diff_key($properties, array_flip(['attributes', 'old']));

    $causer = $activity->causer;
    $eventColor = $activity->getEventColor();
    $eventLabel = $activity->getEventLabel();
    $eventIcon = $activity->getEventIcon();
    $humanCategory = $activity->getHumanLogCategory();
    $clientInfo = $activity->getParsedUserAgent();
    $subject = $activity->subject;
    $isDocument = $subject instanceof \App\Models\Document;
@endphp

@include('filament.components.document-timeline-styles')

<div class="audit-modal-container" style="display: flex; flex-direction: column; gap: 1.25rem; font-size: 0.8125rem;">

    {{-- =====================================================================
         1. HERO HEADER BANNER (EVENT SUMMARY & METADATA)
         ===================================================================== --}}
    <div style="background: linear-gradient(135deg, var(--doc-surface) 0%, var(--doc-surface-muted) 100%); border: 1px solid var(--doc-border); border-radius: 0.75rem; padding: 1.25rem 1.5rem; box-shadow: var(--doc-shadow-sm); display: flex; align-items: center; justify-content: space-between; gap: 1.25rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 1rem; min-width: 0;">
            {{-- Event Large Icon Badge --}}
            <div style="width: 3.25rem; height: 3.25rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background-color: var(--doc-badge-{{ $eventColor }}-bg); color: var(--doc-badge-{{ $eventColor }}-solid); border: 1.5px solid var(--doc-badge-{{ $eventColor }}-border);">
                <x-dynamic-component :component="$eventIcon" style="width: 1.75rem; height: 1.75rem;" />
            </div>

            <div style="min-width: 0;">
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                    <span class="doc-badge doc-badge-{{ $eventColor }}" style="padding: 0.2rem 0.6rem; font-size: 0.6875rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase;">
                        {{ $eventLabel }}
                    </span>
                    <span style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 0.375rem; background-color: var(--doc-surface-subtle); color: var(--doc-text-muted); border: 1px solid var(--doc-border); font-family: monospace; font-weight: 600;">
                        Log #{{ $activity->id }}
                    </span>
                    <span style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 0.375rem; background-color: var(--doc-surface-subtle); color: var(--doc-text-muted); border: 1px solid var(--doc-border); font-weight: 600;">
                        {{ $humanCategory }}
                    </span>
                </div>

                <h3 style="font-size: 1.0625rem; font-weight: 800; color: var(--doc-text-primary); margin: 0; line-height: 1.35; word-break: break-word;">
                    {{ $activity->description ?: 'Perekaman aktivitas sistem' }}
                </h3>
            </div>
        </div>

        {{-- Timestamp Box --}}
        <div style="text-align: right; background-color: var(--doc-surface); padding: 0.625rem 1rem; border-radius: 0.5rem; border: 1px solid var(--doc-border); flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; font-weight: 700; color: var(--doc-text-primary);">
                <x-heroicon-o-calendar style="width: 1rem; height: 1rem; color: var(--doc-text-muted); flex-shrink: 0;" />
                <span>{{ $activity->created_at ? $activity->created_at->translatedFormat('d F Y, H:i:s') : '-' }} WIB</span>
            </div>
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.25rem; font-size: 0.75rem; color: var(--doc-text-muted); margin-top: 0.25rem;">
                <x-heroicon-o-clock style="width: 0.875rem; height: 0.875rem;" />
                <span>{{ $activity->created_at ? $activity->created_at->diffForHumans() : '' }}</span>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         2. BALANCED 2-COLUMN CONTEXT GRID: AKTOR & OBJEK TERDAMPAK
         ===================================================================== --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem;">
        
        {{-- CARD A: IDENTITAS PELAKU & JARINGAN --}}
        <div style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.75rem; padding: 1.125rem 1.25rem; box-shadow: var(--doc-shadow-sm); display: flex; flex-direction: column; gap: 0.875rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--doc-border-subtle); padding-bottom: 0.625rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--doc-text-muted); font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                    <x-heroicon-o-user-circle style="width: 1.125rem; height: 1.125rem; color: var(--doc-badge-info-solid);" />
                    <span>Pelaku Aksi & Lingkungan Sesi</span>
                </div>
                <span class="doc-badge doc-badge-gray" style="font-size: 0.6875rem; padding: 0.15rem 0.5rem;">
                    {{ $causer ? 'User Terautentikasi' : 'Proses Sistem' }}
                </span>
            </div>

            {{-- Actor Profile Info --}}
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                @if($causer)
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.875rem; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        {{ strtoupper(substr($causer->name, 0, 2)) }}
                    </div>
                    <div style="min-width: 0;">
                        <div style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); line-height: 1.25; word-break: break-word;">
                            {{ $causer->name }}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--doc-text-muted); margin-top: 0.125rem;">
                            {{ $causer->email }}
                        </div>
                    </div>
                @else
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; background-color: var(--doc-surface-subtle); border: 1.5px solid var(--doc-border); color: var(--doc-text-muted); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-heroicon-o-cpu-chip style="width: 1.35rem; height: 1.35rem;" />
                    </div>
                    <div>
                        <div style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary);">
                            Sistem / Otomatisasi Kampus
                        </div>
                        <div style="font-size: 0.75rem; color: var(--doc-text-muted);">
                            Daemon Scheduler / System Automation
                        </div>
                    </div>
                @endif
            </div>

            {{-- Client & Network Details --}}
            <div style="margin-top: auto; padding-top: 0.75rem; border-top: 1px dashed var(--doc-border); display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <span style="font-size: 0.6875rem; color: var(--doc-text-muted); font-weight: 700; text-transform: uppercase;">Alamat IP</span>
                    <div style="display: flex; align-items: center; gap: 0.375rem; margin-top: 0.25rem;">
                        <x-heroicon-o-shield-check style="width: 0.9375rem; height: 0.9375rem; color: var(--doc-badge-teal-solid); flex-shrink: 0;" />
                        <code style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); background-color: var(--doc-surface-subtle); padding: 0.15rem 0.4rem; border-radius: 0.25rem; border: 1px solid var(--doc-border);">
                            {{ $activity->ip_address ?: '127.0.0.1' }}
                        </code>
                    </div>
                </div>

                <div>
                    <span style="font-size: 0.6875rem; color: var(--doc-text-muted); font-weight: 700; text-transform: uppercase;">Perangkat & Browser</span>
                    <div style="display: flex; align-items: center; gap: 0.375rem; margin-top: 0.25rem;" title="{{ $activity->user_agent }}">
                        <x-dynamic-component :component="$clientInfo['icon']" style="width: 0.9375rem; height: 0.9375rem; color: var(--doc-badge-info-solid); flex-shrink: 0;" />
                        <span style="font-size: 0.75rem; font-weight: 600; color: var(--doc-text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $clientInfo['name'] }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- CARD B: OBJEK & TARGET MUTASI --}}
        <div style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.75rem; padding: 1.125rem 1.25rem; box-shadow: var(--doc-shadow-sm); display: flex; flex-direction: column; gap: 0.875rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--doc-border-subtle); padding-bottom: 0.625rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--doc-text-muted); font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                    <x-heroicon-o-document-text style="width: 1.125rem; height: 1.125rem; color: var(--doc-badge-purple-solid);" />
                    <span>Objek Target / Entitas Terdampak</span>
                </div>
                @if($activity->subject_type)
                    <span class="doc-badge doc-badge-purple" style="font-size: 0.6875rem; padding: 0.15rem 0.5rem;">
                        {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                    </span>
                @endif
            </div>

            {{-- Subject Details --}}
            <div style="min-width: 0;">
                @if($isDocument && $subject)
                    <div style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); line-height: 1.35; margin-bottom: 0.375rem;">
                        {{ $subject->title }}
                    </div>
                    @if($subject->document_number)
                        <div style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-family: monospace; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border);">
                            <x-heroicon-o-hashtag style="width: 0.875rem; height: 0.875rem;" />
                            <span>{{ $subject->document_number }}</span>
                        </div>
                    @endif
                @else
                    <div style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); line-height: 1.35;">
                        {{ $activity->getFormattedSubjectTitle() }}
                    </div>
                @endif
            </div>

            {{-- Subject Quick Meta --}}
            <div style="margin-top: auto; padding-top: 0.75rem; border-top: 1px dashed var(--doc-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <span style="font-size: 0.75rem; color: var(--doc-text-muted);">
                    Modul: <strong style="color: var(--doc-text-primary);">{{ $humanCategory }}</strong>
                </span>

                @if($isDocument && $subject)
                    <a href="{{ route('filament.admin.resources.documents.view', ['record' => $subject->id]) }}" target="_blank" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 700; color: #2563eb; text-decoration: none; padding: 0.2rem 0.5rem; border-radius: 0.375rem; background-color: rgba(37, 99, 235, 0.08); transition: background-color 0.2s;">
                        <span>Buka Detail Naskah</span>
                        <x-heroicon-m-arrow-top-right-on-square style="width: 0.875rem; height: 0.875rem;" />
                    </a>
                @endif
            </div>
        </div>

    </div>

    {{-- =====================================================================
         3. PERUBAHAN NILAI ATRIBUT (MODERN VISUAL DIFF CARDS)
         ===================================================================== --}}
    @if(! empty($allKeys))
        <div style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.75rem; box-shadow: var(--doc-shadow-sm); overflow: hidden;">
            {{-- Header Diff Section --}}
            <div style="padding: 0.875rem 1.25rem; background-color: var(--doc-surface-muted); border-bottom: 1px solid var(--doc-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); display: flex; align-items: center; justify-content: center;">
                        <x-heroicon-o-arrows-right-left style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                    <div>
                        <span style="font-size: 0.875rem; font-weight: 800; color: var(--doc-text-primary);">
                            Perbandingan Nilai Atribut (Visual Diff)
                        </span>
                        <span style="font-size: 0.75rem; color: var(--doc-text-muted); margin-left: 0.375rem;">
                            Rekaman komparasi data sebelum dan sesudah perubahan
                        </span>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 0.375rem; background-color: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.25);">
                        <span>− Nilai Lama</span>
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 0.375rem; background-color: rgba(34, 197, 94, 0.1); color: #10b981; border: 1px solid rgba(34, 197, 94, 0.25);">
                        <span>+ Nilai Baru</span>
                    </span>
                    <span class="doc-badge doc-badge-info" style="font-size: 0.75rem; padding: 0.2rem 0.6rem; font-weight: 700;">
                        {{ count($allKeys) }} Kolom Diperbarui
                    </span>
                </div>
            </div>

            {{-- Body Diff Cards List --}}
            <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                @foreach($allKeys as $key)
                    @php
                        $oldValRaw = $oldAttributes[$key] ?? null;
                        $newValRaw = $newAttributes[$key] ?? null;

                        $fieldLabel = \App\Models\ActivityLog::getFieldLabel($key);
                        $formattedOld = \App\Models\ActivityLog::formatDiffValue($key, $oldValRaw);
                        $formattedNew = \App\Models\ActivityLog::formatDiffValue($key, $newValRaw);
                    @endphp

                    <div style="border: 1px solid var(--doc-border); border-radius: 0.625rem; background-color: var(--doc-surface-muted); overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                        {{-- Field Bar --}}
                        <div style="padding: 0.625rem 1rem; background-color: var(--doc-surface); border-bottom: 1px solid var(--doc-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 0.8125rem; font-weight: 700; color: var(--doc-text-primary);">
                                    {{ $fieldLabel }}
                                </span>
                                <code style="font-size: 0.6875rem; font-family: monospace; font-weight: 600; padding: 0.15rem 0.4rem; border-radius: 0.25rem; background-color: var(--doc-surface-subtle); color: var(--doc-text-muted); border: 1px solid var(--doc-border);">
                                    {{ $key }}
                                </code>
                            </div>
                            <span class="doc-badge doc-badge-gray" style="font-size: 0.6875rem; padding: 0.15rem 0.5rem;">
                                Perubahan Nilai
                            </span>
                        </div>

                        {{-- Side-by-Side Comparison Container --}}
                        <div style="display: grid; grid-template-columns: 1fr auto 1fr; align-items: stretch; gap: 0; position: relative;">
                            
                            {{-- LEFT: NILAI SEBELUM (OLD) --}}
                            <div style="padding: 1rem 1.125rem; background: rgba(239, 68, 68, 0.05); border-right: 1px dashed rgba(239, 68, 68, 0.2); display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.5rem;">
                                        <span style="display: inline-flex; width: 1.125rem; height: 1.125rem; border-radius: 9999px; background-color: #ef4444; color: #ffffff; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; line-height: 1;">−</span>
                                        <span style="font-size: 0.6875rem; font-weight: 800; color: #dc2626; text-transform: uppercase; letter-spacing: 0.04em;">Sebelum Diubah</span>
                                    </div>
                                    <div style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); line-height: 1.4; word-break: break-word;">
                                        {{ $formattedOld['display'] }}
                                    </div>
                                </div>

                                @if($formattedOld['is_date'])
                                    <div style="margin-top: 0.5rem; font-size: 0.6875rem; font-family: monospace; color: var(--doc-text-muted);">
                                        Raw DB: {{ $formattedOld['raw'] }}
                                    </div>
                                @endif
                            </div>

                            {{-- MIDDLE: ARROW INDICATOR --}}
                            <div style="display: flex; align-items: center; justify-content: center; padding: 0 0.5rem; background-color: var(--doc-surface); border-left: 1px solid var(--doc-border); border-right: 1px solid var(--doc-border); z-index: 1;">
                                <div style="width: 1.75rem; height: 1.75rem; border-radius: 9999px; background-color: var(--doc-surface-subtle); border: 1.5px solid var(--doc-border); display: flex; align-items: center; justify-content: center; color: var(--doc-text-muted);">
                                    <x-heroicon-m-arrow-right style="width: 1rem; height: 1rem;" />
                                </div>
                            </div>

                            {{-- RIGHT: NILAI SESUDAH (NEW) --}}
                            <div style="padding: 1rem 1.125rem; background: rgba(34, 197, 94, 0.06); display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.5rem;">
                                        <span style="display: inline-flex; width: 1.125rem; height: 1.125rem; border-radius: 9999px; background-color: #10b981; color: #ffffff; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; line-height: 1;">+</span>
                                        <span style="font-size: 0.6875rem; font-weight: 800; color: #16a34a; text-transform: uppercase; letter-spacing: 0.04em;">Setelah Diubah</span>
                                    </div>
                                    <div style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); line-height: 1.4; word-break: break-word;">
                                        {{ $formattedNew['display'] }}
                                    </div>
                                </div>

                                @if($formattedNew['is_date'])
                                    <div style="margin-top: 0.5rem; font-size: 0.6875rem; font-family: monospace; color: var(--doc-text-muted);">
                                        Raw DB: {{ $formattedNew['raw'] }}
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- =====================================================================
         4. PARAMETER & PROPERTI KONTEKSTUAL TAMBAHAN (JIKA ADA)
         ===================================================================== --}}
    @if(! empty($extraProperties))
        <div style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: var(--doc-shadow-sm);">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; font-size: 0.75rem; font-weight: 800; color: var(--doc-text-muted); text-transform: uppercase; letter-spacing: 0.04em;">
                <x-heroicon-o-information-circle style="width: 1rem; height: 1rem; color: var(--doc-badge-info-solid);" />
                <span>Parameter & Informasi Tambahan</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                @foreach($extraProperties as $propKey => $propVal)
                    <div style="padding: 0.625rem 0.875rem; border-radius: 0.5rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border);">
                        <span style="font-size: 0.6875rem; font-family: monospace; color: var(--doc-text-muted); font-weight: 700;">{{ $propKey }}</span>
                        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--doc-text-primary); margin-top: 0.25rem; word-break: break-word;">
                            {{ is_scalar($propVal) ? (is_bool($propVal) ? ($propVal ? 'true' : 'false') : $propVal) : json_encode($propVal, JSON_UNESCAPED_SLASHES) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- =====================================================================
         5. REKAMAN DATA FORENSIK MENTAH (RAW JSON PAYLOAD)
         ===================================================================== --}}
    <div x-data="{ open: false }" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.75rem; overflow: hidden; box-shadow: var(--doc-shadow-sm);">
        <button 
            type="button" 
            @click="open = !open" 
            style="width: 100%; padding: 0.875rem 1.25rem; display: flex; align-items: center; justify-content: space-between; background: none; border: none; cursor: pointer; text-align: left; transition: background-color 0.15s;"
            onmouseover="this.style.backgroundColor='var(--doc-surface-muted)'"
            onmouseout="this.style.backgroundColor='transparent'"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <x-heroicon-o-code-bracket style="width: 1.125rem; height: 1.125rem; color: var(--doc-text-muted);" />
                <span style="font-size: 0.8125rem; font-weight: 700; color: var(--doc-text-primary);">
                    Inspeksi Rekaman Forensik Mentah (Raw JSON Payload)
                </span>
                <span class="doc-badge doc-badge-gray" style="font-size: 0.6875rem; padding: 0.15rem 0.4rem;">
                    {{ strlen(json_encode($activity->toArray())) }} bytes
                </span>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--doc-text-muted);">
                <span style="font-size: 0.75rem;" x-text="open ? 'Sembunyikan' : 'Buka JSON'">Buka JSON</span>
                <x-heroicon-m-chevron-down 
                    style="width: 1rem; height: 1rem; transition: transform 0.2s;" 
                    ::style="open ? 'transform: rotate(180deg); width: 1rem; height: 1rem;' : 'width: 1rem; height: 1rem;'" 
                />
            </div>
        </button>

        <div x-show="open" style="display: none; padding: 1rem 1.25rem; background-color: #090d16; border-top: 1px solid var(--doc-border);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="font-size: 0.6875rem; font-family: monospace; color: #64748b; font-weight: 700; text-transform: uppercase;">activity_log.json</span>
                <span style="font-size: 0.6875rem; color: #64748b;">Read-Only Audit Snapshot</span>
            </div>
            <pre style="margin: 0; padding: 1rem; background-color: #030712; color: #38bdf8; font-family: 'Courier New', Courier, monospace; font-size: 0.75rem; border-radius: 0.5rem; max-height: 16rem; overflow: auto; line-height: 1.4; border: 1px solid rgba(255,255,255,0.08);">{{ json_encode($activity->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>

</div>
