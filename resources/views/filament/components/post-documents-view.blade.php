@props([
    'post',
])

@php
    use App\Filament\Resources\Documents\DocumentResource;

    $postDocs = $post->postDocuments()->with('document.unit', 'document.category', 'document.primaryFile')->orderBy('order_index')->get();
    $totalDocs = $postDocs->count();
@endphp

@include('filament.components.post-view-styles')

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
    {{-- Summary Bar --}}
    <div class="doc-card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.25rem; background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="display: flex; width: 2.25rem; height: 2.25rem; align-items: center; justify-content: center; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); flex-shrink: 0;">
                <x-heroicon-o-document-check style="width: 1.25rem; height: 1.25rem;" />
            </span>
            <div>
                <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem;">
                    Naskah Dinas Sah Terkait (Rujukan Legalitas)
                </h4>
                <p style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                    Naskah dinas resmi universitas yang berstatus sah (Approved / Arsip) dan bertaraf akses Publik.
                </p>
            </div>
        </div>

        <span class="doc-badge doc-badge-gray" style="background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); font-weight: 700; font-size: 0.75rem; padding: 0.35rem 0.625rem;">
            Total {{ $totalDocs }} Naskah Terkait
        </span>
    </div>

    {{-- Empty State atau Daftar Naskah --}}
    @if($totalDocs === 0)
        <div class="doc-empty-state" style="border-radius: 0.5rem; border: 1px solid var(--doc-border); background-color: var(--doc-surface); padding: 3rem 1.5rem; text-align: center; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
            <div style="margin: 0 auto; display: flex; width: 3.5rem; height: 3.5rem; align-items: center; justify-content: center; border-radius: 9999px; background-color: var(--doc-surface-muted); color: var(--doc-text-muted); border: 1px solid var(--doc-border);">
                <x-heroicon-o-document-text style="width: 1.75rem; height: 1.75rem;" />
            </div>
            <h4 style="margin-top: 1rem; font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary);">
                Tidak Ada Naskah Dinas Terkait
            </h4>
            <p style="margin-top: 0.375rem; font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 28rem; margin-left: auto; margin-right: auto; line-height: 1.375rem;">
                Anda dapat menautkan naskah dinas resmi (SK Rektor, Nota Kesepahaman, Surat Tugas, atau Lembar Pengesahan) yang relevan melalui formulir pengeditan berita.
            </p>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @foreach($postDocs as $postDoc)
                @php
                    $doc = $postDoc->document;
                @endphp
                @if($doc)
                    <div class="post-doc-card">
                        <div class="post-doc-left">
                            <div class="post-doc-icon-wrap">
                                <x-heroicon-o-document-text style="width: 1.25rem; height: 1.25rem;" />
                            </div>

                            <div style="flex: 1 1 auto;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <span class="doc-badge doc-badge-info doc-badge-mono" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; font-weight: 700;">
                                        {{ $doc->document_number ?? 'DOKUMEN RESMI' }}
                                    </span>

                                    <span class="post-badge post-badge-published" style="font-size: 0.6875rem; padding: 0.15rem 0.45rem;">
                                        SAH / RESMI
                                    </span>

                                    <span style="font-size: 0.75rem; color: var(--doc-text-muted);">
                                        {{ $doc->category?->name ?? 'Naskah Dinas' }} &bull; {{ $doc->unit?->name ?? 'UNIVBI' }}
                                    </span>
                                </div>

                                <div style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin-top: 0.375rem; line-height: 1.375rem;">
                                    {{ $doc->title }}
                                </div>

                                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-top: 0.375rem; font-size: 0.75rem; color: var(--doc-text-muted);">
                                    @if($doc->issued_date)
                                        <span>Tanggal Terbit: <strong style="color: var(--doc-text-primary);">{{ $doc->issued_date->translatedFormat('d F Y') }}</strong></span>
                                    @endif

                                    @if(filled($postDoc->custom_button_label))
                                        <span style="color: var(--doc-badge-info-solid); font-weight: 600;">
                                            Label Tombol Khusus: &ldquo;{{ $postDoc->custom_button_label }}&rdquo;
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="post-doc-actions">
                            <a href="{{ DocumentResource::getUrl('view', ['record' => $doc]) }}"
                               target="_blank"
                               style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 700; padding: 0.45rem 0.875rem; border-radius: 0.375rem; background-color: var(--doc-surface-muted); color: var(--doc-text-primary); border: 1px solid var(--doc-border); text-decoration: none;">
                                <x-heroicon-m-arrow-top-right-on-square style="width: 0.875rem; height: 0.875rem;" />
                                Buka di E-Arsip
                            </a>

                            <a href="{{ route('admin.documents.download', $doc) }}"
                               target="_blank"
                               style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 700; padding: 0.45rem 0.875rem; border-radius: 0.375rem; background-color: #0284c7; color: #ffffff; border: 1px solid #0284c7; text-decoration: none;">
                                <x-heroicon-m-arrow-down-tray style="width: 0.875rem; height: 0.875rem;" />
                                Unduh Salinan
                            </a>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>

