<?php
require_once '../includes/config.php';
require_once '../includes/session_security.php';
require_once '../includes/sales_functions.php';

// Sales kullanıcısı kontrolü
checkSalesSession();

// Dashboard verilerini getir
$dashboardData = getSalesDashboardData($_SESSION['user_id']);
$permissions = getSalesPermissions($_SESSION['user_id']);
$performance = getSalesPerformance($_SESSION['user_id']);

// Bu ay ve geçen ay karşılaştırması
$thisMonth = date('n');
$thisYear = date('Y');
$lastMonth = $thisMonth == 1 ? 12 : $thisMonth - 1;
$lastMonthYear = $thisMonth == 1 ? $thisYear - 1 : $thisYear;

$lastMonthPerformance = getSalesPerformance($_SESSION['user_id'], $lastMonth, $lastMonthYear);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satış Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stats-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stats-card.info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .performance-chart {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sales-sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                </button>
                
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="sales-profil.php"><i class="fas fa-user-edit me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Siteyi Görüntüle</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid">
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 mb-0">Satış Dashboard</h1>
                    <p class="text-muted">Performansınızı takip edin ve rezervasyonlarınızı yönetin</p>
                </div>
            </div>

            <!-- Performance Charts -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="performance-chart">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-area me-2"></i>Aylık Performans Trendi
                        </h5>
                        <canvas id="performanceChart" height="100"></canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="performance-chart">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-pie me-2"></i>Rezervasyon Durumları
                        </h5>
                        <canvas id="statusChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Performance Stats -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>Performans Metrikleri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center p-3 border-end">
                                        <h4 class="text-primary"><?php echo $performance['ortalama_rezervasyon_tutari'] ? '₺' . number_format($performance['ortalama_rezervasyon_tutari'], 0, ',', '.') : '₺0'; ?></h4>
                                        <small class="text-muted">Ortalama Rezervasyon</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3">
                                        <h4 class="text-success"><?php echo $performance['basari_orani'] ? round($performance['basari_orani'], 1) . '%' : '0%'; ?></h4>
                                        <small class="text-muted">Başarı Oranı</small>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center p-3 border-end">
                                        <h4 class="text-info"><?php echo $dashboardData['bekleyen_rezervasyon'] ?? 0; ?></h4>
                                        <small class="text-muted">Bekleyen</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3">
                                        <h4 class="text-warning"><?php echo $dashboardData['iptal_rezervasyon'] ?? 0; ?></h4>
                                        <small class="text-muted">İptal Edilen</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Bu Ay Hedefler
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $monthlyTarget = 10; // Aylık hedef rezervasyon sayısı
                            $revenueTarget = 50000; // Aylık hedef ciro
                            $reservationProgress = ($dashboardData['bu_ay_rezervasyon'] / $monthlyTarget) * 100;
                            $revenueProgress = ($dashboardData['bu_ay_tutar'] / $revenueTarget) * 100;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Rezervasyon Hedefi</small>
                                    <small><?php echo $dashboardData['bu_ay_rezervasyon']; ?>/<?php echo $monthlyTarget; ?></small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: <?php echo min(100, $reservationProgress); ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Ciro Hedefi</small>
                                    <small>₺<?php echo number_format($dashboardData['bu_ay_tutar'], 0, ',', '.'); ?>/₺<?php echo number_format($revenueTarget, 0, ',', '.'); ?></small>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?php echo min(100, $revenueProgress); ?>%"></div>
                                </div>
                            </div>
                            <?php if ($reservationProgress >= 100 || $revenueProgress >= 100): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-trophy me-2"></i>Tebrikler! Hedeflerinizi aştınız!
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Bu Ay Rezervasyon</h6>
                                <h3 class="mb-0"><?php echo $dashboardData['bu_ay_rezervasyon'] ?? 0; ?></h3>
                                <?php if ($lastMonthPerformance['toplam_rezervasyon'] > 0): ?>
                                <?php 
                                $change = (($dashboardData['bu_ay_rezervasyon'] - $lastMonthPerformance['toplam_rezervasyon']) / $lastMonthPerformance['toplam_rezervasyon']) * 100;
                                ?>
                                <small class="<?php echo $change >= 0 ? 'text-success' : 'text-warning'; ?>">
                                    <i class="fas fa-<?php echo $change >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                    %<?php echo abs(round($change, 1)); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Bu Ay Ciro</h6>
                                <h3 class="mb-0">₺<?php echo number_format($dashboardData['bu_ay_tutar'] ?? 0, 0, ',', '.'); ?></h3>
                                <?php if ($lastMonthPerformance['toplam_tutar'] > 0): ?>
                                <?php 
                                $change = (($dashboardData['bu_ay_tutar'] - $lastMonthPerformance['toplam_tutar']) / $lastMonthPerformance['toplam_tutar']) * 100;
                                ?>
                                <small class="<?php echo $change >= 0 ? 'text-success' : 'text-warning'; ?>">
                                    <i class="fas fa-<?php echo $change >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                    %<?php echo abs(round($change, 1)); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <i class="fas fa-lira-sign fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Toplam Rezervasyon</h6>
                                <h3 class="mb-0"><?php echo $dashboardData['toplam_rezervasyon'] ?? 0; ?></h3>
                                <small>Tüm zamanlar</small>
                            </div>
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stats-card info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Komisyon</h6>
                                <h3 class="mb-0">₺<?php echo number_format($performance['komisyon_tutari'] ?? 0, 0, ',', '.'); ?></h3>
                                <small>Bu ay (%<?php echo $performance['komisyon_orani'] ?? 5; ?>)</small>
                            </div>
                            <i class="fas fa-percentage fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reservations -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Son Rezervasyonlar</h6>
                            <a href="sales-rezervasyonlar.php" class="btn btn-sm btn-primary">Tümünü Gör</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($dashboardData['son_rezervasyonlar'])): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
                                <p>Henüz rezervasyon bulunmuyor.</p>
                                <a href="sales-rezervasyon-ekle.php" class="btn btn-primary">İlk Rezervasyonu Ekle</a>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Müşteri</th>
                                            <th>Giriş-Çıkış</th>
                                            <th>Tutar</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dashboardData['son_rezervasyonlar'] as $rezervasyon): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($rezervasyon['musteri_ad'] . ' ' . $rezervasyon['musteri_soyad']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($rezervasyon['telefon']); ?></small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])); ?> - 
                                                    <?php echo date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong>₺<?php echo number_format($rezervasyon['toplam_tutar'], 0, ',', '.'); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($rezervasyon['durum'] == 'onaylandi'): ?>
                                                <span class="badge bg-success">Onaylandı</span>
                                                <?php elseif ($rezervasyon['durum'] == 'beklemede'): ?>
                                                <span class="badge bg-warning">Beklemede</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($rezervasyon['durum']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Quick Actions -->
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Hızlı İşlemler</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if (checkSalesPermission('rezervasyon_ekle')): ?>
                                <a href="sales-rezervasyon-ekle.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Yeni Rezervasyon
                                </a>
                                <?php endif; ?>
                                
                                <?php if (checkSalesPermission('musteri_ekle')): ?>
                                <a href="sales-musteri-ekle.php" class="btn btn-success">
                                    <i class="fas fa-user-plus me-2"></i>Yeni Müşteri
                                </a>
                                <?php endif; ?>
                                
                                <?php if (checkSalesPermission('rezervasyon_goruntule')): ?>
                                <a href="sales-rezervasyonlar.php" class="btn btn-info">
                                    <i class="fas fa-list me-2"></i>Rezervasyon Listesi
                                </a>
                                <?php endif; ?>
                                
                                <a href="sales-performans.php" class="btn btn-warning">
                                    <i class="fas fa-chart-bar me-2"></i>Performans Raporu
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance Summary -->
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Performans Özeti</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Bu Ay Hedef</span>
                                    <strong>10 Rezervasyon</strong>
                                </div>
                                <div class="progress mt-1">
                                    <?php $progress = min(100, (($dashboardData['bu_ay_rezervasyon'] ?? 0) / 10) * 100); ?>
                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <small class="text-muted">%<?php echo round($progress); ?> tamamlandı</small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Ciro Hedefi</span>
                                    <strong>₺50.000</strong>
                                </div>
                                <div class="progress mt-1">
                                    <?php $progress = min(100, (($dashboardData['bu_ay_tutar'] ?? 0) / 50000) * 100); ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <small class="text-muted">%<?php echo round($progress); ?> tamamlandı</small>
                            </div>
                            
                            <hr>
                            <div class="text-center">
                                <h5 class="text-primary">₺<?php echo number_format($performance['komisyon_tutari'] ?? 0, 0, ',', '.'); ?></h5>
                                <small class="text-muted">Bu ay kazancınız</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran'],
                datasets: [{
                    label: 'Rezervasyon Sayısı',
                    data: [<?php 
                        // Son 6 ayın verilerini getir
                        $monthlyData = [];
                        for ($i = 5; $i >= 0; $i--) {
                            $month = date('n', strtotime("-$i months"));
                            $year = date('Y', strtotime("-$i months"));
                            $monthPerf = getSalesPerformance($_SESSION['user_id'], $month, $year);
                            $monthlyData[] = $monthPerf['toplam_rezervasyon'] ?? 0;
                        }
                        echo implode(',', $monthlyData);
                    ?>],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Ciro (₺)',
                    data: [<?php 
                        $revenueData = [];
                        for ($i = 5; $i >= 0; $i--) {
                            $month = date('n', strtotime("-$i months"));
                            $year = date('Y', strtotime("-$i months"));
                            $monthPerf = getSalesPerformance($_SESSION['user_id'], $month, $year);
                            $revenueData[] = ($monthPerf['toplam_tutar'] ?? 0) / 1000; // Binlik olarak göster
                        }
                        echo implode(',', $revenueData);
                    ?>],
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Aylar'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Rezervasyon Sayısı'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Ciro (₺ x1000)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Onaylandı', 'Beklemede', 'İptal', 'Check-in', 'Check-out'],
                datasets: [{
                    data: [
                        <?php echo $dashboardData['onaylanan_rezervasyon'] ?? 0; ?>,
                        <?php echo $dashboardData['bekleyen_rezervasyon'] ?? 0; ?>,
                        <?php echo $dashboardData['iptal_rezervasyon'] ?? 0; ?>,
                        <?php echo $dashboardData['checkin_rezervasyon'] ?? 0; ?>,
                        <?php echo $dashboardData['checkout_rezervasyon'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
                        '#17a2b8',
                        '#6c757d'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
</body>
</html>