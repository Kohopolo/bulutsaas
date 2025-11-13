<?php
require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
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
if (!hasDetailedPermission('kanal_senkronizasyon')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Kanal senkronizasyon yetkiniz bulunmamaktadır']);
    exit;
}

try {
    // Veritabanı bağlantısını test et
    $db_test = fetchOne("SELECT 1 as test");
    $db_online = $db_test !== false;
    
    // Son senkronizasyon zamanını kontrol et
    $last_sync = fetchOne("SELECT MAX(sync_time) as last_sync FROM sync_log");
    $last_sync_time = $last_sync['last_sync'] ?? null;
    
    // Son yedekleme zamanını kontrol et
    $last_backup = fetchOne("SELECT MAX(backup_time) as last_backup FROM backup_log");
    $last_backup_time = $last_backup['last_backup'] ?? null;
    
    // Offline veri kontrolü
    $offline_data_path = '../../data/offline_data.json';
    $has_offline_data = file_exists($offline_data_path) && filesize($offline_data_path) > 0;
    
    // Sistem durumu
    $system_status = [
        'online' => $db_online,
        'last_sync' => $last_sync_time,
        'last_backup' => $last_backup_time,
        'has_offline_data' => $has_offline_data,
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
    
    // Bağlantı durumunu belirle
    $connection_status = 'online';
    if (!$db_online) {
        $connection_status = 'offline';
    } elseif ($has_offline_data) {
        $connection_status = 'syncing';
    }
    
    $response = [
        'success' => true,
        'online' => $db_online,
        'connection_status' => $connection_status,
        'system_status' => $system_status
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Hata durumunda offline olarak işaretle
    $response = [
        'success' => false,
        'online' => false,
        'connection_status' => 'offline',
        'error' => $e->getMessage()
    ];
    
    echo json_encode($response);
}
?>
