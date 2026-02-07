<?php
namespace EasyVol\Utils;

/**
 * Warehouse Categories Utility
 * 
 * Provides consistent category mapping for warehouse items
 */
class WarehouseCategories {
    /**
     * Array of category values and their display labels
     */
    public static function getCategories(): array {
        return [
            'dpi' => 'DPI (Dispositivi Protezione Individuale)',
            'attrezzatura_generica' => 'Attrezzatura Generica',
            'attrezzatura_logistica_da_campo' => 'Attrezzatura Logistica da Campo',
            'attrezzatura_elettronica_ed_accessori' => 'Attrezzatura Elettrica ed Accessori',
            'attrezzatura_idrogeologico' => 'Attrezzatura Idrogeologico',
            'attrezzatura_interventi_e_manutenzioni' => 'Attrezzatura per Interventi e Manutenzioni',
            'attrezzatura_it_radio_tlc' => 'Attrezzatura Informatica Radio TLC',
            'materiale_arredamento_accessori_sedi' => 'Materiale per Arredamento e Accessori Sedi',
            'materiale_sanitario' => 'Materiale Sanitario',
            'cancelleria' => 'Cancelleria',
            'consumabili' => 'Consumabili',
            'altro' => 'Altro'
        ];
    }
    
    /**
     * Get the display label for a category value
     * 
     * @param string|null $category The category value
     * @return string The display label or the original value if not found
     */
    public static function getLabel(?string $category): string {
        if (empty($category)) {
            return '-';
        }
        
        $categories = self::getCategories();
        return $categories[$category] ?? $category;
    }
    
    /**
     * Get all valid category values
     * 
     * @return array List of valid category values
     */
    public static function getValues(): array {
        return array_keys(self::getCategories());
    }
    
    /**
     * Check if a category value is valid
     * 
     * @param string $category The category value to check
     * @return bool True if valid, false otherwise
     */
    public static function isValid(string $category): bool {
        return array_key_exists($category, self::getCategories());
    }
}
