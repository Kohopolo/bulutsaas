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
requireDetailedPermission('fnb_siparis_goruntule', 'F&B sipariş detayları görüntüleme yetkiniz bulunmamaktadır.');

$siparis_id = intval($_GET['id'] ?? 0);
if (!$siparis_id) {
    $_SESSION['error_message'] = 'Geçersiz sipariş ID.';
    header('Location: fnb-siparis-yonetimi.php');
    exit;
}

$success_message = '';
$error_message = '';

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'durum_guncelle') {
            $yeni_durum = sanitizeString($_POST['yeni_durum']);
            $aciklama = sanitizeString($_POST['aciklama']);
            
            $sql = "UPDATE fnb_siparisler SET siparis_durumu = ?, guncelleme_tarihi = NOW() WHERE id = ?";
            if (executeQuery($sql, [$yeni_durum, $siparis_id])) {
                $success_message = 'Sipariş durumu başarıyla güncellendi.';
            } else {
                $error_message = 'Sipariş durumu güncellenirken hata oluştu.';
            }
        }
        
        if ($action == 'hazirlayan_ata') {
            $hazirlayan_id = intval($_POST['hazirlayan_id']);
            
            $sql = "UPDATE fnb_siparisler SET hazirlayan_id = ?, guncelleme_tarihi = NOW() WHERE id = ?";
            if (executeQuery($sql, [$hazirlayan_id, $siparis_id])) {
                $success_message = 'Hazırlayan personel başarıyla atandı.';
            } else {
                $error_message = 'Hazırlayan personel atanırken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Sipariş detaylarını getir
$siparis = fetchOne("
    SELECT fs.*, oda_numaralari.oda_numarasi, ot.oda_tipi_adi as oda_tipi,
           m.ad as musteri_adi, m.soyad as musteri_soyadi, m.email as musteri_email, m.telefon as musteri_telefon,
           k1.ad as siparis_alan_adi, k1.soyad as siparis_alan_soyadi,
           k2.ad as hazirlayan_adi, k2.soyad as hazirlayan_soyadi
    FROM fnb_siparisler fs
    LEFT JOIN oda_numaralari ON fs.oda_id = oda_numaralari.id
    LEFT JOIN oda_tipleri ot ON oda_numaralari.oda_tipi_id = ot.id
    LEFT JOIN musteriler m ON fs.musteri_id = m.id
    LEFT JOIN kullanicilar k1 ON fs.siparis_alan_id = k1.id
    LEFT JOIN kullanicilar k2 ON fs.hazirlayan_id = k2.id
    WHERE fs.id = ?
", [$siparis_id]);

if (!$siparis) {
    $_SESSION['error_message'] = 'Sipariş bulunamadı.';
    header('Location: fnb-siparis-yonetimi.php');
    exit;
}

// Sipariş detaylarını getir
$siparis_detaylari = fetchAll("
    SELECT fsd.*, mo.urun_adi, mo.urun_aciklamasi, mo.fiyat as menu_fiyati
    FROM fnb_siparis_detaylari fsd
    LEFT JOIN menu_ogeleri mo ON fsd.menu_ogesi_id = mo.id
    WHERE fsd.siparis_id = ?
    ORDER BY fsd.id
", [$siparis_id]);

// Hazırlayan personelleri getir
$hazirlayan_personeller = fetchAll("
    SELECT id, ad, soyad, email
    FROM kullanicilar
    WHERE rol IN ('mutfak', 'restoran', 'bar', 'mutfak_manager', 'restoran_manager', 'bar_manager', 'fnb_manager')
    AND durum = 'aktif' AND aktif = 1
    ORDER BY ad, soyad
");

// Sipariş durumları
$siparis_durumlari = [
    'alindi' => 'Alındı',
    'hazirlaniyor' => 'Hazırlanıyor',
    'hazir' => 'Hazır',
    'teslim_edildi' => 'Teslim Edildi',
    'iptal' => 'İptal'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&B Sipariş Detayı - <?php echo htmlspecialchars($siparis['siparis_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">F&B Sipariş Detayı</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="fnb-siparis-yonetimi.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Geri Dön
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Sipariş Bilgileri -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-receipt"></i> Sipariş Bilgileri
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Sipariş No:</strong></td>
                                        <td><?php echo htmlspecialchars($siparis['siparis_no']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Oda:</strong></td>
                                        <td><?php echo htmlspecialchars($siparis['oda_numarasi'] . ' (' . $siparis['oda_tipi'] . ')'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Müşteri:</strong></td>
                                        <td><?php echo htmlspecialchars($siparis['musteri_adi'] . ' ' . $siparis['musteri_soyadi']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Telefon:</strong></td>
                                        <td><?php echo htmlspecialchars($siparis['musteri_telefon']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Departman:</strong></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars(ucfirst($siparis['departman'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Durum:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $siparis['siparis_durumu'] == 'teslim_edildi' ? 'success' : 
                                                    ($siparis['siparis_durumu'] == 'iptal' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo htmlspecialchars($siparis_durumlari[$siparis['siparis_durumu']] ?? $siparis['siparis_durumu']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sipariş Tarihi:</strong></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($siparis['siparis_tarihi'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sipariş Alan:</strong></td>
                                        <td><?php echo htmlspecialchars($siparis['siparis_alan_adi'] . ' ' . $siparis['siparis_alan_soyadi']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Hazırlayan:</strong></td>
                                        <td>
                                            <?php if ($siparis['hazirlayan_adi']): ?>
                                                <?php echo htmlspecialchars($siparis['hazirlayan_adi'] . ' ' . $siparis['hazirlayan_soyadi']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Atanmamış</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sipariş İşlemleri -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-cogs"></i> Sipariş İşlemleri
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Durum Güncelleme -->
                                <form method="POST" class="mb-3">
                                    <input type="hidden" name="action" value="durum_guncelle">
                                    <div class="mb-3">
                                        <label class="form-label">Durum Güncelle</label>
                                        <select name="yeni_durum" class="form-select" required>
                                            <?php foreach ($siparis_durumlari as $key => $value): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $siparis['siparis_durumu'] == $key ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($value); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save"></i> Durumu Güncelle
                                    </button>
                                </form>

                                <!-- Hazırlayan Atama -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="hazirlayan_ata">
                                    <div class="mb-3">
                                        <label class="form-label">Hazırlayan Personel</label>
                                        <select name="hazirlayan_id" class="form-select" required>
                                            <option value="">Personel Seçin</option>
                                            <?php foreach ($hazirlayan_personeller as $personel): ?>
                                                <option value="<?php echo $personel['id']; ?>" <?php echo $siparis['hazirlayan_id'] == $personel['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-user-plus"></i> Personel Ata
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sipariş Detayları -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list"></i> Sipariş Detayları
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Ürün</th>
                                        <th>Açıklama</th>
                                        <th>Adet</th>
                                        <th>Birim Fiyat</th>
                                        <th>Toplam</th>
                                        <th>Özel Notlar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($siparis_detaylari as $detay): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($detay['urun_adi'] ?? 'Özel Ürün'); ?></td>
                                            <td><?php echo htmlspecialchars($detay['urun_aciklamasi'] ?? ''); ?></td>
                                            <td><?php echo $detay['adet']; ?></td>
                                            <td><?php echo number_format($detay['birim_fiyat'], 2); ?>₺</td>
                                            <td><?php echo number_format($detay['toplam_fiyat'], 2); ?>₺</td>
                                            <td><?php echo htmlspecialchars($detay['ozel_notlar'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <th colspan="4">Toplam Tutar</th>
                                        <th><?php echo number_format($siparis['toplam_tutar'], 2); ?>₺</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
