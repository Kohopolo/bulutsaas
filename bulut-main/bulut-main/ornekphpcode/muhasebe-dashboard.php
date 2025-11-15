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
if (!hasDetailedPermission('muhasebe_dashboard')) {
    $_SESSION['error_message'] = 'Muhasebe dashboard görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$bugun = date('Y-m-d');
$bu_ay = date('Y-m');

// İstatistikleri hesapla
$stats = [];

// Toplam fiş sayısı
$toplam_fis_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM muhasebe_fisleri
");
$stats['toplam_fis'] = $toplam_fis_result['toplam'] ?? 0;

// Bu ayki fiş sayısı
$bu_ay_fis_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM muhasebe_fisleri 
    WHERE DATE_FORMAT(fis_tarihi, '%Y-%m') = ?
", [$bu_ay]);
$stats['bu_ay_fis'] = $bu_ay_fis_result['toplam'] ?? 0;

// Toplam borç
$toplam_borc_result = fetchOne("
    SELECT SUM(borc_tutari) as toplam 
    FROM muhasebe_fis_satirlari
");
$stats['toplam_borc'] = $toplam_borc_result['toplam'] ?? 0;

// Toplam alacak
$toplam_alacak_result = fetchOne("
    SELECT SUM(alacak_tutari) as toplam 
    FROM muhasebe_fis_satirlari
");
$stats['toplam_alacak'] = $toplam_alacak_result['toplam'] ?? 0;

// Aktif hesap sayısı
$aktif_hesap_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM muhasebe_hesaplari 
    WHERE aktif = 1
");
$stats['aktif_hesap'] = $aktif_hesap_result['toplam'] ?? 0;

// Rezervasyon entegrasyonu istatistikleri
$rezervasyon_faturalari = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM muhasebe_faturalari 
    WHERE rezervasyon_id IS NOT NULL
");
$stats['rezervasyon_faturalari'] = $rezervasyon_faturalari['toplam'] ?? 0;

$toplam_fatura_tutari = fetchOne("
    SELECT SUM(genel_toplam) as toplam 
    FROM muhasebe_faturalari 
    WHERE rezervasyon_id IS NOT NULL AND durum = 'aktif'
");
$stats['toplam_fatura_tutari'] = $toplam_fatura_tutari['toplam'] ?? 0;

$odenen_faturalar = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM muhasebe_faturalari 
    WHERE rezervasyon_id IS NOT NULL AND durum = 'tamamen_odendi'
");
$stats['odenen_faturalar'] = $odenen_faturalar['toplam'] ?? 0;

// Son fişler
$son_fisler = fetchAll("
    SELECT mf.*, k.ad as olusturan_adi, k.soyad as olusturan_soyadi
    FROM muhasebe_fisleri mf
    LEFT JOIN kullanicilar k ON mf.olusturan_id = k.id
    ORDER BY mf.fis_tarihi DESC
    LIMIT 10
");

// Fiş türleri
$fis_turleri = [
    'gider' => 'Gider Fişi',
    'gelir' => 'Gelir Fişi',
    'mahsup' => 'Mahsup Fişi',
    'kapanis' => 'Kapanış Fişi'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muhasebe Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Muhasebe Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="muhasebe-fis-olustur.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Fiş Oluştur
                            </a>
                            <a href="muhasebe-fis-listesi.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Fiş Listesi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Fiş</h6>
                                        <h3><?php echo $stats['toplam_fis']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-file-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bu Ay</h6>
                                        <h3><?php echo $stats['bu_ay_fis']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Borç</h6>
                                        <h3><?php echo number_format($stats['toplam_borc'], 2); ?>₺</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-arrow-down fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Alacak</h6>
                                        <h3><?php echo number_format($stats['toplam_alacak'], 2); ?>₺</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-arrow-up fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bakiye</h6>
                                        <h3><?php echo number_format($stats['toplam_alacak'] - $stats['toplam_borc'], 2); ?>₺</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-balance-scale fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Aktif Hesap</h6>
                                        <h3><?php echo $stats['aktif_hesap']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calculator fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Rezervasyon Faturaları</h6>
                                        <h3><?php echo $stats['rezervasyon_faturalari']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-receipt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-dark text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Fatura Tutarı</h6>
                                        <h3><?php echo number_format($stats['toplam_fatura_tutari'], 2); ?>₺</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Ödenen Faturalar</h6>
                                        <h3><?php echo $stats['odenen_faturalar']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son Fişler -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history"></i> Son Fişler
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($son_fisler)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Henüz fiş bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Fiş No</th>
                                            <th>Tür</th>
                                            <th>Tarih</th>
                                            <th>Toplam Tutar</th>
                                            <th>Oluşturan</th>
                                            <th>Açıklama</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($son_fisler as $fis): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($fis['fis_no']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($fis_turleri[$fis['fis_turu']] ?? $fis['fis_turu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d.m.Y', strtotime($fis['fis_tarihi'])); ?></td>
                                                <td><?php echo number_format($fis['toplam_tutar'], 2); ?>₺</td>
                                                <td><?php echo htmlspecialchars($fis['olusturan_adi'] . ' ' . $fis['olusturan_soyadi']); ?></td>
                                                <td><?php echo htmlspecialchars($fis['aciklama']); ?></td>
                                                <td>
                                                    <a href="muhasebe-fis-detay.php?id=<?php echo $fis['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> Detay
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>