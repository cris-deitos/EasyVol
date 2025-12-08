<?php
namespace EasyVol\Utils;

/**
 * Path Helper
 * 
 * Centralized utility for path conversion and handling.
 * Ensures consistent path handling across Windows and Unix systems.
 */
class PathHelper {
    /**
     * Convert absolute path to relative path for web display
     * 
     * This method handles both Windows and Unix path separators,
     * converting absolute filesystem paths to relative web paths.
     * 
     * @param string $absolutePath The absolute filesystem path
     * @param string $basePath The base path to remove (defaults to project root)
     * @return string The relative path for web display
     */
    public static function absoluteToRelative($absolutePath, $basePath = null) {
        if ($basePath === null) {
            // Default to two levels up from src/Utils (i.e., project root)
            $basePath = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
        }
        
        // Normalize paths to use forward slashes
        $absolutePath = self::normalizePath($absolutePath);
        $basePath = self::normalizePath($basePath);
        
        // Ensure base path ends with /
        if (substr($basePath, -1) !== '/') {
            $basePath .= '/';
        }
        
        // Check if absolute path starts with base path
        if (strpos($absolutePath, $basePath) === 0) {
            // Remove base path and prepend '../' for web paths
            $relativePath = '../' . substr($absolutePath, strlen($basePath));
            return $relativePath;
        }
        
        // If path doesn't contain base, return as-is
        return $absolutePath;
    }
    
    /**
     * Normalize path separators to forward slashes
     * 
     * Converts Windows backslashes to Unix forward slashes for consistency.
     * 
     * @param string $path The path to normalize
     * @return string The normalized path
     */
    public static function normalizePath($path) {
        // Replace backslashes with forward slashes
        $normalized = str_replace('\\', '/', $path);
        
        // Remove trailing slash if present (except for root)
        if (strlen($normalized) > 1 && substr($normalized, -1) === '/') {
            $normalized = rtrim($normalized, '/');
        }
        
        return $normalized;
    }
    
    /**
     * Convert relative path to absolute path
     * 
     * @param string $relativePath The relative path
     * @param string $basePath The base path to prepend (defaults to project root)
     * @return string The absolute filesystem path
     */
    public static function relativeToAbsolute($relativePath, $basePath = null) {
        if ($basePath === null) {
            // Default to two levels up from src/Utils (i.e., project root)
            $basePath = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
        }
        
        // Remove all leading '../' from relative path
        $relativePath = preg_replace('/^(\.\.\/)+/', '', $relativePath);
        
        // Ensure base path ends with /
        $basePath = self::normalizePath($basePath);
        if (substr($basePath, -1) !== '/') {
            $basePath .= '/';
        }
        
        // Combine and normalize
        $absolutePath = $basePath . $relativePath;
        $absolutePath = self::normalizePath($absolutePath);
        
        return $absolutePath;
    }
    
    /**
     * Ensure path uses Unix-style separators for storage in database
     * 
     * @param string $path The path to convert
     * @return string The path with Unix-style separators
     */
    public static function toUnixStyle($path) {
        return str_replace('\\', '/', $path);
    }
    
    /**
     * Get directory from path (cross-platform)
     * 
     * @param string $path The file path
     * @return string The directory path
     */
    public static function getDirectory($path) {
        $path = self::normalizePath($path);
        return dirname($path);
    }
    
    /**
     * Get filename from path (cross-platform)
     * 
     * @param string $path The file path
     * @return string The filename
     */
    public static function getFilename($path) {
        $path = self::normalizePath($path);
        return basename($path);
    }
}
