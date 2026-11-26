<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Data\System\SystemHealthSnapshotData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

final class SystemHealthService
{
    private ?SystemHealthSnapshotData $memoizedSnapshot = null;

    public function check(): SystemHealthSnapshotData
    {
        return $this->memoizedSnapshot ??= $this->freshCheck();
    }

    public function freshCheck(): SystemHealthSnapshotData
    {
        $database = $this->probeDatabase();
        $cache = $this->probeCache();
        $queue = $this->probeQueue();
        $storage = $this->probeStorageAndDisk();

        $opcacheEnabled = function_exists('opcache_get_status') && @opcache_get_status(false) !== false;
        $primaryPdoExtension = $this->resolvePrimaryPdoExtension($database['driver']);

        $extensionNames = array_values(array_unique([
            $primaryPdoExtension,
            'redis',
            'gd',
            'intl',
            'mbstring',
            'openssl',
            'zip',
            'fileinfo',
            'curl',
            'xml',
            'bcmath',
            'ctype',
        ]));

        /** @var array<string, bool> $requiredExtensions */
        $requiredExtensions = [];

        foreach ($extensionNames as $extensionName) {
            $requiredExtensions[$extensionName] = extension_loaded($extensionName);
        }

        $snapshot = new SystemHealthSnapshotData(
            databaseHealthy: $database['healthy'],
            databaseDriver: $database['driver'],
            databaseName: $database['name'],
            databaseVersion: $database['version'],
            databaseLatencyMs: $database['latency_ms'],
            cacheHealthy: $cache['healthy'],
            cacheDriver: $cache['driver'],
            cacheLatencyMs: $cache['latency_ms'],
            queueDriver: $queue['driver'],
            pendingJobsCount: $queue['pending_jobs'],
            failedJobsCount: $queue['failed_jobs'],
            storageWritable: $storage['writable'],
            publicStorageLinked: $storage['public_linked'],
            diskFreeGb: $storage['free_gb'],
            diskTotalGb: $storage['total_gb'],
            diskUsedPercent: $storage['used_percent'],
            phpVersion: PHP_VERSION,
            laravelVersion: app()->version(),
            appEnvironment: (string) config('app.env', 'production'),
            appDebug: (bool) config('app.debug', false),
            appTimezone: (string) config('app.timezone', 'UTC'),
            serverUtcTime: gmdate('Y-m-d H:i:s').' UTC',
            memoryLimit: $this->readIniSetting('memory_limit'),
            uploadMaxFilesize: $this->readIniSetting('upload_max_filesize'),
            postMaxSize: $this->readIniSetting('post_max_size'),
            maxExecutionTime: (int) ini_get('max_execution_time'),
            opcacheEnabled: $opcacheEnabled,
            requiredExtensions: $requiredExtensions,
        );

        $this->memoizedSnapshot = $snapshot;

        return $snapshot;
    }

    /**
     * @return array{healthy: bool, driver: string, name: string, version: string, latency_ms: float}
     */
    private function probeDatabase(): array
    {
        $defaultConnection = (string) config('database.default', 'mysql');
        $driver = (string) config("database.connections.{$defaultConnection}.driver", $defaultConnection);
        $databaseName = (string) config("database.connections.{$defaultConnection}.database", 'N/A');
        $healthy = false;
        $version = 'Tidak Tersedia';
        $latencyMs = 0.0;

        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName() ?: $driver;
            $databaseName = (string) ($connection->getDatabaseName() ?: $databaseName);

            $startNs = hrtime(true);
            $connection->select('SELECT 1');
            $latencyMs = round((hrtime(true) - $startNs) / 1_000_000, 2);
            $healthy = true;

            try {
                $pdo = $connection->getPdo();
                $rawVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

                if (is_scalar($rawVersion) && (string) $rawVersion !== '') {
                    $version = (string) $rawVersion;
                }
            } catch (Throwable) {
                $version = 'Terhubung';
            }
        } catch (Throwable) {
            $healthy = false;
        }

        if ($databaseName === ':memory:') {
            $databaseName = 'In-Memory (:memory:)';
        } elseif ($driver === 'sqlite' && str_contains($databaseName, DIRECTORY_SEPARATOR)) {
            $databaseName = basename($databaseName);
        }

        return [
            'healthy' => $healthy,
            'driver' => $driver,
            'name' => $databaseName !== '' ? $databaseName : 'N/A',
            'version' => $version,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * @return array{healthy: bool, driver: string, latency_ms: float}
     */
    private function probeCache(): array
    {
        $driver = (string) config('cache.default', 'array');
        $probeKey = 'system:health:probe';
        $token = bin2hex(random_bytes(8));
        $healthy = false;
        $latencyMs = 0.0;

        try {
            $startNs = hrtime(true);
            Cache::put($probeKey, $token, 10);
            $readBack = Cache::get($probeKey);
            Cache::forget($probeKey);
            $latencyMs = round((hrtime(true) - $startNs) / 1_000_000, 2);
            $healthy = $readBack === $token;
        } catch (Throwable) {
            $healthy = false;
        }

        return [
            'healthy' => $healthy,
            'driver' => $driver,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * @return array{driver: string, pending_jobs: int, failed_jobs: int}
     */
    private function probeQueue(): array
    {
        $driver = (string) config('queue.default', 'sync');
        $pendingJobs = 0;
        $failedJobs = 0;

        try {
            if (Schema::hasTable('jobs')) {
                $pendingJobs = (int) DB::table('jobs')->count();
            }
        } catch (Throwable) {
            $pendingJobs = 0;
        }

        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobs = (int) DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
            $failedJobs = 0;
        }

        return [
            'driver' => $driver,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
        ];
    }

    /**
     * @return array{writable: bool, public_linked: bool, free_gb: float, total_gb: float, used_percent: float}
     */
    private function probeStorageAndDisk(): array
    {
        $appStoragePath = storage_path('app');
        $publicStoragePath = public_path('storage');
        $diskPath = base_path();

        $writable = is_dir($appStoragePath) && is_writable($appStoragePath);
        $publicLinked = is_link($publicStoragePath) || is_dir($publicStoragePath);

        $freeBytes = @disk_free_space($diskPath);
        $totalBytes = @disk_total_space($diskPath);

        $freeGb = is_numeric($freeBytes) && $freeBytes >= 0 ? round(((float) $freeBytes) / (1024 ** 3), 2) : 0.0;
        $totalGb = is_numeric($totalBytes) && $totalBytes > 0 ? round(((float) $totalBytes) / (1024 ** 3), 2) : 0.0;

        $usedPercent = ($totalGb > 0 && is_numeric($freeBytes) && is_numeric($totalBytes))
            ? round((max(0.0, (float) $totalBytes - (float) $freeBytes) / (float) $totalBytes) * 100, 1)
            : 0.0;

        $usedPercent = min(100.0, max(0.0, $usedPercent));

        return [
            'writable' => $writable,
            'public_linked' => $publicLinked,
            'free_gb' => $freeGb,
            'total_gb' => $totalGb,
            'used_percent' => $usedPercent,
        ];
    }

    private function resolvePrimaryPdoExtension(string $driver): string
    {
        return match (strtolower($driver)) {
            'pgsql', 'postgres', 'postgresql' => 'pdo_pgsql',
            'mysql', 'mariadb' => 'pdo_mysql',
            'sqlite' => 'pdo_sqlite',
            default => 'pdo_mysql',
        };
    }

    private function readIniSetting(string $key): string
    {
        $value = ini_get($key);

        if ($value === false || $value === '') {
            return 'N/A';
        }

        return $value;
    }
}
