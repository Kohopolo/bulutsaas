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

$success_message = '';
$error_message = '';

// Ekstra servis ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ekstra_servis_ekle'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $servis_turu = $_POST['servis_turu'];
    $servis_aciklamasi = trim($_POST['servis_aciklamasi']);
    $miktar = floatval($_POST['miktar']);
    $birim_fiyat = floatval($_POST['birim_fiyat']);
    $toplam_fiyat = $miktar * $birim_fiyat;
    
    try {
        $pdo->beginTransaction();
        
        // Ekstra servis kaydı
        $sql = "INSERT INTO ekstra_servisler (rezervasyon_id, servis_adi, servis_turu, servis_fiyati, toplam_fiyat, servis_tarihi, aciklama, kullanici_id) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
        
        if (!executeQuery($sql, [$rezervasyon_id, $servis_turu, $servis_turu, $birim_fiyat, $toplam_fiyat, $servis_aciklamasi, $_SESSION['user_id']])) {
            throw new Exception('Ekstra servis kaydedilirken hata oluştu.');
        }
        
        // Rezervasyon toplam fiyatını güncelle
        $sql = "UPDATE rezervasyonlar SET toplam_tutar = toplam_tutar + ? WHERE id = ?";
        executeQuery($sql, [$toplam_fiyat, $rezervasyon_id]);
        
        // Rezervasyon geçmişi kaydı
        $sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem_tipi, aciklama, admin_id, islem_tarihi) 
                VALUES (?, 'ekstra_servis', ?, ?, NOW())";
        executeQuery($sql, [$rezervasyon_id, "Ekstra servis eklendi: {$servis_turu} - {$toplam_fiyat} TL", $_SESSION['user_id']]);
        
        $pdo->commit();
        $success_message = 'Ekstra servis başarıyla eklendi.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Ekstra servis eklenirken hata oluştu: ' . $e->getMessage();
    }
}

// Şikayet/Talep ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sikayet_talep_ekle'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $tip = $_POST['tip'];
    $kategori = $_POST['kategori'];
    $konu = trim($_POST['konu']);
    $aciklama = trim($_POST['aciklama']);
    $oncelik = $_POST['oncelik'];
    
    try {
        $pdo->beginTransaction();
        
        // Şikayet/Talep kaydı
        $sql = "INSERT INTO sikayet_talepler (rezervasyon_id, tip, kategori, konu, aciklama, oncelik, durum, olusturma_tarihi, admin_id) 
                VALUES (?, ?, ?, ?, ?, ?, 'yeni', NOW(), ?)";
        
        if (!executeQuery($sql, [$rezervasyon_id, $tip, $kategori, $konu, $aciklama, $oncelik, $_SESSION['user_id']])) {
            throw new Exception('Şikayet/Talep kaydedilirken hata oluştu.');
        }
        
        $sikayet_id = $pdo->lastInsertId();
        
        // Rezervasyon geçmişi kaydı
        $sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem_tipi, aciklama, admin_id, islem_tarihi) 
                VALUES (?, 'sikayet_talep', ?, ?, NOW())";
        executeQuery($sql, [$rezervasyon_id, "{$tip} kaydedildi: {$konu}", $_SESSION['user_id']]);
        
        $pdo->commit();
        $success_message = ucfirst($tip) . ' başarıyla kaydedildi.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = ucfirst($tip) . ' kaydedilirken hata oluştu: ' . $e->getMessage();
    }
}

// Şikayet/Talep durumu güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['durum_guncelle'])) {
    $sikayet_id = intval($_POST['sikayet_id']);
    $yeni_durum = $_POST['yeni_durum'];
    $cevap = trim($_POST['cevap']);
    
    try {
        $pdo->beginTransaction();
        
        // Durum güncelleme
        $sql = "UPDATE sikayet_talepler SET durum = ?, cevap = ?, cevap_tarihi = NOW(), cevaplayan_admin_id = ? WHERE id = ?";
        
        if (!executeQuery($sql, [$yeni_durum, $cevap, $_SESSION['user_id'], $sikayet_id])) {
            throw new Exception('Durum güncellenirken hata oluştu.');
        }
        
        $pdo->commit();
        $success_message = 'Durum başarıyla güncellendi.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Durum güncellenirken hata oluştu: ' . $e->getMessage();
    }
}

// Aktif rezervasyonlar
$aktif_rezervasyonlar = fetchAll("
    SELECT r.id, r.giris_tarihi, r.cikis_tarihi, r.durum,
           m.ad, m.soyad, m.telefon,
           od.oda_numarasi, ot.oda_tipi_adi
    FROM rezervasyonlar r
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    WHERE r.durum IN ('onaylandi', 'check_in')
    ORDER BY r.giris_tarihi DESC
");

// Bekleyen şikayet/talepler
$bekleyen_sikayetler = fetchAll("
    SELECT st.*, r.giris_tarihi, r.cikis_tarihi,
           m.ad, m.soyad, m.telefon,
           od.oda_numarasi, ot.oda_tipi_adi
    FROM sikayet_talepler st
    LEFT JOIN rezervasyonlar r ON st.rezervasyon_id = r.id
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    WHERE st.durum IN ('yeni', 'isleme_alindi')
    ORDER BY st.oncelik DESC, st.olusturma_tarihi ASC
");

// Son ekstra servisler
$son_ekstra_servisler = fetchAll("
    SELECT es.*, r.giris_tarihi, r.cikis_tarihi,
           m.ad, m.soyad, m.telefon,
           od.oda_numarasi, ot.oda_tipi_adi
    FROM ekstra_servisler es
    LEFT JOIN rezervasyonlar r ON es.rezervasyon_id = r.id
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN oda_numaralari od ON r.oda_numarasi_id = od.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    WHERE DATE(es.servis_tarihi) = CURDATE()
    ORDER BY es.servis_tarihi DESC
");

$page_title = 'Misafir Hizmetleri';
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
                                <i class="fas fa-concierge-bell me-2"></i>Misafir Hizmetleri
                            </h2>
                        </div>
                        <div class="col-auto ms-auto d-print-none">
                            <div class="btn-list">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ekstraServisModal">
                                    <i class="fas fa-plus me-1"></i>Ekstra Servis
                                </button>
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sikayetTalepModal">
                                    <i class="fas fa-comment-alt me-1"></i>Şikayet/Talep
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
        <!-- Bekleyen Şikayet/Talepler -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Bekleyen Şikayet/Talepler
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($bekleyen_sikayetler)): ?>
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="fas fa-smile"></i>
                            </div>
                            <p class="empty-title">Bekleyen şikayet/talep yok</p>
                            <p class="empty-subtitle text-muted">
                                Tüm şikayet ve talepler çözülmüş durumda.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter">
                                <thead>
                                    <tr>
                                        <th>Misafir</th>
                                        <th>Oda</th>
                                        <th>Tip</th>
                                        <th>Konu</th>
                                        <th>Öncelik</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bekleyen_sikayetler as $sikayet): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($sikayet['ad'] . ' ' . $sikayet['soyad']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($sikayet['telefon']) ?></small>
                                            </td>
                                            <td>
                                                <strong>Oda <?= $sikayet['oda_numarasi'] ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($sikayet['oda_tipi_adi']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $sikayet['tip'] == 'sikayet' ? 'danger' : 'info' ?>">
                                                    <?= ucfirst($sikayet['tip']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($sikayet['konu']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($sikayet['kategori']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $sikayet['oncelik'] == 'yuksek' ? 'danger' : 
                                                    ($sikayet['oncelik'] == 'orta' ? 'warning' : 'secondary') 
                                                ?>">
                                                    <?= ucfirst($sikayet['oncelik']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d.m.Y H:i', strtotime($sikayet['olusturma_tarihi'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-success" onclick="durumGuncelle(<?= $sikayet['id'] ?>, '<?= htmlspecialchars($sikayet['konu']) ?>')">
                                                    <i class="fas fa-check me-1"></i>Çözüm
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="detayGoster(<?= $sikayet['id'] ?>)">
                                                    <i class="fas fa-eye me-1"></i>Detay
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
        </div>

        <!-- Bugünkü Ekstra Servisler -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-plus-circle me-2"></i>Bugünkü Ekstra Servisler
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($son_ekstra_servisler)): ?>
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <p class="empty-title">Bugün ekstra servis yok</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($son_ekstra_servisler as $servis): ?>
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <span class="avatar bg-primary text-white">
                                                <?= $servis['oda_numarasi'] ?>
                                            </span>
                                        </div>
                                        <div class="col text-truncate">
                                            <strong><?= htmlspecialchars($servis['servis_turu']) ?></strong><br>
                                            <div class="text-muted"><?= htmlspecialchars($servis['ad'] . ' ' . $servis['soyad']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($servis['aciklama'] ?? '') ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="text-end">
                                                <strong><?= number_format($servis['toplam_fiyat'], 2) ?> TL</strong><br>
                                                <small class="text-muted"><?= date('H:i', strtotime($servis['servis_tarihi'])) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ekstra Servis Modal -->
<div class="modal modal-blur fade" id="ekstraServisModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="modal-header">
                    <h4 class="modal-title">Ekstra Servis Ekle</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Rezervasyon Seçiniz</label>
                                <select name="rezervasyon_id" class="form-select" required>
                                    <option value="">Rezervasyon Seçiniz</option>
                                    <?php foreach ($aktif_rezervasyonlar as $rez): ?>
                                        <option value="<?= $rez['id'] ?>">
                                            Oda <?= $rez['oda_numarasi'] ?> - <?= htmlspecialchars($rez['ad'] . ' ' . $rez['soyad']) ?> 
                                            (<?= date('d.m.Y', strtotime($rez['giris_tarihi'])) ?> - <?= date('d.m.Y', strtotime($rez['cikis_tarihi'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Servis Türü</label>
                                <select name="servis_turu" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <option value="yemek_icecek">Yemek & İçecek</option>
                                    <option value="oda_servisi">Oda Servisi</option>
                                    <option value="camasir">Çamaşır Hizmeti</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="spa_wellness">SPA & Wellness</option>
                                    <option value="ekstra_yatak">Ekstra Yatak</option>
                                    <option value="minibar">Minibar</option>
                                    <option value="telefon">Telefon</option>
                                    <option value="diger">Diğer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Miktar</label>
                                <input type="number" name="miktar" class="form-control" min="1" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Birim Fiyat (TL)</label>
                                <input type="number" name="birim_fiyat" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Toplam Fiyat (TL)</label>
                                <input type="text" class="form-control" id="toplam_fiyat_display" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Servis Açıklaması</label>
                                <textarea name="servis_aciklamasi" class="form-control" rows="3" required placeholder="Servis detaylarını açıklayınız..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">İptal</a>
                    <button type="submit" name="ekstra_servis_ekle" class="btn btn-primary ms-auto">
                        <i class="fas fa-plus me-1"></i>Servis Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Şikayet/Talep Modal -->
<div class="modal modal-blur fade" id="sikayetTalepModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="modal-header">
                    <h4 class="modal-title">Şikayet/Talep Ekle</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Rezervasyon Seçiniz</label>
                                <select name="rezervasyon_id" class="form-select" required>
                                    <option value="">Rezervasyon Seçiniz</option>
                                    <?php foreach ($aktif_rezervasyonlar as $rez): ?>
                                        <option value="<?= $rez['id'] ?>">
                                            Oda <?= $rez['oda_numarasi'] ?> - <?= htmlspecialchars($rez['ad'] . ' ' . $rez['soyad']) ?> 
                                            (<?= date('d.m.Y', strtotime($rez['giris_tarihi'])) ?> - <?= date('d.m.Y', strtotime($rez['cikis_tarihi'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tip</label>
                                <select name="tip" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <option value="sikayet">Şikayet</option>
                                    <option value="talep">Talep</option>
                                    <option value="oneri">Öneri</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="kategori" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <option value="oda">Oda</option>
                                    <option value="temizlik">Temizlik</option>
                                    <option value="yemek">Yemek</option>
                                    <option value="servis">Servis</option>
                                    <option value="personel">Personel</option>
                                    <option value="teknik">Teknik</option>
                                    <option value="diger">Diğer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Öncelik</label>
                                <select name="oncelik" class="form-select" required>
                                    <option value="dusuk">Düşük</option>
                                    <option value="orta" selected>Orta</option>
                                    <option value="yuksek">Yüksek</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Konu</label>
                                <input type="text" name="konu" class="form-control" required placeholder="Şikayet/Talep konusu...">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Açıklama</label>
                                <textarea name="aciklama" class="form-control" rows="4" required placeholder="Detaylı açıklama..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">İptal</a>
                    <button type="submit" name="sikayet_talep_ekle" class="btn btn-primary ms-auto">
                        <i class="fas fa-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Durum Güncelleme Modal -->
<div class="modal modal-blur fade" id="durumGuncelleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="sikayet_id" id="guncelle_sikayet_id">
                
                <div class="modal-header">
                    <h4 class="modal-title">Durum Güncelle</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Yeni Durum</label>
                                <select name="yeni_durum" class="form-select" required>
                                    <option value="isleme_alindi">İşleme Alındı</option>
                                    <option value="cozuldu">Çözüldü</option>
                                    <option value="iptal">İptal</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Cevap/Açıklama</label>
                                <textarea name="cevap" class="form-control" rows="4" required placeholder="Yapılan işlem ve açıklama..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">İptal</a>
                    <button type="submit" name="durum_guncelle" class="btn btn-success ms-auto">
                        <i class="fas fa-check me-1"></i>Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toplam fiyat hesaplama
    const miktarInput = document.querySelector('input[name="miktar"]');
    const birimFiyatInput = document.querySelector('input[name="birim_fiyat"]');
    const toplamFiyatDisplay = document.getElementById('toplam_fiyat_display');
    
    function hesaplaToplamFiyat() {
        const miktar = parseFloat(miktarInput.value) || 0;
        const birimFiyat = parseFloat(birimFiyatInput.value) || 0;
        const toplam = miktar * birimFiyat;
        toplamFiyatDisplay.value = toplam.toFixed(2) + ' TL';
    }
    
    miktarInput.addEventListener('input', hesaplaToplamFiyat);
    birimFiyatInput.addEventListener('input', hesaplaToplamFiyat);
});

function durumGuncelle(sikayetId, konu) {
    document.getElementById('guncelle_sikayet_id').value = sikayetId;
    const modal = new bootstrap.Modal(document.getElementById('durumGuncelleModal'));
    modal.show();
}

function detayGoster(sikayetId) {
    // Detay gösterme işlevi - gelecekte AJAX ile detay getirilebilir
    alert('Detay gösterme özelliği yakında eklenecek.');
}
</script>

<?php include 'footer.php'; ?>