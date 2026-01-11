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

try {
    $app = App::getInstance();
    $config = $app->getConfig();
    
    $dbConfig = $config['database'];
    $backupDir = __DIR__ . '/../backups';
    
    // Create backup directory if not exists
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0750, true);
    }
    
    // Generate filename with timestamp
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . '/' . $filename;
    
    // Check if mysqldump is available
    exec('which mysqldump 2>&1', $whichOutput, $whichReturnCode);
    if ($whichReturnCode !== 0) {
        // Try alternative paths
        $mysqldumpPaths = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump'
        ];
        
        $mysqldumpPath = 'mysqldump'; // default
        foreach ($mysqldumpPaths as $path) {
            if (file_exists($path)) {
                $mysqldumpPath = $path;
                break;
            }
        }
    } else {
        $mysqldumpPath = trim($whichOutput[0]);
    }
    
    echo "Using mysqldump: $mysqldumpPath\n";
    
    // Build mysqldump command with error output redirection
    $command = sprintf(
        '%s --host=%s --port=%d --user=%s --password=%s %s > %s 2>&1',
        $mysqldumpPath,
        escapeshellarg($dbConfig['host']),
        $dbConfig['port'],
        escapeshellarg($dbConfig['username']),
        escapeshellarg($dbConfig['password']),
        escapeshellarg($dbConfig['name']),
        escapeshellarg($filepath)
    );
    
    // Execute backup
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($filepath)) {
        // Compress backup
        exec("gzip " . escapeshellarg($filepath), $gzipOutput, $gzipReturnCode);
        
        if ($gzipReturnCode === 0) {
            $filepath .= '.gz';
            
            $filesize = filesize($filepath);
            echo "Backup created successfully: $filename.gz (" . round($filesize / 1024 / 1024, 2) . " MB)\n";
        } else {
            echo "Warning: Backup created but compression failed. Keeping uncompressed file.\n";
            echo "Gzip output: " . implode("\n", $gzipOutput) . "\n";
        }
        
        // Delete old backups (keep last 30 days)
        $files = glob($backupDir . '/backup_*.sql.gz');
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
        $db->execute($sql, ["Database backup created: $filename.gz"]);
        
    } else {
        $errorMsg = "Backup failed with return code: $returnCode";
        if (!empty($output)) {
            $errorMsg .= "\nOutput: " . implode("\n", $output);
        }
        if (!file_exists($filepath)) {
            $errorMsg .= "\nBackup file was not created at: $filepath";
        }
        throw new \Exception($errorMsg);
    }
    
} catch (\Exception $e) {
    error_log("Backup cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
