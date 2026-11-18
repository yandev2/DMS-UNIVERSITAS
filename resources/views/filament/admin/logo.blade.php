@php
    $logoUrl = null;
    try {
        $logoUrl = \App\Models\LandingPageSetting::current()?->getLogoUrl();
    } catch (\Throwable) {
        $logoUrl = null;
    }
@endphp

<div class="fi-custom-brand flex items-center gap-2.5">
    @if ($logoUrl)
        <img
            src="{{ $logoUrl }}"
            alt="Logo Archiver-Sistem"
            class="fi-custom-brand-logo h-7 w-auto max-h-7 object-contain shrink-0"
            loading="lazy"
        />
    @endif
    <span class="fi-custom-brand-text font-black text-base sm:text-lg tracking-tight text-gray-950 dark:text-white leading-none whitespace-nowrap select-none">
        Archiver-Sistem
    </span>
</div>

