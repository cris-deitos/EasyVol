<?php
namespace EasyVol\Services;

interface SanctionModelInterface {
    public function addSanction($memberId, $data);
    public function updateSanction($id, $data);
    public function getSanctions($memberId);
    public function update($id, $data);
    public function getLatestSanctionDateByType($memberId, $sanctionType);
    public function setApprovalDate($memberId, $approvalDate);
    public function getSanctionById($memberId, $sanctionId);
    public function deleteSanction($id, $memberId = null);
}
