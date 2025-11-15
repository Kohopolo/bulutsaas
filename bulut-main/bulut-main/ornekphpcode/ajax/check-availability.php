<?php
require_once __DIR__ . '/../csrf_protection.php';
require_once __DIR__ . '/../../includes/xss_protection.php';
require_once __DIR__ . '/../../includes/session_security.php';
require_once __DIR__ . '/../../includes/error_handler.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/price-functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// CSRF koruması
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Güvenlik hatası']);
    exit;
}

// POST verilerini al
$giris_tarihi = $_POST['giris_tarihi'] ?? '';
$cikis_tarihi = $_POST['cikis_tarihi'] ?? '';
$oda_tipi_id = intval($_POST['oda_tipi_id'] ?? 0);
$rezervasyon_id = intval($_POST['rezervasyon_id'] ?? 0); // Düzenleme durumunda

// Tarih validasyonu
if (empty($giris_tarihi) || empty($cikis_tarihi) || $oda_tipi_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Eksik bilgi']);
    exit;
}

if (strtotime($cikis_tarihi) <= strtotime($giris_tarihi)) {
    echo json_encode(['success' => false, 'message' => 'Çıkış tarihi giriş tarihinden sonra olmalıdır']);
    exit;
}

try {
    // Oda tipi bilgilerini getir
    $oda_tipi = fetchOne("SELECT * FROM oda_tipleri WHERE id = ? AND durum = 'aktif'", [$oda_tipi_id]);
    if (!$oda_tipi) {
        echo json_encode(['success' => false, 'message' => 'Oda tipi bulunamadı']);
        exit;
    }
    
    // Toplam oda sayısını hesapla
    $toplam_oda = fetchOne("SELECT COUNT(*) as count FROM oda_numaralari WHERE oda_tipi_id = ? AND durum = 'aktif'", [$oda_tipi_id])['count'];
    
    // Oda tipinin check-in ve check-out saatlerini al
    $oda_tipi_saatleri = fetchOne("SELECT checkin_saati, checkout_saati FROM oda_tipleri WHERE id = ?", [$oda_tipi_id]);
    $checkin_saati = $oda_tipi_saatleri['checkin_saati'] ?? '14:00:00';
    $checkout_saati = $oda_tipi_saatleri['checkout_saati'] ?? '12:00:00';
    
    // Rezerve oda sayısını hesapla (mevcut rezervasyonu hariç tut)
    // Doluluk oranı sayfasındaki mantık: Sadece gerçekten dolu olan odaları filtrele
    $rezerve_query = "
        SELECT COUNT(DISTINCT r.oda_numarasi_id) as count 
        FROM rezervasyonlar r 
        INNER JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        WHERE r.oda_tipi_id = ? 
        AND r.durum IN ('onaylandi', 'check_in') 
        AND (
            -- Aktif rezervasyon (check-in yapılmış ve henüz çıkmamış)
            (r.durum = 'check_in' 
             AND DATE(?) >= DATE(r.giris_tarihi) 
             AND DATE(?) < DATE(r.cikis_tarihi))
            OR
            -- Checkout saati öncesi dolu (bugün checkout olacak ama saat henüz gelmemiş)
            (r.durum = 'check_in' 
             AND DATE(?) = DATE(r.cikis_tarihi)
             AND TIME(NOW()) < TIME(r.cikis_tarihi))
            OR
            -- Rezerve (onaylanmış ama henüz check-in yapılmamış)
            (r.durum = 'onaylandi' 
             AND DATE(?) < DATE(r.cikis_tarihi))
        )
    ";
    
    $rezerve_params = [
        $oda_tipi_id, 
        $giris_tarihi, $giris_tarihi,  // Aktif rezervasyon kontrolü
        $giris_tarihi, $giris_tarihi,  // Checkout öncesi dolu kontrolü (TIME(NOW()) kullanılıyor)
        $giris_tarihi   // Rezerve kontrolü
    ];
    
    if ($rezervasyon_id > 0) {
        $rezerve_query .= " AND r.id != ?";
        $rezerve_params[] = $rezervasyon_id;
    }
    
    $rezerve_sayisi = fetchOne($rezerve_query, $rezerve_params)['count'];
    
    // Müsait oda sayısını hesapla
    $musait_oda_sayisi = $toplam_oda - $rezerve_sayisi;
    
    // Müsait odaları getir
    $musait_odalar = [];
    if ($musait_oda_sayisi > 0) {
        $musait_query = "
            SELECT DISTINCT odn.id, odn.oda_numarasi, odn.kat 
            FROM oda_numaralari odn 
            LEFT JOIN rezervasyonlar r ON odn.id = r.oda_numarasi_id 
                AND r.durum IN ('onaylandi', 'check_in')
                AND (
                    -- Doluluk oranı sayfasındaki mantık: Sadece gerçekten dolu olan odaları filtrele
                    -- Aktif rezervasyon (check-in yapılmış ve henüz çıkmamış)
                    (r.durum = 'check_in' 
                     AND DATE(?) >= DATE(r.giris_tarihi) 
                     AND DATE(?) < DATE(r.cikis_tarihi))
                    OR
                    -- Checkout saati öncesi dolu (bugün checkout olacak ama saat henüz gelmemiş)
                    (r.durum = 'check_in' 
                     AND DATE(?) = DATE(r.cikis_tarihi)
                     AND TIME(NOW()) < TIME(r.cikis_tarihi))
                    OR
                    -- Rezerve (onaylanmış ama henüz check-in yapılmamış)
                    (r.durum = 'onaylandi' 
                     AND DATE(?) < DATE(r.cikis_tarihi))
                )
            WHERE odn.oda_tipi_id = ? 
            AND odn.durum = 'aktif'
            AND r.id IS NULL
        ";
        
        $musait_params = [
            $giris_tarihi, $giris_tarihi,  // Aktif rezervasyon kontrolü
            $giris_tarihi, $giris_tarihi,  // Checkout öncesi dolu kontrolü (TIME(NOW()) kullanılıyor)
            $giris_tarihi,  // Rezerve kontrolü
            $oda_tipi_id   // Oda tipi ID
        ];
        
        if ($rezervasyon_id > 0) {
            $musait_query .= " AND r.id != ?";
            $musait_params[] = $rezervasyon_id;
        }
        
        $musait_query .= ") ORDER BY odn.kat ASC, odn.oda_numarasi ASC";
        
        $musait_odalar = fetchAll($musait_query, $musait_params);
    }
    
    // Fiyat hesapla (basit hesaplama - 1 yetişkin için)
    $fiyat_bilgisi = null;
    try {
        $fiyat_hesaplama = calculateSimpleRoomPrice($oda_tipi_id, $giris_tarihi, $cikis_tarihi);
        if ($fiyat_hesaplama && !isset($fiyat_hesaplama['hata'])) {
            $gece_sayisi = (strtotime($cikis_tarihi) - strtotime($giris_tarihi)) / (60 * 60 * 24);
            $fiyat_bilgisi = [
                'toplam_fiyat' => number_format($fiyat_hesaplama['toplam_fiyat'], 2, ',', '.'),
                'ortalama_fiyat' => number_format($fiyat_hesaplama['ortalama_fiyat'], 2, ',', '.'),
                'gece_sayisi' => $gece_sayisi
            ];
        }
    } catch (Exception $e) {
        // Fiyat hesaplama hatası - devam et
    }
    
    // Sonucu döndür
    echo json_encode([
        'success' => true,
        'toplam_oda' => $toplam_oda,
        'rezerve_sayisi' => $rezerve_sayisi,
        'musait_oda_sayisi' => $musait_oda_sayisi,
        'musait_odalar' => $musait_odalar,
        'fiyat_bilgisi' => $fiyat_bilgisi
    ]);
    
} catch (Exception $e) {
    logError("Müsaitlik kontrolü hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Sistem hatası oluştu']);
}
?>