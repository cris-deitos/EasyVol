<?php
namespace EasyVol\Middleware;

/**
 * CSRF Protection Middleware
 * 
 * Protegge da attacchi Cross-Site Request Forgery
 */
class CsrfProtection {
    
    /**
     * Genera token CSRF
     * 
     * @return string
     */
    public static function generateToken() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida token CSRF
     * 
     * @param string $token Token da validare
     * @return bool
     */
    public static function validateToken($token) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Verifica token CSRF dalla richiesta POST
     * 
     * @throws \Exception Se token non valido
     */
    public static function verify() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            
            if (!self::validateToken($token)) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
    }
    
    /**
     * Genera campo hidden per form
     * 
     * @return string HTML
     */
    public static function getHiddenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Genera meta tag per AJAX
     * 
     * @return string HTML
     */
    public static function getMetaTag() {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
}
