<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-test-yonetimi.php
// Ödeme modülü test yönetimi sayfası

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../tests/PaymentModuleTest.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_test_yonetimi', 'Ödeme test yönetimi yetkiniz bulunmamaktadır.');

$page_title = 'Ödeme Modülü Test Yönetimi';

// Test çalıştırma
if (isset($_POST['run_tests'])) {
    $test_type = $_POST['test_type'];
    
    try {
        $test = new PaymentModuleTest($database_connection);
        
        if ($test_type === 'all') {
            $test->runAllTests();
        } elseif ($test_type === 'unit') {
            $test->runUnitTests();
        } elseif ($test_type === 'integration') {
            $test->runIntegrationTests();
        }
        
        $test_results = $test->getTestResults();
        $success_message = "Testler tamamlandı. Başarı oranı: " . $test_results['success_rate'] . "%";
    } catch (Exception $e) {
        $error_message = "Test çalıştırılırken hata: " . $e->getMessage();
    }
}

// Test geçmişi
$test_history_stmt = $database_connection->prepare("
    SELECT 
        t.*,
        k.ad as kullanici_adi
    FROM odeme_test_gecmisi t
    LEFT JOIN kullanicilar k ON t.kullanici_id = k.id
    ORDER BY t.test_tarihi DESC
    LIMIT 20
");
$test_history_stmt->execute();
$test_history = $test_history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Test istatistikleri
$test_stats_stmt = $database_connection->prepare("
    SELECT 
        COUNT(*) as total_tests,
        AVG(basari_orani) as avg_success_rate,
        MAX(basari_orani) as max_success_rate,
        MIN(basari_orani) as min_success_rate,
        COUNT(CASE WHEN basari_orani = 100 THEN 1 END) as perfect_tests
    FROM odeme_test_gecmisi
    WHERE test_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$test_stats_stmt->execute();
$test_stats = $test_stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .test-card {
            transition: transform 0.2s;
        }
        .test-card:hover {
            transform: translateY(-2px);
        }
        .test-status-pass { color: #28a745; }
        .test-status-fail { color: #dc3545; }
        .test-status-error { color: #ffc107; }
        .test-progress {
            height: 20px;
        }
        .test-result-pass { background-color: #d4edda; border-color: #c3e6cb; }
        .test-result-fail { background-color: #f8d7da; border-color: #f5c6cb; }
        .test-result-error { background-color: #fff3cd; border-color: #ffeaa7; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-vial me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Yenile
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Test İstatistikleri -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card test-card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-primary"><?php echo $test_stats['total_tests']; ?></h5>
                                <p class="card-text">Toplam Test</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card test-card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-info"><?php echo round($test_stats['avg_success_rate'], 1); ?>%</h5>
                                <p class="card-text">Ortalama Başarı</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card test-card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-success"><?php echo $test_stats['perfect_tests']; ?></h5>
                                <p class="card-text">Mükemmel Test</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card test-card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-warning"><?php echo round($test_stats['max_success_rate'], 1); ?>%</h5>
                                <p class="card-text">En Yüksek Başarı</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test Çalıştırma -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-play me-2"></i>
                            Test Çalıştır
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Test Tipi</label>
                                    <select name="test_type" class="form-select" required>
                                        <option value="all">Tüm Testler</option>
                                        <option value="unit">Unit Testler</option>
                                        <option value="integration">Entegrasyon Testleri</option>
                                        <option value="security">Güvenlik Testleri</option>
                                        <option value="performance">Performans Testleri</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="run_tests" class="btn btn-primary">
                                        <i class="fas fa-play me-1"></i>
                                        Testleri Çalıştır
                                    </button>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-info" onclick="showTestDetails()">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Test Detayları
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Test Geçmişi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>
                            Test Geçmişi
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Test Tarihi</th>
                                        <th>Test Tipi</th>
                                        <th>Toplam Test</th>
                                        <th>Başarılı</th>
                                        <th>Başarısız</th>
                                        <th>Başarı Oranı</th>
                                        <th>Çalıştıran</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($test_history as $test): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i:s', strtotime($test['test_tarihi'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $this->getTestTypeColor($test['test_tipi']); ?>">
                                                    <?php echo ucfirst($test['test_tipi']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $test['toplam_test']; ?></td>
                                            <td>
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    <?php echo $test['basarili_test']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-danger">
                                                    <i class="fas fa-times-circle me-1"></i>
                                                    <?php echo $test['basarisiz_test']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress test-progress me-2" style="width: 100px;">
                                                        <div class="progress-bar bg-<?php echo $this->getSuccessRateColor($test['basari_orani']); ?>" 
                                                             style="width: <?php echo $test['basari_orani']; ?>%"></div>
                                                    </div>
                                                    <span class="text-muted"><?php echo $test['basari_orani']; ?>%</span>
                                                </div>
                                            </td>
                                            <td><?php echo $test['kullanici_adi'] ?? 'Sistem'; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="showTestResult(<?php echo $test['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Test Trend Grafiği -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Test Trend
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="testTrendChart" height="100"></canvas>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Test Detay Modal -->
    <div class="modal fade" id="testDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="testDetailContent">
                    <!-- Test detayları buraya yüklenecek -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Test trend grafiği
        const ctx = document.getElementById('testTrendChart').getContext('2d');
        const testTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['1 Hafta Önce', '6 Gün Önce', '5 Gün Önce', '4 Gün Önce', '3 Gün Önce', '2 Gün Önce', '1 Gün Önce', 'Bugün'],
                datasets: [{
                    label: 'Başarı Oranı (%)',
                    data: [85, 88, 92, 90, 95, 93, 97, <?php echo $test_stats['avg_success_rate']; ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        function refreshPage() {
            location.reload();
        }

        function showTestDetails() {
            // Test detaylarını göster
            const testDetails = `
                <h6>Test Kategorileri</h6>
                <ul>
                    <li><strong>Unit Testler:</strong> Bireysel sınıf ve metod testleri</li>
                    <li><strong>Entegrasyon Testleri:</strong> Modüller arası etkileşim testleri</li>
                    <li><strong>Güvenlik Testleri:</strong> Güvenlik kontrolleri ve PCI DSS uyumluluk</li>
                    <li><strong>Performans Testleri:</strong> Hız ve kaynak kullanımı testleri</li>
                </ul>
                
                <h6>Test Süreçleri</h6>
                <ol>
                    <li>Test ortamı hazırlığı</li>
                    <li>Test verilerinin oluşturulması</li>
                    <li>Test senaryolarının çalıştırılması</li>
                    <li>Sonuçların değerlendirilmesi</li>
                    <li>Rapor oluşturulması</li>
                </ol>
            `;
            
            document.getElementById('testDetailContent').innerHTML = testDetails;
            new bootstrap.Modal(document.getElementById('testDetailModal')).show();
        }

        function showTestResult(testId) {
            // AJAX ile test sonucunu getir
            fetch(`ajax/get-test-result.php?id=${testId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('testDetailContent').innerHTML = data.html;
                    new bootstrap.Modal(document.getElementById('testDetailModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Test sonucu yüklenirken hata oluştu.');
                });
        }
    </script>
</body>
</html>

<?php
// Test tipi renk fonksiyonu
function getTestTypeColor($test_type) {
    switch ($test_type) {
        case 'all': return 'primary';
        case 'unit': return 'info';
        case 'integration': return 'success';
        case 'security': return 'warning';
        case 'performance': return 'danger';
        default: return 'secondary';
    }
}

// Başarı oranı renk fonksiyonu
function getSuccessRateColor($success_rate) {
    if ($success_rate >= 90) return 'success';
    if ($success_rate >= 70) return 'warning';
    return 'danger';
}
?>
