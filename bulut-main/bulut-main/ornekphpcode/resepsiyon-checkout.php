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
               ot.oda_tipi_adi, od.oda_numarasi,
               DATEDIFF(NOW(), r.giris_tarihi) as konaklama_gun
        FROM rezervasyonlar r 
        JOIN musteriler m ON r.musteri_id = m.id 
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
        LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id 
        WHERE r.id = ? AND r.durum = 'check_in'
    ", [$rezervasyon_id]);
    
    if (!$rezervasyon) {
        $error_message = 'Rezervasyon bulunamadı veya check-out için uygun değil.';
    }
}

// Ekstra hizmetleri getir
$ekstra_hizmetler = [];
$toplam_ekstra = 0;
if ($rezervasyon) {
    // ekstra_hizmetler tablosu mevcut değil, boş array kullan
    $ekstra_hizmetler = [];
    
    // Rezervasyon ek hizmetler bilgisi varsa JSON'dan parse et
    if (!empty($rezervasyon['ek_hizmetler'])) {
        $ek_hizmetler_json = json_decode($rezervasyon['ek_hizmetler'], true);
        if (is_array($ek_hizmetler_json)) {
            foreach ($ek_hizmetler_json as $hizmet) {
                if (isset($hizmet['fiyat']) && isset($hizmet['adet'])) {
                    $toplam_ekstra += $hizmet['fiyat'] * $hizmet['adet'];
                }
            }
        }
    }
}

// Ödemeleri getir
$odemeler = [];
$toplam_odenen = 0;
if ($rezervasyon) {
    $odemeler = fetchAll("
        SELECT * FROM odemeler 
        WHERE rezervasyon_id = ? AND durum = 'tamamlandi'
        ORDER BY odeme_tarihi DESC
    ", [$rezervasyon['id']]);
    
    foreach ($odemeler as $odeme) {
        $toplam_odenen += $odeme['miktar'];
    }
}

// Check-out işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout_submit'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $ekstra_ucret = floatval($_POST['ekstra_ucret']);
    $indirim = floatval($_POST['indirim']);
    $odeme_miktari = floatval($_POST['odeme_miktari']);
    $odeme_yontemi = $_POST['odeme_yontemi'];
    $notlar = trim($_POST['notlar']);
    
    try {
        $pdo->beginTransaction();
        
        // Toplam tutarı hesapla
        $toplam_tutar = ($rezervasyon['toplam_fiyat'] ?? 0) + $toplam_ekstra + $ekstra_ucret - $indirim;
        
        // Rezervasyon durumunu güncelle
        $sql = "UPDATE rezervasyonlar SET 
                durum = 'check_out', 
                ekstra_ucret = ?,
                indirim = ?,
                son_toplam_fiyat = ?,
                gercek_cikis_tarihi = NOW(),
                notlar = CONCAT(IFNULL(notlar, ''), '\n', ?)
                WHERE id = ? AND durum = 'check_in'";
        
        if (!executeQuery($sql, [$ekstra_ucret, $indirim, $toplam_tutar, $notlar, $rezervasyon_id])) {
            throw new Exception('Rezervasyon güncellenirken hata oluştu.');
        }
        
        // Kalan ödeme varsa kaydet
        if ($odeme_miktari > 0) {
            $sql = "INSERT INTO rezervasyon_odemeleri (rezervasyon_id, odeme_tutari, odeme_yontemi, aciklama, kullanici_id, durum, odeme_tarihi) 
                    VALUES (?, ?, ?, 'Check-out ödemesi', ?, 'aktif', NOW())";
            
            if (!executeQuery($sql, [$rezervasyon_id, $odeme_miktari, $odeme_yontemi, $_SESSION['user_id']])) {
                throw new Exception('Ödeme kaydı eklenirken hata oluştu.');
            }
        }
        
        // Fatura oluştur
        $fatura_no = 'F' . date('Ymd') . str_pad($rezervasyon_id, 4, '0', STR_PAD_LEFT);
        $sql = "INSERT INTO faturalar (rezervasyon_id, fatura_no, toplam_tutar, olusturma_tarihi, admin_id) 
                VALUES (?, ?, ?, NOW(), ?)";
        
        if (!executeQuery($sql, [$rezervasyon_id, $fatura_no, $toplam_tutar, $_SESSION['user_id']])) {
            throw new Exception('Fatura oluşturulurken hata oluştu.');
        }
        
        // Rezervasyon geçmişi kaydı
        $sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem_tipi, aciklama, admin_id, islem_tarihi) 
                VALUES (?, 'check_out', 'Misafir check-out işlemi tamamlandı. Fatura No: $fatura_no', ?, NOW())";
        
        executeQuery($sql, [$rezervasyon_id, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        $success_message = 'Check-out işlemi başarıyla tamamlandı. Fatura No: ' . $fatura_no;
        
        // Başarılı işlem sonrası yönlendirme
        header('Location: resepsiyon.php?success=' . urlencode($success_message));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Check-out işlemi sırasında hata oluştu: ' . $e->getMessage();
    }
}

// Bugünkü check-out listesi
$bugun_checkout_listesi = fetchAll("
    SELECT r.*, m.ad, m.soyad, m.telefon, ot.oda_tipi_adi, od.oda_numarasi
    FROM rezervasyonlar r 
    JOIN musteriler m ON r.musteri_id = m.id 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id 
    WHERE DATE(r.cikis_tarihi) = CURDATE() AND r.durum = 'check_in'
    ORDER BY r.cikis_tarihi ASC
");

$page_title = 'Check-Out İşlemleri';
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
                                <i class="fas fa-sign-out-alt me-2"></i>Check-Out İşlemleri
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
        <!-- Check-Out Formu -->
        <?php if ($rezervasyon): ?>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-times me-2"></i>Misafir Check-Out
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
                                            <label class="form-label">Oda Numarası</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($rezervasyon['oda_numarasi']) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Check-In Tarihi</label>
                                            <input type="text" class="form-control" value="<?= isset($rezervasyon['gercek_giris_tarihi']) && $rezervasyon['gercek_giris_tarihi'] ? date('d.m.Y H:i', strtotime($rezervasyon['gercek_giris_tarihi'])) : 'Belirtilmemiş' ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Konaklama Süresi</label>
                                            <input type="text" class="form-control" value="<?= $rezervasyon['konaklama_gun'] ?> gün" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fatura Detayları -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="mb-3">Fatura Detayları</h4>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Açıklama</th>
                                                <th class="text-end">Tutar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Konaklama Ücreti (<?= $rezervasyon['konaklama_gun'] ?> gün)</td>
                                                <td class="text-end"><?= number_format($rezervasyon['toplam_tutar'] ?? 0, 2) ?> TL</td>
                                            </tr>
                                            <?php if (!empty($ekstra_hizmetler)): ?>
                                                <?php foreach ($ekstra_hizmetler as $hizmet): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($hizmet['hizmet_adi']) ?> (<?= $hizmet['adet'] ?> adet)</td>
                                                        <td class="text-end"><?= number_format($hizmet['fiyat'] * $hizmet['adet'], 2) ?> TL</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <tr>
                                                <td><strong>Ara Toplam</strong></td>
                                                <td class="text-end"><strong><?= number_format(($rezervasyon['toplam_tutar'] ?? 0) + $toplam_ekstra, 2) ?> TL</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Ek Ücretler ve İndirimler -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="mb-3">Ek Ücretler ve İndirimler</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ekstra Ücret (Hasar, Ceza vb.)</label>
                                            <input type="number" name="ekstra_ucret" class="form-control" step="0.01" min="0" value="0" id="ekstra_ucret">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">İndirim</label>
                                            <input type="number" name="indirim" class="form-control" step="0.01" min="0" value="0" id="indirim">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ödeme Durumu -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="mb-3">Ödeme Durumu</h4>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Toplam Tutar</label>
                                            <input type="text" class="form-control" id="toplam_tutar" value="<?= number_format(($rezervasyon['toplam_tutar'] ?? 0) + $toplam_ekstra, 2) ?> TL" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Ödenen Tutar</label>
                                            <input type="text" class="form-control" value="<?= number_format($toplam_odenen, 2) ?> TL" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Kalan Tutar</label>
                                            <input type="text" class="form-control" id="kalan_tutar" value="<?= number_format(max(0, ($rezervasyon['toplam_tutar'] ?? 0) + $toplam_ekstra - $toplam_odenen), 2) ?> TL" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tahsil Edilecek Tutar</label>
                                            <input type="number" name="odeme_miktari" class="form-control" step="0.01" min="0" value="<?= max(0, ($rezervasyon['toplam_tutar'] ?? 0) + $toplam_ekstra - $toplam_odenen) ?>" id="odeme_miktari">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ödeme Yöntemi</label>
                                            <select name="odeme_yontemi" class="form-select">
                                                <option value="nakit">Nakit</option>
                                                <option value="kredi_karti">Kredi Kartı</option>
                                                <option value="banka_havalesi">Banka Havalesi</option>
                                                <option value="pos">POS</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Önceki Ödemeler -->
                        <?php if (!empty($odemeler)): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <h4 class="mb-3">Önceki Ödemeler</h4>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Tarih</th>
                                                <th>Tutar</th>
                                                <th>Yöntem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($odemeler as $odeme): ?>
                                                <tr>
                                                    <td><?= date('d.m.Y H:i', strtotime($odeme['odeme_tarihi'])) ?></td>
                                                    <td><?= number_format($odeme['miktar'], 2) ?> TL</td>
                                                    <td><?= htmlspecialchars($odeme['odeme_yontemi']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Notlar -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Check-Out Notları</label>
                                    <textarea name="notlar" class="form-control" rows="3" placeholder="Check-out ile ilgili notlar..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" name="checkout_submit" class="btn btn-success">
                                <i class="fas fa-sign-out-alt me-1"></i>Check-Out Tamamla ve Fatura Oluştur
                            </button>
                            <a href="resepsiyon.php" class="btn btn-link ms-auto">İptal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bugünkü Check-Out Listesi -->
        <div class="<?= $rezervasyon ? 'col-lg-4' : 'col-12' ?>">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list me-2"></i>Bugünkü Check-Out Listesi
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($bugun_checkout_listesi)): ?>
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
                                    <?php foreach ($bugun_checkout_listesi as $item): ?>
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
                                                <span class="badge bg-blue"><?= htmlspecialchars($item['oda_numarasi']) ?></span>
                                            </td>
                                            <td><?= date('H:i', strtotime($item['cikis_tarihi'])) ?></td>
                                            <td>
                                                <?php if (!$rezervasyon || $rezervasyon['id'] != $item['id']): ?>
                                                    <a href="resepsiyon-checkout.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-success">
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
    const ekstraUcret = document.getElementById('ekstra_ucret');
    const indirim = document.getElementById('indirim');
    const toplamTutar = document.getElementById('toplam_tutar');
    const kalanTutar = document.getElementById('kalan_tutar');
    const odemeMiktari = document.getElementById('odeme_miktari');
    
    const baseTotal = <?= $rezervasyon ? (($rezervasyon['toplam_tutar'] ?? 0) + $toplam_ekstra) : 0 ?>;
    const toplamOdenen = <?= $toplam_odenen ?>;
    
    function hesaplaTutar() {
        const ekstra = parseFloat(ekstraUcret.value) || 0;
        const ind = parseFloat(indirim.value) || 0;
        const yeniToplam = baseTotal + ekstra - ind;
        const kalan = Math.max(0, yeniToplam - toplamOdenen);
        
        toplamTutar.value = yeniToplam.toFixed(2) + ' TL';
        kalanTutar.value = kalan.toFixed(2) + ' TL';
        odemeMiktari.value = kalan.toFixed(2);
    }
    
    if (ekstraUcret && indirim) {
        ekstraUcret.addEventListener('input', hesaplaTutar);
        indirim.addEventListener('input', hesaplaTutar);
    }
});
</script>

<?php include 'footer.php'; ?>