@props([
    'letter',
])

@php
    use App\Enums\IncomingLetterStatus;
    use App\Enums\IncomingRecipientStatus;
    use App\Enums\LetterPriority;
    use Illuminate\Support\Facades\Storage;

    $status = $letter->status;
    $priority = $letter->priority ?? LetterPriority::Biasa;
    $recipients = $letter->recipients()->with('recipient')->orderBy('step_order')->get();
    $currentUser = auth()->user();
    $canMarkRead = $letter->canUserMarkAsRead($currentUser);
    $activeRecipientForUser = $letter->getRecipientRecordFor($currentUser);

    $activePath = $letter->getActiveFilePath();
    $hasFile = $activePath && Storage::disk('private')->exists($activePath);
    $ext = $activePath ? strtolower(pathinfo($activePath, PATHINFO_EXTENSION)) : '';
    $isPdf = ($ext === 'pdf');
    $isWord = in_array($ext, ['doc', 'docx'], true);
    $fileSize = $hasFile ? Storage::disk('private')->size($activePath) : 0;
    $fileName = $letter->file_name ?: ($activePath ? basename($activePath) : 'berkas-surat-masuk');
    $isStamped = filled($letter->stamped_file_path);
    $streamUrl = route('incoming-letters.file.stream', $letter->uuid);
    $downloadUrl = route('incoming-letters.file.download', $letter->uuid);
@endphp

@include('filament.components.document-timeline-styles')

{{-- Card 1: Visual Stepper Pelacakan Distribusi & Status Baca --}}
<div class="doc-card" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
    <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="doc-card-title-icon" style="width: 2.25rem; height: 2.25rem; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <x-heroicon-o-inbox-arrow-down class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div>
                <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                    Pelacakan Distribusi & Status Baca Surat Masuk
                </h3>
                <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.25rem 0 0 0;">
                    Alur distribusi berjenjang dari registrasi Humas hingga konfirmasi baca pejabat tujuan.
                </p>
            </div>
        </div>

        <div class="doc-badge-group" style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <span class="doc-badge" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;
                @if($priority === LetterPriority::Segera) background-color: var(--doc-badge-danger-bg); color: var(--doc-badge-danger-text); border: 1px solid var(--doc-badge-danger-border);
                @elseif($priority === LetterPriority::Penting) background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);
                @else background-color: var(--doc-badge-gray-bg); color: var(--doc-badge-gray-text); border: 1px solid var(--doc-badge-gray-border);
                @endif
            ">
                Sifat: {{ $priority->getLabel() }}
            </span>

            <span class="doc-badge" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;
                @if($status === IncomingLetterStatus::Completed) background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);
                @else background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);
                @endif
            ">
                <span class="doc-dot @if($status === IncomingLetterStatus::InProgress) doc-dot-pulse @endif" style="
                    @if($status === IncomingLetterStatus::Completed) background: var(--doc-badge-success-solid);
                    @else background: var(--doc-badge-warning-solid);
                    @endif
                "></span>
                {{ $status->getLabel() }}
            </span>

            @if($letter->agenda_number)
                <span class="doc-badge doc-badge-info doc-badge-mono" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; font-family: ui-monospace, SFMono-Regular, monospace; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-text); border: 1px solid var(--doc-badge-info-border);">
                    <x-heroicon-m-qr-code class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                    {{ $letter->agenda_number }}
                </span>
            @endif
        </div>
    </div>

    @if($canMarkRead && $activeRecipientForUser)
        <div style="background-color: var(--doc-badge-info-bg); border: 1px solid var(--doc-badge-info-border); border-radius: 0.375rem; padding: 0.875rem 1rem; margin-top: 1rem; display: flex; align-items: flex-start; gap: 0.75rem;">
            <div style="flex-shrink: 0; color: var(--doc-badge-info-solid); margin-top: 0.125rem;">
                <x-heroicon-s-bell-alert style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div style="flex: 1; font-size: 0.8125rem; line-height: 1.35rem; color: var(--doc-text-primary);">
                <strong style="color: var(--doc-badge-info-text);">Surat Masuk Menunggu Konfirmasi Baca Anda (Tahap {{ $activeRecipientForUser->step_order }})</strong>
                <p style="margin: 0.2rem 0 0 0; color: var(--doc-text-secondary);">
                    Silakan tinjau berkas surat ini, lalu klik tombol hijau <strong>"Tandai Sudah Dibaca"</strong> di bagian kanan atas halaman.
                    @if($activeRecipientForUser->step_order < $letter->total_steps)
                        Setelah Anda menandai sudah dibaca, surat akan otomatis diteruskan ke pejabat berikutnya.
                    @endif
                </p>
                @if(filled($activeRecipientForUser->instruction_note))
                    <div style="margin-top: 0.4rem; padding: 0.4rem 0.65rem; background: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.25rem; font-size: 0.75rem;">
                        <strong>Catatan Pengantar Humas:</strong> "{{ $activeRecipientForUser->instruction_note }}"
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Visual Stepper Track --}}
    <div class="doc-stepper-overflow" style="overflow-x: auto; padding-bottom: 0.5rem; margin-top: 1.25rem; -webkit-overflow-scrolling: touch;">
        <div class="doc-stepper-track" style="display: flex; align-items: flex-start; min-width: 620px; padding: 0.5rem 0; width: 100%;">
            {{-- Step 0: Registrasi & Stempel Humas --}}
            <div class="doc-stepper-node" style="display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center;">
                <div class="doc-stepper-circle doc-circle-approved" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; background-color: var(--doc-badge-success-solid); color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);">
                    <x-heroicon-s-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                </div>
                <div class="doc-stepper-info" style="margin-top: 0.5rem; max-width: 165px; text-align: center;">
                    <span class="doc-stepper-step-tag" style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: var(--doc-badge-success-solid); text-transform: uppercase; letter-spacing: 0.04em;">
                        Registrasi Humas
                    </span>
                    <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;">
                        {{ $letter->registrar?->name ?? 'Bagian Humas' }}
                    </h5>
                    <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                        Stempel & Nomor Agenda
                    </p>
                    <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-text-subtle); margin: 0.125rem 0 0 0;">
                        {{ $letter->created_at?->translatedFormat('d M Y, H:i') }}
                    </p>
                </div>
            </div>

            {{-- Steps 1..N: Pejabat Penerima --}}
            @foreach($recipients as $item)
                @php
                    $isRead = $item->status === IncomingRecipientStatus::Read;
                    $isUnread = $item->status === IncomingRecipientStatus::Unread;
                @endphp

                <div class="doc-stepper-line" style="flex: 1; height: 3px; margin-top: 1.1875rem; min-width: 2rem;
                    @if($isRead) background-color: var(--doc-badge-success-solid);
                    @elseif($isUnread) background-color: var(--doc-badge-warning-solid);
                    @else background-color: var(--doc-stepper-rail);
                    @endif
                "></div>

                <div class="doc-stepper-node" style="display: flex; flex-direction: column; align-items: center; flex: 1; text-align: center;">
                    <div class="doc-stepper-circle" style="width: 2.375rem; height: 2.375rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 0.8125rem; font-weight: 700;
                        @if($isRead) background-color: var(--doc-badge-success-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-success-bg);
                        @elseif($isUnread) background-color: var(--doc-badge-warning-solid); color: #ffffff; box-shadow: 0 0 0 4px var(--doc-badge-warning-bg);
                        @else background-color: var(--doc-stepper-waiting-bg); color: var(--doc-stepper-waiting-text); border: 2px solid var(--doc-stepper-waiting-border);
                        @endif
                    ">
                        @if($isRead)
                            <x-heroicon-s-check class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                        @elseif($isUnread)
                            <x-heroicon-s-envelope-open class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
                        @else
                            <span>{{ $item->step_order }}</span>
                        @endif
                    </div>

                    <div class="doc-stepper-info" style="margin-top: 0.5rem; max-width: 165px; text-align: center;">
                        <span class="doc-stepper-step-tag" style="display: inline-block; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
                            @if($isRead) color: var(--doc-badge-success-solid);
                            @elseif($isUnread) color: var(--doc-badge-warning-solid);
                            @else color: var(--doc-text-muted);
                            @endif
                        ">
                            Penerima {{ $item->step_order }} • {{ $item->status->getLabel() }}
                        </span>

                        <h5 class="doc-stepper-name" style="font-size: 0.75rem; font-weight: 700; color: var(--doc-text-primary); margin: 0.125rem 0 0 0;">
                            {{ $item->recipient?->name ?? 'Pejabat' }}
                        </h5>

                        @if($item->recipient_position_snapshot)
                            <p class="doc-stepper-sub" style="font-size: 0.6875rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                                {{ $item->recipient_position_snapshot }}
                            </p>
                        @endif

                        @if($isRead && $item->read_at)
                            <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-badge-success-text); font-weight: 600; margin: 0.15rem 0 0 0;">
                                Dibaca: {{ $item->read_at->translatedFormat('d M Y, H:i') }}
                            </p>
                        @elseif($isUnread && $item->opened_at)
                            <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-badge-warning-text); margin: 0.15rem 0 0 0;">
                                Dilihat: {{ $item->opened_at->translatedFormat('d M Y, H:i') }}
                            </p>
                        @elseif($isUnread)
                            <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-text-subtle); font-style: italic; margin: 0.15rem 0 0 0;">
                                Notifikasi terkirim
                            </p>
                        @else
                            <p class="doc-stepper-time" style="font-size: 0.625rem; color: var(--doc-text-subtle); font-style: italic; margin: 0.15rem 0 0 0;">
                                Menunggu giliran sebelumnya
                            </p>
                        @endif

                        @if(filled($item->disposition_note))
                            <div class="doc-stepper-note-pill" style="display: inline-block; font-size: 0.625rem; font-style: italic; background-color: var(--doc-surface-muted); color: var(--doc-text-secondary); border: 1px solid var(--doc-border); padding: 0.125rem 0.5rem; border-radius: 0.25rem; margin-top: 0.25rem; max-width: 155px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $item->disposition_note }}">
                                "{{ $item->disposition_note }}"
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Card 2: Berkas Fisik & Pratinjau Media Surat Masuk (Storage Privat) --}}
<div class="doc-card" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
    <div class="doc-card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--doc-border); margin-bottom: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 0.625rem;">
            <div class="doc-card-title-icon" style="width: 2.25rem; height: 2.25rem; border-radius: 0.375rem; background-color: var(--doc-badge-info-bg); color: var(--doc-badge-info-solid); border: 1px solid var(--doc-badge-info-border); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <x-heroicon-o-document-magnifying-glass class="doc-icon-md" style="width: 1.25rem; height: 1.25rem;" />
            </div>
            <div>
                <h3 class="doc-card-title" style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin: 0; line-height: 1.25rem; letter-spacing: -0.01em;">
                    Berkas Fisik & Pratinjau Media Surat Masuk
                </h3>
                <p class="doc-card-subtitle" style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0.125rem 0 0 0;">
                    Naskah dinas resmi dari instansi eksternal yang disimpan pada direktori privat terproteksi.
                </p>
            </div>
        </div>

        @if($hasFile)
            @if($isStamped)
                <span class="doc-badge doc-badge-teal" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 0.375rem; background-color: var(--doc-badge-teal-bg); color: var(--doc-badge-teal-text); border: 1px solid var(--doc-badge-teal-border);">
                    <x-heroicon-m-check-badge style="width: 0.95rem; height: 0.95rem;" />
                    Berstempel Pojok Kanan Atas & Barcode Aktif
                </span>
            @else
                <span class="doc-badge doc-badge-gray" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.65rem; border-radius: 0.375rem; background-color: var(--doc-badge-gray-bg); color: var(--doc-badge-gray-text); border: 1px solid var(--doc-badge-gray-border);">
                    <x-heroicon-m-document style="width: 0.95rem; height: 0.95rem;" />
                    Berkas Asli Bersih (Tanpa Stempel)
                </span>
            @endif
        @endif
    </div>

    @if($hasFile)
        {{-- File Metadata & Action Bar --}}
        <div class="doc-file-card" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: 1rem 1.25rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); border-radius: 0.375rem;">
            <div style="display: flex; align-items: center; gap: 0.875rem; min-width: 0;">
                @if($isPdf)
                    <div class="doc-modal-file-icon doc-modal-file-icon-pdf" style="width: 2.75rem; height: 2.75rem; border-radius: 0.375rem;" title="Berkas PDF">
                        <x-heroicon-s-document-text class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                    </div>
                @elseif($isWord)
                    <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 2.75rem; height: 2.75rem; border-radius: 0.375rem;" title="Berkas Microsoft Word">
                        <x-heroicon-s-document class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                    </div>
                @else
                    <div class="doc-modal-file-icon" style="width: 2.75rem; height: 2.75rem; border-radius: 0.375rem;" title="Berkas Surat">
                        <x-heroicon-s-paper-clip class="doc-icon-md" style="width: 1.375rem; height: 1.375rem;" />
                    </div>
                @endif

                <div style="min-width: 0;">
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--doc-text-primary); word-break: break-all; line-height: 1.25rem; letter-spacing: -0.01em;">
                        {{ $fileName }}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--doc-text-muted); margin-top: 0.25rem; display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <span class="doc-modal-tag {{ $isPdf ? 'doc-modal-tag-pdf' : ($isWord ? 'doc-modal-tag-doc' : 'doc-badge-gray') }}">
                            {{ strtoupper($ext ?: 'BERKAS') }}
                        </span>
                        <span style="font-family: ui-monospace, SFMono-Regular, monospace; font-weight: 500;">
                            {{ number_format($fileSize / 1024, 1) }} KB
                        </span>
                        @if($letter->agenda_number)
                            <span>•</span>
                            <span class="doc-modal-tag doc-modal-tag-mono" title="Nomor Agenda Humas">
                                <x-heroicon-m-hashtag class="doc-icon-xs" style="width: 0.75rem; height: 0.75rem; opacity: 0.7;" />
                                {{ $letter->agenda_number }}
                            </span>
                        @endif
                        @if($isStamped)
                            <span>•</span>
                            <span style="color: var(--doc-badge-teal-text); font-weight: 600;">Berstempel Pojok Kanan Atas</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <button
                    type="button"
                    wire:click="mountAction('preview')"
                    class="doc-btn doc-btn-primary"
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4375rem 0.875rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid #1d4ed8; background-color: #2563eb; color: #ffffff;"
                >
                    <x-heroicon-m-eye class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                    <span>Pratinjau Layar Penuh</span>
                </button>

                @if($isPdf)
                    <a
                        href="{{ $streamUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="doc-btn doc-btn-outlined"
                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4375rem 0.875rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; border: 1px solid var(--doc-border-strong); background-color: var(--doc-surface); color: var(--doc-text-primary);"
                    >
                        <x-heroicon-m-arrow-top-right-on-square class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                        <span>Buka di Tab Baru</span>
                    </a>
                @endif

                <a
                    href="{{ $downloadUrl }}"
                    class="doc-btn doc-btn-outlined"
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4375rem 0.875rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; text-decoration: none; border: 1px solid var(--doc-border-strong); background-color: var(--doc-surface); color: var(--doc-text-primary);"
                >
                    <x-heroicon-m-arrow-down-tray class="doc-icon-xs" style="width: 0.875rem; height: 0.875rem;" />
                    <span>Unduh Berkas</span>
                </a>
            </div>
        </div>

        {{-- Embedded Interactive Media Preview Canvas --}}
        @if($isPdf)
            <div style="margin-top: 1rem; border-radius: 0.5rem; overflow: hidden; border: 1px solid var(--doc-border); background-color: var(--doc-surface-muted);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; padding: 0.625rem 1rem; background-color: var(--doc-surface); border-bottom: 1px solid var(--doc-border); font-size: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: var(--doc-text-primary);">
                        <x-heroicon-s-eye style="width: 1rem; height: 1rem; color: #2563eb;" />
                        <span>Pratinjau Interaktif Dokumen PDF (Langsung di Halaman)</span>
                    </div>
                    <span style="font-size: 0.7rem; color: var(--doc-text-muted);">
                        Gunakan kontrol di dalam bingkai untuk navigasi halaman, zoom, atau cetak
                    </span>
                </div>
                <div style="width: 100%; height: 680px; min-height: 540px; background-color: #525659; position: relative;">
                    <iframe
                        src="{{ $streamUrl }}#toolbar=1&navpanes=0"
                        style="width: 100%; height: 100%; border: none; display: block;"
                        title="Pratinjau Berkas Surat Masuk"
                        loading="lazy"
                    ></iframe>
                </div>
            </div>
        @elseif($isWord)
            <div class="doc-empty-state" style="margin-top: 1rem; padding: 2rem; border-radius: 0.375rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); text-align: center;">
                <div class="doc-modal-file-icon doc-modal-file-icon-word" style="width: 3.25rem; height: 3.25rem; margin: 0 auto 1rem auto; border-radius: 0.5rem;">
                    <x-heroicon-s-document-arrow-down class="doc-icon-lg" style="width: 1.75rem; height: 1.75rem;" />
                </div>
                <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--doc-text-primary); margin-bottom: 0.375rem;">
                    Berkas Surat Microsoft Word (.{{ strtoupper($ext) }})
                </h4>
                <p style="font-size: 0.8125rem; color: var(--doc-text-muted); max-width: 30rem; margin: 0 auto 1.25rem auto; line-height: 1.45;">
                    Pratinjau visual langsung di browser dioptimalkan untuk format PDF. Silakan unduh berkas untuk membukanya menggunakan Microsoft Word atau LibreOffice.
                </p>
                <a
                    href="{{ $downloadUrl }}"
                    class="doc-btn doc-btn-primary"
                    style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; border-radius: 0.375rem; font-size: 0.8125rem; font-weight: 600; text-decoration: none; border: 1px solid #1d4ed8; background-color: #2563eb; color: #ffffff;"
                >
                    <x-heroicon-m-arrow-down-tray style="width: 1rem; height: 1rem;" />
                    <span>Unduh Dokumen Word (.{{ strtoupper($ext) }})</span>
                </a>
            </div>
        @endif
    @else
        <div class="doc-empty-state" style="padding: 1.75rem; border-radius: 0.375rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); text-align: center;">
            <div class="doc-empty-icon" style="margin-bottom: 0.75rem;">
                <x-heroicon-o-document-magnifying-glass class="doc-icon-lg" style="width: 2rem; height: 2rem; color: var(--doc-text-muted); margin: 0 auto;" />
            </div>
            <h4 style="font-size: 0.875rem; font-weight: 700; color: var(--doc-text-primary); margin: 0 0 0.25rem 0;">Berkas Fisik Belum Tersedia</h4>
            <p style="font-size: 0.75rem; color: var(--doc-text-muted); margin: 0;">Surat masuk ini belum memiliki berkas naskah fisik yang diunggah ke penyimpanan privat.</p>
        </div>
    @endif
</div>

{{-- Card 3: Detail Informasi Surat Masuk & Tabel Riwayat Baca --}}
<div class="doc-card" style="background-color: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: 0.5rem; padding: 1.25rem 1.5rem; box-shadow: var(--doc-shadow-sm); color: var(--doc-text-primary);">
    <div style="padding-bottom: 0.875rem; border-bottom: 1px solid var(--doc-border); margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h4 style="font-size: 0.875rem; font-weight: 700; margin: 0; color: var(--doc-text-primary);">
                Rincian Identitas Surat Masuk Eksternal
            </h4>
            <p style="font-size: 0.75rem; margin: 0.15rem 0 0 0; color: var(--doc-text-muted);">
                Data surat dari pihak eksternal beserta status pembubuhan stempel pojok kanan atas.
            </p>
        </div>
        @if($letter->stamped_file_path)
            <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.65rem; border-radius: 0.375rem; background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);">
                <x-heroicon-s-check-badge style="width: 0.95rem; height: 0.95rem;" />
                Stempel Pojok Kanan Atas & Barcode Aktif
            </span>
        @endif
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem 1.5rem; font-size: 0.8125rem;">
        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Nomor Agenda Humas</div>
            <div style="font-weight: 700; font-family: ui-monospace, monospace; margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->agenda_number ?? '-' }}</div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Unit Tujuan Utama</div>
            <div style="font-weight: 600; margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->unit?->name ?? '-' }} ({{ $letter->unit?->code ?? '-' }})</div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Asal Surat / Instansi Pengirim</div>
            <div style="font-weight: 600; margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->sender_name }}</div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Nomor & Tanggal Surat Pengirim</div>
            <div style="margin-top: 0.2rem; color: var(--doc-text-primary);">
                {{ $letter->external_letter_number ?: 'Tanpa Nomor' }}
                @if($letter->external_letter_date)
                    • {{ $letter->external_letter_date->translatedFormat('d F Y') }}
                @endif
            </div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Tanggal Diterima Humas</div>
            <div style="margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->received_date?->translatedFormat('d F Y') ?? '-' }}</div>
        </div>

        <div>
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Petugas Registrasi Humas</div>
            <div style="margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->registrar?->name ?? '-' }}</div>
        </div>

        <div style="grid-column: 1 / -1;">
            <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Perihal Surat</div>
            <div style="font-weight: 700; font-size: 0.9rem; margin-top: 0.2rem; color: var(--doc-text-primary);">{{ $letter->subject }}</div>
        </div>

        @if(filled($letter->summary))
            <div style="grid-column: 1 / -1;">
                <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--doc-text-muted); letter-spacing: 0.03em;">Ringkasan / Keterangan Singkat Humas</div>
                <div style="margin-top: 0.25rem; padding: 0.65rem 0.85rem; background-color: var(--doc-surface-muted); border: 1px solid var(--doc-border); border-radius: 0.375rem; color: var(--doc-text-secondary); line-height: 1.45;">
                    {{ $letter->summary }}
                </div>
            </div>
        @endif
    </div>

    {{-- Tabel Detail Tracking Penerima & Disposisi --}}
    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--doc-border);">
        <h5 style="font-size: 0.8125rem; font-weight: 700; margin: 0 0 0.75rem 0; color: var(--doc-text-primary);">
            Log Jejak Baca & Catatan Disposisi Pejabat Penerima
        </h5>

        <div style="overflow-x: auto; border: 1px solid var(--doc-border); border-radius: 0.375rem;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.78rem; text-align: left;">
                <thead>
                    <tr style="background-color: var(--doc-surface-muted); border-bottom: 1px solid var(--doc-border); color: var(--doc-text-muted); font-size: 0.7rem; text-transform: uppercase;">
                        <th style="padding: 0.6rem 0.75rem;">Urutan</th>
                        <th style="padding: 0.6rem 0.75rem;">Pejabat Penerima</th>
                        <th style="padding: 0.6rem 0.75rem;">Pengantar Humas</th>
                        <th style="padding: 0.6rem 0.75rem;">Status Baca</th>
                        <th style="padding: 0.6rem 0.75rem;">Waktu Buka / Dibaca</th>
                        <th style="padding: 0.6rem 0.75rem;">Catatan Disposisi / Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recipients as $row)
                        <tr style="border-bottom: 1px solid var(--doc-border);">
                            <td style="padding: 0.65rem 0.75rem; font-weight: 700;">Tahap {{ $row->step_order }}</td>
                            <td style="padding: 0.65rem 0.75rem;">
                                <div style="font-weight: 600; color: var(--doc-text-primary);">{{ $row->recipient?->name ?? '-' }}</div>
                                <div style="font-size: 0.7rem; color: var(--doc-text-muted);">{{ $row->recipient_position_snapshot ?? '-' }}</div>
                            </td>
                            <td style="padding: 0.65rem 0.75rem; color: var(--doc-text-secondary);">
                                {{ $row->instruction_note ?: '-' }}
                            </td>
                            <td style="padding: 0.65rem 0.75rem;">
                                @if($row->status === IncomingRecipientStatus::Read)
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.15rem 0.5rem; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 600; background-color: var(--doc-badge-success-bg); color: var(--doc-badge-success-text); border: 1px solid var(--doc-badge-success-border);">
                                        ✓ Sudah Dibaca
                                    </span>
                                @elseif($row->status === IncomingRecipientStatus::Unread)
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.15rem 0.5rem; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 600; background-color: var(--doc-badge-warning-bg); color: var(--doc-badge-warning-text); border: 1px solid var(--doc-badge-warning-border);">
                                        ● Giliran Aktif (Belum Dibaca)
                                    </span>
                                @else
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.15rem 0.5rem; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 600; background-color: var(--doc-badge-gray-bg); color: var(--doc-badge-gray-text); border: 1px solid var(--doc-badge-gray-border);">
                                        Menunggu Giliran
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 0.65rem 0.75rem; font-size: 0.72rem; color: var(--doc-text-secondary);">
                                @if($row->read_at)
                                    <div><strong>Dibaca:</strong> {{ $row->read_at->translatedFormat('d M Y, H:i') }} WIB</div>
                                @endif
                                @if($row->opened_at)
                                    <div style="color: var(--doc-text-muted);">Dibuka: {{ $row->opened_at->translatedFormat('d M Y, H:i') }} WIB</div>
                                @elseif(! $row->read_at)
                                    <span style="color: var(--doc-text-subtle);">-</span>
                                @endif
                            </td>
                            <td style="padding: 0.65rem 0.75rem; color: var(--doc-text-primary);">
                                @if(filled($row->disposition_note))
                                    <span style="font-style: italic;">"{{ $row->disposition_note }}"</span>
                                @else
                                    <span style="color: var(--doc-text-subtle);">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 1rem; text-align: center; color: var(--doc-text-muted);">
                                Belum ada daftar penerima distribusi pada surat masuk ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

