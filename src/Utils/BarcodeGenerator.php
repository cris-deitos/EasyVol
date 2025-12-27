<?php
namespace EasyVol\Utils;

/**
 * Barcode Generator Utility
 * 
 * Generates barcodes using PHP GD library.
 * 
 * Note: This is a simplified barcode implementation for visual identification.
 * For production use with barcode scanners, consider using a proper Code 128 library
 * such as 'picqer/php-barcode-generator' for full specification compliance.
 */
class BarcodeGenerator {
    
    /**
     * Generate barcode image
     * 
     * Note: This generates a simplified visual barcode for human-readable labels.
     * It is not a compliant Code 128 implementation and may not work with all scanners.
     * For scanner-compatible barcodes, integrate a proper barcode library.
     * 
     * @param string $data Data to encode
     * @param string $outputPath Output file path (optional)
     * @param int $width Width of the barcode in pixels
     * @param int $height Height of the barcode in pixels
     * @param bool $showText Show text below barcode
     * @return string Path of the file or data URI
     */
    public static function generate($data, $outputPath = null, $width = 300, $height = 80, $showText = true) {
        // Simplified barcode implementation using bars (not full Code 128 spec)
        $barWidth = max(1, $width / (strlen($data) * 12));
        $imageWidth = (int)(strlen($data) * 12 * $barWidth);
        $textHeight = $showText ? 20 : 0;
        $imageHeight = $height + $textHeight;
        
        // Create image
        $image = imagecreate($imageWidth, $imageHeight);
        
        // Allocate colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Fill background
        imagefill($image, 0, 0, $white);
        
        // Generate bars
        $x = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $charCode = ord($data[$i]);
            $pattern = ($charCode % 2 == 0) ? '101' : '110'; // Simplified pattern
            
            for ($j = 0; $j < strlen($pattern); $j++) {
                if ($pattern[$j] == '1') {
                    imagefilledrectangle($image, $x, 0, $x + $barWidth - 1, $height - 1, $black);
                }
                $x += $barWidth;
            }
        }
        
        // Add text
        if ($showText) {
            $fontSize = 3;
            $textWidth = imagefontwidth($fontSize) * strlen($data);
            $textX = ($imageWidth - $textWidth) / 2;
            imagestring($image, $fontSize, $textX, $height + 2, $data, $black);
        }
        
        // Save or return data URI
        if ($outputPath) {
            imagepng($image, $outputPath);
            imagedestroy($image);
            return $outputPath;
        } else {
            ob_start();
            imagepng($image);
            $imageData = ob_get_clean();
            imagedestroy($image);
            return 'data:image/png;base64,' . base64_encode($imageData);
        }
    }
    
    /**
     * Generate barcode for warehouse item
     * 
     * @param int $itemId Item ID
     * @param string $itemCode Item code
     * @param string $outputPath Output file path
     * @return string
     */
    public static function generateForWarehouseItem($itemId, $itemCode, $outputPath = null) {
        $code = $itemCode ?: 'ITEM' . str_pad($itemId, 6, '0', STR_PAD_LEFT);
        return self::generate($code, $outputPath, 300, 80, true);
    }
    
    /**
     * Generate barcode for vehicle
     * 
     * @param int $vehicleId Vehicle ID
     * @param string $plateNumber Plate number
     * @param string $outputPath Output file path
     * @return string
     */
    public static function generateForVehicle($vehicleId, $plateNumber, $outputPath = null) {
        return self::generate($plateNumber, $outputPath, 300, 80, true);
    }
}
