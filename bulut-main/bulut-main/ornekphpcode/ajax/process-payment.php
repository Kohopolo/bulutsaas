<?php
/**
 * Ödeme İşlemi API Endpoint
 * Rezervasyon ödemelerini işler ve otomatik muhasebe kayıtları oluşturur
 */

header('Content-Type: application/json');

// Güvenlik kontrolleri
require_once '../csrf_protection.php';
require_once '../../includes/session_security.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/reservation-accounting-integration.php';

// Admin kontrolü
if (!checkAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// CSRF token kontrolü
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz CSRF token']);
    exit;
}

// POST verilerini al
$reservationId = (int)($_POST['reservation_id'] ?? 0);
$paymentAmount = (float)($_POST['payment_amount'] ?? 0);
$paymentMethod = sanitizeString($_POST['payment_method'] ?? '');
$paymentDate = sanitizeString($_POST['payment_date'] ?? date('Y-m-d'));
$description = sanitizeString($_POST['description'] ?? '');

// Validasyon
$errors = [];

if ($reservationId <= 0) {
    $errors[] = 'Geçersiz rezervasyon ID';
}

if ($paymentAmount <= 0) {
    $errors[] = 'Ödeme tutarı 0\'dan büyük olmalıdır';
}

if (!in_array($paymentMethod, ['nakit', 'kredi_karti', 'banka_transferi', 'cek', 'havale'])) {
    $errors[] = 'Geçersiz ödeme yöntemi';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    // Rezervasyon var mı kontrol et
    $reservation = fetchOne("
        SELECT r.*, m.ad as musteri_ad, m.soyad as musteri_soyad
        FROM rezervasyonlar r
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        WHERE r.id = ?
    ", [$reservationId]);
    
    if (!$reservation) {
        throw new Exception('Rezervasyon bulunamadı');
    }
    
    // Ödeme tutarını kontrol et
    $totalPaid = fetchOne("
        SELECT COALESCE(SUM(odeme_tutari), 0) as toplam_odenen
        FROM rezervasyon_odemeleri
        WHERE rezervasyon_id = ? AND durum = 'aktif'
    ", [$reservationId])['toplam_odenen'] ?? 0;
    
    $remainingAmount = $reservation['toplam_tutar'] - $totalPaid;
    
    if ($paymentAmount > $remainingAmount) {
        throw new Exception("Ödeme tutarı kalan tutardan fazla. Kalan tutar: " . number_format($remainingAmount, 2) . " TL");
    }
    
    // Rezervasyon-Muhasebe entegrasyonu
    $integration = new ReservationAccountingIntegration($pdo);
    
    // Ödeme verilerini hazırla
    $paymentData = [
        'amount' => $paymentAmount,
        'method' => $paymentMethod,
        'date' => $paymentDate,
        'description' => $description ?: "Ödeme - {$reservation['musteri_ad']} {$reservation['musteri_soyad']}"
    ];
    
    // Ödeme kaydını oluştur
    $result = $integration->createPaymentRecord($reservationId, $paymentData);
    
    if ($result['success']) {
        // Başarılı yanıt
        echo json_encode([
            'success' => true,
            'message' => 'Ödeme başarıyla kaydedildi',
            'payment_id' => $result['payment_id'],
            'remaining_amount' => $remainingAmount - $paymentAmount
        ]);
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    error_log("Ödeme işlemi hatası: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ödeme işlemi sırasında hata oluştu: ' . $e->getMessage()
    ]);
}

