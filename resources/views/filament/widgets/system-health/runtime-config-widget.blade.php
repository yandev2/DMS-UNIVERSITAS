<x-filament-widgets::widget>
    @php
        $rows = $this->getConfigRows();
        $totalCount = count($rows);
        $securityCount = count(array_filter($rows, static fn (array $row): bool => $row['category'] === 'security'));
        $performanceCount = count(array_filter($rows, static fn (array $row): bool => $row['category'] === 'performance'));
        $jsonPayload = json_encode($this->getSnapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    @endphp

    <div
        class="pl-card flex h-full flex-col justify-between p-4"
        x-data="{
            tab: 'all',
            copied: false,
            payload: @js($jsonPayload ?: '{}'),
            async copySnapshot() {
                try {
                    await navigator.clipboard.writeText(this.payload);
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2000);
                } catch (e) {
                    this.copied = false;
                }
            }
        }"
    >
        <div>
            <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                        <x-filament::icon icon="heroicon-o-cog-6-tooth" class="h-4 w-4" />
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold text-slate-900 dark:text-white">
                            Konfigurasi Runtime &amp; PHP
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Parameter lingkungan, keamanan, dan batas eksekusi
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    x-on:click="copySnapshot()"
                    class="ui-btn-secondary"
                >
                    <x-filament::icon icon="heroicon-o-clipboard-document" class="h-3.5 w-3.5" />
                    <span x-text="copied ? 'Disalin!' : 'Salin JSON'"></span>
                </button>
            </div>

            <div class="mt-3">
                <div class="ui-tab-group" role="tablist" aria-label="Filter Konfigurasi Runtime">
                    <button
                        type="button"
                        class="ui-tab-item"
                        x-bind:data-active="tab === 'all' ? 'true' : 'false'"
                        x-on:click="tab = 'all'"
                    >
                        <span>Semua</span>
                        <span class="ui-tab-badge">{{ $totalCount }}</span>
                    </button>
                    <button
                        type="button"
                        class="ui-tab-item"
                        x-bind:data-active="tab === 'security' ? 'true' : 'false'"
                        x-on:click="tab = 'security'"
                    >
                        <span>Keamanan</span>
                        <span class="ui-tab-badge">{{ $securityCount }}</span>
                    </button>
                    <button
                        type="button"
                        class="ui-tab-item"
                        x-bind:data-active="tab === 'performance' ? 'true' : 'false'"
                        x-on:click="tab = 'performance'"
                    >
                        <span>Performa</span>
                        <span class="ui-tab-badge">{{ $performanceCount }}</span>
                    </button>
                </div>
            </div>
        </div>

        <dl class="mt-3 divide-y divide-slate-100 border-t border-slate-100 text-xs dark:divide-slate-800 dark:border-slate-800">
            @foreach ($rows as $row)
                <div
                    class="flex items-center justify-between gap-3 py-2"
                    x-show="tab === 'all' || tab === '{{ $row['category'] }}'"
                >
                    <dt class="text-slate-500 dark:text-slate-400">{{ $row['label'] }}</dt>
                    <dd @class([
                        'text-right font-semibold',
                        'text-amber-600 dark:text-amber-400' => $row['warning'],
                        'text-slate-900 dark:text-white' => ! $row['warning'],
                    ])>
                        {{ $row['value'] }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-filament-widgets::widget>

