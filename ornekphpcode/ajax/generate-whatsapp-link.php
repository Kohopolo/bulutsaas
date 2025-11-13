<?php
/**
 * WhatsApp Linki Oluşturma AJAX Endpoint
 */

session_start();
require_once '../../includes/config.php';
require_once '../../includes/security.php';
require_once '../../includes/pdf-token-manager.php';

// JSON response için header
header('Content-Type: application/json');

// Admin kontrolü
require_once '../../includes/functions.php';
if (!checkAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('rezervasyon_goruntule')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Rezervasyon görüntüleme yetkiniz bulunmamaktadır']);
    exit;
}

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST istekleri kabul edilir']);
    exit;
}

// JSON veri al
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz JSON verisi']);
    exit;
}

// Gerekli parametreleri kontrol et
$rezervasyon_id = $input['rezervasyon_id'] ?? null;
$type = $input['type'] ?? null;
$phone = $input['phone'] ?? null;

if (!$rezervasyon_id || !$type || !$phone) {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler']);
    exit;
}

// Geçerli PDF türleri
$valid_types = ['voucher', 'contract', 'receipt'];
if (!in_array($type, $valid_types)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz PDF türü']);
    exit;
}

try {
    // Rezervasyon kontrolü - müşteri bilgileriyle birlikte
    $stmt = $pdo->prepare("
        SELECT r.id, m.telefon as musteri_telefon 
        FROM rezervasyonlar r 
        LEFT JOIN musteriler m ON r.musteri_id = m.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$rezervasyon_id]);
    $rezervasyon = $stmt->fetch();
    
    if (!$rezervasyon) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı']);
        exit;
    }
    
    // WhatsApp linki oluştur
    $whatsappLink = PDFTokenManager::generateWhatsAppPDFLink($rezervasyon_id, $type, $phone);
    
    if (!$whatsappLink) {
        echo json_encode(['success' => false, 'message' => 'WhatsApp linki oluşturulamadı']);
        exit;
    }
    
    // Başarılı response
    echo json_encode([
        'success' => true,
        'whatsapp_link' => $whatsappLink,
        'message' => 'WhatsApp linki başarıyla oluşturuldu'
    ]);
    
} catch (Exception $e) {
    error_log('WhatsApp link generation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Sistem hatası: ' . $e->getMessage()]);
}
?>