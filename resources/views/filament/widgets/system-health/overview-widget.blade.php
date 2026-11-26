<x-filament-widgets::widget>
    @php
        $snapshot = $this->getSnapshot();
        $overallHealthy = $snapshot->isOverallHealthy();
        $dbProgress = min(100, max(6, (int) round(($snapshot->databaseLatencyMs / 100) * 100)));
        $cacheProgress = min(100, max(6, (int) round(($snapshot->cacheLatencyMs / 50) * 100)));
        $diskColorClass = $snapshot->diskUsedPercent >= 85
            ? 'bg-rose-500'
            : ($snapshot->diskUsedPercent >= 70 ? 'bg-amber-500' : 'bg-blue-600');
    @endphp

    <div class="pl-kpi-widget-root" @if ($this->autoPoll) wire:poll.15s="refreshSnapshot" @endif>
        {{-- Status Summary & Live Telemetry Controls --}}
        <div class="pl-card flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div @class([
                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border',
                    'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400' => $overallHealthy,
                    'border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400' => ! $overallHealthy,
                ])>
                    @if ($overallHealthy)
                        <x-filament::icon icon="heroicon-o-shield-check" class="h-5 w-5" />
                    @else
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" />
                    @endif
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-xs font-semibold text-slate-900 dark:text-white">
                            Status Keseluruhan Server
                        </h3>
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                            'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' => $overallHealthy,
                            'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' => ! $overallHealthy,
                        ])>
                            <span @class([
                                'h-1.5 w-1.5 rounded-full',
                                'bg-emerald-500' => $overallHealthy,
                                'bg-rose-500' => ! $overallHealthy,
                            ])></span>
                            {{ $overallHealthy ? 'Operasional Normal' : 'Perlu Perhatian' }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                        Pemeriksaan terakhir pukul <span class="font-medium tabular-nums text-slate-700 dark:text-slate-300">{{ $this->lastCheckedAt }}</span>
                        @if ($this->autoPoll)
                            <span class="ms-1 text-blue-600 dark:text-blue-400">• Auto-poll 15 dtk aktif</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="toggleAutoPoll"
                    @class([
                        'ui-btn-secondary',
                        '!border-blue-300 !bg-blue-50 !text-blue-700 dark:!border-blue-500/40 dark:!bg-blue-500/15 dark:!text-blue-300' => $this->autoPoll,
                    ])
                >
                    <x-filament::icon icon="heroicon-o-signal" class="h-3.5 w-3.5" />
                    <span>{{ $this->autoPoll ? 'Auto-Poll: Aktif' : 'Auto-Poll: Mati' }}</span>
                </button>

                <button
                    type="button"
                    wire:click="probeNow"
                    wire:loading.attr="disabled"
                    class="ui-btn-secondary"
                >
                    <x-filament::icon icon="heroicon-o-arrow-path" class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="probeNow" />
                    <span>Probe Ulang</span>
                </button>
            </div>
        </div>

        {{-- 4 Compact KPI Cards --}}
        <div class="pl-health-kpi-grid">
            {{-- 1. Database --}}
            <div class="pl-card flex flex-col justify-between p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Database
                        </p>
                        <p class="mt-1 truncate text-sm font-bold text-slate-900 dark:text-white">
                            {{ strtoupper($snapshot->databaseDriver) }}
                        </p>
                    </div>
                    <span @class([
                        'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' => $snapshot->databaseHealthy,
                        'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' => ! $snapshot->databaseHealthy,
                    ])>
                        {{ $snapshot->databaseHealthy ? 'Terhubung' : 'Terputus' }}
                    </span>
                </div>

                <div class="mt-3 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="truncate text-slate-500 dark:text-slate-400" title="{{ $snapshot->databaseName }}">
                            {{ $snapshot->databaseName }}
                        </span>
                        <span class="shrink-0 font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                            {{ number_format($snapshot->databaseLatencyMs, 2) }} ms
                        </span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            class="{{ $snapshot->databaseHealthy ? 'bg-emerald-500' : 'bg-rose-500' }} h-full rounded-full transition-all"
                            style="width: {{ $snapshot->databaseHealthy ? $dbProgress : 100 }}%;"
                        ></div>
                    </div>
                    <p class="truncate text-[10px] text-slate-400 dark:text-slate-500">
                        Versi: {{ $snapshot->databaseVersion }}
                    </p>
                </div>
            </div>

            {{-- 2. Cache --}}
            <div class="pl-card flex flex-col justify-between p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Cache Store
                        </p>
                        <p class="mt-1 truncate text-sm font-bold text-slate-900 dark:text-white">
                            {{ strtoupper($snapshot->cacheDriver) }}
                        </p>
                    </div>
                    <span @class([
                        'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' => $snapshot->cacheHealthy,
                        'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' => ! $snapshot->cacheHealthy,
                    ])>
                        {{ $snapshot->cacheHealthy ? 'Aktif' : 'Bermasalah' }}
                    </span>
                </div>

                <div class="mt-3 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-slate-500 dark:text-slate-400">Latensi Baca/Tulis</span>
                        <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                            {{ number_format($snapshot->cacheLatencyMs, 2) }} ms
                        </span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            class="{{ $snapshot->cacheHealthy ? 'bg-blue-600' : 'bg-rose-500' }} h-full rounded-full transition-all"
                            style="width: {{ $snapshot->cacheHealthy ? $cacheProgress : 100 }}%;"
                        ></div>
                    </div>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500">
                        Probe put/get/forget terverifikasi
                    </p>
                </div>
            </div>

            {{-- 3. Queue --}}
            <div class="pl-card flex flex-col justify-between p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Antrean Pekerjaan
                        </p>
                        <p class="mt-1 truncate text-sm font-bold text-slate-900 dark:text-white">
                            {{ strtoupper($snapshot->queueDriver) }}
                        </p>
                    </div>
                    <span @class([
                        'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' => $snapshot->failedJobsCount === 0,
                        'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' => $snapshot->failedJobsCount > 0,
                    ])>
                        {{ $snapshot->failedJobsCount === 0 ? 'Bebas Error' : $snapshot->failedJobsCount.' Gagal' }}
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-lg bg-slate-50 px-2.5 py-1.5 dark:bg-slate-800/60">
                        <div class="text-[10px] text-slate-500 dark:text-slate-400">Pending</div>
                        <div class="text-xs font-bold tabular-nums text-slate-900 dark:text-white">
                            {{ number_format($snapshot->pendingJobsCount) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-2.5 py-1.5 dark:bg-slate-800/60">
                        <div class="text-[10px] text-slate-500 dark:text-slate-400">Failed</div>
                        <div @class([
                            'text-xs font-bold tabular-nums',
                            'text-slate-900 dark:text-white' => $snapshot->failedJobsCount === 0,
                            'text-rose-600 dark:text-rose-400' => $snapshot->failedJobsCount > 0,
                        ])>
                            {{ number_format($snapshot->failedJobsCount) }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Storage & Disk --}}
            <div class="pl-card flex flex-col justify-between p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Storage &amp; Disk
                        </p>
                        <p class="mt-1 truncate text-sm font-bold text-slate-900 dark:text-white">
                            {{ number_format($snapshot->diskFreeGb, 1) }} GB Tersisa
                        </p>
                    </div>
                    <span @class([
                        'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                        'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30' => $snapshot->storageWritable,
                        'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30' => ! $snapshot->storageWritable,
                    ])>
                        {{ $snapshot->storageWritable ? 'Writable' : 'Read-Only' }}
                    </span>
                </div>

                <div class="mt-3 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-slate-500 dark:text-slate-400">Terpakai</span>
                        <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                            {{ number_format($snapshot->diskUsedPercent, 1) }}%
                        </span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            class="{{ $diskColorClass }} h-full rounded-full transition-all"
                            style="width: {{ min(100, max(2, $snapshot->diskUsedPercent)) }}%;"
                        ></div>
                    </div>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500">
                        Total kapasitas: {{ number_format($snapshot->diskTotalGb, 1) }} GB
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>

