<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-8 pt-6 pb-4 flex items-center justify-start gap-4 border-t border-gray-200 dark:border-gray-800">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">
                Simpan Perubahan Pengaturan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
