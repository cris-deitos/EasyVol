<?php
namespace EasyVol\Utils;

/**
 * Italian Fiscal Code (Codice Fiscale) Validator
 * 
 * Validates Italian fiscal codes and verifies them against personal data
 */
class FiscalCodeValidator {
    
    private const MONTHS = [
        1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'H',
        7 => 'L', 8 => 'M', 9 => 'P', 10 => 'R', 11 => 'S', 12 => 'T'
    ];
    
    private const ODD_VALUES = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13,
        '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13,
        'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21, 'K' => 2, 'L' => 4,
        'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8,
        'S' => 12, 'T' => 14, 'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25,
        'Y' => 24, 'Z' => 23
    ];
    
    private const EVEN_VALUES = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5,
        '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5,
        'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11,
        'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17,
        'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
        'Y' => 24, 'Z' => 25
    ];
    
    private const CHECK_CODE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    /**
     * Validate basic fiscal code format
     * 
     * @param string $fiscalCode Fiscal code to validate
     * @return bool True if format is valid
     */
    public static function isValidFormat($fiscalCode) {
        if (empty($fiscalCode)) {
            return false;
        }
        
        $fiscalCode = strtoupper(trim($fiscalCode));
        
        // Check length and format: 6 letters + 2 digits + 1 letter + 2 digits + 4 alphanumeric + 1 letter
        return preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z0-9]{4}[A-Z]$/', $fiscalCode) === 1;
    }
    
    /**
     * Validate fiscal code checksum
     * 
     * @param string $fiscalCode Fiscal code to validate
     * @return bool True if checksum is valid
     */
    public static function isValidChecksum($fiscalCode) {
        if (!self::isValidFormat($fiscalCode)) {
            return false;
        }
        
        $fiscalCode = strtoupper(trim($fiscalCode));
        $chars = str_split(substr($fiscalCode, 0, 15));
        $sum = 0;
        
        foreach ($chars as $index => $char) {
            if ($index % 2 === 0) {
                // Odd position (0-indexed, so even index)
                $sum += self::ODD_VALUES[$char] ?? 0;
            } else {
                // Even position (0-indexed, so odd index)
                $sum += self::EVEN_VALUES[$char] ?? 0;
            }
        }
        
        $checkChar = self::CHECK_CODE[$sum % 26];
        return $checkChar === substr($fiscalCode, 15, 1);
    }
    
    /**
     * Verify fiscal code against personal data
     * 
     * @param string $fiscalCode Fiscal code to verify
     * @param array $personalData Personal data (last_name, first_name, birth_date, gender)
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function verifyAgainstPersonalData($fiscalCode, $personalData) {
        $errors = [];
        
        if (empty($fiscalCode)) {
            return ['valid' => false, 'errors' => ['Codice fiscale mancante']];
        }
        
        $fiscalCode = strtoupper(trim($fiscalCode));
        
        // Check basic format
        if (!self::isValidFormat($fiscalCode)) {
            $errors[] = 'Formato codice fiscale non valido';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check checksum
        if (!self::isValidChecksum($fiscalCode)) {
            $errors[] = 'Checksum codice fiscale non valido';
        }
        
        // Extract components from fiscal code
        $yearCode = substr($fiscalCode, 6, 2);
        $monthCode = substr($fiscalCode, 8, 1);
        $dayCode = (int)substr($fiscalCode, 9, 2);
        $genderFromCode = $dayCode > 40 ? 'F' : 'M';
        
        // Verify gender
        if (!empty($personalData['gender'])) {
            if ($personalData['gender'] !== $genderFromCode) {
                $errors[] = 'Il sesso non corrisponde al codice fiscale';
            }
        }
        
        // Verify birth date
        if (!empty($personalData['birth_date'])) {
            $birthDate = is_string($personalData['birth_date']) ? 
                new \DateTime($personalData['birth_date']) : 
                $personalData['birth_date'];
            
            // Extract year, month, day from birth date
            $year = (int)$birthDate->format('y');
            $month = (int)$birthDate->format('m');
            $day = (int)$birthDate->format('d');
            
            // Verify year code
            if ((int)$yearCode !== $year) {
                $errors[] = 'L\'anno di nascita non corrisponde al codice fiscale';
            }
            
            // Verify month code
            if (self::MONTHS[$month] !== $monthCode) {
                $errors[] = 'Il mese di nascita non corrisponde al codice fiscale';
            }
            
            // Verify day code
            $expectedDayCode = $genderFromCode === 'F' ? $day + 40 : $day;
            if ($dayCode !== $expectedDayCode) {
                $errors[] = 'Il giorno di nascita non corrisponde al codice fiscale';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Get a human-readable description of fiscal code errors
     * 
     * @param string $fiscalCode Fiscal code to check
     * @param array $personalData Personal data
     * @return string Error description or empty string if valid
     */
    public static function getErrorDescription($fiscalCode, $personalData) {
        $verification = self::verifyAgainstPersonalData($fiscalCode, $personalData);
        
        if ($verification['valid']) {
            return '';
        }
        
        return implode('; ', $verification['errors']);
    }
}
