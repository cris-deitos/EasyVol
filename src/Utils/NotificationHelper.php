<?php
namespace EasyVol\Utils;

use EasyVol\App;

/**
 * Notification Helper
 * 
 * Gestisce le notifiche del sistema in modo efficiente con memorizzazione nella cache
 */
class NotificationHelper {
    private static $cachedNotifications = null;
    private static $cachedCount = null;
    
    /**
     * Ottieni tutte le notifiche con cache per richiesta
     * 
     * @return array Array di notifiche
     */
    public static function getNotifications() {
        // Restituisci il risultato dalla cache se disponibile
        if (self::$cachedNotifications !== null) {
            return self::$cachedNotifications;
        }
        
        $app = App::getInstance();
        $db = $app->getDb();
        $notifications = [];
        
        // Pending applications
        if ($app->checkPermission('applications', 'view')) {
            $pendingApps = $db->fetchOne("SELECT COUNT(*) as count FROM member_applications WHERE status = 'pending'")['count'] ?? 0;
            if ($pendingApps > 0) {
                $notifications[] = [
                    'text' => "Domande iscrizione in sospeso: $pendingApps",
                    'link' => 'applications.php',
                    'icon' => 'bi-inbox',
                    'count' => $pendingApps,
                    'type' => 'applications'
                ];
            }
        }
        
        // Pending fee payments
        if ($app->checkPermission('members', 'edit')) {
            $pendingFees = $db->fetchOne("SELECT COUNT(*) as count FROM fee_payment_requests WHERE status = 'pending'")['count'] ?? 0;
            if ($pendingFees > 0) {
                $notifications[] = [
                    'text' => "Quote associative da verificare: $pendingFees",
                    'link' => 'fee_payments.php',
                    'icon' => 'bi-receipt-cutoff',
                    'count' => $pendingFees,
                    'type' => 'fee_payments'
                ];
            }
        }
        
        // Memorizza nella cache per questa richiesta
        self::$cachedNotifications = $notifications;
        
        return $notifications;
    }
    
    /**
     * Ottieni il numero totale di notifiche
     * 
     * @return int
     */
    public static function getNotificationCount() {
        // Restituisci il risultato dalla cache se disponibile
        if (self::$cachedCount !== null) {
            return self::$cachedCount;
        }
        
        $notifications = self::getNotifications();
        $count = array_sum(array_column($notifications, 'count'));
        
        // Memorizza nella cache per questa richiesta
        self::$cachedCount = $count;
        
        return $count;
    }
    
    /**
     * Ottieni il conteggio di un tipo specifico di notifica
     * 
     * @param string $type Tipo di notifica
     * @return int
     */
    public static function getNotificationCountByType($type) {
        $notifications = self::getNotifications();
        
        foreach ($notifications as $notification) {
            if ($notification['type'] === $type) {
                return $notification['count'];
            }
        }
        
        return 0;
    }
    
    /**
     * Azzera la cache (utile per testing o dopo modifiche)
     */
    public static function resetCache() {
        self::$cachedNotifications = null;
        self::$cachedCount = null;
    }
}
