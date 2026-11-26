<?php

declare(strict_types=1);

namespace App\Data\System;

final readonly class SystemHealthSnapshotData
{
    /**
     * @param  array<string, bool>  $requiredExtensions
     */
    public function __construct(
        public bool $databaseHealthy,
        public string $databaseDriver,
        public string $databaseName,
        public string $databaseVersion,
        public float $databaseLatencyMs,
        public bool $cacheHealthy,
        public string $cacheDriver,
        public float $cacheLatencyMs,
        public string $queueDriver,
        public int $pendingJobsCount,
        public int $failedJobsCount,
        public bool $storageWritable,
        public bool $publicStorageLinked,
        public float $diskFreeGb,
        public float $diskTotalGb,
        public float $diskUsedPercent,
        public string $phpVersion,
        public string $laravelVersion,
        public string $appEnvironment,
        public bool $appDebug,
        public string $appTimezone,
        public string $serverUtcTime,
        public string $memoryLimit,
        public string $uploadMaxFilesize,
        public string $postMaxSize,
        public int $maxExecutionTime,
        public bool $opcacheEnabled,
        public array $requiredExtensions,
    ) {}

    public function isOverallHealthy(): bool
    {
        return $this->databaseHealthy
            && $this->cacheHealthy
            && $this->storageWritable
            && $this->failedJobsCount === 0;
    }
}
