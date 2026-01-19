<?php
/**
 * Advanced Dashboard with Statistics and Charts
 */

require_once __DIR__ . '/../src/Autoloader.php';
EasyVol\Autoloader::register();

use EasyVol\App;
use EasyVol\Utils\AutoLogger;
use EasyVol\Controllers\DashboardController;

$app = App::getInstance();

if (!$app->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permission for advanced dashboard
$hasAdvancedPermission = $app->checkPermission('dashboard', 'view_advanced');

$db = $app->getDb();
$config = $app->getConfig();
$user = $app->getCurrentUser();
$controller = new DashboardController($db, $config, $user['id']);

// Get dashboard data
try {
    $dashboardData = $controller->getDashboardData(true);
    $kpis = $dashboardData['kpis'];
    
    // Get year-over-year stats if user has advanced permission
    $yoyEventStats = $hasAdvancedPermission ? $controller->getYoYEventStats() : [];
    $yoyMemberStats = $hasAdvancedPermission ? $controller->getYoYMemberStats() : [];
    
    // Get geographic data if available
    $startDate = date('Y-01-01');
    $geoData = $hasAdvancedPermission ? $controller->getGeographicInterventionData($startDate) : [];
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $kpis = [];
    $yoyEventStats = [];
    $yoyMemberStats = [];
    $geoData = [];
}

// Log page access
AutoLogger::logPageAccess();

$pageTitle = 'Dashboard Statistiche Avanzate';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - EasyVol</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Leaflet for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .kpi-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .kpi-card.primary { border-left-color: #0d6efd; }
        .kpi-card.success { border-left-color: #198754; }
        .kpi-card.warning { border-left-color: #ffc107; }
        .kpi-card.danger { border-left-color: #dc3545; }
        .kpi-card.info { border-left-color: #0dcaf0; }
        .kpi-card.secondary { border-left-color: #6c757d; }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }
        
        #interventionMap {
            height: 500px;
            border-radius: 8px;
        }
        
        .export-btn {
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../src/Views/includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../src/Views/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-graph-up"></i> <?= htmlspecialchars($pageTitle) ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-speedometer2"></i> Dashboard Base
                            </a>
                            <a href="reports.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-file-earmark-text"></i> Report
                            </a>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card kpi-card primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Soci Attivi</h6>
                                        <h2 class="mb-0"><?= number_format($kpis['active_members']['value'] ?? 0) ?></h2>
                                    </div>
                                    <div class="text-primary">
                                        <i class="bi bi-people fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card kpi-card success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Cadetti</h6>
                                        <h2 class="mb-0"><?= number_format($kpis['junior_members']['value'] ?? 0) ?></h2>
                                    </div>
                                    <div class="text-success">
                                        <i class="bi bi-person-badge fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card kpi-card info h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Mezzi Operativi</h6>
                                        <h2 class="mb-0"><?= number_format($kpis['operational_vehicles']['value'] ?? 0) ?></h2>
                                    </div>
                                    <div class="text-info">
                                        <i class="bi bi-truck fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card kpi-card warning h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Eventi Attivi</h6>
                                        <h2 class="mb-0"><?= number_format($kpis['active_events']['value'] ?? 0) ?></h2>
                                    </div>
                                    <div class="text-warning">
                                        <i class="bi bi-calendar-event fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional KPIs Row -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card kpi-card secondary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Corsi Attivi</h6>
                                        <h2 class="mb-0"><?= number_format($kpis['active_training']['value'] ?? 0) ?></h2>
                                    </div>
                                    <div class="text-secondary">
                                        <i class="bi bi-mortarboard fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card kpi-card danger h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Articoli Sotto Scorta</h6>
                                        <h2 class="mb-0"><?= number_format($kpis['low_stock_items']['value'] ?? 0) ?></h2>
                                    </div>
                                    <div class="text-danger">
                                        <i class="bi bi-exclamation-triangle fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card kpi-card primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Interventi YTD</h6>
                                        <h2 class="mb-0"><?= number_format($kpis['ytd_interventions']['value'] ?? 0) ?></h2>
                                    </div>
                                    <div class="text-primary">
                                        <i class="bi bi-activity fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card kpi-card success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Ore Volontariato YTD</h6>
                                        <h2 class="mb-0"><?= number_format($kpis['ytd_volunteer_hours']['value'] ?? 0) ?></h2>
                                    </div>
                                    <div class="text-success">
                                        <i class="bi bi-clock-history fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($hasAdvancedPermission): ?>
                <!-- Charts Section -->
                <div class="row mb-4">
                    <!-- Event Statistics Chart -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Eventi per Tipo</h5>
                                <button class="btn btn-sm btn-outline-primary export-btn" onclick="exportChart('eventStatsChart', 'eventi_per_tipo')">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="eventStatsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Event Trend Chart -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Trend Eventi Mensili</h5>
                                <button class="btn btn-sm btn-outline-primary export-btn" onclick="exportChart('monthlyTrendChart', 'trend_eventi_mensili')">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- Volunteer Activity Chart -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-people"></i> Top 20 Volontari per Ore</h5>
                                <button class="btn btn-sm btn-outline-primary export-btn" onclick="exportChart('volunteerActivityChart', 'volontari_top_ore')">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="volunteerActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Warehouse Stock Chart -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-box-seam"></i> Stato Magazzino</h5>
                                <button class="btn btn-sm btn-outline-primary export-btn" onclick="exportChart('warehouseChart', 'stato_magazzino')">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="warehouseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Geographic Map -->
                <?php if (!empty($geoData)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-map"></i> Mappa Geografica Interventi</h5>
                            </div>
                            <div class="card-body">
                                <div id="interventionMap"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; // hasAdvancedPermission ?>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dashboard data from PHP
        const dashboardData = <?= json_encode($dashboardData) ?>;
        const geoData = <?= json_encode($geoData) ?>;
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($hasAdvancedPermission): ?>
            initEventStatsChart();
            initMonthlyTrendChart();
            initVolunteerActivityChart();
            initWarehouseChart();
            
            <?php if (!empty($geoData)): ?>
            initInterventionMap();
            <?php endif; ?>
            <?php endif; ?>
        });
        
        // Event Statistics Chart
        function initEventStatsChart() {
            const ctx = document.getElementById('eventStatsChart').getContext('2d');
            const eventStats = dashboardData.event_stats || [];
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: eventStats.map(e => e.event_type),
                    datasets: [{
                        label: 'Totale',
                        data: eventStats.map(e => e.count),
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Completati',
                        data: eventStats.map(e => e.completed),
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Monthly Trend Chart
        function initMonthlyTrendChart() {
            const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
            const monthlyTrend = dashboardData.monthly_trend || [];
            
            // Group by event type
            const eventTypes = [...new Set(monthlyTrend.map(m => m.event_type))];
            const months = [...new Set(monthlyTrend.map(m => m.month))].sort();
            
            const datasets = eventTypes.map((type, index) => {
                const colors = [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)'
                ];
                
                return {
                    label: type,
                    data: months.map(month => {
                        const found = monthlyTrend.find(m => m.month === month && m.event_type === type);
                        return found ? found.count : 0;
                    }),
                    backgroundColor: colors[index % colors.length],
                    borderColor: colors[index % colors.length].replace('0.5', '1'),
                    borderWidth: 2,
                    fill: false
                };
            });
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Volunteer Activity Chart
        function initVolunteerActivityChart() {
            const ctx = document.getElementById('volunteerActivityChart').getContext('2d');
            const volunteerActivity = dashboardData.volunteer_activity || [];
            
            new Chart(ctx, {
                type: 'horizontalBar',
                data: {
                    labels: volunteerActivity.map(v => v.name),
                    datasets: [{
                        label: 'Ore Totali',
                        data: volunteerActivity.map(v => v.total_hours),
                        backgroundColor: 'rgba(255, 159, 64, 0.5)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Warehouse Stock Chart
        function initWarehouseChart() {
            const ctx = document.getElementById('warehouseChart').getContext('2d');
            const warehouseStats = dashboardData.warehouse_stats || [];
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: warehouseStats.map(w => w.category),
                    datasets: [{
                        data: warehouseStats.map(w => w.total_quantity),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)',
                            'rgba(255, 159, 64, 0.5)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
        
        // Initialize Intervention Map
        function initInterventionMap() {
            if (geoData.length === 0) return;
            
            // Initialize map centered on Italy
            const map = L.map('interventionMap').setView([42.5, 12.5], 6);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add markers for each intervention
            geoData.forEach(intervention => {
                if (intervention.latitude && intervention.longitude) {
                    const marker = L.marker([intervention.latitude, intervention.longitude])
                        .addTo(map);
                    
                    marker.bindPopup(`
                        <strong>${intervention.title}</strong><br>
                        ${intervention.municipality || ''}, ${intervention.province || ''}<br>
                        Tipo: ${intervention.event_type || ''}<br>
                        Volontari: ${intervention.volunteer_count || 0}<br>
                        Ore: ${intervention.total_hours || 0}
                    `);
                }
            });
        }
        
        // Export chart as image
        function exportChart(chartId, filename) {
            const canvas = document.getElementById(chartId);
            const url = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = filename + '.png';
            link.href = url;
            link.click();
        }
    </script>
</body>
</html>
