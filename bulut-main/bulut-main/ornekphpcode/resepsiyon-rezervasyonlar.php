<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';

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

// Rezervasyon silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    $sql = "DELETE FROM rezervasyonlar WHERE id = ?";
    if (executeQuery($sql, [$id])) {
        $success_message = 'Rezervasyon başarıyla silindi.';
    } else {
        $error_message = 'Rezervasyon silinirken hata oluştu.';
    }
}

// Oda değiştirme
if (isset($_POST['room_change']) && isset($_POST['rezervasyon_id']) && isset($_POST['new_room_number_id'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $new_room_number_id = intval($_POST['new_room_number_id']);
    
    // Mevcut rezervasyon bilgilerini al
    $rezervasyon = fetchOne("SELECT giris_tarihi, cikis_tarihi, oda_tipi_id, oda_numarasi_id FROM rezervasyonlar WHERE id = ?", [$rezervasyon_id]);
    
    if ($rezervasyon) {
        // Oda tipinin check-in ve check-out saatlerini al
        $oda_tipi_saatleri = fetchOne("SELECT checkin_saati, checkout_saati FROM oda_tipleri WHERE id = ?", [$rezervasyon['oda_tipi_id']]);
        $checkin_saati = $oda_tipi_saatleri['checkin_saati'] ?? '14:00:00';
        $checkout_saati = $oda_tipi_saatleri['checkout_saati'] ?? '12:00:00';
        
        // Yeni odanın müsait olup olmadığını kontrol et - Basit ve doğru saat bazlı kontrol
        $conflict_check = fetchOne("
            SELECT COUNT(*) as count 
            FROM rezervasyonlar r
            INNER JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
            WHERE r.oda_numarasi_id = ? 
            AND r.id != ? 
            AND r.durum NOT IN ('iptal', 'tamamlandi')
            AND (
                -- Çakışma kontrolü: Mevcut rezervasyon henüz çıkmamış VE yeni rezervasyon girmiş
                (r.cikis_tarihi > ? AND r.giris_tarihi < ?)
            )
            AND NOT (
                -- Check-out saati geçmişse oda müsait sayılır
                (r.durum = 'check_in' AND DATE(r.cikis_tarihi) = DATE(?) AND TIME(r.cikis_tarihi) <= ?) OR
                -- Check-in saati henüz gelmemişse oda müsait sayılır
                (r.durum = 'onaylandi' AND DATE(r.giris_tarihi) = DATE(?) AND TIME(r.giris_tarihi) > ?)
            )
        ", [
            $new_room_number_id, $rezervasyon_id,
            $rezervasyon['giris_tarihi'], $rezervasyon['cikis_tarihi'],  // Çakışma kontrolü
            $rezervasyon['giris_tarihi'], $checkout_saati,  // Check-out saati kontrolü
            $rezervasyon['giris_tarihi'], $checkin_saati   // Check-in saati kontrolü
        ]);
        
        if ($conflict_check['count'] == 0) {
            // Eski odayı temizle
            if ($rezervasyon['oda_numarasi_id']) {
                $old_room_sql = "UPDATE oda_numaralari SET durum = 'aktif' WHERE id = ?";
                executeQuery($old_room_sql, [$rezervasyon['oda_numarasi_id']]);
            }
            
            // Yeni odayı dolu yap
            $new_room_sql = "UPDATE oda_numaralari SET durum = 'dolu' WHERE id = ?";
            executeQuery($new_room_sql, [$new_room_number_id]);
            
            // Rezervasyonu güncelle
            $update_sql = "UPDATE rezervasyonlar SET oda_numarasi_id = ? WHERE id = ?";
            if (executeQuery($update_sql, [$new_room_number_id, $rezervasyon_id])) {
                $success_message = 'Oda başarıyla değiştirildi.';
            } else {
                $error_message = 'Oda değiştirilirken hata oluştu.';
            }
        } else {
            $error_message = 'Seçilen oda bu tarihlerde müsait değil.';
        }
    }
}

// Durum güncelleme
if (isset($_POST['durum_guncelle']) && isset($_POST['rezervasyon_id']) && isset($_POST['yeni_durum'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $yeni_durum = sanitizeString($_POST['yeni_durum']);
    
    // Geçerli durumlar
    $gecerli_durumlar = ['beklemede', 'onaylandi', 'check_in', 'check_out', 'iptal', 'tamamlandi'];
    
    if (in_array($yeni_durum, $gecerli_durumlar)) {
        $sql = "UPDATE rezervasyonlar SET durum = ?";
        
        // Check-out ise gercek_cikis_tarihi ekle
        if ($yeni_durum == 'check_out') {
            $sql .= ", gercek_cikis_tarihi = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        
        if (executeQuery($sql, [$yeni_durum, $rezervasyon_id])) {
            // Oda durumunu güncelle
            if ($yeni_durum == 'check_out') {
                $rezervasyon = fetchOne("SELECT oda_numarasi_id, durum FROM rezervasyonlar WHERE id = ?", [$rezervasyon_id]);
                
                // Check-out ise odayı temizlik bekliyor yap
                if ($rezervasyon && $rezervasyon['oda_numarasi_id']) {
                    $oda_sql = "UPDATE oda_numaralari SET durum = 'temizlik_bekliyor' WHERE id = ?";
                    executeQuery($oda_sql, [$rezervasyon['oda_numarasi_id']]);
                }
            }
            
            $success_message = 'Rezervasyon durumu başarıyla güncellendi.';
        } else {
            $error_message = 'Durum güncellenirken hata oluştu.';
        }
    } else {
        $error_message = 'Geçersiz durum.';
    }
}

// Filtreleme
$durum_filtre = $_GET['durum'] ?? '';
$musteri_filtre = $_GET['musteri'] ?? '';
$tarih_filtre = $_GET['tarih'] ?? '';

$where_conditions = [];
$params = [];

if ($durum_filtre) {
    $where_conditions[] = "r.durum = ?";
    $params[] = $durum_filtre;
}

if ($musteri_filtre) {
    $where_conditions[] = "(m.ad LIKE ? OR m.soyad LIKE ? OR m.email LIKE ? OR r.musteri_adi LIKE ? OR r.musteri_soyadi LIKE ?)";
    $params[] = "%$musteri_filtre%";
    $params[] = "%$musteri_filtre%";
    $params[] = "%$musteri_filtre%";
    $params[] = "%$musteri_filtre%";
    $params[] = "%$musteri_filtre%";
}

if ($tarih_filtre) {
    $where_conditions[] = "DATE(r.giris_tarihi) = ?";
    $params[] = $tarih_filtre;
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
$count_sql = "SELECT COUNT(*) as toplam FROM rezervasyonlar r 
              LEFT JOIN musteriler m ON r.musteri_id = m.id
              $where_clause";
$toplam_result = fetchOne($count_sql, $params);
$toplam_kayit = $toplam_result['toplam'];
$toplam_sayfa = ceil($toplam_kayit / $limit);

// Rezervasyonları getir
$sql = "SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi, 
               m.ad as musteri_adi, m.soyad as musteri_soyadi, m.email as musteri_email, m.telefon as musteri_telefon,
               COALESCE(s.ad, 'Web Site') as sales_ad, 
               COALESCE(s.soyad, '') as sales_soyad
        FROM rezervasyonlar r 
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
        LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id 
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        LEFT JOIN kullanicilar s ON r.satis_elemani_id = s.id AND s.rol IN ('sales', 'admin', 'superadmin', 'ekip')
        $where_clause 
        ORDER BY r.olusturma_tarihi DESC 
        LIMIT $limit OFFSET $offset";

$rezervasyonlar = fetchAll($sql, $params);

// İstatistikler
$beklemede = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'beklemede'");
$onaylandi = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'onaylandi'");
$check_in = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'check_in'");
$bugun_rezervasyonlar = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE DATE(olusturma_tarihi) = CURDATE()");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervasyonlar - Resepsiyon</title>
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
                        <i class="fas fa-calendar-check me-2"></i>Rezervasyonlar
                        <small class="text-muted">Resepsiyon</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="resepsiyon-hizli-rezervasyon.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i>Hızlı Rezervasyon
                            </a>
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
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Bekleyen Rezervasyonlar
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $beklemede['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                            Onaylanan Rezervasyonlar
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $onaylandi['sayi']; ?>
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
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Check-in Yapılan
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $check_in['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-key fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Bugünkü Rezervasyonlar
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $bugun_rezervasyonlar['sayi']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
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
                                <label for="durum" class="form-label">Durum</label>
                                <select class="form-select" id="durum" name="durum">
                                    <option value="">Tümü</option>
                                    <option value="beklemede" <?php echo $durum_filtre == 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                    <option value="onaylandi" <?php echo $durum_filtre == 'onaylandi' ? 'selected' : ''; ?>>Onaylandı</option>
                                    <option value="check_in" <?php echo $durum_filtre == 'check_in' ? 'selected' : ''; ?>>Check-in</option>
                                    <option value="check_out" <?php echo $durum_filtre == 'check_out' ? 'selected' : ''; ?>>Check-out</option>
                                    <option value="iptal" <?php echo $durum_filtre == 'iptal' ? 'selected' : ''; ?>>İptal</option>
                                    <option value="tamamlandi" <?php echo $durum_filtre == 'tamamlandi' ? 'selected' : ''; ?>>Tamamlandı</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="musteri" class="form-label">Müşteri</label>
                                <input type="text" class="form-control" id="musteri" name="musteri" 
                                       value="<?php echo htmlspecialchars($musteri_filtre); ?>" 
                                       placeholder="Ad, soyad veya e-posta">
                            </div>
                            <div class="col-md-3">
                                <label for="tarih" class="form-label">Giriş Tarihi</label>
                                <input type="date" class="form-control" id="tarih" name="tarih" 
                                       value="<?php echo htmlspecialchars($tarih_filtre); ?>">
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

                <!-- Rezervasyonlar Tablosu -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Rezervasyon Listesi
                            <span class="badge bg-secondary ms-2"><?php echo $toplam_kayit; ?> kayıt</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($rezervasyonlar)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Rezervasyon bulunamadı</h5>
                                <p class="text-muted">Belirtilen kriterlere uygun rezervasyon bulunamadı.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Rezervasyon Kodu</th>
                                            <th>Müşteri</th>
                                            <th>Oda</th>
                                            <th>Tarihler</th>
                                            <th>Durum</th>
                                            <th>Ödeme Durumu</th>
                                            <th>Toplam</th>
                                            <th>Satış Elemanı</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rezervasyonlar as $rezervasyon): ?>
                                        <tr>
                                            <td><?php echo $rezervasyon['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></strong>
                                                <?php if ($rezervasyon['erken_checkout']): ?>
                                                    <span class="badge bg-warning ms-2">
                                                        <i class="fas fa-clock me-1"></i>Erken Check-out
                                                    </span>
                                                <?php endif; ?>
                                                <br><small class="text-muted">
                                                    <?php echo formatTurkishDate($rezervasyon['olusturma_tarihi']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($rezervasyon['musteri_email']); ?></small>
                                                <?php if ($rezervasyon['musteri_telefon']): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($rezervasyon['musteri_telefon']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rezervasyon['oda_tipi_adi']): ?>
                                                    <strong><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></strong>
                                                    <?php if ($rezervasyon['oda_numarasi']): ?>
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-bed me-1"></i><?php echo htmlspecialchars($rezervasyon['oda_numarasi']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Oda atanmamış</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo formatTurkishDate($rezervasyon['giris_tarihi']); ?></strong>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-arrow-right me-1"></i>
                                                    <?php echo formatTurkishDate($rezervasyon['cikis_tarihi']); ?>
                                                </small>
                                                <?php
                                                $gece_sayisi = (strtotime($rezervasyon['cikis_tarihi']) - strtotime($rezervasyon['giris_tarihi'])) / (60 * 60 * 24);
                                                ?>
                                                <br><small class="badge bg-info"><?php echo $gece_sayisi; ?> gece</small>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <select name="yeni_durum" class="form-select form-select-sm" 
                                                            onchange="this.form.submit()" 
                                                            style="min-width: 120px;">
                                                        <option value="beklemede" <?php echo $rezervasyon['durum'] == 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                                        <option value="onaylandi" <?php echo $rezervasyon['durum'] == 'onaylandi' ? 'selected' : ''; ?>>Onaylandı</option>
                                                        <option value="check_in" <?php echo $rezervasyon['durum'] == 'check_in' ? 'selected' : ''; ?>>Check-in</option>
                                                        <option value="check_out" <?php echo $rezervasyon['durum'] == 'check_out' ? 'selected' : ''; ?>>Check-out</option>
                                                        <option value="iptal" <?php echo $rezervasyon['durum'] == 'iptal' ? 'selected' : ''; ?>>İptal</option>
                                                        <option value="tamamlandi" <?php echo $rezervasyon['durum'] == 'tamamlandi' ? 'selected' : ''; ?>>Tamamlandı</option>
                                                    </select>
                                                    <input type="hidden" name="rezervasyon_id" value="<?php echo $rezervasyon['id']; ?>">
                                                    <input type="hidden" name="durum_guncelle" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <?php
                                                $odeme_durumu_class = [
                                                    'odenmedi' => 'danger',
                                                    'kismi_odeme' => 'warning',
                                                    'odendi' => 'success'
                                                ];
                                                $odeme_durumu_text = [
                                                    'odenmedi' => 'Ödenmedi',
                                                    'kismi_odeme' => 'Kısmi Ödeme',
                                                    'odendi' => 'Ödendi'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $odeme_durumu_class[$rezervasyon['odeme_durumu']] ?? 'secondary'; ?>">
                                                    <?php echo $odeme_durumu_text[$rezervasyon['odeme_durumu']] ?? 'Bilinmiyor'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="text-success">
                                                    <?php echo number_format($rezervasyon['toplam_fiyat'] ?? 0, 2) . '₺'; ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?php if ($rezervasyon['sales_ad']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-<?php echo $rezervasyon['sales_ad'] == 'Web Site' ? 'globe' : 'user-tie'; ?> text-<?php echo $rezervasyon['sales_ad'] == 'Web Site' ? 'info' : 'success'; ?> me-2"></i>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($rezervasyon['sales_ad'] . ($rezervasyon['sales_soyad'] ? ' ' . $rezervasyon['sales_soyad'] : '')); ?></strong>
                                                            <br><small class="text-muted"><?php echo $rezervasyon['sales_ad'] == 'Web Site' ? 'Web Sitesi' : 'Atanmış'; ?></small>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Atanmamış</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="rezervasyon-detay.php?id=<?php echo $rezervasyon['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Detay">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                                            title="Oda Ata/Değiştir"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#roomAssignModal<?php echo $rezervasyon['id']; ?>">
                                                        <i class="fas fa-bed"></i>
                                                    </button>
                                                    <a href="resepsiyon-hizli-rezervasyon.php?duzenle=<?php echo $rezervasyon['id']; ?>" 
                                                       class="btn btn-sm btn-outline-secondary" title="Düzenle">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?sil=<?php echo $rezervasyon['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" title="Sil"
                                                       onclick="return confirm('Bu rezervasyonu silmek istediğinizden emin misiniz?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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
                                        <a class="page-link" href="?page=<?php echo $i; ?>&durum=<?php echo urlencode($durum_filtre); ?>&musteri=<?php echo urlencode($musteri_filtre); ?>&tarih=<?php echo urlencode($tarih_filtre); ?>">
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

    <!-- Oda Ata/Değiştir Modal'ları -->
    <?php foreach ($rezervasyonlar as $rezervasyon): ?>
    <div class="modal fade" id="roomAssignModal<?php echo $rezervasyon['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-bed me-2"></i>Oda Ata/Değiştir
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="room_change" value="1">
                        <input type="hidden" name="rezervasyon_id" value="<?php echo $rezervasyon['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Rezervasyon Bilgileri</label>
                            <div class="alert alert-info">
                                <strong><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></strong><br>
                                <small>
                                    <?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?><br>
                                    <?php echo formatTurkishDate($rezervasyon['giris_tarihi']); ?> - <?php echo formatTurkishDate($rezervasyon['cikis_tarihi']); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_room_number_id_<?php echo $rezervasyon['id']; ?>" class="form-label">Oda Seçin</label>
                            <select class="form-select" id="new_room_number_id_<?php echo $rezervasyon['id']; ?>" name="new_room_number_id" required>
                                <option value="">Oda Seçin</option>
                                <?php
                                // Müsait odaları getir
                                $musait_odalar = fetchAll("
                                    SELECT odn.*, ot.oda_tipi_adi 
                                    FROM oda_numaralari odn
                                    LEFT JOIN oda_tipleri ot ON odn.oda_tipi_id = ot.id
                                    WHERE odn.durum IN ('aktif', 'temiz') 
                                    AND ot.id = ?
                                    ORDER BY odn.oda_numarasi ASC
                                ", [$rezervasyon['oda_tipi_id']]);
                                
                                foreach ($musait_odalar as $oda):
                                    // Bu odanın bu tarihlerde müsait olup olmadığını kontrol et - Basit ve doğru saat bazlı kontrol
                                    $conflict_check = fetchOne("
                                        SELECT COUNT(*) as count 
                                        FROM rezervasyonlar r
                                        INNER JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
                                        WHERE r.oda_numarasi_id = ? 
                                        AND r.id != ? 
                                        AND r.durum NOT IN ('iptal', 'tamamlandi')
                                        AND (
                                            -- Çakışma kontrolü: Mevcut rezervasyon henüz çıkmamış VE yeni rezervasyon girmiş
                                            (r.cikis_tarihi > ? AND r.giris_tarihi < ?)
                                        )
                                        AND NOT (
                                            -- Check-out saati geçmişse oda müsait sayılır
                                            (r.durum = 'check_in' AND DATE(r.cikis_tarihi) = DATE(?) AND TIME(r.cikis_tarihi) <= ?) OR
                                            -- Check-in saati henüz gelmemişse oda müsait sayılır
                                            (r.durum = 'onaylandi' AND DATE(r.giris_tarihi) = DATE(?) AND TIME(r.giris_tarihi) > ?)
                                        )
                                    ", [
                                        $oda['id'], $rezervasyon['id'],
                                        $rezervasyon['giris_tarihi'], $rezervasyon['cikis_tarihi'],  // Çakışma kontrolü
                                        $rezervasyon['giris_tarihi'], $checkout_saati,  // Check-out saati kontrolü
                                        $rezervasyon['giris_tarihi'], $checkin_saati   // Check-in saati kontrolü
                                    ]);
                                    
                                    $selected = ($oda['id'] == $rezervasyon['oda_numarasi_id']) ? 'selected' : '';
                                    $disabled = ($conflict_check['count'] > 0 && $oda['id'] != $rezervasyon['oda_numarasi_id']) ? 'disabled' : '';
                                ?>
                                <option value="<?php echo $oda['id']; ?>" <?php echo $selected . ' ' . $disabled; ?>>
                                    <?php echo htmlspecialchars($oda['oda_numarasi']); ?> 
                                    (<?php echo htmlspecialchars($oda['oda_tipi_adi']); ?>)
                                    <?php if ($oda['id'] == $rezervasyon['oda_numarasi_id']): ?>
                                        - Mevcut Oda
                                    <?php elseif ($conflict_check['count'] > 0): ?>
                                        - Müsait Değil
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($rezervasyon['oda_numarasi']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            Mevcut oda: <strong><?php echo htmlspecialchars($rezervasyon['oda_numarasi']); ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Oda Ata
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
