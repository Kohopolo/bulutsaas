<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_raporlar', 'Ödeme raporlarını görüntüleme yetkiniz bulunmamaktadır.');

$page_title = "Ödeme Raporları";
$current_page = "odeme-raporlari";

// Filtreleme parametreleri
$provider_id = $_GET['provider_id'] ?? '';
$tarih_baslangic = $_GET['tarih_baslangic'] ?? date('Y-m-01'); // Bu ayın başı
$tarih_bitis = $_GET['tarih_bitis'] ?? date('Y-m-d'); // Bugün
$rapor_tipi = $_GET['rapor_tipi'] ?? 'genel';

// Ödeme sağlayıcıları listesi
$providerlar = fetchAll("SELECT id, provider_adi FROM odeme_providerlari WHERE aktif = 1 ORDER BY provider_adi");

// Filtreleme koşulları
$where_conditions = ["DATE(oi.islem_tarihi) BETWEEN ? AND ?"];
$params = [$tarih_baslangic, $tarih_bitis];

if (!empty($provider_id)) {
    $where_conditions[] = "oi.provider_id = ?";
    $params[] = $provider_id;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Genel İstatistikler
$genel_istatistikler = fetchOne("
    SELECT 
        COUNT(*) as toplam_islem,
        COUNT(CASE WHEN oi.durum = 'basarili' THEN 1 END) as basarili_islem,
        COUNT(CASE WHEN oi.durum = 'basarisiz' THEN 1 END) as basarisiz_islem,
        COUNT(CASE WHEN oi.durum = 'beklemede' THEN 1 END) as beklemede_islem,
        COUNT(CASE WHEN oi.durum = 'iptal' THEN 1 END) as iptal_islem,
        COUNT(CASE WHEN oi.durum = 'iade' THEN 1 END) as iade_islem,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE 0 END) as toplam_tutar,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.komisyon_tutari ELSE 0 END) as toplam_komisyon,
        AVG(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE NULL END) as ortalama_tutar,
        MIN(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE NULL END) as minimum_tutar,
        MAX(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE NULL END) as maksimum_tutar
    FROM odeme_islemleri oi
    $where_clause
", $params);

// Sağlayıcı Bazlı İstatistikler
$provider_istatistikleri = fetchAll("
    SELECT 
        p.provider_adi,
        p.komisyon_orani,
        COUNT(oi.id) as toplam_islem,
        COUNT(CASE WHEN oi.durum = 'basarili' THEN 1 END) as basarili_islem,
        COUNT(CASE WHEN oi.durum = 'basarisiz' THEN 1 END) as basarisiz_islem,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE 0 END) as toplam_tutar,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.komisyon_tutari ELSE 0 END) as toplam_komisyon,
        AVG(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE NULL END) as ortalama_tutar
    FROM odeme_providerlari p
    LEFT JOIN odeme_islemleri oi ON p.id = oi.provider_id $where_clause
    GROUP BY p.id, p.provider_adi, p.komisyon_orani
    HAVING toplam_islem > 0
    ORDER BY toplam_tutar DESC
", $params);

// Günlük İstatistikler (Son 30 gün)
$gunluk_istatistikler = fetchAll("
    SELECT 
        DATE(oi.islem_tarihi) as tarih,
        COUNT(*) as toplam_islem,
        COUNT(CASE WHEN oi.durum = 'basarili' THEN 1 END) as basarili_islem,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE 0 END) as toplam_tutar,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.komisyon_tutari ELSE 0 END) as toplam_komisyon
    FROM odeme_islemleri oi
    WHERE DATE(oi.islem_tarihi) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(oi.islem_tarihi)
    ORDER BY tarih DESC
    LIMIT 30
");

// Taksit Bazlı İstatistikler
$taksit_istatistikleri = fetchAll("
    SELECT 
        oi.taksit_sayisi,
        COUNT(*) as toplam_islem,
        COUNT(CASE WHEN oi.durum = 'basarili' THEN 1 END) as basarili_islem,
        SUM(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE 0 END) as toplam_tutar,
        AVG(CASE WHEN oi.durum = 'basarili' THEN oi.tutar ELSE NULL END) as ortalama_tutar
    FROM odeme_islemleri oi
    $where_clause
    GROUP BY oi.taksit_sayisi
    ORDER BY oi.taksit_sayisi
", $params);

// Başarı oranı hesapla
$basarili_oran = $genel_istatistikler['toplam_islem'] > 0 ? 
    ($genel_istatistikler['basarili_islem'] / $genel_istatistikler['toplam_islem']) * 100 : 0;

include 'header.php';
?>

<div class="desktop-container">
    <div class="desktop-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="page-title">
                    <i class="fas fa-chart-bar me-2"></i>
                    Ödeme Raporları
                </h1>
                <p class="page-subtitle mb-0">Ödeme işlemleri analiz ve raporları</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="header-actions">
                    <button class="btn btn-outline-success" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> PDF'e Aktar
                    </button>
                    <button class="btn btn-outline-info" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel'e Aktar
                    </button>
                    <a href="odeme-islemleri.php" class="btn btn-outline-primary">
                        <i class="fas fa-list"></i> İşlemler
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="form-container">
        <!-- Filtreleme -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Rapor Filtreleri
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Sağlayıcı</label>
                        <select name="provider_id" class="form-select">
                            <option value="">Tüm Sağlayıcılar</option>
                            <?php foreach ($providerlar as $provider): ?>
                            <option value="<?= $provider['id'] ?>" <?= $provider_id == $provider['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($provider['provider_adi']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" name="tarih_baslangic" class="form-control" value="<?= htmlspecialchars($tarih_baslangic) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" name="tarih_bitis" class="form-control" value="<?= htmlspecialchars($tarih_bitis) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rapor Tipi</label>
                        <select name="rapor_tipi" class="form-select">
                            <option value="genel" <?= $rapor_tipi === 'genel' ? 'selected' : '' ?>>Genel</option>
                            <option value="detayli" <?= $rapor_tipi === 'detayli' ? 'selected' : '' ?>>Detaylı</option>
                            <option value="komisyon" <?= $rapor_tipi === 'komisyon' ? 'selected' : '' ?>>Komisyon</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Raporu Güncelle
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Genel İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($genel_istatistikler['toplam_islem'] ?? 0) ?></div>
                        <div class="stats-label">Toplam İşlem</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($genel_istatistikler['basarili_islem'] ?? 0) ?></div>
                        <div class="stats-label">Başarılı İşlem</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-lira-sign"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($genel_istatistikler['toplam_tutar'] ?? 0, 0) ?>₺</div>
                        <div class="stats-label">Toplam Tutar</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($basarili_oran, 1) ?>%</div>
                        <div class="stats-label">Başarı Oranı</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detaylı İstatistikler -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-pie me-2"></i>İşlem Durumları
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="durumChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>Günlük Trend
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Özet Bilgiler
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-label">Ortalama Tutar</div>
                                    <div class="metric-value"><?= number_format($genel_istatistikler['ortalama_tutar'] ?? 0, 2) ?>₺</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-label">Minimum Tutar</div>
                                    <div class="metric-value"><?= number_format($genel_istatistikler['minimum_tutar'] ?? 0, 2) ?>₺</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-label">Maksimum Tutar</div>
                                    <div class="metric-value"><?= number_format($genel_istatistikler['maksimum_tutar'] ?? 0, 2) ?>₺</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div class="metric-label">Toplam Komisyon</div>
                                    <div class="metric-value"><?= number_format($genel_istatistikler['toplam_komisyon'] ?? 0, 2) ?>₺</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sağlayıcı Bazlı Raporlar -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-building me-2"></i>Sağlayıcı Bazlı Performans
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Sağlayıcı</th>
                                <th>Toplam İşlem</th>
                                <th>Başarılı İşlem</th>
                                <th>Başarı Oranı</th>
                                <th>Toplam Tutar</th>
                                <th>Ortalama Tutar</th>
                                <th>Toplam Komisyon</th>
                                <th>Komisyon Oranı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($provider_istatistikleri as $provider): ?>
                            <?php 
                            $provider_basarili_oran = $provider['toplam_islem'] > 0 ? 
                                ($provider['basarili_islem'] / $provider['toplam_islem']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($provider['provider_adi']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= number_format($provider['toplam_islem']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?= number_format($provider['basarili_islem']) ?></span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?= $provider_basarili_oran >= 90 ? 'bg-success' : ($provider_basarili_oran >= 70 ? 'bg-warning' : 'bg-danger') ?>" 
                                             style="width: <?= $provider_basarili_oran ?>%">
                                            <?= number_format($provider_basarili_oran, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= number_format($provider['toplam_tutar'], 0) ?>₺</strong>
                                </td>
                                <td>
                                    <?= number_format($provider['ortalama_tutar'] ?? 0, 2) ?>₺
                                </td>
                                <td>
                                    <span class="text-warning"><?= number_format($provider['toplam_komisyon'], 2) ?>₺</span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= number_format($provider['komisyon_orani'], 2) ?>%</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Taksit Bazlı Raporlar -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-credit-card me-2"></i>Taksit Bazlı Analiz
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Taksit Sayısı</th>
                                <th>Toplam İşlem</th>
                                <th>Başarılı İşlem</th>
                                <th>Başarı Oranı</th>
                                <th>Toplam Tutar</th>
                                <th>Ortalama Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taksit_istatistikleri as $taksit): ?>
                            <?php 
                            $taksit_basarili_oran = $taksit['toplam_islem'] > 0 ? 
                                ($taksit['basarili_islem'] / $taksit['toplam_islem']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <?php if ($taksit['taksit_sayisi'] == 1): ?>
                                        <span class="badge bg-secondary">Peşin</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><?= $taksit['taksit_sayisi'] ?> Taksit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= number_format($taksit['toplam_islem']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?= number_format($taksit['basarili_islem']) ?></span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?= $taksit_basarili_oran >= 90 ? 'bg-success' : ($taksit_basarili_oran >= 70 ? 'bg-warning' : 'bg-danger') ?>" 
                                             style="width: <?= $taksit_basarili_oran ?>%">
                                            <?= number_format($taksit_basarili_oran, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= number_format($taksit['toplam_tutar'], 0) ?>₺</strong>
                                </td>
                                <td>
                                    <?= number_format($taksit['ortalama_tutar'] ?? 0, 2) ?>₺
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// İşlem Durumları Grafiği
const durumCtx = document.getElementById('durumChart').getContext('2d');
const durumChart = new Chart(durumCtx, {
    type: 'doughnut',
    data: {
        labels: ['Başarılı', 'Başarısız', 'Beklemede', 'İptal', 'İade'],
        datasets: [{
            data: [
                <?= $genel_istatistikler['basarili_islem'] ?? 0 ?>,
                <?= $genel_istatistikler['basarisiz_islem'] ?? 0 ?>,
                <?= $genel_istatistikler['beklemede_islem'] ?? 0 ?>,
                <?= $genel_istatistikler['iptal_islem'] ?? 0 ?>,
                <?= $genel_istatistikler['iade_islem'] ?? 0 ?>
            ],
            backgroundColor: [
                '#28a745',
                '#dc3545',
                '#ffc107',
                '#6c757d',
                '#343a40'
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

// Günlük Trend Grafiği
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach (array_reverse($gunluk_istatistikler) as $gun): ?>
            '<?= date('d.m', strtotime($gun['tarih'])) ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Toplam Tutar (₺)',
            data: [
                <?php foreach (array_reverse($gunluk_istatistikler) as $gun): ?>
                <?= $gun['toplam_tutar'] ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4
        }, {
            label: 'İşlem Sayısı',
            data: [
                <?php foreach (array_reverse($gunluk_istatistikler) as $gun): ?>
                <?= $gun['toplam_islem'] ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});

function exportToPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'pdf');
    window.open('ajax/export-payment-reports.php?' + params.toString(), '_blank');
}

function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.open('ajax/export-payment-reports.php?' + params.toString(), '_blank');
}
</script>

<style>
.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 20px;
    color: white;
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stats-icon {
    font-size: 2.5rem;
    margin-right: 15px;
    opacity: 0.8;
}

.stats-number {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.stats-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 5px;
}

.metric-item {
    text-align: center;
    padding: 10px;
    border-radius: 8px;
    background: #f8f9fa;
    margin-bottom: 10px;
}

.metric-label {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.metric-value {
    font-size: 1.1rem;
    font-weight: bold;
    color: #495057;
}

.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    border-radius: 10px 10px 0 0 !important;
}

.table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: bold;
}
</style>

<?php include 'footer.php'; ?>
