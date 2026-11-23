@props([
    'document',
])

@php
    use App\Enums\VisibilityLevel;

    $primaryFile = $document->primaryFile ?? $document->files()->where('is_active', true)->first();
    $isPdf = $primaryFile && ($primaryFile->file_extension === 'pdf' || $primaryFile->mime_type === 'application/pdf');
    $unit = $document->unit;
    $category = $document->category;
    $academicYear = $document->academicYear;
    $criteria = $document->accreditationCriteria;
    $replacedDoc = $document->replacedDocument;
    $uploader = $document->uploader;
    $canDownload = auth()->user()?->can('downloadPrivate', $document);
    $streamUrl = route('admin.documents.stream', $document);
    $downloadUrl = route('admin.documents.download', $document);

    // Hitung status masa berlaku jika ada
    $isExpired = false;
    $daysRemaining = null;
    if ($document->has_expiry && $document->expired_date) {
        $now = now()->startOfDay();
        $expiry = $document->expired_date->copy()->startOfDay();
        $isExpired = $expiry->isPast();
        $daysRemaining = (int) $now->diffInDays($expiry, false);
    }
@endphp

@include('filament.components.document-timeline-styles')

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
    {{-- Card 1: Identitas Resmi & Klasifikasi Naskah Dinas --}}
    <div class="doc-card" style="margin-bottom: 0; color: var(--doc-text-primary);">
        <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border); margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="doc-card-title-icon">
                    <x-heroicon-o-document-text class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                </div>
                <div>
                    <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                        Identitas Resmi & Klasifikasi Naskah Dinas
                    </h3>
                    <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                        Informasi nomor surat, kepemilikan unit kerja, dan ringkasan isi dokumen.
                    </p>
                </div>
            </div>

            <div class="doc-badge-group">
                @if($document->document_number)
                    <span class="doc-badge doc-badge-info doc-badge-mono" style="font-size: 0.8125rem; padding: 0.3125rem 0.75rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border); font-weight: 700;">
                        <x-heroicon-m-tag class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                        {{ $document->document_number }}
                    </span>
                @else
                    <span class="doc-badge doc-badge-warning" style="font-size: 0.75rem; padding: 0.3125rem 0.625rem; background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);">
                        <x-heroicon-m-clock class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                        MENUNGGU PENGESAHAN (DRAF)
                    </span>
                @endif
            </div>
        </div>

        {{-- Judul Naskah Dinas --}}
        <div style="margin-bottom: 1.25rem;">
            <span class="doc-spec-label">
                Judul Dokumen / Nama Naskah Dinas
            </span>
            <div style="font-size: 1.125rem; font-weight: 700; color: var(--doc-text-primary); line-height: 1.5rem; margin-top: 0.375rem;">
                {{ $document->title }}
            </div>
        </div>

        {{-- Ringkasan / Abstrak Callout --}}
        <div style="margin-bottom: 1.25rem;">
            <span class="doc-spec-label">
                Ringkasan / Abstrak Naskah Dinas
            </span>
            <div class="doc-meta-box" style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--doc-text-secondary); line-height: 1.45rem;">
                @if($document->description)
                    {{ $document->description }}
                @else
                    <span style="font-style: italic; color: var(--doc-text-muted);">Tidak ada ringkasan atau uraian tambahan yang dilampirkan.</span>
                @endif
            </div>
        </div>

        {{-- Spacious 2-Column Property Grid --}}
        <div class="doc-property-grid">
            {{-- Kolom 1: Tata Kelola & Klasifikasi Dokumen --}}
            <div class="doc-property-col">
                {{-- Unit Kerja --}}
                <div class="doc-property-row">
                    <span class="doc-property-label">Unit Kerja / Pengusul</span>
                    <div style="display: flex; align-items: flex-start; gap: 0.625rem; margin-top: 0.125rem;">
                        <x-heroicon-m-building-office class="doc-icon-sm" style="width: 1.125rem; height: 1.125rem; color: var(--doc-text-muted); margin-top: 0.125rem; flex-shrink: 0;" />
                        <div class="doc-property-value">
                            <span>{{ $unit?->name ?? 'Universitas' }}</span>
                            @if($unit?->code)
                                <span style="font-weight: 700; color: var(--doc-text-muted); margin-left: 0.375rem;">({{ $unit->code }})</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Kategori Dokumen --}}
                <div class="doc-property-row">
                    <span class="doc-property-label">Kategori Klasifikasi</span>
                    <div style="display: flex; align-items: flex-start; gap: 0.625rem; margin-top: 0.125rem;">
                        <x-heroicon-m-tag class="doc-icon-sm" style="width: 1.125rem; height: 1.125rem; color: var(--doc-text-muted); margin-top: 0.125rem; flex-shrink: 0;" />
                        <div class="doc-property-value">
                            {{ $category?->name ?? 'Naskah Dinas' }}
                        </div>
                    </div>
                </div>

                {{-- Tahun Akademik --}}
                <div class="doc-property-row">
                    <span class="doc-property-label">Tahun Akademik</span>
                    <div style="display: flex; align-items: center; gap: 0.625rem; margin-top: 0.125rem;">
                        <x-heroicon-m-calendar class="doc-icon-sm" style="width: 1.125rem; height: 1.125rem; color: var(--doc-text-muted); flex-shrink: 0;" />
                        <div class="doc-property-value">
                            {{ $academicYear?->name ?? 'Semua Periode' }}
                        </div>
                    </div>
                </div>

                {{-- Aksesibilitas --}}
                <div class="doc-property-row" style="border-bottom: none; padding-bottom: 0;">
                    <span class="doc-property-label">Tingkat Aksesibilitas Berkas</span>
                    <div style="margin-top: 0.25rem;">
                        @if($document->visibility === VisibilityLevel::Public)
                            <span class="doc-badge doc-badge-success" style="background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);">
                                <x-heroicon-m-globe-alt class="doc-icon-xs" />
                                {{ $document->visibility->getLabel() }}
                            </span>
                        @elseif($document->visibility === VisibilityLevel::Confidential)
                            <span class="doc-badge doc-badge-danger" style="background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-text); border: 1px solid var(--doc-badge-danger-border);">
                                <x-heroicon-m-lock-closed class="doc-icon-xs" />
                                {{ $document->visibility->getLabel() }}
                            </span>
                        @else
                            <span class="doc-badge doc-badge-info" style="background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);">
                                <x-heroicon-m-shield-check class="doc-icon-xs" />
                                {{ $document->visibility?->getLabel() ?? 'Internal Civitas Unit' }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Kolom 2: Legalitas & Masa Berlaku --}}
            <div class="doc-property-col">
                {{-- Pejabat Penandatangan --}}
                <div class="doc-property-row">
                    <span class="doc-property-label">Pejabat Pengesah / Penandatangan</span>
                    <div style="display: flex; align-items: flex-start; gap: 0.625rem; margin-top: 0.125rem;">
                        <x-heroicon-m-user-circle class="doc-icon-sm" style="width: 1.125rem; height: 1.125rem; color: var(--doc-text-muted); margin-top: 0.125rem; flex-shrink: 0;" />
                        <div>
                            @if($document->signer_name_snapshot)
                                <div class="doc-property-value" style="font-weight: 700;">
                                    {{ $document->signer_name_snapshot }}
                                </div>
                                @if($document->signer_position_snapshot)
                                    <div style="font-size: 0.75rem; color: var(--doc-text-muted); margin-top: 0.125rem; line-height: 1.15rem;">
                                        {{ $document->signer_position_snapshot }}
                                    </div>
                                @endif
                                @if($document->final_scan_uploaded_at)
                                    <div style="font-size: 0.75rem; color: var(--doc-badge-success-solid); margin-top: 0.25rem; font-weight: 600;">
                                        Scan TTD Basah diarsipkan oleh {{ $document->finalScanUploader?->name ?? 'Unit / Humas' }} ({{ $document->final_scan_uploaded_at->translatedFormat('d M Y, H:i') }})
                                    </div>
                                @endif
                            @else
                                <span style="font-size: 0.8125rem; color: var(--doc-text-muted); font-style: italic;">
                                    Dicatat saat persetujuan resmi
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Tanggal Terbit --}}
                <div class="doc-property-row">
                    <span class="doc-property-label">Tanggal Pengesahan Resmi</span>
                    <div style="display: flex; align-items: center; gap: 0.625rem; margin-top: 0.125rem;">
                        <x-heroicon-m-calendar-days class="doc-icon-sm" style="width: 1.125rem; height: 1.125rem; color: var(--doc-text-muted); flex-shrink: 0;" />
                        <div class="doc-property-value">
                            @if($document->issued_date)
                                {{ $document->issued_date->translatedFormat('d F Y') }}
                            @elseif($document->approved_at)
                                {{ $document->approved_at->translatedFormat('d F Y') }}
                            @else
                                <span style="color: var(--doc-text-muted); font-style: italic;">Belum disahkan</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Metode Penomoran --}}
                <div class="doc-property-row">
                    <span class="doc-property-label">Metode Penomoran Naskah</span>
                    <div style="margin-top: 0.25rem;">
                        @if($document->is_manual_number)
                            <span class="doc-badge doc-badge-warning" style="background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);">
                                <x-heroicon-m-pencil class="doc-icon-xs" />
                                Nomor Manual Fisik
                            </span>
                        @else
                            <span class="doc-badge doc-badge-success" style="background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);">
                                <x-heroicon-m-bolt class="doc-icon-xs" />
                                Otomatis Sistem
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Masa Berlaku --}}
                <div class="doc-property-row" style="border-bottom: none; padding-bottom: 0;">
                    <span class="doc-property-label">Masa Berlaku Dokumen</span>
                    <div style="margin-top: 0.25rem;">
                        @if($document->has_expiry && $document->expired_date)
                            @if($isExpired)
                                <span class="doc-badge doc-badge-danger" style="background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-text); border: 1px solid var(--doc-badge-danger-border);">
                                    Telah Kedaluwarsa ({{ $document->expired_date->translatedFormat('d M Y') }})
                                </span>
                            @elseif($daysRemaining !== null && $daysRemaining <= 30)
                                <span class="doc-badge doc-badge-warning" style="background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);">
                                    {{ $daysRemaining }} Hari Lagi ({{ $document->expired_date->translatedFormat('d M Y') }})
                                </span>
                            @else
                                <span class="doc-badge doc-badge-success" style="background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);">
                                    Aktif s.d {{ $document->expired_date->translatedFormat('d M Y') }}
                                </span>
                            @endif
                        @else
                            <span class="doc-badge doc-badge-info" style="background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);">
                                <x-heroicon-m-clock class="doc-icon-xs" />
                                Berlaku Permanen
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 2: Berkas Fisik Dokumen (Storage Privat) --}}
    <div class="doc-card" style="margin-bottom: 0; color: var(--doc-text-primary);">
        <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border); margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="doc-card-title-icon">
                    <x-heroicon-o-arrow-up-tray class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                </div>
                <div>
                    <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                        Berkas Fisik Dokumen (Storage Privat)
                    </h3>
                    <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                        Berkas disimpan di direktori privat terenkripsi dengan penamaan baku otomatis.
                    </p>
                </div>
            </div>

            @if($primaryFile)
                @if($primaryFile->stamped_file_path)
                    <span class="doc-badge doc-badge-teal" style="background-color: var(--doc-badge-teal-bg); color: var(--doc-badge-teal-text); border: 1px solid var(--doc-badge-teal-border); font-weight: 700;">
                        <x-heroicon-m-check-badge class="doc-icon-xs" />
                        Berstempel Sah & QR Code Keabsahan
                    </span>
                @else
                    <span class="doc-badge doc-badge-gray" style="background-color: var(--doc-badge-gray-bg); color: var(--doc-badge-gray-text); border: 1px solid var(--doc-badge-gray-border);">
                        <x-heroicon-m-document class="doc-icon-xs" />
                        Berkas Asli Bersih (Tanpa Stempel)
                    </span>
                @endif
            @endif
        </div>

        @if($primaryFile)
            <div class="doc-file-card">
                <div style="display: flex; align-items: center; gap: 0.875rem; min-width: 0;">
                    @if($isPdf)
                        <div class="doc-modal-file-icon doc-modal-file-icon-pdf" style="width: 2.75rem; height: 2.75rem; border-radius: 0.625rem;" title="Berkas PDF">
                            <x-heroicon-s-document-text class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                        </div>
                    @else
                        <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 2.75rem; height: 2.75rem; border-radius: 0.625rem;" title="Berkas Microsoft Word">
                            <x-heroicon-s-document class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                        </div>
                    @endif
                    <div style="min-width: 0;">
                        <div style="font-size: 0.875rem; font-weight: 600; color: var(--doc-text-primary); word-break: break-all; line-height: 1.25rem; letter-spacing: -0.01em;">
                            {{ $primaryFile->standardized_file_name ?? $primaryFile->original_file_name }}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--doc-text-muted); margin-top: 0.25rem; display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <span class="doc-modal-tag {{ $isPdf ? 'doc-modal-tag-pdf' : 'doc-modal-tag-doc' }}">
                                {{ strtoupper($primaryFile->file_extension ?? 'FILE') }}
                            </span>
                            <span style="font-family: ui-monospace, SFMono-Regular, monospace; font-weight: 500;">{{ number_format(($primaryFile->file_size ?? 0) / 1024, 1) }} KB</span>
                            @if($primaryFile->page_count)
                                <span>•</span>
                                <span>{{ $primaryFile->page_count }} Halaman</span>
                            @endif
                            @if($primaryFile->original_file_name && $primaryFile->original_file_name !== $primaryFile->standardized_file_name)
                                <span>•</span>
                                <span style="font-style: italic;">Asli: {{ Str::limit($primaryFile->original_file_name, 30) }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    @if(auth()->user()?->can('view', $document))
                        <button
                            type="button"
                            wire:click="mountAction('preview')"
                            class="doc-btn doc-btn-primary"
                        >
                            <x-heroicon-m-eye class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                            <span>Pratinjau Berkas</span>
                        </button>
                    @endif

                    @if($isPdf)
                        <a
                            href="{{ $streamUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="doc-btn doc-btn-outlined"
                        >
                            <x-heroicon-m-arrow-top-right-on-square class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                            <span>Buka di Tab Baru</span>
                        </a>
                    @endif

                    @if($canDownload)
                        <a
                            href="{{ $downloadUrl }}"
                            class="doc-btn doc-btn-outlined"
                        >
                            <x-heroicon-m-arrow-down-tray class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                            <span>Unduh Berkas</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Audit Checksum Hash --}}
            @php
                $effectiveHash = $primaryFile->getEffectiveSha256Hash();
                $isFinalStampedHash = filled($primaryFile->stamped_file_hash_sha256);
            @endphp
            @if($effectiveHash)
                <div style="margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.375rem; font-size: 0.6875rem; color: var(--doc-text-muted); font-family: ui-monospace, SFMono-Regular, monospace; background-color: var(--doc-surface-muted); padding: 0.625rem 0.875rem; border-radius: 0.625rem; border: 1px solid var(--doc-border);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <x-heroicon-m-finger-print class="doc-icon-xs" style="color: var(--doc-text-muted); flex-shrink: 0;" />
                        <span style="font-weight: 700; color: var(--doc-text-primary);">
                            {{ $isFinalStampedHash ? 'SHA-256 Final (Ber-QR):' : 'SHA-256:' }}
                        </span>
                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $effectiveHash }}</span>
                    </div>
                    @if($isFinalStampedHash && $primaryFile->file_hash_sha256 && $primaryFile->file_hash_sha256 !== $effectiveHash)
                        <div style="font-size: 0.625rem; color: var(--doc-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            SHA-256 Draf Awal: {{ $primaryFile->file_hash_sha256 }}
                        </div>
                    @endif
                </div>
            @endif
        @else
            <div class="doc-empty-state" style="padding: 1.5rem; border-radius: 0.375rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); text-align: center;">
                <p style="font-size: 0.8125rem; color: var(--doc-text-muted); margin: 0;">
                    Dokumen ini belum memiliki berkas fisik yang tersimpan di sistem.
                </p>
            </div>
        @endif
    </div>

    {{-- Grid Kolom Bawah: Borang Akreditasi & Rantai Revisi --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
        {{-- Card 3: Borang Akreditasi --}}
        <div class="doc-card" style="margin-bottom: 0; color: var(--doc-text-primary);">
            <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border); margin-bottom: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="doc-card-title-icon" style="background-color: var(--doc-badge-purple-bg) !important; color: var(--doc-badge-purple-solid) !important; border: 1px solid var(--doc-badge-purple-border);">
                        <x-heroicon-o-academic-cap class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                    <div>
                        <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                            Borang Akreditasi (BAN-PT / LAM)
                        </h3>
                        <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                            Pemetaan ke instrumen kriteria akreditasi.
                        </p>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="doc-spec-item">
                    <span class="doc-spec-label">Status Instrumen Akreditasi</span>
                    <div class="doc-spec-value" style="margin-top: 0.25rem;">
                        @if($document->is_borang)
                            <span class="doc-badge doc-badge-success" style="background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);">
                                <x-heroicon-m-check-circle class="doc-icon-xs" />
                                Bukti Borang Akreditasi (Bank Data Aktif)
                            </span>
                        @else
                            <span class="doc-badge doc-badge-gray" style="background-color: var(--doc-badge-gray-bg); color: var(--doc-badge-gray-text); border: 1px solid var(--doc-badge-gray-border);">
                                Dokumen Operasional Reguler
                            </span>
                        @endif
                    </div>
                </div>

                @if($document->is_borang)
                    <div class="doc-spec-item">
                        <span class="doc-spec-label">Kriteria Borang Terkait (K1–K9)</span>
                        <div style="margin-top: 0.25rem; display: flex; flex-wrap: wrap; gap: 0.375rem;">
                            @forelse($criteria as $criterion)
                                <span class="doc-badge doc-badge-info" style="background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);">
                                    <x-heroicon-m-bookmark class="doc-icon-xs" />
                                    {{ $criterion->name }}
                                </span>
                            @empty
                                <span style="font-size: 0.75rem; color: var(--doc-text-muted); font-style: italic;">
                                    Belum dipetakan ke kriteria borang spesifik.
                                </span>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Card 4: Rantai Revisi & Penggantian Dokumen --}}
        <div class="doc-card" style="margin-bottom: 0; color: var(--doc-text-primary);">
            <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border); margin-bottom: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div class="doc-card-title-icon" style="background-color: var(--doc-badge-warning-bg) !important; color: var(--doc-badge-warning-solid) !important; border: 1px solid var(--doc-badge-warning-border);">
                        <x-heroicon-o-arrow-path class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                    </div>
                    <div>
                        <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                            Rantai Revisi Naskah Dinas
                        </h3>
                        <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                            Pelacakan keterkaitan dengan naskah dinas terdahulu.
                        </p>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @if($replacedDoc)
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 0.875rem 1rem; border-radius: 0.375rem; background-color: var(--doc-badge-warning-bg); border: 1px solid var(--doc-badge-warning-border);">
                        <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 0;">
                            <x-heroicon-m-arrow-path class="doc-icon-sm" style="color: var(--doc-badge-warning-solid); flex-shrink: 0;" />
                            <div style="min-width: 0;">
                                <div style="font-size: 0.8125rem; font-weight: 700; color: var(--doc-badge-warning-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    Memperbarui: {{ $replacedDoc->title }}
                                </div>
                                @if($replacedDoc->document_number)
                                    <div style="font-size: 0.6875rem; font-family: ui-monospace, monospace; color: var(--doc-badge-warning-text); opacity: 0.85;">
                                        No: {{ $replacedDoc->document_number }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        <a
                            href="{{ route('filament.admin.resources.documents.view', $replacedDoc) }}"
                            class="doc-btn doc-btn-outlined"
                            style="font-size: 0.6875rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; background-color: var(--doc-surface); color: var(--doc-text-primary); border: 1px solid var(--doc-border-strong);"
                        >
                            Buka Dokumen Lama
                        </a>
                    </div>
                @else
                    <div class="doc-spec-item">
                        <span class="doc-spec-label">Status Keterkaitan</span>
                        <div class="doc-spec-value" style="color: var(--doc-text-muted); font-size: 0.8125rem; margin-top: 0.25rem;">
                            Dokumen Mandiri — bukan merupakan revisi atau pengganti naskah dinas terdahulu.
                        </div>
                    </div>
                @endif

                {{-- Audit Ringkas Pengunggah --}}
                <div style="padding-top: 0.5rem; border-top: 1px solid var(--doc-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; font-size: 0.6875rem; color: var(--doc-text-muted);">
                    <div>
                        Diunggah oleh <strong>{{ $uploader?->name ?? 'Sistem' }}</strong>
                    </div>
                    <div>
                        {{ $document->created_at->translatedFormat('d M Y, H:i') }} WIB
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
