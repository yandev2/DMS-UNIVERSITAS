<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Spatie\DbDumper\DbDumper;

class SqlitePdoTestDumper extends DbDumper
{
    public function setDbName(string $dbName): static
    {
        $this->dbName = $dbName === ':memory:'
            ? 'unbi_sqlite_memory'
            : str_replace([':', '\\', '/'], '_', $dbName);

        return $this;
    }

    public function dumpToFile(string $dumpFile): void
    {
        $pdo = DB::connection()->getPdo();
        $sqlLines = ["PRAGMA foreign_keys = OFF;\n"];

        $tables = $pdo->query(
            "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY rowid ASC"
        )?->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach (array_reverse($tables) as $table) {
            $escapedTable = str_replace('"', '""', (string) $table['name']);
            $sqlLines[] = "DROP TABLE IF EXISTS \"{$escapedTable}\";";
        }

        foreach ($tables as $table) {
            $sqlLines[] = (string) $table['sql'].';';
        }

        foreach ($tables as $table) {
            $tableName = (string) $table['name'];
            $escapedTable = str_replace('"', '""', $tableName);

            $rows = $pdo->query("SELECT * FROM \"{$escapedTable}\"")?->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $columns = implode(', ', array_map(
                    fn (string $col): string => '"'.str_replace('"', '""', $col).'"',
                    array_keys($row)
                ));
                $values = implode(', ', array_map(function (mixed $val) use ($pdo): string {
                    if ($val === null) {
                        return 'NULL';
                    }
                    if (is_int($val) || is_float($val)) {
                        return (string) $val;
                    }

                    return $pdo->quote((string) $val);
                }, array_values($row)));

                $sqlLines[] = "INSERT INTO \"{$escapedTable}\" ({$columns}) VALUES ({$values});";
            }
        }

        $sqlLines[] = "PRAGMA foreign_keys = ON;\n";
        File::ensureDirectoryExists(dirname($dumpFile));
        File::put($dumpFile, implode("\n", $sqlLines));
    }
}
