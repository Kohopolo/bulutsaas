<?php
require_once '../../config/database.php';
require_once '../csrf_protection.php';
require_once '../../includes/functions.php';

// JSON header
header('Content-Type: application/json');

// CSRF token kontrolü
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Geçersiz CSRF token']);
    exit;
}

// Admin kontrolü
if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('rezervasyon_checkin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Check-in işlemi için yetkiniz bulunmamaktadır']);
    exit;
}

try {
    $rezervasyon_id = intval($_POST['rezervasyon_id'] ?? 0);
    
    if ($rezervasyon_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz rezervasyon ID']);
        exit;
    }
    
    // Rezervasyon bilgilerini al
    $rezervasyon = fetchOne("
        SELECT r.*, ot.oda_tipi_adi 
        FROM rezervasyonlar r 
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
        WHERE r.id = ? AND r.durum = 'onaylandi'
    ", [$rezervasyon_id]);
    
    if (!$rezervasyon) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı veya check-in yapılamaz durumda']);
        exit;
    }
    
    // Müsait odaları bul
    $musait_odalar = fetchAll("
        SELECT odn.* 
        FROM oda_numaralari odn 
        WHERE odn.oda_tipi_id = ? 
        AND odn.durum = 'aktif'
        AND odn.id NOT IN (
            SELECT DISTINCT r.oda_numarasi_id 
            FROM rezervasyonlar r 
            WHERE r.oda_numarasi_id IS NOT NULL 
            AND r.durum = 'check_in' 
            AND r.id != ?
        )
        ORDER BY odn.oda_numarasi ASC
        LIMIT 1
    ", [$rezervasyon['oda_tipi_id'], $rezervasyon_id]);
    
    if (empty($musait_odalar)) {
        echo json_encode(['success' => false, 'message' => 'Bu oda tipi için müsait oda bulunamadı']);
        exit;
    }
    
    $oda_numarasi_id = $musait_odalar[0]['id'];
    
    // Check-in işlemi
    $sql = "UPDATE rezervasyonlar SET durum = 'check_in', oda_numarasi_id = ?, gercek_giris_tarihi = NOW() WHERE id = ?";
    
    if (executeQuery($sql, [$oda_numarasi_id, $rezervasyon_id])) {
        // Oda durumunu dolu yap
        $sql = "UPDATE oda_numaralari SET durum = 'dolu' WHERE id = ?";
        executeQuery($sql, [$oda_numarasi_id]);
        
        // Rezervasyon geçmişi kaydı
        $sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem_tipi, aciklama, kullanici_id, olusturma_tarihi) 
                VALUES (?, 'check_in', 'Check-in işlemi yapıldı - Oda: " . $musait_odalar[0]['oda_numarasi'] . "', ?, NOW())";
        executeQuery($sql, [$rezervasyon_id, $_SESSION['user_id']]);
        
        // Oda geçmişi kaydı
        $sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                VALUES (?, 'aktif', 'dolu', 'Check-in ile oda dolu olarak işaretlendi', ?, NOW())";
        executeQuery($sql, [$oda_numarasi_id, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Check-in işlemi başarıyla tamamlandı. Oda: ' . $musait_odalar[0]['oda_numarasi'] . ' dolu olarak işaretlendi.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Check-in işlemi sırasında hata oluştu']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
}
?>