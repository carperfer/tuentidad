<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Copia de la base de datos en un .sql.gz, hecha solo con PHP: el
 * alojamiento no tiene consola para usar mysqldump.
 *
 * El archivo se puede importar tal cual desde phpMyAdmin o con
 * `gunzip -c copia.sql.gz | mysql …`.
 */
class DatabaseBackup
{
    private const BATCH    = 500;
    private const FILENAME = '/\Atuentidad-\d{8}-\d{6}\.sql\.gz\z/';

    public function __construct(
        private readonly BaseConnection $db,
        private readonly string $directory,
        private readonly int $keep = 14,
    ) {
    }

    /**
     * Crea la copia y borra las más antiguas que sobren.
     *
     * @return array{filename: string, size: int, tables: int}
     */
    public function create(): array
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('No se ha podido crear el directorio de copias.');
        }

        $filename = 'tuentidad-' . date('Ymd-His') . '.sql.gz';
        $path     = $this->directory . DIRECTORY_SEPARATOR . $filename;
        $temp     = $path . '.part';
        $gz       = gzopen($temp, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('No se ha podido crear el archivo de copia.');
        }

        $tables = $this->db->listTables();

        try {
            $this->write($gz, "-- Copia de tuentidad del " . date('Y-m-d H:i:s') . " (UTC)\n");
            $this->write($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

            foreach ($tables as $table) {
                $this->dumpTable($gz, $table);
            }

            $this->write($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            gzclose($gz);
        }

        if (! rename($temp, $path)) {
            throw new RuntimeException('No se ha podido guardar el archivo de copia.');
        }
        $this->prune();

        return ['filename' => $filename, 'size' => (int) filesize($path), 'tables' => count($tables)];
    }

    /**
     * Copias guardadas, de la más reciente a la más antigua.
     *
     * @return list<string>
     */
    public function list(): array
    {
        $files = array_values(array_filter(
            array_map('basename', glob($this->directory . DIRECTORY_SEPARATOR . 'tuentidad-*.sql.gz') ?: []),
            static fn (string $name): bool => preg_match(self::FILENAME, $name) === 1,
        ));
        rsort($files);

        return $files;
    }

    /**
     * @param resource $gz
     */
    private function dumpTable($gz, string $table): void
    {
        $quoted = $this->db->escapeIdentifiers($table);
        $create = $this->db->query("SHOW CREATE TABLE {$quoted}")->getRowArray();
        if ($create === null || ! isset($create['Create Table'])) {
            throw new RuntimeException("No se ha podido leer la estructura de {$table}.");
        }

        $this->write($gz, "DROP TABLE IF EXISTS {$quoted};\n{$create['Create Table']};\n\n");

        for ($offset = 0; ; $offset += self::BATCH) {
            $rows = $this->db->query("SELECT * FROM {$quoted} LIMIT " . self::BATCH . " OFFSET {$offset}")->getResultArray();
            if ($rows === []) {
                break;
            }

            $columns = implode(', ', array_map($this->db->escapeIdentifiers(...), array_keys($rows[0])));
            $values  = array_map(
                fn (array $row): string => '(' . implode(', ', array_map(fn (mixed $value): string => (string) $this->db->escape($value), $row)) . ')',
                $rows,
            );
            $this->write($gz, "INSERT INTO {$quoted} ({$columns}) VALUES\n" . implode(",\n", $values) . ";\n");

            if (count($rows) < self::BATCH) {
                break;
            }
        }

        $this->write($gz, "\n");
    }

    /**
     * @param resource $gz
     */
    private function write($gz, string $sql): void
    {
        if (gzwrite($gz, $sql) === false) {
            throw new RuntimeException('No se ha podido escribir la copia.');
        }
    }

    private function prune(): void
    {
        foreach (array_slice($this->list(), $this->keep) as $old) {
            unlink($this->directory . DIRECTORY_SEPARATOR . $old);
        }
    }
}
