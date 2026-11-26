<x-filament-widgets::widget>
    @php
        $extensions = $this->getExtensionsList();
        $totalCount = count($extensions);
        $loadedCount = count(array_filter($extensions, static fn (array $item): bool => $item['loaded']));
        $missingCount = $totalCount - $loadedCount;
    @endphp

    <div
        class="pl-card p-4"
        x-data="{
            search: '',
            statusFilter: 'all',
            matches(name, purpose, loaded) {
                if (this.statusFilter === 'loaded' && !loaded) return false;
                if (this.statusFilter === 'missing' && loaded) return false;
                const q = this.search.trim().toLowerCase();
                if (!q) return true;
                return name.toLowerCase().includes(q) || purpose.toLowerCase().includes(q);
            }
        }"
    >
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-3 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <x-filament::icon icon="heroicon-o-puzzle-piece" class="h-4 w-4" />
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-slate-900 dark:text-white">
                        Ekstensi PHP Wajib &amp; Modul Runtime
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ $loadedCount }} aktif dari {{ $totalCount }} modul yang dipantau
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <div class="ui-tab-group" role="tablist" aria-label="Filter Status Ekstensi">
                    <button
                        type="button"
                        class="ui-tab-item"
                        x-bind:data-active="statusFilter === 'all' ? 'true' : 'false'"
                        x-on:click="statusFilter = 'all'"
                    >
                        <span>Semua</span>
                        <span class="ui-tab-badge">{{ $totalCount }}</span>
                    </button>
                    <button
                        type="button"
                        class="ui-tab-item"
                        x-bind:data-active="statusFilter === 'loaded' ? 'true' : 'false'"
                        x-on:click="statusFilter = 'loaded'"
                    >
                        <span>Aktif</span>
                        <span class="ui-tab-badge">{{ $loadedCount }}</span>
                    </button>
                    <button
                        type="button"
                        class="ui-tab-item"
                        x-bind:data-active="statusFilter === 'missing' ? 'true' : 'false'"
                        x-on:click="statusFilter = 'missing'"
                    >
                        <span>Hilang</span>
                        <span class="ui-tab-badge">{{ $missingCount }}</span>
                    </button>
                </div>

                <div class="relative w-full sm:w-52">
                    <input
                        type="search"
                        x-model="search"
                        placeholder="Cari ekstensi..."
                        aria-label="Cari ekstensi PHP"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500"
                    />
                </div>
            </div>
        </div>

        <div class="pl-ext-grid mt-3">
            @foreach ($extensions as $extension)
                <div
                    x-show="matches(@js($extension['name']), @js($extension['purpose']), @js($extension['loaded']))"
                    @class([
                        'flex items-center justify-between gap-2 rounded-lg border px-3 py-2.5 transition',
                        'border-slate-200/80 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-800/40' => $extension['loaded'],
                        'border-rose-200 bg-rose-50/60 dark:border-rose-500/30 dark:bg-rose-500/10' => ! $extension['loaded'],
                    ])
                >
                    <div class="min-w-0">
                        <div class="truncate font-mono text-xs font-bold text-slate-900 dark:text-white">
                            {{ $extension['name'] }}
                        </div>
                        <div class="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400" title="{{ $extension['purpose'] }}">
                            {{ $extension['purpose'] }}
                        </div>
                    </div>

                    <span @class([
                        'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset',
                        'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-300 dark:ring-emerald-500/30' => $extension['loaded'],
                        'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/20 dark:text-rose-300 dark:ring-rose-500/30' => ! $extension['loaded'],
                    ])>
                        {{ $extension['loaded'] ? 'Aktif' : 'Hilang' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>

