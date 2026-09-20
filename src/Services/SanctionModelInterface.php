<?php
namespace EasyVol\Services;

/**
 * Shared contract for member models that support sanction persistence and approval-date synchronization.
 */
interface SanctionModelInterface {
    /**
     * Create a sanction for the current entity owner.
     *
     * @param int $memberId Owner ID (member or junior member, depending on implementation)
     * @param array $data Sanction payload to persist
     * @return mixed
     */
    public function addSanction($memberId, $data);

    /**
     * Update an existing sanction by its ID.
     *
     * @param int $id Sanction ID
     * @param array $data Sanction payload to persist
     * @return mixed
     */
    public function updateSanction($id, $data);

    /**
     * Return all sanctions for the specified owner.
     *
     * @param int $memberId Owner ID (member or junior member, depending on implementation)
     * @return array
     */
    public function getSanctions($memberId);

    /**
     * Update the owning entity record.
     *
     * @param int $id Owner ID (member or junior member, depending on implementation)
     * @param array $data Fields to update
     * @return mixed
     */
    public function update($id, $data);

    /**
     * Return the latest sanction date for the given sanction type and owner.
     *
     * @param int $memberId Owner ID (member or junior member, depending on implementation)
     * @param string $sanctionType Sanction type to search
     * @return string|null
     */
    public function getLatestSanctionDateByType($memberId, $sanctionType);

    /**
     * Persist the approval date for the owning entity.
     *
     * @param int $memberId Owner ID (member or junior member, depending on implementation)
     * @param string|null $approvalDate Approval date in Y-m-d format, or null to clear it
     * @return mixed
     */
    public function setApprovalDate($memberId, $approvalDate);

    /**
     * Return a sanction scoped to the specified owner.
     *
     * @param int $memberId Owner ID (member or junior member, depending on implementation)
     * @param int $sanctionId Sanction ID
     * @return array|false
     */
    public function getSanctionById($memberId, $sanctionId);

    /**
     * Delete a sanction, optionally scoped to the specified owner.
     *
     * @param int $id Sanction ID
     * @param int|null $memberId Owner ID (member or junior member, depending on implementation)
     * @return mixed
     */
    public function deleteSanction($id, $memberId = null);
}
