<?php
namespace EasyVol\Services;

/**
 * Sanction Service
 * 
 * Centralized service for handling member and junior member sanctions.
 * Eliminates code duplication and provides consistent sanction logic.
 */
class SanctionService {
    /**
     * Valid sanction types
     */
    const VALID_TYPES = ['decaduto', 'dimesso', 'in_aspettativa', 'sospeso', 'in_congedo', 'operativo'];
    
    /**
     * Suspension sanction types that can be reversed by 'operativo'
     */
    const SUSPENSION_TYPES = ['in_aspettativa', 'sospeso', 'in_congedo'];
    
    /**
     * Sanction types that consolidate to 'sospeso' status
     */
    const CONSOLIDATE_TO_SOSPESO = ['in_aspettativa', 'in_congedo'];
    
    /**
     * Validate sanction type
     * 
     * @param string $sanctionType The sanction type to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidType($sanctionType) {
        return in_array($sanctionType, self::VALID_TYPES);
    }
    
    /**
     * Calculate the new member status based on sanction type and history
     * 
     * @param string $sanctionType The type of sanction being applied
     * @param string $sanctionDate The date of the sanction (Y-m-d format)
     * @param array $allSanctions All existing sanctions for the member
     * @return string The new status to apply to the member
     */
    public static function calculateNewStatus($sanctionType, $sanctionDate, $allSanctions) {
        $newStatus = $sanctionType;
        
        // Special handling for operativo sanction
        if ($sanctionType === 'operativo') {
            $hasPreviousSuspension = self::hasPreviousSuspension($sanctionDate, $allSanctions);
            
            // If operativo comes after a suspension, return to active status
            if ($hasPreviousSuspension) {
                $newStatus = 'attivo';
            }
        }
        
        // Apply status consolidation logic
        // If in_aspettativa or in_congedo -> set status to sospeso (unless already set to attivo by operativo)
        if (in_array($sanctionType, self::CONSOLIDATE_TO_SOSPESO) && $newStatus === $sanctionType) {
            $newStatus = 'sospeso';
        }
        
        return $newStatus;
    }
    
    /**
     * Check if there's a previous suspension before the given date
     * 
     * @param string $currentDate The date to check against (Y-m-d format)
     * @param array $allSanctions All existing sanctions for the member
     * @return bool True if there's a previous suspension, false otherwise
     */
    private static function hasPreviousSuspension($currentDate, $allSanctions) {
        $currentTimestamp = strtotime($currentDate);
        
        foreach ($allSanctions as $sanction) {
            $sanctionTimestamp = strtotime($sanction['sanction_date']);
            if ($sanctionTimestamp < $currentTimestamp && 
                in_array($sanction['sanction_type'], self::SUSPENSION_TYPES)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Process a sanction save operation (create or update)
     * 
     * @param object $memberModel The member model instance (Member or JuniorMember)
     * @param int $memberId The ID of the member
     * @param int $sanctionId The ID of the sanction (0 for new sanction)
     * @param array $data The sanction data
     * @return array Result array with 'success' and optional 'error' keys
     */
    public static function processSanction($memberModel, $memberId, $sanctionId, $data) {
        try {
            // Validate sanction type
            if (!self::isValidType($data['sanction_type'])) {
                return ['success' => false, 'error' => 'Tipo di provvedimento non valido'];
            }
            
            // Save or update the sanction
            if ($sanctionId > 0) {
                $memberModel->updateSanction($sanctionId, $data);
            } else {
                $memberModel->addSanction($memberId, $data);
            }
            
            // Get all sanctions to calculate new status
            $allSanctions = $memberModel->getSanctions($memberId);
            
            // Calculate the new status based on sanction type and history
            $newStatus = self::calculateNewStatus(
                $data['sanction_type'],
                $data['sanction_date'],
                $allSanctions
            );
            
            // Update member status
            $memberModel->update($memberId, ['member_status' => $newStatus]);
            
            return ['success' => true, 'new_status' => $newStatus];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Validate sanction date
     * 
     * @param string $date The date to validate (Y-m-d format)
     * @return bool True if valid, false otherwise
     */
    public static function isValidDate($date) {
        if (empty($date)) {
            return false;
        }
        
        $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
        return $dateTime && $dateTime->format('Y-m-d') === $date;
    }
}
