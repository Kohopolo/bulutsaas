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
if (!hasDetailedPermission('rezervasyon_checkout')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Check-out işlemi için yetkiniz bulunmamaktadır']);
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
        SELECT r.*, odn.oda_numarasi 
        FROM rezervasyonlar r 
        LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id 
        WHERE r.id = ? AND r.durum = 'check_in'
    ", [$rezervasyon_id]);
    
    if (!$rezervasyon) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı veya check-out yapılamaz durumda']);
        exit;
    }
    
    // Check-out işlemi
    $sql = "UPDATE rezervasyonlar SET durum = 'check_out', gercek_cikis_tarihi = NOW() WHERE id = ?";
    
    if (executeQuery($sql, [$rezervasyon_id])) {
        // Oda durumunu temizlik bekliyor yap
        $sql = "UPDATE oda_numaralari SET durum = 'temizlik_bekliyor' WHERE id = ?";
        executeQuery($sql, [$rezervasyon['oda_numarasi_id']]);
        
        // Rezervasyon geçmişi kaydı
        $sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem_tipi, aciklama, kullanici_id, olusturma_tarihi) 
                VALUES (?, 'check_out', 'Check-out işlemi yapıldı - Oda: " . $rezervasyon['oda_numarasi'] . " (Temizlik bekliyor)', ?, NOW())";
        executeQuery($sql, [$rezervasyon_id, $_SESSION['user_id']]);
        
        // Oda geçmişi kaydı
        $sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                VALUES (?, 'dolu', 'temizlik_bekliyor', 'Check-out sonrası temizlik bekliyor', ?, NOW())";
        executeQuery($sql, [$rezervasyon['oda_numarasi_id'], $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Check-out işlemi başarıyla tamamlandı. Oda: ' . $rezervasyon['oda_numarasi'] . ' temizlik bekliyor.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Check-out işlemi sırasında hata oluştu']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
}
?>