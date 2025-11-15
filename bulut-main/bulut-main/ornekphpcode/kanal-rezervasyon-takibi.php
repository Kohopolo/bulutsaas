<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Yetki kontrolü
if (!hasDetailedPermission('kanal_raporlar')) {
    $_SESSION['error_message'] = 'Kanal rezervasyon takibi yetkiniz bulunmamaktadır.';
    header('Location: index.php');
    exit;
}

$page_title = "Kanal Rezervasyon Takibi";
$current_page = "kanal-rezervasyon-takibi";

// Filtreleme parametreleri
$kanal_id = $_GET['kanal_id'] ?? '';
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-t');
$durum = $_GET['durum'] ?? '';

// Kanalları getir
$kanallar = fetchAll("SELECT * FROM kanallar WHERE aktif = 1 ORDER BY kanal_adi");

// Kanal bazlı rezervasyon istatistikleri
$kanal_istatistikleri = fetchAll("
    SELECT 
        k.id,
        k.kanal_adi,
        k.kanal_kodu,
        COUNT(r.id) as toplam_rezervasyon,
        SUM(r.toplam_tutar) as toplam_gelir,
        AVG(r.toplam_tutar) as ortalama_rezervasyon_tutari,
        COUNT(CASE WHEN r.durum = 'onaylandi' THEN 1 END) as onaylanan_rezervasyon,
        COUNT(CASE WHEN r.durum = 'check_in' THEN 1 END) as checkin_rezervasyon,
        COUNT(CASE WHEN r.durum = 'check_out' THEN 1 END) as checkout_rezervasyon,
        COUNT(CASE WHEN r.durum = 'iptal' THEN 1 END) as iptal_rezervasyon,
        SUM(CASE WHEN r.durum = 'onaylandi' THEN r.toplam_tutar ELSE 0 END) as onaylanan_gelir,
        SUM(CASE WHEN r.durum = 'check_in' THEN r.toplam_tutar ELSE 0 END) as checkin_gelir,
        SUM(CASE WHEN r.durum = 'check_out' THEN r.toplam_tutar ELSE 0 END) as checkout_gelir
    FROM kanallar k
    LEFT JOIN rezervasyonlar r ON k.id = r.kanal_id 
        AND r.giris_tarihi BETWEEN ? AND ?
        " . ($durum ? "AND r.durum = ?" : "") . "
    WHERE k.aktif = 1
    GROUP BY k.id
    ORDER BY toplam_gelir DESC
", array_filter([$baslangic_tarihi, $bitis_tarihi, $durum]));

// Detaylı rezervasyon listesi
$where_conditions = ["r.giris_tarihi BETWEEN ? AND ?"];
$params = [$baslangic_tarihi, $bitis_tarihi];

if ($kanal_id) {
    $where_conditions[] = "r.kanal_id = ?";
    $params[] = $kanal_id;
}

if ($durum) {
    $where_conditions[] = "r.durum = ?";
    $params[] = $durum;
}

$rezervasyonlar = fetchAll("
    SELECT 
        r.*,
        k.kanal_adi,
        k.kanal_kodu,
        CONCAT(m.ad, ' ', m.soyad) as musteri_adi,
        m.telefon as musteri_telefon,
        m.email as musteri_email,
        ot.oda_tipi_adi,
        onr.oda_numarasi
    FROM rezervasyonlar r
    LEFT JOIN kanallar k ON r.kanal_id = k.id
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN oda_numaralari onr ON r.oda_numarasi_id = onr.id
    WHERE " . implode(' AND ', $where_conditions) . "
    ORDER BY r.giris_tarihi DESC, r.olusturma_tarihi DESC
    LIMIT 100
", $params);

// Aylık kanal performans trendi
$aylik_trend = fetchAll("
    SELECT 
        DATE_FORMAT(r.giris_tarihi, '%Y-%m') as ay,
        k.kanal_adi,
        COUNT(r.id) as rezervasyon_sayisi,
        SUM(r.toplam_tutar) as toplam_gelir,
        AVG(r.toplam_tutar) as ortalama_tutar
    FROM rezervasyonlar r
    JOIN kanallar k ON r.kanal_id = k.id
    WHERE r.giris_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    " . ($kanal_id ? "AND r.kanal_id = ?" : "") . "
    GROUP BY DATE_FORMAT(r.giris_tarihi, '%Y-%m'), k.id
    ORDER BY ay DESC, toplam_gelir DESC
", $kanal_id ? [$kanal_id] : []);

// Kanal karşılaştırma verileri
$kanal_karsilastirma = fetchAll("
    SELECT 
        k.kanal_adi,
        COUNT(r.id) as rezervasyon_sayisi,
        SUM(r.toplam_tutar) as toplam_gelir,
        AVG(r.toplam_tutar) as ortalama_tutar,
        COUNT(CASE WHEN r.durum = 'iptal' THEN 1 END) as iptal_sayisi,
        ROUND((COUNT(CASE WHEN r.durum = 'iptal' THEN 1 END) / COUNT(r.id)) * 100, 2) as iptal_orani
    FROM kanallar k
    LEFT JOIN rezervasyonlar r ON k.id = r.kanal_id 
        AND r.giris_tarihi BETWEEN ? AND ?
    WHERE k.aktif = 1
    GROUP BY k.id
    HAVING rezervasyon_sayisi > 0
    ORDER BY toplam_gelir DESC
", [$baslangic_tarihi, $bitis_tarihi]);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Otel Yönetim Sistemi</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        .desktop-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .desktop-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-container {
            background: white;
            margin: 0;
            padding: 20px;
            border-radius: 0;
            box-shadow: none;
        }
        
        .istatistik-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .istatistik-deger {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .istatistik-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .kanal-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .kanal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .kanal-durum {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .durum-onaylandi { background: #d4edda; color: #155724; }
        .durum-checkin { background: #cce5ff; color: #004085; }
        .durum-checkout { background: #d1ecf1; color: #0c5460; }
        .durum-iptal { background: #f8d7da; color: #721c24; }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Desktop Header -->
        <div class="desktop-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Kanal Rezervasyon Takibi
                        </h1>
                        <p class="mb-0 mt-2" style="opacity: 0.9;">
                            <i class="fas fa-clock me-2"></i>
                            <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="kanal-analiz.php" class="btn btn-outline-light">
                                <i class="fas fa-chart-bar"></i> Analiz
                            </a>
                            <a href="kanal-performans.php" class="btn btn-outline-light">
                                <i class="fas fa-tachometer-alt"></i> Performans
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt"></i> Çıkış
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="container-fluid">
                <!-- Filtreleme Formu -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filtreleme Seçenekleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="kanal_id" class="form-label">Kanal</label>
                                <select class="form-select" name="kanal_id" id="kanal_id">
                                    <option value="">Tüm Kanallar</option>
                                    <?php foreach ($kanallar as $kanal): ?>
                                        <option value="<?= $kanal['id'] ?>" <?= $kanal_id == $kanal['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kanal['kanal_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç</label>
                                <input type="date" class="form-control" name="baslangic_tarihi" 
                                       id="baslangic_tarihi" value="<?= $baslangic_tarihi ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="bitis_tarihi" class="form-label">Bitiş</label>
                                <input type="date" class="form-control" name="bitis_tarihi" 
                                       id="bitis_tarihi" value="<?= $bitis_tarihi ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="durum" class="form-label">Durum</label>
                                <select class="form-select" name="durum" id="durum">
                                    <option value="">Tüm Durumlar</option>
                                    <option value="onaylandi" <?= $durum === 'onaylandi' ? 'selected' : '' ?>>Onaylandı</option>
                                    <option value="check_in" <?= $durum === 'check_in' ? 'selected' : '' ?>>Check-in</option>
                                    <option value="check_out" <?= $durum === 'check_out' ? 'selected' : '' ?>>Check-out</option>
                                    <option value="iptal" <?= $durum === 'iptal' ? 'selected' : '' ?>>İptal</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filtrele
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Kanal İstatistikleri -->
                <div class="row mb-4">
                    <?php foreach ($kanal_istatistikleri as $istatistik): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="kanal-card">
                                <div class="kanal-header">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($istatistik['kanal_adi']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($istatistik['kanal_kodu']) ?></small>
                                    </div>
                                    <span class="badge bg-primary"><?= $istatistik['toplam_rezervasyon'] ?></span>
                                </div>
                                
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="fw-bold text-success"><?= number_format($istatistik['toplam_gelir'] ?? 0, 0) ?>₺</div>
                                        <small class="text-muted">Toplam Gelir</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="fw-bold text-info"><?= number_format($istatistik['ortalama_rezervasyon_tutari'] ?? 0, 0) ?>₺</div>
                                        <small class="text-muted">Ortalama</small>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Onaylandı</small>
                                        <small><?= $istatistik['onaylanan_rezervasyon'] ?></small>
                                    </div>
                                    <div class="progress progress-bar-custom">
                                        <div class="progress-bar bg-success" style="width: <?= $istatistik['toplam_rezervasyon'] > 0 ? ($istatistik['onaylanan_rezervasyon'] / $istatistik['toplam_rezervasyon']) * 100 : 0 ?>%"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-1 mt-2">
                                        <small>Check-in</small>
                                        <small><?= $istatistik['checkin_rezervasyon'] ?></small>
                                    </div>
                                    <div class="progress progress-bar-custom">
                                        <div class="progress-bar bg-info" style="width: <?= $istatistik['toplam_rezervasyon'] > 0 ? ($istatistik['checkin_rezervasyon'] / $istatistik['toplam_rezervasyon']) * 100 : 0 ?>%"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-1 mt-2">
                                        <small>İptal</small>
                                        <small><?= $istatistik['iptal_rezervasyon'] ?></small>
                                    </div>
                                    <div class="progress progress-bar-custom">
                                        <div class="progress-bar bg-danger" style="width: <?= $istatistik['toplam_rezervasyon'] > 0 ? ($istatistik['iptal_rezervasyon'] / $istatistik['toplam_rezervasyon']) * 100 : 0 ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Grafikler - Yan Yana -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-line me-2"></i>
                                Aylık Trend
                            </h5>
                            <canvas id="trendChart" height="150"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-bar me-2"></i>
                                Kanal Karşılaştırması
                            </h5>
                            <canvas id="comparisonChart" height="150"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detaylı Rezervasyon Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Detaylı Rezervasyon Listesi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rezervasyon No</th>
                                        <th>Kanal</th>
                                        <th>Müşteri</th>
                                        <th>Oda Tipi</th>
                                        <th>Giriş Tarihi</th>
                                        <th>Çıkış Tarihi</th>
                                        <th>Tutar</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rezervasyonlar as $rezervasyon): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($rezervasyon['rezervasyon_kodu'] ?? 'N/A') ?></strong>
                                                <?php if (!empty($rezervasyon['kanal_rezervasyon_no'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($rezervasyon['kanal_rezervasyon_no']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rezervasyon['kanal_adi']): ?>
                                                    <span class="badge bg-primary"><?= htmlspecialchars($rezervasyon['kanal_adi']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Direkt</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($rezervasyon['musteri_adi'] ?? 'Bilinmiyor') ?></strong>
                                                <?php if ($rezervasyon['musteri_telefon']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($rezervasyon['musteri_telefon']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($rezervasyon['oda_tipi_adi'] ?? 'Bilinmiyor') ?>
                                                <?php if ($rezervasyon['oda_numarasi']): ?>
                                                    <br><small class="text-muted">Oda: <?= htmlspecialchars($rezervasyon['oda_numarasi']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])) ?></td>
                                            <td><?= date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])) ?></td>
                                            <td>
                                                <strong><?= number_format($rezervasyon['toplam_tutar'], 2) ?>₺</strong>
                                            </td>
                                            <td>
                                                <span class="kanal-durum durum-<?= $rezervasyon['durum'] ?>">
                                                    <?php
                                                    switch ($rezervasyon['durum']) {
                                                        case 'onaylandi': echo 'Onaylandı'; break;
                                                        case 'check_in': echo 'Check-in'; break;
                                                        case 'check_out': echo 'Check-out'; break;
                                                        case 'iptal': echo 'İptal'; break;
                                                        default: echo ucfirst($rezervasyon['durum']); break;
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="rezervasyon-detay.php?id=<?= $rezervasyon['id'] ?>" class="btn btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($rezervasyon['kanal_id']): ?>
                                                        <button class="btn btn-outline-info" onclick="kanalDetay(<?= $rezervasyon['kanal_id'] ?>)">
                                                            <i class="fas fa-info-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Trend grafiği
        const trendData = <?= json_encode($aylik_trend) ?>;
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        
        // Kanal bazlı veri gruplama
        const kanalTrendData = {};
        trendData.forEach(item => {
            if (!kanalTrendData[item.kanal_adi]) {
                kanalTrendData[item.kanal_adi] = [];
            }
            kanalTrendData[item.kanal_adi].push(item);
        });
        
        const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe'];
        const datasets = [];
        let colorIndex = 0;
        
        Object.keys(kanalTrendData).forEach(kanal => {
            const data = kanalTrendData[kanal];
            datasets.push({
                label: kanal,
                data: data.map(item => parseFloat(item.toplam_gelir)),
                borderColor: colors[colorIndex % colors.length],
                backgroundColor: colors[colorIndex % colors.length] + '20',
                tension: 0.4,
                fill: false
            });
            colorIndex++;
        });
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [...new Set(trendData.map(item => item.ay))].sort(),
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Gelir (₺)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Ay'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Karşılaştırma grafiği
        const comparisonData = <?= json_encode($kanal_karsilastirma) ?>;
        const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
        
        new Chart(comparisonCtx, {
            type: 'bar',
            data: {
                labels: comparisonData.map(item => item.kanal_adi),
                datasets: [{
                    label: 'Toplam Gelir (₺)',
                    data: comparisonData.map(item => parseFloat(item.toplam_gelir)),
                    backgroundColor: '#667eea',
                    borderColor: '#667eea',
                    borderWidth: 1
                }, {
                    label: 'Rezervasyon Sayısı',
                    data: comparisonData.map(item => item.rezervasyon_sayisi),
                    backgroundColor: '#764ba2',
                    borderColor: '#764ba2',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Gelir (₺)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Rezervasyon Sayısı'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Kanal detay fonksiyonu
        function kanalDetay(kanalId) {
            // Kanal detay modalını aç
            fetch(`kanal-rezervasyon-takibi.php?action=kanal_detay&id=${kanalId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Modal içeriğini oluştur ve göster
                        alert('Kanal detayları: ' + JSON.stringify(data.kanal));
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                });
        }
    </script>
</body>
</html>
