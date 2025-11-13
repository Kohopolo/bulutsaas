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

// Güvenlik kontrolü
if (!startSecureSession()) {
    logSecurityEvent('SESSION_SECURITY_VIOLATION', 'Güvensiz session tespit edildi');
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Template Engine'i başlat
$templateEngine = new TemplateEngine($pdo);

// Template seçimi
$selectedTemplate = $_GET['template'] ?? '';
$selectedPage = $_GET['page'] ?? 'index';
$selectedFile = $_GET['file'] ?? '';

// Mevcut template'leri al
$templates = $templateEngine->getAvailableTemplates();
$currentTemplate = null;

if ($selectedTemplate) {
    foreach ($templates as $template) {
        if ($template['slug'] === $selectedTemplate) {
            $currentTemplate = $template;
            break;
        }
    }
}

// CSRF token kontrolü
if ($_POST && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $error_message = 'Güvenlik hatası: Geçersiz form token.';
    logSecurityEvent('CSRF_TOKEN_INVALID', 'Template editor sayfasında geçersiz CSRF token');
}

// Dosya kaydetme işlemi
if ($_POST && empty($error_message)) {
    try {
        if (isset($_POST['save_file']) && $currentTemplate) {
            $fileContent = $_POST['file_content'] ?? '';
            $filePath = sanitizeString($_POST['file_path']);
            
            // Güvenlik kontrolü - sadece template dizini içindeki dosyalar
            $templateDir = realpath('../templates/' . $currentTemplate['slug']);
            $fullFilePath = realpath($templateDir . '/' . $filePath);
            
            if (!$fullFilePath || strpos($fullFilePath, $templateDir) !== 0) {
                throw new Exception('Geçersiz dosya yolu.');
            }
            
            // Dosyayı kaydet
            if (file_put_contents($fullFilePath, $fileContent) !== false) {
                // Cache'i temizle
                $templateEngine->clearCache();
                $success_message = 'Dosya başarıyla kaydedildi.';
                logSecurityEvent('TEMPLATE_FILE_SAVED', "Dosya kaydedildi: $filePath", $_SESSION['user_id'] ?? 0);
            } else {
                throw new Exception('Dosya kaydedilemedi.');
            }
        }
        
        // Template değişkenlerini kaydetme
        if (isset($_POST['save_variables']) && $currentTemplate) {
            $variables = [];
            
            // POST verilerinden değişkenleri al
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'var_') === 0) {
                    $varName = substr($key, 4);
                    $variables[$varName] = sanitizeString($value);
                }
            }
            
            // Değişkenleri kaydet
            $templateEngine->setTemplateVariables($currentTemplate['slug'], $variables);
            $templateEngine->clearCache();
            
            $success_message = 'Template değişkenleri başarıyla kaydedildi.';
            logSecurityEvent('TEMPLATE_VARIABLES_SAVED', "Template değişkenleri kaydedildi: {$currentTemplate['slug']}", $_SESSION['user_id'] ?? 0);
        }
        
    } catch (Exception $e) {
        $error_message = 'Hata: ' . $e->getMessage();
        logSecurityEvent('TEMPLATE_EDITOR_ERROR', $e->getMessage(), $_SESSION['user_id'] ?? 0);
    }
}

// Dosya listesini al
function getTemplateFiles($templateSlug) {
    $templateDir = '../templates/' . $templateSlug;
    $files = [];
    
    if (is_dir($templateDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($templateDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($templateDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                $files[] = $relativePath;
            }
        }
    }
    
    sort($files);
    return $files;
}

// Dosya içeriğini al
function getFileContent($templateSlug, $filePath) {
    $fullPath = '../templates/' . $templateSlug . '/' . $filePath;
    
    if (file_exists($fullPath) && is_file($fullPath)) {
        return file_get_contents($fullPath);
    }
    
    return '';
}

// Dosya türünü belirle
function getFileType($filePath) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'html':
        case 'htm':
            return 'html';
        case 'css':
            return 'css';
        case 'js':
            return 'javascript';
        case 'json':
            return 'json';
        case 'php':
            return 'php';
        default:
            return 'text';
    }
}

$templateFiles = $currentTemplate ? getTemplateFiles($currentTemplate['slug']) : [];
$currentFileContent = '';
$currentFileType = 'text';

if ($selectedFile && $currentTemplate) {
    $currentFileContent = getFileContent($currentTemplate['slug'], $selectedFile);
    $currentFileType = getFileType($selectedFile);
}

// Template değişkenlerini al
$templateVariables = [];
if ($currentTemplate) {
    // Database'den template ID'yi al
    $stmt = $pdo->prepare("SELECT id FROM templates WHERE slug = ?");
    $stmt->execute([$currentTemplate['slug']]);
    $templateData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($templateData) {
        $dbVariables = $templateEngine->getTemplateVariables($templateData['id']);
        // Array'i key-value formatına çevir
        foreach ($dbVariables as $var) {
            $templateVariables[$var['variable_key']] = $var['variable_value'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Editör - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .editor-container {
            height: calc(100vh - 200px);
            min-height: 500px;
        }
        .file-tree {
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
            height: 100%;
            overflow-y: auto;
        }
        .file-item {
            padding: 8px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .file-item:hover {
            background-color: #e9ecef;
        }
        .file-item.active {
            background-color: #007bff;
            color: white;
        }
        .file-icon {
            width: 16px;
            margin-right: 8px;
        }
        .CodeMirror {
            height: 100%;
            font-size: 14px;
        }
        .preview-frame {
            width: 100%;
            height: 100%;
            border: none;
        }
        .variable-input {
            margin-bottom: 10px;
        }
        .tabs-container {
            border-bottom: 1px solid #dee2e6;
        }
        .nav-tabs .nav-link {
            border-bottom: 2px solid transparent;
        }
        .nav-tabs .nav-link.active {
            border-bottom-color: #007bff;
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
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2><i class="fas fa-edit me-2"></i>Template Editör</h2>
                            <div>
                                <a href="template-yonetimi.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Geri Dön
                                </a>
                                <?php if ($currentTemplate): ?>
                                    <a href="../index.php?preview_template=<?php echo urlencode($currentTemplate['slug']); ?>" 
                                       target="_blank" class="btn btn-info">
                                        <i class="fas fa-eye me-2"></i>Önizleme
                                    </a>
                                <?php endif; ?>
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
                        
                        <!-- Template Seçimi -->
                        <?php if (!$currentTemplate): ?>
                            <div class="card">
                                <div class="card-body text-center">
                                    <h4>Template Seçin</h4>
                                    <div class="row justify-content-center">
                                        <?php foreach ($templates as $template): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h5><?php echo htmlspecialchars($template['name']); ?></h5>
                                                        <p class="text-muted"><?php echo htmlspecialchars($template['description']); ?></p>
                                                        <a href="?template=<?php echo urlencode($template['slug']); ?>" class="btn btn-primary">
                                                            <i class="fas fa-edit me-2"></i>Düzenle
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Editör Arayüzü -->
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5><i class="fas fa-paint-brush me-2"></i><?php echo htmlspecialchars($currentTemplate['name']); ?></h5>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="saveCurrentFile()">
                                                <i class="fas fa-save me-1"></i>Kaydet
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="refreshPreview()">
                                                <i class="fas fa-sync me-1"></i>Yenile
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <!-- Tabs -->
                                    <div class="tabs-container">
                                        <ul class="nav nav-tabs" id="editorTabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="files-tab" data-bs-toggle="tab" data-bs-target="#files" type="button" role="tab">
                                                    <i class="fas fa-file-code me-2"></i>Dosyalar
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="variables-tab" data-bs-toggle="tab" data-bs-target="#variables" type="button" role="tab">
                                                    <i class="fas fa-cogs me-2"></i>Değişkenler
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview" type="button" role="tab">
                                                    <i class="fas fa-eye me-2"></i>Önizleme
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <div class="tab-content" id="editorTabContent">
                                        <!-- Dosyalar Tab -->
                                        <div class="tab-pane fade show active" id="files" role="tabpanel">
                                            <div class="row editor-container">
                                                <div class="col-md-3 file-tree">
                                                    <div class="p-3">
                                                        <h6><i class="fas fa-folder me-2"></i>Dosyalar</h6>
                                                        <?php foreach ($templateFiles as $file): ?>
                                                            <div class="file-item <?php echo ($selectedFile === $file) ? 'active' : ''; ?>" 
                                                                 onclick="selectFile('<?php echo htmlspecialchars($file); ?>')">
                                                                <i class="fas fa-file file-icon"></i>
                                                                <?php echo htmlspecialchars($file); ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-9">
                                                    <?php if ($selectedFile): ?>
                                                        <form method="post" id="fileForm">
                                                            <?php echo generateCSRFTokenInput(); ?>
                                                            <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($selectedFile); ?>">
                                                            <textarea name="file_content" id="codeEditor"><?php echo htmlspecialchars($currentFileContent); ?></textarea>
                                                            <div class="p-3 border-top">
                                                                <button type="submit" name="save_file" class="btn btn-success">
                                                                    <i class="fas fa-save me-2"></i>Dosyayı Kaydet
                                                                </button>
                                                                <span class="text-muted ms-3">
                                                                    <i class="fas fa-info-circle me-1"></i>
                                                                    Dosya: <?php echo htmlspecialchars($selectedFile); ?>
                                                                </span>
                                                            </div>
                                                        </form>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center h-100">
                                                            <div class="text-center text-muted">
                                                                <i class="fas fa-file-code fa-3x mb-3"></i>
                                                                <h5>Düzenlemek için bir dosya seçin</h5>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Değişkenler Tab -->
                                        <div class="tab-pane fade" id="variables" role="tabpanel">
                                            <div class="p-4">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div>
                                                        <h5 class="mb-1"><i class="fas fa-cogs me-2"></i>Template Değişkenleri</h5>
                                                        <p class="text-muted mb-0">Bu değişkenler template'te {{variable_name}} şeklinde kullanılabilir.</p>
                                                    </div>
                                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#variableHelpModal">
                                                        <i class="fas fa-question-circle me-1"></i>Nasıl Kullanılır?
                                                    </button>
                                                </div>
                                                
                                                <?php if (isset($_GET['debug'])): ?>
                                                <div class="alert alert-warning">
                                                    <strong>Debug Info:</strong><br>
                                                    Template: <?php echo $currentTemplate['slug'] ?? 'N/A'; ?><br>
                                                    Variables isset: <?php echo isset($currentTemplate['variables']) ? 'YES' : 'NO'; ?><br>
                                                    Variables empty: <?php echo empty($currentTemplate['variables']) ? 'YES' : 'NO'; ?><br>
                                                    Variables count: <?php echo isset($currentTemplate['variables']) ? count($currentTemplate['variables']) : '0'; ?><br>
                                                    <?php if (isset($currentTemplate['variables']) && !empty($currentTemplate['variables'])): ?>
                                                        Variables keys: <?php echo implode(', ', array_keys($currentTemplate['variables'])); ?><br>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <form method="post" id="variablesForm">
                                                    <?php echo generateCSRFTokenInput(); ?>
                                                    
                                                    <div class="row">
                                                        <?php if (!empty($currentTemplate['variables'])): ?>
                                                            <?php foreach ($currentTemplate['variables'] as $varName => $varConfig): ?>
                                                                <?php 
                                                                    $defaultValue = is_array($varConfig) ? ($varConfig['default'] ?? '') : $varConfig;
                                                                    $varLabel = is_array($varConfig) ? ($varConfig['label'] ?? $varName) : $varName;
                                                                    $varType = is_array($varConfig) ? ($varConfig['type'] ?? 'text') : 'text';
                                                                    $currentValue = $templateVariables[$varName] ?? $defaultValue;
                                                                ?>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">
                                                                        <?php echo htmlspecialchars($varLabel); ?>
                                                                    </label>
                                                                    
                                                                    <?php if ($varType === 'color'): ?>
                                                                        <div class="input-group">
                                                                            <input type="color" class="form-control form-control-color" 
                                                                                   name="var_<?php echo htmlspecialchars($varName); ?>" 
                                                                                   value="<?php echo htmlspecialchars($currentValue); ?>">
                                                                            <input type="text" class="form-control" 
                                                                                   value="<?php echo htmlspecialchars($currentValue); ?>" 
                                                                                   readonly>
                                                                        </div>
                                                                    <?php elseif ($varType === 'select' && isset($varConfig['options'])): ?>
                                                                        <select class="form-select" name="var_<?php echo htmlspecialchars($varName); ?>">
                                                                            <?php foreach ($varConfig['options'] as $option): ?>
                                                                                <option value="<?php echo htmlspecialchars($option); ?>" 
                                                                                        <?php echo $currentValue === $option ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($option); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    <?php elseif ($varType === 'image'): ?>
                                                                        <div class="input-group">
                                                                            <input type="text" class="form-control" 
                                                                                   name="var_<?php echo htmlspecialchars($varName); ?>" 
                                                                                   value="<?php echo htmlspecialchars($currentValue); ?>"
                                                                                   placeholder="<?php echo htmlspecialchars($defaultValue); ?>">
                                                                            <button type="button" class="btn btn-outline-secondary">
                                                                                <i class="fas fa-image"></i>
                                                                            </button>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <input type="text" class="form-control" 
                                                                               name="var_<?php echo htmlspecialchars($varName); ?>" 
                                                                               value="<?php echo htmlspecialchars($currentValue); ?>"
                                                                               placeholder="<?php echo htmlspecialchars($defaultValue); ?>">
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <div class="col-12">
                                                                <div class="alert alert-info">
                                                                    <i class="fas fa-info-circle me-2"></i>
                                                                    Bu template için tanımlanmış değişken bulunmuyor.
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (!empty($currentTemplate['variables'])): ?>
                                                        <div class="mt-4">
                                                            <button type="submit" name="save_variables" class="btn btn-primary">
                                                                <i class="fas fa-save me-2"></i>Değişkenleri Kaydet
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <!-- Önizleme Tab -->
                                        <div class="tab-pane fade" id="preview" role="tabpanel">
                                            <div class="editor-container">
                                                <iframe src="../index.php?preview_template=<?php echo urlencode($currentTemplate['slug']); ?>" 
                                                        class="preview-frame" id="previewFrame"></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Variable Help Modal -->
    <div class="modal fade" id="variableHelpModal" tabindex="-1" aria-labelledby="variableHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="variableHelpModalLabel">
                        <i class="fas fa-question-circle me-2"></i>Template Değişkenlerini Nasıl Kullanırım?
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-primary"><i class="fas fa-code me-2"></i>Temel Kullanım</h6>
                    <p>Template HTML dosyalarında değişkenleri kullanmak için <code>{{variable_name}}</code> formatını kullanın.</p>
                    
                    <div class="alert alert-info">
                        <strong>Format:</strong> <code>{{'{{'}}variable_name}}</code> (çift süslü parantez, boşluk yok)
                    </div>
                    
                    <hr>
                    
                    <h6 class="text-primary"><i class="fas fa-lightbulb me-2"></i>Örnekler</h6>
                    
                    <div class="mb-3">
                        <strong>1. Sayfa Başlığında Otel Adı:</strong>
                        <pre class="bg-light p-2 rounded"><code>&lt;title&gt;{{'{{'}}otel_adi}} - {{'{{'}}site_title}}&lt;/title&gt;</code></pre>
                        <small class="text-muted">Sonuç: <code>&lt;title&gt;Grand Otel İstanbul - Premium Konaklama&lt;/title&gt;</code></small>
                    </div>
                    
                    <div class="mb-3">
                        <strong>2. Logo Gösterme (img src):</strong>
                        <pre class="bg-light p-2 rounded"><code>&lt;img src="{{'{{'}}logo_url}}" alt="{{'{{'}}otel_adi}}"&gt;</code></pre>
                        <small class="text-muted">Sonuç: <code>&lt;img src="/assets/images/logo.svg" alt="Grand Otel İstanbul"&gt;</code></small>
                    </div>
                    
                    <div class="mb-3">
                        <strong>3. Resim Gösterme (img src):</strong>
                        <pre class="bg-light p-2 rounded"><code>&lt;img src="{{'{{'}}hero_image}}" alt="{{'{{'}}otel_adi}}" class="img-fluid"&gt;</code></pre>
                        <small class="text-muted">Sonuç: <code>&lt;img src="/assets/images/hero-bg.jpg" alt="Grand Otel İstanbul"&gt;</code></small>
                    </div>
                    
                    <div class="mb-3">
                        <strong>3.1. Lazy Loading (data-src):</strong>
                        <pre class="bg-light p-2 rounded"><code>&lt;img data-src="{{'{{'}}hero_image}}" alt="{{'{{'}}otel_adi}}" class="lazy"&gt;
&lt;img src="placeholder.jpg" data-src="{{'{{'}}hero_image}}" class="lazy"&gt;</code></pre>
                        <small class="text-muted">Sonuç: <code>&lt;img data-src="/assets/images/hero-bg.jpg" alt="Grand Otel İstanbul"&gt;</code></small>
                        <br><small class="text-info"><i class="fas fa-info-circle"></i> Lazy loading kütüphaneleri için (LazyLoad.js, Lozad.js vb.)</small>
                    </div>
                    
                    <div class="mb-3">
                        <strong>4. Başlıkta Otel Adı:</strong>
                        <pre class="bg-light p-2 rounded"><code>&lt;h1&gt;{{'{{'}}otel_adi}}'na Hoş Geldiniz&lt;/h1&gt;</code></pre>
                        <small class="text-muted">Sonuç: <code>&lt;h1&gt;Grand Otel İstanbul'a Hoş Geldiniz&lt;/h1&gt;</code></small>
                    </div>
                    
                    <div class="mb-3">
                        <strong>5. CSS'de Renk Kullanma:</strong>
                        <pre class="bg-light p-2 rounded"><code>&lt;style&gt;
    .btn-primary {
        background-color: {{'{{'}}primary_color}};
    }
&lt;/style&gt;</code></pre>
                        <small class="text-muted">Sonuç: <code>background-color: #c9a96e;</code></small>
                    </div>
                    
                    <div class="mb-3">
                        <strong>6. Background Image (CSS):</strong>
                        <pre class="bg-light p-2 rounded"><code>&lt;section style="background-image: url('{{'{{'}}hero_image}}');"&gt;
    &lt;h1&gt;{{'{{'}}otel_adi}}&lt;/h1&gt;
    &lt;p&gt;{{'{{'}}otel_aciklama}}&lt;/p&gt;
&lt;/section&gt;</code></pre>
                        <small class="text-muted">Not: Background için <code>url()</code> kullanın, img src için doğrudan değişken adı</small>
                    </div>
                    
                    <hr>
                    
                    <h6 class="text-primary"><i class="fas fa-list me-2"></i>Kullanılabilir Değişkenler</h6>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Değişken</th>
                                    <th>Açıklama</th>
                                    <th>Örnek</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>{{'{{'}}otel_adi}}</code></td>
                                    <td>Otel adı</td>
                                    <td>Grand Otel İstanbul</td>
                                </tr>
                                <tr>
                                    <td><code>{{'{{'}}site_title}}</code></td>
                                    <td>Site başlığı</td>
                                    <td>Premium Konaklama</td>
                                </tr>
                                <tr>
                                    <td><code>{{'{{'}}otel_aciklama}}</code></td>
                                    <td>Otel açıklaması</td>
                                    <td>İstanbul'un kalbinde lüks...</td>
                                </tr>
                                <tr>
                                    <td><code>{{'{{'}}logo_url}}</code></td>
                                    <td>Logo dosya yolu</td>
                                    <td>/assets/images/logo.svg</td>
                                </tr>
                                <tr>
                                    <td><code>{{'{{'}}primary_color}}</code></td>
                                    <td>Ana renk</td>
                                    <td>#c9a96e</td>
                                </tr>
                                <tr>
                                    <td><code>{{'{{'}}font_family}}</code></td>
                                    <td>Ana font</td>
                                    <td>Poppins, sans-serif</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <hr>
                    
                    <h6 class="text-danger"><i class="fas fa-times-circle me-2"></i>Yaygın Hatalar</h6>
                    
                    <div class="alert alert-danger mb-2">
                        <strong>❌ YANLIŞ:</strong> <code>{otel_adi}</code> (tek süslü parantez)<br>
                        <strong>✅ DOĞRU:</strong> <code>{{'{{'}}otel_adi}}</code> (çift süslü parantez)
                    </div>
                    
                    <div class="alert alert-danger mb-2">
                        <strong>❌ YANLIŞ:</strong> <code>{{{{ otel_adi }}</code> (boşluk var)<br>
                        <strong>✅ DOĞRU:</strong> <code>{{'{{'}}otel_adi}}</code> (boşluk yok)
                    </div>
                    
                    <div class="alert alert-danger mb-2">
                        <strong>❌ YANLIŞ:</strong> <code>&lt;?php echo $otel_adi; ?&gt;</code> (PHP syntax)<br>
                        <strong>✅ DOĞRU:</strong> <code>{{'{{'}}otel_adi}}</code> (template syntax)
                    </div>
                    
                    <hr>
                    
                    <h6 class="text-success"><i class="fas fa-check-circle me-2"></i>İpuçları</h6>
                    <ul>
                        <li>Değişkenler her yerde kullanılabilir: HTML, CSS, attributes, meta tags</li>
                        <li>Değişken adları küçük harf ve alt çizgi ile yazılır: <code>otel_adi</code></li>
                        <li>Template Editor'den değerleri değiştirdiğinizde tüm sayfalara yansır</li>
                        <li>Değişken tanımları <code>config.json</code> dosyasındadır</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <a href="../docs/template-variables-guide.md" target="_blank" class="btn btn-primary">
                        <i class="fas fa-book me-1"></i>Detaylı Rehber
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        let codeEditor;
        
        <?php if ($selectedFile): ?>
        // CodeMirror editörünü başlat
        codeEditor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
            lineNumbers: true,
            mode: '<?php echo $currentFileType; ?>',
            theme: 'monokai',
            indentUnit: 2,
            tabSize: 2,
            lineWrapping: true,
            autoCloseBrackets: true,
            matchBrackets: true
        });
        <?php endif; ?>
        
        function selectFile(fileName) {
            window.location.href = '?template=<?php echo urlencode($selectedTemplate); ?>&file=' + encodeURIComponent(fileName);
        }
        
        function saveCurrentFile() {
            if (codeEditor) {
                document.querySelector('textarea[name="file_content"]').value = codeEditor.getValue();
                document.getElementById('fileForm').submit();
            }
        }
        
        function refreshPreview() {
            const previewFrame = document.getElementById('previewFrame');
            if (previewFrame) {
                previewFrame.src = previewFrame.src;
            }
        }
        
        // Klavye kısayolları
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveCurrentFile();
            }
        });
        
        // Tab değişikliklerinde önizlemeyi yenile
        const previewTab = document.getElementById('preview-tab');
        if (previewTab) {
            previewTab.addEventListener('shown.bs.tab', function() {
                refreshPreview();
            });
        }
        
        // Color input'ları senkronize et
        document.querySelectorAll('input[type="color"]').forEach(function(colorInput) {
            colorInput.addEventListener('input', function() {
                const textInput = this.nextElementSibling;
                if (textInput && textInput.type === 'text') {
                    textInput.value = this.value;
                }
            });
        });
    </script>
</body>
</html>