<?php
/**
 * Newsletter Controller
 * 
 * Handles newsletter management operations
 */

namespace EasyVol\Controllers;

use EasyVol\Database;
use EasyVol\Models\Newsletter;

class NewsletterController
{
    private Database $db;
    private Newsletter $model;
    private array $config;
    
    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->model = new Newsletter($db);
        $this->config = $config;
    }
    
    /**
     * Get all newsletters with filters
     */
    public function index(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->model->getAll($filters, $page, $perPage);
    }
    
    /**
     * Count newsletters with filters
     */
    public function count(array $filters = []): int
    {
        return $this->model->count($filters);
    }
    
    /**
     * Get newsletter by ID
     */
    public function getById(int $id): ?array
    {
        return $this->model->getById($id);
    }
    
    /**
     * Create or update newsletter
     */
    public function save(array $data, ?int $id = null): array
    {
        try {
            if ($id) {
                // Update existing newsletter
                $success = $this->model->update($id, $data);
                if (!$success) {
                    return ['success' => false, 'message' => 'Impossibile aggiornare la newsletter. Verifica che sia ancora una bozza.'];
                }
                return ['success' => true, 'message' => 'Newsletter aggiornata con successo', 'id' => $id];
            } else {
                // Create new newsletter
                $newId = $this->model->create($data);
                return ['success' => true, 'message' => 'Newsletter creata con successo', 'id' => $newId];
            }
        } catch (\Exception $e) {
            error_log("Newsletter save error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante il salvataggio della newsletter'];
        }
    }
    
    /**
     * Delete newsletter (only drafts)
     */
    public function delete(int $id): array
    {
        try {
            $newsletter = $this->model->getById($id);
            if (!$newsletter) {
                return ['success' => false, 'message' => 'Newsletter non trovata'];
            }
            
            if ($newsletter['status'] !== 'draft') {
                return ['success' => false, 'message' => 'Solo le bozze possono essere eliminate'];
            }
            
            // Delete attachments files
            $attachments = $this->model->getAttachments($id);
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['filepath'])) {
                    unlink($attachment['filepath']);
                }
            }
            
            $success = $this->model->delete($id);
            if ($success) {
                return ['success' => true, 'message' => 'Newsletter eliminata con successo'];
            }
            
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        } catch (\Exception $e) {
            error_log("Newsletter delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione della newsletter'];
        }
    }
    
    /**
     * Clone newsletter
     */
    public function clone(int $id, int $userId): array
    {
        try {
            $newId = $this->model->clone($id, $userId);
            if ($newId) {
                return ['success' => true, 'message' => 'Newsletter clonata con successo', 'id' => $newId];
            }
            
            return ['success' => false, 'message' => 'Newsletter non trovata'];
        } catch (\Exception $e) {
            error_log("Newsletter clone error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la clonazione della newsletter'];
        }
    }
    
    /**
     * Upload attachment
     */
    public function uploadAttachment(int $newsletterId, array $file): array
    {
        try {
            // Validate newsletter exists and is a draft
            $newsletter = $this->model->getById($newsletterId);
            if (!$newsletter || $newsletter['status'] !== 'draft') {
                return ['success' => false, 'message' => 'Newsletter non valida o non modificabile'];
            }
            
            // Validate file
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return ['success' => false, 'message' => 'File non valido'];
            }
            
            // Check file size (max 10MB)
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                return ['success' => false, 'message' => 'File troppo grande (max 10MB)'];
            }
            
            // Create upload directory if not exists
            $uploadDir = __DIR__ . '/../../uploads/newsletters/' . $newsletterId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
            $filepath = $uploadDir . '/' . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'message' => 'Errore durante il caricamento del file'];
            }
            
            // Save to database
            $attachmentId = $this->model->addAttachment($newsletterId, [
                'filename' => $file['name'],
                'filepath' => $filepath,
                'filesize' => $file['size']
            ]);
            
            return [
                'success' => true,
                'message' => 'File caricato con successo',
                'id' => $attachmentId,
                'filename' => $file['name']
            ];
        } catch (\Exception $e) {
            error_log("Newsletter attachment upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante il caricamento del file'];
        }
    }
    
    /**
     * Delete attachment
     */
    public function deleteAttachment(int $attachmentId): array
    {
        try {
            $attachments = $this->db->query("SELECT * FROM newsletter_attachments WHERE id = ?", [$attachmentId]);
            if (empty($attachments)) {
                return ['success' => false, 'message' => 'Allegato non trovato'];
            }
            
            $attachment = $attachments[0];
            
            // Check if newsletter is still a draft
            $newsletter = $this->model->getById($attachment['newsletter_id']);
            if (!$newsletter || $newsletter['status'] !== 'draft') {
                return ['success' => false, 'message' => 'Impossibile eliminare allegato da newsletter non modificabile'];
            }
            
            // Delete file
            if (file_exists($attachment['filepath'])) {
                unlink($attachment['filepath']);
            }
            
            // Delete from database
            $this->model->deleteAttachment($attachmentId);
            
            return ['success' => true, 'message' => 'Allegato eliminato con successo'];
        } catch (\Exception $e) {
            error_log("Newsletter attachment delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione dell\'allegato'];
        }
    }
    
    /**
     * Send newsletter immediately or schedule for later
     */
    public function send(int $id, ?string $scheduledAt = null): array
    {
        try {
            $newsletter = $this->model->getById($id);
            if (!$newsletter) {
                return ['success' => false, 'message' => 'Newsletter non trovata'];
            }
            
            // Get recipient filter
            $recipientFilter = json_decode($newsletter['recipient_filter'], true) ?? [];
            
            // Get recipients
            $recipients = $this->model->getRecipients($recipientFilter);
            if (empty($recipients)) {
                return ['success' => false, 'message' => 'Nessun destinatario trovato'];
            }
            
            // Get attachments
            $attachments = $this->model->getAttachments($id);
            $attachmentPaths = array_column($attachments, 'filepath');
            
            // Determine send time
            $sendTime = $scheduledAt ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null;
            
            // Queue emails for each recipient
            $sentCount = 0;
            $failedCount = 0;
            
            foreach ($recipients as $recipient) {
                try {
                    // Add to email queue
                    $queueSql = "INSERT INTO email_queue 
                                (recipient, subject, body, attachments, scheduled_at, status)
                                VALUES (?, ?, ?, ?, ?, 'pending')";
                    
                    $this->db->execute($queueSql, [
                        $recipient['email'],
                        $newsletter['subject'],
                        $newsletter['body_html'],
                        json_encode($attachmentPaths),
                        $sendTime
                    ]);
                    
                    $emailQueueId = $this->db->getLastInsertId();
                    
                    // Add recipient record
                    $this->model->addRecipient($id, [
                        'email' => $recipient['email'],
                        'recipient_name' => $recipient['name'] ?? null,
                        'recipient_type' => $recipient['type'],
                        'recipient_id' => $recipient['id'] ?? null,
                        'email_queue_id' => $emailQueueId,
                        'status' => 'pending'
                    ]);
                    
                    $sentCount++;
                } catch (\Exception $e) {
                    error_log("Failed to queue email for " . $recipient['email'] . ": " . $e->getMessage());
                    $failedCount++;
                }
            }
            
            // Update newsletter status
            if ($scheduledAt) {
                $this->model->markAsScheduled($id);
                $message = "Newsletter programmata con successo per il " . date('d/m/Y H:i', strtotime($scheduledAt));
            } else {
                // For immediate sending, we mark as sent but actual sending happens via cron
                $userId = $_SESSION['user_id'] ?? null;
                $this->model->markAsSent($id, $userId, count($recipients), $sentCount, $failedCount);
                $message = "Newsletter aggiunta alla coda di invio. $sentCount email in coda, $failedCount errori.";
            }
            
            return [
                'success' => true,
                'message' => $message,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount
            ];
        } catch (\Exception $e) {
            error_log("Newsletter send error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'invio della newsletter: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get available recipients for selection
     */
    public function getAvailableRecipients(): array
    {
        try {
            $recipients = [
                'members' => [],
                'junior_members' => [],
                'guardians' => []
            ];
            
            // Get active members
            $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email
                    FROM members
                    WHERE email IS NOT NULL AND email != ''
                    AND status = 'attivo'
                    ORDER BY last_name, first_name";
            $recipients['members'] = $this->db->query($sql);
            
            // Get active cadets
            $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email
                    FROM junior_members
                    WHERE email IS NOT NULL AND email != ''
                    AND status = 'attivo'
                    ORDER BY last_name, first_name";
            $recipients['junior_members'] = $this->db->query($sql);
            
            return $recipients;
        } catch (\Exception $e) {
            error_log("Get available recipients error: " . $e->getMessage());
            return ['members' => [], 'junior_members' => [], 'guardians' => []];
        }
    }
}
