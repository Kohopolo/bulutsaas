<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('kanal_analiz', 'Kanal analiz görüntüleme yetkiniz bulunmamaktadır.');

$page_title = "Kanal Analiz";

// Filtreleme parametreleri
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01', strtotime('-1 month')); // Geçen ayın başı
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-d'); // Bugün
$analiz_tipi = $_GET['analiz_tipi'] ?? 'genel'; // genel, karsilastirma, trend

// Kanal listesi
$kanallar = fetchAll("SELECT id, kanal_adi, kanal_kodu, kanal_tipi FROM kanallar WHERE aktif = 1 ORDER BY kanal_adi");

// Genel analiz verileri
$genel_analiz = [];

// Kanal bazlı performans karşılaştırması
$kanal_karsilastirma = fetchAll("
    SELECT 
        k.id,
        k.kanal_adi,
        k.kanal_kodu,
        k.kanal_tipi,
        k.komisyon_orani,
        COALESCE(SUM(kp.toplam_rezervasyon), 0) as toplam_rezervasyon,
        COALESCE(SUM(kp.toplam_gece), 0) as toplam_gece,
        COALESCE(SUM(kp.toplam_misafir), 0) as toplam_misafir,
        COALESCE(SUM(kp.brut_gelir), 0) as toplam_brut_gelir,
        COALESCE(SUM(kp.net_gelir), 0) as toplam_net_gelir,
        COALESCE(SUM(kp.komisyon_tutari), 0) as toplam_komisyon,
        COALESCE(SUM(kp.iptal_edilen_rezervasyon), 0) as toplam_iptal,
        COALESCE(AVG(kp.ortalama_gece_fiyati), 0) as ortalama_gece_fiyati,
        COALESCE(AVG(kp.doluluk_orani), 0) as ortalama_doluluk,
        COALESCE(AVG(kp.revpar), 0) as ortalama_revpar,
        COALESCE(AVG(kp.adr), 0) as ortalama_adr
    FROM kanallar k
    LEFT JOIN kanal_performans kp ON k.id = kp.kanal_id 
        AND kp.tarih BETWEEN ? AND ?
    WHERE k.aktif = 1
    GROUP BY k.id, k.kanal_adi, k.kanal_kodu, k.kanal_tipi, k.komisyon_orani
    ORDER BY toplam_brut_gelir DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// Eğer performans verisi yoksa, rezervasyonlardan hesapla
if (empty(array_filter($kanal_karsilastirma, function($k) { return $k['toplam_rezervasyon'] > 0; }))) {
    $kanal_karsilastirma = fetchAll("
        SELECT 
            k.id,
            k.kanal_adi,
            k.kanal_kodu,
            k.kanal_tipi,
            k.komisyon_orani,
            COUNT(r.id) as toplam_rezervasyon,
            SUM(DATEDIFF(r.cikis_tarihi, r.giris_tarihi)) as toplam_gece,
            SUM(r.yetiskin_sayisi + r.cocuk_sayisi) as toplam_misafir,
            SUM(r.toplam_tutar) as toplam_brut_gelir,
            SUM(r.toplam_tutar * (1 - k.komisyon_orani / 100)) as toplam_net_gelir,
            SUM(r.toplam_tutar * k.komisyon_orani / 100) as toplam_komisyon,
            SUM(CASE WHEN r.durum = 'iptal' THEN 1 ELSE 0 END) as toplam_iptal,
            AVG(r.toplam_tutar / DATEDIFF(r.cikis_tarihi, r.giris_tarihi)) as ortalama_gece_fiyati,
            0 as ortalama_doluluk,
            0 as ortalama_revpar,
            AVG(r.toplam_tutar / DATEDIFF(r.cikis_tarihi, r.giris_tarihi)) as ortalama_adr
        FROM kanallar k
        LEFT JOIN rezervasyonlar r ON k.id = r.kanal_id 
            AND r.giris_tarihi BETWEEN ? AND ?
        WHERE k.aktif = 1
        GROUP BY k.id, k.kanal_adi, k.kanal_kodu, k.kanal_tipi, k.komisyon_orani
        ORDER BY toplam_brut_gelir DESC
    ", [$baslangic_tarihi, $bitis_tarihi]);
}

// Toplam istatistikler
$toplam_istatistikler = [
    'toplam_rezervasyon' => array_sum(array_column($kanal_karsilastirma, 'toplam_rezervasyon')),
    'toplam_gece' => array_sum(array_column($kanal_karsilastirma, 'toplam_gece')),
    'toplam_misafir' => array_sum(array_column($kanal_karsilastirma, 'toplam_misafir')),
    'toplam_brut_gelir' => array_sum(array_column($kanal_karsilastirma, 'toplam_brut_gelir')),
    'toplam_net_gelir' => array_sum(array_column($kanal_karsilastirma, 'toplam_net_gelir')),
    'toplam_komisyon' => array_sum(array_column($kanal_karsilastirma, 'toplam_komisyon')),
    'toplam_iptal' => array_sum(array_column($kanal_karsilastirma, 'toplam_iptal'))
];

// Kanal tipi bazlı analiz
$kanal_tipi_analiz = [];
foreach ($kanal_karsilastirma as $kanal) {
    $tip = $kanal['kanal_tipi'];
    if (!isset($kanal_tipi_analiz[$tip])) {
        $kanal_tipi_analiz[$tip] = [
            'kanal_sayisi' => 0,
            'toplam_rezervasyon' => 0,
            'toplam_brut_gelir' => 0,
            'toplam_komisyon' => 0,
            'ortalama_komisyon_orani' => 0
        ];
    }
    
    $kanal_tipi_analiz[$tip]['kanal_sayisi']++;
    $kanal_tipi_analiz[$tip]['toplam_rezervasyon'] += $kanal['toplam_rezervasyon'];
    $kanal_tipi_analiz[$tip]['toplam_brut_gelir'] += $kanal['toplam_brut_gelir'];
    $kanal_tipi_analiz[$tip]['toplam_komisyon'] += $kanal['toplam_komisyon'];
    $kanal_tipi_analiz[$tip]['ortalama_komisyon_orani'] += $kanal['komisyon_orani'];
}

// Ortalama komisyon oranlarını hesapla
foreach ($kanal_tipi_analiz as $tip => &$veri) {
    $veri['ortalama_komisyon_orani'] = $veri['ortalama_komisyon_orani'] / $veri['kanal_sayisi'];
}

// Aylık trend analizi
$aylik_trend = fetchAll("
    SELECT 
        DATE_FORMAT(tarih, '%Y-%m') as ay,
        SUM(toplam_rezervasyon) as toplam_rezervasyon,
        SUM(brut_gelir) as toplam_brut_gelir,
        SUM(komisyon_tutari) as toplam_komisyon
    FROM kanal_performans 
    WHERE tarih BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(tarih, '%Y-%m')
    ORDER BY ay ASC
", [$baslangic_tarihi, $bitis_tarihi]);

// Eğer performans verisi yoksa, rezervasyonlardan hesapla
if (empty($aylik_trend)) {
    $aylik_trend = fetchAll("
        SELECT 
            DATE_FORMAT(giris_tarihi, '%Y-%m') as ay,
            COUNT(*) as toplam_rezervasyon,
            SUM(toplam_tutar) as toplam_brut_gelir,
            SUM(toplam_tutar * k.komisyon_orani / 100) as toplam_komisyon
        FROM rezervasyonlar r
        JOIN kanallar k ON r.kanal_id = k.id
        WHERE r.giris_tarihi BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(giris_tarihi, '%Y-%m')
        ORDER BY ay ASC
    ", [$baslangic_tarihi, $bitis_tarihi]);
}

// En iyi performans gösteren kanallar
$en_iyi_kanallar = array_slice($kanal_karsilastirma, 0, 5);

// En düşük performans gösteren kanallar
$en_dusuk_kanallar = array_slice(array_reverse($kanal_karsilastirma), 0, 5);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .desktop-container {
            padding: 20px;
            min-height: 100vh;
        }
        
        .desktop-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .filter-section {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .chart-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .table-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table td {
            border-color: rgba(0, 0, 0, 0.1);
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-action-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .metric-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .metric-positive {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .metric-negative {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .metric-neutral {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .metric-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .ranking-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .ranking-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: rgba(102, 126, 234, 0.05);
            border-left: 4px solid #667eea;
        }
        
        .ranking-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .ranking-info {
            flex: 1;
        }
        
        .ranking-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .ranking-details {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .ranking-value {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background: rgba(102, 126, 234, 0.2);
        }
        
        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .desktop-container {
                padding: 10px;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Header -->
        <div class="desktop-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="fas fa-analytics me-3" style="color: #667eea;"></i>
                        Kanal Analiz
                    </h1>
                    <p class="text-muted mb-0 mt-2">
                        <i class="fas fa-clock me-2"></i>
                        <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="quick-actions">
                        <a href="kanal-listesi.php" class="quick-action-btn">
                            <i class="fas fa-list"></i>
                            Kanal Listesi
                        </a>
                        <a href="kanal-performans.php" class="quick-action-btn">
                            <i class="fas fa-chart-line"></i>
                            Performans
                        </a>
                        <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left"></i> Geri
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt"></i> Çıkış
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtreleme -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Analiz Tipi</label>
                    <select name="analiz_tipi" class="form-select">
                        <option value="genel" <?= $analiz_tipi === 'genel' ? 'selected' : '' ?>>Genel Analiz</option>
                        <option value="karsilastirma" <?= $analiz_tipi === 'karsilastirma' ? 'selected' : '' ?>>Kanal Karşılaştırması</option>
                        <option value="trend" <?= $analiz_tipi === 'trend' ? 'selected' : '' ?>>Trend Analizi</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Başlangıç Tarihi</label>
                    <input type="date" name="baslangic_tarihi" class="form-control" value="<?= $baslangic_tarihi ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bitiş Tarihi</label>
                    <input type="date" name="bitis_tarihi" class="form-control" value="<?= $bitis_tarihi ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Analiz Et
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Genel İstatistikler -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?= number_format($toplam_istatistikler['toplam_rezervasyon']) ?></div>
                    <div class="stats-label">Toplam Rezervasyon</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stats-number"><?= number_format($toplam_istatistikler['toplam_brut_gelir'], 0) ?>₺</div>
                    <div class="stats-label">Toplam Brüt Gelir</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stats-number"><?= number_format($toplam_istatistikler['toplam_komisyon'], 0) ?>₺</div>
                    <div class="stats-label">Toplam Komisyon</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stats-number"><?= count($kanallar) ?></div>
                    <div class="stats-label">Aktif Kanal</div>
                </div>
            </div>
        </div>

        <!-- Grafikler -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>
                        Kanal Tipi Dağılımı
                    </h5>
                    <canvas id="kanalTipiChart" height="300"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar me-2"></i>
                        Gelir Dağılımı
                    </h5>
                    <canvas id="gelirDagilimChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Aylık Trend -->
        <?php if (!empty($aylik_trend)): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="chart-container">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-line me-2"></i>
                            Aylık Trend Analizi
                        </h5>
                        <canvas id="aylikTrendChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- En İyi ve En Düşük Performans -->
        <div class="row">
            <div class="col-md-6">
                <div class="ranking-card">
                    <h5 class="mb-3">
                        <i class="fas fa-trophy me-2"></i>
                        En İyi Performans (Top 5)
                    </h5>
                    <?php foreach ($en_iyi_kanallar as $index => $kanal): ?>
                        <div class="ranking-item">
                            <div class="ranking-number"><?= $index + 1 ?></div>
                            <div class="ranking-info">
                                <div class="ranking-name"><?= htmlspecialchars($kanal['kanal_adi']) ?></div>
                                <div class="ranking-details">
                                    <?= number_format($kanal['toplam_rezervasyon']) ?> rezervasyon • 
                                    %<?= number_format($kanal['komisyon_orani'], 1) ?> komisyon
                                </div>
                            </div>
                            <div class="ranking-value"><?= number_format($kanal['toplam_brut_gelir'], 0) ?>₺</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ranking-card">
                    <h5 class="mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        En Düşük Performans (Bottom 5)
                    </h5>
                    <?php foreach ($en_dusuk_kanallar as $index => $kanal): ?>
                        <div class="ranking-item">
                            <div class="ranking-number"><?= $index + 1 ?></div>
                            <div class="ranking-info">
                                <div class="ranking-name"><?= htmlspecialchars($kanal['kanal_adi']) ?></div>
                                <div class="ranking-details">
                                    <?= number_format($kanal['toplam_rezervasyon']) ?> rezervasyon • 
                                    %<?= number_format($kanal['komisyon_orani'], 1) ?> komisyon
                                </div>
                            </div>
                            <div class="ranking-value"><?= number_format($kanal['toplam_brut_gelir'], 0) ?>₺</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Detaylı Kanal Karşılaştırması -->
        <div class="table-container">
            <h5 class="mb-3">
                <i class="fas fa-table me-2"></i>
                Detaylı Kanal Karşılaştırması
            </h5>
            
            <?php if (empty($kanal_karsilastirma)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h4>Analiz Verisi Bulunamadı</h4>
                    <p>Seçilen tarih aralığında analiz verisi bulunamadı.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Kanal</th>
                                <th>Tip</th>
                                <th>Rezervasyon</th>
                                <th>Gece</th>
                                <th>Misafir</th>
                                <th>Brüt Gelir</th>
                                <th>Net Gelir</th>
                                <th>Komisyon</th>
                                <th>İptal</th>
                                <th>Komisyon %</th>
                                <th>ADR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kanal_karsilastirma as $kanal): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($kanal['kanal_adi']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($kanal['kanal_kodu']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $kanal['kanal_tipi'] === 'ota' ? 'danger' : ($kanal['kanal_tipi'] === 'direct' ? 'success' : 'warning') ?>">
                                            <?= strtoupper($kanal['kanal_tipi']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="metric-badge metric-positive">
                                            <i class="fas fa-calendar-check"></i>
                                            <?= number_format($kanal['toplam_rezervasyon']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($kanal['toplam_gece']) ?></td>
                                    <td><?= number_format($kanal['toplam_misafir']) ?></td>
                                    <td>
                                        <span class="metric-badge metric-positive">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <?= number_format($kanal['toplam_brut_gelir'], 0) ?>₺
                                        </span>
                                    </td>
                                    <td>
                                        <span class="metric-badge metric-positive">
                                            <i class="fas fa-coins"></i>
                                            <?= number_format($kanal['toplam_net_gelir'], 0) ?>₺
                                        </span>
                                    </td>
                                    <td>
                                        <span class="metric-badge metric-negative">
                                            <i class="fas fa-percentage"></i>
                                            <?= number_format($kanal['toplam_komisyon'], 0) ?>₺
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($kanal['toplam_iptal'] > 0): ?>
                                            <span class="metric-badge metric-negative">
                                                <i class="fas fa-times"></i>
                                                <?= number_format($kanal['toplam_iptal']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="metric-badge metric-neutral">
                                                <i class="fas fa-check"></i>
                                                0
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="metric-badge metric-warning">
                                            <i class="fas fa-percentage"></i>
                                            %<?= number_format($kanal['komisyon_orani'], 1) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($kanal['ortalama_adr'], 0) ?>₺</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Canlı saat güncelleme
        function updateLiveTime() {
            const now = new Date();
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timeString = `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`;
            const timeElement = document.querySelector('.current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        updateLiveTime();
        setInterval(updateLiveTime, 1000);

        // Grafik verileri
        const kanalTipiAnaliz = <?= json_encode($kanal_tipi_analiz) ?>;
        const kanalKarsilastirma = <?= json_encode($kanal_karsilastirma) ?>;
        const aylikTrend = <?= json_encode($aylik_trend) ?>;

        // Kanal tipi dağılımı grafiği
        const kanalTipiCtx = document.getElementById('kanalTipiChart').getContext('2d');
        new Chart(kanalTipiCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(kanalTipiAnaliz).map(tip => tip.toUpperCase()),
                datasets: [{
                    data: Object.values(kanalTipiAnaliz).map(v => v.kanal_sayisi),
                    backgroundColor: [
                        '#e74c3c',
                        '#27ae60',
                        '#f39c12',
                        '#9b59b6',
                        '#34495e',
                        '#95a5a6'
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

        // Gelir dağılımı grafiği
        const gelirDagilimCtx = document.getElementById('gelirDagilimChart').getContext('2d');
        new Chart(gelirDagilimCtx, {
            type: 'bar',
            data: {
                labels: kanalKarsilastirma.slice(0, 10).map(k => k.kanal_adi),
                datasets: [{
                    label: 'Brüt Gelir',
                    data: kanalKarsilastirma.slice(0, 10).map(k => k.toplam_brut_gelir),
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: '#28a745',
                    borderWidth: 1
                }, {
                    label: 'Komisyon',
                    data: kanalKarsilastirma.slice(0, 10).map(k => k.toplam_komisyon),
                    backgroundColor: 'rgba(220, 53, 69, 0.8)',
                    borderColor: '#dc3545',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('tr-TR') + '₺';
                            }
                        }
                    }
                }
            }
        });

        // Aylık trend grafiği
        <?php if (!empty($aylik_trend)): ?>
        const aylikTrendCtx = document.getElementById('aylikTrendChart').getContext('2d');
        new Chart(aylikTrendCtx, {
            type: 'line',
            data: {
                labels: aylikTrend.map(t => t.ay),
                datasets: [{
                    label: 'Rezervasyon Sayısı',
                    data: aylikTrend.map(t => t.toplam_rezervasyon),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: 'Brüt Gelir',
                    data: aylikTrend.map(t => t.toplam_brut_gelir),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('tr-TR') + '₺';
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
