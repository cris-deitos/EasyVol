<?php
namespace EasyVol\Utils;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;

/**
 * QR Code Generator Utility
 * 
 * Gestisce la generazione di QR code per EasyVol
 */
class QrCodeGenerator {
    
    /**
     * Genera QR code
     * 
     * @param string $data Dati da codificare
     * @param string $outputPath Percorso output (opzionale)
     * @param int $size Dimensione (default 300)
     * @param string $label Etichetta (opzionale)
     * @return string Path del file o data URI
     */
    public static function generate($data, $outputPath = null, $size = 300, $label = null) {
        $qrCode = QrCode::create($data)
            ->setSize($size)
            ->setMargin(10)
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::High);
        
        $writer = new PngWriter();
        
        // Add label if provided
        $labelObj = null;
        if ($label) {
            $labelObj = Label::create($label)
                ->setTextColor([0, 0, 0]);
        }
        
        $result = $writer->write($qrCode, null, null, $labelObj);
        
        // Save to file or return data URI
        if ($outputPath) {
            $result->saveToFile($outputPath);
            return $outputPath;
        } else {
            return $result->getDataUri();
        }
    }
    
    /**
     * Genera QR code per articolo magazzino
     * 
     * @param int $itemId ID articolo
     * @param string $itemCode Codice articolo
     * @param string $outputPath Percorso output
     * @return string
     */
    public static function generateForWarehouseItem($itemId, $itemCode, $outputPath = null) {
        $data = json_encode([
            'type' => 'warehouse_item',
            'id' => $itemId,
            'code' => $itemCode
        ]);
        
        return self::generate($data, $outputPath, 200, "Cod: $itemCode");
    }
    
    /**
     * Genera QR code per mezzo
     * 
     * @param int $vehicleId ID mezzo
     * @param string $plateNumber Targa/codice
     * @param string $outputPath Percorso output
     * @return string
     */
    public static function generateForVehicle($vehicleId, $plateNumber, $outputPath = null) {
        $data = json_encode([
            'type' => 'vehicle',
            'id' => $vehicleId,
            'plate' => $plateNumber
        ]);
        
        return self::generate($data, $outputPath, 200, $plateNumber);
    }
    
    /**
     * Genera QR code per evento
     * 
     * @param int $eventId ID evento
     * @param string $eventCode Codice evento
     * @param string $outputPath Percorso output
     * @return string
     */
    public static function generateForEvent($eventId, $eventCode, $outputPath = null) {
        $data = json_encode([
            'type' => 'event',
            'id' => $eventId,
            'code' => $eventCode
        ]);
        
        return self::generate($data, $outputPath, 300, "Evento: $eventCode");
    }
    
    /**
     * Decodifica QR code da immagine
     * 
     * Note: This functionality requires the zxing/zxing library which is not included
     * in the default dependencies. To implement QR code decoding, install:
     * composer require zxing/zxing
     * 
     * @param string $imagePath Path to the QR code image file
     * @return string|false Decoded data from QR code, or false if not implemented/failed
     * @deprecated Not yet implemented. Install zxing/zxing library to use this feature.
     */
    public static function decode($imagePath) {
        // Note: Decoding requires additional library (zxing/zxing)
        // For now, return false as decoding is not implemented
        trigger_error('QR code decoding not yet implemented. Install zxing/zxing library.', E_USER_NOTICE);
        return false;
    }
}
