<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü - Resepsiyon yetkisi
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Resepsiyon yetkisi kontrolü
$allowed_roles = ['resepsiyon', 'admin', 'superadmin', 'ekip'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header('Location: index.php');
    exit;
}

$success_message = '';
$error_message = '';

// URL'den gelen success mesajını kontrol et
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Oda durumu güncelleme
if (isset($_POST['oda_durum_guncelle']) && isset($_POST['oda_id']) && isset($_POST['yeni_durum'])) {
    $oda_id = intval($_POST['oda_id']);
    $yeni_durum = sanitizeString($_POST['yeni_durum']);
    
    // Geçerli durumlar
    $gecerli_durumlar = ['aktif', 'dolu', 'kirli', 'temizlik_bekliyor', 'bakimda', 'devre_disi', 'temiz', 'bakim', 'pasif'];
    
    if (in_array($yeni_durum, $gecerli_durumlar)) {
        // Oda dolu mu kontrol et
        $oda_kontrol = fetchOne("SELECT odn.*, r.id as rezervasyon_id, r.durum as rezervasyon_durum
                                 FROM oda_numaralari odn 
                                 LEFT JOIN rezervasyonlar r ON odn.id = r.oda_numarasi_id 
                                 AND r.durum IN ('onaylandi', 'check_in', 'check_out')
                                 AND CURDATE() BETWEEN DATE(r.giris_tarihi) AND DATE(r.cikis_tarihi)
                                 WHERE odn.id = ?", [$oda_id]);
        
        // Eğer oda dolu ise durum güncellemesine izin verme
        if (!empty($oda_kontrol['rezervasyon_id'])) {
            $error_message = "Dolu odaların durumu değiştirilemez. Önce rezervasyonu iptal edin veya check-out yapın.";
        } else {
            $sql = "UPDATE oda_numaralari SET durum = ? WHERE id = ?";
            
            if (executeQuery($sql, [$yeni_durum, $oda_id])) {
                $success_message = 'Oda durumu başarıyla güncellendi.';
            } else {
                $error_message = 'Oda durumu güncellenirken hata oluştu.';
            }
        }
    } else {
        $error_message = 'Geçersiz durum.';
    }
}

// Filtreleme
$durum_filtre = $_GET['durum'] ?? '';
$kat_filtre = $_GET['kat'] ?? '';
$oda_tipi_filtre = $_GET['oda_tipi'] ?? '';

$where_conditions = [];
$params = [];

if ($durum_filtre) {
    $where_conditions[] = "odn.durum = ?";
    $params[] = $durum_filtre;
}

if ($kat_filtre) {
    $where_conditions[] = "odn.kat = ?";
    $params[] = $kat_filtre;
}

if ($oda_tipi_filtre) {
    $where_conditions[] = "ot.id = ?";
    $params[] = $oda_tipi_filtre;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Sayfalama
$limit = 20;
$page = intval($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// Toplam kayıt sayısı
$count_sql = "SELECT COUNT(*) as toplam FROM oda_numaralari odn 
              LEFT JOIN oda_tipleri ot ON odn.oda_tipi_id = ot.id
              $where_clause";
$toplam_result = fetchOne($count_sql, $params);
$toplam_kayit = $toplam_result['toplam'];
$toplam_sayfa = ceil($toplam_kayit / $limit);

// Odaları getir - gelişmiş durum kontrolü
$sql = "SELECT odn.*, ot.oda_tipi_adi, ot.aciklama as oda_tipi_aciklama,
               r.id as rezervasyon_id, r.rezervasyon_kodu, r.durum as rezervasyon_durum,
               r.giris_tarihi, r.cikis_tarihi, r.musteri_adi, r.musteri_soyadi, r.musteri_telefon,
               CASE 
                   -- Öncelik 1: Aktif rezervasyon (check-in yapılmış)
                   WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                                 AND CURDATE() >= DATE(r.giris_tarihi) 
                                 AND CURDATE() < DATE(r.cikis_tarihi) 
                                 THEN 1 END) > 0 THEN 'dolu'
                   
                   -- Öncelik 2: Checkout saati öncesi dolu (bugün checkout olacak)
                   WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                                 AND CURDATE() = DATE(r.cikis_tarihi)
                                 AND TIME(NOW()) < TIME(r.cikis_tarihi)
                                 THEN 1 END) > 0 THEN 'checkout_oncesi_dolu'
                   
                   -- Öncelik 3: Rezerve (onaylanmış ama henüz check-in yapılmamış)
                   WHEN COUNT(CASE WHEN r.durum = 'onaylandi' 
                                 AND CURDATE() < DATE(r.cikis_tarihi)
                                 THEN 1 END) > 0 THEN 'rezerve'
                   
                   -- Öncelik 4: Temizlik bekliyor (checkout yapılmış ama oda hala aktif)
                   WHEN COUNT(CASE WHEN r.durum = 'check_out' 
                                 AND r.gercek_cikis_tarihi IS NOT NULL
                                 AND odn.durum = 'aktif'
                                 THEN 1 END) > 0 THEN 'temizlik_bekliyor'
                   
                   -- Varsayılan: Oda durumu
                   ELSE odn.durum
               END as final_durum,
               CASE 
                   WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                                 AND CURDATE() >= DATE(r.giris_tarihi) 
                                 AND CURDATE() < DATE(r.cikis_tarihi) 
                                 THEN 1 END) > 0 THEN 'danger'
                   
                   WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                                 AND CURDATE() = DATE(r.cikis_tarihi)
                                 AND TIME(NOW()) < TIME(r.cikis_tarihi)
                                 THEN 1 END) > 0 THEN 'info'
                   
                   WHEN COUNT(CASE WHEN r.durum = 'onaylandi' 
                                 AND CURDATE() < DATE(r.cikis_tarihi)
                                 THEN 1 END) > 0 THEN 'warning'
                   
                   WHEN COUNT(CASE WHEN r.durum = 'check_out' 
                                 AND r.gercek_cikis_tarihi IS NOT NULL
                                 AND odn.durum = 'aktif'
                                 THEN 1 END) > 0 THEN 'secondary'
                   
                   WHEN odn.durum = 'dolu' THEN 'danger'
                   WHEN odn.durum = 'aktif' THEN 'success'
                   WHEN odn.durum = 'kirli' THEN 'warning'
                   WHEN odn.durum = 'bakimda' THEN 'info'
                   WHEN odn.durum = 'temizlik_bekliyor' THEN 'secondary'
                   WHEN odn.durum = 'devre_disi' THEN 'dark'
                   WHEN odn.durum = 'bakim' THEN 'info'
                   WHEN odn.durum = '' OR odn.durum IS NULL THEN 'secondary'
                   ELSE 'primary'
               END as durum_renk,
               CASE 
                   WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                                 AND CURDATE() = DATE(r.cikis_tarihi)
                                 AND TIME(NOW()) < TIME(r.cikis_tarihi)
                                 THEN 1 END) > 0 THEN 'Checkout saati yaklaşıyor'
                   
                   WHEN COUNT(CASE WHEN r.durum = 'onaylandi' 
                                 AND CURDATE() < DATE(r.cikis_tarihi)
                                 THEN 1 END) > 0 
                        AND COUNT(CASE WHEN r.durum = 'check_out' 
                                     AND r.gercek_cikis_tarihi IS NOT NULL
                                     THEN 1 END) > 0 THEN 'Rezerve - Temizlik bekliyor'
                   
                   WHEN COUNT(CASE WHEN r.durum = 'check_out' 
                                 AND r.gercek_cikis_tarihi IS NOT NULL
                                 AND odn.durum = 'aktif'
                                 THEN 1 END) > 0 THEN 'Temizlik bekliyor'
                   
                   ELSE NULL
               END as uyari_mesaji
        FROM oda_numaralari odn 
        LEFT JOIN oda_tipleri ot ON odn.oda_tipi_id = ot.id
        LEFT JOIN rezervasyonlar r ON odn.id = r.oda_numarasi_id 
            AND r.durum IN ('onaylandi', 'check_in', 'check_out')
        $where_clause 
        GROUP BY odn.id, odn.oda_numarasi, odn.oda_tipi_id, odn.durum, ot.oda_tipi_adi, ot.aciklama
        ORDER BY odn.kat ASC, odn.oda_numarasi ASC
        LIMIT $limit OFFSET $offset";

$odalar = fetchAll($sql, $params);

// İstatistikler
$dolu_odalar = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE durum = 'dolu'");
$aktif_odalar = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE durum = 'aktif'");
$temizlik_bekleyen = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE durum = 'temizlik_bekliyor'");
$toplam_oda = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari");

// Kat listesi
$katlar = fetchAll("SELECT DISTINCT kat FROM oda_numaralari WHERE kat IS NOT NULL ORDER BY kat ASC");

// Oda tipi listesi
$oda_tipleri = fetchAll("SELECT id, oda_tipi_adi FROM oda_tipleri ORDER BY oda_tipi_adi ASC");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odalar - Resepsiyon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-bed me-2"></i>Odalar
                        <small class="text-muted">Resepsiyon</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Yazdır
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Toplam Oda
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $toplam_oda['sayi']; ?>
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
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Aktif Odalar
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $aktif_odalar['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                            Dolu Odalar
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $dolu_odalar['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user fa-2x text-gray-300"></i>
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
                                            Temizlik Bekleyen
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $temizlik_bekleyen['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-broom fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-filter me-2"></i>Filtreler
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="durum" class="form-label">Oda Durumu</label>
                                <select class="form-select" id="durum" name="durum">
                                    <option value="">Tümü</option>
                                    <option value="aktif" <?php echo $durum_filtre == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="dolu" <?php echo $durum_filtre == 'dolu' ? 'selected' : ''; ?>>Dolu</option>
                                    <option value="kirli" <?php echo $durum_filtre == 'kirli' ? 'selected' : ''; ?>>Kirli</option>
                                    <option value="temizlik_bekliyor" <?php echo $durum_filtre == 'temizlik_bekliyor' ? 'selected' : ''; ?>>Temizlik Bekliyor</option>
                                    <option value="bakimda" <?php echo $durum_filtre == 'bakimda' ? 'selected' : ''; ?>>Bakımda</option>
                                    <option value="devre_disi" <?php echo $durum_filtre == 'devre_disi' ? 'selected' : ''; ?>>Devre Dışı</option>
                                    <option value="temiz" <?php echo $durum_filtre == 'temiz' ? 'selected' : ''; ?>>Temiz</option>
                                    <option value="bakim" <?php echo $durum_filtre == 'bakim' ? 'selected' : ''; ?>>Bakım</option>
                                    <option value="pasif" <?php echo $durum_filtre == 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="kat" class="form-label">Kat</label>
                                <select class="form-select" id="kat" name="kat">
                                    <option value="">Tümü</option>
                                    <?php foreach ($katlar as $kat): ?>
                                    <option value="<?php echo $kat['kat']; ?>" <?php echo $kat_filtre == $kat['kat'] ? 'selected' : ''; ?>>
                                        <?php echo $kat['kat']; ?>. Kat
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="oda_tipi" class="form-label">Oda Tipi</label>
                                <select class="form-select" id="oda_tipi" name="oda_tipi">
                                    <option value="">Tümü</option>
                                    <?php foreach ($oda_tipleri as $tip): ?>
                                    <option value="<?php echo $tip['id']; ?>" <?php echo $oda_tipi_filtre == $tip['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tip['oda_tipi_adi']); ?>
                                    </option>
                                    <?php endforeach; ?>
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

                <!-- Odalar Tablosu -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Oda Listesi
                            <span class="badge bg-secondary ms-2"><?php echo $toplam_kayit; ?> kayıt</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($odalar)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Oda bulunamadı</h5>
                                <p class="text-muted">Belirtilen kriterlere uygun oda bulunamadı.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Oda No</th>
                                            <th>Kat</th>
                                            <th>Oda Tipi</th>
                                            <th>Durum</th>
                                            <th>Rezervasyon Bilgileri</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($odalar as $oda): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($oda['oda_numarasi']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $oda['kat']; ?>. Kat</span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($oda['oda_tipi_adi']); ?></strong>
                                                <?php if ($oda['oda_tipi_aciklama']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($oda['oda_tipi_aciklama']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <?php 
                                                    // Gelişmiş durum kontrolü
                                                    $gosterilecek_durum = $oda['final_durum'] ?? $oda['durum'];
                                                    $durum_renk = $oda['durum_renk'] ?? 'secondary';
                                                    $uyari_mesaji = $oda['uyari_mesaji'] ?? '';
                                                    
                                                    // Durum değiştirilebilir mi kontrol et
                                                    $durum_degistirilebilir = true;
                                                    if (in_array($gosterilecek_durum, ['dolu', 'rezerve', 'checkout_oncesi_dolu'])) {
                                                        $durum_degistirilebilir = false;
                                                    }
                                                    ?>
                                                    
                                                    <!-- Durum Badge -->
                                                    <div class="mb-2">
                                                        <span class="badge bg-<?= $durum_renk ?> fs-6">
                                                            <?php 
                                                            switch($gosterilecek_durum) {
                                                                case 'dolu':
                                                                    echo '<i class="fas fa-times-circle me-1"></i>DOLU';
                                                                    break;
                                                                case 'rezerve':
                                                                    echo '<i class="fas fa-clock me-1"></i>REZERVE';
                                                                    break;
                                                                case 'checkout_oncesi_dolu':
                                                                    echo '<i class="fas fa-hourglass-half me-1"></i>CHECKOUT BEKLİYOR';
                                                                    break;
                                                                case 'temizlik_bekliyor':
                                                                    echo '<i class="fas fa-broom me-1"></i>TEMİZLİK BEKLİYOR';
                                                                    break;
                                                                case 'aktif':
                                                                    echo '<i class="fas fa-check-circle me-1"></i>AKTİF';
                                                                    break;
                                                                case 'kirli':
                                                                    echo '<i class="fas fa-exclamation-triangle me-1"></i>KİRLİ';
                                                                    break;
                                                                case 'bakimda':
                                                                    echo '<i class="fas fa-tools me-1"></i>BAKIMDA';
                                                                    break;
                                                                case 'devre_disi':
                                                                    echo '<i class="fas fa-ban me-1"></i>DEVRE DIŞI';
                                                                    break;
                                                                default:
                                                                    echo '<i class="fas fa-question-circle me-1"></i>' . strtoupper($gosterilecek_durum);
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Uyarı Mesajı -->
                                                    <?php if ($uyari_mesaji): ?>
                                                        <div class="alert alert-warning p-2 mb-2">
                                                            <small><i class="fas fa-exclamation-triangle me-1"></i><?= $uyari_mesaji ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Durum Değiştirme Dropdown -->
                                                    <select name="yeni_durum" class="form-select form-select-sm" 
                                                            onchange="this.form.submit()" 
                                                            style="min-width: 140px;"
                                                            <?php echo !$durum_degistirilebilir ? 'disabled' : ''; ?>>
                                                        <option value="aktif" <?php echo $gosterilecek_durum == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                                        <option value="dolu" <?php echo $gosterilecek_durum == 'dolu' ? 'selected' : ''; ?>>Dolu</option>
                                                        <option value="kirli" <?php echo $gosterilecek_durum == 'kirli' ? 'selected' : ''; ?>>Kirli</option>
                                                        <option value="temizlik_bekliyor" <?php echo $gosterilecek_durum == 'temizlik_bekliyor' ? 'selected' : ''; ?>>Temizlik Bekliyor</option>
                                                        <option value="bakimda" <?php echo $gosterilecek_durum == 'bakimda' ? 'selected' : ''; ?>>Bakımda</option>
                                                        <option value="devre_disi" <?php echo $gosterilecek_durum == 'devre_disi' ? 'selected' : ''; ?>>Devre Dışı</option>
                                                        <option value="temiz" <?php echo $gosterilecek_durum == 'temiz' ? 'selected' : ''; ?>>Temiz</option>
                                                        <option value="bakim" <?php echo $gosterilecek_durum == 'bakim' ? 'selected' : ''; ?>>Bakım</option>
                                                        <option value="pasif" <?php echo $gosterilecek_durum == 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                                                    </select>
                                                    <input type="hidden" name="oda_id" value="<?php echo $oda['id']; ?>">
                                                    <input type="hidden" name="oda_durum_guncelle" value="1">
                                                    
                                                    <?php if (!$durum_degistirilebilir): ?>
                                                        <small class="text-muted d-block mt-1">
                                                            <i class="fas fa-info-circle"></i> Rezervasyonlu oda - Durum değiştirilemez
                                                        </small>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td>
                                                <?php if ($oda['rezervasyon_id']): ?>
                                                    <div class="alert alert-info p-2 mb-0">
                                                        <strong><?php echo htmlspecialchars($oda['rezervasyon_kodu']); ?></strong><br>
                                                        <small>
                                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($oda['musteri_adi'] . ' ' . $oda['musteri_soyadi']); ?><br>
                                                            <i class="fas fa-calendar me-1"></i><?php echo formatTurkishDate($oda['giris_tarihi']); ?> - <?php echo formatTurkishDate($oda['cikis_tarihi']); ?><br>
                                                            <?php if ($oda['musteri_telefon']): ?>
                                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($oda['musteri_telefon']); ?><br>
                                                            <?php endif; ?>
                                                            <span class="badge bg-<?php 
                                                                switch($oda['rezervasyon_durum']) {
                                                                    case 'check_in':
                                                                        echo 'success';
                                                                        break;
                                                                    case 'onaylandi':
                                                                        echo 'warning';
                                                                        break;
                                                                    case 'check_out':
                                                                        echo 'secondary';
                                                                        break;
                                                                    default:
                                                                        echo 'info';
                                                                }
                                                            ?>">
                                                                <?php 
                                                                switch($oda['rezervasyon_durum']) {
                                                                    case 'check_in':
                                                                        echo '<i class="fas fa-check-circle me-1"></i>Check-in';
                                                                        break;
                                                                    case 'onaylandi':
                                                                        echo '<i class="fas fa-clock me-1"></i>Rezerve';
                                                                        break;
                                                                    case 'check_out':
                                                                        echo '<i class="fas fa-sign-out-alt me-1"></i>Check-out';
                                                                        break;
                                                                    default:
                                                                        echo ucfirst($oda['rezervasyon_durum']);
                                                                }
                                                                ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-bed me-1"></i>Boş
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($oda['rezervasyon_id']): ?>
                                                        <a href="rezervasyon-detay.php?id=<?php echo $oda['rezervasyon_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Rezervasyon Detayı">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="resepsiyon-rezervasyonlar.php" 
                                                           class="btn btn-sm btn-outline-info" title="Rezervasyon Listesi">
                                                            <i class="fas fa-list"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="resepsiyon-hizli-rezervasyon.php" 
                                                           class="btn btn-sm btn-outline-success" title="Yeni Rezervasyon">
                                                            <i class="fas fa-plus"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Sayfalama -->
                            <?php if ($toplam_sayfa > 1): ?>
                            <nav aria-label="Sayfa navigasyonu">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $toplam_sayfa; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&durum=<?php echo urlencode($durum_filtre); ?>&kat=<?php echo urlencode($kat_filtre); ?>&oda_tipi=<?php echo urlencode($oda_tipi_filtre); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
