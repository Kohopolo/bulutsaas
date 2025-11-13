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
if (!hasDetailedPermission('fnb_raporlar')) {
    $_SESSION['error_message'] = 'F&B raporları görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

// Tarih aralığı
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-d');

// Departman filtresi
$departman_filtre = $_GET['departman'] ?? '';

// Rapor verilerini getir
$rapor_verileri = [];

// Genel istatistikler
$rapor_verileri['genel'] = fetchOne("
    SELECT 
        COUNT(*) as toplam_siparis,
        SUM(toplam_tutar) as toplam_ciro,
        SUM(toplam_maliyet) as toplam_maliyet,
        AVG(toplam_tutar) as ortalama_siparis_tutari
    FROM fnb_siparisler 
    WHERE DATE(siparis_tarihi) BETWEEN ? AND ? 
    AND siparis_durumu != 'iptal'
    " . ($departman_filtre ? "AND departman = ?" : ""), 
    $departman_filtre ? [$baslangic_tarihi, $bitis_tarihi, $departman_filtre] : [$baslangic_tarihi, $bitis_tarihi]
);

// Departman bazında satışlar
$rapor_verileri['departman_satislari'] = fetchAll("
    SELECT 
        departman,
        COUNT(*) as siparis_sayisi,
        SUM(toplam_tutar) as toplam_ciro,
        SUM(toplam_maliyet) as toplam_maliyet,
        AVG(toplam_tutar) as ortalama_siparis_tutari
    FROM fnb_siparisler 
    WHERE DATE(siparis_tarihi) BETWEEN ? AND ? 
    AND siparis_durumu != 'iptal'
    GROUP BY departman
    ORDER BY toplam_ciro DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// Günlük satışlar
$rapor_verileri['gunluk_satislar'] = fetchAll("
    SELECT 
        DATE(siparis_tarihi) as tarih,
        COUNT(*) as siparis_sayisi,
        SUM(toplam_tutar) as toplam_ciro
    FROM fnb_siparisler 
    WHERE DATE(siparis_tarihi) BETWEEN ? AND ? 
    AND siparis_durumu != 'iptal'
    GROUP BY DATE(siparis_tarihi)
    ORDER BY tarih DESC
", [$baslangic_tarihi, $bitis_tarihi]);

// En çok satan ürünler
$rapor_verileri['en_cok_satan'] = fetchAll("
    SELECT 
        mo.urun_adi,
        mo.kategori_id,
        mk.kategori_adi,
        mk.departman,
        SUM(fsd.adet) as toplam_adet,
        SUM(fsd.toplam_fiyat) as toplam_ciro
    FROM fnb_siparis_detaylari fsd
    JOIN fnb_siparisler fs ON fsd.siparis_id = fs.id
    JOIN menu_ogeleri mo ON fsd.menu_ogesi_id = mo.id
    JOIN menu_kategorileri mk ON mo.kategori_id = mk.id
    WHERE DATE(fs.siparis_tarihi) BETWEEN ? AND ? 
    AND fs.siparis_durumu != 'iptal'
    GROUP BY mo.id
    ORDER BY toplam_adet DESC
    LIMIT 10
", [$baslangic_tarihi, $bitis_tarihi]);

// Departmanlar
$departmanlar = [
    'mutfak' => 'Mutfak',
    'restoran' => 'Restoran',
    'bar' => 'Bar',
    'pastane' => 'Pastane'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&B Raporlar - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>F&B Raporlar</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="fnb-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Dashboard
                        </a>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Rapor Filtreleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" 
                                           value="<?php echo $baslangic_tarihi; ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" 
                                           value="<?php echo $bitis_tarihi; ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="departman" class="form-label">Departman</label>
                                    <select class="form-select" id="departman" name="departman">
                                        <option value="">Tüm Departmanlar</option>
                                        <?php foreach ($departmanlar as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $departman_filtre == $key ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i>Filtrele
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Genel İstatistikler -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Toplam Sipariş</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rapor_verileri['genel']['toplam_siparis']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Toplam Ciro</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($rapor_verileri['genel']['toplam_ciro'], 2); ?>₺</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Toplam Maliyet</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($rapor_verileri['genel']['toplam_maliyet'], 2); ?>₺</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calculator fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Ortalama Sipariş</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($rapor_verileri['genel']['ortalama_siparis_tutari'], 2); ?>₺</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafikler -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Departman Bazında Satışlar</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="departmanChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Günlük Satış Trendi</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="gunlukChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Departman Bazında Satışlar -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie me-2"></i>Departman Bazında Satışlar
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Departman</th>
                                        <th>Sipariş Sayısı</th>
                                        <th>Toplam Ciro</th>
                                        <th>Toplam Maliyet</th>
                                        <th>Kar</th>
                                        <th>Ortalama Sipariş</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapor_verileri['departman_satislari'] as $satis): ?>
                                    <?php $kar = $satis['toplam_ciro'] - $satis['toplam_maliyet']; ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $departmanlar[$satis['departman']] == 'Mutfak' ? 'danger' : ($departmanlar[$satis['departman']] == 'Restoran' ? 'success' : ($departmanlar[$satis['departman']] == 'Bar' ? 'info' : 'warning')); ?>">
                                                <?php echo $departmanlar[$satis['departman']]; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $satis['siparis_sayisi']; ?></td>
                                        <td><strong><?php echo number_format($satis['toplam_ciro'], 2); ?>₺</strong></td>
                                        <td><?php echo number_format($satis['toplam_maliyet'], 2); ?>₺</td>
                                        <td>
                                            <span class="text-<?php echo $kar >= 0 ? 'success' : 'danger'; ?>">
                                                <strong><?php echo number_format($kar, 2); ?>₺</strong>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($satis['ortalama_siparis_tutari'], 2); ?>₺</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- En Çok Satan Ürünler -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-trophy me-2"></i>En Çok Satan Ürünler
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Sıra</th>
                                        <th>Ürün Adı</th>
                                        <th>Kategori</th>
                                        <th>Departman</th>
                                        <th>Toplam Adet</th>
                                        <th>Toplam Ciro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapor_verileri['en_cok_satan'] as $index => $urun): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                <?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($urun['urun_adi']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($urun['kategori_adi']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $departmanlar[$urun['departman']] == 'Mutfak' ? 'danger' : ($departmanlar[$urun['departman']] == 'Restoran' ? 'success' : ($departmanlar[$urun['departman']] == 'Bar' ? 'info' : 'warning')); ?>">
                                                <?php echo $departmanlar[$urun['departman']]; ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo $urun['toplam_adet']; ?></strong></td>
                                        <td><strong><?php echo number_format($urun['toplam_ciro'], 2); ?>₺</strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Departman bazında satışlar grafiği
        const departmanCtx = document.getElementById('departmanChart').getContext('2d');
        const departmanData = <?php echo json_encode($rapor_verileri['departman_satislari']); ?>;
        
        new Chart(departmanCtx, {
            type: 'doughnut',
            data: {
                labels: departmanData.map(item => {
                    const departmanNames = {
                        'mutfak': 'Mutfak',
                        'restoran': 'Restoran',
                        'bar': 'Bar',
                        'pastane': 'Pastane'
                    };
                    return departmanNames[item.departman];
                }),
                datasets: [{
                    data: departmanData.map(item => item.toplam_ciro),
                    backgroundColor: [
                        '#dc3545',
                        '#198754',
                        '#0dcaf0',
                        '#ffc107'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Günlük satış trendi grafiği
        const gunlukCtx = document.getElementById('gunlukChart').getContext('2d');
        const gunlukData = <?php echo json_encode($rapor_verileri['gunluk_satislar']); ?>;
        
        new Chart(gunlukCtx, {
            type: 'line',
            data: {
                labels: gunlukData.map(item => {
                    const date = new Date(item.tarih);
                    return date.toLocaleDateString('tr-TR');
                }),
                datasets: [{
                    label: 'Günlük Ciro (₺)',
                    data: gunlukData.map(item => item.toplam_ciro),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
