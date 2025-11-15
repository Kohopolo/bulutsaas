<?php
require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(403);
    exit('Yetkisiz erişim');
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('rezervasyon_goruntule')) {
    http_response_code(403);
    exit('Rezervasyon görüntüleme yetkiniz bulunmamaktadır');
}

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    exit('Geçersiz ID');
}

// Silinen rezervasyon detaylarını getir
$rezervasyon = fetchOne("
    SELECT sr.*, ot.oda_tipi_adi, odn.oda_numarasi, odn.kat,
           m.ad as musteri_adi, m.soyad as musteri_soyadi, 
           m.email as musteri_email, m.telefon as musteri_telefon,
           m.tc_kimlik as musteri_tc, m.adres as musteri_adres,
           COALESCE(s.ad, 'Web Site') as sales_ad, 
           COALESCE(s.soyad, '') as sales_soyad
    FROM silinen_rezervasyonlar sr 
    LEFT JOIN oda_tipleri ot ON sr.oda_tipi_id = ot.id 
    LEFT JOIN oda_numaralari odn ON sr.oda_numarasi_id = odn.id 
    LEFT JOIN musteriler m ON sr.musteri_id = m.id
    LEFT JOIN kullanicilar s ON sr.satis_elemani_id = s.id AND s.rol IN ('sales', 'admin', 'superadmin', 'ekip')
    WHERE sr.id = ?
", [$id]);

if (!$rezervasyon) {
    http_response_code(404);
    exit('Rezervasyon bulunamadı');
}

// Çocuk yaşlarını çöz
$cocuk_yaslari = [];
if ($rezervasyon['cocuk_yaslari']) {
    $cocuk_yaslari = json_decode($rezervasyon['cocuk_yaslari'], true) ?: [];
}

// Yetişkin detaylarını çöz
$yetiskin_detaylari = [];
if ($rezervasyon['yetiskin_detaylari']) {
    $yetiskin_detaylari = json_decode($rezervasyon['yetiskin_detaylari'], true) ?: [];
}

// Çocuk detaylarını çöz
$cocuk_detaylari = [];
if ($rezervasyon['cocuk_detaylari']) {
    $cocuk_detaylari = json_decode($rezervasyon['cocuk_detaylari'], true) ?: [];
}

// Ek hizmetleri getir
$ek_hizmetler = [];
if (isset($rezervasyon['ek_hizmetler']) && $rezervasyon['ek_hizmetler']) {
    $ek_hizmet_ids = json_decode($rezervasyon['ek_hizmetler'], true);
    $ek_hizmet_ids = $ek_hizmet_ids ?? [];
    if (is_array($ek_hizmet_ids) && !empty($ek_hizmet_ids)) {
        $placeholders = str_repeat('?,', count($ek_hizmet_ids) - 1) . '?';
        $ek_hizmetler = fetchAll("SELECT * FROM hizmetler WHERE id IN ($placeholders)", $ek_hizmet_ids);
    }
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-primary mb-3">Rezervasyon Bilgileri</h6>
        <table class="table table-borderless">
            <tr>
                <td><strong>Rezervasyon Kodu:</strong></td>
                <td><span class="badge bg-primary"><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?></span></td>
            </tr>
            <tr>
                <td><strong>Orijinal ID:</strong></td>
                <td><span class="badge bg-secondary">#<?php echo $rezervasyon['orijinal_id']; ?></span></td>
            </tr>
            <tr>
                <td><strong>Oda Tipi:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></td>
            </tr>
            <?php if ($rezervasyon['oda_numarasi']): ?>
            <tr>
                <td><strong>Oda Numarası:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['oda_numarasi']); ?> (Kat: <?php echo $rezervasyon['kat']; ?>)</td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Giriş Tarihi:</strong></td>
                <td><?php echo formatTurkishDate($rezervasyon['giris_tarihi'], 'd F Y'); ?></td>
            </tr>
            <tr>
                <td><strong>Çıkış Tarihi:</strong></td>
                <td><?php echo formatTurkishDate($rezervasyon['cikis_tarihi'], 'd F Y'); ?></td>
            </tr>
            <tr>
                <td><strong>Gece Sayısı:</strong></td>
                <td><?php echo date_diff(date_create($rezervasyon['giris_tarihi']), date_create($rezervasyon['cikis_tarihi']))->days; ?> gece</td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-primary mb-3">Müşteri Bilgileri</h6>
        <table class="table table-borderless">
            <tr>
                <td><strong>Ad Soyad:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?></td>
            </tr>
            <tr>
                <td><strong>E-posta:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['musteri_email']); ?></td>
            </tr>
            <tr>
                <td><strong>Telefon:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['musteri_telefon']); ?></td>
            </tr>
            <?php if ($rezervasyon['musteri_tc']): ?>
            <tr>
                <td><strong>TC Kimlik:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['musteri_tc']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($rezervasyon['musteri_adres']): ?>
            <tr>
                <td><strong>Adres:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['musteri_adres']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <h6 class="text-primary mb-3">Misafir Bilgileri</h6>
        <table class="table table-borderless">
            <tr>
                <td><strong>Yetişkin Sayısı:</strong></td>
                <td><?php echo $rezervasyon['yetiskin_sayisi']; ?></td>
            </tr>
            <tr>
                <td><strong>Çocuk Sayısı:</strong></td>
                <td><?php echo $rezervasyon['cocuk_sayisi']; ?></td>
            </tr>
            <?php if (!empty($cocuk_yaslari)): ?>
            <tr>
                <td><strong>Çocuk Yaşları:</strong></td>
                <td><?php echo implode(', ', $cocuk_yaslari); ?> yaş</td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-primary mb-3">Ödeme Bilgileri</h6>
        <table class="table table-borderless">
            <tr>
                <td><strong>Toplam Tutar:</strong></td>
                <td><span class="text-success fw-bold"><?php echo number_format($rezervasyon['toplam_tutar'], 2); ?>₺</span></td>
            </tr>
            <tr>
                <td><strong>Ödenen Tutar:</strong></td>
                <td><span class="text-info"><?php echo number_format($rezervasyon['odenen_tutar'], 2); ?>₺</span></td>
            </tr>
            <tr>
                <td><strong>Kalan Tutar:</strong></td>
                <td><span class="text-warning"><?php echo number_format($rezervasyon['kalan_tutar'], 2); ?>₺</span></td>
            </tr>
            <tr>
                <td><strong>Ödeme Durumu:</strong></td>
                <td>
                    <?php
                    $odeme_durumu_class = [
                        'odenmedi' => 'danger',
                        'kismi_odeme' => 'warning',
                        'odendi' => 'success'
                    ];
                    ?>
                    <span class="badge bg-<?php echo $odeme_durumu_class[$rezervasyon['odeme_durumu']] ?? 'secondary'; ?>">
                        <?php echo ucfirst($rezervasyon['odeme_durumu']); ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <h6 class="text-primary mb-3">Rezervasyon Durumu</h6>
        <table class="table table-borderless">
            <tr>
                <td><strong>Durum:</strong></td>
                <td>
                    <?php
                    $durum_class = [
                        'beklemede' => 'warning',
                        'onaylandi' => 'info',
                        'check_in' => 'primary',
                        'check_out' => 'success',
                        'iptal' => 'danger',
                        'tamamlandi' => 'success'
                    ];
                    ?>
                    <span class="badge bg-<?php echo $durum_class[$rezervasyon['durum']] ?? 'secondary'; ?>">
                        <?php echo ucfirst($rezervasyon['durum']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Satış Elemanı:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['sales_ad'] . ' ' . $rezervasyon['sales_soyad']); ?></td>
            </tr>
            <tr>
                <td><strong>Oluşturma Tarihi:</strong></td>
                <td><?php echo formatTurkishDate($rezervasyon['olusturma_tarihi'], 'd.m.Y H:i'); ?></td>
            </tr>
            <?php if ($rezervasyon['gercek_giris_tarihi']): ?>
            <tr>
                <td><strong>Gerçek Giriş:</strong></td>
                <td><?php echo formatTurkishDate($rezervasyon['gercek_giris_tarihi'], 'd.m.Y H:i'); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($rezervasyon['gercek_cikis_tarihi']): ?>
            <tr>
                <td><strong>Gerçek Çıkış:</strong></td>
                <td><?php echo formatTurkishDate($rezervasyon['gercek_cikis_tarihi'], 'd.m.Y H:i'); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-primary mb-3">Silme Bilgileri</h6>
        <table class="table table-borderless">
            <tr>
                <td><strong>Silme Tarihi:</strong></td>
                <td><?php echo formatTurkishDate($rezervasyon['silme_tarihi'], 'd.m.Y H:i'); ?></td>
            </tr>
            <tr>
                <td><strong>Silen Kullanıcı:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['silen_kullanici_adi']); ?></td>
            </tr>
            <tr>
                <td><strong>Silme Nedeni:</strong></td>
                <td><?php echo htmlspecialchars($rezervasyon['silme_nedeni']); ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if (!empty($ek_hizmetler)): ?>
<div class="row mt-4">
    <div class="col-12">
        <h6 class="text-primary mb-3">Ek Hizmetler</h6>
        <div class="row">
            <?php foreach ($ek_hizmetler as $hizmet): ?>
                <div class="col-md-4 mb-2">
                    <div class="card">
                        <div class="card-body p-2">
                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($hizmet['hizmet_adi']); ?></h6>
                            <p class="card-text text-muted mb-1"><?php echo htmlspecialchars($hizmet['aciklama']); ?></p>
                            <span class="badge bg-success"><?php echo number_format($hizmet['fiyat'], 2); ?>₺</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($rezervasyon['notlar']): ?>
<div class="row mt-4">
    <div class="col-12">
        <h6 class="text-primary mb-3">Notlar</h6>
        <div class="alert alert-info">
            <?php echo nl2br(htmlspecialchars($rezervasyon['notlar'])); ?>
        </div>
    </div>
</div>
<?php endif; ?>
