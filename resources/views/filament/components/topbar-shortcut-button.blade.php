@php
    $shortcutUrl = \App\Filament\Resources\UserShortcuts\UserShortcutResource::getUrl('index');
    $isActive = request()->routeIs('filament.admin.resources.user-shortcuts.*');
@endphp

@if(auth()->check())
    <div class="db-topbar-shortcut-wrap">
        <a
            href="{{ $shortcutUrl }}"
            class="db-topbar-shortcut-btn {{ $isActive ? 'is-active' : '' }}"
            title="Kelola Pintasan Web Pribadi Anda"
        >
            <svg style="width: 15px; height: 15px; min-width: 15px; min-height: 15px; flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            <span>Pintasan</span>
        </a>
    </div>
@endif

