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
requireDetailedPermission('resepsiyon_dashboard', 'Resepsiyon dashboard görüntüleme yetkiniz bulunmamaktadır.');

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Bugünkü istatistikleri al
$bugun = date('Y-m-d');
$yarin = date('Y-m-d', strtotime('+1 day'));

// Bugünkü check-in'ler
$bugun_checkin = fetchAll("
    SELECT r.*, m.ad, m.soyad, m.telefon, od.oda_numarasi, ot.oda_tipi_adi 
    FROM rezervasyonlar r 
    JOIN musteriler m ON r.musteri_id = m.id 
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    WHERE DATE(r.giris_tarihi) = ? AND r.durum = 'onaylandi'
    ORDER BY r.giris_tarihi ASC
", [$bugun]);

// Bugünkü check-out'lar
$bugun_checkout = fetchAll("
    SELECT r.*, m.ad, m.soyad, m.telefon, od.oda_numarasi, ot.oda_tipi_adi 
    FROM rezervasyonlar r 
    JOIN musteriler m ON r.musteri_id = m.id 
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    WHERE DATE(r.cikis_tarihi) = ? AND r.durum = 'check_in'
    ORDER BY r.cikis_tarihi ASC
", [$bugun]);

// Aktif konaklamalar
$aktif_konaklamalar = fetchAll("
    SELECT r.*, m.ad, m.soyad, m.telefon, od.oda_numarasi, ot.oda_tipi_adi 
    FROM rezervasyonlar r 
    JOIN musteriler m ON r.musteri_id = m.id 
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    WHERE r.durum = 'check_in'
    ORDER BY od.oda_numarasi ASC
");

// Oda durumları
$toplam_oda = fetchOne("SELECT COUNT(*) as count FROM oda_numaralari")['count'];
$dolu_oda = fetchOne("SELECT COUNT(*) as count FROM rezervasyonlar WHERE durum = 'check_in'")['count'];
$musait_oda = $toplam_oda - $dolu_oda;

// Bekleyen rezervasyonlar
$bekleyen_rezervasyonlar = fetchAll("
    SELECT r.*, m.ad, m.soyad, m.telefon 
    FROM rezervasyonlar r 
    JOIN musteriler m ON r.musteri_id = m.id 
    WHERE r.durum = 'beklemede'
    ORDER BY r.olusturma_tarihi DESC
    LIMIT 5
");

$page_title = 'Resepsiyon Dashboard';
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <h2 class="page-title">
                                <i class="fas fa-concierge-bell me-2"></i>Resepsiyon Dashboard
                            </h2>
                        </div>
                        <div class="col-auto ms-auto d-print-none">
                            <div class="btn-list">
                                <a href="resepsiyon-raporlar.php" class="btn btn-outline-info">
                                    <i class="fas fa-chart-bar me-1"></i>Günlük Raporlar
                                </a>
                                <a href="resepsiyon-checkin.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-1"></i>Check-In
                                </a>
                                <a href="resepsiyon-checkout.php" class="btn btn-warning">
                                    <i class="fas fa-sign-out-alt me-1"></i>Check-Out
                                </a>
                                <a href="resepsiyon-hizli-rezervasyon.php" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i>Hızlı Rezervasyon
                                </a>
                                <a href="resepsiyon-rezervasyonlar.php" class="btn btn-info">
                                    <i class="fas fa-list me-1"></i>Rezervasyonlar
                                </a>
                                <a href="resepsiyon-odalar.php" class="btn btn-secondary">
                                    <i class="fas fa-bed me-1"></i>Odalar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- İstatistik Kartları -->
    <div class="row mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Toplam Oda</div>
                        <div class="ms-auto lh-1">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-muted" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-bed"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="h1 mb-3"><?= $toplam_oda ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Dolu Oda</div>
                        <div class="ms-auto lh-1">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-muted" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-door-closed text-danger"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="h1 mb-3 text-danger"><?= $dolu_oda ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Müsait Oda</div>
                        <div class="ms-auto lh-1">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-muted" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-door-open text-success"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="h1 mb-3 text-success"><?= $musait_oda ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Doluluk Oranı</div>
                        <div class="ms-auto lh-1">
                            <div class="dropdown">
                                <a class="dropdown-toggle text-muted" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-chart-pie"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="h1 mb-3"><?= $toplam_oda > 0 ? round(($dolu_oda / $toplam_oda) * 100) : 0 ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Bugünkü Check-In'ler -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-sign-in-alt me-2"></i>Bugünkü Check-In'ler (<?= count($bugun_checkin) ?>)
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($bugun_checkin)): ?>
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-calendar-check fa-3x mb-3"></i>
                            <p>Bugün check-in yapacak misafir bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Misafir</th>
                                        <th>Oda</th>
                                        <th>Saat</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bugun_checkin as $rezervasyon): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex py-1 align-items-center">
                                                    <div class="flex-fill">
                                                        <div class="font-weight-medium"><?= htmlspecialchars($rezervasyon['ad'] . ' ' . $rezervasyon['soyad']) ?></div>
                                                        <div class="text-muted"><?= htmlspecialchars($rezervasyon['telefon']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($rezervasyon['oda_numarasi']): ?>
                                                    <span class="badge bg-blue"><?= htmlspecialchars($rezervasyon['oda_numarasi']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Oda Atanmamış</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('H:i', strtotime($rezervasyon['giris_tarihi'])) ?></td>
                                            <td>
                                                <a href="resepsiyon-checkin.php?id=<?= $rezervasyon['id'] ?>" class="btn btn-sm btn-primary">
                                                    Check-In
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
        </div>

        <!-- Bugünkü Check-Out'lar -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-sign-out-alt me-2"></i>Bugünkü Check-Out'lar (<?= count($bugun_checkout) ?>)
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($bugun_checkout)): ?>
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-calendar-times fa-3x mb-3"></i>
                            <p>Bugün check-out yapacak misafir bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Misafir</th>
                                        <th>Oda</th>
                                        <th>Saat</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bugun_checkout as $rezervasyon): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex py-1 align-items-center">
                                                    <div class="flex-fill">
                                                        <div class="font-weight-medium"><?= htmlspecialchars($rezervasyon['ad'] . ' ' . $rezervasyon['soyad']) ?></div>
                                                        <div class="text-muted"><?= htmlspecialchars($rezervasyon['telefon']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-blue"><?= htmlspecialchars($rezervasyon['oda_numarasi']) ?></span>
                                            </td>
                                            <td><?= date('H:i', strtotime($rezervasyon['cikis_tarihi'])) ?></td>
                                            <td>
                                                <a href="resepsiyon-checkout.php?id=<?= $rezervasyon['id'] ?>" class="btn btn-sm btn-success">
                                                    Check-Out
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
        </div>
    </div>

    <!-- Aktif Konaklamalar ve Bekleyen Rezervasyonlar -->
    <div class="row mt-4">
        <!-- Aktif Konaklamalar -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-users me-2"></i>Aktif Konaklamalar (<?= count($aktif_konaklamalar) ?>)
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($aktif_konaklamalar)): ?>
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-bed fa-3x mb-3"></i>
                            <p>Şu anda konaklayan misafir bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>Oda</th>
                                        <th>Misafir</th>
                                        <th>Check-In</th>
                                        <th>Check-Out</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($aktif_konaklamalar as $rezervasyon): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-success"><?= htmlspecialchars($rezervasyon['oda_numarasi']) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex py-1 align-items-center">
                                                    <div class="flex-fill">
                                                        <div class="font-weight-medium"><?= htmlspecialchars($rezervasyon['ad'] . ' ' . $rezervasyon['soyad']) ?></div>
                                                        <div class="text-muted"><?= htmlspecialchars($rezervasyon['telefon']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= date('d.m.Y H:i', strtotime($rezervasyon['giris_tarihi'])) ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($rezervasyon['cikis_tarihi'])) ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="resepsiyon-misafir-detay.php?id=<?= $rezervasyon['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="resepsiyon-checkout.php?id=<?= $rezervasyon['id'] ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-sign-out-alt"></i>
                                                    </a>
                                                </div>
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

        <!-- Bekleyen Rezervasyonlar -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock me-2"></i>Bekleyen Rezervasyonlar
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($bekleyen_rezervasyonlar)): ?>
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <p>Bekleyen rezervasyon bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($bekleyen_rezervasyonlar as $rezervasyon): ?>
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <span class="status-dot status-dot-animated bg-warning"></span>
                                        </div>
                                        <div class="col text-truncate">
                                            <strong><?= htmlspecialchars($rezervasyon['ad'] . ' ' . $rezervasyon['soyad']) ?></strong>
                                            <div class="text-muted"><?= htmlspecialchars($rezervasyon['telefon']) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <a href="rezervasyonlar.php?id=<?= $rezervasyon['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>