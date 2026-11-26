<x-filament-widgets::widget>
    @php
        $snapshot = $this->getSnapshot();
        $usedPercent = $snapshot->diskUsedPercent;
        $ringColor = $usedPercent >= 85
            ? '#f43f5e'
            : ($usedPercent >= 70 ? '#f59e0b' : '#2563eb');
        $usedGb = max(0.0, round($snapshot->diskTotalGb - $snapshot->diskFreeGb, 2));
    @endphp

    <div class="pl-card flex h-full flex-col justify-between p-4">
        <div>
            <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                        <x-filament::icon icon="heroicon-o-circle-stack" class="h-4 w-4" />
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold text-slate-900 dark:text-white">
                            Penyimpanan &amp; Kapasitas Disk
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Partisi server, tautan publik, dan batas unggah berkas
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="testStorageWrite"
                    wire:loading.attr="disabled"
                    class="ui-btn-secondary"
                >
                    <x-filament::icon icon="heroicon-o-pencil-square" class="h-3.5 w-3.5" wire:loading.class="animate-pulse" wire:target="testStorageWrite" />
                    <span>Uji Tulis Storage</span>
                </button>
            </div>

            <div class="mt-4 flex flex-col items-center gap-4 sm:flex-row">
                <x-charts.donut-ring
                    :percentage="$usedPercent"
                    :size="92"
                    :stroke-width="9"
                    :color="$ringColor"
                    :label="number_format($usedPercent, 1) . '%'"
                    sublabel="Terpakai"
                />

                <div class="grid w-full flex-1 grid-cols-3 gap-2">
                    <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                        <div class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Terpakai</div>
                        <div class="mt-0.5 text-xs font-bold tabular-nums text-slate-900 dark:text-white">
                            {{ number_format($usedGb, 2) }} GB
                        </div>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                        <div class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Tersedia</div>
                        <div class="mt-0.5 text-xs font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                            {{ number_format($snapshot->diskFreeGb, 2) }} GB
                        </div>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                        <div class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Kapasitas</div>
                        <div class="mt-0.5 text-xs font-bold tabular-nums text-slate-900 dark:text-white">
                            {{ number_format($snapshot->diskTotalGb, 2) }} GB
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <dl class="mt-4 divide-y divide-slate-100 border-t border-slate-100 text-xs dark:divide-slate-800 dark:border-slate-800">
            <div class="flex items-center justify-between gap-3 py-2">
                <dt class="text-slate-500 dark:text-slate-400">Izin Tulis <code class="font-mono text-[11px]">storage/app</code></dt>
                <dd>
                    <span @class([
                        'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' => $snapshot->storageWritable,
                        'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' => ! $snapshot->storageWritable,
                    ])>
                        {{ $snapshot->storageWritable ? 'Dapat Ditulis' : 'Tidak Dapat Ditulis' }}
                    </span>
                </dd>
            </div>
            <div class="flex items-center justify-between gap-3 py-2">
                <dt class="text-slate-500 dark:text-slate-400">Symlink <code class="font-mono text-[11px]">public/storage</code></dt>
                <dd>
                    <span @class([
                        'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' => $snapshot->publicStorageLinked,
                        'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30' => ! $snapshot->publicStorageLinked,
                    ])>
                        {{ $snapshot->publicStorageLinked ? 'Tersambung' : 'Belum Ditautkan' }}
                    </span>
                </dd>
            </div>
            <div class="flex items-center justify-between gap-3 py-2">
                <dt class="text-slate-500 dark:text-slate-400">Batas Unggah (<code class="font-mono text-[11px]">upload_max_filesize</code>)</dt>
                <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $snapshot->uploadMaxFilesize }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3 py-2">
                <dt class="text-slate-500 dark:text-slate-400">Batas Request (<code class="font-mono text-[11px]">post_max_size</code>)</dt>
                <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $snapshot->postMaxSize }}</dd>
            </div>
        </dl>
    </div>
</x-filament-widgets::widget>
