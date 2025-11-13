<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/ForecastEngine.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('forecast_goruntule', 'Forecast görüntüleme yetkiniz bulunmamaktadır.');

$page_title = 'AI Tahmin Motoru (Forecast)';

// Forecast Engine
try {
    $forecastEngine = new ForecastEngine($pdo);
} catch (Exception $e) {
    die('Forecast Engine hatası: ' . $e->getMessage());
}

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Geçersiz token');
    }
    
    if ($_POST['action'] === 'calculate_forecast' && hasDetailedPermission('forecast_hesapla')) {
        set_time_limit(300); // 5 dakika
        
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $endDate = $_POST['end_date'] ?? date('Y-m-d', strtotime('+90 days'));
        $types = $_POST['forecast_types'] ?? ['revenue', 'occupancy', 'adr'];
        
        try {
            error_log("Forecast hesaplama başlatılıyor: $startDate - $endDate");
            $result = $forecastEngine->calculateForecasts($startDate, $endDate, $types);
            error_log("Forecast hesaplama sonucu: " . json_encode($result));
            
            if ($result['success']) {
                $success_message = "Forecast hesaplaması tamamlandı! {$result['records_processed']} kayıt oluşturuldu.";
            } else {
                $error_message = "Forecast hesaplama hatası: " . $result['error'];
            }
        } catch (Exception $e) {
            error_log("Forecast hesaplama exception: " . $e->getMessage());
            $error_message = "Forecast hesaplama hatası: " . $e->getMessage();
        }
    }
}

// Son 30 gün için forecast verilerini al
$stmt = $pdo->query("
    SELECT 
        forecast_type,
        target_date,
        predicted_value,
        confidence_score,
        lower_bound,
        upper_bound,
        actual_value
    FROM forecast_data
    WHERE target_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY target_date ASC, forecast_type ASC
");
$forecastData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grafik için verileri grupla
$chartData = [
    'dates' => [],
    'revenue' => ['predicted' => [], 'actual' => [], 'lower' => [], 'upper' => []],
    'occupancy' => ['predicted' => [], 'actual' => [], 'lower' => [], 'upper' => []],
    'adr' => ['predicted' => [], 'actual' => [], 'lower' => [], 'upper' => []]
];

foreach ($forecastData as $row) {
    $date = date('d.m', strtotime($row['target_date']));
    if (!in_array($date, $chartData['dates'])) {
        $chartData['dates'][] = $date;
    }
    
    $type = $row['forecast_type'];
    if (isset($chartData[$type])) {
        $chartData[$type]['predicted'][] = $row['predicted_value'];
        $chartData[$type]['actual'][] = $row['actual_value'] ?? null;
        $chartData[$type]['lower'][] = $row['lower_bound'];
        $chartData[$type]['upper'][] = $row['upper_bound'];
    }
}

// Uyarıları al
$stmt = $pdo->query("
    SELECT * FROM forecast_alerts
    WHERE is_dismissed = 0
    ORDER BY 
        FIELD(severity, 'critical', 'warning', 'info'),
        olusturma_tarihi DESC
    LIMIT 10
");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Özet istatistikler
$stmt = $pdo->query("
    SELECT 
        AVG(CASE WHEN forecast_type = 'occupancy' THEN predicted_value END) as avg_occupancy,
        AVG(CASE WHEN forecast_type = 'adr' THEN predicted_value END) as avg_adr,
        SUM(CASE WHEN forecast_type = 'revenue' THEN predicted_value END) as total_revenue,
        AVG(confidence_score) as avg_confidence
    FROM forecast_data
    WHERE target_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="container-fluid">
    <!-- Başlık -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-brain text-primary"></i> AI Tahmin Motoru</h1>
            <p class="text-muted mb-0">Yapay zeka destekli gelir ve doluluk tahminleri</p>
        </div>
        <?php if (hasDetailedPermission('forecast_hesapla')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#calculateModal">
            <i class="fas fa-calculator"></i> Yeni Tahmin Hesapla
        </button>
        <?php endif; ?>
    </div>

    <!-- Mesajlar -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Özet Kartlar -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Ortalama Doluluk (30 Gün)</p>
                            <h3 class="mb-0"><?= number_format($stats['avg_occupancy'] ?? 0, 1) ?>%</h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-bed fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Ortalama ADR</p>
                            <h3 class="mb-0">₺<?= number_format($stats['avg_adr'] ?? 0, 0) ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Tahmini Gelir (30 Gün)</p>
                            <h3 class="mb-0">₺<?= number_format($stats['total_revenue'] ?? 0, 0) ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-chart-line fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1 small">Ortalama Güven Skoru</p>
                            <h3 class="mb-0"><?= number_format($stats['avg_confidence'] ?? 0, 0) ?>%</h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-star fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Uyarılar -->
    <?php if (!empty($alerts)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-bell text-warning"></i> Akıllı Uyarılar</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($alerts as $alert): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <?php
                                        $badgeClass = [
                                            'critical' => 'danger',
                                            'warning' => 'warning',
                                            'info' => 'info'
                                        ][$alert['severity']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?> me-2"><?= strtoupper($alert['severity']) ?></span>
                                        <strong><?= htmlspecialchars($alert['title']) ?></strong>
                                        <?php if ($alert['target_date']): ?>
                                            <small class="text-muted ms-2">
                                                <i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($alert['target_date'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($alert['message']) ?></p>
                                    <?php if ($alert['recommended_action']): ?>
                                        <p class="mb-0 small text-primary">
                                            <i class="fas fa-lightbulb"></i> <strong>Öneri:</strong> <?= htmlspecialchars($alert['recommended_action']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary ms-3" onclick="dismissAlert(<?= $alert['id'] ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grafikler -->
    <div class="row g-3 mb-4">
        <!-- Gelir Tahmini -->
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-area text-success"></i> Gelir Tahmini (30 Gün)</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Doluluk Oranı -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-percentage text-primary"></i> Doluluk Oranı Tahmini</h5>
                </div>
                <div class="card-body">
                    <canvas id="occupancyChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <!-- ADR Tahmini -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-coins text-warning"></i> ADR Tahmini</h5>
                </div>
                <div class="card-body">
                    <canvas id="adrChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hesaplama Modal -->
<?php if (hasDetailedPermission('forecast_hesapla')): ?>
<div class="modal fade" id="calculateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= generateCSRFTokenInput(); ?>
                <input type="hidden" name="action" value="calculate_forecast">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calculator"></i> Forecast Hesapla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+90 days')) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tahmin Tipleri</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="forecast_types[]" value="revenue" id="type_revenue" checked>
                            <label class="form-check-label" for="type_revenue">Gelir (Revenue)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="forecast_types[]" value="occupancy" id="type_occupancy" checked>
                            <label class="form-check-label" for="type_occupancy">Doluluk (Occupancy)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="forecast_types[]" value="adr" id="type_adr" checked>
                            <label class="form-check-label" for="type_adr">ADR (Average Daily Rate)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="forecast_types[]" value="revpar" id="type_revpar">
                            <label class="form-check-label" for="type_revpar">RevPAR</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="forecast_types[]" value="demand" id="type_demand">
                            <label class="form-check-label" for="type_demand">Talep (Demand)</label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> Hesaplama işlemi veri miktarına göre 1-5 dakika sürebilir.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play"></i> Hesaplamayı Başlat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Chart verileri
const chartData = <?= json_encode($chartData) ?>;

// Gelir Grafiği
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: chartData.dates,
        datasets: [{
            label: 'Tahmini Gelir',
            data: chartData.revenue.predicted,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.4
        }, {
            label: 'Alt Sınır',
            data: chartData.revenue.lower,
            borderColor: 'rgba(75, 192, 192, 0.3)',
            borderDash: [5, 5],
            fill: false
        }, {
            label: 'Üst Sınır',
            data: chartData.revenue.upper,
            borderColor: 'rgba(75, 192, 192, 0.3)',
            borderDash: [5, 5],
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ₺' + context.parsed.y.toLocaleString('tr-TR');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₺' + value.toLocaleString('tr-TR');
                    }
                }
            }
        }
    }
});

// Doluluk Grafiği
new Chart(document.getElementById('occupancyChart'), {
    type: 'line',
    data: {
        labels: chartData.dates,
        datasets: [{
            label: 'Doluluk Oranı',
            data: chartData.occupancy.predicted,
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Doluluk: %' + context.parsed.y.toFixed(1);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return '%' + value;
                    }
                }
            }
        }
    }
});

// ADR Grafiği
new Chart(document.getElementById('adrChart'), {
    type: 'bar',
    data: {
        labels: chartData.dates,
        datasets: [{
            label: 'Ortalama Oda Fiyatı',
            data: chartData.adr.predicted,
            backgroundColor: 'rgba(255, 206, 86, 0.6)',
            borderColor: 'rgb(255, 206, 86)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'ADR: ₺' + context.parsed.y.toLocaleString('tr-TR');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₺' + value.toLocaleString('tr-TR');
                    }
                }
            }
        }
    }
});

// Uyarı kapat
function dismissAlert(alertId) {
    if (!confirm('Bu uyarıyı kapatmak istediğinize emin misiniz?')) return;
    
    fetch('ajax/dismiss-forecast-alert.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            alert_id: alertId,
            csrf_token: '<?= generateCSRFToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Hata: ' + data.message);
        }
    });
}
</script>

<?php include 'footer.php'; ?>

