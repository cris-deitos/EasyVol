<?php
namespace EasyVol\Utils;

/**
 * Utility per validazione e normalizzazione sigle provincia
 */
class ProvinceHelper {
    /**
     * Normalizza una sigla provincia: trim, maiuscolo, solo lettere A-Z, max 2 caratteri
     */
    public static function normalize($value) {
        if ($value === null || $value === '') {
            return null;
        }

        $value = mb_strtoupper(trim((string) $value), 'UTF-8');
        $value = preg_replace('/[^A-Z]/u', '', $value);
        $value = mb_substr($value, 0, 2, 'UTF-8');

        return $value !== '' ? $value : null;
    }

    /**
     * Restituisce il messaggio di validazione oppure null se il valore è valido
     */
    public static function getValidationError($value, $label, $required = true) {
        $value = trim((string) $value);

        if ($value === '') {
            return $required ? "La $label è obbligatoria." : null;
        }

        if (preg_match('/^[A-Za-z]{2}$/', $value) !== 1) {
            return "La $label deve essere indicata con la sigla di 2 lettere (es. BS).";
        }

        return null;
    }
}
