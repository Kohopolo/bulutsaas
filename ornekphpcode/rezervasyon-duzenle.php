<?php
/**
 * Rezervasyon Düzenleme Sayfası
 * Otel Rezervasyon Sistemi - Admin Panel
 */

// Output buffering başlat
ob_start();

// Hata raporlamayı etkinleştir (geliştirme için)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Güvenlik ve gerekli dosyaları dahil et
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/price-functions.php';
require_once __DIR__ . '/../includes/pdf-generator.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once __DIR__ . '/../includes/detailed_permission_functions.php';
requireDetailedPermission('rezervasyon_duzenle', 'Rezervasyon düzenleme yetkiniz bulunmamaktadır.');

// Rezervasyon ID kontrolü
$rezervasyon_id = intval($_GET['id'] ?? 0);
if (!$rezervasyon_id) {
    header('Location: rezervasyonlar.php');
    exit;
}

// Rezervasyon bilgilerini getir
$rezervasyon = fetchOne("
    SELECT r.*, m.ad as musteri_ad, m.soyad as musteri_soyad, m.email as musteri_email, 
           m.telefon as musteri_telefon, ot.oda_tipi_adi, odn.oda_numarasi
    FROM rezervasyonlar r
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id
    WHERE r.id = ?
", [$rezervasyon_id]);

if (!$rezervasyon) {
    header('Location: rezervasyonlar.php');
    exit;
}

// Misafir detaylarını çöz
$yetiskin_detaylari = [];
$cocuk_detaylari = [];

if (!empty($rezervasyon['yetiskin_detaylari'])) {
    $yetiskin_detaylari = json_decode($rezervasyon['yetiskin_detaylari'], true) ?: [];
}

if (!empty($rezervasyon['cocuk_detaylari'])) {
    $cocuk_detaylari = json_decode($rezervasyon['cocuk_detaylari'], true) ?: [];
}

// Form gönderildiğinde işle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reservation'])) {
    // Debug logging
    error_log("DEBUG: Form submission started for reservation ID: " . $rezervasyon_id);
    
    // CSRF token kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        error_log("DEBUG: CSRF token validation failed");
        $error = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        error_log("DEBUG: CSRF token validated successfully");
        try {
            // Form verilerini al ve temizle
            $giris_tarihi = $_POST['giris_tarihi'] ?? '';
            $cikis_tarihi = $_POST['cikis_tarihi'] ?? '';
            $yetiskin_sayisi = intval($_POST['yetiskin_sayisi'] ?? 1);
            $cocuk_sayisi = intval($_POST['cocuk_sayisi'] ?? 0);
            $toplam_tutar = floatval($_POST['toplam_tutar'] ?? 0);
            $odenen_tutar = floatval($_POST['odenen_tutar'] ?? 0);
            $durum = $_POST['durum'] ?? 'beklemede';
            $odeme_durumu = $_POST['odeme_durumu'] ?? 'odenmedi';
            $ozel_istekler = trim($_POST['ozel_istekler'] ?? '');
            $notlar = trim($_POST['notlar'] ?? '');

            // Misafir detaylarını işle
            $yetiskin_detaylari_json = '';
            $cocuk_detaylari_json = '';

            // Yetişkin detayları
            if (isset($_POST['yetiskin_ad']) && is_array($_POST['yetiskin_ad'])) {
                $yetiskin_detaylari_array = [];
                for ($i = 0; $i < count($_POST['yetiskin_ad']); $i++) {
                    if (!empty($_POST['yetiskin_ad'][$i])) {
                        $yetiskin_detaylari_array[] = [
                            'ad' => trim($_POST['yetiskin_ad'][$i]),
                            'soyad' => trim($_POST['yetiskin_soyad'][$i] ?? ''),
                            'cinsiyet' => $_POST['yetiskin_cinsiyet'][$i] ?? 'erkek',
                            'tc_kimlik' => trim($_POST['yetiskin_tc'][$i] ?? '')
                        ];
                    }
                }
                $yetiskin_detaylari_json = json_encode($yetiskin_detaylari_array, JSON_UNESCAPED_UNICODE);
            }

            // Çocuk detayları
            if (isset($_POST['cocuk_ad']) && is_array($_POST['cocuk_ad'])) {
                $cocuk_detaylari_array = [];
                for ($i = 0; $i < count($_POST['cocuk_ad']); $i++) {
                    if (!empty($_POST['cocuk_ad'][$i])) {
                        $cocuk_detaylari_array[] = [
                            'ad' => trim($_POST['cocuk_ad'][$i]),
                            'soyad' => trim($_POST['cocuk_soyad'][$i] ?? ''),
                            'cinsiyet' => $_POST['cocuk_cinsiyet'][$i] ?? 'erkek',
                            'yas' => intval($_POST['cocuk_yas'][$i] ?? 0)
                        ];
                    }
                }
                $cocuk_detaylari_json = json_encode($cocuk_detaylari_array, JSON_UNESCAPED_UNICODE);
            }

            // Rezervasyonu güncelle
            error_log("DEBUG: About to execute database update query");
            $update_result = executeQuery("
                UPDATE rezervasyonlar 
                SET giris_tarihi = ?, cikis_tarihi = ?, yetiskin_sayisi = ?, cocuk_sayisi = ?,
                    toplam_tutar = ?, odenen_tutar = ?, durum = ?, odeme_durumu = ?,
                    ozel_istekler = ?, notlar = ?, yetiskin_detaylari = ?, cocuk_detaylari = ?, 
                    guncelleme_tarihi = NOW()
                WHERE id = ?
            ", [
                $giris_tarihi, $cikis_tarihi, $yetiskin_sayisi, $cocuk_sayisi,
                $toplam_tutar, $odenen_tutar, $durum, $odeme_durumu,
                $ozel_istekler, $notlar, $yetiskin_detaylari_json, $cocuk_detaylari_json, $rezervasyon_id
            ]);
            error_log("DEBUG: Database update result: " . ($update_result ? 'SUCCESS' : 'FAILED'));

            if ($update_result) {
                error_log("DEBUG: Starting reservation history and PDF generation");
                
                // Rezervasyon geçmişine kaydet
                executeQuery("
                    INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, olusturma_tarihi)
                    VALUES (?, 'guncellendi', 'Rezervasyon bilgileri güncellendi', NOW())
                ", [$rezervasyon_id]);
                error_log("DEBUG: Reservation history saved");

                // PDF'leri yeniden oluştur ve arşivle
                try {
                    error_log("PDF oluşturma başlıyor - Rezervasyon ID: " . $rezervasyon_id);
                    
                    $pdfGenerator = new PDFGenerator($pdo);
                    
                    // Voucher PDF'i oluştur
                    $voucher_result = $pdfGenerator->generateReservationVoucher($rezervasyon_id, $_SESSION['admin_id']);
                    error_log("Voucher sonucu: " . json_encode($voucher_result));
                    
                    // Contract PDF'i oluştur
                    $contract_result = $pdfGenerator->generateContract($rezervasyon_id, $_SESSION['admin_id']);
                    error_log("Contract sonucu: " . json_encode($contract_result));
                    
                    // Her iki PDF de başarılı mı kontrol et
                    if ($voucher_result['success'] && $contract_result['success']) {
                        if ($voucher_result['archived'] && $contract_result['archived']) {
                            $success_message = "Rezervasyon güncellendi ve PDF arşivi başarıyla oluşturuldu.";
                        } else {
                            $success_message = "Rezervasyon güncellendi ancak PDF arşivleme sırasında sorun oluştu.";
                        }
                    } else {
                        $error_message = "Rezervasyon güncellendi ancak PDF oluşturma başarısız: ";
                        if (!$voucher_result['success']) {
                            $error_message .= "Voucher: " . ($voucher_result['error'] ?? 'Bilinmeyen hata') . " ";
                        }
                        if (!$contract_result['success']) {
                            $error_message .= "Contract: " . ($contract_result['error'] ?? 'Bilinmeyen hata');
                        }
                    }
                } catch (Exception $e) {
                    error_log("PDF oluşturma hatası: " . $e->getMessage());
                    $error_message = "Rezervasyon güncellendi ancak PDF arşivi oluşturulamadı: " . $e->getMessage();
                }
                
                // Başarılı güncelleme sonrası rezervasyonlar sayfasına yönlendir
                error_log("DEBUG: About to redirect to rezervasyonlar.php");
                ob_end_clean(); // Output buffer'ı temizle
                error_log("DEBUG: Output buffer cleaned, sending redirect header");
                header('Location: rezervasyonlar.php?success=' . urlencode($success_message));
                error_log("DEBUG: Redirect header sent, calling exit");
                exit;
            } else {
                error_log("DEBUG: Database update failed");
                $error = 'Rezervasyon güncellenirken bir hata oluştu.';
            }

        } catch (Exception $e) {
            error_log("DEBUG: Exception caught: " . $e->getMessage());
            error_log("DEBUG: Exception file: " . $e->getFile());
            error_log("DEBUG: Exception line: " . $e->getLine());
            $error = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Oda tiplerini getir
$oda_tipleri = fetchAll("SELECT id, oda_tipi_adi FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");

// Sayfa başlığı
$page_title = 'Rezervasyon Düzenle';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <i class="fas fa-edit me-2"></i>
                        Rezervasyon Düzenle
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="rezervasyonlar.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Geri Dön
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-edit me-2"></i>
                                    Rezervasyon Bilgileri
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="reservationForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="giris_tarihi" class="form-label">Giriş Tarihi</label>
                                            <input type="date" class="form-control" id="giris_tarihi" name="giris_tarihi" 
                                                   value="<?php echo htmlspecialchars($rezervasyon['giris_tarihi']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cikis_tarihi" class="form-label">Çıkış Tarihi</label>
                                            <input type="date" class="form-control" id="cikis_tarihi" name="cikis_tarihi" 
                                                   value="<?php echo htmlspecialchars($rezervasyon['cikis_tarihi']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="yetiskin_sayisi" class="form-label">Yetişkin Sayısı</label>
                                            <input type="number" class="form-control" id="yetiskin_sayisi" name="yetiskin_sayisi" 
                                                   value="<?php echo $rezervasyon['yetiskin_sayisi']; ?>" min="1" max="10" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cocuk_sayisi" class="form-label">Çocuk Sayısı</label>
                                            <input type="number" class="form-control" id="cocuk_sayisi" name="cocuk_sayisi" 
                                                   value="<?php echo $rezervasyon['cocuk_sayisi']; ?>" min="0" max="10">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="toplam_tutar" class="form-label">Toplam Tutar (₺)</label>
                                            <input type="number" class="form-control" id="toplam_tutar" name="toplam_tutar" 
                                                   value="<?php echo $rezervasyon['toplam_tutar']; ?>" step="0.01" min="0" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="odenen_tutar" class="form-label">Ödenen Tutar (₺)</label>
                                            <input type="number" class="form-control" id="odenen_tutar" name="odenen_tutar" 
                                                   value="<?php echo $rezervasyon['odenen_tutar']; ?>" step="0.01" min="0">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="durum" class="form-label">Rezervasyon Durumu</label>
                                            <select class="form-select" id="durum" name="durum" required>
                                                <option value="beklemede" <?php echo $rezervasyon['durum'] == 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                                <option value="onaylandi" <?php echo $rezervasyon['durum'] == 'onaylandi' ? 'selected' : ''; ?>>Onaylandı</option>
                                                <option value="check_in" <?php echo $rezervasyon['durum'] == 'check_in' ? 'selected' : ''; ?>>Check-in</option>
                                                <option value="check_out" <?php echo $rezervasyon['durum'] == 'check_out' ? 'selected' : ''; ?>>Check-out</option>
                                                <option value="iptal" <?php echo $rezervasyon['durum'] == 'iptal' ? 'selected' : ''; ?>>İptal</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="odeme_durumu" class="form-label">Ödeme Durumu</label>
                                            <select class="form-select" id="odeme_durumu" name="odeme_durumu" required>
                                                <option value="odenmedi" <?php echo $rezervasyon['odeme_durumu'] == 'odenmedi' ? 'selected' : ''; ?>>Ödenmedi</option>
                                                <option value="kısmi" <?php echo $rezervasyon['odeme_durumu'] == 'kısmi' ? 'selected' : ''; ?>>Kısmi Ödendi</option>
                                                <option value="tamamen_odendi" <?php echo $rezervasyon['odeme_durumu'] == 'tamamen_odendi' ? 'selected' : ''; ?>>Tamamen Ödendi</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="ozel_istekler" class="form-label">Özel İstekler</label>
                                        <textarea class="form-control" id="ozel_istekler" name="ozel_istekler" rows="3"><?php echo htmlspecialchars($rezervasyon['ozel_istekler'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notlar" class="form-label">Notlar</label>
                                        <textarea class="form-control" id="notlar" name="notlar" rows="3"><?php echo htmlspecialchars($rezervasyon['notlar'] ?? ''); ?></textarea>
                                    </div>

                                    <!-- Yetişkin Misafir Detayları -->
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">
                                                <i class="fas fa-users me-2"></i>
                                                Yetişkin Misafir Detayları
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="yetiskin-container">
                                                <?php if (!empty($yetiskin_detaylari)): ?>
                                                    <?php foreach ($yetiskin_detaylari as $index => $yetiskin): ?>
                                                        <div class="row mb-2 yetiskin-row">
                                                            <div class="col-md-3">
                                                                <input type="text" class="form-control" name="yetiskin_ad[]" 
                                                                       placeholder="Ad" value="<?php echo htmlspecialchars($yetiskin['ad'] ?? ''); ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <input type="text" class="form-control" name="yetiskin_soyad[]" 
                                                                       placeholder="Soyad" value="<?php echo htmlspecialchars($yetiskin['soyad'] ?? ''); ?>">
                                                            </div>
                                                            <div class="col-md-2">
                                                                <select class="form-select" name="yetiskin_cinsiyet[]">
                                                                    <option value="erkek" <?php echo ($yetiskin['cinsiyet'] ?? '') == 'erkek' ? 'selected' : ''; ?>>Erkek</option>
                                                                    <option value="kadın" <?php echo ($yetiskin['cinsiyet'] ?? '') == 'kadın' ? 'selected' : ''; ?>>Kadın</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <input type="text" class="form-control" name="yetiskin_tc[]" 
                                                                       placeholder="TC Kimlik No" value="<?php echo htmlspecialchars($yetiskin['tc_kimlik'] ?? ''); ?>">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <button type="button" class="btn btn-danger btn-sm remove-yetiskin">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-success btn-sm" id="add-yetiskin">
                                                <i class="fas fa-plus me-1"></i>
                                                Yetişkin Ekle
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Çocuk Misafir Detayları -->
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="card-title mb-0">
                                                <i class="fas fa-child me-2"></i>
                                                Çocuk Misafir Detayları
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="cocuk-container">
                                                <?php if (!empty($cocuk_detaylari)): ?>
                                                    <?php foreach ($cocuk_detaylari as $index => $cocuk): ?>
                                                        <div class="row mb-2 cocuk-row">
                                                            <div class="col-md-3">
                                                                <input type="text" class="form-control" name="cocuk_ad[]" 
                                                                       placeholder="Ad" value="<?php echo htmlspecialchars($cocuk['ad'] ?? ''); ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <input type="text" class="form-control" name="cocuk_soyad[]" 
                                                                       placeholder="Soyad" value="<?php echo htmlspecialchars($cocuk['soyad'] ?? ''); ?>">
                                                            </div>
                                                            <div class="col-md-2">
                                                                <select class="form-select" name="cocuk_cinsiyet[]">
                                                                    <option value="erkek" <?php echo ($cocuk['cinsiyet'] ?? '') == 'erkek' ? 'selected' : ''; ?>>Erkek</option>
                                                                    <option value="kadın" <?php echo ($cocuk['cinsiyet'] ?? '') == 'kadın' ? 'selected' : ''; ?>>Kadın</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <input type="number" class="form-control" name="cocuk_yas[]" 
                                                                       placeholder="Yaş" min="0" max="17" value="<?php echo intval($cocuk['yas'] ?? 0); ?>">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <button type="button" class="btn btn-danger btn-sm remove-cocuk">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-success btn-sm" id="add-cocuk">
                                                <i class="fas fa-plus me-1"></i>
                                                Çocuk Ekle
                                            </button>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="rezervasyonlar.php" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-times me-1"></i>
                                            İptal
                                        </a>
                                        <button type="submit" name="update_reservation" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>
                                            Güncelle
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Rezervasyon Özeti
                                </h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Rezervasyon No:</strong></td>
                                        <td>#<?php echo $rezervasyon['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Müşteri:</strong></td>
                                        <td><?php echo htmlspecialchars($rezervasyon['musteri_ad'] . ' ' . $rezervasyon['musteri_soyad']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Oda Tipi:</strong></td>
                                        <td><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></td>
                                    </tr>
                                    <?php if ($rezervasyon['oda_numarasi']): ?>
                                    <tr>
                                        <td><strong>Oda No:</strong></td>
                                        <td><?php echo htmlspecialchars($rezervasyon['oda_numarasi']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong>Kalan Tutar:</strong></td>
                                        <td><?php echo number_format($rezervasyon['kalan_tutar'], 2); ?> ₺</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Oluşturma:</strong></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($rezervasyon['olusturma_tarihi'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tarih validasyonu
        document.getElementById('giris_tarihi').addEventListener('change', function() {
            const giris = new Date(this.value);
            const cikis = document.getElementById('cikis_tarihi');
            
            if (cikis.value) {
                const cikisDate = new Date(cikis.value);
                if (giris >= cikisDate) {
                    cikis.value = '';
                    alert('Çıkış tarihi giriş tarihinden sonra olmalıdır.');
                }
            }
            
            // Minimum çıkış tarihi ayarla
            const minCikis = new Date(giris);
            minCikis.setDate(minCikis.getDate() + 1);
            cikis.min = minCikis.toISOString().split('T')[0];
        });

        document.getElementById('cikis_tarihi').addEventListener('change', function() {
            const cikis = new Date(this.value);
            const giris = new Date(document.getElementById('giris_tarihi').value);
            
            if (giris && cikis <= giris) {
                this.value = '';
                alert('Çıkış tarihi giriş tarihinden sonra olmalıdır.');
            }
        });
    </script>

    <script>
        // Misafir ekleme/çıkarma işlemleri
        document.getElementById('add-yetiskin').addEventListener('click', function() {
            const container = document.getElementById('yetiskin-container');
            const newRow = document.createElement('div');
            newRow.className = 'row mb-2 yetiskin-row';
            newRow.innerHTML = `
                <div class="col-md-3">
                    <input type="text" class="form-control" name="yetiskin_ad[]" placeholder="Ad">
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="yetiskin_soyad[]" placeholder="Soyad">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="yetiskin_cinsiyet[]">
                        <option value="erkek">Erkek</option>
                        <option value="kadın">Kadın</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="yetiskin_tc[]" placeholder="TC Kimlik No">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm remove-yetiskin">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        });

        document.getElementById('add-cocuk').addEventListener('click', function() {
            const container = document.getElementById('cocuk-container');
            const newRow = document.createElement('div');
            newRow.className = 'row mb-2 cocuk-row';
            newRow.innerHTML = `
                <div class="col-md-3">
                    <input type="text" class="form-control" name="cocuk_ad[]" placeholder="Ad">
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="cocuk_soyad[]" placeholder="Soyad">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="cocuk_cinsiyet[]">
                        <option value="erkek">Erkek</option>
                        <option value="kadın">Kadın</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" name="cocuk_yas[]" placeholder="Yaş" min="0" max="17">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm remove-cocuk">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        });

        // Misafir silme işlemleri
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-yetiskin') || e.target.parentElement.classList.contains('remove-yetiskin')) {
                const row = e.target.closest('.yetiskin-row');
                if (row) {
                    row.remove();
                }
            }
            
            if (e.target.classList.contains('remove-cocuk') || e.target.parentElement.classList.contains('remove-cocuk')) {
                const row = e.target.closest('.cocuk-row');
                if (row) {
                    row.remove();
                }
            }
        });
    </script>
</body>
</html>
<?php
// Output buffer'ı flush et
ob_end_flush();
?>