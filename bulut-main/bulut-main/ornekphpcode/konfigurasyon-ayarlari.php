<?php
require_once '../config/dynamic-config.php';
require_once '../includes/session_security.php';
require_once '../includes/functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('sistem_ayarlari', 'Sistem ayarları yetkiniz bulunmamaktadır.');

$message = '';
$messageType = '';

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_server':
                $remoteHost = sanitizeString($_POST['remote_host'] ?? '');
                $remotePort = intval($_POST['remote_port'] ?? 80);
                $remoteProtocol = sanitizeString($_POST['remote_protocol'] ?? 'http');
                $remotePath = sanitizeString($_POST['remote_path'] ?? '');
                
                if ($remoteHost && $remotePort) {
                    setConfig('server.remote.host', $remoteHost);
                    setConfig('server.remote.port', $remotePort);
                    setConfig('server.remote.protocol', $remoteProtocol);
                    setConfig('server.remote.path', $remotePath);
                    
                    $message = 'Sunucu ayarları başarıyla güncellendi!';
                    $messageType = 'success';
                } else {
                    $message = 'Geçersiz sunucu ayarları!';
                    $messageType = 'error';
                }
                break;
                
            case 'update_database':
                $dbHost = sanitizeString($_POST['db_host'] ?? '');
                $dbName = sanitizeString($_POST['db_name'] ?? '');
                $dbUser = sanitizeString($_POST['db_user'] ?? '');
                $dbPass = sanitizeString($_POST['db_pass'] ?? '');
                
                if ($dbHost && $dbName && $dbUser) {
                    setConfig('database.remote.host', $dbHost);
                    setConfig('database.remote.database', $dbName);
                    setConfig('database.remote.username', $dbUser);
                    setConfig('database.remote.password', $dbPass);
                    
                    $message = 'Veritabanı ayarları başarıyla güncellendi!';
                    $messageType = 'success';
                } else {
                    $message = 'Geçersiz veritabanı ayarları!';
                    $messageType = 'error';
                }
                break;
                
            case 'update_sync':
                $syncEnabled = isset($_POST['sync_enabled']);
                $syncInterval = intval($_POST['sync_interval'] ?? 60);
                
                setConfig('sync.enabled', $syncEnabled);
                setConfig('sync.interval', $syncInterval * 1000); // milisaniye
                
                $message = 'Senkronizasyon ayarları başarıyla güncellendi!';
                $messageType = 'success';
                break;
                
            case 'reset_config':
                if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'yes') {
                    $dynamicConfig->reset();
                    $message = 'Konfigürasyon varsayılan ayarlara sıfırlandı!';
                    $messageType = 'success';
                }
                break;
        }
    }
}

// Mevcut ayarları al
$currentConfig = getConfig();
$remoteServer = $currentConfig['server']['remote'];
$database = $currentConfig['database']['remote'];
$sync = $currentConfig['sync'];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigürasyon Ayarları - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .config-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .config-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-cogs me-2"></i>Konfigürasyon Ayarları</h1>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Sunucu Ayarları -->
                <div class="config-section">
                    <div class="config-header">
                        <h3><i class="fas fa-server me-2"></i>Sunucu Ayarları</h3>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_server">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="remote_host" class="form-label">Sunucu Adresi</label>
                                    <input type="text" class="form-control" id="remote_host" name="remote_host" 
                                           value="<?= htmlspecialchars($remoteServer['host']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="remote_port" class="form-label">Port</label>
                                    <input type="number" class="form-control" id="remote_port" name="remote_port" 
                                           value="<?= htmlspecialchars($remoteServer['port']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="remote_protocol" class="form-label">Protokol</label>
                                    <select class="form-select" id="remote_protocol" name="remote_protocol">
                                        <option value="http" <?= $remoteServer['protocol'] === 'http' ? 'selected' : '' ?>>HTTP</option>
                                        <option value="https" <?= $remoteServer['protocol'] === 'https' ? 'selected' : '' ?>>HTTPS</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remote_path" class="form-label">Path</label>
                            <input type="text" class="form-control" id="remote_path" name="remote_path" 
                                   value="<?= htmlspecialchars($remoteServer['path']) ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Sunucu Ayarlarını Kaydet
                        </button>
                    </form>
                    
                    <div class="mt-3">
                        <strong>Mevcut URL:</strong> 
                        <span class="badge bg-info"><?= getRemoteServerUrl() ?></span>
                    </div>
                </div>
                
                <!-- Veritabanı Ayarları -->
                <div class="config-section">
                    <div class="config-header">
                        <h3><i class="fas fa-database me-2"></i>Veritabanı Ayarları</h3>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_database">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Veritabanı Sunucusu</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" 
                                           value="<?= htmlspecialchars($database['host']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Veritabanı Adı</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" 
                                           value="<?= htmlspecialchars($database['database']) ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Kullanıcı Adı</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" 
                                           value="<?= htmlspecialchars($database['username']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Şifre</label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass" 
                                           value="<?= htmlspecialchars($database['password']) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Veritabanı Ayarlarını Kaydet
                        </button>
                    </form>
                </div>
                
                <!-- Senkronizasyon Ayarları -->
                <div class="config-section">
                    <div class="config-header">
                        <h3><i class="fas fa-sync me-2"></i>Senkronizasyon Ayarları</h3>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_sync">
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sync_enabled" name="sync_enabled" 
                                       <?= $sync['enabled'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sync_enabled">
                                    Otomatik Senkronizasyon
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sync_interval" class="form-label">Senkronizasyon Aralığı (saniye)</label>
                            <input type="number" class="form-control" id="sync_interval" name="sync_interval" 
                                   value="<?= $sync['interval'] / 1000 ?>" min="10" max="3600">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Senkronizasyon Ayarlarını Kaydet
                        </button>
                    </form>
                </div>
                
                <!-- Sistem Bilgileri -->
                <div class="config-section">
                    <div class="config-header">
                        <h3><i class="fas fa-info-circle me-2"></i>Sistem Bilgileri</h3>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Uygulama Adı:</strong></td>
                                    <td><?= htmlspecialchars($currentConfig['app']['name']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Sürüm:</strong></td>
                                    <td><?= htmlspecialchars($currentConfig['app']['version']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Local Server:</strong></td>
                                    <td><span class="badge bg-success"><?= getLocalServerUrl() ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Remote Server:</strong></td>
                                    <td><span class="badge bg-info"><?= getRemoteServerUrl() ?></span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Offline Mode:</strong></td>
                                    <td><span class="badge bg-<?= $currentConfig['features']['offline_mode'] ? 'success' : 'danger' ?>">
                                        <?= $currentConfig['features']['offline_mode'] ? 'Aktif' : 'Pasif' ?>
                                    </span></td>
                                </tr>
                                <tr>
                                    <td><strong>PWA:</strong></td>
                                    <td><span class="badge bg-<?= $currentConfig['features']['pwa'] ? 'success' : 'danger' ?>">
                                        <?= $currentConfig['features']['pwa'] ? 'Aktif' : 'Pasif' ?>
                                    </span></td>
                                </tr>
                                <tr>
                                    <td><strong>Desktop App:</strong></td>
                                    <td><span class="badge bg-<?= $currentConfig['features']['desktop_app'] ? 'success' : 'danger' ?>">
                                        <?= $currentConfig['features']['desktop_app'] ? 'Aktif' : 'Pasif' ?>
                                    </span></td>
                                </tr>
                                <tr>
                                    <td><strong>Web App:</strong></td>
                                    <td><span class="badge bg-<?= $currentConfig['features']['web_app'] ? 'success' : 'danger' ?>">
                                        <?= $currentConfig['features']['web_app'] ? 'Aktif' : 'Pasif' ?>
                                    </span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tehlikeli İşlemler -->
                <div class="config-section border-danger">
                    <div class="config-header text-danger">
                        <h3><i class="fas fa-exclamation-triangle me-2"></i>Tehlikeli İşlemler</h3>
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('Tüm konfigürasyonu varsayılan ayarlara sıfırlamak istediğinizden emin misiniz?')">
                        <input type="hidden" name="action" value="reset_config">
                        <input type="hidden" name="confirm_reset" value="yes">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-warning me-2"></i>
                            <strong>Dikkat!</strong> Bu işlem tüm konfigürasyon ayarlarını varsayılan değerlere sıfırlar.
                        </div>
                        
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-undo me-2"></i>Konfigürasyonu Sıfırla
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

