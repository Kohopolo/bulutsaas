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

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Tarih parametreleri
$tarih = $_GET['tarih'] ?? date('Y-m-d');
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-d');
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-d');

// Günlük doluluk raporu
$doluluk_raporu = fetchOne("
    SELECT 
        COUNT(DISTINCT od.id) as toplam_oda,
        COUNT(DISTINCT CASE WHEN r.durum IN ('check_in', 'onaylandi') 
                           AND ? BETWEEN DATE(r.giris_tarihi) AND DATE(r.cikis_tarihi) 
                           THEN r.oda_numarasi_id END) as dolu_oda,
        ROUND((COUNT(DISTINCT CASE WHEN r.durum IN ('check_in', 'onaylandi') 
                                  AND ? BETWEEN DATE(r.giris_tarihi) AND DATE(r.cikis_tarihi) 
                                  THEN r.oda_numarasi_id END) * 100.0 / COUNT(DISTINCT od.id)), 2) as doluluk_orani
    FROM oda_numaralari od
    LEFT JOIN rezervasyonlar r ON od.id = r.oda_numarasi_id
", [$tarih, $tarih]);

// Günlük gelir raporu
$gelir_raporu = fetchOne("
    SELECT 
        COALESCE(SUM(CASE WHEN o.odeme_tarihi = ? THEN o.miktar END), 0) as gunluk_gelir,
        COALESCE(SUM(CASE WHEN DATE(o.odeme_tarihi) BETWEEN ? AND ? THEN o.miktar END), 0) as donem_gelir,
        COUNT(CASE WHEN o.odeme_tarihi = ? THEN o.id END) as gunluk_odeme_sayisi
    FROM odemeler o
    WHERE o.durum = 'basarili'
", [$tarih, $baslangic_tarihi, $bitis_tarihi, $tarih]);

// Günlük check-in listesi
$gunluk_checkin = fetchAll("
    SELECT r.id, r.giris_tarihi, r.durum,
           m.ad, m.soyad, m.telefon,
           od.oda_numarasi, ot.oda_tipi_adi, ot.base_price,
           r.yetiskin_sayisi, r.cocuk_sayisi,
           COALESCE(SUM(o.miktar), 0) as odenen_tutar,
           r.toplam_fiyat
    FROM rezervasyonlar r
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN odemeler o ON r.id = o.rezervasyon_id AND o.durum = 'basarili'
    WHERE DATE(r.giris_tarihi) = ?
    GROUP BY r.id
    ORDER BY r.giris_tarihi
", [$tarih]);

// Günlük check-out listesi
$gunluk_checkout = fetchAll("
    SELECT r.id, r.cikis_tarihi, r.durum,
           m.ad, m.soyad, m.telefon,
           od.oda_numarasi, ot.oda_tipi_adi, ot.base_price,
           r.yetiskin_sayisi, r.cocuk_sayisi,
           COALESCE(SUM(o.miktar), 0) as odenen_tutar,
           r.toplam_fiyat,
           COALESCE(SUM(es.toplam_fiyat), 0) as ekstra_servis_tutari
    FROM rezervasyonlar r
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN odemeler o ON r.id = o.rezervasyon_id AND o.durum = 'basarili'
    LEFT JOIN ekstra_servisler es ON r.id = es.rezervasyon_id
    WHERE DATE(r.cikis_tarihi) = ?
    GROUP BY r.id
    ORDER BY r.cikis_tarihi
", [$tarih]);

// Oda tipi bazında doluluk
$oda_tipi_doluluk = fetchAll("
    SELECT ot.oda_tipi_adi,
           COUNT(DISTINCT od.id) as toplam_oda,
           COUNT(DISTINCT CASE WHEN r.durum IN ('check_in', 'onaylandi') 
                              AND ? BETWEEN DATE(r.giris_tarihi) AND DATE(r.cikis_tarihi) 
                              THEN r.oda_numarasi_id END) as dolu_oda,
           ROUND((COUNT(DISTINCT CASE WHEN r.durum IN ('check_in', 'onaylandi') 
                                     AND ? BETWEEN DATE(r.giris_tarihi) AND DATE(r.cikis_tarihi) 
                                     THEN r.oda_numarasi_id END) * 100.0 / COUNT(DISTINCT od.id)), 2) as doluluk_orani
    FROM oda_tipleri ot
    LEFT JOIN oda_numaralari od ON ot.id = od.oda_tipi_id
    LEFT JOIN rezervasyonlar r ON od.id = r.oda_numarasi_id
    GROUP BY ot.id, ot.oda_tipi_adi
    ORDER BY ot.oda_tipi_adi
", [$tarih, $tarih]);

// Ödeme yöntemi bazında gelir
$odeme_yontemi_gelir = fetchAll("
    SELECT o.odeme_yontemi,
           COUNT(*) as islem_sayisi,
           SUM(o.miktar) as toplam_tutar
    FROM odemeler o
    WHERE DATE(o.odeme_tarihi) = ? AND o.durum = 'basarili'
    GROUP BY o.odeme_yontemi
    ORDER BY toplam_tutar DESC
", [$tarih]);

// Ekstra servisler raporu
$ekstra_servisler = fetchAll("
    SELECT es.servis_turu,
           COUNT(*) as adet,
           SUM(es.toplam_fiyat) as toplam_tutar
    FROM ekstra_servisler es
    WHERE DATE(es.servis_tarihi) = ?
    GROUP BY es.servis_turu
    ORDER BY toplam_tutar DESC
", [$tarih]);

$page_title = 'Günlük Raporlar';
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
                                <i class="fas fa-chart-bar me-2"></i>Günlük Raporlar
                            </h2>
                        </div>
                        <div class="col-auto ms-auto d-print-none">
                            <div class="btn-list">
                                <button class="btn btn-outline-primary" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i>Yazdır
                                </button>
                                <a href="resepsiyon.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Geri Dön
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarih Seçimi -->
    <div class="row mb-4 d-print-none">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Rapor Tarihi</label>
                            <input type="date" name="tarih" class="form-control" value="<?= $tarih ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Başlangıç Tarihi (Gelir)</label>
                            <input type="date" name="baslangic_tarihi" class="form-control" value="<?= $baslangic_tarihi ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bitiş Tarihi (Gelir)</label>
                            <input type="date" name="bitis_tarihi" class="form-control" value="<?= $bitis_tarihi ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">
                                <i class="fas fa-search me-1"></i>Rapor Getir
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Özet Kartlar -->
    <div class="row mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar">
                                <i class="fas fa-bed"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                Doluluk Oranı
                            </div>
                            <div class="text-muted">
                                %<?= $doluluk_raporu['doluluk_orani'] ?? 0 ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-success text-white avatar">
                                <i class="fas fa-lira-sign"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                Günlük Gelir
                            </div>
                            <div class="text-muted">
                                <?= number_format($gelir_raporu['gunluk_gelir'] ?? 0, 2) ?> TL
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-info text-white avatar">
                                <i class="fas fa-sign-in-alt"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                Check-In
                            </div>
                            <div class="text-muted">
                                <?= count($gunluk_checkin) ?> Rezervasyon
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-warning text-white avatar">
                                <i class="fas fa-sign-out-alt"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium">
                                Check-Out
                            </div>
                            <div class="text-muted">
                                <?= count($gunluk_checkout) ?> Rezervasyon
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Doluluk Raporu -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-pie me-2"></i>Oda Tipi Bazında Doluluk
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead>
                                <tr>
                                    <th>Oda Tipi</th>
                                    <th>Toplam</th>
                                    <th>Dolu</th>
                                    <th>Doluluk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($oda_tipi_doluluk as $tip): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tip['oda_tipi_adi']) ?></td>
                                        <td><?= $tip['toplam_oda'] ?></td>
                                        <td><?= $tip['dolu_oda'] ?></td>
                                        <td>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar" style="width: <?= $tip['doluluk_orani'] ?>%" role="progressbar">
                                                    <span class="visually-hidden"><?= $tip['doluluk_orani'] ?>%</span>
                                                </div>
                                            </div>
                                            <small class="text-muted">%<?= $tip['doluluk_orani'] ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gelir Raporu -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-money-bill-wave me-2"></i>Ödeme Yöntemi Bazında Gelir
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($odeme_yontemi_gelir)): ?>
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <p class="empty-title">Bugün ödeme yok</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Ödeme Yöntemi</th>
                                        <th>İşlem Sayısı</th>
                                        <th>Toplam Tutar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($odeme_yontemi_gelir as $odeme): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= ucfirst(str_replace('_', ' ', $odeme['odeme_yontemi'])) ?>
                                                </span>
                                            </td>
                                            <td><?= $odeme['islem_sayisi'] ?></td>
                                            <td><strong><?= number_format($odeme['toplam_tutar'] ?? 0, 2) ?> TL</strong></td>
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

    <!-- Ekstra Servisler -->
    <?php if (!empty($ekstra_servisler)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-concierge-bell me-2"></i>Ekstra Servisler
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead>
                                <tr>
                                    <th>Servis Türü</th>
                                    <th>Adet</th>
                                    <th>Toplam Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ekstra_servisler as $servis): ?>
                                    <tr>
                                        <td><?= ucfirst(str_replace('_', ' ', $servis['servis_turu'])) ?></td>
                                        <td><?= $servis['adet'] ?></td>
                                        <td><strong><?= number_format($servis['toplam_tutar'] ?? 0, 2) ?> TL</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Check-In Listesi -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-sign-in-alt me-2"></i>Günlük Check-In Listesi (<?= date('d.m.Y', strtotime($tarih)) ?>)
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($gunluk_checkin)): ?>
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <p class="empty-title">Bugün check-in yok</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Rezervasyon</th>
                                        <th>Misafir</th>
                                        <th>Oda</th>
                                        <th>Kişi Sayısı</th>
                                        <th>Tutar</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gunluk_checkin as $checkin): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold">#<?= $checkin['id'] ?></span>
                                                    <small class="text-muted"><?= date('H:i', strtotime($checkin['giris_tarihi'])) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span><?= htmlspecialchars($checkin['ad'] . ' ' . $checkin['soyad']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($checkin['telefon']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold"><?= $checkin['oda_numarasi'] ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($checkin['oda_tipi_adi']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= $checkin['yetiskin_sayisi'] ?>Y + <?= $checkin['cocuk_sayisi'] ?>Ç
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold"><?= number_format($checkin['toplam_fiyat'] ?? 0, 2) ?> TL</span>
                                                    <small class="text-success">Ödenen: <?= number_format($checkin['odenen_tutar'] ?? 0, 2) ?> TL</small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $durum_class = match($checkin['durum']) {
                                                    'check_in' => 'bg-success',
                                                    'onaylandi' => 'bg-warning',
                                                    'beklemede' => 'bg-secondary',
                                                    default => 'bg-primary'
                                                };
                                                $durum_text = match($checkin['durum']) {
                                                    'check_in' => 'Check-In',
                                                    'onaylandi' => 'Onaylandı',
                                                    'beklemede' => 'Beklemede',
                                                    default => ucfirst($checkin['durum'])
                                                };
                                                ?>
                                                <span class="badge <?= $durum_class ?>"><?= $durum_text ?></span>
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

    <!-- Check-Out Listesi -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-sign-out-alt me-2"></i>Günlük Check-Out Listesi (<?= date('d.m.Y', strtotime($tarih)) ?>)
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($gunluk_checkout)): ?>
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="fas fa-sign-out-alt"></i>
                            </div>
                            <p class="empty-title">Bugün check-out yok</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Rezervasyon</th>
                                        <th>Misafir</th>
                                        <th>Oda</th>
                                        <th>Kişi Sayısı</th>
                                        <th>Tutar</th>
                                        <th>Ekstra</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gunluk_checkout as $checkout): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold">#<?= $checkout['id'] ?></span>
                                                    <small class="text-muted"><?= date('H:i', strtotime($checkout['cikis_tarihi'])) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span><?= htmlspecialchars($checkout['ad'] . ' ' . $checkout['soyad']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($checkout['telefon']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold"><?= $checkout['oda_numarasi'] ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($checkout['oda_tipi_adi']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= $checkout['yetiskin_sayisi'] ?>Y + <?= $checkout['cocuk_sayisi'] ?>Ç
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold"><?= number_format($checkout['toplam_fiyat'] ?? 0, 2) ?> TL</span>
                                                    <small class="text-success">Ödenen: <?= number_format($checkout['odenen_tutar'] ?? 0, 2) ?> TL</small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($checkout['ekstra_servis_tutari'] > 0): ?>
                                                    <span class="text-warning fw-bold">
                                                        +<?= number_format($checkout['ekstra_servis_tutari'] ?? 0, 2) ?> TL
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $durum_class = match($checkout['durum']) {
                                                    'check_out' => 'bg-danger',
                                                    'check_in' => 'bg-success',
                                                    'onaylandi' => 'bg-warning',
                                                    default => 'bg-primary'
                                                };
                                                $durum_text = match($checkout['durum']) {
                                                    'check_out' => 'Check-Out',
                                                    'check_in' => 'Check-In',
                                                    'onaylandi' => 'Onaylandı',
                                                    default => ucfirst($checkout['durum'])
                                                };
                                                ?>
                                                <span class="badge <?= $durum_class ?>"><?= $durum_text ?></span>
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

    <!-- Gelir Özeti -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line me-2"></i>Gelir Özeti
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card card-sm bg-primary text-white">
                                <div class="card-body text-center">
                                    <div class="h1 m-0"><?= number_format($gelir_raporu['gunluk_gelir'] ?? 0, 2) ?> TL</div>
                                    <div class="text-white-50">Günlük Gelir</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-sm bg-success text-white">
                                <div class="card-body text-center">
                                    <div class="h1 m-0"><?= number_format($gelir_raporu['donem_gelir'] ?? 0, 2) ?> TL</div>
                                    <div class="text-white-50">Dönem Geliri</div>
                                    <small>(<?= date('d.m.Y', strtotime($baslangic_tarihi)) ?> - <?= date('d.m.Y', strtotime($bitis_tarihi)) ?>)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-sm bg-info text-white">
                                <div class="card-body text-center">
                                    <div class="h1 m-0"><?= $gelir_raporu['gunluk_odeme_sayisi'] ?? 0 ?></div>
                                    <div class="text-white-50">Günlük İşlem Sayısı</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .d-print-none {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        page-break-inside: avoid;
    }
    
    .table {
        font-size: 12px;
    }
    
    .badge {
        border: 1px solid #000;
        color: #000 !important;
        background-color: transparent !important;
    }
}
</style>

<?php include 'footer.php'; ?>