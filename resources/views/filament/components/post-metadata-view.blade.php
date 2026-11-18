@props([
    'post',
])

@php
    use App\Enums\PostStatus;
    use App\Filament\Resources\Documents\DocumentResource;

    $thumbnailUrl = $post->getThumbnailUrl();
    $publicUrl = $post->getPublicUrl();
    $zipUrl = $post->getZipDownloadUrl();
    $photos = $post->photos()->take(4)->get();
    $totalPhotos = $post->photos()->count();
    $postDocs = $post->postDocuments()->with('document.unit', 'document.category')->get();
@endphp

@include('filament.components.post-view-styles')

<div class="post-main-grid">
    {{-- ====================================================================
         KOLOM KIRI: KONTEN LENGKAP ARTIKEL, FOTO UTAMA & NASKAH TERKAIT
         ==================================================================== --}}
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        {{-- Card Konten Utama --}}
        <div class="post-article-box">
            {{-- Foto Sampul Utama (Cover) --}}
            @if($thumbnailUrl)
                <div class="post-cover-frame">
                    <img src="{{ $thumbnailUrl }}" alt="{{ $post->title }}" class="post-cover-img" />
                    @if(filled($post->thumbnail_caption))
                        <div class="post-cover-caption">
                            <x-heroicon-m-camera style="width: 0.875rem; height: 0.875rem; display: inline-block; vertical-align: -1px; margin-right: 0.25rem;" />
                            {{ $post->thumbnail_caption }}
                        </div>
                    @endif
                </div>
            @endif

            {{-- Judul Naskah Berita --}}
            <h2 class="post-article-title">
                {{ $post->title }}
            </h2>

            {{-- Ringkasan / Abstrak Berita (Excerpt) --}}
            @if(filled($post->excerpt))
                <div class="post-excerpt-lead">
                    <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--doc-badge-info-solid); margin-bottom: 0.25rem;">
                        Ringkasan / Abstrak Warta:
                    </div>
                    {{ $post->excerpt }}
                </div>
            @endif

            {{-- Uraian Lengkap Naskah Berita (Rich Text Content) --}}
            <div class="post-prose">
                @if(filled($post->content))
                    {!! $post->content !!}
                @else
                    <p style="font-style: italic; color: var(--doc-text-muted);">
                        Belum ada narasi atau uraian teks berita yang dicantumkan.
                    </p>
                @endif
            </div>
        </div>

        {{-- Preview Cepat Naskah Dinas Sah Terkait (jika ada) --}}
        @if($postDocs->isNotEmpty())
            <div class="post-article-box" style="padding: 1.25rem 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--doc-border);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <x-heroicon-m-document-check style="width: 1.25rem; height: 1.25rem; color: #0284c7;" />
                        <h3 style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0;">
                            Naskah Dinas Sah Terkait (Publik)
                        </h3>
                    </div>
                    <span style="font-size: 0.75rem; color: var(--doc-text-muted); font-weight: 600;">
                        {{ $postDocs->count() }} Dokumen Terlampir
                    </span>
                </div>

                @foreach($postDocs as $postDoc)
                    @php $doc = $postDoc->document; @endphp
                    @if($doc)
                        <div class="post-doc-card">
                            <div class="post-doc-left">
                                <div class="post-doc-icon-wrap">
                                    <x-heroicon-o-document-text style="width: 1.25rem; height: 1.25rem;" />
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                        <span class="doc-badge doc-badge-info doc-badge-mono" style="font-size: 0.75rem; padding: 0.15rem 0.5rem; font-weight: 700;">
                                            {{ $doc->document_number ?? 'DOKUMEN RESMI' }}
                                        </span>
                                        <span style="font-size: 0.6875rem; color: var(--doc-text-muted);">
                                            {{ $doc->unit?->name ?? 'UNIVBI' }}
                                        </span>
                                    </div>
                                    <div style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin-top: 0.25rem;">
                                        {{ $doc->title }}
                                    </div>
                                    @if(filled($postDoc->custom_button_label))
                                        <div style="font-size: 0.75rem; color: var(--doc-badge-info-solid); font-weight: 600; margin-top: 0.125rem;">
                                            Label Tombol: &ldquo;{{ $postDoc->custom_button_label }}&rdquo;
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="post-doc-actions">
                                <a href="{{ DocumentResource::getUrl('view', ['record' => $doc]) }}"
                                   target="_blank"
                                   style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 700; padding: 0.4rem 0.75rem; border-radius: 0.375rem; background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); text-decoration: none;">
                                    <x-heroicon-m-arrow-top-right-on-square style="width: 0.875rem; height: 0.875rem;" />
                                    Buka di E-Arsip
                                </a>

                                <a href="{{ route('admin.documents.download', $doc) }}"
                                   target="_blank"
                                   style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 700; padding: 0.4rem 0.75rem; border-radius: 0.375rem; background-color: #0284c7; color: #ffffff; border: 1px solid #0284c7; text-decoration: none;">
                                    <x-heroicon-m-arrow-down-tray style="width: 0.875rem; height: 0.875rem;" />
                                    Unduh Berkas
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Preview Cepat Foto Dokumentasi (Strip 4 Foto) --}}
        @if($photos->isNotEmpty())
            <div class="post-article-box" style="padding: 1.25rem 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--doc-border);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <x-heroicon-m-photo style="width: 1.25rem; height: 1.25rem; color: #2563eb;" />
                        <h3 style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0;">
                            Dokumentasi Foto Kegiatan (Pratinjau)
                        </h3>
                    </div>
                    <span style="font-size: 0.75rem; color: var(--doc-text-muted); font-weight: 600;">
                        {{ $totalPhotos }} Foto Tersedia
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 0.75rem;">
                    @foreach($photos as $photo)
                        <div style="border-radius: 0.375rem; overflow: hidden; border: 1px solid var(--doc-border); background-color: var(--doc-surface-muted); aspect-ratio: 4/3; position: relative;">
                            <img src="{{ $photo->getThumbnailUrl() }}" alt="{{ $photo->caption ?? 'Dokumentasi' }}" style="width: 100%; height: 100%; object-fit: cover;" />
                            @if($photo->is_cover)
                                <span style="position: absolute; bottom: 0.25rem; left: 0.25rem; background-color: rgba(37, 99, 235, 0.9); color: #ffffff; font-size: 0.5625rem; font-weight: 700; padding: 0.1rem 0.35rem; border-radius: 0.2rem; text-transform: uppercase;">
                                    Cover
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ====================================================================
         KOLOM KANAN: METADATA PELAKSANA, JEJAK REDAKSI & STATISTIK
         ==================================================================== --}}
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        {{-- Card 1: Detail Pelaksana & Agenda Acara --}}
        <div class="post-side-card">
            <div class="post-side-card-header">
                <x-heroicon-m-building-office style="width: 1.125rem; height: 1.125rem; color: var(--doc-badge-info-solid);" />
                <h3 class="post-side-card-title">Pelaksana & Agenda Acara</h3>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Kategori Warta</span>
                <div class="post-prop-value" style="font-weight: 700; color: var(--doc-badge-info-solid);">
                    {{ $post->category?->name ?? '-' }}
                </div>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Unit Penyelenggara</span>
                <div class="post-prop-value">
                    {{ $post->unit?->name ?? 'Universitas' }}
                    @if($post->unit?->code)
                        <span style="font-weight: 700; color: var(--doc-text-muted); font-size: 0.75rem;">({{ $post->unit->code }})</span>
                    @endif
                </div>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Tahun Akademik</span>
                <div class="post-prop-value">
                    {{ $post->academicYear?->name ?? 'Semua Periode' }}
                </div>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Tanggal Pelaksanaan Acara</span>
                <div class="post-prop-value" style="display: flex; align-items: center; gap: 0.375rem;">
                    <x-heroicon-m-calendar style="width: 0.9375rem; height: 0.9375rem; color: var(--doc-text-muted);" />
                    {{ $post->event_date?->translatedFormat('d F Y') ?? '-' }}
                </div>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Lokasi Kegiatan</span>
                <div class="post-prop-value" style="display: flex; align-items: center; gap: 0.375rem;">
                    <x-heroicon-m-map-pin style="width: 0.9375rem; height: 0.9375rem; color: var(--doc-text-muted);" />
                    {{ $post->event_location ?: 'Kampus Utama UNIVBI' }}
                </div>
            </div>
        </div>

        {{-- Card 2: Jejak Editorial & Publikasi --}}
        <div class="post-side-card">
            <div class="post-side-card-header">
                <x-heroicon-m-shield-check style="width: 1.125rem; height: 1.125rem; color: #059669;" />
                <h3 class="post-side-card-title">Jejak Editorial & Publikasi</h3>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Penulis Naskah (Author)</span>
                <div class="post-prop-value" style="font-weight: 700;">
                    {{ $post->author?->name ?? '-' }}
                    @if($post->author?->nip_nidn)
                        <div style="font-size: 0.6875rem; color: var(--doc-text-muted); font-weight: 500; margin-top: 0.125rem;">
                            NIDN/NIP: {{ $post->author->nip_nidn }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Pengajuan ke Humas</span>
                <div class="post-prop-value">
                    @if($post->submitted_at)
                        {{ $post->submitted_at->translatedFormat('d M Y, H:i') }} WIB
                    @else
                        <span style="color: var(--doc-text-muted); font-style: italic;">Belum diajukan</span>
                    @endif
                </div>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Editor / Penyetuju Humas</span>
                <div class="post-prop-value">
                    @if($post->approver)
                        <strong>{{ $post->approver->name }}</strong>
                        @if($post->approved_at)
                            <div style="font-size: 0.6875rem; color: var(--doc-text-muted); margin-top: 0.125rem;">
                                Disetujui: {{ $post->approved_at->translatedFormat('d M Y, H:i') }} WIB
                            </div>
                        @endif
                    @else
                        <span style="color: var(--doc-text-muted); font-style: italic;">Menunggu moderasi</span>
                    @endif
                </div>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Jadwal Tayang Publik</span>
                <div class="post-prop-value">
                    @if($post->published_at)
                        <strong style="color: #059669;">{{ $post->published_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                    @else
                        <span style="color: var(--doc-text-muted); font-style: italic;">Belum dijadwalkan</span>
                    @endif
                </div>
            </div>

            <div class="post-prop-item">
                <span class="post-prop-label">Akses Paket Foto ZIP</span>
                <div class="post-prop-value">
                    @if($post->allow_public_zip_download)
                        <span style="color: #059669; font-weight: 700; display: inline-flex; align-items: center; gap: 0.25rem;">
                            <x-heroicon-m-check-circle style="width: 0.875rem; height: 0.875rem;" />
                            Terbuka untuk Publik
                        </span>
                    @else
                        <span style="color: var(--doc-text-muted); font-style: italic;">Dibatasi internal</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 3: Statistik Keterbacaan & Pengunjung --}}
        <div class="post-side-card">
            <div class="post-side-card-header">
                <x-heroicon-m-chart-bar style="width: 1.125rem; height: 1.125rem; color: #6366f1;" />
                <h3 class="post-side-card-title">Statistik Pembaca & Unduhan</h3>
            </div>

            <div class="post-metric-grid">
                <div class="post-metric-box">
                    <div class="post-metric-val">{{ number_format($post->views_count ?? 0) }}</div>
                    <div class="post-metric-lbl">Pembaca</div>
                </div>

                <div class="post-metric-box">
                    <div class="post-metric-val">{{ number_format($post->shares_count ?? 0) }}</div>
                    <div class="post-metric-lbl">Dibagikan</div>
                </div>

                <div class="post-metric-box">
                    <div class="post-metric-val">{{ $totalPhotos }}</div>
                    <div class="post-metric-lbl">Foto Galeri</div>
                </div>

                <div class="post-metric-box">
                    <div class="post-metric-val">{{ number_format($post->zip_downloads_count ?? 0) }}</div>
                    <div class="post-metric-lbl">Unduhan ZIP</div>
                </div>
            </div>
        </div>

        {{-- Card 4: Tautan Akses Portal Publik --}}
        <div class="post-side-card" x-data="{ copied: false }">
            <div class="post-side-card-header">
                <x-heroicon-m-globe-alt style="width: 1.125rem; height: 1.125rem; color: #0284c7;" />
                <h3 class="post-side-card-title">Akses Portal Publik</h3>
            </div>

            <div style="font-size: 0.75rem; color: var(--doc-text-muted); margin-bottom: 0.5rem;">
                Tautan resmi publik naskah berita:
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); border-radius: 0.375rem; padding: 0.5rem 0.625rem; margin-bottom: 0.75rem;">
                <input type="text"
                       readonly
                       value="{{ $publicUrl }}"
                       style="width: 100%; border: none; background: transparent; font-size: 0.75rem; font-family: ui-monospace, monospace; color: var(--doc-text-secondary); outline: none; text-overflow: ellipsis;" />

                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $publicUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        title="Salin Tautan Publik"
                        style="background: transparent; border: none; cursor: pointer; color: var(--doc-text-muted); padding: 0.25rem; display: flex; align-items: center;">
                    <template x-if="!copied">
                        <x-heroicon-m-clipboard-document style="width: 1rem; height: 1rem;" />
                    </template>
                    <template x-if="copied">
                        <x-heroicon-m-check style="width: 1rem; height: 1rem; color: #059669;" />
                    </template>
                </button>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                @if($post->status === PostStatus::Published)
                    <a href="{{ $publicUrl }}"
                       target="_blank"
                       style="display: flex; align-items: center; justify-content: center; gap: 0.375rem; width: 100%; padding: 0.5rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.375rem; background-color: #2563eb; color: #ffffff; text-decoration: none;">
                        <x-heroicon-m-arrow-top-right-on-square style="width: 1rem; height: 1rem;" />
                        Lihat di Portal Publik
                    </a>
                @else
                    <div style="text-align: center; font-size: 0.75rem; color: var(--doc-text-muted); font-style: italic; padding: 0.25rem 0;">
                        Tautan publik aktif saat status artikel Tayang Publik (Published).
                    </div>
                @endif

                @if($totalPhotos > 0)
                    <a href="{{ $zipUrl }}"
                       target="_blank"
                       style="display: flex; align-items: center; justify-content: center; gap: 0.375rem; width: 100%; padding: 0.5rem; font-size: 0.8125rem; font-weight: 700; border-radius: 0.375rem; background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); text-decoration: none;">
                        <x-heroicon-m-arrow-down-tray style="width: 1rem; height: 1rem; color: #059669;" />
                        Unduh Paket Foto (.ZIP)
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

