<?php
/**
 * Cron Job: Database Backup
 * 
 * Crea backup automatico del database
 * Eseguire giornalmente: 0 2 * * * php /path/to/easyvol/cron/backup.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;

/**
 * Escape identificatori SQL con backtick
 */
function quoteIdentifier(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

/**
 * Scrittura streaming su file (normale o gzip)
 */
function writeBackupChunk($handle, string $content, bool $isGzip): void {
    $result = $isGzip ? gzwrite($handle, $content) : fwrite($handle, $content);
    if ($result === false) {
        throw new \Exception('Errore durante la scrittura del file di backup');
    }
}

/**
 * Estrae la query CREATE da SHOW CREATE TABLE/VIEW
 */
function extractCreateStatement(array $row): string {
    foreach ($row as $key => $value) {
        if (is_string($key) && stripos($key, 'Create ') === 0) {
            return $value;
        }
    }

    $values = array_values($row);
    if (!isset($values[1])) {
        throw new \Exception('Impossibile leggere lo statement CREATE');
    }

    return $values[1];
}

/**
 * Determina tipi colonna speciali per serializzazione valori
 */
function getColumnTypeMaps(\PDO $pdo, string $table): array {
    $numericColumns = [];
    $binaryColumns = [];
    $numericPrefixes = [
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'bigint',
        'decimal',
        'float',
        'double',
        'real',
        'numeric'
    ];

    $stmt = $pdo->query('SHOW COLUMNS FROM ' . quoteIdentifier($table));
    while ($column = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $columnName = $column['Field'];
        $type = strtolower((string) $column['Type']);

        foreach ($numericPrefixes as $prefix) {
            if (str_starts_with($type, $prefix)) {
                $numericColumns[$columnName] = true;
                break;
            }
        }

        if (
            str_contains($type, 'blob') ||
            str_contains($type, 'binary') ||
            str_starts_with($type, 'bit')
        ) {
            $binaryColumns[$columnName] = true;
        }
    }

    return [$numericColumns, $binaryColumns];
}

/**
 * Converte un valore SQL in literal valido per dump
 */
function toSqlLiteral(\PDO $pdo, mixed $value, bool $isNumericColumn, bool $isBinaryColumn): string {
    if ($value === null) {
        return 'NULL';
    }

    if ($isBinaryColumn) {
        return '0x' . bin2hex((string) $value);
    }

    if ($isNumericColumn && is_numeric($value)) {
        return (string) $value;
    }

    return $pdo->quote((string) $value);
}

try {
    $app = App::getInstance();
    $pdo = $app->getDb()->getConnection();
    $backupDir = __DIR__ . '/../backups';

    // Create backup directory if not exists
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
            throw new \Exception('Impossibile creare la directory backup: ' . $backupDir);
        }
    }

    // Protegge la directory backup da accessi web diretti (se applicabile)
    $htaccessPath = $backupDir . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        file_put_contents($htaccessPath, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }

    // Generate filename with timestamp (.gz solo se zlib disponibile)
    $useGzip = function_exists('gzopen') && function_exists('gzwrite') && function_exists('gzclose');
    $filename = 'backup_' . date('Y-m-d_H-i-s') . ($useGzip ? '.sql.gz' : '.sql');
    $filepath = $backupDir . '/' . $filename;

    $handle = $useGzip ? gzopen($filepath, 'wb9') : fopen($filepath, 'wb');
    if ($handle === false) {
        throw new \Exception('Impossibile aprire il file di backup in scrittura: ' . $filepath);
    }

    echo "Avvio backup database in PHP puro...\n";
    echo "Formato output: " . ($useGzip ? 'SQL compresso (.sql.gz)' : 'SQL non compresso (.sql)') . "\n";

    writeBackupChunk($handle, "-- EasyVol Database Backup\n", $useGzip);
    writeBackupChunk($handle, "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n", $useGzip);
    writeBackupChunk($handle, "SET NAMES utf8mb4;\n", $useGzip);
    writeBackupChunk($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n", $useGzip);

    $tablesStmt = $pdo->query('SHOW FULL TABLES');
    $objects = $tablesStmt->fetchAll(\PDO::FETCH_NUM);

    foreach ($objects as $objectInfo) {
        $tableName = $objectInfo[0];
        $tableType = strtoupper($objectInfo[1] ?? 'BASE TABLE');
        $quotedTable = quoteIdentifier($tableName);

        if ($tableType === 'VIEW') {
            echo "Esporto vista: {$tableName}\n";
            $createViewStmt = $pdo->query('SHOW CREATE VIEW ' . $quotedTable);
            $createViewRow = $createViewStmt->fetch(\PDO::FETCH_ASSOC);
            if ($createViewRow === false) {
                throw new \Exception("Impossibile leggere CREATE VIEW per {$tableName}");
            }

            $createViewSql = extractCreateStatement($createViewRow);
            writeBackupChunk($handle, "--\n-- Vista {$tableName}\n--\n", $useGzip);
            writeBackupChunk($handle, "DROP VIEW IF EXISTS {$quotedTable};\n", $useGzip);
            writeBackupChunk($handle, $createViewSql . ";\n\n", $useGzip);
            continue;
        }

        echo "Esporto tabella: {$tableName}\n";

        $createTableStmt = $pdo->query('SHOW CREATE TABLE ' . $quotedTable);
        $createTableRow = $createTableStmt->fetch(\PDO::FETCH_ASSOC);
        if ($createTableRow === false) {
            throw new \Exception("Impossibile leggere CREATE TABLE per {$tableName}");
        }

        $createTableSql = extractCreateStatement($createTableRow);
        writeBackupChunk($handle, "--\n-- Struttura tabella {$tableName}\n--\n", $useGzip);
        writeBackupChunk($handle, "DROP TABLE IF EXISTS {$quotedTable};\n", $useGzip);
        writeBackupChunk($handle, $createTableSql . ";\n\n", $useGzip);

        [$numericColumns, $binaryColumns] = getColumnTypeMaps($pdo, $tableName);
        $rowsExported = 0;
        $usingUnbufferedQuery = false;
        $previousBuffered = true;

        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
            try {
                $bufferedAttribute = $pdo->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
                $previousBuffered = $bufferedAttribute === null ? true : (bool) $bufferedAttribute;
                $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $usingUnbufferedQuery = true;
            } catch (\Throwable $bufferingError) {
                $usingUnbufferedQuery = false;
            }
        }

        try {
            $dataStmt = $pdo->query('SELECT * FROM ' . $quotedTable);
            $insertPrefix = null;

            while (($row = $dataStmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $values = [];
                foreach ($row as $column => $value) {
                    $values[] = toSqlLiteral(
                        $pdo,
                        $value,
                        isset($numericColumns[$column]),
                        isset($binaryColumns[$column])
                    );
                }

                if ($insertPrefix === null) {
                    $columns = array_map(static fn(string $columnName): string => quoteIdentifier($columnName), array_keys($row));
                    $insertPrefix = 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ') VALUES ';
                }

                writeBackupChunk(
                    $handle,
                    $insertPrefix . '(' . implode(', ', $values) . ");\n",
                    $useGzip
                );
                $rowsExported++;
            }
        } finally {
            if (isset($dataStmt) && $dataStmt instanceof \PDOStatement) {
                $dataStmt->closeCursor();
            }
            if ($usingUnbufferedQuery) {
                $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $previousBuffered);
            }
        }

        writeBackupChunk($handle, "\n", $useGzip);
        echo "Righe esportate da {$tableName}: {$rowsExported}\n";
    }

    writeBackupChunk($handle, "SET FOREIGN_KEY_CHECKS=1;\n", $useGzip);

    if ($useGzip) {
        gzclose($handle);
    } else {
        fclose($handle);
    }
    $handle = null;

    if (!file_exists($filepath) || filesize($filepath) === 0) {
        throw new \Exception('Backup creato ma file vuoto o non trovato: ' . $filepath);
    }

    $filesize = filesize($filepath);
    echo "Backup creato con successo: {$filename} (" . round($filesize / 1024 / 1024, 2) . " MB)\n";

    // Delete old backups (keep last 30 days) per file .sql e .sql.gz
    $files = array_unique(array_merge(
        glob($backupDir . '/backup_*.sql') ?: [],
        glob($backupDir . '/backup_*.sql.gz') ?: []
    ));
    $now = time();

    foreach ($files as $file) {
        if ($now - filemtime($file) >= 30 * 24 * 60 * 60) {
            unlink($file);
            echo "Deleted old backup: " . basename($file) . "\n";
        }
    }

    // Log activity
    $db = $app->getDb();
    $sql = "INSERT INTO activity_logs (user_id, module, action, description, created_at) 
            VALUES (NULL, 'cron', 'backup', ?, NOW())";
    $db->execute($sql, ["Database backup created: {$filename}"]);

} catch (\Throwable $e) {
    error_log("Backup cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";

    if (isset($handle) && $handle !== null) {
        if (isset($useGzip) && $useGzip) {
            @gzclose($handle);
        } else {
            @fclose($handle);
        }
    }

    if (isset($filepath) && is_file($filepath)) {
        unlink($filepath);
    }

    if (defined('CRON_JOB_NAME')) {
        throw $e;
    }

    exit(1);
}
