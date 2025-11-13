<?php
require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRF token kontrolü - daha esnek
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF token hatası. Token: ' . substr($csrf_token, 0, 10) . '...'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $oda_tipi_id = intval($_POST['oda_tipi_id'] ?? 0);
    $giris_tarihi = $_POST['giris_tarihi'] ?? '';
    $cikis_tarihi = $_POST['cikis_tarihi'] ?? '';
    
    // Validasyon
    if ($oda_tipi_id <= 0) {
        throw new Exception('Geçersiz oda tipi');
    }
    
    if (empty($giris_tarihi) || empty($cikis_tarihi)) {
        throw new Exception('Tarih bilgileri eksik');
    }
    
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
    
    // Müsait odaları bul - Basit çakışma kontrolü
    $sql = "SELECT DISTINCT odn.id, odn.oda_numarasi, odn.oda_tipi_id, ot.oda_tipi_adi
            FROM oda_numaralari odn
            INNER JOIN oda_tipleri ot ON odn.oda_tipi_id = ot.id
            LEFT JOIN rezervasyonlar r ON odn.id = r.oda_numarasi_id 
                AND r.durum IN ('onaylandi', 'check_in')
                AND (
                    (r.giris_tarihi <= ? AND r.cikis_tarihi > ?)
                )
            WHERE odn.oda_tipi_id = ? 
            AND odn.durum = 'aktif'
            AND r.id IS NULL
            ORDER BY CAST(odn.oda_numarasi AS UNSIGNED)";
    
    // Mevcut saati al
    $mevcut_saat = date('H:i:s');
    
    // Mevcut datetime'ı oluştur
    $mevcut_datetime = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $cikis_tarihi, $giris_tarihi,  // Basit çakışma kontrolü
        $oda_tipi_id   // Oda tipi ID
    ]);
    
    $odalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sonuç mesajını iyileştir
    $mesaj = count($odalar) > 0 
        ? count($odalar) . ' müsait oda bulundu' 
        : 'Seçilen tarihler için müsait oda bulunamadı';
    
    echo json_encode([
        'success' => true,
        'odalar' => $odalar,
        'count' => count($odalar),
        'message' => $mesaj
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('Get musait odalar error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Odalar yüklenirken hata oluştu.',
        'message' => 'Odalar yüklenirken hata oluştu. Lütfen tekrar deneyin.'
    ], JSON_UNESCAPED_UNICODE);
}
?>