<?php
/**
 * Multi Otel Modülü - Ana Sayfa
 * Multi otel yönetimi için dashboard
 */

require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../includes/error_handler.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once 'includes/multi-otel-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Mevcut otel bilgisini al
$current_otel = getCurrentOtel();
if (!$current_otel) {
    // İlk oteli seç
    $user_oteller = getUserOteller($_SESSION['user_id']);
    if (!empty($user_oteller)) {
        setCurrentOtel($user_oteller[0]['id']);
        $current_otel = getCurrentOtel();
    } else {
        // Kullanıcının hiç oteli yok, otel ekleme sayfasına yönlendir
        header('Location: oteller.php');
        exit;
    }
}

// İstatistikler
$stats = getOtelStats($current_otel['id']);

// Bugünkü rezervasyonlar
$bugun_rezervasyonlar = fetchAll("
    SELECT r.*, ot.oda_tipi_adi, onum.oda_numarasi, m.ad as musteri_adi, m.soyad as musteri_soyadi
    FROM rezervasyonlar r
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN oda_numaralari onum ON r.oda_numarasi_id = onum.id
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    WHERE r.otel_id = ? AND DATE(r.olusturma_tarihi) = CURDATE()
    ORDER BY r.olusturma_tarihi DESC
    LIMIT 5
", [$current_otel['id']]);

// Yaklaşan check-in'ler
$yaklasan_checkin = fetchAll("
    SELECT r.*, ot.oda_tipi_adi, onum.oda_numarasi, m.ad as musteri_adi, m.soyad as musteri_soyadi
    FROM rezervasyonlar r
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN oda_numaralari onum ON r.oda_numarasi_id = onum.id
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    WHERE r.otel_id = ? AND r.durum = 'onaylandi' AND r.giris_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY r.giris_tarihi ASC
    LIMIT 5
", [$current_otel['id']]);

// Yaklaşan check-out'lar
$yaklasan_checkout = fetchAll("
    SELECT r.*, ot.oda_tipi_adi, onum.oda_numarasi, m.ad as musteri_adi, m.soyad as musteri_soyadi
    FROM rezervasyonlar r
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN oda_numaralari onum ON r.oda_numarasi_id = onum.id
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    WHERE r.otel_id = ? AND r.durum = 'check_in' AND r.cikis_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY r.cikis_tarihi ASC
    LIMIT 5
", [$current_otel['id']]);

// Kullanıcının yetkili olduğu otelleri getir
$user_oteller = getUserOteller($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi Otel Yönetimi - <?php echo htmlspecialchars($current_otel['otel_adi']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/multi-otel-sidebar.php'; ?>

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
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid">
            <!-- Hoş Geldiniz -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                <small class="text-muted">- <?php echo htmlspecialchars($current_otel['otel_adi']); ?></small>
                            </h1>
                            <p class="text-muted">Multi otel yönetim paneline hoş geldiniz</p>
                        </div>
                        <div class="btn-group">
                            <a href="rezervasyon-ekle-multi.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Yeni Rezervasyon
                            </a>
                            <a href="oteller.php" class="btn btn-outline-secondary">
                                <i class="fas fa-building me-2"></i>Oteller
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Toplam Rezervasyon
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['toplam_rezervasyon']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar fa-2x text-gray-300"></i>
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
                                        Aktif Konaklama
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['aktif_konaklama']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-bed fa-2x text-gray-300"></i>
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
                                        Toplam Gelir
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['toplam_gelir'], 2); ?> ₺
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-lira-sign fa-2x text-gray-300"></i>
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
                                        Doluluk Oranı
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        %<?php echo $stats['doluluk_orani']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hızlı İşlemler -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Hızlı İşlemler</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="rezervasyon-ekle-multi.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                        <i class="fas fa-hotel fa-2x mb-2"></i>
                                        <span>Çoklu Oda Rezervasyon</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="rezervasyon-ekle.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                        <i class="fas fa-plus fa-2x mb-2"></i>
                                        <span>Tek Oda Rezervasyon</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="oda-tipleri.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                        <i class="fas fa-bed fa-2x mb-2"></i>
                                        <span>Oda Tipleri</span>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="raporlar.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                        <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                        <span>Raporlar</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Günlük Özet -->
            <div class="row">
                <!-- Bugünkü Rezervasyonlar -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-day me-2"></i>Bugünkü Rezervasyonlar
                                <span class="badge bg-light text-dark ms-2"><?php echo count($bugun_rezervasyonlar); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bugun_rezervasyonlar)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                <p>Bugün rezervasyon yok</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($bugun_rezervasyonlar as $rezervasyon): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?> - 
                                            <?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary"><?php echo number_format($rezervasyon['toplam_tutar'], 2); ?> ₺</span>
                                        <br>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($rezervasyon['olusturma_tarihi'])); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="rezervasyonlar.php" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-eye me-1"></i>Tümünü Görüntüle
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Yaklaşan Check-in'ler -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-sign-in-alt me-2"></i>Yaklaşan Check-in'ler
                                <span class="badge bg-light text-dark ms-2"><?php echo count($yaklasan_checkin); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($yaklasan_checkin)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                <p>Yaklaşan check-in yok</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($yaklasan_checkin as $rezervasyon): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-info"><?php echo date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])); ?></span>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="rezervasyonlar.php?durum=onaylandi" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-eye me-1"></i>Tümünü Görüntüle
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Yaklaşan Check-out'lar -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-sign-out-alt me-2"></i>Yaklaşan Check-out'lar
                                <span class="badge bg-light text-dark ms-2"><?php echo count($yaklasan_checkout); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($yaklasan_checkout)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                <p>Yaklaşan check-out yok</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($yaklasan_checkout as $rezervasyon): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning"><?php echo date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])); ?></span>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="rezervasyonlar.php?durum=check_in" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-eye me-1"></i>Tümünü Görüntüle
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Otel Bilgileri -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-building me-2"></i>Otel Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h6 class="text-primary"><?php echo htmlspecialchars($current_otel['otel_adi']); ?></h6>
                                    <?php if ($current_otel['kisa_aciklama']): ?>
                                    <p class="text-muted"><?php echo htmlspecialchars($current_otel['kisa_aciklama']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($current_otel['telefon'] || $current_otel['email']): ?>
                                <div class="col-12">
                                    <?php if ($current_otel['telefon']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-phone me-2 text-muted"></i>
                                        <small><?php echo htmlspecialchars($current_otel['telefon']); ?></small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($current_otel['email']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-envelope me-2 text-muted"></i>
                                        <small><?php echo htmlspecialchars($current_otel['email']); ?></small>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($current_otel['website']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-globe me-2 text-muted"></i>
                                        <small><a href="<?php echo htmlspecialchars($current_otel['website']); ?>" target="_blank">Website</a></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-12 mt-3">
                                    <a href="otel-duzenle.php?id=<?php echo $current_otel['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-edit me-1"></i>Otel Bilgilerini Düzenle
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
