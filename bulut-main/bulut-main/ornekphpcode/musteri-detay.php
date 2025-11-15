<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';

// Fiyat formatı fonksiyonu
function formatPrice($price) {
    if (empty($price) || !is_numeric($price)) {
        return '0,00 ₺';
    }
    return number_format($price, 2, ',', '.') . ' ₺';
}

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('musteri_goruntule', 'Müşteri görüntüleme yetkiniz bulunmamaktadır.');

// ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: musteriler.php');
    exit;
}

$musteri_id = intval($_GET['id']);

// Müşteri bilgilerini getir
$musteri = fetchOne("SELECT * FROM musteriler WHERE id = ?", [$musteri_id]);

if (!$musteri) {
    header('Location: musteriler.php');
    exit;
}

// Müşterinin rezervasyonlarını getir
$rezervasyonlar = fetchAll("
    SELECT r.*, ot.oda_tipi_adi 
    FROM rezervasyonlar r
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    WHERE r.musteri_id = ?
    ORDER BY r.olusturma_tarihi DESC
", [$musteri_id]);

// Müşterinin iadelerini getir
$iadeler = fetchAll("
    SELECT ri.*, r.rezervasyon_kodu, r.erken_checkout
    FROM rezervasyon_iadeleri ri
    LEFT JOIN rezervasyonlar r ON ri.rezervasyon_id = r.id
    WHERE r.musteri_id = ?
    ORDER BY ri.olusturma_tarihi DESC
", [$musteri_id]);

// Müşteri istatistikleri
$istatistikler = fetchOne("
    SELECT 
        COUNT(*) as toplam_rezervasyon,
        SUM(CASE WHEN durum NOT IN ('iptal') THEN toplam_tutar ELSE 0 END) as toplam_harcama,
        SUM(CASE WHEN durum NOT IN ('iptal') THEN odenen_tutar ELSE 0 END) as toplam_odenen,
        MAX(olusturma_tarihi) as son_rezervasyon
    FROM rezervasyonlar 
    WHERE musteri_id = ?
", [$musteri_id]);

// İade istatistikleri
$iade_istatistikleri = fetchOne("
    SELECT 
        COUNT(*) as toplam_iade,
        SUM(CASE WHEN ri.durum = 'aktif' THEN ri.iade_tutari ELSE 0 END) as toplam_odenen_iade,
        SUM(CASE WHEN ri.durum = 'iptal' THEN ri.iade_tutari ELSE 0 END) as bekleyen_iade
    FROM rezervasyon_iadeleri ri
    LEFT JOIN rezervasyonlar r ON ri.rezervasyon_id = r.id
    WHERE r.musteri_id = ?
", [$musteri_id]);

// Müşterinin tüm ödemelerini rezervasyon bazlı getir
$odemeler = fetchAll("
    SELECT 
        ro.*,
        r.rezervasyon_kodu,
        r.giris_tarihi,
        r.cikis_tarihi,
        r.toplam_tutar as rezervasyon_tutari,
        ot.oda_tipi_adi,
        k.ad as kullanici_adi,
        k.soyad as kullanici_soyadi
    FROM rezervasyon_odemeleri ro
    LEFT JOIN rezervasyonlar r ON ro.rezervasyon_id = r.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN kullanicilar k ON ro.kullanici_id = k.id
    WHERE r.musteri_id = ?
    ORDER BY ro.odeme_tarihi DESC
", [$musteri_id]);

// Ödeme istatistikleri
$odeme_istatistikleri = fetchOne("
    SELECT 
        COUNT(*) as toplam_odeme,
        SUM(ro.odeme_tutari) as toplam_odenen,
        SUM(CASE WHEN ro.odeme_yontemi = 'nakit' THEN ro.odeme_tutari ELSE 0 END) as nakit_odeme,
        SUM(CASE WHEN ro.odeme_yontemi = 'kredi_karti' THEN ro.odeme_tutari ELSE 0 END) as kredi_karti_odeme,
        SUM(CASE WHEN ro.odeme_yontemi = 'havale' THEN ro.odeme_tutari ELSE 0 END) as havale_odeme
    FROM rezervasyon_odemeleri ro
    LEFT JOIN rezervasyonlar r ON ro.rezervasyon_id = r.id
    WHERE r.musteri_id = ?
", [$musteri_id]);

// Müşteri borç/alacak hesabı hesaplamaları
$borc_alacak_hesabi = fetchOne("
    SELECT 
        -- Toplam rezervasyon tutarları
        SUM(CASE WHEN r.durum NOT IN ('iptal') THEN r.toplam_tutar ELSE 0 END) as toplam_rezervasyon_tutari,
        SUM(CASE WHEN r.durum NOT IN ('iptal') THEN r.odenen_tutar ELSE 0 END) as toplam_odenen_tutar,
        
        -- Toplam iade tutarları
        SUM(CASE WHEN ri.durum = 'aktif' THEN ri.iade_tutari ELSE 0 END) as toplam_iade_tutari,
        
        -- Kalan borç hesaplama
        (SUM(CASE WHEN r.durum NOT IN ('iptal') THEN r.toplam_tutar ELSE 0 END) - 
         SUM(CASE WHEN r.durum NOT IN ('iptal') THEN r.odenen_tutar ELSE 0 END)) as kalan_borc,
        
        -- Net alacak hesaplama (iade - kalan borç)
        (SUM(CASE WHEN ri.durum = 'aktif' THEN ri.iade_tutari ELSE 0 END) - 
         (SUM(CASE WHEN r.durum NOT IN ('iptal') THEN r.toplam_tutar ELSE 0 END) - 
          SUM(CASE WHEN r.durum NOT IN ('iptal') THEN r.odenen_tutar ELSE 0 END))) as net_alacak
        
    FROM rezervasyonlar r
    LEFT JOIN rezervasyon_iadeleri ri ON r.id = ri.rezervasyon_id
    WHERE r.musteri_id = ?
", [$musteri_id]);

// Borç/alacak durumunu belirle
$borc_alacak_durumu = 'denge';
$borc_alacak_tutari = 0;

if ($borc_alacak_hesabi['net_alacak'] > 0) {
    $borc_alacak_durumu = 'alacak';
    $borc_alacak_tutari = $borc_alacak_hesabi['net_alacak'];
} elseif ($borc_alacak_hesabi['net_alacak'] < 0) {
    $borc_alacak_durumu = 'borc';
    $borc_alacak_tutari = abs($borc_alacak_hesabi['net_alacak']);
}

$page_title = 'Müşteri Detayı';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: #343a40;
            padding-top: 20px;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar .sidebar-header {
            padding: 20px;
            background: #2c3034;
            color: white;
            text-align: center;
            border-bottom: 1px solid #495057;
        }
        
        .sidebar .sidebar-header h4 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .sidebar .components {
            padding: 0;
            margin: 0;
        }
        
        .sidebar .components li {
            list-style: none;
        }
        
        .sidebar .components li a {
            display: block;
            padding: 12px 20px;
            color: #adb5bd;
            text-decoration: none;
            transition: all 0.3s;
            border-bottom: 1px solid #495057;
        }
        
        .sidebar .components li a:hover,
        .sidebar .components li.active > a {
            background: #495057;
            color: white;
        }
        
        .sidebar .components li a i {
            margin-right: 10px;
            width: 20px;
        }
        
        .sidebar .collapse li a {
            padding-left: 40px;
            background: #2c3034;
            font-size: 0.9rem;
        }
        
        .sidebar .collapse li a:hover,
        .sidebar .collapse li.active a {
            background: #495057;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .btn-outline-primary:hover {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .badge {
            font-size: 0.75em;
        }
        
        .text-primary { color: #0d6efd !important; }
        .text-success { color: #198754 !important; }
        .text-info { color: #0dcaf0 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-danger { color: #dc3545 !important; }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="musteri-duzenle.php?id=<?php echo $musteri['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i> Düzenle
                            </a>
                            <a href="musteriler.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Geri Dön
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Müşteri Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Ad Soyad:</strong></td>
                                        <td><?php echo htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>E-posta:</strong></td>
                                        <td><?php echo htmlspecialchars($musteri['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Telefon:</strong></td>
                                        <td><?php echo htmlspecialchars($musteri['telefon']); ?></td>
                                    </tr>
                                    <?php if (isset($musteri['tc_kimlik']) && $musteri['tc_kimlik']): ?>
                                    <tr>
                                        <td><strong>TC Kimlik:</strong></td>
                                        <td><?php echo htmlspecialchars($musteri['tc_kimlik']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong>Durum:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($musteri['durum'] ?? 'aktif') == 'aktif' ? 'success' : 'secondary'; ?>">
                                                <?php echo ($musteri['durum'] ?? 'aktif') == 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kayıt Tarihi:</strong></td>
                                        <td><?php echo formatTurkishDate($musteri['olusturma_tarihi'], 'd.m.Y H:i'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">İstatistikler</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <h4 class="text-primary"><?php echo $istatistikler['toplam_rezervasyon'] ?? 0; ?></h4>
                                        <small class="text-muted">Toplam Rezervasyon</small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <h4 class="text-success"><?php echo formatPrice($istatistikler['toplam_harcama'] ?? 0); ?></h4>
                                        <small class="text-muted">Toplam Harcama</small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <h4 class="text-info"><?php echo formatPrice($istatistikler['toplam_odenen'] ?? 0); ?></h4>
                                        <small class="text-muted">Toplam Ödenen</small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <h4 class="text-warning"><?php echo formatPrice($iade_istatistikleri['toplam_odenen_iade'] ?? 0); ?></h4>
                                        <small class="text-muted">Toplam İade Ödenen</small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <h4 class="text-danger"><?php echo formatPrice($iade_istatistikleri['bekleyen_iade'] ?? 0); ?></h4>
                                        <small class="text-muted">Bekleyen İade</small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <h4 class="text-primary"><?php echo $odeme_istatistikleri['toplam_odeme'] ?? 0; ?></h4>
                                        <small class="text-muted">Toplam Ödeme Sayısı</small>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <h4 class="text-success"><?php echo formatPrice($odeme_istatistikleri['toplam_odenen'] ?? 0); ?></h4>
                                        <small class="text-muted">Toplam Ödenen Tutar</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Borç/Alacak Hesabı -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calculator me-2"></i>Borç/Alacak Hesabı
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <h6 class="text-muted">Toplam Rezervasyon</h6>
                                        <h4 class="text-primary"><?php echo formatPrice($borc_alacak_hesabi['toplam_rezervasyon_tutari'] ?? 0); ?></h4>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h6 class="text-muted">Toplam Ödenen</h6>
                                        <h4 class="text-success"><?php echo formatPrice($borc_alacak_hesabi['toplam_odenen_tutar'] ?? 0); ?></h4>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h6 class="text-muted">Kalan Borç</h6>
                                        <h4 class="text-warning"><?php echo formatPrice($borc_alacak_hesabi['kalan_borc'] ?? 0); ?></h4>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h6 class="text-muted">Toplam İade</h6>
                                        <h4 class="text-info"><?php echo formatPrice($borc_alacak_hesabi['toplam_iade_tutari'] ?? 0); ?></h4>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="text-center">
                                    <?php if ($borc_alacak_durumu == 'alacak'): ?>
                                        <div class="alert alert-success">
                                            <h5 class="mb-1">
                                                <i class="fas fa-arrow-up me-2"></i>Müşteri Alacaklı
                                            </h5>
                                            <h3 class="mb-0"><?php echo formatPrice($borc_alacak_tutari); ?></h3>
                                            <small>Müşteriye ödenecek tutar</small>
                                        </div>
                                    <?php elseif ($borc_alacak_durumu == 'borc'): ?>
                                        <div class="alert alert-danger">
                                            <h5 class="mb-1">
                                                <i class="fas fa-arrow-down me-2"></i>Müşteri Borçlu
                                            </h5>
                                            <h3 class="mb-0"><?php echo formatPrice($borc_alacak_tutari); ?></h3>
                                            <small>Müşteriden alınacak tutar</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <h5 class="mb-1">
                                                <i class="fas fa-balance-scale me-2"></i>Hesap Dengeli
                                            </h5>
                                            <h3 class="mb-0">0,00 ₺</h3>
                                            <small>Borç ve alacak eşit</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Rezervasyon Geçmişi</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($rezervasyonlar)): ?>
                                    <p class="text-muted">Bu müşteriye ait rezervasyon bulunmamaktadır.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Rezervasyon Kodu</th>
                                                    <th>Oda Tipi</th>
                                                    <th>Tarihler</th>
                                                    <th>Tutar</th>
                                                    <th>Durum</th>
                                                    <th>İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rezervasyonlar as $rezervasyon): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></td>
                                                    <td><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></td>
                                                    <td>
                                                        <?php echo formatTurkishDate($rezervasyon['giris_tarihi'], 'd.m.Y'); ?> - 
                                                        <?php echo formatTurkishDate($rezervasyon['cikis_tarihi'], 'd.m.Y'); ?>
                                                    </td>
                                                    <td><?php echo formatPrice($rezervasyon['toplam_tutar']); ?></td>
                                                    <td>
                                                        <?php
                                                        $durum_class = [
                                                            'beklemede' => 'warning',
                                                            'onaylandi' => 'success',
                                                            'check_in' => 'info',
                                                            'check_out' => 'primary',
                                                            'iptal' => 'danger'
                                                        ];
                                                        $durum_text = [
                                                            'beklemede' => 'Beklemede',
                                                            'onaylandi' => 'Onaylandı',
                                                            'check_in' => 'Giriş Yapıldı',
                                                            'check_out' => 'Çıkış Yapıldı',
                                                            'iptal' => 'İptal'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-<?php echo $durum_class[$rezervasyon['durum']] ?? 'secondary'; ?>">
                                                            <?php echo $durum_text[$rezervasyon['durum']] ?? $rezervasyon['durum']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="rezervasyon-detay.php?id=<?php echo $rezervasyon['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Detay">
                                                            <i class="fas fa-eye"></i>
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

                <!-- Ödeme Geçmişi ve Folyo Kartı -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-credit-card me-2"></i>Ödeme Geçmişi ve Folyo Kartı
                                </h5>
                                <button class="btn btn-sm btn-outline-primary" onclick="showFolioModal()">
                                    <i class="fas fa-print me-1"></i>Folyo Kartı
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (empty($odemeler)): ?>
                                    <p class="text-muted">Bu müşteriye ait ödeme kaydı bulunmamaktadır.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped" id="odemeTablosu">
                                            <thead>
                                                <tr>
                                                    <th>Ödeme ID</th>
                                                    <th>Rezervasyon</th>
                                                    <th>Oda Tipi</th>
                                                    <th>Konaklama Tarihleri</th>
                                                    <th>Ödeme Tutarı</th>
                                                    <th>Ödeme Türü</th>
                                                    <th>Ödeme Tarihi</th>
                                                    <th>İşlem Yapan</th>
                                                    <th>İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $toplam_odenen = 0;
                                                foreach ($odemeler as $odeme): 
                                                    $toplam_odenen += $odeme['odeme_tutari'];
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong class="text-primary">#<?php echo $odeme['id']; ?></strong>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($odeme['rezervasyon_kodu']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            Rezervasyon Tutarı: <?php echo formatPrice($odeme['rezervasyon_tutari']); ?>
                                                        </small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($odeme['oda_tipi_adi'] ?? 'Belirtilmemiş'); ?></td>
                                                    <td>
                                                        <?php echo formatTurkishDate($odeme['giris_tarihi'], 'd.m.Y'); ?> - 
                                                        <?php echo formatTurkishDate($odeme['cikis_tarihi'], 'd.m.Y'); ?>
                                                    </td>
                                                    <td>
                                                        <strong class="text-success">
                                                            <?php echo formatPrice($odeme['odeme_tutari']); ?>
                                                        </strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $odeme_yontemi_class = [
                                                            'nakit' => 'success',
                                                            'kredi_karti' => 'primary',
                                                            'havale' => 'info',
                                                            'cek' => 'warning'
                                                        ];
                                                        $odeme_yontemi_text = [
                                                            'nakit' => 'Nakit',
                                                            'kredi_karti' => 'Kredi Kartı',
                                                            'havale' => 'Havale',
                                                            'cek' => 'Çek'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-<?php echo $odeme_yontemi_class[$odeme['odeme_yontemi']] ?? 'secondary'; ?>">
                                                            <?php echo $odeme_yontemi_text[$odeme['odeme_yontemi']] ?? ucfirst($odeme['odeme_yontemi']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatTurkishDate($odeme['odeme_tarihi'], 'd.m.Y H:i'); ?></td>
                                                    <td>
                                                        <?php if ($odeme['kullanici_adi']): ?>
                                                            <?php echo htmlspecialchars($odeme['kullanici_adi'] . ' ' . $odeme['kullanici_soyadi']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sistem</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" onclick="showPaymentDetails(<?php echo $odeme['id']; ?>)" title="Ödeme Detayları">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-primary">
                                                    <th colspan="4" class="text-end">TOPLAM ÖDENEN:</th>
                                                    <th class="text-success"><?php echo formatPrice($toplam_odenen); ?></th>
                                                    <th colspan="4"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- İade Geçmişi -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-money-bill-wave me-2"></i>İade Geçmişi
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($iadeler)): ?>
                                    <p class="text-muted">Bu müşteriye ait iade kaydı bulunmamaktadır.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>İade ID</th>
                                                    <th>Rezervasyon</th>
                                                    <th>İade Tutarı</th>
                                                    <th>Durum</th>
                                                    <th>Oluşturma Tarihi</th>
                                                    <th>İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($iadeler as $iade): ?>
                                                <tr>
                                                    <td><?php echo $iade['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($iade['rezervasyon_kodu']); ?></strong>
                                                        <?php if ($iade['erken_checkout']): ?>
                                                            <span class="badge bg-warning ms-2">
                                                                <i class="fas fa-clock me-1"></i>Erken Check-out
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong class="text-success">
                                                            <?php echo number_format($iade['iade_tutari'], 2); ?>₺
                                                        </strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $durum_class = [
                                                            'aktif' => 'success',
                                                            'iptal' => 'danger',
                                                            'beklemede' => 'warning',
                                                            'onaylandi' => 'info',
                                                            'odendi' => 'success'
                                                        ];
                                                        $durum_text = [
                                                            'aktif' => 'Aktif',
                                                            'iptal' => 'İptal',
                                                            'beklemede' => 'Beklemede',
                                                            'onaylandi' => 'Onaylandı',
                                                            'odendi' => 'Ödendi'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-<?php echo $durum_class[$iade['durum'] ?: 'aktif']; ?>">
                                                            <?php echo $durum_text[$iade['durum'] ?: 'aktif']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatTurkishDate($iade['olusturma_tarihi']); ?></td>
                                                    <td>
                                                        <a href="rezervasyon-iadeleri.php" class="btn btn-sm btn-outline-primary" title="İade Detayları">
                                                            <i class="fas fa-eye"></i>
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

                <!-- İade Ödemesi Girişi -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-credit-card me-2"></i>İade Ödemesi Girişi
                                </h5>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#iadeOdemeModal">
                                    <i class="fas fa-plus me-1"></i>Yeni İade Ödemesi
                                </button>
                            </div>
                            <div class="card-body">
                                <?php
                                // Müşterinin iade ödemelerini getir
                                $iade_odemeleri = fetchAll("
                                    SELECT 
                                        io.*,
                                        ri.rezervasyon_id,
                                        r.rezervasyon_kodu,
                                        k.ad as kullanici_adi,
                                        k.soyad as kullanici_soyadi
                                    FROM iade_odemeleri io
                                    LEFT JOIN rezervasyon_iadeleri ri ON io.iade_id = ri.id
                                    LEFT JOIN rezervasyonlar r ON ri.rezervasyon_id = r.id
                                    LEFT JOIN kullanicilar k ON io.kullanici_id = k.id
                                    WHERE r.musteri_id = ?
                                    ORDER BY io.odeme_tarihi DESC
                                ", [$musteri_id]);
                                ?>
                                
                                <?php if (empty($iade_odemeleri)): ?>
                                    <p class="text-muted">Bu müşteriye ait iade ödemesi kaydı bulunmamaktadır.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Ödeme ID</th>
                                                    <th>Rezervasyon</th>
                                                    <th>İade ID</th>
                                                    <th>Ödeme Tutarı</th>
                                                    <th>Ödeme Yöntemi</th>
                                                    <th>Ödeme Tarihi</th>
                                                    <th>İşlem Yapan</th>
                                                    <th>Durum</th>
                                                    <th>İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $toplam_iade_odenen = 0;
                                                foreach ($iade_odemeleri as $iade_odeme): 
                                                    $toplam_iade_odenen += $iade_odeme['odeme_tutari'];
                                                ?>
                                                <tr>
                                                    <td><?php echo $iade_odeme['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($iade_odeme['rezervasyon_kodu']); ?></strong>
                                                    </td>
                                                    <td><?php echo $iade_odeme['iade_id']; ?></td>
                                                    <td>
                                                        <strong class="text-success">
                                                            <?php echo formatPrice($iade_odeme['odeme_tutari']); ?>
                                                        </strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $odeme_yontemi_class = [
                                                            'nakit' => 'success',
                                                            'kredi_karti' => 'primary',
                                                            'havale' => 'info',
                                                            'cek' => 'warning'
                                                        ];
                                                        $odeme_yontemi_text = [
                                                            'nakit' => 'Nakit',
                                                            'kredi_karti' => 'Kredi Kartı',
                                                            'havale' => 'Havale',
                                                            'cek' => 'Çek'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-<?php echo $odeme_yontemi_class[$iade_odeme['odeme_yontemi']] ?? 'secondary'; ?>">
                                                            <?php echo $odeme_yontemi_text[$iade_odeme['odeme_yontemi']] ?? 'Bilinmiyor'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatTurkishDate($iade_odeme['odeme_tarihi']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($iade_odeme['kullanici_adi'] . ' ' . $iade_odeme['kullanici_soyadi']); ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $durum_class = [
                                                            'tamamlandi' => 'success',
                                                            'beklemede' => 'warning',
                                                            'iptal' => 'danger'
                                                        ];
                                                        $durum_text = [
                                                            'tamamlandi' => 'Tamamlandı',
                                                            'beklemede' => 'Beklemede',
                                                            'iptal' => 'İptal'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-<?php echo $durum_class[$iade_odeme['durum']] ?? 'secondary'; ?>">
                                                            <?php echo $durum_text[$iade_odeme['durum']] ?? 'Bilinmiyor'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" onclick="showIadeOdemeDetail(<?php echo $iade_odeme['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-success">
                                                    <th colspan="3">Toplam İade Ödenen:</th>
                                                    <th class="text-success"><?php echo formatPrice($toplam_iade_odenen); ?></th>
                                                    <th colspan="5"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Ödeme Detay Modal -->
    <div class="modal fade" id="paymentDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ödeme Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="paymentDetailContent">
                    <!-- Ödeme detayları buraya yüklenecek -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" onclick="printPaymentReceipt()">Makbuz Yazdır</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Folyo Kartı Modal -->
    <div class="modal fade" id="folioModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Müşteri Folyo Kartı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="folioContent">
                    <!-- Folyo kartı içeriği buraya yüklenecek -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" onclick="printFolio()">Yazdır</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sayfa yüklendiğinde çalışacak fonksiyon
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Müşteri detay sayfası yüklendi');
            
            // Bootstrap modal'larının yüklendiğini kontrol et
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap yüklenmedi!');
            } else {
                console.log('Bootstrap yüklendi');
            }
        });

        // Ödeme detaylarını göster
        function showPaymentDetails(paymentId) {
            try {
                console.log('Ödeme detayları gösteriliyor:', paymentId);
                
                // Modal'ı bul
                const modal = document.getElementById('paymentDetailModal');
                if (!modal) {
                    console.error('Payment detail modal bulunamadı');
                    alert('Modal bulunamadı');
                    return;
                }
                
                // Modal içeriğini güncelle - gerçek ödeme detaylarını göster
                const content = document.getElementById('paymentDetailContent');
                if (content) {
                    // Ödeme tablosundan ilgili satırı bul
                    const paymentRow = document.querySelector(`button[onclick="showPaymentDetails(${paymentId})"]`).closest('tr');
                    if (paymentRow) {
                        const cells = paymentRow.querySelectorAll('td');
                        const odemeId = cells[0]?.textContent?.trim() || 'N/A';
                        const rezervasyon = cells[1]?.textContent?.trim() || 'N/A';
                        const odaTipi = cells[2]?.textContent?.trim() || 'N/A';
                        const konaklamaTarihleri = cells[3]?.textContent?.trim() || 'N/A';
                        const odemeTutari = cells[4]?.textContent?.trim() || 'N/A';
                        const odemeTuru = cells[5]?.textContent?.trim() || 'N/A';
                        const odemeTarihi = cells[6]?.textContent?.trim() || 'N/A';
                        const islemYapan = cells[7]?.textContent?.trim() || 'N/A';
                        
                        content.innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Ödeme Bilgileri</h6>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Ödeme ID:</strong></td>
                                            <td>${odemeId}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Rezervasyon:</strong></td>
                                            <td>${rezervasyon}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Oda Tipi:</strong></td>
                                            <td>${odaTipi}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Konaklama Tarihleri:</strong></td>
                                            <td>${konaklamaTarihleri}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Ödeme Detayları</h6>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Ödeme Tutarı:</strong></td>
                                            <td><span class="text-success fw-bold">${odemeTutari}</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ödeme Türü:</strong></td>
                                            <td>${odemeTuru}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ödeme Tarihi:</strong></td>
                                            <td>${odemeTarihi}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>İşlem Yapan:</strong></td>
                                            <td>${islemYapan}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <h6><i class="fas fa-info-circle me-2"></i>Bilgi</h6>
                                <p class="mb-0">Bu ödeme kaydı sistem tarafından otomatik olarak oluşturulmuştur. Daha detaylı bilgi için rezervasyon detay sayfasını ziyaret edebilirsiniz.</p>
                            </div>
                        `;
                    } else {
                        content.innerHTML = `
                            <div class="alert alert-warning">
                                <h6>Ödeme Detayları</h6>
                                <p><strong>Ödeme ID:</strong> ${paymentId}</p>
                                <p><strong>Durum:</strong> Ödeme detayları bulunamadı</p>
                            </div>
                        `;
                    }
                }
                
                // Modal'ı göster
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
                
            } catch (error) {
                console.error('Ödeme detayları gösterilirken hata:', error);
                alert('Ödeme detayları gösterilirken hata oluştu: ' + error.message);
            }
        }

        // Folyo modal'ını göster
        function showFolioModal() {
            try {
                console.log('Folyo modal açılıyor');
                
                // Modal'ı bul
                const modal = document.getElementById('folioModal');
                if (!modal) {
                    console.error('Folio modal bulunamadı');
                    alert('Folyo modal bulunamadı');
                    return;
                }
                
                // İçeriği oluştur
                const folioContent = generateFolioContent();
                console.log('Folyo içeriği oluşturuldu');
                
                // Modal içeriğini güncelle
                const contentDiv = document.getElementById('folioContent');
                if (contentDiv) {
                    contentDiv.innerHTML = folioContent;
                    console.log('Folyo içeriği modal\'a yüklendi');
                } else {
                    console.error('Folio content div bulunamadı');
                }
                
                // Modal'ı göster
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
                console.log('Folyo modal açıldı');
                
            } catch (error) {
                console.error('Folyo modal açılırken hata:', error);
                alert('Folyo modal açılırken hata oluştu: ' + error.message);
            }
        }

        // Folyo kartını yazdır
        function printFolio() {
            try {
                console.log('Folyo yazdırılıyor...');
                
                const folioContent = generateFolioContent();
                const printWindow = window.open('', '_blank');
                
                if (!printWindow) {
                    alert('Pop-up engelleyici nedeniyle yazdırma penceresi açılamadı. Lütfen pop-up engelleyiciyi kapatın.');
                    return;
                }
                
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Müşteri Folyo Kartı</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
                            .customer-info { margin-bottom: 20px; }
                            .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                            .table th, .table td { border: 1px solid #000; padding: 8px; text-align: left; }
                            .table th { background-color: #f0f0f0; }
                            .total { font-weight: bold; background-color: #f0f0f0; }
                            .footer { margin-top: 30px; text-align: center; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        ${folioContent}
                    </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
                
                console.log('Folyo yazdırma penceresi açıldı');
                
            } catch (error) {
                console.error('Folyo yazdırılırken hata:', error);
                alert('Folyo yazdırılırken hata oluştu: ' + error.message);
            }
        }

        // Folyo kartı içeriğini oluştur
        function generateFolioContent() {
            try {
                console.log('Folyo içeriği oluşturuluyor...');
                
                const customerName = <?php echo json_encode($musteri['ad'] . ' ' . $musteri['soyad']); ?>;
                const customerEmail = <?php echo json_encode($musteri['email']); ?>;
                const customerPhone = <?php echo json_encode($musteri['telefon']); ?>;
                const printDate = new Date().toLocaleDateString('tr-TR');
                
                console.log('Müşteri bilgileri:', { customerName, customerEmail, customerPhone });
            
            let content = `
                <div class="text-center border-bottom pb-3 mb-4">
                    <h2 class="text-primary">OTEL REZERVASYON SİSTEMİ</h2>
                    <h4>MÜŞTERİ FOLYO KARTI</h4>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="text-primary">Müşteri Bilgileri</h5>
                        <table class="table table-borderless">
                            <tr><td><strong>Ad Soyad:</strong></td><td>${customerName}</td></tr>
                            <tr><td><strong>E-posta:</strong></td><td>${customerEmail}</td></tr>
                            <tr><td><strong>Telefon:</strong></td><td>${customerPhone}</td></tr>
                            <tr><td><strong>Yazdırma Tarihi:</strong></td><td>${printDate}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-primary">Özet Bilgiler</h5>
                        <table class="table table-borderless">
                            <tr><td><strong>Toplam Rezervasyon:</strong></td><td>${<?php echo json_encode($istatistikler['toplam_rezervasyon'] ?? 0); ?>}</td></tr>
                            <tr><td><strong>Toplam Harcama:</strong></td><td>${<?php echo json_encode(formatPrice($istatistikler['toplam_harcama'] ?? 0)); ?>}</td></tr>
                            <tr><td><strong>Toplam Ödenen:</strong></td><td>${<?php echo json_encode(formatPrice($odeme_istatistikleri['toplam_odenen'] ?? 0)); ?>}</td></tr>
                            <tr><td><strong>Toplam İade:</strong></td><td>${<?php echo json_encode(formatPrice($iade_istatistikleri['toplam_odenen_iade'] ?? 0)); ?>}</td></tr>
                        </table>
                    </div>
                </div>
                
                <h5 class="text-primary mb-3">Ödeme Geçmişi</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-primary">
                            <tr>
                                <th>Ödeme ID</th>
                                <th>Rezervasyon</th>
                                <th>Oda Tipi</th>
                                <th>Tarihler</th>
                                <th>Tutar</th>
                                <th>Ödeme Türü</th>
                                <th>Ödeme Tarihi</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            <?php if (!empty($odemeler)): ?>
                <?php foreach ($odemeler as $odeme): ?>
                    content += `
                        <tr>
                            <td><strong class="text-primary">#${<?php echo json_encode($odeme['id']); ?>}</strong></td>
                            <td><strong>${<?php echo json_encode($odeme['rezervasyon_kodu']); ?>}</strong></td>
                            <td>${<?php echo json_encode($odeme['oda_tipi_adi'] ?? 'Belirtilmemiş'); ?>}</td>
                            <td>${<?php echo json_encode(formatTurkishDate($odeme['giris_tarihi'], 'd.m.Y') . ' - ' . formatTurkishDate($odeme['cikis_tarihi'], 'd.m.Y')); ?>}</td>
                            <td><strong class="text-success">${<?php echo json_encode(formatPrice($odeme['odeme_tutari'])); ?>}</strong></td>
                            <td><span class="badge bg-primary">${<?php echo json_encode(ucfirst($odeme['odeme_yontemi'])); ?>}</span></td>
                            <td>${<?php echo json_encode(formatTurkishDate($odeme['odeme_tarihi'], 'd.m.Y H:i')); ?>}</td>
                        </tr>
                    `;
                <?php endforeach; ?>
            <?php else: ?>
                content += `<tr><td colspan="7" class="text-center text-muted">Ödeme kaydı bulunmamaktadır.</td></tr>`;
            <?php endif; ?>
            
            content += `
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th colspan="4" class="text-end">TOPLAM ÖDENEN:</th>
                                <th class="text-success">${<?php echo json_encode(formatPrice($toplam_odenen)); ?>}</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="text-center mt-4 pt-3 border-top">
                    <small class="text-muted">
                        Bu folyo kartı otomatik olarak oluşturulmuştur. | Yazdırma Tarihi: ${printDate}
                    </small>
                </div>
            `;
            
            console.log('Folyo içeriği başarıyla oluşturuldu');
            return content;
            
            } catch (error) {
                console.error('Folyo içeriği oluşturulurken hata:', error);
                return `
                    <div class="alert alert-danger">
                        <h5>Hata!</h5>
                        <p>Folyo kartı oluşturulurken bir hata oluştu: ${error.message}</p>
                        <p>Lütfen sayfayı yenileyin ve tekrar deneyin.</p>
                    </div>
                `;
            }
        }

        // Makbuz yazdır
        function printPaymentReceipt() {
            alert('Makbuz yazdırma özelliği yakında eklenecek');
        }

        // İade ödemesi detayını göster
        function showIadeOdemeDetail(iadeOdemeId) {
            alert('İade ödemesi detayı: ' + iadeOdemeId);
        }
    </script>

    <!-- İade Ödemesi Modal -->
    <div class="modal fade" id="iadeOdemeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-credit-card me-2"></i>Yeni İade Ödemesi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="iadeOdemeForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="iade_odeme_ekle">
                        <input type="hidden" name="musteri_id" value="<?php echo $musteri_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="iade_id" class="form-label">İade Seçin <span class="text-danger">*</span></label>
                                    <select class="form-select" id="iade_id" name="iade_id" required>
                                        <option value="">İade seçin...</option>
                                        <?php foreach ($iadeler as $iade): ?>
                                            <?php if ($iade['durum'] == 'aktif'): ?>
                                                <option value="<?php echo $iade['id']; ?>" 
                                                        data-tutar="<?php echo $iade['iade_tutari']; ?>"
                                                        data-rezervasyon="<?php echo htmlspecialchars($iade['rezervasyon_kodu']); ?>">
                                                    <?php echo htmlspecialchars($iade['rezervasyon_kodu']); ?> - 
                                                    <?php echo formatPrice($iade['iade_tutari']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="odeme_tutari" class="form-label">Ödeme Tutarı <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="odeme_tutari" name="odeme_tutari" 
                                           step="0.01" min="0.01" required>
                                    <small class="form-text text-muted">
                                        Kısmi ödeme yapabilirsiniz
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="odeme_yontemi" class="form-label">Ödeme Yöntemi <span class="text-danger">*</span></label>
                                    <select class="form-select" id="odeme_yontemi" name="odeme_yontemi" required>
                                        <option value="">Seçin...</option>
                                        <option value="nakit">Nakit</option>
                                        <option value="kredi_karti">Kredi Kartı</option>
                                        <option value="havale">Havale</option>
                                        <option value="cek">Çek</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="odeme_tarihi" class="form-label">Ödeme Tarihi</label>
                                    <input type="datetime-local" class="form-control" id="odeme_tarihi" name="odeme_tarihi" 
                                           value="<?php echo date('Y-m-d\TH:i'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3" 
                                      placeholder="İade ödemesi hakkında açıklama..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Bilgi</h6>
                            <ul class="mb-0">
                                <li>Kısmi ödeme yapabilirsiniz</li>
                                <li>Toplam ödeme tutarı iade tutarını geçemez</li>
                                <li>Ödeme tamamlandığında iade durumu otomatik güncellenir</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>İade Ödemesi Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // İade seçildiğinde tutarı otomatik doldur
        document.getElementById('iade_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const tutar = selectedOption.getAttribute('data-tutar');
            const rezervasyon = selectedOption.getAttribute('data-rezervasyon');
            
            if (tutar) {
                document.getElementById('odeme_tutari').value = tutar;
                document.getElementById('odeme_tutari').max = tutar;
            }
        });

        // Form gönderimi
        document.getElementById('iadeOdemeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/process-iade-odeme.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Response'u text olarak al
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('İade ödemesi başarıyla kaydedildi!');
                        location.reload();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response text:', text);
                    alert('Sunucudan geçersiz yanıt alındı. Lütfen sayfayı yenileyin.');
                }
            })
            .catch(error => {
                console.error('Network Error:', error);
                alert('Bir hata oluştu: ' + error.message);
            });
        });
    </script>
</body>
</html>