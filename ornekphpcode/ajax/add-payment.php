<?php
require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../includes/error_handler.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('rezervasyon_odeme_ekle')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ödeme ekleme yetkiniz bulunmamaktadır.']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}

$rezervasyon_id = intval($_POST['rezervasyon_id'] ?? 0);
$odeme_tutari = floatval($_POST['odeme_tutari'] ?? 0);
$odeme_yontemi = sanitizeString($_POST['odeme_yontemi'] ?? '');
$odeme_aciklama = sanitizeString($_POST['odeme_aciklama'] ?? '');

if (!$rezervasyon_id || $odeme_tutari <= 0 || !$odeme_yontemi) {
    echo json_encode(['success' => false, 'message' => 'Eksik veya geçersiz veriler']);
    exit;
}

try {
    // Rezervasyon bilgilerini al
    $rezervasyon = fetchOne("SELECT * FROM rezervasyonlar WHERE id = ?", [$rezervasyon_id]);
    if (!$rezervasyon) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı']);
        exit;
    }

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
    
    // Geçmişe kaydet
    $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id) VALUES (?, 'odeme_eklendi', ?, ?)";
    executeQuery($gecmis_sql, [
        $rezervasyon_id, 
        "Ödeme eklendi: " . number_format($odeme_tutari, 2) . "₺ (" . $odeme_yontemi . ")" . ($odeme_aciklama ? " - " . $odeme_aciklama : ""), 
        $_SESSION['user_id']
    ]);
    
    $pdo->commit();
    
    // Güncel ödeme listesini getir
    $odemeler = fetchAll("
        SELECT ro.*, u.ad as kullanici_adi, u.soyad as kullanici_soyadi
        FROM rezervasyon_odemeleri ro
        LEFT JOIN kullanicilar u ON ro.kullanici_id = u.id
        WHERE ro.rezervasyon_id = ? AND ro.durum = 'aktif'
        ORDER BY ro.odeme_tarihi DESC
    ", [$rezervasyon_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ödeme başarıyla eklendi',
        'payments' => $odemeler,
        'total_paid' => $toplam_odenen,
        'payment_status' => $odeme_durumu,
        'remaining_amount' => $rezervasyon['toplam_fiyat'] - $toplam_odenen
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Ödeme ekleme hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ödeme eklenirken hata oluştu: ' . $e->getMessage()]);
}
?>
