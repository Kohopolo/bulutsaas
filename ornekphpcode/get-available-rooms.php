
<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Cache-busting headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Hem GET hem POST parametrelerini kabul et
$oda_tipi_id = intval($_GET['oda_tipi_id'] ?? $_POST['oda_tipi_id'] ?? 0);
$giris_tarihi = $_GET['giris_tarihi'] ?? $_POST['giris_tarihi'] ?? '';
$cikis_tarihi = $_GET['cikis_tarihi'] ?? $_POST['cikis_tarihi'] ?? '';

if (!$oda_tipi_id || !$giris_tarihi || !$cikis_tarihi) {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler']);
    exit;
}

try {
    // Oda tipinin check-in ve check-out saatlerini al
    $oda_tipi_saatleri = fetchOne("SELECT checkin_saati, checkout_saati FROM oda_tipleri WHERE id = ?", [$oda_tipi_id]);
    $checkin_saati = $oda_tipi_saatleri['checkin_saati'] ?? '14:00:00';
    $checkout_saati = $oda_tipi_saatleri['checkout_saati'] ?? '12:00:00';
    
    // Tarih formatını düzenle - Veritabanından alınan saatleri kullan
    if (strlen($giris_tarihi) == 10) { // Y-m-d formatı
        $giris_tarihi = $giris_tarihi . ' ' . $checkin_saati; // Veritabanından alınan check-in saati
    } else {
        $giris_tarihi = date('Y-m-d H:i:s', strtotime($giris_tarihi));
    }
    
    if (strlen($cikis_tarihi) == 10) { // Y-m-d formatı
        $cikis_tarihi = $cikis_tarihi . ' ' . $checkout_saati; // Veritabanından alınan check-out saati
    } else {
        $cikis_tarihi = date('Y-m-d H:i:s', strtotime($cikis_tarihi));
    }
    
    // Müsait odaları getir - Basit çakışma kontrolü
    $sql = "SELECT DISTINCT odn.* 
            FROM oda_numaralari odn 
            LEFT JOIN rezervasyonlar r ON odn.id = r.oda_numarasi_id 
                AND r.durum IN ('onaylandi', 'check_in')
                AND (
                    (r.giris_tarihi <= ? AND r.cikis_tarihi > ?)
                )
            WHERE odn.oda_tipi_id = ? 
            AND odn.durum = 'aktif'
            AND r.id IS NULL
            ORDER BY odn.oda_numarasi ASC";
    
    // Mevcut datetime'ı oluştur
    $mevcut_datetime = date('Y-m-d H:i:s');
    
    $rooms = fetchAll($sql, [
        $cikis_tarihi, $giris_tarihi,  // Basit çakışma kontrolü
        $oda_tipi_id   // Oda tipi ID
    ]);
    
    echo json_encode([
        'success' => true,
        'rooms' => $rooms
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
}
?>
