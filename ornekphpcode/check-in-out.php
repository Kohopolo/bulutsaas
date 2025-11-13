
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
requireDetailedPermission('checkin_checkout', 'Check-in/Check-out işlemleri yetkiniz bulunmamaktadır.');

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';

// Check-in/Check-out işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $action = $_POST['action'];
    $oda_numarasi_id = isset($_POST['oda_numarasi_id']) ? intval($_POST['oda_numarasi_id']) : null;
    
    if ($action == 'check_in') {
        // Check-in işlemi
        if (!$oda_numarasi_id) {
            $error_message = 'Check-in için oda numarası seçilmelidir.';
        } else {
            // Oda müsait mi kontrol et
            $oda_kontrol = fetchOne("
                SELECT COUNT(*) as sayi 
                FROM rezervasyonlar 
                WHERE oda_numarasi_id = ? 
                AND durum = 'check_in' 
                AND id != ?
            ", [$oda_numarasi_id, $rezervasyon_id]);
            
            if ($oda_kontrol['sayi'] > 0) {
                $error_message = 'Bu oda şu anda dolu.';
            } else {
                $sql = "UPDATE rezervasyonlar SET durum = 'check_in', oda_numarasi_id = ?, gercek_giris_tarihi = NOW() WHERE id = ?";
                if (executeQuery($sql, [$oda_numarasi_id, $rezervasyon_id])) {
                    $success_message = 'Check-in işlemi başarıyla tamamlandı.';
                } else {
                    $error_message = 'Check-in işlemi sırasında hata oluştu.';
                }
            }
        }
    } elseif ($action == 'check_out') {
        // Check-out işlemi
        $sql = "UPDATE rezervasyonlar SET durum = 'check_out', gercek_cikis_tarihi = NOW() WHERE id = ?";
        if (executeQuery($sql, [$rezervasyon_id])) {
            $success_message = 'Check-out işlemi başarıyla tamamlandı.';
        } else {
            $error_message = 'Check-out işlemi sırasında hata oluştu.';
        }
    }
}

// Bugünkü check-in bekleyen rezervasyonlar
$bugun_check_in = fetchAll("
    SELECT r.*, ot.oda_tipi_adi, m.ad, m.soyad, 
           CONCAT(m.ad, ' ', m.soyad) as ad_soyad,
           CONCAT('RZ-', LPAD(r.id, 6, '0')) as rezervasyon_no
    FROM rezervasyonlar r 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    WHERE DATE(r.giris_tarihi) = CURDATE() 
    AND r.durum = 'onaylandi'
    ORDER BY r.giris_tarihi ASC
");

// Bugünkü check-out bekleyen rezervasyonlar
$bugun_check_out = fetchAll("
    SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi, m.ad, m.soyad,
           CONCAT(m.ad, ' ', m.soyad) as ad_soyad,
           CONCAT('RZ-', LPAD(r.id, 6, '0')) as rezervasyon_no
    FROM rezervasyonlar r 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id 
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    WHERE DATE(r.cikis_tarihi) = CURDATE() 
    AND r.durum = 'check_in'
    ORDER BY r.cikis_tarihi ASC
");

// Aktif konaklamalar
$aktif_konaklamalar = fetchAll("
    SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi, m.ad, m.soyad,
           CONCAT(m.ad, ' ', m.soyad) as ad_soyad,
           CONCAT('RZ-', LPAD(r.id, 6, '0')) as rezervasyon_no
    FROM rezervasyonlar r 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id 
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    WHERE r.durum = 'check_in' 
    AND DATE(r.cikis_tarihi) >= CURDATE()
    ORDER BY r.cikis_tarihi ASC
");

// Müsait odaları getir
function getMusaitOdalar($oda_tipi_id, $giris_tarihi, $cikis_tarihi) {
    $sql = "SELECT odn.* 
            FROM oda_numaralari odn 
            WHERE odn.oda_tipi_id = ? 
            AND odn.durum = 'aktif'
            AND odn.id NOT IN (
                SELECT DISTINCT r.oda_numarasi_id 
                FROM rezervasyonlar r 
                WHERE r.oda_numarasi_id IS NOT NULL 
                AND r.durum IN ('check_in') 
                AND (
                    (DATE(r.giris_tarihi) <= ? AND DATE(r.cikis_tarihi) > ?) OR
                    (DATE(r.giris_tarihi) < ? AND DATE(r.cikis_tarihi) >= ?)
                )
            )
            ORDER BY odn.oda_numarasi ASC";
    
    return fetchAll($sql, [$oda_tipi_id, $giris_tarihi, $giris_tarihi, $cikis_tarihi, $cikis_tarihi]);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in / Check-out - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Check-in / Check-out İşlemleri</h5>
                            </div>
                            <div class="card-body">
                                <!-- Bugünkü Check-in'ler -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary">Bugünkü Check-in'ler</h6>
                                        <?php if (empty($bugun_check_in)): ?>
                                            <div class="alert alert-info">Bugün check-in yapacak rezervasyon bulunmuyor.</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Rezervasyon No</th>
                                                            <th>Müşteri</th>
                                                            <th>Oda Tipi</th>
                                                            <th>Giriş Tarihi</th>
                                                            <th>Çıkış Tarihi</th>
                                                            <th>Durum</th>
                                                            <th>İşlemler</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($bugun_check_in as $rezervasyon): ?>
                                                            <tr>
                                                                <td><?= $rezervasyon['rezervasyon_no'] ?></td>
                                                                <td><?= $rezervasyon['ad_soyad'] ?></td>
                                                                <td><?= $rezervasyon['oda_tipi_adi'] ?></td>
                                                                <td><?= date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])) ?></td>
                                                                <td><?= date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])) ?></td>
                                                                <td>
                                                                    <span class="badge bg-warning">Bekliyor</span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-success btn-sm" onclick="checkinYap(<?= $rezervasyon['id'] ?>)">
                                                                        <i class="fas fa-sign-in-alt"></i> Check-in
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Bugünkü Check-out'lar -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-danger">Bugünkü Check-out'lar</h6>
                                        <?php if (empty($bugun_check_out)): ?>
                                            <div class="alert alert-info">Bugün check-out yapacak rezervasyon bulunmuyor.</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Rezervasyon No</th>
                                                            <th>Müşteri</th>
                                                            <th>Oda Tipi</th>
                                                            <th>Oda No</th>
                                                            <th>Giriş Tarihi</th>
                                                            <th>Çıkış Tarihi</th>
                                                            <th>Durum</th>
                                                            <th>İşlemler</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($bugun_check_out as $rezervasyon): ?>
                                                            <tr>
                                                                <td><?= $rezervasyon['rezervasyon_no'] ?></td>
                                                                <td><?= $rezervasyon['ad_soyad'] ?></td>
                                                                <td><?= $rezervasyon['oda_tipi_adi'] ?></td>
                                                                <td><?= $rezervasyon['oda_numarasi'] ?></td>
                                                                <td><?= date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])) ?></td>
                                                                <td><?= date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])) ?></td>
                                                                <td>
                                                                    <span class="badge bg-success">Check-in</span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-danger btn-sm" onclick="checkoutYap(<?= $rezervasyon['id'] ?>)">
                                                                        <i class="fas fa-sign-out-alt"></i> Check-out
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Aktif Konaklamalar -->
                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="text-success">Aktif Konaklamalar</h6>
                                        <?php if (empty($aktif_konaklamalar)): ?>
                                            <div class="alert alert-info">Şu anda aktif konaklama bulunmuyor.</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Rezervasyon No</th>
                                                            <th>Müşteri</th>
                                                            <th>Oda Tipi</th>
                                                            <th>Oda No</th>
                                                            <th>Giriş Tarihi</th>
                                                            <th>Çıkış Tarihi</th>
                                                            <th>Kalan Gün</th>
                                                            <th>Durum</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($aktif_konaklamalar as $rezervasyon): ?>
                                                            <?php 
                                                            $kalan_gun = ceil((strtotime($rezervasyon['cikis_tarihi']) - time()) / (60 * 60 * 24));
                                                            ?>
                                                            <tr>
                                                                <td><?= $rezervasyon['rezervasyon_no'] ?></td>
                                                                <td><?= $rezervasyon['ad_soyad'] ?></td>
                                                                <td><?= $rezervasyon['oda_tipi_adi'] ?></td>
                                                                <td><?= $rezervasyon['oda_numarasi'] ?></td>
                                                                <td><?= date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])) ?></td>
                                                                <td><?= date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])) ?></td>
                                                                <td><?= $kalan_gun ?> gün</td>
                                                                <td>
                                                                    <span class="badge bg-success">Konaklıyor</span>
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        function checkinYap(rezervasyonId) {
            if (confirm('Bu rezervasyon için check-in işlemi yapılsın mı?')) {
                // AJAX ile check-in işlemi
                const formData = new FormData();
                formData.append('rezervasyon_id', rezervasyonId);
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                
                fetch('ajax/checkin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Check-in işlemi başarıyla tamamlandı!');
                        location.reload();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu!');
                });
            }
        }

        function checkoutYap(rezervasyonId) {
            if (confirm('Bu rezervasyon için check-out işlemi yapılsın mı?')) {
                // AJAX ile check-out işlemi
                const formData = new FormData();
                formData.append('rezervasyon_id', rezervasyonId);
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                
                fetch('ajax/checkout.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Check-out işlemi başarıyla tamamlandı!');
                        location.reload();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu!');
                });
            }
        }
    </script>
</body>
</html>
