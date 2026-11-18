@props([
    'post',
])

@php
    $photos = $post->photos()->orderBy('order_index')->get();
    $totalPhotos = $photos->count();
    $coverPhoto = $photos->firstWhere('is_cover', true) ?? $photos->first();
    $zipUrl = $post->getZipDownloadUrl();
@endphp

@include('filament.components.post-view-styles')

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
    {{-- Summary Bar Galeri --}}
    <div class="doc-card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.25rem; background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="display: flex; width: 2.25rem; height: 2.25rem; align-items: center; justify-content: center; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); flex-shrink: 0;">
                <x-heroicon-o-photo style="width: 1.25rem; height: 1.25rem;" />
            </span>
            <div>
                <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem;">
                    Galeri Dokumentasi Foto Kegiatan & Publikasi
                </h4>
                <p style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                    Kumpulan foto dokumentasi beresolusi tinggi yang dilampirkan pada naskah warta ini.
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="doc-badge doc-badge-gray" style="background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); font-weight: 700; font-size: 0.75rem; padding: 0.35rem 0.625rem;">
                Total {{ $totalPhotos }} Foto
            </span>

            @if($totalPhotos > 0)
                <a href="{{ $zipUrl }}"
                   target="_blank"
                   style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 700; padding: 0.4rem 0.75rem; border-radius: 0.375rem; background-color: #059669; color: #ffffff; text-decoration: none;">
                    <x-heroicon-m-arrow-down-tray style="width: 0.875rem; height: 0.875rem;" />
                    Unduh Paket (.ZIP)
                </a>
            @endif
        </div>
    </div>

    {{-- Daftar Foto Grid / Empty State --}}
    @if($totalPhotos === 0)
        <div class="doc-empty-state" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); padding: 3rem 1.5rem; text-align: center; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
            <div style="margin: 0 auto; display: flex; width: 3.5rem; height: 3.5rem; align-items: center; justify-content: center; border-radius: 9999px; background-color: var(--doc-surface-muted); color: var(--doc-text-muted); border: 1px solid var(--doc-border);">
                <x-heroicon-o-photo style="width: 1.75rem; height: 1.75rem;" />
            </div>
            <h4 style="margin-top: 1rem; font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary);">
                Belum Ada Foto Dokumentasi
            </h4>
            <p style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 28rem; margin-left: auto; margin-right: auto; line-height: 1.375rem;">
                Anda dapat menambahkan foto dokumentasi, mengatur urutan tampilan, dan memilih foto sampul (cover) melalui menu ubah naskah.
            </p>
        </div>
    @else
        <div class="post-gallery-grid">
            @foreach($photos as $photo)
                @php
                    $isCover = (bool) $photo->is_cover;
                    $photoUrl = $photo->getUrl();
                    $fileSizeFormatted = $photo->file_size ? number_format($photo->file_size / 1024, 0) . ' KB' : '-';
                @endphp
                <div class="post-photo-card">
                    {{-- Thumbnail & Cover Badge --}}
                    <div class="post-photo-thumb-wrap">
                        <img src="{{ $photo->getThumbnailUrl() }}"
                             alt="{{ $photo->caption ?? 'Dokumentasi' }}"
                             loading="lazy"
                             class="post-photo-thumb" />

                        @if($isCover)
                            <span class="post-photo-cover-tag">
                                ★ Sampul Utama
                            </span>
                        @endif
                    </div>

                    {{-- Takarir & Info Teknis --}}
                    <div class="post-photo-body">
                        <div>
                            <div class="post-photo-caption">
                                {{ $photo->caption ?: 'Dokumentasi Kegiatan UNIVBI' }}
                            </div>

                            <div style="font-size: 0.6875rem; color: var(--doc-text-muted); font-family: ui-monospace, monospace; margin-bottom: 0.5rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="{{ $photo->original_file_name }}">
                                {{ $photo->original_file_name ?? 'foto.jpg' }}
                            </div>
                        </div>

                        <div>
                            <div class="post-photo-meta">
                                <span>
                                    {{ strtoupper($photo->file_extension ?? 'JPG') }} &bull; {{ $fileSizeFormatted }}
                                </span>
                                @if($photo->width && $photo->height)
                                    <span>{{ $photo->width }}x{{ $photo->height }} px</span>
                                @endif
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.625rem;">
                                <a href="{{ $photoUrl }}"
                                   target="_blank"
                                   style="display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem; flex: 1; padding: 0.35rem 0.5rem; font-size: 0.6875rem; font-weight: 700; border-radius: 0.25rem; background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); text-decoration: none;">
                                    <x-heroicon-m-arrow-top-right-on-square style="width: 0.75rem; height: 0.75rem;" />
                                    Buka Penuh
                                </a>

                                <a href="{{ $photoUrl }}"
                                   download="{{ $photo->original_file_name ?? 'dokumentasi.jpg' }}"
                                   style="display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem; flex: 1; padding: 0.35rem 0.5rem; font-size: 0.6875rem; font-weight: 700; border-radius: 0.25rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); text-decoration: none;">
                                    <x-heroicon-m-arrow-down-tray style="width: 0.75rem; height: 0.75rem;" />
                                    Unduh
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

