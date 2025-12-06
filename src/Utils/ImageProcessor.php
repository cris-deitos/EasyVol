<?php
namespace EasyVol\Utils;

/**
 * Image Processor Utility
 * 
 * Gestisce il ridimensionamento e la manipolazione di immagini
 */
class ImageProcessor {
    
    /**
     * Ridimensiona immagine mantenendo proporzioni
     * 
     * @param string $sourcePath Percorso immagine sorgente
     * @param string $destPath Percorso immagine destinazione
     * @param int $maxWidth Larghezza massima
     * @param int $maxHeight Altezza massima
     * @param int $quality Qualità JPEG (1-100)
     * @return bool
     */
    public static function resize($sourcePath, $destPath, $maxWidth, $maxHeight, $quality = 90) {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        
        // Don't upscale
        if ($ratio > 1) {
            $ratio = 1;
        }
        
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Create new image
        $dest = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
            imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resample
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save image
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($dest, $destPath, $quality);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($dest, $destPath, 9);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($dest, $destPath);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($dest, $destPath, $quality);
                break;
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($dest);
        
        return $result;
    }
    
    /**
     * Crea thumbnail quadrato con crop
     * 
     * @param string $sourcePath Percorso sorgente
     * @param string $destPath Percorso destinazione
     * @param int $size Dimensione thumbnail
     * @param int $quality Qualità (1-100)
     * @return bool
     */
    public static function createThumbnail($sourcePath, $destPath, $size = 200, $quality = 90) {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Calculate crop dimensions (center crop)
        $minDim = min($width, $height);
        $srcX = (int)(($width - $minDim) / 2);
        $srcY = (int)(($height - $minDim) / 2);
        
        // Create thumbnail
        $thumb = imagecreatetruecolor($size, $size);
        
        // Preserve transparency
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $size, $size, $transparent);
        }
        
        // Resample and crop
        imagecopyresampled($thumb, $source, 0, 0, $srcX, $srcY, $size, $size, $minDim, $minDim);
        
        // Save thumbnail
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($thumb, $destPath, $quality);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($thumb, $destPath, 9);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($thumb, $destPath);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($thumb, $destPath, $quality);
                break;
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($thumb);
        
        return $result;
    }
    
    /**
     * Ruota immagine
     * 
     * @param string $sourcePath Percorso sorgente
     * @param string $destPath Percorso destinazione
     * @param float $angle Angolo rotazione (gradi)
     * @param int $quality Qualità (1-100)
     * @return bool
     */
    public static function rotate($sourcePath, $destPath, $angle, $quality = 90) {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Rotate
        $rotated = imagerotate($source, $angle, 0);
        
        // Save
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($rotated, $destPath, $quality);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($rotated, $destPath, 9);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($rotated, $destPath);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($rotated, $destPath, $quality);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($rotated);
        
        return $result;
    }
    
    /**
     * Ottieni dimensioni immagine
     * 
     * @param string $path Percorso immagine
     * @return array|false ['width' => int, 'height' => int, 'type' => string]
     */
    public static function getDimensions($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        $imageInfo = getimagesize($path);
        if (!$imageInfo) {
            return false;
        }
        
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => image_type_to_mime_type($imageInfo[2])
        ];
    }
    
    /**
     * Verifica se è un'immagine valida
     * 
     * @param string $path Percorso file
     * @return bool
     */
    public static function isValidImage($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        $imageInfo = getimagesize($path);
        return $imageInfo !== false;
    }
}
