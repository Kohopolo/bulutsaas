<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-compliance-yonetimi.php
// Ödeme compliance yönetimi sayfası

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/compliance/PaymentCompliance.php';
require_once '../includes/logging/PaymentLogger.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_compliance_goruntule', 'Ödeme compliance yönetimi yetkiniz bulunmamaktadır.');

$logger = new PaymentLogger($database_connection);
$compliance = new PaymentCompliance($database_connection, $logger);

$page_title = 'Ödeme Compliance Yönetimi';

// Compliance raporu oluştur
if (isset($_POST['generate_report'])) {
    try {
        $compliance_report = $compliance->generateComplianceReport();
        $success_message = "Compliance raporu başarıyla oluşturuldu. Genel skor: " . $compliance_report['overall_score'] . "%";
    } catch (Exception $e) {
        $error_message = "Compliance raporu oluşturulurken hata: " . $e->getMessage();
    }
}

// Son compliance raporunu al
$stmt = $database_connection->prepare("
    SELECT * FROM odeme_compliance_raporlari 
    ORDER BY rapor_tarihi DESC 
    LIMIT 1
");
$stmt->execute();
$last_report = $stmt->fetch(PDO::FETCH_ASSOC);

// Compliance istatistikleri
$stats_stmt = $database_connection->prepare("
    SELECT 
        COUNT(*) as total_reports,
        AVG(genel_skor) as avg_score,
        MAX(genel_skor) as max_score,
        MIN(genel_skor) as min_score
    FROM odeme_compliance_raporlari
");
$stats_stmt->execute();
$compliance_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Güvenlik açıkları
$vulnerabilities_stmt = $database_connection->prepare("
    SELECT 
        COUNT(*) as total_vulnerabilities,
        SUM(CASE WHEN durum = 'acik' THEN 1 ELSE 0 END) as open_vulnerabilities,
        SUM(CASE WHEN oncelik = 'kritik' THEN 1 ELSE 0 END) as critical_vulnerabilities
    FROM odeme_guvenlik_aciklari
");
$vulnerabilities_stmt->execute();
$vulnerability_stats = $vulnerabilities_stmt->fetch(PDO::FETCH_ASSOC);
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
        .compliance-score {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .score-excellent { color: #28a745; }
        .score-good { color: #17a2b8; }
        .score-warning { color: #ffc107; }
        .score-danger { color: #dc3545; }
        .requirement-card {
            transition: transform 0.2s;
        }
        .requirement-card:hover {
            transform: translateY(-2px);
        }
        .status-compliant { color: #28a745; }
        .status-partial { color: #ffc107; }
        .status-non-compliant { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-shield-alt me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Yenile
                            </button>
                            <?php if (hasPermission('odeme_compliance_rapor')): ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="generate_report" class="btn btn-sm btn-primary">
                                    <i class="fas fa-file-alt"></i> Rapor Oluştur
                                </button>
                            </form>
                            <?php endif; ?>
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

                <!-- Compliance İstatistikleri -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-primary"><?php echo $compliance_stats['total_reports']; ?></h5>
                                <p class="card-text">Toplam Rapor</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-info"><?php echo round($compliance_stats['avg_score'], 1); ?>%</h5>
                                <p class="card-text">Ortalama Skor</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-warning"><?php echo $vulnerability_stats['open_vulnerabilities']; ?></h5>
                                <p class="card-text">Açık Güvenlik Açığı</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-danger"><?php echo $vulnerability_stats['critical_vulnerabilities']; ?></h5>
                                <p class="card-text">Kritik Açık</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son Compliance Raporu -->
                <?php if ($last_report): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Son Compliance Raporu
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <div class="compliance-score <?php echo $this->getScoreClass($last_report['genel_skor']); ?>">
                                            <?php echo $last_report['genel_skor']; ?>%
                                        </div>
                                        <p class="text-muted">Genel PCI DSS Skoru</p>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Rapor Detayları</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Tarih:</strong> <?php echo date('d.m.Y H:i', strtotime($last_report['rapor_tarihi'])); ?></li>
                                            <li><strong>Oluşturan:</strong> <?php echo $last_report['olusturan_kullanici'] ?? 'Sistem'; ?></li>
                                            <li><strong>Durum:</strong> 
                                                <span class="badge bg-<?php echo $this->getScoreClass($last_report['genel_skor']); ?>">
                                                    <?php echo $this->getScoreStatus($last_report['genel_skor']); ?>
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Hızlı İşlemler</h6>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-primary btn-sm" onclick="showReportDetails(<?php echo $last_report['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>
                                                Detayları Görüntüle
                                            </button>
                                            <button class="btn btn-outline-success btn-sm" onclick="exportReport(<?php echo $last_report['id']; ?>)">
                                                <i class="fas fa-download me-1"></i>
                                                Raporu İndir
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PCI DSS Gereksinimleri -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list-check me-2"></i>
                                    PCI DSS Gereksinimleri
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    $requirements = [
                                        '1' => 'Güvenli ağ ve sistemler kurun ve sürdürün',
                                        '2' => 'Kart sahibi verilerini korumak için güvenlik yapılandırması uygulayın',
                                        '3' => 'Kart sahibi verilerini koruyun',
                                        '4' => 'Açık, genel ağlarda kart sahibi verilerini şifreleyin',
                                        '5' => 'Antivirüs yazılımı kullanın ve düzenli olarak güncelleyin',
                                        '6' => 'Güvenli sistemler ve uygulamalar geliştirin ve sürdürün',
                                        '7' => 'İş gereksinimlerine göre kart sahibi verilerine erişimi kısıtlayın',
                                        '8' => 'Bilgisayar erişimini benzersiz kimliklerle tanımlayın ve atayın',
                                        '9' => 'Fiziksel erişimi kart sahibi verilerine kısıtlayın',
                                        '10' => 'Ağ kaynaklarına ve kart sahibi verilerine erişimi izleyin ve test edin',
                                        '11' => 'Güvenlik sistemlerini ve süreçlerini düzenli olarak test edin',
                                        '12' => 'Güvenlik politikası oluşturun ve sürdürün'
                                    ];
                                    
                                    foreach ($requirements as $req_id => $req_name):
                                        $score = $last_report ? json_decode($last_report['gereksinim_detaylari'], true)[$req_id]['score'] ?? 0 : 0;
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card requirement-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title">Gereksinim <?php echo $req_id; ?></h6>
                                                    <span class="badge bg-<?php echo $this->getScoreClass($score); ?>">
                                                        <?php echo $score; ?>%
                                                    </span>
                                                </div>
                                                <p class="card-text small"><?php echo $req_name; ?></p>
                                                <div class="progress mb-2" style="height: 5px;">
                                                    <div class="progress-bar bg-<?php echo $this->getScoreClass($score); ?>" 
                                                         style="width: <?php echo $score; ?>%"></div>
                                                </div>
                                                <small class="text-muted">
                                                    Durum: 
                                                    <span class="status-<?php echo $this->getStatusClass($score); ?>">
                                                        <?php echo $this->getStatusText($score); ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compliance Trend Grafiği -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-area me-2"></i>
                                    Compliance Trend
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="complianceChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Rapor Detay Modal -->
    <div class="modal fade" id="reportDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compliance Rapor Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reportDetailContent">
                    <!-- Rapor detayları buraya yüklenecek -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Compliance trend grafiği
        const ctx = document.getElementById('complianceChart').getContext('2d');
        const complianceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['1 Ay Önce', '2 Hafta Önce', '1 Hafta Önce', '3 Gün Önce', '1 Gün Önce', 'Bugün'],
                datasets: [{
                    label: 'Compliance Skoru (%)',
                    data: [75, 78, 82, 85, 88, <?php echo $last_report ? $last_report['genel_skor'] : 0; ?>],
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

        function showReportDetails(reportId) {
            // AJAX ile rapor detaylarını getir
            fetch(`ajax/get-compliance-report-details.php?id=${reportId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('reportDetailContent').innerHTML = data.html;
                    new bootstrap.Modal(document.getElementById('reportDetailModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Rapor detayları yüklenirken hata oluştu.');
                });
        }

        function exportReport(reportId) {
            window.open(`ajax/export-compliance-report.php?id=${reportId}`, '_blank');
        }
    </script>
</body>
</html>

<?php
// Skor sınıfı belirleme fonksiyonu
function getScoreClass($score) {
    if ($score >= 90) return 'success';
    if ($score >= 80) return 'info';
    if ($score >= 60) return 'warning';
    return 'danger';
}

// Skor durumu belirleme fonksiyonu
function getScoreStatus($score) {
    if ($score >= 90) return 'Mükemmel';
    if ($score >= 80) return 'İyi';
    if ($score >= 60) return 'Orta';
    return 'Düşük';
}

// Durum sınıfı belirleme fonksiyonu
function getStatusClass($score) {
    if ($score >= 80) return 'compliant';
    if ($score >= 60) return 'partial';
    return 'non-compliant';
}

// Durum metni belirleme fonksiyonu
function getStatusText($score) {
    if ($score >= 80) return 'Uyumlu';
    if ($score >= 60) return 'Kısmen Uyumlu';
    return 'Uyumsuz';
}
?>
