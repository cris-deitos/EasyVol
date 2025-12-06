<?php
namespace EasyVol\Utils;

/**
 * File Uploader Utility
 * 
 * Gestisce l'upload di file in modo sicuro
 */
class FileUploader {
    private $uploadDir;
    private $allowedTypes;
    private $maxSize;
    
    /**
     * Constructor
     * 
     * @param string $uploadDir Directory di upload
     * @param array $allowedTypes Tipi MIME consentiti
     * @param int $maxSize Dimensione massima in bytes (default 10MB)
     */
    public function __construct($uploadDir, $allowedTypes = [], $maxSize = 10485760) {
        $this->uploadDir = rtrim($uploadDir, '/');
        $this->allowedTypes = $allowedTypes;
        $this->maxSize = $maxSize;
        
        // Create upload directory if not exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Upload file
     * 
     * @param array $file $_FILES array element
     * @param string $subdirectory Sottocartella (opzionale)
     * @param string $newName Nuovo nome file (opzionale, genera automatico se non fornito)
     * @return array ['success' => bool, 'path' => string, 'filename' => string, 'error' => string]
     */
    public function upload($file, $subdirectory = '', $newName = null) {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Parametri non validi'];
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }
        
        // Check file size
        if ($file['size'] > $this->maxSize) {
            return ['success' => false, 'error' => 'File troppo grande. Massimo ' . $this->formatBytes($this->maxSize)];
        }
        
        // Check MIME type
        if (!empty($this->allowedTypes)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            
            if (!in_array($mimeType, $this->allowedTypes)) {
                return ['success' => false, 'error' => 'Tipo di file non consentito'];
            }
        }
        
        // Generate filename
        if ($newName === null) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = $this->generateUniqueFilename($extension);
        } else {
            // Sanitize provided filename
            $newName = $this->sanitizeFilename($newName);
        }
        
        // Create full path
        $targetDir = $this->uploadDir;
        if (!empty($subdirectory)) {
            $targetDir .= '/' . trim($subdirectory, '/');
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        }
        
        $targetPath = $targetDir . '/' . $newName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Errore durante il salvataggio del file'];
        }
        
        // Set permissions
        chmod($targetPath, 0644);
        
        return [
            'success' => true,
            'path' => $targetPath,
            'filename' => $newName,
            'error' => null
        ];
    }
    
    /**
     * Upload multiplo
     * 
     * @param array $files $_FILES array
     * @param string $subdirectory Sottocartella
     * @return array Array di risultati
     */
    public function uploadMultiple($files, $subdirectory = '') {
        $results = [];
        
        if (!isset($files['name']) || !is_array($files['name'])) {
            return $results;
        }
        
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            $results[] = $this->upload($file, $subdirectory);
        }
        
        return $results;
    }
    
    /**
     * Elimina file
     * 
     * @param string $path Percorso file
     * @return bool
     */
    public function delete($path) {
        if (file_exists($path) && is_file($path)) {
            return unlink($path);
        }
        
        return false;
    }
    
    /**
     * Genera nome file univoco
     * 
     * @param string $extension Estensione file
     * @return string
     */
    private function generateUniqueFilename($extension) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return $timestamp . '_' . $random . '.' . strtolower($extension);
    }
    
    /**
     * Sanitizza nome file
     * 
     * @param string $filename Nome file
     * @return string
     */
    private function sanitizeFilename($filename) {
        // Remove any path components
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        
        // Remove multiple underscores/hyphens
        $filename = preg_replace('/[_\-]+/', '_', $filename);
        
        return $filename;
    }
    
    /**
     * Ottieni messaggio errore upload
     * 
     * @param int $errorCode Codice errore
     * @return string
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Il file supera la dimensione massima consentita dal server',
            UPLOAD_ERR_FORM_SIZE => 'Il file supera la dimensione massima consentita',
            UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo parzialmente',
            UPLOAD_ERR_NO_FILE => 'Nessun file caricato',
            UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante',
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco',
            UPLOAD_ERR_EXTENSION => 'Upload bloccato da un\'estensione PHP'
        ];
        
        return $errors[$errorCode] ?? 'Errore sconosciuto durante l\'upload';
    }
    
    /**
     * Formatta bytes in formato leggibile
     * 
     * @param int $bytes Bytes
     * @return string
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Valida tipo file per immagini
     * 
     * @return array
     */
    public static function getImageMimeTypes() {
        return [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp'
        ];
    }
    
    /**
     * Valida tipo file per documenti
     * 
     * @return array
     */
    public static function getDocumentMimeTypes() {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain'
        ];
    }
}
