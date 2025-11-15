<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';

// Güvenli session başlatma
startSecureSession();

// Admin kontrolü
require_once '../includes/functions.php';
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('template_yonetimi', 'Template yönetimi yetkiniz bulunmamaktadır.');

// Türkçe karakter desteği
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// Dinamik yol belirleme fonksiyonu
function getAdminPath($relativePath) {
    $currentDir = dirname(__FILE__);
    $rootDir = dirname($currentDir);
    $fullPath = $rootDir . '/' . $relativePath;
    
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    
    $alternatives = [
        '../' . $relativePath,
        './' . $relativePath
    ];
    
    foreach ($alternatives as $altPath) {
        if (file_exists($altPath)) {
            return $altPath;
        }
    }
    
    return $relativePath;
}

require_once getAdminPath('config/database.php');
require_once getAdminPath('includes/functions.php');
require_once getAdminPath('includes/TemplateEngine.php');

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('template_goruntule', 'Template görüntüleme yetkiniz bulunmamaktadır.');

// Güvenlik kontrolü
if (!startSecureSession()) {
    logSecurityEvent('SESSION_SECURITY_VIOLATION', 'Güvensiz session tespit edildi');
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Template Engine'i başlat
$templateEngine = new TemplateEngine($pdo);

// CSRF token kontrolü
if ($_POST && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $error_message = 'Güvenlik hatası: Geçersiz form token.';
    logSecurityEvent('CSRF_TOKEN_INVALID', 'Template yönetimi sayfasında geçersiz CSRF token');
}

// Template işlemleri
if ($_POST && empty($error_message)) {
    try {
        // Template aktif etme
        if (isset($_POST['activate_template'])) {
            $template_slug = sanitizeString($_POST['template_slug']);
            if ($templateEngine->setActiveTemplate($template_slug)) {
                $success_message = 'Template başarıyla aktif edildi.';
                logSecurityEvent('TEMPLATE_ACTIVATED', "Template aktif edildi: $template_slug", $_SESSION['user_id'] ?? 0);
            } else {
                $error_message = 'Template aktif edilirken hata oluştu.';
            }
        }
        
        // Template silme
        if (isset($_POST['delete_template'])) {
            $template_slug = sanitizeString($_POST['template_slug']);
            
            // Aktif template silinmesin
            $activeTemplate = $templateEngine->getActiveTemplate();
            if ($activeTemplate && $activeTemplate['slug'] === $template_slug) {
                $error_message = 'Aktif template silinemez. Önce başka bir template aktif edin.';
            } else {
                if ($templateEngine->deleteTemplate($template_slug)) {
                    $success_message = 'Template başarıyla silindi.';
                    logSecurityEvent('TEMPLATE_DELETED', "Template silindi: $template_slug", $_SESSION['user_id'] ?? 0);
                } else {
                    $error_message = 'Template silinirken hata oluştu.';
                }
            }
        }
        
        // Cache temizleme
        if (isset($_POST['clear_cache'])) {
            $templateEngine->clearCache();
            $success_message = 'Template cache başarıyla temizlendi.';
            logSecurityEvent('TEMPLATE_CACHE_CLEARED', 'Template cache temizlendi', $_SESSION['user_id'] ?? 0);
        }
        
    } catch (Exception $e) {
        $error_message = 'Hata: ' . $e->getMessage();
        logSecurityEvent('TEMPLATE_MANAGEMENT_ERROR', $e->getMessage(), $_SESSION['user_id'] ?? 0);
    }
}

// Template listesini al
$templates = $templateEngine->getTumTemalar();
$activeTemplate = $templateEngine->getActiveTemplate();

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .template-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .template-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .template-card.active {
            border-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
        }
        .template-preview {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }
        .template-info {
            padding: 15px;
        }
        .template-actions {
            padding: 15px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
        }
        .badge-active {
            background: #28a745;
        }
        .cache-info {
            background: #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div id="content">
            <?php include 'includes/header.php'; ?>
            
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-paint-brush me-2"></i>Template Yönetimi</h2>
                            <div>
                                <a href="template-upload.php" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Yeni Template Yükle
                                </a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" name="clear_cache" class="btn btn-outline-secondary" 
                                            onclick="return confirm('Template cache temizlensin mi?')">
                                        <i class="fas fa-broom me-2"></i>Cache Temizle
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Cache Bilgisi -->
                        <div class="cache-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong><i class="fas fa-info-circle me-2"></i>Aktif Template:</strong>
                                    <?php if ($activeTemplate): ?>
                                        <span class="badge badge-active"><?php echo htmlspecialchars($activeTemplate['name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Hiçbiri</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong><i class="fas fa-database me-2"></i>Toplam Template:</strong>
                                    <span class="badge bg-info"><?php echo count($templates); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Template Listesi -->
                        <div class="row">
                            <?php if (empty($templates)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                                        <h4>Henüz template yüklenmemiş</h4>
                                        <p>Sisteminizde kullanılabilir template bulunmuyor. Yeni template yüklemek için yukarıdaki butonu kullanın.</p>
                                        <a href="template-upload.php" class="btn btn-primary">
                                            <i class="fas fa-upload me-2"></i>İlk Template'i Yükle
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($templates as $template): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="template-card <?php echo ($activeTemplate && $activeTemplate['slug'] === $template['slug']) ? 'active' : ''; ?>">
                                            <div class="template-preview">
                                                <?php if (!empty($template['preview_image']) && file_exists("../templates/{$template['slug']}/{$template['preview_image']}")): ?>
                                                    <img src="../templates/<?php echo htmlspecialchars($template['slug']); ?>/<?php echo htmlspecialchars($template['preview_image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($template['name']); ?>" 
                                                         class="img-fluid" style="width: 100%; height: 200px; object-fit: cover;">
                                                <?php else: ?>
                                                    <i class="fas fa-paint-brush"></i>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="template-info">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($template['name']); ?></h5>
                                                    <?php if ($activeTemplate && $activeTemplate['slug'] === $template['slug']): ?>
                                                        <span class="badge badge-active">Aktif</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($template['description']); ?></p>
                                                
                                                <div class="row text-center">
                                                    <div class="col-4">
                                                        <small class="text-muted">Versiyon</small><br>
                                                        <strong><?php echo htmlspecialchars($template['version']); ?></strong>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">Yazar</small><br>
                                                        <strong><?php echo htmlspecialchars($template['author']); ?></strong>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">Sayfa</small><br>
                                                        <strong><?php echo isset($template['pages']) ? count($template['pages']) : 0; ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="template-actions">
                                                <div class="btn-group w-100">
                                                    <?php if (!$activeTemplate || $activeTemplate['slug'] !== $template['slug']): ?>
                                                        <form method="post" class="flex-fill">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="template_slug" value="<?php echo htmlspecialchars($template['slug']); ?>">
                                                            <button type="submit" name="activate_template" class="btn btn-success btn-sm w-100">
                                                                <i class="fas fa-check me-1"></i>Aktif Et
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-success btn-sm w-100" disabled>
                                                            <i class="fas fa-check me-1"></i>Aktif Template
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <a href="template-editor.php?template=<?php echo urlencode($template['slug']); ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <a href="../index.php?preview_template=<?php echo urlencode($template['slug']); ?>" 
                                                       target="_blank" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if (!$activeTemplate || $activeTemplate['slug'] !== $template['slug']): ?>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="template_slug" value="<?php echo htmlspecialchars($template['slug']); ?>">
                                                            <button type="submit" name="delete_template" class="btn btn-danger btn-sm"
                                                                    onclick="return confirm('Bu template silinsin mi? Bu işlem geri alınamaz.')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>