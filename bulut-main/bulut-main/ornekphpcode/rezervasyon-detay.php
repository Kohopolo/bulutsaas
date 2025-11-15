
<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';

// Payment provider sınıflarını dahil et
require_once '../includes/payment/PaymentProcessor.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('rezervasyon_goruntule', 'Rezervasyon görüntüleme yetkiniz bulunmamaktadır.');

$rezervasyon_id = intval($_GET['id'] ?? 0);

if (!$rezervasyon_id) {
    header('Location: rezervasyonlar.php');
    exit;
}

// Rezervasyon detaylarını getir
$rezervasyon = fetchOne("
    SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi, odn.kat,
           m.ad as musteri_adi, m.soyad as musteri_soyadi, 
           m.email as musteri_email, m.telefon as musteri_telefon,
           m.tc_kimlik as musteri_tc, m.adres as musteri_adres
    FROM rezervasyonlar r 
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
    LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id 
    LEFT JOIN musteriler m ON r.musteri_id = m.id
    WHERE r.id = ?
", [$rezervasyon_id]);

if (!$rezervasyon) {
    header('Location: rezervasyonlar.php');
    exit;
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

// Ödeme verilerini getir (normal ödemeler)
// Hem rezervasyon_odemeleri hem de odemeler tablosundan çek
$odemeler_1 = fetchAll("
    SELECT ro.*, k.ad as kullanici_adi, k.soyad as kullanici_soyadi, 'odeme' as odeme_tipi,
           ro.odeme_tutari as tutar
    FROM rezervasyon_odemeleri ro
    LEFT JOIN kullanicilar k ON ro.kullanici_id = k.id
    WHERE ro.rezervasyon_id = ? AND ro.durum = 'aktif'
    ORDER BY ro.odeme_tarihi DESC
", [$rezervasyon_id]);

$odemeler_2 = fetchAll("
    SELECT o.id, o.rezervasyon_id, o.tutar, o.odeme_yontemi, 
           o.durum, o.aciklama, o.olusturma_tarihi as odeme_tarihi,
           NULL as kullanici_adi, NULL as kullanici_soyadi, 'odeme' as odeme_tipi,
           o.tutar as odeme_tutari
    FROM odemeler o
    WHERE o.rezervasyon_id = ? AND o.durum = 'basarili'
    ORDER BY o.olusturma_tarihi DESC
", [$rezervasyon_id]);

// İki diziyi birleştir
$odemeler = array_merge($odemeler_1, $odemeler_2);

// İade ödemelerini getir
$iade_odemeleri = fetchAll("
    SELECT io.*, k.ad as kullanici_adi, k.soyad as kullanici_soyadi, 'iade' as odeme_tipi,
           ri.iade_tutari, ri.iade_nedeni
    FROM iade_odemeleri io
    LEFT JOIN rezervasyon_iadeleri ri ON io.iade_id = ri.id
    LEFT JOIN kullanicilar k ON io.kullanici_id = k.id
    WHERE ri.rezervasyon_id = ? AND io.durum = 'tamamlandi'
    ORDER BY io.odeme_tarihi DESC
", [$rezervasyon_id]);

// Tüm ödemeleri birleştir (önce normal ödemeler, sonra iade ödemeleri)
$tum_odemeler = array_merge($odemeler, $iade_odemeleri);

// Tarihe göre sırala
usort($tum_odemeler, function($a, $b) {
    return strtotime($b['odeme_tarihi']) - strtotime($a['odeme_tarihi']);
});

// Toplam ödenen tutarı hesapla (normal ödemeler - iade ödemeleri)
$toplam_odenen = 0;
foreach ($odemeler as $odeme) {
    $toplam_odenen += $odeme['odeme_tutari'];
}

$toplam_iade_odenen = 0;
foreach ($iade_odemeleri as $iade_odeme) {
    $toplam_iade_odenen += $iade_odeme['odeme_tutari'];
}

$net_odenen = $toplam_odenen - $toplam_iade_odenen;

// Debug: Ödeme bilgilerini log'a yaz
error_log("Rezervasyon ID: $rezervasyon_id, Normal ödeme sayısı: " . count($odemeler) . ", İade ödeme sayısı: " . count($iade_odemeleri) . ", Toplam ödenen: $toplam_odenen, Toplam iade: $toplam_iade_odenen, Net ödenen: $net_odenen");

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

// Rezervasyon geçmişi
$gecmis = fetchAll("
    SELECT * FROM rezervasyon_gecmisi 
    WHERE rezervasyon_id = ? 
    ORDER BY olusturma_tarihi DESC
", [$rezervasyon_id]);

$success_message = '';
$error_message = '';

// Durum güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $aciklama = sanitizeString($_POST['aciklama'] ?? '');
    
    if ($action == 'onayla') {
        $sql = "UPDATE rezervasyonlar SET durum = 'onaylandi' WHERE id = ?";
        if (executeQuery($sql, [$rezervasyon_id])) {
            // Geçmişe kaydet
            $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id) VALUES (?, 'onaylandi', ?, ?)";
            executeQuery($gecmis_sql, [$rezervasyon_id, $aciklama ?: 'Rezervasyon onaylandı', $_SESSION['user_id']]);
            
            // Otomatik fatura oluştur
            require_once '../includes/reservation-accounting-integration.php';
            $integration = new ReservationAccountingIntegration($pdo);
            $invoiceResult = $integration->createInvoiceOnReservationApproval($rezervasyon_id);
            
            // Otomatik bildirim gönder
            require_once '../includes/notification-system.php';
            $notification = new NotificationSystem($pdo);
            $notificationResult = $notification->sendReservationApprovalNotification($rezervasyon_id);
            
            if ($invoiceResult['success']) {
                $success_message = 'Rezervasyon başarıyla onaylandı, fatura oluşturuldu ve bildirim gönderildi.';
            } else {
                $success_message = 'Rezervasyon onaylandı ancak fatura oluşturulamadı: ' . $invoiceResult['message'];
            }
            
            $rezervasyon['durum'] = 'onaylandi';
        } else {
            $error_message = 'Rezervasyon onaylanırken hata oluştu.';
        }
    } elseif ($action == 'iptal') {
        $sql = "UPDATE rezervasyonlar SET durum = 'iptal' WHERE id = ?";
        if (executeQuery($sql, [$rezervasyon_id])) {
            // Geçmişe kaydet
            $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id) VALUES (?, 'iptal_edildi', ?, ?)";
            executeQuery($gecmis_sql, [$rezervasyon_id, $aciklama ?: 'Rezervasyon iptal edildi', $_SESSION['user_id']]);
            
            // Otomatik iade işlemi
            require_once '../includes/reservation-accounting-integration.php';
            $integration = new ReservationAccountingIntegration($pdo);
            $cancellationData = ['fee' => 0]; // İptal ücreti (şimdilik 0)
            $refundResult = $integration->processCancellationRefund($rezervasyon_id, $cancellationData);
            
            // Otomatik iptal bildirimi gönder
            require_once '../includes/notification-system.php';
            $notification = new NotificationSystem($pdo);
            $notificationResult = $notification->sendReservationCancellationNotification($rezervasyon_id, $aciklama);
            
            if ($refundResult['success']) {
                $success_message = 'Rezervasyon iptal edildi, iade işlemi tamamlandı ve bildirim gönderildi.';
            } else {
                $success_message = 'Rezervasyon iptal edildi ancak iade işlemi tamamlanamadı: ' . $refundResult['message'];
            }
            
            $rezervasyon['durum'] = 'iptal';
        } else {
            $error_message = 'Rezervasyon iptal edilirken hata oluştu.';
        }
    } elseif ($action == 'not_ekle') {
        $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, islem_tipi, aciklama, kullanici_id) VALUES (?, 'guncellendi', 'not_eklendi', ?, ?)";
        if (executeQuery($gecmis_sql, [$rezervasyon_id, $aciklama, $_SESSION['user_id']])) {
            $success_message = 'Not başarıyla eklendi.';
            // Geçmişi yeniden yükle
            $gecmis = fetchAll("
                SELECT * FROM rezervasyon_gecmisi 
                WHERE rezervasyon_id = ? 
                ORDER BY olusturma_tarihi DESC
            ", [$rezervasyon_id]);
        } else {
            $error_message = 'Not eklenirken hata oluştu.';
        }
    } elseif ($action == 'odeme_ekle') {
        // Ödeme ekleme yetkisi kontrolü
        if (!hasDetailedPermission('rezervasyon_odeme_ekle')) {
            $error_message = 'Ödeme ekleme yetkiniz bulunmamaktadır.';
        } else {
            $odeme_tutari = floatval($_POST['odeme_tutari'] ?? 0);
            $odeme_yontemi = sanitizeString($_POST['odeme_yontemi'] ?? '');
            $odeme_aciklama = sanitizeString($_POST['odeme_aciklama'] ?? '');
            
            if ($odeme_tutari > 0 && $odeme_yontemi) {
            try {
                error_log("Ödeme ekleniyor - Rezervasyon ID: $rezervasyon_id, Tutar: $odeme_tutari, Yöntem: $odeme_yontemi");
                $pdo->beginTransaction();
                
                // Ödeme kaydını ekle
                $odeme_sql = "INSERT INTO rezervasyon_odemeleri (
                    rezervasyon_id, odeme_tutari, odeme_yontemi, aciklama, kullanici_id, durum, odeme_tarihi
                ) VALUES (?, ?, ?, ?, ?, 'aktif', NOW())";
                
                executeQuery($odeme_sql, [
                    $rezervasyon_id,
                    $odeme_tutari,
                    $odeme_yontemi,
                    $odeme_aciklama,
                    $_SESSION['user_id']
                ]);
                
                // Toplam ödenen tutarı hesapla
                $toplam_odenen = fetchOne("
                    SELECT COALESCE(SUM(odeme_tutari), 0) as toplam 
                    FROM rezervasyon_odemeleri 
                    WHERE rezervasyon_id = ? AND durum = 'aktif'
                ", [$rezervasyon_id])['toplam'];
                
                // Ödeme durumunu güncelle
                $odeme_durumu = 'odenmedi';
                if ($toplam_odenen >= $rezervasyon['toplam_fiyat']) {
                    $odeme_durumu = 'odendi';
                } elseif ($toplam_odenen > 0) {
                    $odeme_durumu = 'kismi_odeme';
                }
                
                $durum_sql = "UPDATE rezervasyonlar SET 
                    odeme_durumu = ?, 
                    odenen_tutar = ?
                    WHERE id = ?";
                
                executeQuery($durum_sql, [
                    $odeme_durumu,
                    $toplam_odenen,
                    $rezervasyon_id
                ]);
                
                // Rezervasyon geçmişine kaydet
                $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (
                    rezervasyon_id, islem, aciklama, kullanici_id, olusturma_tarihi
                ) VALUES (?, 'odeme_yapildi', ?, ?, NOW())";
                
                $gecmis_aciklama = "Ödeme eklendi: " . number_format($odeme_tutari, 2) . "₺ (" . $odeme_yontemi . ")";
                if ($odeme_aciklama) {
                    $gecmis_aciklama .= " - " . $odeme_aciklama;
                }
                $gecmis_aciklama .= " - Yeni ödeme durumu: " . $odeme_durumu;
                
                executeQuery($gecmis_sql, [
                    $rezervasyon_id,
                    $gecmis_aciklama,
                    $_SESSION['user_id']
                ]);
                
                $pdo->commit();
                $success_message = 'Ödeme başarıyla eklendi. Yeni ödeme durumu: ' . $odeme_durumu;
                error_log("Ödeme başarıyla eklendi - Rezervasyon ID: $rezervasyon_id, Yeni toplam ödenen: $toplam_odenen");
                
                // Verileri yeniden yükle
                $rezervasyon = fetchOne("
                    SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi, odn.kat,
                           m.ad as musteri_adi, m.soyad as musteri_soyadi, 
                           m.email as musteri_email, m.telefon as musteri_telefon,
                           m.tc_kimlik as musteri_tc, m.adres as musteri_adres
                    FROM rezervasyonlar r 
                    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
                    LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id 
                    LEFT JOIN musteriler m ON r.musteri_id = m.id
                    WHERE r.id = ?
                ", [$rezervasyon_id]);
                
                $odemeler = fetchAll("
                    SELECT ro.*, k.ad as kullanici_adi, k.soyad as kullanici_soyadi
                    FROM rezervasyon_odemeleri ro
                    LEFT JOIN kullanicilar k ON ro.kullanici_id = k.id
                    WHERE ro.rezervasyon_id = ? AND ro.durum = 'aktif'
                    ORDER BY ro.odeme_tarihi DESC
                ", [$rezervasyon_id]);
                
                $toplam_odenen = 0;
                foreach ($odemeler as $odeme) {
                    $toplam_odenen += $odeme['odeme_tutari'];
                }
                
                $gecmis = fetchAll("
                    SELECT * FROM rezervasyon_gecmisi 
                    WHERE rezervasyon_id = ? 
                    ORDER BY olusturma_tarihi DESC
                ", [$rezervasyon_id]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                $error_message = 'Ödeme eklenirken hata oluştu: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Lütfen geçerli bir ödeme tutarı ve yöntemi girin.';
        }
        }
    } elseif ($action == 'early_checkout') {
        $yeni_cikis_tarihi = sanitizeString($_POST['yeni_cikis_tarihi'] ?? '');
        $yeni_cikis_saati = sanitizeString($_POST['yeni_cikis_saati'] ?? '');
        $manuel_iade_tutari = floatval($_POST['manuel_iade_tutari'] ?? 0);
        
        if ($yeni_cikis_tarihi && $yeni_cikis_saati) {
            try {
                $pdo->beginTransaction();
                
                // Yeni çıkış tarihini birleştir
                $yeni_cikis_datetime = $yeni_cikis_tarihi . ' ' . $yeni_cikis_saati . ':00';
                
                // Kalan gece sayısını hesapla
                $giris = new DateTime($rezervasyon['giris_tarihi']);
                $cikis = new DateTime($yeni_cikis_datetime);
                $kalan_gece_sayisi = $giris->diff($cikis)->days;
                
                // Orijinal gece sayısını hesapla
                $orijinal_giris = new DateTime($rezervasyon['giris_tarihi']);
                $orijinal_cikis = new DateTime($rezervasyon['cikis_tarihi']);
                $orijinal_gece_sayisi = $orijinal_giris->diff($orijinal_cikis)->days;
                
                // Günlük fiyatı hesapla (toplam_tutar kullan)
                $gunluk_fiyat = $orijinal_gece_sayisi > 0 ? $rezervasyon['toplam_tutar'] / $orijinal_gece_sayisi : 0;
                
                // Kullanılan gece sayısı = kalan gece sayısı
                $kullanilan_gece_sayisi = $kalan_gece_sayisi;
                $kullanilmayan_gece_sayisi = $orijinal_gece_sayisi - $kullanilan_gece_sayisi;
                
                // Yeni toplam tutar = kullanılan geceler için
                $yeni_fiyat = $kullanilan_gece_sayisi * $gunluk_fiyat;
                
                error_log("Erken check-out hesaplama - Orijinal gece: $orijinal_gece_sayisi, Kalan gece: $kalan_gece_sayisi, Kullanılan: $kullanilan_gece_sayisi, Kullanılmayan: $kullanilmayan_gece_sayisi, Günlük fiyat: $gunluk_fiyat, Yeni fiyat: $yeni_fiyat, Orijinal toplam: " . $rezervasyon['toplam_tutar']);
                
                // İade tutarını belirle = ödenen tutar - kullanılan gecelerin fiyatı
                $odenen_tutar = $rezervasyon['odenen_tutar'] ?? 0;
                $kullanilan_gece_fiyati = $kullanilan_gece_sayisi * $gunluk_fiyat;
                $hesaplanan_iade = $odenen_tutar - $kullanilan_gece_fiyati;
                
                // İade tutarı negatif olamaz (müşteri borçlu ise iade yok)
                $hesaplanan_iade = max(0, $hesaplanan_iade);
                
                $iade_tutari = $manuel_iade_tutari > 0 ? $manuel_iade_tutari : $hesaplanan_iade;
                
                error_log("İade hesaplama - Ödenen: $odenen_tutar, Kullanılan gece fiyatı: $kullanilan_gece_fiyati, Hesaplanan iade: $hesaplanan_iade, Final iade: $iade_tutari");
                
                // Rezervasyonu güncelle
                $update_sql = "UPDATE rezervasyonlar SET 
                    cikis_tarihi = ?, 
                    gercek_cikis_tarihi = ?,
                    toplam_tutar = ?,
                    durum = 'tamamlandi',
                    erken_checkout = 1
                    WHERE id = ?";
                
                executeQuery($update_sql, [
                    $yeni_cikis_datetime,
                    $yeni_cikis_datetime,
                    $yeni_fiyat,
                    $rezervasyon_id
                ]);
                
                // İade kaydı oluştur
                if ($iade_tutari > 0) {
                    $iade_sql = "INSERT INTO rezervasyon_iadeleri (
                        rezervasyon_id, iade_tutari, iade_nedeni, 
                        durum, olusturma_tarihi, kullanici_id
                    ) VALUES (?, ?, ?, 'aktif', NOW(), ?)";
                    
                    $iade_aciklama = 'Erken check-out: ' . ($_POST['aciklama'] ?? '');
                    if ($manuel_iade_tutari > 0) {
                        $iade_aciklama .= ' (Manuel iade tutarı: ' . number_format($manuel_iade_tutari, 2) . '₺)';
                    } else {
                        $iade_aciklama .= ' (Otomatik hesaplanan: ' . number_format($hesaplanan_iade, 2) . '₺)';
                    }
                    
                    executeQuery($iade_sql, [
                        $rezervasyon_id,
                        $iade_tutari,
                        $iade_aciklama,
                        $_SESSION['user_id']
                    ]);
                }
                
                // Tarih kontrolü: Bugün mü yoksa gelecek tarih mi?
                $bugun = date('Y-m-d');
                $yeni_tarih = date('Y-m-d', strtotime($yeni_cikis_tarihi));
                
                if ($yeni_tarih == $bugun) {
                    // Bugün ise direkt check-out yap
                    $yeni_durum = 'check_out';
                    $islem_tipi = 'erken_checkout';
                    $gecmis_islem = 'check_out';
                    
                    // Oda durumunu temizlik bekliyor yap
                    if ($rezervasyon['oda_numarasi_id']) {
                        $oda_sql = "UPDATE oda_numaralari SET durum = 'temizlik_bekliyor' WHERE id = ?";
                        executeQuery($oda_sql, [$rezervasyon['oda_numarasi_id']]);
                    }
                    
                    $gecmis_aciklama = 'Erken check-out yapıldı (Bugün). Yeni çıkış: ' . $yeni_cikis_datetime;
                } else {
                    // Gelecek tarih ise check-in durumunda bırak
                    $yeni_durum = 'check_in';
                    $islem_tipi = 'erken_checkout_gelecek';
                    $gecmis_islem = 'guncellendi';
                    
                    $gecmis_aciklama = 'Erken check-out tarihi güncellendi (Gelecek tarih). Yeni çıkış: ' . $yeni_cikis_datetime;
                }
                
                // Rezervasyon durumunu güncelle
                $durum_sql = "UPDATE rezervasyonlar SET durum = ?, cikis_tarihi = ? WHERE id = ?";
                executeQuery($durum_sql, [$yeni_durum, $yeni_cikis_tarihi, $rezervasyon_id]);
                
                // Geçmişe kaydet
                $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (
                    rezervasyon_id, islem, islem_tipi, aciklama, kullanici_id
                ) VALUES (?, ?, ?, ?, ?)";
                
                if ($iade_tutari > 0) {
                    $gecmis_aciklama .= ' - İade tutarı: ' . number_format($iade_tutari, 2) . '₺';
                    if ($manuel_iade_tutari > 0) {
                        $gecmis_aciklama .= ' (Manuel)';
                    } else {
                        $gecmis_aciklama .= ' (Otomatik)';
                    }
                }
                
                if ($yeni_tarih == $bugun) {
                    $gecmis_aciklama .= '. Oda durumu temizlik bekliyor olarak güncellendi.';
                } else {
                    $gecmis_aciklama .= '. Rezervasyon check-in durumunda bırakıldı.';
                }
                
                executeQuery($gecmis_sql, [
                    $rezervasyon_id,
                    $gecmis_islem,
                    $islem_tipi,
                    $gecmis_aciklama,
                    $_SESSION['user_id']
                ]);
                
                $pdo->commit();
                
                if ($yeni_tarih == $bugun) {
                    $success_message = "Erken check-out başarıyla tamamlandı. Rezervasyon check-out durumuna geçirildi.";
                } else {
                    $success_message = "Erken check-out tarihi güncellendi. Rezervasyon check-in durumunda bırakıldı.";
                }
                
                if ($iade_tutari > 0) {
                    $success_message .= " İade tutarı: " . number_format($iade_tutari, 2) . "₺";
                }
                
                // Rezervasyon bilgilerini yeniden yükle
                $rezervasyon = fetchOne("
                    SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi, odn.kat,
                           m.ad as musteri_adi, m.soyad as musteri_soyadi, 
                           m.email as musteri_email, m.telefon as musteri_telefon,
                           m.tc_kimlik as musteri_tc, m.adres as musteri_adres
                    FROM rezervasyonlar r 
                    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
                    LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id 
                    LEFT JOIN musteriler m ON r.musteri_id = m.id
                    WHERE r.id = ?
                ", [$rezervasyon_id]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = 'Erken check-out işlemi sırasında hata oluştu: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Çıkış tarihi ve saati gereklidir.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervasyon Detayı - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                </button>
                
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Rezervasyon Detayı</h1>
                            <p class="text-muted">Rezervasyon: <?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu'] ?? ''); ?></p>
                        </div>
                        <div>
                            <a href="rezervasyonlar.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Geri Dön
                            </a>
                            
                            <!-- PDF Butonları -->
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-outline-info dropdown-toggle" 
                                        data-bs-toggle="dropdown" title="PDF İşlemleri">
                                    <i class="fas fa-file-pdf me-2"></i>PDF İndir
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <form method="POST" action="pdf-download.php" class="d-inline" target="_blank">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="voucher" value="1">
                                            <input type="hidden" name="rezervasyon_id" value="<?php echo $rezervasyon['id']; ?>">
                                            <button type="submit" class="dropdown-item">
                                                <i class="fas fa-ticket-alt me-2"></i>Voucher İndir
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="pdf-download.php" class="d-inline" target="_blank">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="contract" value="1">
                                            <input type="hidden" name="rezervasyon_id" value="<?php echo $rezervasyon['id']; ?>">
                                            <button type="submit" class="dropdown-item">
                                                <i class="fas fa-file-contract me-2"></i>Sözleşme İndir
                                            </button>
                                        </form>
                                    </li>
                                    <?php if ($rezervasyon['odeme_durumu'] == 'odendi'): ?>
                                    <li>
                                        <form method="POST" action="pdf-download.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="receipt" value="1">
                                            <input type="hidden" name="odeme_id" value="<?php echo $rezervasyon['id']; ?>">
                                            <button type="submit" class="dropdown-item">
                                                <i class="fas fa-receipt me-2"></i>Ödeme Makbuzu İndir
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a href="#" class="dropdown-item whatsapp-send" 
                                           data-rezervasyon-id="<?php echo $rezervasyon['id']; ?>" 
                                           data-type="voucher" 
                                           data-phone="<?php echo $rezervasyon['musteri_telefon']; ?>">
                                            <i class="fab fa-whatsapp me-2 text-success"></i>Voucher WhatsApp Gönder
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="dropdown-item whatsapp-send" 
                                           data-rezervasyon-id="<?php echo $rezervasyon['id']; ?>" 
                                           data-type="contract" 
                                           data-phone="<?php echo $rezervasyon['musteri_telefon']; ?>">
                                            <i class="fab fa-whatsapp me-2 text-success"></i>Sözleşme WhatsApp Gönder
                                        </a>
                                    </li>
                                    <?php if ($rezervasyon['odeme_durumu'] == 'odendi'): ?>
                                    <li>
                                        <a href="#" class="dropdown-item whatsapp-send" 
                                           data-rezervasyon-id="<?php echo $rezervasyon['id']; ?>" 
                                           data-type="receipt" 
                                           data-phone="<?php echo $rezervasyon['musteri_telefon']; ?>">
                                            <i class="fab fa-whatsapp me-2 text-success"></i>Makbuz WhatsApp Gönder
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a href="pdf-archive-view.php?id=<?php echo $rezervasyon['id']; ?>" class="dropdown-item">
                                            <i class="fas fa-archive me-2"></i>PDF Arşivi Görüntüle
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            
                            <a href="rezervasyon-duzenle.php?id=<?php echo $rezervasyon['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Düzenle
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Rezervasyon Bilgileri -->
                    <div class="card shadow mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-info-circle me-2"></i>Rezervasyon Bilgileri
                            </h6>
                            <span class="badge bg-<?php 
                                $durum_class = 'secondary';
                                switch($rezervasyon['durum']) {
                                    case 'beklemede': $durum_class = 'warning'; break;
                                    case 'onaylandi': $durum_class = 'success'; break;
                                    case 'check_in': $durum_class = 'info'; break;
                                    case 'check_out': $durum_class = 'secondary'; break;
                                    case 'iptal': $durum_class = 'danger'; break;
                                }
                                echo $durum_class;
                            ?> fs-6">
                                <?php echo ucfirst($rezervasyon['durum']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Rezervasyon Kodu:</strong></td>
                                            <td><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu'] ?? ''); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Oda Tipi:</strong></td>
                                            <td><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Oda Numarası:</strong></td>
                                            <td>
                                                <?php if ($rezervasyon['oda_numarasi']): ?>
                                                    <?php echo htmlspecialchars($rezervasyon['oda_numarasi']); ?> 
                                                    (<?php echo $rezervasyon['kat']; ?>. Kat)
                                                <?php else: ?>
                                                    <span class="text-muted">Henüz atanmadı</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if ($rezervasyon['oda_numarasi']): ?>
                                        <tr>
                                            <td><strong>Oda Durumu:</strong></td>
                                            <td>
                                                <?php
                                                // Oda durumunu getir
                                                $oda_durum = fetchOne("SELECT durum FROM oda_numaralari WHERE id = ?", [$rezervasyon['oda_numarasi_id']]);
                                                $durum_class = [
                                                    'aktif' => 'success',
                                                    'dolu' => 'primary',
                                                    'kirli' => 'warning',
                                                    'temizlik_bekliyor' => 'info',
                                                    'bakimda' => 'danger',
                                                    'devre_disi' => 'secondary',
                                                    'temiz' => 'success',
                                                    'bakim' => 'danger',
                                                    'pasif' => 'secondary'
                                                ];
                                                $durum_text = [
                                                    'aktif' => 'Aktif',
                                                    'dolu' => 'Dolu',
                                                    'kirli' => 'Kirli',
                                                    'temizlik_bekliyor' => 'Temizlik Bekliyor',
                                                    'bakimda' => 'Bakımda',
                                                    'devre_disi' => 'Devre Dışı',
                                                    'temiz' => 'Temiz',
                                                    'bakim' => 'Bakım',
                                                    'pasif' => 'Pasif'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $durum_class[$oda_durum['durum']] ?? 'secondary'; ?>">
                                                    <?php echo $durum_text[$oda_durum['durum']] ?? $oda_durum['durum']; ?>
                                                </span>
                                            </td>
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
                                            <td>
                                                <?php 
                                                $giris = new DateTime($rezervasyon['giris_tarihi']);
                                                $cikis = new DateTime($rezervasyon['cikis_tarihi']);
                                                echo $giris->diff($cikis)->days . ' gece';
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
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
                                            <td><?php echo implode(', ', array_map(function($yas) { return $yas . ' yaş'; }, $cocuk_yaslari)); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td><strong>Toplam Tutar:</strong></td>
                                            <td><strong class="text-success"><?php echo formatCurrency($rezervasyon['toplam_tutar']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ödeme Durumu:</strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $rezervasyon['odeme_durumu'] == 'odendi' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($rezervasyon['odeme_durumu']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Rezervasyon Tarihi:</strong></td>
                                            <td><?php echo formatTurkishDate($rezervasyon['olusturma_tarihi'], 'd.m.Y H:i'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <?php if ($rezervasyon['ozel_istekler']): ?>
                            <div class="mt-3">
                                <h6><strong>Özel İstekler:</strong></h6>
                                <div class="bg-light p-3 rounded">
                                    <?php echo nl2br(htmlspecialchars($rezervasyon['ozel_istekler'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Misafir Detayları -->
                    <?php if (!empty($yetiskin_detaylari) || !empty($cocuk_detaylari)): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-users me-2"></i>Misafir Detayları
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($yetiskin_detaylari)): ?>
                            <div class="mb-4">
                                <h6 class="text-success mb-3">
                                    <i class="fas fa-user-tie me-2"></i>Yetişkin Misafirler (<?php echo count($yetiskin_detaylari); ?>)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Sıra</th>
                                                <th>Ad</th>
                                                <th>Soyad</th>
                                                <th>Cinsiyet</th>
                                                <th>TC Kimlik</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($yetiskin_detaylari as $index => $yetiskin): ?>
                                            <tr>
                                                <td><span class="badge bg-primary"><?php echo $index + 1; ?></span></td>
                                                <td><?php echo htmlspecialchars($yetiskin['ad'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($yetiskin['soyad'] ?? ''); ?></td>
                                                <td>
                                                    <?php if (isset($yetiskin['cinsiyet'])): ?>
                                                        <span class="badge bg-<?php echo $yetiskin['cinsiyet'] == 'erkek' ? 'info' : 'warning'; ?>">
                                                            <?php echo ucfirst($yetiskin['cinsiyet']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($yetiskin['tc_kimlik'] ?? ''); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($cocuk_detaylari)): ?>
                            <div>
                                <h6 class="text-info mb-3">
                                    <i class="fas fa-child me-2"></i>Çocuk Misafirler (<?php echo count($cocuk_detaylari); ?>)
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Sıra</th>
                                                <th>Ad</th>
                                                <th>Soyad</th>
                                                <th>Cinsiyet</th>
                                                <th>Yaş</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cocuk_detaylari as $index => $cocuk): ?>
                                            <tr>
                                                <td><span class="badge bg-primary"><?php echo $index + 1; ?></span></td>
                                                <td><?php echo htmlspecialchars($cocuk['ad'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($cocuk['soyad'] ?? ''); ?></td>
                                                <td>
                                                    <?php if (isset($cocuk['cinsiyet'])): ?>
                                                        <span class="badge bg-<?php echo $cocuk['cinsiyet'] == 'erkek' ? 'info' : 'warning'; ?>">
                                                            <?php echo ucfirst($cocuk['cinsiyet']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($cocuk['yas'] ?? ''); ?> yaş</span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Müşteri Bilgileri -->
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-user me-2"></i>Müşteri Bilgileri
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Ad Soyad:</strong></td>
                                            <td><?php echo htmlspecialchars(($rezervasyon['musteri_adi'] ?? '') . ' ' . ($rezervasyon['musteri_soyadi'] ?? '')); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>E-posta:</strong></td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($rezervasyon['musteri_email'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($rezervasyon['musteri_email'] ?? ''); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Telefon:</strong></td>
                                            <td>
                                                <a href="tel:<?php echo htmlspecialchars($rezervasyon['musteri_telefon'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($rezervasyon['musteri_telefon'] ?? ''); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>TC Kimlik No:</strong></td>
                                            <td><?php echo htmlspecialchars(isset($rezervasyon['musteri_tc']) ? $rezervasyon['musteri_tc'] : 'Belirtilmemiş'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Adres:</strong></td>
                                            <td><?php echo htmlspecialchars(isset($rezervasyon['musteri_adres']) ? $rezervasyon['musteri_adres'] : 'Belirtilmemiş'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ek Hizmetler -->
                    <?php if (!empty($ek_hizmetler)): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-concierge-bell me-2"></i>Ek Hizmetler
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Hizmet</th>
                                            <th>Açıklama</th>
                                            <th>Fiyat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ek_hizmetler as $hizmet): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($hizmet['hizmet_adi']); ?></td>
                                            <td><?php echo htmlspecialchars($hizmet['aciklama'] ?: '-'); ?></td>
                                            <td><?php echo formatCurrency($hizmet['fiyat']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Check-in/Check-out Bilgileri -->
                    <?php if (isset($rezervasyon['gercek_giris_tarihi']) && $rezervasyon['gercek_giris_tarihi'] || isset($rezervasyon['gercek_cikis_tarihi']) && $rezervasyon['gercek_cikis_tarihi']): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-clock me-2"></i>Check-in / Check-out Bilgileri
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (isset($rezervasyon['gercek_giris_tarihi']) && $rezervasyon['gercek_giris_tarihi']): ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-sign-in-alt text-success me-2"></i>
                                        <strong>Check-in:</strong>
                                    </div>
                                    <div class="ms-4">
                                        <?php echo formatTurkishDate($rezervasyon['gercek_giris_tarihi'], 'd F Y H:i'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($rezervasyon['gercek_cikis_tarihi']) && $rezervasyon['gercek_cikis_tarihi']): ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-sign-out-alt text-warning me-2"></i>
                                        <strong>Check-out:</strong>
                                    </div>
                                    <div class="ms-4">
                                        <?php echo formatTurkishDate($rezervasyon['gercek_cikis_tarihi'], 'd F Y H:i'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <!-- Hızlı İşlemler -->
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-bolt me-2"></i>Hızlı İşlemler
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($rezervasyon['durum'] == 'beklemede'): ?>
                            <div class="d-grid gap-2">
                                <button class="btn btn-success" onclick="showActionModal('onayla')">
                                    <i class="fas fa-check me-2"></i>Rezervasyonu Onayla
                                </button>
                                <button class="btn btn-danger" onclick="showActionModal('iptal')">
                                    <i class="fas fa-times me-2"></i>Rezervasyonu İptal Et
                                </button>
                            </div>
                            <?php elseif ($rezervasyon['durum'] == 'onaylandi'): ?>
                            <div class="d-grid gap-2">
                                <a href="check-in-out.php" class="btn btn-info">
                                    <i class="fas fa-key me-2"></i>Check-in İşlemi
                                </a>
                                <button class="btn btn-danger" onclick="showActionModal('iptal')">
                                    <i class="fas fa-times me-2"></i>Rezervasyonu İptal Et
                                </button>
                            </div>
                            <?php elseif ($rezervasyon['durum'] == 'check_in'): ?>
                            <div class="d-grid gap-2">
                                <a href="check-in-out.php" class="btn btn-warning">
                                    <i class="fas fa-door-open me-2"></i>Check-out İşlemi
                                </a>
                                <button class="btn btn-outline-warning" onclick="showEarlyCheckoutModal()">
                                    <i class="fas fa-clock me-2"></i>Erken Check-out
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" onclick="showActionModal('not_ekle')">
                                    <i class="fas fa-sticky-note me-2"></i>Not Ekle
                                </button>
                                <a href="rezervasyon-duzenle.php?id=<?php echo $rezervasyon['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-edit me-2"></i>Düzenle
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Ödeme İşlemleri -->
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-money-bill-wave me-2"></i>Ödeme İşlemleri
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Ödeme Formu -->
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Yeni Ödeme Ekle</h6>
                                    <?php if (hasDetailedPermission('rezervasyon_odeme_ekle')): ?>
                                    <form id="paymentForm">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="reservation_id" value="<?php echo $rezervasyon['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label for="payment_amount" class="form-label">Ödeme Tutarı</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                                       step="0.01" min="0.01" max="<?php echo max(0.01, $rezervasyon['toplam_tutar'] - $net_odenen); ?>" required>
                                                <span class="input-group-text">₺</span>
                                            </div>
                                            <small class="form-text text-muted">
                                                Maksimum: <?php echo number_format(max(0.01, $rezervasyon['toplam_tutar'] - $net_odenen), 2); ?>₺
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">Ödeme Yöntemi</label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="nakit">Nakit</option>
                                                <option value="kredi_karti">Kredi Kartı</option>
                                                <option value="banka_transferi">Banka Transferi</option>
                                                <option value="havale">Havale</option>
                                                <option value="cek">Çek</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="odeme_aciklama" class="form-label">Açıklama</label>
                                            <textarea class="form-control" id="odeme_aciklama" name="odeme_aciklama" rows="2" 
                                                      placeholder="Ödeme hakkında açıklama"></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-success" id="paymentSubmitBtn">
                                            <i class="fas fa-plus me-2"></i>Ödeme Ekle
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Ödeme ekleme yetkiniz bulunmamaktadır.
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="mb-3">Ödeme Durumu</h6>
                                    <div class="alert alert-info">
                                        <div class="row">
                                            <div class="col-6">
                                                <strong>Toplam Tutar:</strong><br>
                                                <span class="text-primary"><?php echo number_format($rezervasyon['toplam_tutar'], 2); ?>₺</span>
                                            </div>
                                            <div class="col-6">
                                                <strong>Ödenen Tutar:</strong><br>
                                                <span class="text-success total-paid-amount"><?php echo number_format($toplam_odenen, 2); ?>₺</span>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-6">
                                                <strong>Kalan Tutar:</strong><br>
                                                <span class="text-warning remaining-amount"><?php echo number_format($rezervasyon['toplam_tutar'] - $net_odenen, 2); ?>₺</span>
                                            </div>
                                            <div class="col-6">
                                                <strong>Ödeme Durumu:</strong><br>
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
                                                <span class="badge bg-<?php echo $odeme_durumu_class[$rezervasyon['odeme_durumu']] ?? 'secondary'; ?> payment-status">
                                                    <?php echo $odeme_durumu_text[$rezervasyon['odeme_durumu']] ?? 'Bilinmiyor'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ödeme Geçmişi -->
                            <?php if (!empty($odemeler)): ?>
                            <hr>
                            <h6 class="mb-3">Ödeme Geçmişi</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tarih</th>
                                            <th>Tutar</th>
                                            <th>Yöntem</th>
                                            <th>Açıklama</th>
                                            <th>İşlem Yapan</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody class="payment-list">
                                        <?php foreach ($tum_odemeler as $odeme): ?>
                                        <tr>
                                            <td><?php echo formatTurkishDate($odeme['odeme_tarihi'], 'd.m.Y H:i'); ?></td>
                                            <td>
                                                <?php if ($odeme['odeme_tipi'] == 'iade'): ?>
                                                    <strong class="text-danger">-<?php echo number_format($odeme['odeme_tutari'], 2); ?>₺</strong>
                                                    <br><small class="text-muted">İade Ödemesi</small>
                                                <?php else: ?>
                                                    <strong class="text-success">+<?php echo number_format($odeme['odeme_tutari'], 2); ?>₺</strong>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($odeme['odeme_tipi'] == 'iade'): ?>
                                                    <span class="badge bg-danger">İade Ödemesi</span>
                                                    <?php if (isset($odeme['iade_nedeni'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($odeme['iade_nedeni']); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php
                                                    $yontem_text = [
                                                        'nakit' => 'Nakit',
                                                        'kredi_karti' => 'Kredi Kartı',
                                                        'havale' => 'Havale',
                                                        'cek' => 'Çek',
                                                        'diger' => 'Diğer'
                                                    ];
                                                    echo $yontem_text[$odeme['odeme_yontemi']] ?? 'Bilinmiyor';
                                                    ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($odeme['aciklama'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($odeme['kullanici_adi'] ?? 'Sistem'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $odeme['durum'] == 'aktif' ? 'success' : 'danger'; ?>">
                                                    <?php echo $odeme['durum'] == 'aktif' ? 'Aktif' : 'İptal'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-money-bill-wave fa-2x mb-2 d-block"></i>
                                Henüz ödeme kaydı yok.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rezervasyon Geçmişi -->
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-history me-2"></i>Rezervasyon Geçmişi
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($gecmis)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-history fa-2x mb-2 d-block"></i>
                                Henüz işlem geçmişi yok.
                            </div>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($gecmis as $index => $islem): ?>
                                <div class="timeline-item <?php echo $index === 0 ? 'timeline-item-latest' : ''; ?>">
                                    <div class="timeline-marker bg-<?php 
                                        switch($islem['islem']) {
                                            case 'onaylandi':
                                                echo 'success';
                                                break;
                                            case 'iptal':
                                                echo 'danger';
                                                break;
                                            case 'check_in':
                                                echo 'info';
                                                break;
                                            case 'check_out':
                                                echo 'warning';
                                                break;
                                            case 'not':
                                                echo 'secondary';
                                                break;
                                            default:
                                                echo 'primary';
                                                break;
                                        }
                                    ?>"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title">
                                            <?php 
                                            switch($islem['islem']) {
                                                case 'onaylandi':
                                                    echo 'Rezervasyon Onaylandı';
                                                    break;
                                                case 'iptal':
                                                    echo 'Rezervasyon İptal Edildi';
                                                    break;
                                                case 'check_in':
                                                    echo 'Check-in Yapıldı';
                                                    break;
                                                case 'check_out':
                                                    echo 'Check-out Yapıldı';
                                                    break;
                                                case 'not':
                                                    echo 'Not Eklendi';
                                                    break;
                                                default:
                                                    echo ucfirst($islem['islem']);
                                                    break;
                                            }
                                            ?>
                                        </h6>
                                        <?php if ($islem['aciklama']): ?>
                                        <p class="timeline-text"><?php echo htmlspecialchars($islem['aciklama']); ?></p>
                                        <?php endif; ?>
                                        <small class="timeline-date text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatTurkishDate($islem['olusturma_tarihi'], 'd.m.Y H:i'); ?>
                                        </small>
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
    </div>

    <!-- İşlem Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="actionModalTitle">İşlem</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="actionType">
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3" 
                                      placeholder="İşlemle ilgili açıklama ekleyebilirsiniz (isteğe bağlı)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn" id="actionButton">İşlemi Onayla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Erken Check-out Modal -->
    <div class="modal fade" id="earlyCheckoutModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-clock me-2"></i>Erken Check-out İşlemi
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="early_checkout">
                        <input type="hidden" name="rezervasyon_id" value="<?php echo $rezervasyon['id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="yeni_cikis_tarihi" class="form-label">Yeni Çıkış Tarihi</label>
                                    <input type="date" class="form-control" id="yeni_cikis_tarihi" name="yeni_cikis_tarihi" 
                                           min="<?php echo date('Y-m-d'); ?>" 
                                           max="<?php echo $rezervasyon['cikis_tarihi']; ?>" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="yeni_cikis_saati" class="form-label">Çıkış Saati</label>
                                    <input type="time" class="form-control" id="yeni_cikis_saati" name="yeni_cikis_saati" 
                                           value="12:00" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>İade Hesaplama Bilgileri</h6>
                            <p class="mb-1"><strong>Orijinal Giriş:</strong> <?php echo formatTurkishDate($rezervasyon['giris_tarihi']); ?></p>
                            <p class="mb-1"><strong>Orijinal Çıkış:</strong> <?php echo formatTurkishDate($rezervasyon['cikis_tarihi']); ?></p>
                            <p class="mb-1"><strong>Orijinal Toplam:</strong> <?php echo number_format($rezervasyon['toplam_tutar'], 2) . '₺'; ?></p>
                            <p class="mb-0"><strong>Ödenen Tutar:</strong> <?php echo number_format($rezervasyon['odenen_tutar'], 2) . '₺'; ?></p>
                        </div>
                        
                        <!-- Sistem Tarafından Otomatik Hesaplanan İade Tutarı -->
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-calculator me-2"></i>Sistem Tarafından Otomatik Hesaplanan İade Tutarı</h6>
                            <p class="mb-1"><strong>Açıklama:</strong> Yeni çıkış tarihi seçildiğinde sistem otomatik olarak fiyat hesaplama sistemini kullanarak yeni toplam tutarı hesaplayacak ve orijinal toplam ile yeni toplam arasındaki farkı iade tutarı olarak gösterecektir.</p>
                            <p class="mb-1"><strong>Hesaplama Yöntemi:</strong> Özel fiyatlar → Sezonluk fiyatlar → Kampanya fiyatları → Temel fiyat sırasıyla kontrol edilerek en uygun fiyat belirlenir.</p>
                            <p class="mb-0"><strong>İade Tutarı:</strong> <span class="text-success fw-bold">Yeni çıkış tarihi seçildiğinde otomatik hesaplanacak</span></p>
                        </div>
                        
                        <!-- Otomatik Hesaplama Sonuçları -->
                        <div id="hesaplama_sonuclari" class="alert alert-success" style="display: none;">
                            <h6><i class="fas fa-calculator me-2"></i>Otomatik Hesaplama Sonuçları</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Yeni Toplam:</strong> <span id="yeni_toplam" class="text-primary fw-bold">0₺</span></p>
                                    <p class="mb-1"><strong>Hesaplanan İade:</strong> <span id="hesaplanan_iade" class="text-warning fw-bold">0₺</span></p>
                                    <p class="mb-0"><strong>Ödenen Tutar:</strong> <span id="odenen_tutar" class="text-info">0₺</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Gece Sayısı:</strong> <span id="gece_sayisi" class="text-secondary">0</span></p>
                                    <p class="mb-1"><strong>İade Oranı:</strong> <span id="iade_orani" class="text-secondary">0%</span></p>
                                    <p class="mb-0"><strong>Gerçek İade:</strong> <span id="gercek_iade_tutari" class="text-success fw-bold">0₺</span></p>
                                </div>
                            </div>
                            <hr>
                            <div class="alert alert-info mb-0">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>Not:</strong> Gerçek iade tutarı, ödenen tutardan fazla olamaz. 
                                    Hesaplanan iade tutarı ödenen tutardan büyükse, sadece ödenen tutar kadar iade yapılır.
                                </small>
                            </div>
                        </div>
                        
                        <!-- Manuel İade Tutarı -->
                        <div class="mb-3">
                            <label for="manuel_iade_tutari" class="form-label">Manuel İade Tutarı (Opsiyonel)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="manuel_iade_tutari" name="manuel_iade_tutari" 
                                       step="0.01" min="0" max="<?php echo max(0, $rezervasyon['odenen_tutar']); ?>"
                                       placeholder="Manuel iade tutarı girin">
                                <span class="input-group-text">₺</span>
                            </div>
                            <small class="form-text text-muted">
                                Boş bırakılırsa otomatik hesaplanan iade tutarı kullanılır.
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="erken_checkout_aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="erken_checkout_aciklama" name="aciklama" rows="3" 
                                      placeholder="Erken check-out nedeni ve iade işlemi hakkında açıklama"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-calculator me-2"></i>İade Hesapla ve Check-out Yap
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        function showActionModal(action) {
            const modal = new bootstrap.Modal(document.getElementById('actionModal'));
            const title = document.getElementById('actionModalTitle');
            const button = document.getElementById('actionButton');
            const actionType = document.getElementById('actionType');
            
            actionType.value = action;
            
            switch(action) {
                case 'onayla':
                    title.textContent = 'Rezervasyonu Onayla';
                    button.textContent = 'Onayla';
                    button.className = 'btn btn-success';
                    break;
                case 'iptal':
                    title.textContent = 'Rezervasyonu İptal Et';
                    button.textContent = 'İptal Et';
                    button.className = 'btn btn-danger';
                    break;
                case 'not_ekle':
                    title.textContent = 'Not Ekle';
                    button.textContent = 'Not Ekle';
                    button.className = 'btn btn-primary';
                    document.getElementById('aciklama').required = true;
                    break;
            }
            
            modal.show();
        }
        
        function showEarlyCheckoutModal() {
            const modal = new bootstrap.Modal(document.getElementById('earlyCheckoutModal'));
            
            // Modal açıldığında hesaplama yap
            modal._element.addEventListener('shown.bs.modal', function() {
                // Event listener'ları ekle
                const yeniCikisTarihi = document.getElementById('yeni_cikis_tarihi');
                const yeniCikisSaati = document.getElementById('yeni_cikis_saati');
                
                if (yeniCikisTarihi) {
                    yeniCikisTarihi.addEventListener('change', hesaplaIade);
                }
                if (yeniCikisSaati) {
                    yeniCikisSaati.addEventListener('change', hesaplaIade);
                }
                
                // Hemen hesaplama yap
                setTimeout(() => {
                    hesaplaIade();
                }, 500);
            });
            
            modal.show();
        }
        
        function hesaplaIade() {
            const yeniTarih = document.getElementById('yeni_cikis_tarihi').value;
            const yeniSaat = document.getElementById('yeni_cikis_saati').value;
            
            if (!yeniTarih || !yeniSaat) {
                document.getElementById('hesaplama_sonuclari').style.display = 'none';
                return;
            }
            
            // Loading göster
            document.getElementById('hesaplama_sonuclari').style.display = 'block';
            document.getElementById('yeni_toplam').textContent = 'Hesaplanıyor...';
            document.getElementById('hesaplanan_iade').textContent = 'Hesaplanıyor...';
            document.getElementById('odenen_tutar').textContent = 'Hesaplanıyor...';
            document.getElementById('gece_sayisi').textContent = 'Hesaplanıyor...';
            document.getElementById('iade_orani').textContent = 'Hesaplanıyor...';
            document.getElementById('gercek_iade_tutari').textContent = 'Hesaplanıyor...';
            
            // AJAX ile fiyat hesapla
            const requestData = {
                rezervasyon_id: <?php echo $rezervasyon['id']; ?>,
                yeni_cikis_tarihi: yeniTarih,
                yeni_cikis_saati: yeniSaat
            };
            
            fetch('ajax/calculate-early-checkout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    
                    document.getElementById('yeni_toplam').textContent = data.yeni_toplam + '₺';
                    document.getElementById('hesaplanan_iade').textContent = data.hesaplanan_iade + '₺';
                    document.getElementById('odenen_tutar').textContent = data.odenen_tutar + '₺';
                    document.getElementById('gece_sayisi').textContent = data.gece_sayisi;
                    document.getElementById('iade_orani').textContent = data.iade_orani + '%';
                    document.getElementById('gercek_iade_tutari').textContent = data.gercek_iade_tutari + '₺';
                    document.getElementById('hesaplama_sonuclari').style.display = 'block';
                    
                    // Manuel iade tutarı için maksimum değeri güncelle
                    document.getElementById('manuel_iade_tutari').max = data.gercek_iade_tutari;
                } else {
                    document.getElementById('hesaplama_sonuclari').style.display = 'none';
                    alert('Hesaplama hatası: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('hesaplama_sonuclari').style.display = 'none';
                alert('Hesaplama sırasında hata oluştu.');
            });
        }

        // Sayfa yüklendiğinde event listener'ları ekle
        document.addEventListener('DOMContentLoaded', function() {
            // WhatsApp gönderme fonksiyonu
            document.querySelectorAll('.whatsapp-send').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const rezervasyonId = this.getAttribute('data-rezervasyon-id');
                    const type = this.getAttribute('data-type');
                    const phone = this.getAttribute('data-phone');
                    
                    // Loading spinner göster
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gönderiliyor...';
                    this.style.pointerEvents = 'none';
                    
                    // AJAX isteği
                    fetch('ajax/generate-whatsapp-link.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            rezervasyon_id: rezervasyonId,
                            type: type,
                            phone: phone
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // WhatsApp linkini aç
                            window.open(data.whatsapp_link, '_blank');
                        } else {
                            alert('Hata: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                    })
                    .finally(() => {
                        // Loading spinner'ı kaldır
                        this.innerHTML = originalText;
                        this.style.pointerEvents = 'auto';
                    });
                });
            });
        });
        
        // Ödeme ekleme AJAX
        document.addEventListener('DOMContentLoaded', function() {
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = document.getElementById('paymentSubmitBtn');
                    const originalText = submitBtn.innerHTML;
                    
                    // Butonu devre dışı bırak
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Ekleniyor...';
                    
                    const formData = new FormData(this);
                    
                    fetch('ajax/process-payment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data.success) {
                                alert('Ödeme başarıyla eklendi!');
                                location.reload();
                            } else {
                                alert('Hata: ' + data.message);
                            }
                        } catch (e) {
                            alert('Sunucu hatası oluştu.');
                        }
                    })
                    .catch(error => {
                        alert('Ödeme eklenirken hata oluştu.');
                    })
                    .finally(() => {
                        // Butonu tekrar aktif et
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    });
                });
            }
        });
    </script>


    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-marker {
            position: absolute;
            left: -23px;
            top: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #dee2e6;
        }
        
        .timeline-item-latest .timeline-marker {
            box-shadow: 0 0 0 2px #007bff;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #dee2e6;
        }
        
        .timeline-title {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: 600;
        }
        
        .timeline-text {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: #6c757d;
        }
        
        .timeline-date {
            font-size: 12px;
        }
    </style>
    
</body>
</html>
