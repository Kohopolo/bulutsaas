<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/room-price-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';

// Oda durumu güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['oda_durum_guncelle'])) {
    $oda_id = intval($_POST['oda_id']);
    $yeni_durum = $_POST['yeni_durum'];
    $aciklama = trim($_POST['aciklama']);
    
    // Eğer temiz seçilirse, aktif olarak kaydet
    if ($yeni_durum == 'temiz') {
        $yeni_durum = 'aktif';
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mevcut oda durumunu al
        $mevcut_oda = fetchOne("SELECT durum FROM oda_numaralari WHERE id = ?", [$oda_id]);
        $eski_durum = $mevcut_oda['durum'];
        
        // Eğer oda bakımdaysa ve yeni durum temiz(aktif) ise, devam eden bakımları tamamla
        if ($eski_durum == 'bakimda' && $yeni_durum == 'aktif') {
            // Devam eden bakımları tamamla
            $sql = "UPDATE oda_bakim SET durum = 'tamamlandi', bitis_tarihi = NOW(), tamamlama_notu = ? 
                    WHERE oda_id = ? AND durum = 'devam_ediyor'";
            executeQuery($sql, ['Otomatik tamamlandı - Oda temizlendi', $oda_id]);
        }
        
        // Oda durumunu güncelle
        $sql = "UPDATE oda_numaralari SET durum = ? WHERE id = ?";
        if (!executeQuery($sql, [$yeni_durum, $oda_id])) {
            throw new Exception('Oda durumu güncellenirken hata oluştu.');
        }
        
        // Oda geçmişi kaydı
        $sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        executeQuery($sql, [$oda_id, $eski_durum, $yeni_durum, $aciklama, $_SESSION['user_id']]);
        
        $pdo->commit();
        $success_message = 'Oda durumu başarıyla güncellendi.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Oda durumu güncellenirken hata oluştu: ' . $e->getMessage();
    }
}

// Temizlik işlemi - sadece housekeeper ve housekeeper_manager yetkili
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['temizlik_tamamla'])) {
    // Yetki kontrolü
    if (!isset($_SESSION['user_role']) || 
        ($_SESSION['user_role'] != 'housekeeper' && $_SESSION['user_role'] != 'housekeeper_manager')) {
        $error_message = 'Bu işlem için yetkiniz bulunmamaktadır. Sadece temizlik personeli bu işlemi yapabilir.';
    } else {
        $oda_id = intval($_POST['oda_id']);
        $temizlik_notu = trim($_POST['temizlik_notu']);
    
    try {
        $pdo->beginTransaction();
        
        // Odanın oda_tipi_id'sini al
        $oda_info = fetchOne("SELECT oda_tipi_id FROM oda_numaralari WHERE id = ?", [$oda_id]);
        if (!$oda_info) {
            throw new Exception('Oda bilgisi bulunamadı.');
        }
        
        // Oda tipinin check-in saatini al
        $oda_tipi = fetchOne("SELECT checkin_saati FROM oda_tipleri WHERE id = ?", [$oda_info['oda_tipi_id']]);
        $checkin_saati = $oda_tipi['checkin_saati'] ?? '14:00';
        
        // Bugün için bu odada aktif rezervasyon var mı kontrol et
        $bugun = date('Y-m-d');
        $checkin_datetime = $bugun . ' ' . $checkin_saati;
        
        $rezervasyon_var = fetchOne("
            SELECT COUNT(*) as count 
            FROM rezervasyonlar 
            WHERE oda_numarasi_id = ? 
            AND durum IN ('onaylandi', 'check_in') 
            AND giris_tarihi <= ? 
            AND cikis_tarihi > ?
        ", [$oda_id, $bugun, $bugun]);
        
        // Eğer aktif rezervasyon varsa oda durumunu 'dolu' yap, yoksa 'aktif' yap
        $yeni_durum = ($rezervasyon_var['count'] > 0) ? 'dolu' : 'aktif';
        
        // Oda durumunu güncelle
        $sql = "UPDATE oda_numaralari SET durum = ? WHERE id = ?";
        if (!executeQuery($sql, [$yeni_durum, $oda_id])) {
            throw new Exception('Temizlik işlemi kaydedilirken hata oluştu.');
        }
        
        // Temizlik kaydı
        $sql = "INSERT INTO oda_temizlik (oda_id, temizlik_tarihi, temizlik_notu, admin_id) 
                VALUES (?, NOW(), ?, ?)";
        executeQuery($sql, [$oda_id, $temizlik_notu, $_SESSION['user_id']]);
        
        // Oda geçmişi kaydı
        $sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                SELECT ?, durum, ?, ?, ?, NOW() FROM oda_numaralari WHERE id = ?";
        $aciklama = 'Temizlik tamamlandı: ' . $temizlik_notu . ($yeni_durum == 'dolu' ? ' (Aktif rezervasyon nedeniyle dolu olarak işaretlendi)' : '');
        executeQuery($sql, [$oda_id, $yeni_durum, $aciklama, $_SESSION['user_id'], $oda_id]);
        
        $pdo->commit();
        $success_message = 'Temizlik işlemi başarıyla tamamlandı. Oda durumu: ' . ($yeni_durum == 'dolu' ? 'Dolu (Aktif rezervasyon var)' : 'Aktif');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Temizlik işlemi kaydedilirken hata oluştu: ' . $e->getMessage();
    }
    } // Yetki kontrolü kapanış
}

// Bakım işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bakim_baslat'])) {
    $oda_id = intval($_POST['oda_id']);
    $bakim_turu = $_POST['bakim_turu'];
    $bakim_aciklamasi = trim($_POST['bakim_aciklamasi']);
    $tahmini_sure = intval($_POST['tahmini_sure']);
    
    try {
        $pdo->beginTransaction();
        
        // Oda durumunu bakımda yap
        $sql = "UPDATE oda_numaralari SET durum = 'bakimda' WHERE id = ?";
        if (!executeQuery($sql, [$oda_id])) {
            throw new Exception('Bakım durumu güncellenirken hata oluştu.');
        }
        
        // Bakım kaydı
        $sql = "INSERT INTO oda_bakim (oda_id, bakim_turu, bakim_aciklamasi, baslama_tarihi, tahmini_bitis_tarihi, durum, admin_id) 
                VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR), 'devam_ediyor', ?)";
        executeQuery($sql, [$oda_id, $bakim_turu, $bakim_aciklamasi, $tahmini_sure, $_SESSION['user_id']]);
        
        // Oda geçmişi kaydı
        $sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                SELECT ?, durum, 'bakimda', ?, ?, NOW() FROM oda_numaralari WHERE id = ?";
        executeQuery($sql, [$oda_id, 'Bakım başlatıldı: ' . $bakim_turu . ' - ' . $bakim_aciklamasi, $_SESSION['user_id'], $oda_id]);
        
        $pdo->commit();
        $success_message = 'Bakım işlemi başarıyla başlatıldı.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Bakım işlemi başlatılırken hata oluştu: ' . $e->getMessage();
    }
}

// Bakım tamamlama
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bakim_tamamla'])) {
    $bakim_id = intval($_POST['bakim_id']);
    $tamamlama_notu = trim($_POST['tamamlama_notu']);
    
    try {
        $pdo->beginTransaction();
        
        // Bakım kaydını güncelle
        $sql = "UPDATE oda_bakim SET durum = 'tamamlandi', bitis_tarihi = NOW(), tamamlama_notu = ? WHERE id = ?";
        if (!executeQuery($sql, [$tamamlama_notu, $bakim_id])) {
            throw new Exception('Bakım tamamlama kaydedilirken hata oluştu.');
        }
        
        // Oda durumunu temizlik bekliyor yap
        $sql = "UPDATE oda_numaralari SET durum = 'temizlik_bekliyor' 
                WHERE id = (SELECT oda_id FROM oda_bakim WHERE id = ?)";
        executeQuery($sql, [$bakim_id]);
        
        // Oda geçmişi kaydı
        $sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                SELECT oda_id, 'bakimda', 'temizlik_bekliyor', ?, ?, NOW() FROM oda_bakim WHERE id = ?";
        executeQuery($sql, ['Bakım tamamlandı: ' . $tamamlama_notu, $_SESSION['user_id'], $bakim_id]);
        
        $pdo->commit();
        $success_message = 'Bakım işlemi başarıyla tamamlandı.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Bakım tamamlama kaydedilirken hata oluştu: ' . $e->getMessage();
    }
}

// Tüm odaları getir ve gelişmiş rezervasyon durumlarını kontrol et
$odalar = fetchAll("
    SELECT od.*, ot.oda_tipi_adi, ot.base_price, ot.fiyatlama_sistemi,
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
                             AND od.durum = 'aktif'
                             THEN 1 END) > 0 THEN 'temizlik_bekliyor'
               
               -- Varsayılan: Oda durumu
               ELSE od.durum
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
                             AND od.durum = 'aktif'
                             THEN 1 END) > 0 THEN 'secondary'
               
               WHEN od.durum = 'dolu' THEN 'danger'
               WHEN od.durum = 'aktif' THEN 'success'
               WHEN od.durum = 'kirli' THEN 'warning'
               WHEN od.durum = 'bakimda' THEN 'info'
               WHEN od.durum = 'temizlik_bekliyor' THEN 'secondary'
               WHEN od.durum = 'devre_disi' THEN 'dark'
               WHEN od.durum = 'bakim' THEN 'info'
               WHEN od.durum = '' OR od.durum IS NULL THEN 'secondary'
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
                             AND od.durum = 'aktif'
                             THEN 1 END) > 0 THEN 'Temizlik bekliyor'
               
               ELSE NULL
           END as uyari_mesaji
    FROM oda_numaralari od
    LEFT JOIN oda_tipleri ot ON od.oda_tipi_id = ot.id
    LEFT JOIN rezervasyonlar r ON od.id = r.oda_numarasi_id 
        AND r.durum IN ('onaylandi', 'check_in', 'check_out')
    GROUP BY od.id, od.oda_numarasi, od.oda_tipi_id, od.durum, ot.oda_tipi_adi, ot.base_price, ot.fiyatlama_sistemi
    ORDER BY od.oda_numarasi
");

// Her oda için dinamik fiyat hesapla
foreach ($odalar as $index => $oda) {
    if ($oda['oda_tipi_id']) {
        $fiyat_bilgisi = getOdaYonetimiGuncelFiyat($oda['oda_tipi_id']);
        $odalar[$index]['guncel_fiyat'] = $fiyat_bilgisi['fiyat'];
        $odalar[$index]['fiyat_tipi'] = $fiyat_bilgisi['fiyat_tipi'];
        $odalar[$index]['fiyat_aciklama'] = $fiyat_bilgisi['aciklama'];
        $odalar[$index]['kampanya_aciklama'] = $fiyat_bilgisi['kampanya_aciklama'];
        $odalar[$index]['indirim_orani'] = $fiyat_bilgisi['indirim_orani'];
        $odalar[$index]['original_fiyat'] = $fiyat_bilgisi['original_fiyat'];
    } else {
        $odalar[$index]['guncel_fiyat'] = 0;
        $odalar[$index]['fiyat_tipi'] = 'Hata';
        $odalar[$index]['fiyat_aciklama'] = 'Oda tipi tanımlı değil';
        $odalar[$index]['kampanya_aciklama'] = null;
        $odalar[$index]['indirim_orani'] = 0;
        $odalar[$index]['original_fiyat'] = 0;
    }
}

// Devam eden bakımlar
$devam_eden_bakimlar = fetchAll("
    SELECT ob.*, od.oda_numarasi, ot.oda_tipi_adi
    FROM oda_bakim ob
    LEFT JOIN oda_numaralari od ON ob.oda_id = od.id
    LEFT JOIN oda_tipleri ot ON od.oda_tipi_id = ot.id
    WHERE ob.durum = 'devam_ediyor'
    ORDER BY ob.baslama_tarihi DESC
");

// Oda tiplerini al (filtre için)
$oda_tipleri = fetchAll("SELECT id, oda_tipi_adi FROM oda_tipleri ORDER BY oda_tipi_adi");

// Oda durumu istatistikleri - rezervasyonları da dikkate al
$durum_stats = fetchAll("
    SELECT 
        CASE 
            WHEN r.id IS NOT NULL THEN 'dolu'
            ELSE od.durum
        END as durum, 
        COUNT(*) as sayi
    FROM oda_numaralari od
    LEFT JOIN rezervasyonlar r ON od.id = r.oda_numarasi_id 
        AND r.durum IN ('onaylandi', 'check_in')
        AND CURDATE() >= DATE(r.giris_tarihi) 
        AND CURDATE() < DATE(r.cikis_tarihi)
    GROUP BY 
        CASE 
            WHEN r.id IS NOT NULL THEN 'dolu'
            ELSE od.durum
        END
");

$page_title = 'Oda Yönetimi';
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
                                <i class="fas fa-bed me-2"></i>Oda Yönetimi
                            </h2>
                        </div>
                        <div class="col-auto ms-auto d-print-none">
                            <div class="btn-list">
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

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible" role="alert">
            <div class="d-flex">
                <div><i class="fas fa-check-circle me-2"></i></div>
                <div><?= htmlspecialchars($success_message) ?></div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible" role="alert">
            <div class="d-flex">
                <div><i class="fas fa-exclamation-circle me-2"></i></div>
                <div><?= htmlspecialchars($error_message) ?></div>
            </div>
            <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
    <?php endif; ?>

    <!-- Oda Durumu İstatistikleri -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Oda Durumu İstatistikleri</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($durum_stats as $stat): ?>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="card card-sm status-card" style="cursor: pointer;" onclick="filterByStatus('<?= $stat['durum'] ?>')">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <span class="bg-<?= 
                                                    $stat['durum'] == 'dolu' ? 'danger' : 
                                                    ($stat['durum'] == 'aktif' ? 'success' : 
                                                    ($stat['durum'] == 'kirli' ? 'warning' : 
                                                    ($stat['durum'] == 'bakimda' ? 'info' : 
                                                    ($stat['durum'] == 'temizlik_bekliyor' ? 'secondary' :
                                                    ($stat['durum'] == 'devre_disi' ? 'dark' : 
                                                    ($stat['durum'] == 'bakim' ? 'info' :
                                                    (empty(trim($stat['durum'])) ? 'secondary' : 'primary'))))))) 
                                                ?> text-white avatar fs-5 fw-bold">
                                                    <?= $stat['sayi'] ?>
                                                </span>
                                            </div>
                                            <div class="col">
                                                <div class="font-weight-medium fs-6 fw-bold">
                                                    <?php 
                                                        $durum_text = trim($stat['durum']);
                                                        if (empty($durum_text)) {
                                                            echo 'Boş';
                                                        } else {
                                                            echo ucfirst(str_replace('_', ' ', $durum_text));
                                                        }
                                                    ?>
                                                </div>
                                                <div class="text-muted small">Tıklayarak filtrele</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Devam Eden Bakımlar -->
    <?php if (!empty($devam_eden_bakimlar)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tools me-2"></i>Devam Eden Bakımlar
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead>
                                <tr>
                                    <th>Oda</th>
                                    <th>Bakım Türü</th>
                                    <th>Açıklama</th>
                                    <th>Başlama</th>
                                    <th>Tahmini Bitiş</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($devam_eden_bakimlar as $bakim): ?>
                                    <tr>
                                        <td>
                                            <strong>Oda <?= $bakim['oda_numarasi'] ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($bakim['oda_tipi_adi']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($bakim['bakim_turu']) ?></td>
                                        <td><?= htmlspecialchars($bakim['bakim_aciklamasi']) ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($bakim['baslama_tarihi'])) ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($bakim['tahmini_bitis_tarihi'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="bakimTamamla(<?= $bakim['id'] ?>)">
                                                <i class="fas fa-check me-1"></i>Tamamla
                                            </button>
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
    <?php endif; ?>

    <!-- Oda Listesi -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="card-title">
                                <i class="fas fa-list me-2"></i>Tüm Odalar
                            </h3>
                        </div>
                        <div class="col-auto">
                            <div class="btn-list">
                                <!-- Gelişmiş Filtreler -->
                                <div class="row g-2">
                                    <!-- Oda Numarası Arama -->
                                    <div class="col-auto">
                                        <div class="input-group" style="width: 180px;">
                                            <input type="text" class="form-control form-control-sm" id="roomSearch" placeholder="Oda numarası ara..." onkeyup="applyFilters()">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="clearSearch()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Oda Tipi Filtresi -->
                                    <div class="col-auto">
                                        <select class="form-select form-select-sm" id="roomTypeFilter" onchange="applyFilters()" style="width: 150px;">
                                            <option value="">Tüm Tipler</option>
                                            <?php foreach ($oda_tipleri as $tip): ?>
                                                <option value="<?= htmlspecialchars($tip['oda_tipi_adi']) ?>"><?= htmlspecialchars($tip['oda_tipi_adi']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Durum Filtresi -->
                                    <div class="col-auto">
                                        <select class="form-select form-select-sm" id="statusFilter" onchange="applyFilters()" style="width: 150px;">
                                            <option value="">Tüm Durumlar</option>
                                            <option value="aktif">Aktif/Temiz</option>
                                            <option value="dolu">Dolu</option>
                                            <option value="kirli">Kirli</option>
                                            <option value="bakimda">Bakımda</option>
                                            <option value="temizlik_bekliyor">Temizlik Bekliyor</option>
                                            <option value="devre_disi">Devre Dışı</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Tarih Filtresi -->
                                    <div class="col-auto">
                                        <input type="date" class="form-control form-control-sm" id="dateFilter" onchange="applyFilters()" style="width: 150px;" title="Tarih Filtresi">
                                    </div>
                                    
                                    <!-- Tarih Aralığı -->
                                    <div class="col-auto">
                                        <input type="date" class="form-control form-control-sm" id="dateFromFilter" onchange="applyFilters()" style="width: 140px;" title="Başlangıç Tarihi">
                                    </div>
                                    <div class="col-auto">
                                        <input type="date" class="form-control form-control-sm" id="dateToFilter" onchange="applyFilters()" style="width: 140px;" title="Bitiş Tarihi">
                                    </div>
                                    
                                    <!-- Filtreleri Temizle -->
                                    <div class="col-auto">
                                        <button class="btn btn-outline-secondary btn-sm" onclick="clearAllFilters()">
                                            <i class="fas fa-eraser me-1"></i>Temizle
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Export Buttons -->
                                <div class="mt-2">
                                    <button class="btn btn-outline-danger btn-sm" onclick="exportToPDF()">
                                        <i class="fas fa-file-pdf me-1"></i>PDF İndir
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel me-1"></i>Excel İndir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row" id="roomList">
                        <?php foreach ($odalar as $oda): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
                                <div class="card card-sm border-<?= $oda['durum_renk'] ?>">
                                    <div class="card-status-top bg-<?= $oda['durum_renk'] ?>"></div>
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <span class="bg-<?= $oda['durum_renk'] ?> text-white avatar">
                                                    <?= $oda['oda_numarasi'] ?>
                                                </span>
                                            </div>
                                            <div class="col">
                                                <div class="font-weight-medium">
                                                    Oda <?= $oda['oda_numarasi'] ?>
                                                </div>
                                                <div class="text-muted">
                                                    <?= htmlspecialchars($oda['oda_tipi_adi']) ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php if ($oda['indirim_orani'] > 0): ?>
                                                        <span class="text-decoration-line-through text-muted">
                                                            <?= number_format($oda['original_fiyat'], 2) ?> TL
                                                        </span>
                                                        <span class="text-success fw-bold">
                                                            <?= number_format($oda['guncel_fiyat'], 2) ?> TL/gece
                                                        </span>
                                                        <span class="badge bg-red-lt">-%<?= $oda['indirim_orani'] ?></span>
                                                    <?php else: ?>
                                                        <?= number_format($oda['guncel_fiyat'], 2) ?> TL/gece
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <?= htmlspecialchars($oda['fiyat_tipi']) ?>
                                                    <?php if ($oda['kampanya_aciklama']): ?>
                                                        <br><span class="text-success"><?= htmlspecialchars($oda['kampanya_aciklama']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="row">
                                                <div class="col">
                                                    <div class="text-center">
                                                        <span class="badge bg-<?= $oda['durum_renk'] ?> text-white fs-5 px-4 py-3 fw-bold text-uppercase shadow-sm" style="font-size: 1.1rem !important; letter-spacing: 1px; border: 2px solid rgba(255,255,255,0.3); min-width: 120px; display: inline-block;">
                                                            <?php 
                                                                $durum_text = trim($oda['final_durum']);
                                                                if (empty($durum_text)) {
                                                                    echo 'Boş';
                                                                } elseif ($durum_text == 'rezerve') {
                                                                    echo 'Rezerve';
                                                                } elseif ($durum_text == 'checkout_oncesi_dolu') {
                                                                    echo 'Dolu (Checkout Bekliyor)';
                                                                } elseif ($durum_text == 'temizlik_bekliyor') {
                                                                    echo 'Temizlik Bekliyor';
                                                                } else {
                                                                    echo ucfirst(str_replace('_', ' ', $durum_text));
                                                                }
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($oda['uyari_mesaji'])): ?>
                                            <div class="mt-2">
                                                <div class="alert alert-warning alert-sm py-1 px-2 mb-2" style="font-size: 0.8rem;">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <?= htmlspecialchars($oda['uyari_mesaji']) ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2">
                                                <div class="btn-list d-flex justify-content-center">
                                                    <?php if (($oda['durum'] == 'kirli' || $oda['durum'] == 'temizlik_bekliyor') && 
                                                              (isset($_SESSION['user_role']) && 
                                                               ($_SESSION['user_role'] == 'housekeeper' || $_SESSION['user_role'] == 'housekeeper_manager'))): ?>
                                                        <button class="btn btn-sm btn-success" onclick="temizlikTamamla(<?= $oda['id'] ?>)">
                                                            <i class="fas fa-broom me-1"></i>Temizle
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($oda['durum'] != 'bakimda'): ?>
                                                        <button class="btn btn-sm btn-info" onclick="bakimBaslat(<?= $oda['id'] ?>)">
                                                            <i class="fas fa-tools me-1"></i>Bakım
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="durumDegistir(<?= $oda['id'] ?>, '<?= $oda['durum'] ?>')">
                                                        <i class="fas fa-edit me-1"></i>Durum
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Durum Değiştirme Modal -->
<div class="modal modal-blur fade" id="durumModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="oda_id" id="durum_oda_id">
                
                <div class="modal-header">
                    <h4 class="modal-title">Oda Durumu Değiştir</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Yeni Durum</label>
                        <select name="yeni_durum" class="form-select" required>
                            <option value="temiz">Temiz (Aktif)</option>
                            <option value="kirli">Kirli</option>
                            <option value="temizlik_bekliyor">Temizlik Bekliyor</option>
                            <option value="bakimda">Bakımda</option>
                            <option value="devre_disi">Devre Dışı</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="aciklama" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">İptal</a>
                    <button type="submit" name="oda_durum_guncelle" class="btn btn-primary ms-auto">
                        <i class="fas fa-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Temizlik Modal -->
<div class="modal modal-blur fade" id="temizlikModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="oda_id" id="temizlik_oda_id">
                
                <div class="modal-header">
                    <h4 class="modal-title">Temizlik Tamamla</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Temizlik Notu</label>
                        <textarea name="temizlik_notu" class="form-control" rows="3" placeholder="Temizlik ile ilgili notlar..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">İptal</a>
                    <button type="submit" name="temizlik_tamamla" class="btn btn-success ms-auto">
                        <i class="fas fa-broom me-1"></i>Temizlik Tamamla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bakım Başlat Modal -->
<div class="modal modal-blur fade" id="bakimModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="oda_id" id="bakim_oda_id">
                
                <div class="modal-header">
                    <h4 class="modal-title">Bakım Başlat</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Bakım Türü</label>
                                <select name="bakim_turu" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <option value="elektrik">Elektrik</option>
                                    <option value="tesisat">Tesisat</option>
                                    <option value="klima">Klima</option>
                                    <option value="mobilya">Mobilya</option>
                                    <option value="boyama">Boyama</option>
                                    <option value="genel_bakim">Genel Bakım</option>
                                    <option value="diger">Diğer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tahmini Süre (Saat)</label>
                                <input type="number" name="tahmini_sure" class="form-control" min="1" max="168" value="2" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Bakım Açıklaması</label>
                                <textarea name="bakim_aciklamasi" class="form-control" rows="4" required placeholder="Yapılacak bakım işlemlerini detaylı olarak açıklayınız..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">İptal</a>
                    <button type="submit" name="bakim_baslat" class="btn btn-info ms-auto">
                        <i class="fas fa-tools me-1"></i>Bakım Başlat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bakım Tamamla Modal -->
<div class="modal modal-blur fade" id="bakimTamamlaModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="bakim_id" id="tamamla_bakim_id">
                
                <div class="modal-header">
                    <h4 class="modal-title">Bakım Tamamla</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tamamlama Notu</label>
                        <textarea name="tamamlama_notu" class="form-control" rows="3" placeholder="Yapılan işlemler ve sonuç..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">İptal</a>
                    <button type="submit" name="bakim_tamamla" class="btn btn-success ms-auto">
                        <i class="fas fa-check me-1"></i>Bakım Tamamla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function durumDegistir(odaId, mevcutDurum) {
    document.getElementById('durum_oda_id').value = odaId;
    const modal = new bootstrap.Modal(document.getElementById('durumModal'));
    modal.show();
}

function temizlikTamamla(odaId) {
    document.getElementById('temizlik_oda_id').value = odaId;
    const modal = new bootstrap.Modal(document.getElementById('temizlikModal'));
    modal.show();
}

function bakimBaslat(odaId) {
    document.getElementById('bakim_oda_id').value = odaId;
    const modal = new bootstrap.Modal(document.getElementById('bakimModal'));
    modal.show();
}

function bakimTamamla(bakimId) {
    document.getElementById('tamamla_bakim_id').value = bakimId;
    const modal = new bootstrap.Modal(document.getElementById('bakimTamamlaModal'));
    modal.show();
}

// Gelişmiş filtre fonksiyonları
let currentFilters = {
    search: '',
    roomType: '',
    status: '',
    date: '',
    dateFrom: '',
    dateTo: ''
};

document.addEventListener('DOMContentLoaded', function() {
    // İlk yüklemede tüm odaları göster
    applyFilters();
});

function applyFilters() {
    // Mevcut filtre değerlerini al
    currentFilters.search = document.getElementById('roomSearch').value.trim();
    currentFilters.roomType = document.getElementById('roomTypeFilter').value;
    currentFilters.status = document.getElementById('statusFilter').value;
    currentFilters.date = document.getElementById('dateFilter').value;
    currentFilters.dateFrom = document.getElementById('dateFromFilter').value;
    currentFilters.dateTo = document.getElementById('dateToFilter').value;
    
    const roomCards = document.querySelectorAll('#roomList .col-xl-3');
    let visibleCount = 0;
    
    roomCards.forEach(function(card) {
        let showCard = true;
        
        // Oda numarası arama kontrolü
        if (currentFilters.search !== '') {
            const roomNumber = card.querySelector('.avatar').textContent.trim();
            if (!roomNumber.toLowerCase().includes(currentFilters.search.toLowerCase())) {
                showCard = false;
            }
        }
        
        // Oda tipi filtresi kontrolü
        if (showCard && currentFilters.roomType !== '') {
            const roomTypeElement = card.querySelector('.text-muted');
            if (roomTypeElement) {
                const roomType = roomTypeElement.textContent.trim();
                if (roomType !== currentFilters.roomType) {
                    showCard = false;
                }
            } else {
                showCard = false;
            }
        }
        
        // Durum filtresi kontrolü
        if (showCard && currentFilters.status !== '') {
            const statusElement = card.querySelector('.card-status-top');
            if (statusElement) {
                const statusClass = statusElement.className;
                let roomStatus = '';
                
                // CSS sınıfından durumu belirle
                if (statusClass.includes('bg-success')) {
                    roomStatus = 'aktif';
                } else if (statusClass.includes('bg-danger')) {
                    roomStatus = 'dolu';
                } else if (statusClass.includes('bg-warning')) {
                    roomStatus = 'kirli';
                } else if (statusClass.includes('bg-info')) {
                    roomStatus = 'bakimda';
                } else if (statusClass.includes('bg-secondary')) {
                    roomStatus = 'temizlik_bekliyor';
                } else if (statusClass.includes('bg-dark')) {
                    roomStatus = 'devre_disi';
                }
                
                if (roomStatus !== currentFilters.status) {
                    showCard = false;
                }
            } else {
                showCard = false;
            }
        }
        
        // Tarih filtresi kontrolü (tek tarih)
        if (showCard && currentFilters.date !== '') {
            // Bu örnekte oda kartlarında tarih bilgisi yok, 
            // gerçek uygulamada rezervasyon tarihleri kontrol edilebilir
            // Şimdilik bu filtreyi aktif bırakıyoruz
        }
        
        // Tarih aralığı filtresi kontrolü
        if (showCard && (currentFilters.dateFrom !== '' || currentFilters.dateTo !== '')) {
            // Bu örnekte oda kartlarında tarih bilgisi yok,
            // gerçek uygulamada rezervasyon tarihleri kontrol edilebilir
            // Şimdilik bu filtreyi aktif bırakıyoruz
        }
        
        // Kartı göster/gizle
        if (showCard) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Sonuç sayısını göster (isteğe bağlı)
    updateResultCount(visibleCount);
}

function updateResultCount(count) {
    // Sonuç sayısını göstermek için (isteğe bağlı)
    const totalRooms = document.querySelectorAll('#roomList .col-xl-3').length;
    console.log(`Gösterilen: ${count} / ${totalRooms} oda`);
}

function clearSearch() {
    document.getElementById('roomSearch').value = '';
    applyFilters();
}

function clearAllFilters() {
    document.getElementById('roomSearch').value = '';
    document.getElementById('roomTypeFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFilter').value = '';
    document.getElementById('dateFromFilter').value = '';
    document.getElementById('dateToFilter').value = '';
    applyFilters();
}

// İstatistik kartından duruma göre filtreleme
function filterByStatus(status) {
    // Önce tüm filtreleri temizle
    clearAllFilters();
    
    // Durum filtresini ayarla
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        // Durum değerlerini eşleştir
        let filterValue = '';
        switch(status) {
            case 'aktif':
                filterValue = 'aktif';
                break;
            case 'dolu':
                filterValue = 'dolu';
                break;
            case 'kirli':
                filterValue = 'kirli';
                break;
            case 'bakimda':
            case 'bakim':
                filterValue = 'bakimda';
                break;
            case 'temizlik_bekliyor':
                filterValue = 'temizlik_bekliyor';
                break;
            case 'devre_disi':
                filterValue = 'devre_disi';
                break;
            default:
                filterValue = '';
        }
        
        statusFilter.value = filterValue;
        currentFilters.status = filterValue;
        
        // Filtreleri uygula
        applyFilters();
        
        // Oda listesi bölümüne kaydır
        const roomListSection = document.querySelector('#roomList').closest('.card');
        if (roomListSection) {
            roomListSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

// PDF Export fonksiyonu
function exportToPDF() {
    const filteredRooms = getFilteredRoomsData();
    
    // PDF export için form oluştur ve gönder
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_rooms_pdf.php';
    form.style.display = 'none';
    
    const filterInput = document.createElement('input');
    filterInput.type = 'hidden';
    filterInput.name = 'filters';
    filterInput.value = JSON.stringify(currentFilters);
    
    const dataInput = document.createElement('input');
    dataInput.type = 'hidden';
    dataInput.name = 'rooms_data';
    dataInput.value = JSON.stringify(filteredRooms);
    
    form.appendChild(filterInput);
    form.appendChild(dataInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Excel Export fonksiyonu
function exportToExcel() {
    const filteredRooms = getFilteredRoomsData();
    
    // Excel export için form oluştur ve gönder
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_rooms_excel.php';
    form.style.display = 'none';
    
    const filterInput = document.createElement('input');
    filterInput.type = 'hidden';
    filterInput.name = 'filters';
    filterInput.value = JSON.stringify(currentFilters);
    
    const dataInput = document.createElement('input');
    dataInput.type = 'hidden';
    dataInput.name = 'rooms_data';
    dataInput.value = JSON.stringify(filteredRooms);
    
    form.appendChild(filterInput);
    form.appendChild(dataInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Filtrelenmiş oda verilerini topla
function getFilteredRoomsData() {
    const visibleRooms = [];
    const roomCards = document.querySelectorAll('#roomList .col-xl-3');
    
    roomCards.forEach(function(card) {
        if (card.style.display !== 'none') {
            const roomNumber = card.querySelector('.avatar').textContent.trim();
            const roomTypeElement = card.querySelector('.text-muted');
            const roomType = roomTypeElement ? roomTypeElement.textContent.trim() : '';
            
            // Durum bilgisini CSS sınıfından al
            const statusElement = card.querySelector('.card-status-top');
            let status = 'Bilinmiyor';
            if (statusElement) {
                const statusClass = statusElement.className;
                if (statusClass.includes('bg-success')) {
                    status = 'Aktif/Temiz';
                } else if (statusClass.includes('bg-danger')) {
                    status = 'Dolu';
                } else if (statusClass.includes('bg-warning')) {
                    status = 'Kirli';
                } else if (statusClass.includes('bg-info')) {
                    status = 'Bakımda';
                } else if (statusClass.includes('bg-secondary')) {
                    status = 'Temizlik Bekliyor';
                } else if (statusClass.includes('bg-dark')) {
                    status = 'Devre Dışı';
                }
            }
            
            const priceElement = card.querySelector('.text-muted.small');
            const price = priceElement ? priceElement.textContent.trim() : '';
            
            visibleRooms.push({
                room_number: roomNumber,
                room_type: roomType,
                status: status,
                price: price
            });
        }
    });
    
    return visibleRooms;
}
</script>

<?php include 'footer.php'; ?>