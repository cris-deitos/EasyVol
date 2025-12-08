<?php
namespace EasyVol\Utils;

/**
 * Country List Utility
 * 
 * Provides a centralized list of nationalities for forms
 */
class CountryList {
    /**
     * Get list of nationalities in Italian
     * 
     * @return array List of nationalities
     */
    public static function getNationalities() {
        return [
            'Italiana', 'Albanese', 'Argentina', 'Austriaca', 'Belga', 'Brasiliana', 
            'Britannica', 'Bulgara', 'Canadese', 'Cinese', 'Croata', 'Cubana', 
            'Danese', 'Ecuadoriana', 'Egiziana', 'Filippina', 'Finlandese', 'Francese', 
            'Georgiana', 'Giapponese', 'Greca', 'Indiana', 'Iraniana', 'Irlandese', 
            'Marocchina', 'Messicana', 'Moldava', 'Nigeriana', 'Norvegese', 'Olandese', 
            'Pachistana', 'Peruviana', 'Polacca', 'Portoghese', 'Romena', 'Russa', 
            'Senegalese', 'Serba', 'Slovacca', 'Slovena', 'Spagnola', 'Svedese', 
            'Svizzera', 'Tedesca', 'Tunisina', 'Turca', 'Ucraina', 'Ungherese', 
            'Statunitense', 'Venezuelana'
        ];
    }
    
    /**
     * Get nationality options as HTML
     * 
     * @param string $selected Currently selected nationality
     * @return string HTML option elements
     */
    public static function getNationalityOptions($selected = 'Italiana') {
        $html = '';
        $nationalities = self::getNationalities();
        
        foreach ($nationalities as $nationality) {
            $selectedAttr = ($nationality === $selected) ? 'selected' : '';
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                htmlspecialchars($nationality),
                $selectedAttr,
                htmlspecialchars($nationality)
            );
        }
        
        return $html;
    }
}
