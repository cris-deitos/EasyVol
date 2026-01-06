<?php
/**
 * Reusable Pagination Component
 * 
 * Usage:
 * include __DIR__ . '/../src/Views/includes/pagination.php';
 * 
 * Required variables:
 * - $page: Current page number
 * - $totalPages: Total number of pages
 * 
 * Optional variables:
 * - $showInfo: Show "Mostrando X-Y di Z risultati" (default: true)
 * - $totalResults: Total number of results (required if $showInfo is true)
 * - $perPage: Results per page (required if $showInfo is true)
 */

// Set defaults
$showInfo = $showInfo ?? true;

// Build query string preserving existing parameters
$queryString = $_GET;
unset($queryString['page']);
$queryBase = http_build_query($queryString);
$queryBase = $queryBase ? $queryBase . '&' : '';

// Calculate result range for display
if ($showInfo && isset($totalResults) && isset($perPage)) {
    $startResult = ($page - 1) * $perPage + 1;
    $endResult = min($page * $perPage, $totalResults);
}
?>

<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-3">
    <?php if ($showInfo && isset($totalResults) && isset($perPage)): ?>
    <div class="text-muted small">
        Mostrando <?= $startResult ?>-<?= $endResult ?> di <?= $totalResults ?> risultati
    </div>
    <?php else: ?>
    <div></div>
    <?php endif; ?>
    
    <nav aria-label="Navigazione pagine">
        <ul class="pagination pagination-sm mb-0 justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= $queryBase ?>page=1" title="Prima pagina">
                        <i class="bi bi-chevron-bar-left"></i> Prima
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?<?= $queryBase ?>page=<?= $page - 1 ?>" title="Pagina precedente">
                        <i class="bi bi-chevron-left"></i> Precedente
                    </a>
                </li>
            <?php endif; ?>
            
            <?php
            // Show max 5 page numbers at a time
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            // Adjust if we're near the beginning or end
            if ($page <= 2) {
                $endPage = min($totalPages, 5);
            }
            if ($page >= $totalPages - 1) {
                $startPage = max(1, $totalPages - 4);
            }
            ?>
            
            <?php if ($startPage > 1): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            <?php endif; ?>
            
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= $queryBase ?>page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ($endPage < $totalPages): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            <?php endif; ?>
            
            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= $queryBase ?>page=<?= $page + 1 ?>" title="Pagina successiva">
                        Successiva <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?<?= $queryBase ?>page=<?= $totalPages ?>" title="Ultima pagina">
                        Ultima <i class="bi bi-chevron-bar-right"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>
