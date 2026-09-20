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
     * Special status filter for active members without board approval sanction.
     */
    public const FILTER_ACTIVE_WITHOUT_APPROVAL = 'attivo_senza_approvazione';

    /**
     * Sanction type used for board approval.
     */
    public const BOARD_APPROVAL_SANCTION_TYPE = 'approvazione_consiglio_direttivo';

    /**
     * Valid sanction types
     */
    const VALID_TYPES = ['decaduto', 'dimesso', 'escluso', 'in_aspettativa', 'sospeso', 'in_congedo', 'attivo', self::BOARD_APPROVAL_SANCTION_TYPE];
    
    /**
     * Suspension sanction types that can be reversed by 'attivo'
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
     * Check whether the special "active without approval" filter is active.
     *
     * @param string|null $status
     * @return bool
     */
    public static function isActiveWithoutApprovalFilter($status) {
        return $status === self::FILTER_ACTIVE_WITHOUT_APPROVAL;
    }

    /**
     * Build NOT EXISTS SQL fragment for members without board approval sanction.
     *
     * @param string $memberAlias Table alias for members/junior_members
     * @param string $sanctionsTable Sanctions table name
     * @param string $memberForeignKey Foreign key column in sanctions table
     * @return string
     */
    public static function getMissingApprovalCondition($memberAlias, $sanctionsTable, $memberForeignKey) {
        return "NOT EXISTS (
            SELECT 1
            FROM {$sanctionsTable} approval_sanctions
            WHERE approval_sanctions.{$memberForeignKey} = {$memberAlias}.id
              AND approval_sanctions.sanction_type = ?
        )";
    }

    /**
     * Append the appropriate status filter clause to a query condition list.
     *
     * @param array $conditions
     * @param array $params
     * @param string|null $status
     * @param string $memberAlias
     * @param string $sanctionsTable
     * @param string $memberForeignKey
     * @return void
     */
    public static function appendStatusFilter(array &$conditions, array &$params, $status, $memberAlias, $sanctionsTable, $memberForeignKey) {
        if (empty($status)) {
            return;
        }

        if (self::isActiveWithoutApprovalFilter($status)) {
            $conditions[] = "{$memberAlias}.member_status = 'attivo'";
            $conditions[] = self::getMissingApprovalCondition($memberAlias, $sanctionsTable, $memberForeignKey);
            $params[] = self::BOARD_APPROVAL_SANCTION_TYPE;
            return;
        }

        $conditions[] = "{$memberAlias}.member_status = ?";
        $params[] = $status;
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
        
        // Special handling for attivo sanction
        // 'attivo' is a valid sanction type but NOT a valid member_status
        // It always sets the member status to 'attivo' (returning to active status)
        if ($sanctionType === 'attivo') {
            // Always return 'attivo' for 'attivo' sanction type
            // This ensures the member is set to active status regardless of previous sanctions
            return 'attivo';
        }
        
        // Special handling for approvazione_consiglio_direttivo
        // This represents board approval and should set the member to active status
        if ($sanctionType === self::BOARD_APPROVAL_SANCTION_TYPE) {
            return 'attivo';
        }
        
        // Apply status consolidation logic
        // If in_aspettativa or in_congedo -> set status to sospeso (unless already set to attivo by attivo)
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
            
            // Prepare update data with the new status
            $updateData = ['member_status' => $newStatus];
            
            // Update approval_date if sanction type is 'approvazione_consiglio_direttivo'
            if ($data['sanction_type'] === self::BOARD_APPROVAL_SANCTION_TYPE) {
                $updateData['approval_date'] = $data['sanction_date'];
            }
            
            // Update termination_date if sanction type is 'escluso', 'dimesso', or 'decaduto'
            if (in_array($data['sanction_type'], ['escluso', 'dimesso', 'decaduto'])) {
                $updateData['termination_date'] = $data['sanction_date'];
            }
            
            // Update member status and dates
            $memberModel->update($memberId, $updateData);

            // Keep approval_date aligned with the actual approval sanctions history.
            self::synchronizeApprovalDate($memberModel, $memberId);
            
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

    /**
     * Recalculate approval_date from the actual approval sanctions on record.
     *
     * @param object $memberModel
     * @param int $memberId
     * @return void
     */
    public static function synchronizeApprovalDate($memberModel, $memberId) {
        if (!method_exists($memberModel, 'getLatestSanctionDateByType')) {
            return;
        }

        $approvalDate = $memberModel->getLatestSanctionDateByType($memberId, self::BOARD_APPROVAL_SANCTION_TYPE);

        $memberModel->update($memberId, ['approval_date' => $approvalDate]);
    }
}
