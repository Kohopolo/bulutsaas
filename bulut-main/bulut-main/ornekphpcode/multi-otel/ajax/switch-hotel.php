<?php
/**
 * Multi Otel - Otel Değiştirme AJAX
 * Kullanıcının otel değiştirmesi için AJAX endpoint
 */

require_once '../../csrf_protection.php';
require_once '../../../includes/xss_protection.php';
require_once '../../../includes/session_security.php';
require_once '../../../includes/error_handler.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../includes/multi-otel-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// CSRF token kontrolü
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Güvenlik hatası']);
    exit;
}

$otel_id = intval($_POST['otel_id'] ?? 0);

if ($otel_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz otel ID']);
    exit;
}

// Kullanıcının bu otele erişim yetkisi var mı kontrol et
$user_oteller = getUserOteller($_SESSION['user_id']);
$has_access = false;

foreach ($user_oteller as $otel) {
    if ($otel['id'] == $otel_id) {
        $has_access = true;
        break;
    }
}

if (!$has_access) {
    echo json_encode(['success' => false, 'message' => 'Bu otele erişim yetkiniz yok']);
    exit;
}

// Otel bilgilerini al
$otel = getOtel($otel_id);
if (!$otel) {
    echo json_encode(['success' => false, 'message' => 'Otel bulunamadı']);
    exit;
}

// Session'a otel bilgilerini kaydet
if (setCurrentOtel($otel_id)) {
    echo json_encode([
        'success' => true, 
        'message' => 'Otel başarıyla değiştirildi',
        'otel' => [
            'id' => $otel['id'],
            'otel_adi' => $otel['otel_adi'],
            'kisa_aciklama' => $otel['kisa_aciklama']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Otel değiştirilemedi']);
}
