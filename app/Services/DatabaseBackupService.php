<?php

namespace App\Services;

use Generator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    public const DIRECTORY = 'backups';
    public const MARKER = 'RIUS_DATABASE_BACKUP_V1';

    public function create(?string $prefix = null): array
    {
        $directory = storage_path('app/private/'.self::DIRECTORY);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder backup tidak dapat dibuat.');
        }

        $safePrefix = $prefix !== null
            ? preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix)
            : 'backup';

        $databaseName = (string) DB::connection()->getDatabaseName();
        $filename = sprintf(
            '%s_%s_%s.sql',
            $safePrefix ?: 'backup',
            preg_replace('/[^A-Za-z0-9_-]/', '_', $databaseName),
            now()->format('Ymd_His')
        );
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('File backup tidak dapat dibuat.');
        }

        try {
            $this->exportToHandle(DB::connection(), $handle, $databaseName);
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($path);
            throw $exception;
        }

        fclose($handle);

        clearstatcache(true, $path);

        return [
            'filename' => $filename,
            'path' => $path,
            'size' => filesize($path) ?: 0,
        ];
    }

    public function restore(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File backup tidak dapat dibaca.');
        }

        $preview = file_get_contents($path, false, null, 0, 4096);

        if ($preview === false || ! str_contains($preview, self::MARKER)) {
            throw new RuntimeException('File bukan backup yang dibuat oleh aplikasi ini.');
        }

        set_time_limit(0);
        DB::disableQueryLog();

        $executed = 0;

        foreach ($this->statements($path) as $statement) {
            $trimmed = trim($statement);

            if ($trimmed === '') {
                continue;
            }

            DB::unprepared($trimmed);
            $executed++;
        }

        DB::purge();
        DB::reconnect();

        return [
            'statements' => $executed,
        ];
    }

    private function exportToHandle(
        ConnectionInterface $connection,
        mixed $handle,
        string $databaseName,
    ): void {
        $tables = [];
        $views = [];

        foreach ($connection->select('SHOW FULL TABLES') as $row) {
            $values = array_values((array) $row);
            $name = (string) ($values[0] ?? '');
            $type = strtoupper((string) ($values[1] ?? ''));

            if ($name === '') {
                continue;
            }

            if ($type === 'VIEW') {
                $views[] = $name;
            } else {
                $tables[] = $name;
            }
        }

        sort($tables);
        sort($views);

        $this->line($handle, '-- '.self::MARKER);
        $this->line($handle, '-- Database: '.$databaseName);
        $this->line($handle, '-- Dibuat: '.now()->toDateTimeString());
        $this->line($handle, '-- Impor hanya melalui modul Backup Database aplikasi ini atau phpMyAdmin.');
        $this->line($handle);
        $this->line($handle, 'SET NAMES utf8mb4;');
        $this->line($handle, 'SET FOREIGN_KEY_CHECKS=0;');
        $this->line($handle, 'SET UNIQUE_CHECKS=0;');
        $this->line($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';");
        $this->line($handle);

        if ($views !== []) {
            $this->line($handle, '-- Hapus view lebih dahulu');
            foreach (array_reverse($views) as $view) {
                $this->line($handle, 'DROP VIEW IF EXISTS '.$this->identifier($view).';');
            }
            $this->line($handle);
        }

        $this->writeRoutineDrops($connection, $handle, 'PROCEDURE');
        $this->writeRoutineDrops($connection, $handle, 'FUNCTION');

        $this->line($handle, '-- Struktur tabel');
        foreach (array_reverse($tables) as $table) {
            $this->line($handle, 'DROP TABLE IF EXISTS '.$this->identifier($table).';');
        }
        $this->line($handle);

        foreach ($tables as $table) {
            $row = $connection->selectOne('SHOW CREATE TABLE '.$this->identifier($table));
            $values = array_values((array) $row);
            $create = (string) ($values[1] ?? '');

            if ($create === '') {
                throw new RuntimeException("Struktur tabel {$table} tidak dapat dibaca.");
            }

            $this->line($handle, '-- Tabel: '.$table);
            $this->line($handle, $create.';');
            $this->line($handle);
        }

        $this->line($handle, '-- Data tabel');
        foreach ($tables as $table) {
            $this->writeTableData($connection, $handle, $table);
        }

        if ($views !== []) {
            $this->line($handle, '-- View');
            foreach ($views as $view) {
                $row = $connection->selectOne('SHOW CREATE VIEW '.$this->identifier($view));
                $data = (array) $row;
                $create = (string) ($data['Create View'] ?? array_values($data)[1] ?? '');

                if ($create === '') {
                    continue;
                }

                $create = $this->stripDefiner($create);
                $this->line($handle, $create.';');
                $this->line($handle);
            }
        }

        $this->writeTriggers($connection, $handle);
        $this->writeRoutines($connection, $handle, 'PROCEDURE');
        $this->writeRoutines($connection, $handle, 'FUNCTION');

        $this->line($handle, 'SET UNIQUE_CHECKS=1;');
        $this->line($handle, 'SET FOREIGN_KEY_CHECKS=1;');
        $this->line($handle, '-- Selesai');
    }

    private function writeTableData(
        ConnectionInterface $connection,
        mixed $handle,
        string $table,
    ): void {
        $columns = array_map(
            static fn (object $column): string => (string) $column->Field,
            $connection->select('SHOW COLUMNS FROM '.$this->identifier($table))
        );

        if ($columns === []) {
            return;
        }

        $primaryColumns = array_map(
            static fn (object $index): string => (string) $index->COLUMN_NAME,
            $connection->select(
                "SELECT COLUMN_NAME
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND INDEX_NAME = 'PRIMARY'
                 ORDER BY SEQ_IN_INDEX",
                [$table]
            )
        );

        $sql = 'SELECT * FROM '.$this->identifier($table);

        if ($primaryColumns !== []) {
            $sql .= ' ORDER BY '.implode(', ', array_map([$this, 'identifier'], $primaryColumns));
        }

        $rows = [];
        $pdo = $connection->getPdo();

        foreach ($connection->cursor($sql) as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = $this->literal($pdo, $row->{$column} ?? null);
            }

            $rows[] = '('.implode(', ', $values).')';

            if (count($rows) >= 100) {
                $this->writeInsert($handle, $table, $columns, $rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $this->writeInsert($handle, $table, $columns, $rows);
        }

        $this->line($handle);
    }

    private function writeInsert(
        mixed $handle,
        string $table,
        array $columns,
        array $rows,
    ): void {
        $columnSql = implode(', ', array_map([$this, 'identifier'], $columns));

        $this->line(
            $handle,
            'INSERT INTO '.$this->identifier($table).' ('.$columnSql.') VALUES'
        );
        $this->line($handle, implode(",\n", $rows).';');
    }

    private function writeTriggers(ConnectionInterface $connection, mixed $handle): void
    {
        $triggers = $connection->select('SHOW TRIGGERS');

        if ($triggers === []) {
            return;
        }

        $this->line($handle, '-- Trigger');
        $this->line($handle, 'DELIMITER $$');

        foreach ($triggers as $trigger) {
            $name = (string) ($trigger->Trigger ?? '');

            if ($name === '') {
                continue;
            }

            $row = $connection->selectOne('SHOW CREATE TRIGGER '.$this->identifier($name));
            $data = (array) $row;
            $create = (string) ($data['SQL Original Statement'] ?? $data['Create Trigger'] ?? '');

            if ($create === '') {
                continue;
            }

            $create = $this->stripDefiner($create);
            $this->line($handle, 'DROP TRIGGER IF EXISTS '.$this->identifier($name).'$$');
            $this->line($handle, $create.'$$');
            $this->line($handle);
        }

        $this->line($handle, 'DELIMITER ;');
        $this->line($handle);
    }

    private function writeRoutineDrops(
        ConnectionInterface $connection,
        mixed $handle,
        string $type,
    ): void {
        $routines = $connection->select(
            "SELECT ROUTINE_NAME
             FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()
               AND ROUTINE_TYPE = ?
             ORDER BY ROUTINE_NAME",
            [$type]
        );

        if ($routines === []) {
            return;
        }

        foreach ($routines as $routine) {
            $name = (string) $routine->ROUTINE_NAME;
            $this->line($handle, 'DROP '.$type.' IF EXISTS '.$this->identifier($name).';');
        }

        $this->line($handle);
    }

    private function writeRoutines(
        ConnectionInterface $connection,
        mixed $handle,
        string $type,
    ): void {
        $routines = $connection->select(
            "SELECT ROUTINE_NAME
             FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()
               AND ROUTINE_TYPE = ?
             ORDER BY ROUTINE_NAME",
            [$type]
        );

        if ($routines === []) {
            return;
        }

        $this->line($handle, '-- '.$type);
        $this->line($handle, 'DELIMITER $$');

        foreach ($routines as $routine) {
            $name = (string) $routine->ROUTINE_NAME;
            $row = $connection->selectOne(
                'SHOW CREATE '.$type.' '.$this->identifier($name)
            );
            $data = (array) $row;
            $key = $type === 'PROCEDURE' ? 'Create Procedure' : 'Create Function';
            $create = (string) ($data[$key] ?? '');

            if ($create === '') {
                continue;
            }

            $this->line($handle, $this->stripDefiner($create).'$$');
            $this->line($handle);
        }

        $this->line($handle, 'DELIMITER ;');
        $this->line($handle);
    }

    private function statements(string $path): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('File restore tidak dapat dibuka.');
        }

        $delimiter = ';';
        $buffer = '';
        $insideBlockComment = false;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
                $trimmed = trim($line);

                if ($insideBlockComment) {
                    if (str_contains($trimmed, '*/')) {
                        $insideBlockComment = false;
                    }
                    continue;
                }

                if ($buffer === '' && str_starts_with($trimmed, '/*')) {
                    if (! str_contains($trimmed, '*/')) {
                        $insideBlockComment = true;
                    }
                    continue;
                }

                if ($buffer === '' && (
                    $trimmed === ''
                    || str_starts_with($trimmed, '--')
                    || str_starts_with($trimmed, '#')
                )) {
                    continue;
                }

                if ($buffer === '' && preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches) === 1) {
                    $delimiter = trim($matches[1]);
                    continue;
                }

                $buffer .= $line;

                if (! $this->hasTerminalDelimiter($buffer, $delimiter)) {
                    continue;
                }

                $statement = rtrim($buffer);
                $statement = substr($statement, 0, -strlen($delimiter));
                $buffer = '';

                yield $statement;
            }

            if (trim($buffer) !== '') {
                throw new RuntimeException('File SQL tidak lengkap atau delimiter tidak ditutup.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function hasTerminalDelimiter(string $sql, string $delimiter): bool
    {
        $length = strlen($sql);
        $delimiterLength = strlen($delimiter);
        $singleQuoted = false;
        $doubleQuoted = false;
        $backtickQuoted = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if (($singleQuoted || $doubleQuoted) && $character === '\\') {
                $escaped = true;
                continue;
            }

            if ($singleQuoted) {
                if ($character === "'") {
                    if (($sql[$index + 1] ?? null) === "'") {
                        $index++;
                        continue;
                    }

                    $singleQuoted = false;
                }

                continue;
            }

            if ($doubleQuoted) {
                if ($character === '"') {
                    if (($sql[$index + 1] ?? null) === '"') {
                        $index++;
                        continue;
                    }

                    $doubleQuoted = false;
                }

                continue;
            }

            if ($backtickQuoted) {
                if ($character === '`') {
                    if (($sql[$index + 1] ?? null) === '`') {
                        $index++;
                        continue;
                    }

                    $backtickQuoted = false;
                }

                continue;
            }

            if ($character === "'") {
                $singleQuoted = true;
                continue;
            }

            if ($character === '"') {
                $doubleQuoted = true;
                continue;
            }

            if ($character === '`') {
                $backtickQuoted = true;
                continue;
            }

            if (
                substr($sql, $index, $delimiterLength) === $delimiter
                && trim(substr($sql, $index + $delimiterLength)) === ''
            ) {
                return true;
            }
        }

        return false;
    }

    private function literal(\PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $quoted = $pdo->quote((string) $value);

        if ($quoted === false) {
            throw new RuntimeException('Nilai database tidak dapat diubah menjadi SQL.');
        }

        return $quoted;
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private function stripDefiner(string $sql): string
    {
        return preg_replace(
            '/\sDEFINER\s*=\s*(?:`[^`]+`@`[^`]+`|[^\s]+)/i',
            '',
            $sql
        ) ?? $sql;
    }

    private function line(mixed $handle, string $content = ''): void
    {
        if (fwrite($handle, $content.PHP_EOL) === false) {
            throw new RuntimeException('Gagal menulis file backup.');
        }
    }
}
