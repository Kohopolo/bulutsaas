<?php
/**
 * Template Ayarlarını Yükle
 */

header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../includes/detailed_permission_functions.php';

// Yetki kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı!']);
    exit;
}

try {
    // Template ayarlarını yükle
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM template_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Varsayılan değerler
    $defaultSettings = [
        'otel_adi' => 'Premium Hotel',
        'logo_url' => 'assets/images/logo.svg',
        'otel_adres' => '123 Ocean Drive, Breezie Island',
        'otel_telefon' => '(123) 456-7890',
        'facebook_url' => '#',
        'twitter_url' => '#',
        'instagram_url' => '#',
        'pinterest_url' => '#',
        'linkedin_url' => '#'
    ];

    // Mevcut ayarları varsayılanlarla birleştir
    $finalSettings = array_merge($defaultSettings, $settings);

    echo json_encode(['success' => true, 'settings' => $finalSettings]);

} catch (Exception $e) {
    error_log("Template settings load error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Yükleme hatası: ' . $e->getMessage()]);
}
?>

