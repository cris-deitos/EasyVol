<?php
/**
 * Cron Job: Database Backup
 * 
 * Crea backup automatico del database
 * Eseguire giornalmente: 0 2 * * * php /path/to/easyvol/cron/backup.php
 */

require_once __DIR__ . '/../src/Autoloader.php';

use EasyVol\App;

try {
    $app = new App(false);
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
    
    // Build mysqldump command
    $command = sprintf(
        'mysqldump --host=%s --port=%d --user=%s --password=%s %s > %s',
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
        exec("gzip " . escapeshellarg($filepath));
        $filepath .= '.gz';
        
        $filesize = filesize($filepath);
        echo "Backup created successfully: $filename.gz (" . round($filesize / 1024 / 1024, 2) . " MB)\n";
        
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
        $db = $app->getDatabase();
        $sql = "INSERT INTO activity_logs (user_id, module, action, description, created_at) 
                VALUES (NULL, 'cron', 'backup', ?, NOW())";
        $db->execute($sql, ["Database backup created: $filename.gz"]);
        
    } else {
        throw new \Exception("Backup failed with return code: $returnCode");
    }
    
} catch (\Exception $e) {
    error_log("Backup cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
