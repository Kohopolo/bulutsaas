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
requireDetailedPermission('kanal_performans', 'Kanal performans görüntüleme yetkiniz bulunmamaktadır.');

$page_title = "Kanal Performans";

// Filtreleme parametreleri
$kanal_id = intval($_GET['kanal_id'] ?? 0);
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01'); // Bu ayın başı
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-d'); // Bugün
$periyot = $_GET['periyot'] ?? 'daily'; // daily, weekly, monthly

// Kanal listesi
$kanallar = fetchAll("SELECT id, kanal_adi, kanal_kodu FROM kanallar WHERE aktif = 1 ORDER BY kanal_adi");

// Seçili kanal bilgisi
$secilen_kanal = null;
if ($kanal_id) {
    $secilen_kanal = fetchOne("SELECT * FROM kanallar WHERE id = ?", [$kanal_id]);
}

// Performans verilerini getir
$performans_verileri = [];
$toplam_istatistikler = [];

if ($kanal_id && $secilen_kanal) {
    // Kanal bazlı performans verileri
    $performans_verileri = fetchAll("
        SELECT 
            kp.*,
            DATE(kp.tarih) as tarih_gun
        FROM kanal_performans kp
        WHERE kp.kanal_id = ? 
        AND kp.tarih BETWEEN ? AND ?
        ORDER BY kp.tarih ASC
    ", [$kanal_id, $baslangic_tarihi, $bitis_tarihi]);

    // Toplam istatistikler
    $toplam_istatistikler = fetchOne("
        SELECT 
            SUM(toplam_rezervasyon) as toplam_rezervasyon,
            SUM(toplam_gece) as toplam_gece,
            SUM(toplam_misafir) as toplam_misafir,
            SUM(brut_gelir) as toplam_brut_gelir,
            SUM(net_gelir) as toplam_net_gelir,
            SUM(komisyon_tutari) as toplam_komisyon,
            SUM(iptal_edilen_rezervasyon) as toplam_iptal,
            AVG(ortalama_gece_fiyati) as ortalama_gece_fiyati,
            AVG(doluluk_orani) as ortalama_doluluk,
            AVG(revpar) as ortalama_revpar,
            AVG(adr) as ortalama_adr
        FROM kanal_performans 
        WHERE kanal_id = ? 
        AND tarih BETWEEN ? AND ?
    ", [$kanal_id, $baslangic_tarihi, $bitis_tarihi]);

    // Eğer performans verisi yoksa, rezervasyonlardan hesapla
    if (empty($performans_verileri)) {
        $rezervasyon_verileri = fetchAll("
            SELECT 
                DATE(giris_tarihi) as tarih,
                COUNT(*) as rezervasyon_sayisi,
                SUM(DATEDIFF(cikis_tarihi, giris_tarihi)) as toplam_gece,
                SUM(yetiskin_sayisi + cocuk_sayisi) as toplam_misafir,
                SUM(toplam_tutar) as toplam_tutar,
                SUM(CASE WHEN durum = 'iptal' THEN 1 ELSE 0 END) as iptal_sayisi
            FROM rezervasyonlar 
            WHERE kanal_id = ? 
            AND giris_tarihi BETWEEN ? AND ?
            GROUP BY DATE(giris_tarihi)
            ORDER BY tarih ASC
        ", [$kanal_id, $baslangic_tarihi, $bitis_tarihi]);

        // Rezervasyon verilerini performans formatına çevir
        foreach ($rezervasyon_verileri as $veri) {
            $komisyon_tutari = ($veri['toplam_tutar'] * $secilen_kanal['komisyon_orani']) / 100;
            $net_gelir = $veri['toplam_tutar'] - $komisyon_tutari;
            
            $performans_verileri[] = [
                'tarih' => $veri['tarih'],
                'tarih_gun' => $veri['tarih'],
                'toplam_rezervasyon' => $veri['rezervasyon_sayisi'],
                'toplam_gece' => $veri['toplam_gece'],
                'toplam_misafir' => $veri['toplam_misafir'],
                'brut_gelir' => $veri['toplam_tutar'],
                'net_gelir' => $net_gelir,
                'komisyon_tutari' => $komisyon_tutari,
                'iptal_edilen_rezervasyon' => $veri['iptal_sayisi'],
                'ortalama_gece_fiyati' => $veri['toplam_gece'] > 0 ? $veri['toplam_tutar'] / $veri['toplam_gece'] : 0,
                'doluluk_orani' => 0, // Bu hesaplama için oda sayısı gerekli
                'revpar' => 0, // Bu hesaplama için oda sayısı gerekli
                'adr' => $veri['toplam_gece'] > 0 ? $veri['toplam_tutar'] / $veri['toplam_gece'] : 0
            ];
        }

        // Toplam istatistikleri yeniden hesapla
        if (!empty($performans_verileri)) {
            $toplam_istatistikler = [
                'toplam_rezervasyon' => array_sum(array_column($performans_verileri, 'toplam_rezervasyon')),
                'toplam_gece' => array_sum(array_column($performans_verileri, 'toplam_gece')),
                'toplam_misafir' => array_sum(array_column($performans_verileri, 'toplam_misafir')),
                'toplam_brut_gelir' => array_sum(array_column($performans_verileri, 'brut_gelir')),
                'toplam_net_gelir' => array_sum(array_column($performans_verileri, 'net_gelir')),
                'toplam_komisyon' => array_sum(array_column($performans_verileri, 'komisyon_tutari')),
                'toplam_iptal' => array_sum(array_column($performans_verileri, 'iptal_edilen_rezervasyon')),
                'ortalama_gece_fiyati' => array_sum(array_column($performans_verileri, 'ortalama_gece_fiyati')) / count($performans_verileri),
                'ortalama_doluluk' => 0,
                'ortalama_revpar' => 0,
                'ortalama_adr' => array_sum(array_column($performans_verileri, 'adr')) / count($performans_verileri)
            ];
        }
    }
} else {
    // Tüm kanallar için genel performans
    $performans_verileri = fetchAll("
        SELECT 
            k.kanal_adi,
            k.kanal_kodu,
            SUM(kp.toplam_rezervasyon) as toplam_rezervasyon,
            SUM(kp.toplam_gece) as toplam_gece,
            SUM(kp.toplam_misafir) as toplam_misafir,
            SUM(kp.brut_gelir) as toplam_brut_gelir,
            SUM(kp.net_gelir) as toplam_net_gelir,
            SUM(kp.komisyon_tutari) as toplam_komisyon,
            SUM(kp.iptal_edilen_rezervasyon) as toplam_iptal,
            AVG(kp.ortalama_gece_fiyati) as ortalama_gece_fiyati,
            AVG(kp.doluluk_orani) as ortalama_doluluk,
            AVG(kp.revpar) as ortalama_revpar,
            AVG(kp.adr) as ortalama_adr
        FROM kanal_performans kp
        JOIN kanallar k ON kp.kanal_id = k.id
        WHERE kp.tarih BETWEEN ? AND ?
        GROUP BY k.id, k.kanal_adi, k.kanal_kodu
        ORDER BY toplam_brut_gelir DESC
    ", [$baslangic_tarihi, $bitis_tarihi]);

    // Genel toplam istatistikler
    $toplam_istatistikler = fetchOne("
        SELECT 
            SUM(toplam_rezervasyon) as toplam_rezervasyon,
            SUM(toplam_gece) as toplam_gece,
            SUM(toplam_misafir) as toplam_misafir,
            SUM(brut_gelir) as toplam_brut_gelir,
            SUM(net_gelir) as toplam_net_gelir,
            SUM(komisyon_tutari) as toplam_komisyon,
            SUM(iptal_edilen_rezervasyon) as toplam_iptal,
            AVG(ortalama_gece_fiyati) as ortalama_gece_fiyati,
            AVG(doluluk_orani) as ortalama_doluluk,
            AVG(revpar) as ortalama_revpar,
            AVG(adr) as ortalama_adr
        FROM kanal_performans 
        WHERE tarih BETWEEN ? AND ?
    ", [$baslangic_tarihi, $bitis_tarihi]);
}

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
                        <i class="fas fa-chart-line me-3" style="color: #667eea;"></i>
                        Kanal Performans
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
                        <a href="kanal-analiz.php" class="quick-action-btn">
                            <i class="fas fa-analytics"></i>
                            Analiz
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
                    <label class="form-label">Kanal</label>
                    <select name="kanal_id" class="form-select">
                        <option value="">Tüm Kanallar</option>
                        <?php foreach ($kanallar as $kanal): ?>
                            <option value="<?= $kanal['id'] ?>" <?= $kanal_id == $kanal['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kanal['kanal_adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Başlangıç Tarihi</label>
                    <input type="date" name="baslangic_tarihi" class="form-control" value="<?= $baslangic_tarihi ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Bitiş Tarihi</label>
                    <input type="date" name="bitis_tarihi" class="form-control" value="<?= $bitis_tarihi ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Periyot</label>
                    <select name="periyot" class="form-select">
                        <option value="daily" <?= $periyot === 'daily' ? 'selected' : '' ?>>Günlük</option>
                        <option value="weekly" <?= $periyot === 'weekly' ? 'selected' : '' ?>>Haftalık</option>
                        <option value="monthly" <?= $periyot === 'monthly' ? 'selected' : '' ?>>Aylık</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrele
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($secilen_kanal): ?>
            <!-- Seçili Kanal Bilgisi -->
            <div class="content-card mb-4">
                <h4 class="mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <?= htmlspecialchars($secilen_kanal['kanal_adi']) ?> Performans Raporu
                </h4>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Kanal Kodu:</strong> <?= htmlspecialchars($secilen_kanal['kanal_kodu']) ?></p>
                        <p><strong>Kanal Tipi:</strong> <?= strtoupper($secilen_kanal['kanal_tipi']) ?></p>
                        <p><strong>Komisyon Oranı:</strong> %<?= number_format($secilen_kanal['komisyon_orani'] ?? 0, 2) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Tarih Aralığı:</strong> <?= date('d.m.Y', strtotime($baslangic_tarihi)) ?> - <?= date('d.m.Y', strtotime($bitis_tarihi)) ?></p>
                        <p><strong>Durum:</strong> 
                            <span class="badge bg-<?= $secilen_kanal['aktif'] ? 'success' : 'danger' ?>">
                                <?= $secilen_kanal['aktif'] ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <?php if (!empty($toplam_istatistikler)): ?>
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stats-number"><?= number_format($toplam_istatistikler['toplam_rezervasyon'] ?? 0) ?></div>
                        <div class="stats-label">Toplam Rezervasyon</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stats-number"><?= number_format($toplam_istatistikler['toplam_brut_gelir'] ?? 0, 0) ?>₺</div>
                        <div class="stats-label">Brüt Gelir</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stats-number"><?= number_format($toplam_istatistikler['toplam_komisyon'] ?? 0, 0) ?>₺</div>
                        <div class="stats-label">Toplam Komisyon</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                            <i class="fas fa-bed"></i>
                        </div>
                        <div class="stats-number"><?= number_format($toplam_istatistikler['toplam_gece'] ?? 0) ?></div>
                        <div class="stats-label">Toplam Gece</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Grafikler -->
        <?php if (!empty($performans_verileri)): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-line me-2"></i>
                            Rezervasyon Trendi
                        </h5>
                        <canvas id="rezervasyonChart" height="300"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-bar me-2"></i>
                            Gelir Dağılımı
                        </h5>
                        <canvas id="gelirChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Performans Tablosu -->
        <div class="table-container">
            <h5 class="mb-3">
                <i class="fas fa-table me-2"></i>
                Detaylı Performans Verileri
            </h5>
            
            <?php if (empty($performans_verileri)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h4>Performans Verisi Bulunamadı</h4>
                    <p>Seçilen tarih aralığında performans verisi bulunamadı.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <?php if (!$kanal_id): ?>
                                    <th>Kanal</th>
                                <?php endif; ?>
                                <th>Tarih</th>
                                <th>Rezervasyon</th>
                                <th>Gece</th>
                                <th>Misafir</th>
                                <th>Brüt Gelir</th>
                                <th>Net Gelir</th>
                                <th>Komisyon</th>
                                <th>İptal</th>
                                <th>ADR</th>
                                <th>RevPAR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performans_verileri as $veri): ?>
                                <tr>
                                    <?php if (!$kanal_id): ?>
                                        <td>
                                            <strong><?= htmlspecialchars($veri['kanal_adi']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($veri['kanal_kodu']) ?></small>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= date('d.m.Y', strtotime($veri['tarih_gun'] ?? $veri['tarih'])) ?></td>
                                    <td>
                                        <span class="metric-badge metric-positive">
                                            <i class="fas fa-calendar-check"></i>
                                            <?= number_format($veri['toplam_rezervasyon'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($veri['toplam_gece'] ?? 0) ?></td>
                                    <td><?= number_format($veri['toplam_misafir'] ?? 0) ?></td>
                                    <td>
                                        <span class="metric-badge metric-positive">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <?= number_format($veri['brut_gelir'] ?? 0, 0) ?>₺
                                        </span>
                                    </td>
                                    <td>
                                        <span class="metric-badge metric-positive">
                                            <i class="fas fa-coins"></i>
                                            <?= number_format($veri['net_gelir'] ?? 0, 0) ?>₺
                                        </span>
                                    </td>
                                    <td>
                                        <span class="metric-badge metric-negative">
                                            <i class="fas fa-percentage"></i>
                                            <?= number_format($veri['komisyon_tutari'] ?? 0, 0) ?>₺
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($veri['iptal_edilen_rezervasyon'] > 0): ?>
                                            <span class="metric-badge metric-negative">
                                                <i class="fas fa-times"></i>
                                                <?= number_format($veri['iptal_edilen_rezervasyon'] ?? 0) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="metric-badge metric-neutral">
                                                <i class="fas fa-check"></i>
                                                0
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($veri['adr'] ?? $veri['ortalama_gece_fiyati'], 0) ?>₺</td>
                                    <td><?= number_format($veri['revpar'] ?? 0, 0) ?>₺</td>
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
        <?php if (!empty($performans_verileri)): ?>
        const performansVerileri = <?= json_encode($performans_verileri) ?>;
        
        // Rezervasyon trendi grafiği
        const rezervasyonCtx = document.getElementById('rezervasyonChart').getContext('2d');
        new Chart(rezervasyonCtx, {
            type: 'line',
            data: {
                labels: performansVerileri.map(v => {
                    const tarih = new Date(v.tarih_gun || v.tarih);
                    return tarih.toLocaleDateString('tr-TR');
                }),
                datasets: [{
                    label: 'Rezervasyon Sayısı',
                    data: performansVerileri.map(v => v.toplam_rezervasyon),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'İptal Edilen',
                    data: performansVerileri.map(v => v.iptal_edilen_rezervasyon),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: true
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
                        beginAtZero: true
                    }
                }
            }
        });

        // Gelir dağılımı grafiği
        const gelirCtx = document.getElementById('gelirChart').getContext('2d');
        new Chart(gelirCtx, {
            type: 'bar',
            data: {
                labels: performansVerileri.map(v => {
                    const tarih = new Date(v.tarih_gun || v.tarih);
                    return tarih.toLocaleDateString('tr-TR');
                }),
                datasets: [{
                    label: 'Brüt Gelir',
                    data: performansVerileri.map(v => v.brut_gelir),
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: '#28a745',
                    borderWidth: 1
                }, {
                    label: 'Net Gelir',
                    data: performansVerileri.map(v => v.net_gelir),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }, {
                    label: 'Komisyon',
                    data: performansVerileri.map(v => v.komisyon_tutari),
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
        <?php endif; ?>
    </script>
</body>
</html>
