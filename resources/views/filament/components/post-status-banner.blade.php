@props([
    'post',
])

@php
    use App\Enums\PostPeriod;
    use App\Enums\PostStatus;

    $status = $post->status;
    $period = $post->period ?? PostPeriod::Daily;
    $statusClass = match ($status) {
        PostStatus::Published => 'post-badge-published',
        PostStatus::PendingReview => 'post-badge-pending',
        PostStatus::Revision => 'post-badge-revision',
        PostStatus::Archived => 'post-badge-archived',
        default => 'post-badge-draft',
    };

    $statusIcon = match ($status) {
        PostStatus::Published => 'heroicon-m-check-badge',
        PostStatus::PendingReview => 'heroicon-m-clock',
        PostStatus::Revision => 'heroicon-m-exclamation-triangle',
        PostStatus::Archived => 'heroicon-m-archive-box',
        default => 'heroicon-m-pencil-square',
    };

    $photosCount = $post->getAttribute('actual_photos_count') !== null
        ? (int) $post->getAttribute('actual_photos_count')
        : $post->photos()->count();
    $documentsCount = $post->getAttribute('actual_documents_count') !== null
        ? (int) $post->getAttribute('actual_documents_count')
        : $post->postDocuments()->count();
@endphp

@include('filament.components.post-view-styles')

<div class="post-banner-card">
    {{-- Baris Atas: Badge Status, Tag Khusus & Stat Ringkas --}}
    <div class="post-banner-top">
        <div class="post-tag-group">
            {{-- Status Badge (Crisp Rectangular, Strictly NO Pill) --}}
            <span class="post-badge {{ $statusClass }}">
                <x-filament::icon :icon="$statusIcon" style="width: 0.875rem; height: 0.875rem;" />
                {{ $status?->getLabel() ?? 'DRAF ARTIKEL' }}
            </span>

            {{-- Badge Jenis Edisi Berita (Harian / Mingguan / Bulanan) --}}
            <span class="post-badge post-badge-draft" style="background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;">
                <x-filament::icon :icon="$period->getIcon()" style="width: 0.875rem; height: 0.875rem;" />
                {{ strtoupper($period->getLabel()) }}
            </span>

            {{-- Tag Berita Utama (Featured) --}}
            @if($post->is_featured)
                <span class="post-badge post-badge-featured" title="Ditampilkan di banner utama portal">
                    <x-heroicon-m-star style="width: 0.875rem; height: 0.875rem;" />
                    BERITA UTAMA
                </span>
            @endif

            {{-- Tag Disematkan (Pinned) --}}
            @if($post->is_pinned)
                <span class="post-badge post-badge-pinned" title="Disematkan di urutan teratas artikel">
                    <x-heroicon-m-bookmark style="width: 0.875rem; height: 0.875rem;" />
                    DISEMATKAN
                </span>
            @endif

            {{-- Tag Bukti Borang Akreditasi --}}
            @if($post->is_borang)
                <span class="post-badge post-badge-borang" title="Terkait bukti fisik borang akreditasi">
                    <x-heroicon-m-academic-cap style="width: 0.875rem; height: 0.875rem;" />
                    BUKTI BORANG
                </span>
            @endif
        </div>

        {{-- Stat Ringkas Header --}}
        <div class="post-stat-strip">
            <span class="post-stat-chip" title="Jumlah pembaca portal">
                <x-heroicon-m-eye style="width: 0.9375rem; height: 0.9375rem; color: var(--doc-text-muted);" />
                <strong>{{ number_format($post->views_count ?? 0) }}</strong> Pembaca
            </span>

            <span class="post-stat-chip" title="Jumlah unduhan arsip galeri foto">
                <x-heroicon-m-arrow-down-tray style="width: 0.9375rem; height: 0.9375rem; color: var(--doc-text-muted);" />
                <strong>{{ number_format($post->zip_downloads_count ?? 0) }}</strong> Unduh ZIP
            </span>

            <span class="post-stat-chip" title="Total dokumentasi foto terunggah">
                <x-heroicon-m-photo style="width: 0.9375rem; height: 0.9375rem; color: var(--doc-text-muted);" />
                <strong>{{ $photosCount }}</strong> Foto
            </span>

            @if($documentsCount > 0)
                <span class="post-stat-chip" title="Naskah dinas resmi terkait yang ditautkan">
                    <x-heroicon-m-document-check style="width: 0.9375rem; height: 0.9375rem; color: #0284c7;" />
                    <strong style="color: #0284c7;">{{ $documentsCount }}</strong> Naskah Sah
                </span>
            @endif
        </div>
    </div>

    {{-- Info Baris Bawah: Judul & Jalur Editorial --}}
    <div style="margin-top: 1rem;">
        <div style="display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap;">
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--doc-badge-info-solid); text-transform: uppercase; letter-spacing: 0.05em;">
                {{ $post->category?->name ?? 'Warta Kampus' }}
            </span>
            <span style="color: var(--doc-border-strong);">&bull;</span>
            <span style="font-size: 0.75rem; color: var(--doc-text-muted);">
                Penyelenggara: <strong style="color: var(--doc-text-primary);">{{ $post->unit?->name ?? 'Universitas' }}</strong>
            </span>
            @if($post->event_date)
                <span style="color: var(--doc-border-strong);">&bull;</span>
                <span style="font-size: 0.75rem; color: var(--doc-text-muted);">
                    Acara: <strong style="color: var(--doc-text-primary);">{{ $post->event_date->translatedFormat('d F Y') }}</strong>
                </span>
            @endif
        </div>

        <h1 style="font-size: 1.25rem; font-weight: 800; color: var(--doc-text-primary); line-height: 1.625rem; margin-top: 0.375rem; margin-bottom: 0;">
            {{ $post->title }}
        </h1>
    </div>

    {{-- Catatan Redaksional / Moderasi Humas (jika ada) --}}
    @if(filled($post->moderation_note))
        <div class="post-moderation-alert">
            <div style="display: flex; align-items: flex-start; gap: 0.625rem;">
                <x-heroicon-m-chat-bubble-bottom-center-text style="width: 1.125rem; height: 1.125rem; flex-shrink: 0; margin-top: 0.125rem;" />
                <div>
                    <strong style="display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        Catatan Redaksional / Revisi Humas:
                    </strong>
                    <div style="margin-top: 0.25rem;">
                        {{ $post->moderation_note }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

