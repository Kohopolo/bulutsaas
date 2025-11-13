<?php
require_once __DIR__ . '/../csrf_protection.php';
require_once __DIR__ . '/../../includes/xss_protection.php';
require_once __DIR__ . '/../../includes/session_security.php';
require_once __DIR__ . '/../../includes/error_handler.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';

// Debug mode için
$debug_mode = isset($_GET['debug']) || isset($_POST['debug']);

// Admin kontrolü
if (!checkAdmin()) {
    http_response_code(403);
    $response = ['success' => false, 'message' => 'Yetkisiz erişim'];
    if ($debug_mode) $response['debug'] = 'Admin check failed';
    echo json_encode($response);
    exit;
}

// CSRF koruması - debug modda atla
$csrf_token = $_POST['csrf_token'] ?? '';
if (!$debug_mode && !validateCSRFToken($csrf_token)) {
    http_response_code(403);
    $response = ['success' => false, 'message' => 'Güvenlik hatası'];
    if ($debug_mode) {
        $response['debug'] = [
            'error' => 'CSRF validation failed',
            'received_token' => $csrf_token,
            'session_token' => $_SESSION['csrf_token'] ?? 'not_set',
            'post_data' => array_keys($_POST)
        ];
    }
    echo json_encode($response);
    exit;
}

// POST verilerini al
$giris_tarihi = $_POST['giris_tarihi'] ?? '';
$cikis_tarihi = $_POST['cikis_tarihi'] ?? '';
$yetiskin_sayisi = intval($_POST['yetiskin_sayisi'] ?? 1);
$cocuk_sayisi = intval($_POST['cocuk_sayisi'] ?? 0);
$cocuk_yaslari = $_POST['cocuk_yaslari'] ?? [];

// Tarih validasyonu
if (empty($giris_tarihi) || empty($cikis_tarihi)) {
    echo json_encode(['success' => false, 'message' => 'Tarih bilgileri eksik']);
    exit;
}

if (strtotime($cikis_tarihi) <= strtotime($giris_tarihi)) {
    echo json_encode(['success' => false, 'message' => 'Çıkış tarihi giriş tarihinden sonra olmalıdır']);
    exit;
}

try {
    // Tüm aktif oda tiplerini getir
    $oda_tipleri = fetchAll("
        SELECT id, oda_tipi_adi, max_yetiskin, max_cocuk, base_price, fiyatlama_sistemi,
               yetiskin_carpanlari, cocuk_yas_araligi, ucretsiz_cocuk_yaslari
        FROM oda_tipleri 
        WHERE durum = 'aktif' 
        ORDER BY oda_tipi_adi ASC
    ");
    
    $available_room_types = [];
    
    foreach ($oda_tipleri as $oda_tipi) {
        // Kapasite kontrolü
        $toplam_kisi = $yetiskin_sayisi + $cocuk_sayisi;
        $max_toplam = $oda_tipi['max_yetiskin'] + $oda_tipi['max_cocuk'];
        
        // Oda tipi kapasitesi yeterli mi?
        if ($oda_tipi['max_yetiskin'] >= $yetiskin_sayisi && 
            $oda_tipi['max_cocuk'] >= $cocuk_sayisi && 
            $max_toplam >= $toplam_kisi) {
            
            // Bu oda tipi için müsait oda var mı kontrol et
            $toplam_oda = fetchOne("
                SELECT COUNT(*) as count 
                FROM oda_numaralari 
                WHERE oda_tipi_id = ? AND durum = 'aktif'
            ", [$oda_tipi['id']])['count'];
            
            // Oda tipinin check-in ve check-out saatlerini al
            $oda_tipi_saatleri = fetchOne("SELECT checkin_saati, checkout_saati FROM oda_tipleri WHERE id = ?", [$oda_tipi['id']]);
            $checkin_saati = $oda_tipi_saatleri['checkin_saati'] ?? '14:00:00';
            $checkout_saati = $oda_tipi_saatleri['checkout_saati'] ?? '12:00:00';
            
            // Rezerve oda sayısını hesapla - Basit yöntemle
            $rezerve_sayisi = fetchOne("
                SELECT COUNT(DISTINCT COALESCE(r.oda_numarasi_id, r.id)) as count 
                FROM rezervasyonlar r 
                WHERE r.oda_tipi_id = ? 
                AND r.durum IN ('onaylandi', 'check_in') 
                AND (
                    (r.giris_tarihi <= ? AND r.cikis_tarihi > ?)
                )
            ", [
                $oda_tipi['id'], 
                $cikis_tarihi, $giris_tarihi  // Basit çakışma kontrolü
            ])['count'];
            
            $musait_oda_sayisi = $toplam_oda - $rezerve_sayisi;
            
            // Müsait oda varsa listeye ekle
            if ($musait_oda_sayisi > 0) {
                $available_room_types[] = [
                    'id' => $oda_tipi['id'],
                    'oda_tipi_adi' => $oda_tipi['oda_tipi_adi'],
                    'max_yetiskin' => $oda_tipi['max_yetiskin'],
                    'max_cocuk' => $oda_tipi['max_cocuk'],
                    'base_price' => $oda_tipi['base_price'],
                    'fiyatlama_sistemi' => $oda_tipi['fiyatlama_sistemi'],
                    'toplam_oda' => $toplam_oda,
                    'musait_oda_sayisi' => $musait_oda_sayisi,
                    'yetiskin_carpanlari' => $oda_tipi['yetiskin_carpanlari'],
                    'cocuk_yas_araligi' => $oda_tipi['cocuk_yas_araligi'],
                    'ucretsiz_cocuk_yaslari' => $oda_tipi['ucretsiz_cocuk_yaslari']
                ];
            }
        }
    }
    
    // Sonucu döndür
    $response = [
        'success' => true,
        'available_room_types' => $available_room_types,
        'total_found' => count($available_room_types)
    ];
    
    // Debug bilgileri ekle
    if ($debug_mode) {
        $response['debug'] = [
            'input_params' => [
                'giris_tarihi' => $giris_tarihi,
                'cikis_tarihi' => $cikis_tarihi,
                'yetiskin_sayisi' => $yetiskin_sayisi,
                'cocuk_sayisi' => $cocuk_sayisi,
                'cocuk_yaslari' => $cocuk_yaslari
            ],
            'total_room_types_found' => count($oda_tipleri),
            'processing_details' => []
        ];
        
        // Her oda tipi için debug bilgisi
        foreach ($oda_tipleri as $oda_tipi) {
            $toplam_kisi = $yetiskin_sayisi + $cocuk_sayisi;
            $max_toplam = $oda_tipi['max_yetiskin'] + $oda_tipi['max_cocuk'];
            
            $capacity_check = ($oda_tipi['max_yetiskin'] >= $yetiskin_sayisi && 
                              $oda_tipi['max_cocuk'] >= $cocuk_sayisi && 
                              $max_toplam >= $toplam_kisi);
            
            $toplam_oda = fetchOne("
                SELECT COUNT(*) as count 
                FROM oda_numaralari 
                WHERE oda_tipi_id = ? AND durum = 'aktif'
            ", [$oda_tipi['id']])['count'];
            
            // Debug için de aynı mantığı kullan
            $rezerve_sayisi = fetchOne("
                SELECT COUNT(DISTINCT COALESCE(r.oda_numarasi_id, r.id)) as count 
                FROM rezervasyonlar r 
                WHERE r.oda_tipi_id = ? 
                AND r.durum IN ('onaylandi', 'check_in') 
                AND (
                    (r.giris_tarihi <= ? AND r.cikis_tarihi > ?)
                )
            ", [
                $oda_tipi['id'], 
                $cikis_tarihi, $giris_tarihi  // Basit çakışma kontrolü
            ])['count'];
            
            $musait_oda_sayisi = $toplam_oda - $rezerve_sayisi;
            
            $response['debug']['processing_details'][] = [
                'room_type' => $oda_tipi['oda_tipi_adi'],
                'capacity_check' => $capacity_check,
                'max_adult' => $oda_tipi['max_yetiskin'],
                'max_child' => $oda_tipi['max_cocuk'],
                'total_rooms' => $toplam_oda,
                'reserved_rooms' => $rezerve_sayisi,
                'available_rooms' => $musait_oda_sayisi,
                'included_in_result' => ($capacity_check && $musait_oda_sayisi > 0)
            ];
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logError("Oda tipi listesi hatası: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Sistem hatası oluştu: ' . $e->getMessage()];
    if ($debug_mode) {
        $response['debug'] = [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    echo json_encode($response);
}
?>