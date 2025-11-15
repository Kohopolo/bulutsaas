<?php
// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Oturum süreniz dolmuş!']);
    exit;
}

require_once '../../includes/detailed_permission_functions.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!hasDetailedPermission('script_yonetimi_goruntule')) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok!']);
    exit;
}

$service_id = $_GET['service_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM site_script_settings WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Servis bulunamadı!']);
        exit;
    }
    
    // Form HTML'i oluştur
    $html = '<div class="form-group">
        <label>Tracking ID / API Key *</label>
        <input type="text" class="form-control" name="tracking_id" value="' . htmlspecialchars($service['tracking_id'] ?? '') . '" required>
        <small class="text-muted">Servisin sağladığı ID/Key değerini girin</small>
    </div>';
    
    // Ek alanlar (servis tipine göre)
    if ($service['service_name'] === 'tawk_to') {
        $config = json_decode($service['additional_config'] ?? '{}', true);
        $html .= '<div class="form-group">
            <label>Widget ID</label>
            <input type="text" class="form-control" name="widget_id" value="' . htmlspecialchars($config['widget_id'] ?? '') . '">
        </div>';
    }
    
    if ($service['service_name'] === 'whatsapp_chat') {
        $config = json_decode($service['additional_config'] ?? '{}', true);
        $html .= '<div class="form-group">
            <label>Karşılama Mesajı</label>
            <textarea class="form-control" name="welcome_message" rows="2">' . htmlspecialchars($config['welcome_message'] ?? 'Merhaba, size nasıl yardımcı olabilirim?') . '</textarea>
        </div>';
    }
    
    // Genel ayarlar
    $html .= '
    <hr>
    <h6>Genel Ayarlar</h6>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Pozisyon</label>
                <select class="form-control" name="position">
                    <option value="head" ' . ($service['script_position'] === 'head' ? 'selected' : '') . '>Head</option>
                    <option value="body_start" ' . ($service['script_position'] === 'body_start' ? 'selected' : '') . '>Body Start</option>
                    <option value="body_end" ' . ($service['script_position'] === 'body_end' ? 'selected' : '') . '>Body End</option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Öncelik</label>
                <input type="number" class="form-control" name="priority" value="' . ($service['priority'] ?? 50) . '" min="0" max="100">
            </div>
        </div>
    </div>
    <div class="form-group">
        <label>Cookie Consent Kategorisi</label>
        <select class="form-control" name="consent_category">
            <option value="necessary" ' . ($service['consent_category'] === 'necessary' ? 'selected' : '') . '>Necessary</option>
            <option value="analytics" ' . ($service['consent_category'] === 'analytics' ? 'selected' : '') . '>Analytics</option>
            <option value="marketing" ' . ($service['consent_category'] === 'marketing' ? 'selected' : '') . '>Marketing</option>
            <option value="preferences" ' . ($service['consent_category'] === 'preferences' ? 'selected' : '') . '>Preferences</option>
        </select>
    </div>';
    
    echo json_encode([
        'success' => true,
        'data' => $service,
        'settings_html' => $html
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?>

