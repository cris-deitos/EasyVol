<?php
namespace EasyVol\Utils;

/**
 * Vehicle Identifier Utility
 * 
 * Provides centralized logic for generating vehicle identifiers
 * throughout the application.
 */
class VehicleIdentifier {
    
    /**
     * Build brand + model string with proper trimming
     * 
     * @param array $vehicle Vehicle data array
     * @return string Brand and model combined, or empty string
     */
    private static function buildBrandModel(array $vehicle): string {
        $brand = trim($vehicle['brand'] ?? '');
        $model = trim($vehicle['model'] ?? '');
        
        // Only combine if at least one is non-empty
        if ($brand !== '' || $model !== '') {
            return trim("$brand $model");
        }
        
        return '';
    }
    
    /**
     * Build a human-readable vehicle identifier from available fields
     * Priority: license_plate > serial_number > brand+model > fallback
     * 
     * @param array $vehicle Vehicle data array
     * @return string Vehicle identifier for display/logging
     */
    public static function build(array $vehicle): string {
        // Priority 1: License plate
        $licensePlate = trim($vehicle['license_plate'] ?? '');
        if ($licensePlate !== '') {
            return $licensePlate;
        }
        
        // Priority 2: Serial number / Chassis number
        $serialNumber = trim($vehicle['serial_number'] ?? '');
        if ($serialNumber !== '') {
            return $serialNumber;
        }
        
        // Priority 3: Brand + Model
        $brandModel = self::buildBrandModel($vehicle);
        if ($brandModel !== '') {
            return $brandModel;
        }
        
        // Priority 4: Fallback to ID or generic name
        if (isset($vehicle['id'])) {
            return "Mezzo ID {$vehicle['id']}";
        }
        
        return 'Nuovo mezzo';
    }
    
    /**
     * Generate internal name for database storage from available fields
     * Same priority as build() but with timestamp fallback for new records
     * Priority: license_plate > serial_number > brand+model > timestamp
     * 
     * @param array $vehicle Vehicle data array
     * @return string Generated name for database storage
     */
    public static function generateInternalName(array $vehicle): string {
        // Priority 1: License plate
        $licensePlate = trim($vehicle['license_plate'] ?? '');
        if ($licensePlate !== '') {
            return $licensePlate;
        }
        
        // Priority 2: Serial number / Chassis number
        $serialNumber = trim($vehicle['serial_number'] ?? '');
        if ($serialNumber !== '') {
            return $serialNumber;
        }
        
        // Priority 3: Brand + Model
        $brandModel = self::buildBrandModel($vehicle);
        if ($brandModel !== '') {
            return $brandModel;
        }
        
        // Priority 4: Timestamp for new records
        return 'Mezzo ' . date('YmdHis');
    }
}
