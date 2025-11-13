<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-guvenlik-monitoring.php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/payment/PaymentProcessor.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_guvenlik_monitoring', 'Ödeme güvenlik monitoring yetkiniz bulunmamaktadır.');

$page_title = 'Güvenlik Monitoring';
$active_menu = 'odeme_yonetimi';

// Payment processor'ı başlat
$payment_processor = new PaymentProcessor($pdo);

// Zaman aralığı
$days = $_GET['days'] ?? 7;
$days = max(1, min(30, (int)$days)); // 1-30 gün arası

// Güvenlik istatistikleri
$security_stats = $payment_processor->getSecurityStats($days);

// Günlük güvenlik istatistikleri
$daily_stats = fetchAll("
    SELECT 
        DATE(islem_tarihi) as tarih,
        COUNT(*) as toplam_kontrol,
        SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as bloklanan_islem,
        SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as kritik_risk,
        SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as yuksek_risk,
        SUM(CASE WHEN risk_level = 'medium' THEN 1 ELSE 0 END) as orta_risk,
        AVG(risk_score) as ortalama_risk_puani,
        COUNT(DISTINCT ip_address) as benzersiz_ip_sayisi
    FROM odeme_guvenlik_loglari 
    WHERE islem_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE(islem_tarihi)
    ORDER BY tarih DESC
", [$days]);

// Risk seviyesi dağılımı
$risk_distribution = fetchAll("
    SELECT 
        risk_level,
        COUNT(*) as sayi,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM odeme_guvenlik_loglari WHERE islem_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)), 2) as yuzde
    FROM odeme_guvenlik_loglari 
    WHERE islem_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY risk_level
    ORDER BY 
        CASE risk_level 
            WHEN 'critical' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            WHEN 'minimal' THEN 5 
        END
", [$days, $days]);

// En çok bloklanan IP'ler
$top_blocked_ips = fetchAll("
    SELECT 
        ip_address,
        COUNT(*) as blok_sayisi,
        MAX(islem_tarihi) as son_blok_tarihi,
        AVG(risk_score) as ortalama_risk
    FROM odeme_guvenlik_loglari 
    WHERE blocked = 1 AND islem_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY ip_address
    ORDER BY blok_sayisi DESC
    LIMIT 10
", [$days]);

// En çok kullanılan risk faktörleri
$top_risk_factors = fetchAll("
    SELECT 
        risk_factors,
        COUNT(*) as kullanım_sayisi
    FROM odeme_guvenlik_loglari 
    WHERE islem_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY) AND risk_factors IS NOT NULL
    GROUP BY risk_factors
    ORDER BY kullanım_sayisi DESC
    LIMIT 10
", [$days]);

// SSL durumu
$ssl_stats = fetchOne("
    SELECT 
        COUNT(*) as toplam_kontrol,
        SUM(CASE WHEN ssl_valid = 1 THEN 1 ELSE 0 END) as gecerli_ssl,
        SUM(CASE WHEN ssl_valid = 0 THEN 1 ELSE 0 END) as gecersiz_ssl
    FROM odeme_ssl_loglari 
    WHERE kontrol_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)
", [$days]);

// Son güvenlik ihlalleri
$recent_violations = fetchAll("
    SELECT 
        ihlal_tipi,
        ip_address,
        risk_score,
        ihlal_tarihi,
        cozum_durumu
    FROM odeme_guvenlik_ihlalleri 
    WHERE ihlal_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ORDER BY ihlal_tarihi DESC
    LIMIT 20
", [$days]);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="index.php">Ana Sayfa</a></li>
                        <li class="breadcrumb-item"><a href="odeme-yonetimi.php">Ödeme Yönetimi</a></li>
                        <li class="breadcrumb-item"><a href="odeme-guvenlik-yonetimi.php">Güvenlik Yönetimi</a></li>
                        <li class="breadcrumb-item active">Monitoring</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="fas fa-chart-line me-2"></i>Güvenlik Monitoring
                </h4>
            </div>
        </div>
    </div>

    <!-- Zaman Aralığı Seçici -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="d-flex align-items-center">
                        <label for="days" class="form-label me-2 mb-0">Zaman Aralığı:</label>
                        <select name="days" id="days" class="form-select me-2" style="width: auto;" onchange="this.form.submit()">
                            <option value="1" <?= $days == 1 ? 'selected' : '' ?>>Son 1 Gün</option>
                            <option value="7" <?= $days == 7 ? 'selected' : '' ?>>Son 7 Gün</option>
                            <option value="14" <?= $days == 14 ? 'selected' : '' ?>>Son 14 Gün</option>
                            <option value="30" <?= $days == 30 ? 'selected' : '' ?>>Son 30 Gün</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Genel İstatistikler -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-shield-alt text-primary" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($security_stats['toplam_kontrol'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Toplam Kontrol</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <i class="fas fa-ban text-danger" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($security_stats['bloklanan_islem'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Bloklanan</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($security_stats['kritik_risk'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Kritik Risk</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-info">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line text-info" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($security_stats['ortalama_risk_puani'] ?? 0, 1) ?></h5>
                    <p class="text-muted mb-0">Ort. Risk</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-success">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($ssl_stats['gecerli_ssl'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Geçerli SSL</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-secondary">
                <div class="card-body text-center">
                    <i class="fas fa-globe text-secondary" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($security_stats['benzersiz_ip_sayisi'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Benzersiz IP</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Günlük İstatistikler Grafiği -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Günlük Güvenlik İstatistikleri</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyStatsChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Risk Seviyesi Dağılımı -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-pie-chart me-2"></i>Risk Seviyesi Dağılımı</h5>
                </div>
                <div class="card-body">
                    <canvas id="riskDistributionChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- En Çok Bloklanan IP'ler -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-ban me-2"></i>En Çok Bloklanan IP'ler</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>IP Adresi</th>
                                    <th>Blok Sayısı</th>
                                    <th>Ort. Risk</th>
                                    <th>Son Blok</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_blocked_ips as $ip): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($ip['ip_address']) ?></code></td>
                                        <td><span class="badge bg-danger"><?= $ip['blok_sayisi'] ?></span></td>
                                        <td><?= number_format($ip['ortalama_risk'], 1) ?></td>
                                        <td><?= date('d.m H:i', strtotime($ip['son_blok_tarihi'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- En Çok Kullanılan Risk Faktörleri -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>En Çok Kullanılan Risk Faktörleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Risk Faktörü</th>
                                    <th>Kullanım Sayısı</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_risk_factors as $factor): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($factor['risk_factors']) ?></td>
                                        <td><span class="badge bg-warning"><?= $factor['kullanım_sayisi'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Son Güvenlik İhlalleri -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Son Güvenlik İhlalleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>İhlal Tipi</th>
                                    <th>IP Adresi</th>
                                    <th>Risk Puanı</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_violations as $violation): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-danger"><?= htmlspecialchars($violation['ihlal_tipi']) ?></span>
                                        </td>
                                        <td><code><?= htmlspecialchars($violation['ip_address']) ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $violation['risk_score'] >= 80 ? 'danger' : 'warning' ?>">
                                                <?= $violation['risk_score'] ?>
                                            </span>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($violation['ihlal_tarihi'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $violation['cozum_durumu'] === 'cozuldu' ? 'success' : ($violation['cozum_durumu'] === 'beklemede' ? 'warning' : 'danger') ?>">
                                                <?= ucfirst($violation['cozum_durumu']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Günlük istatistikler grafiği
const dailyStatsCtx = document.getElementById('dailyStatsChart').getContext('2d');
const dailyStatsData = <?= json_encode($daily_stats) ?>;

new Chart(dailyStatsCtx, {
    type: 'line',
    data: {
        labels: dailyStatsData.map(item => item.tarih).reverse(),
        datasets: [
            {
                label: 'Toplam Kontrol',
                data: dailyStatsData.map(item => item.toplam_kontrol).reverse(),
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.1
            },
            {
                label: 'Bloklanan İşlem',
                data: dailyStatsData.map(item => item.bloklanan_islem).reverse(),
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.1
            },
            {
                label: 'Kritik Risk',
                data: dailyStatsData.map(item => item.kritik_risk).reverse(),
                borderColor: 'rgb(255, 159, 64)',
                backgroundColor: 'rgba(255, 159, 64, 0.1)',
                tension: 0.1
            }
        ]
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

// Risk seviyesi dağılımı grafiği
const riskDistributionCtx = document.getElementById('riskDistributionChart').getContext('2d');
const riskDistributionData = <?= json_encode($risk_distribution) ?>;

new Chart(riskDistributionCtx, {
    type: 'doughnut',
    data: {
        labels: riskDistributionData.map(item => item.risk_level),
        datasets: [{
            data: riskDistributionData.map(item => item.sayi),
            backgroundColor: [
                '#dc3545', // critical - kırmızı
                '#fd7e14', // high - turuncu
                '#ffc107', // medium - sarı
                '#20c997', // low - yeşil
                '#6c757d'  // minimal - gri
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
