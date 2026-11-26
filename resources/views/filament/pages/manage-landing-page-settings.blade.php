<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="fi-form-actions" style="margin-top: 1.5rem; padding-top: 1.25rem; padding-bottom: 0.5rem; border-top: 1px solid rgba(148, 163, 184, 0.2); display: flex; align-items: center; justify-content: flex-start; gap: 0.75rem;">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-check" class="ui-header-action-create">
                Simpan Perubahan Pengaturan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
