#!/usr/bin/env php
<?php
/**
 * Annual Member Data Verification Cron Job
 * 
 * Sends an email to each active member with their complete profile data
 * to verify and update their information.
 * 
 * Schedule: January 7th at 09:00 each year
 * Crontab: 0 9 7 1 * php /path/to/easyvol/cron/annual_member_verification.php
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\EmailSender;

try {
    // Initialize
    $app = App::getInstance();
    $db = $app->getDb();
    $config = $app->getConfig();
    
    $emailSender = new EmailSender($config, $db);
    
    $currentYear = date('Y');
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting annual member data verification job for year $currentYear...\n";
    // Check if we already sent emails this year
    $sql = "SELECT COUNT(*) as count FROM annual_data_verification_emails WHERE year = ?";
    $result = $db->fetchOne($sql, [$currentYear]);
    
    if ($result['count'] > 0) {
        echo "Verification emails for year $currentYear already sent. Skipping.\n";
        exit(0);
    }
    
    $sentCount = 0;
    $failedCount = 0;
    
    // ========================================
    // 1. Process Adult Members
    // ========================================
    echo "Processing adult members...\n";
    
    $sql = "SELECT m.*, 
            GROUP_CONCAT(DISTINCT CONCAT(ma.address_type, ':', ma.street, ' ', ma.number, ', ', ma.cap, ' ', ma.city, ' (', ma.province, ')') SEPARATOR '\n') as addresses,
            GROUP_CONCAT(DISTINCT CONCAT(mc.contact_type, ': ', mc.value) SEPARATOR '\n') as contacts
            FROM members m
            LEFT JOIN member_addresses ma ON m.id = ma.member_id
            LEFT JOIN member_contacts mc ON m.id = mc.member_id
            WHERE m.member_status = 'attivo'
            GROUP BY m.id";
    
    $members = $db->fetchAll($sql);
    
    foreach ($members as $member) {
        // Get email
        $emailSql = "SELECT value FROM member_contacts WHERE member_id = ? AND contact_type = 'email' LIMIT 1";
        $emailRow = $db->fetchOne($emailSql, [$member['id']]);
        
        if (!$emailRow || empty($emailRow['value'])) {
            echo "  Skipping member {$member['registration_number']} - no email address\n";
            continue;
        }
        
        $email = $emailRow['value'];
        
        // Get additional data
        $licenses = $db->fetchAll("SELECT * FROM member_licenses WHERE member_id = ?", [$member['id']]);
        $health = $db->fetchOne("SELECT * FROM member_health WHERE member_id = ?", [$member['id']]);
        
        // Build email
        $subject = "Verifica Annuale Dati Anagrafici - " . $config['association']['name'];
        $body = buildMemberVerificationEmail($member, $licenses, $health, $config);
        
        // Queue email
        if ($emailSender->queue($email, $subject, $body)) {
            // Log success
            $db->execute(
                "INSERT INTO annual_data_verification_emails 
                (member_id, member_type, email, sent_at, year, status) 
                VALUES (?, 'adult', ?, NOW(), ?, 'queued')",
                [$member['id'], $email, $currentYear]
            );
            $sentCount++;
            echo "  Queued verification email to {$member['first_name']} {$member['last_name']} ({$email})\n";
        } else {
            // Log failure
            $db->execute(
                "INSERT INTO annual_data_verification_emails 
                (member_id, member_type, email, sent_at, year, status, error_message) 
                VALUES (?, 'adult', ?, NOW(), ?, 'failed', 'Email queue failed')",
                [$member['id'], $email, $currentYear]
            );
            $failedCount++;
            echo "  Failed to queue to {$member['first_name']} {$member['last_name']} ({$email})\n";
        }
        
        // No need for delay when queuing - the queue processor handles rate limiting
    }
    
    // ========================================
    // 2. Process Junior Members
    // ========================================
    echo "Processing junior members...\n";
    
    $sql = "SELECT jm.*,
            GROUP_CONCAT(DISTINCT CONCAT(jg.guardian_type, ': ', jg.first_name, ' ', jg.last_name, ' - Email: ', jg.email, ' - Tel: ', jg.phone) SEPARATOR '\n') as guardians
            FROM junior_members jm
            LEFT JOIN junior_member_guardians jg ON jm.id = jg.junior_member_id
            WHERE jm.member_status = 'attivo'
            GROUP BY jm.id";
    
    $juniorMembers = $db->fetchAll($sql);
    
    foreach ($juniorMembers as $member) {
        // Get guardian email (prefer father's email, then mother's)
        $emailSql = "SELECT email FROM junior_member_guardians 
                     WHERE junior_member_id = ? AND email IS NOT NULL 
                     ORDER BY FIELD(guardian_type, 'padre', 'madre', 'tutore') 
                     LIMIT 1";
        $emailRow = $db->fetchOne($emailSql, [$member['id']]);
        
        if (!$emailRow || empty($emailRow['email'])) {
            echo "  Skipping junior member {$member['registration_number']} - no guardian email\n";
            continue;
        }
        
        $email = $emailRow['email'];
        
        // Build email
        $subject = "Verifica Annuale Dati Anagrafici - " . $config['association']['name'];
        $body = buildJuniorMemberVerificationEmail($member, $config);
        
        // Queue email
        if ($emailSender->queue($email, $subject, $body)) {
            // Log success
            $db->execute(
                "INSERT INTO annual_data_verification_emails 
                (member_id, member_type, junior_member_id, email, sent_at, year, status) 
                VALUES (NULL, 'junior', ?, ?, NOW(), ?, 'queued')",
                [$member['id'], $email, $currentYear]
            );
            $sentCount++;
            echo "  Queued verification email for {$member['first_name']} {$member['last_name']} to guardian ({$email})\n";
        } else {
            // Log failure
            $db->execute(
                "INSERT INTO annual_data_verification_emails 
                (member_id, member_type, junior_member_id, email, sent_at, year, status, error_message) 
                VALUES (NULL, 'junior', ?, ?, NOW(), ?, 'failed', 'Email queue failed')",
                [$member['id'], $email, $currentYear]
            );
            $failedCount++;
            echo "  Failed to queue for {$member['first_name']} {$member['last_name']} to guardian ({$email})\n";
        }
        
        // No need for delay when queuing - the queue processor handles rate limiting
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Annual verification job completed\n";
    echo "Total sent: $sentCount\n";
    echo "Total failed: $failedCount\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);

/**
 * Build verification email for adult member
 */
function buildMemberVerificationEmail($member, $licenses, $health, $config) {
    $associationName = $config['association']['name'] ?? 'Associazione';
    $associationEmail = $config['association']['email'] ?? $config['email']['from_email'];
    
    $body = "<html><body style='font-family: Arial, sans-serif;'>";
    $body .= "<h2>Verifica Annuale Dati Anagrafici</h2>";
    $body .= "<p>Gentile " . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . ",</p>";
    $body .= "<p>Come previsto dalla normativa vigente, ti chiediamo di verificare i tuoi dati anagrafici 
              in nostro possesso e comunicarci eventuali variazioni.</p>";
    
    $body .= "<div style='border: 1px solid #ddd; padding: 20px; margin: 20px 0; background-color: #f9f9f9;'>";
    $body .= "<h3>Dati Anagrafici</h3>";
    $body .= "<p><strong>Matricola:</strong> " . htmlspecialchars($member['registration_number']) . "</p>";
    $body .= "<p><strong>Nome:</strong> " . htmlspecialchars($member['first_name']) . "</p>";
    $body .= "<p><strong>Cognome:</strong> " . htmlspecialchars($member['last_name']) . "</p>";
    $body .= "<p><strong>Codice Fiscale:</strong> " . htmlspecialchars($member['tax_code'] ?? 'N/D') . "</p>";
    $body .= "<p><strong>Data di Nascita:</strong> " . ($member['birth_date'] ? date('d/m/Y', strtotime($member['birth_date'])) : 'N/D') . "</p>";
    $body .= "<p><strong>Luogo di Nascita:</strong> " . htmlspecialchars($member['birth_place'] ?? 'N/D') . "</p>";
    
    if ($member['addresses']) {
        $body .= "<h4>Indirizzi</h4>";
        $body .= "<pre>" . htmlspecialchars($member['addresses']) . "</pre>";
    }
    
    if ($member['contacts']) {
        $body .= "<h4>Contatti</h4>";
        $body .= "<pre>" . htmlspecialchars($member['contacts']) . "</pre>";
    }
    
    if (!empty($licenses)) {
        $body .= "<h4>Patenti e Brevetti</h4>";
        $body .= "<ul>";
        foreach ($licenses as $license) {
            $body .= "<li>" . htmlspecialchars($license['license_type']) . " - " . 
                     htmlspecialchars($license['license_number'] ?? 'N/D');
            if ($license['expiry_date']) {
                $body .= " (Scadenza: " . date('d/m/Y', strtotime($license['expiry_date'])) . ")";
            }
            $body .= "</li>";
        }
        $body .= "</ul>";
    }
    
    if ($health) {
        $body .= "<h4>Informazioni Sanitarie</h4>";
        if ($health['allergies']) {
            $body .= "<p><strong>Allergie:</strong> " . htmlspecialchars($health['allergies']) . "</p>";
        }
        if ($health['intolerances']) {
            $body .= "<p><strong>Intolleranze:</strong> " . htmlspecialchars($health['intolerances']) . "</p>";
        }
        if ($health['pathologies']) {
            $body .= "<p><strong>Patologie:</strong> " . htmlspecialchars($health['pathologies']) . "</p>";
        }
        if ($health['diet']) {
            $body .= "<p><strong>Dieta:</strong> " . htmlspecialchars($health['diet']) . "</p>";
        }
    }
    
    $body .= "</div>";
    
    $body .= "<p><strong>Se i dati sono corretti:</strong> non è necessaria alcuna azione.</p>";
    $body .= "<p><strong>Se ci sono variazioni:</strong> accedi al portale dedicato ai servizi digitali interni al link 
              <a href='https://sdi.protezionecivilebassogarda.it/'>https://sdi.protezionecivilebassogarda.it/</a> 
              e premi sul pulsante azzurro <strong>\"CONTROLLA DATI SCHEDA PERSONALE\"</strong> 
              (raggiungibile direttamente a questo link:  
              <a href='https://sdi.protezionecivilebassogarda.it/EasyVol/public/member_portal_verify.php'>https://sdi.protezionecivilebassogarda.it/EasyVol/public/member_portal_verify.php</a>).</p>";
    
    $body .= "<p>Grazie per la collaborazione.</p>";
    $body .= "<p><em>$associationName</em></p>";
    $body .= "<hr>";
    $body .= "<p style='font-size: 0.9em; color: #666;'>Questo è un messaggio automatico, si prega di non rispondere a questa email. 
              Per comunicazioni utilizzare l'indirizzo {$associationEmail}</p>";
    $body .= "</body></html>";
    
    return $body;
}

/**
 * Build verification email for junior member
 */
function buildJuniorMemberVerificationEmail($member, $config) {
    $associationName = $config['association']['name'] ?? 'Associazione';
    $associationEmail = $config['association']['email'] ?? $config['email']['from_email'];
    
    $body = "<html><body style='font-family: Arial, sans-serif;'>";
    $body .= "<h2>Verifica Annuale Dati Anagrafici - Socio Minorenne</h2>";
    $body .= "<p>Gentile Genitore/Tutore,</p>";
    $body .= "<p>Come previsto dalla normativa vigente, ti chiediamo di verificare i dati anagrafici 
              del socio minorenne in nostro possesso e comunicarci eventuali variazioni.</p>";
    
    $body .= "<div style='border: 1px solid #ddd; padding: 20px; margin: 20px 0; background-color: #f9f9f9;'>";
    $body .= "<h3>Dati Socio Minorenne</h3>";
    $body .= "<p><strong>Matricola:</strong> " . htmlspecialchars($member['registration_number']) . "</p>";
    $body .= "<p><strong>Nome:</strong> " . htmlspecialchars($member['first_name']) . "</p>";
    $body .= "<p><strong>Cognome:</strong> " . htmlspecialchars($member['last_name']) . "</p>";
    $body .= "<p><strong>Codice Fiscale:</strong> " . htmlspecialchars($member['tax_code'] ?? 'N/D') . "</p>";
    $body .= "<p><strong>Data di Nascita:</strong> " . ($member['birth_date'] ? date('d/m/Y', strtotime($member['birth_date'])) : 'N/D') . "</p>";
    $body .= "<p><strong>Luogo di Nascita:</strong> " . htmlspecialchars($member['birth_place'] ?? 'N/D') . "</p>";
    
    if ($member['guardians']) {
        $body .= "<h4>Dati Genitori/Tutori</h4>";
        $body .= "<pre>" . htmlspecialchars($member['guardians']) . "</pre>";
    }
    
    $body .= "</div>";
    
    $body .= "<p><strong>Se i dati sono corretti:</strong> non è necessaria alcuna azione.</p>";
    $body .= "<p><strong>Se ci sono variazioni:</strong> ti preghiamo di comunicarcele via email a 
              <a href='mailto:{$associationEmail}'>{$associationEmail}</a> indicando nel soggetto 
              'AGGIORNAMENTO DATI CADETTO - " . htmlspecialchars($member['registration_number']) . "'.</p>";
    
    $body .= "<p>Grazie per la collaborazione.</p>";
    $body .= "<p><em>$associationName</em></p>";
    $body .= "<hr>";
    $body .= "<p style='font-size: 0.9em; color: #666;'>Questo è un messaggio automatico, si prega di non rispondere a questa email. 
              Per comunicazioni utilizzare l'indirizzo {$associationEmail}</p>";
    $body .= "</body></html>";
    
    return $body;
}