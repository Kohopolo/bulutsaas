<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('resepsiyon_checkin_islemleri', 'Resepsiyon check-in işlemleri yetkiniz bulunmamaktadır.');

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';
$rezervasyon = null;

// Rezervasyon ID'si varsa bilgileri getir
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $rezervasyon_id = intval($_GET['id']);
    $rezervasyon = fetchOne("
        SELECT r.*, m.ad, m.soyad, m.telefon, m.email, m.tc_kimlik, m.adres,
               ot.oda_tipi_adi, od.oda_numarasi
        FROM rezervasyonlar r 
        JOIN musteriler m ON r.musteri_id = m.id 
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
        LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id 
        WHERE r.id = ? AND r.durum = 'onaylandi'
    ", [$rezervasyon_id]);
    
    if (!$rezervasyon) {
        $error_message = 'Rezervasyon bulunamadı veya check-in için uygun değil.';
    } else {
        // Ödeme bilgilerini getir
        $odemeler = fetchAll("
            SELECT * FROM rezervasyon_odemeleri 
            WHERE rezervasyon_id = ? AND durum = 'aktif'
            ORDER BY odeme_tarihi DESC
        ", [$rezervasyon_id]);
        
        // Toplam ödenen tutarı hesapla
        $toplam_odenen = 0;
        foreach ($odemeler as $odeme) {
            $toplam_odenen += $odeme['odeme_tutari'];
        }
        
        // Kalan tutarı hesapla
        $kalan_tutar = $rezervasyon['toplam_fiyat'] - $toplam_odenen;
        
        // Ödeme durumunu belirle
        if ($kalan_tutar <= 0) {
            $odeme_durumu = 'odendi';
        } elseif ($toplam_odenen > 0) {
            $odeme_durumu = 'kismi_odeme';
        } else {
            $odeme_durumu = 'odenmedi';
        }
    }
}

// Check-in işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkin_submit'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $oda_numarasi_id = intval($_POST['oda_numarasi_id']);
    $odeme_durumu = $_POST['odeme_durumu'];
    $odeme_miktari = floatval($_POST['odeme_miktari']);
    $odeme_yontemi = $_POST['odeme_yontemi'];
    $notlar = trim($_POST['notlar']);
    
    try {
        $pdo->beginTransaction();
        
        // Rezervasyon durumunu güncelle
        $sql = "UPDATE rezervasyonlar SET 
                durum = 'check_in', 
                oda_numarasi_id = ?, 
                gercek_giris_tarihi = NOW(),
                notlar = ?,
                odeme_durumu = ?
                WHERE id = ? AND durum = 'onaylandi'";
        
        if (!executeQuery($sql, [$oda_numarasi_id, $notlar, $odeme_durumu, $rezervasyon_id])) {
            throw new Exception('Rezervasyon güncellenirken hata oluştu.');
        }
        
        // Ödeme kaydı ekle
        if ($odeme_miktari > 0) {
            $sql = "INSERT INTO rezervasyon_odemeleri (rezervasyon_id, odeme_tutari, odeme_yontemi, aciklama, kullanici_id, durum, odeme_tarihi) 
                    VALUES (?, ?, ?, 'Check-in ödemesi', ?, 'aktif', NOW())";
            
            if (!executeQuery($sql, [$rezervasyon_id, $odeme_miktari, $odeme_yontemi, $_SESSION['user_id']])) {
                throw new Exception('Ödeme kaydı eklenirken hata oluştu.');
            }
        }
        
        // Rezervasyon geçmişi kaydı
        $sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id, olusturma_tarihi) 
                VALUES (?, 'check_in', 'Misafir check-in işlemi tamamlandı', ?, NOW())";
        
        executeQuery($sql, [$rezervasyon_id, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        $success_message = 'Check-in işlemi başarıyla tamamlandı.';
        
        // Başarılı işlem sonrası yönlendirme
        header('Location: resepsiyon.php?success=' . urlencode($success_message));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Check-in işlemi sırasında hata oluştu: ' . $e->getMessage();
    }
}

// Bugünkü check-in listesi
$bugun_checkin_listesi = fetchAll("
    SELECT r.*, m.ad, m.soyad, m.telefon, ot.oda_tipi_adi, od.oda_numarasi
    FROM rezervasyonlar r 
    JOIN musteriler m ON r.musteri_id = m.id 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id 
    WHERE DATE(r.giris_tarihi) = CURDATE() AND r.durum = 'onaylandi'
    ORDER BY r.giris_tarihi ASC
");

// Müsait odalar
$musait_odalar = fetchAll("
    SELECT od.id, od.oda_numarasi, ot.oda_tipi_adi, ot.max_yetiskin
    FROM oda_numaralari od
    JOIN oda_tipleri ot ON od.oda_tipi_id = ot.id
    WHERE od.id NOT IN (
        SELECT oda_numarasi_id 
        FROM rezervasyonlar 
        WHERE durum = 'check_in' 
        AND oda_numarasi_id IS NOT NULL
    )
    ORDER BY od.oda_numarasi ASC
");

$page_title = 'Check-In İşlemleri';
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
                                <i class="fas fa-sign-in-alt me-2"></i>Check-In İşlemleri
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

    <div class="row">
        <!-- Check-In Formu -->
        <?php if ($rezervasyon): ?>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-check me-2"></i>Misafir Check-In
                    </h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="rezervasyon_id" value="<?= $rezervasyon['id'] ?>">
                    
                    <div class="card-body">
                        <!-- Misafir Bilgileri -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="mb-3">Misafir Bilgileri</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ad Soyad</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($rezervasyon['ad'] . ' ' . $rezervasyon['soyad']) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">TC Kimlik No</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($rezervasyon['tc_kimlik']) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Telefon</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($rezervasyon['telefon']) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">E-posta</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($rezervasyon['email']) ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rezervasyon Bilgileri -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="mb-3">Rezervasyon Bilgileri</h4>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Giriş Tarihi</label>
                                            <input type="text" class="form-control" value="<?= date('d.m.Y H:i', strtotime($rezervasyon['giris_tarihi'])) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Çıkış Tarihi</label>
                                            <input type="text" class="form-control" value="<?= date('d.m.Y H:i', strtotime($rezervasyon['cikis_tarihi'])) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Oda Tipi</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($rezervasyon['oda_tipi_adi']) ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Oda Seçimi -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="mb-3">Oda Ataması</h4>
                                <div class="mb-3">
                                    <label class="form-label">Oda Numarası <span class="text-danger">*</span></label>
                                    <select name="oda_numarasi_id" class="form-select" required>
                                        <option value="">Oda Seçiniz</option>
                                        <?php foreach ($musait_odalar as $oda): ?>
                                            <option value="<?= $oda['id'] ?>" <?= ($rezervasyon['oda_numarasi_id'] == $oda['id']) ? 'selected' : '' ?>>
                                                Oda <?= $oda['oda_numarasi'] ?> - <?= $oda['oda_tipi_adi'] ?> (<?= $oda['max_yetiskin'] ?> kişi)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Ödeme Bilgileri -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="mb-3">Ödeme Bilgileri</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Toplam Tutar</label>
                                            <input type="text" class="form-control" value="<?= number_format($rezervasyon['toplam_fiyat'], 2) ?> ₺" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Ödenen Tutar</label>
                                            <input type="text" class="form-control" value="<?= number_format($toplam_odenen, 2) ?> ₺" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Kalan Tutar</label>
                                            <input type="text" class="form-control" value="<?= number_format($kalan_tutar, 2) ?> ₺" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Ödeme Durumu</label>
                                            <select name="odeme_durumu" class="form-select">
                                                <option value="odendi" <?= $odeme_durumu == 'odendi' ? 'selected' : '' ?>>Ödendi</option>
                                                <option value="kismi_odeme" <?= $odeme_durumu == 'kismi_odeme' ? 'selected' : '' ?>>Kısmi Ödeme</option>
                                                <option value="odenmedi" <?= $odeme_durumu == 'odenmedi' ? 'selected' : '' ?>>Ödenmedi</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ek Ödeme Miktarı</label>
                                            <input type="number" name="odeme_miktari" class="form-control" step="0.01" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ödeme Yöntemi</label>
                                            <select name="odeme_yontemi" class="form-select">
                                                <option value="nakit">Nakit</option>
                                                <option value="kredi_karti">Kredi Kartı</option>
                                                <option value="havale">Havale</option>
                                                <option value="cek">Çek</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Ödeme Geçmişi -->
                                <?php if (!empty($odemeler)): ?>
                                <div class="mt-3">
                                    <h6>Ödeme Geçmişi</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Tarih</th>
                                                    <th>Tutar</th>
                                                    <th>Yöntem</th>
                                                    <th>Açıklama</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($odemeler as $odeme): ?>
                                                <tr>
                                                    <td><?= date('d.m.Y H:i', strtotime($odeme['odeme_tarihi'])) ?></td>
                                                    <td><?= number_format($odeme['odeme_tutari'], 2) ?> ₺</td>
                                                    <td><?= ucfirst($odeme['odeme_yontemi']) ?></td>
                                                    <td><?= htmlspecialchars($odeme['aciklama']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Notlar -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Check-In Notları</label>
                                    <textarea name="notlar" class="form-control" rows="3" placeholder="Check-in ile ilgili notlar..."><?= htmlspecialchars($rezervasyon['notlar']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" name="checkin_submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-1"></i>Check-In Tamamla
                            </button>
                            <a href="resepsiyon.php" class="btn btn-link ms-auto">İptal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bugünkü Check-In Listesi -->
        <div class="<?= $rezervasyon ? 'col-lg-4' : 'col-12' ?>">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list me-2"></i>Bugünkü Check-In Listesi
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($bugun_checkin_listesi)): ?>
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
                                    <?php foreach ($bugun_checkin_listesi as $item): ?>
                                        <tr class="<?= ($rezervasyon && $rezervasyon['id'] == $item['id']) ? 'table-active' : '' ?>">
                                            <td>
                                                <div class="d-flex py-1 align-items-center">
                                                    <div class="flex-fill">
                                                        <div class="font-weight-medium"><?= htmlspecialchars($item['ad'] . ' ' . $item['soyad']) ?></div>
                                                        <div class="text-muted"><?= htmlspecialchars($item['telefon']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($item['oda_numarasi']): ?>
                                                    <span class="badge bg-blue"><?= htmlspecialchars($item['oda_numarasi']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Oda Atanmamış</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('H:i', strtotime($item['giris_tarihi'])) ?></td>
                                            <td>
                                                <?php if (!$rezervasyon || $rezervasyon['id'] != $item['id']): ?>
                                                    <a href="resepsiyon-checkin.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">
                                                        Seç
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Seçili</span>
                                                <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ödeme durumu değiştiğinde ödeme miktarını güncelle
    const odemeDurumu = document.querySelector('select[name="odeme_durumu"]');
    const odemeMiktari = document.querySelector('input[name="odeme_miktari"]');
    const toplamTutar = <?= $rezervasyon ? $rezervasyon['toplam_fiyat'] : 0 ?>;
    
    if (odemeDurumu && odemeMiktari) {
        odemeDurumu.addEventListener('change', function() {
            if (this.value === 'beklemede') {
                odemeMiktari.value = 0;
            } else if (this.value === 'tamamlandi') {
                odemeMiktari.value = 0; // Ek ödeme 0 olarak kalmalı
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>