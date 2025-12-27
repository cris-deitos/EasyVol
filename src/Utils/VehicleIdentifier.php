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
     * Build a human-readable vehicle identifier from available fields
     * Priority: license_plate > serial_number > brand+model > fallback
     * 
     * @param array $vehicle Vehicle data array
     * @return string Vehicle identifier for display/logging
     */
    public static function build(array $vehicle): string {
        // Priority 1: License plate
        if (!empty($vehicle['license_plate'])) {
            return $vehicle['license_plate'];
        }
        
        // Priority 2: Serial number / Chassis number
        if (!empty($vehicle['serial_number'])) {
            return $vehicle['serial_number'];
        }
        
        // Priority 3: Brand + Model
        $brandModel = trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
        if (!empty($brandModel)) {
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
        if (!empty($vehicle['license_plate'])) {
            return $vehicle['license_plate'];
        }
        
        // Priority 2: Serial number / Chassis number
        if (!empty($vehicle['serial_number'])) {
            return $vehicle['serial_number'];
        }
        
        // Priority 3: Brand + Model
        $brandModel = trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? ''));
        if (!empty($brandModel)) {
            return $brandModel;
        }
        
        // Priority 4: Timestamp for new records
        return 'Mezzo ' . date('YmdHis');
    }
}
