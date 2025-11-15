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
require_once getAdminPath('includes/SimpleZipArchive.php');
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

// CSRF token kontrolü
if ($_POST && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $error_message = 'Güvenlik hatası: Geçersiz form token.';
    logSecurityEvent('CSRF_TOKEN_INVALID', 'Template upload sayfasında geçersiz CSRF token');
}

// ZIP Template Upload İşlemi
if ($_POST && empty($error_message)) {
    try {
        if (isset($_POST['upload_zip']) && isset($_FILES['template_zip'])) {
            $uploadedFile = $_FILES['template_zip'];
            
            // Dosya yükleme kontrolü
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Dosya yükleme hatası: ' . $uploadedFile['error']);
            }
            
            // Dosya boyutu kontrolü (50MB max)
            if ($uploadedFile['size'] > 50 * 1024 * 1024) {
                throw new Exception('Dosya boyutu çok büyük. Maksimum 50MB olmalıdır.');
            }
            
            // Dosya türü kontrolü
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $uploadedFile['tmp_name']);
            finfo_close($fileInfo);
            
            if ($mimeType !== 'application/zip') {
                throw new Exception('Sadece ZIP dosyaları yüklenebilir.');
            }
            
            // Geçici dizin oluştur
            $tempDir = sys_get_temp_dir() . '/template_upload_' . uniqid();
            if (!mkdir($tempDir, 0755, true)) {
                throw new Exception('Geçici dizin oluşturulamadı.');
            }
            
            try {
                // ZIP dosyasını aç
                $zip = class_exists("ZipArchive") ? new ZipArchive() : new SimpleZipArchive();
                $result = $zip->open($uploadedFile['tmp_name']);
                
                if ($result !== TRUE) {
                    throw new Exception('ZIP dosyası açılamadı: ' . $result);
                }
                
                // ZIP içeriğini geçici dizine çıkart
                $zip->extractTo($tempDir);
                $zip->close();
                
                // config.json dosyasını bul
                $configFile = null;
                $templateDir = null;
                
                // Ana dizinde config.json var mı kontrol et
                if (file_exists($tempDir . '/config.json')) {
                    $configFile = $tempDir . '/config.json';
                    $templateDir = $tempDir;
                } else {
                    // Alt dizinlerde config.json ara
                    $dirs = glob($tempDir . '/*', GLOB_ONLYDIR);
                    foreach ($dirs as $dir) {
                        if (file_exists($dir . '/config.json')) {
                            $configFile = $dir . '/config.json';
                            $templateDir = $dir;
                            break;
                        }
                    }
                }
                
                if (!$configFile) {
                    throw new Exception('Template config.json dosyası bulunamadı.');
                }
                
                // Config dosyasını oku ve doğrula
                $configContent = file_get_contents($configFile);
                $config = json_decode($configContent, true);
                
                if (!$config) {
                    // JSON hatalarını düzeltmeye çalış
                    $fixedContent = $configContent;
                    $fixedContent = str_replace(["\r\n", "\r"], "\n", $fixedContent);
                    $fixedContent = preg_replace('/,\s*}/', '}', $fixedContent);
                    $fixedContent = preg_replace('/,\s*]/', ']', $fixedContent);
                    
                    $config = json_decode($fixedContent, true);
                    if (!$config) {
                        throw new Exception('config.json dosyası geçersiz JSON formatında.');
                    }
                }
                
                // Gerekli alanları kontrol et
                $requiredFields = ['name', 'slug', 'version', 'author', 'pages'];
                foreach ($requiredFields as $field) {
                    if (!isset($config[$field])) {
                        throw new Exception("config.json dosyasında '$field' alanı eksik.");
                    }
                }
                
                // Template slug'ının benzersiz olduğunu kontrol et
                $existingTemplates = $templateEngine->getAvailableTemplates();
                foreach ($existingTemplates as $existing) {
                    if ($existing['slug'] === $config['slug']) {
                        throw new Exception('Bu template slug\'ı zaten mevcut: ' . $config['slug']);
                    }
                }
                
                // Template dizinini oluştur
                $finalTemplateDir = '../templates/' . $config['slug'];
                if (file_exists($finalTemplateDir)) {
                    throw new Exception('Template dizini zaten mevcut: ' . $config['slug']);
                }
                
                if (!mkdir($finalTemplateDir, 0755, true)) {
                    throw new Exception('Template dizini oluşturulamadı.');
                }
                
                // Dosyaları kopyala
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($templateDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $item) {
                    $target = $finalTemplateDir . '/' . $iterator->getSubPathName();
                    
                    if ($item->isDir()) {
                        if (!mkdir($target, 0755, true)) {
                            throw new Exception('Dizin oluşturulamadı: ' . $target);
                        }
                    } else {
                        if (!copy($item, $target)) {
                            throw new Exception('Dosya kopyalanamadı: ' . $item);
                        }
                    }
                }
                
                // Veritabanına template bilgilerini kaydet
                $result = $templateEngine->registerTemplate($config);
                
                if ($result) {
                    // Template ID'sini al
                    $stmt = $pdo->prepare("SELECT id FROM templates WHERE slug = ?");
                    $stmt->execute([$config['slug']]);
                    $template = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($template) {
                        $templateId = $template['id'];
                        
                        // Template değişkenlerini oluştur
                        $defaultVariables = [
                            'site_title' => $config['name'],
                            'otel_adi' => $config['name'],
                            'otel_aciklama' => $config['description'] ?? '',
                            'site_author' => $config['author'],
                            'logo_url' => '/assets/images/logo.svg',
                            'favicon_url' => '/assets/images/favicon.png',
                            'primary_color' => $config['primary_color'] ?? '#1a1a1a',
                            'secondary_color' => $config['secondary_color'] ?? '#2c2c2c',
                            'accent_color' => $config['accent_color'] ?? '#d4af37',
                            'background_color' => $config['background_color'] ?? '#ffffff',
                            'text_color' => $config['text_color'] ?? '#333333',
                            'font_family' => $config['font_family'] ?? "'Poppins', 'Arial', sans-serif"
                        ];
                        
                        $variableCount = 0;
                        foreach ($defaultVariables as $varName => $varValue) {
                            $stmt = $pdo->prepare("
                                INSERT INTO template_variables (template_id, variable_name, variable_key, variable_value, variable_type, is_required, sort_order, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ");
                            
                            if ($stmt->execute([$templateId, $varName, $varName, $varValue, 'text', 0, $variableCount * 10])) {
                                $variableCount++;
                            }
                        }
                    }
                }
                
                $success_message = 'Template başarıyla yüklendi: ' . $config['name'];
                logSecurityEvent('TEMPLATE_UPLOADED', 'Template yüklendi: ' . $config['slug'], $_SESSION['user_id'] ?? 0);
                
            } finally {
                // Geçici dizini temizle
                if (is_dir($tempDir)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    
                    foreach ($iterator as $item) {
                        if ($item->isDir()) {
                            rmdir($item);
                        } else {
                            unlink($item);
                        }
                    }
                    rmdir($tempDir);
                }
            }
        }
        
    } catch (Exception $e) {
        $error_message = 'Hata: ' . $e->getMessage();
        logSecurityEvent('TEMPLATE_UPLOAD_ERROR', $e->getMessage(), $_SESSION['user_id'] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Yükle - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        .upload-area.dragover {
            border-color: #28a745;
            background-color: #f0fff4;
        }
        .template-structure {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            font-size: 14px;
        }
        .requirements {
            background: #e9ecef;
            border-radius: 5px;
            padding: 15px;
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
                            <h2><i class="fas fa-upload me-2"></i>Template Yükle</h2>
                            <a href="template-yonetimi.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Geri Dön
                            </a>
                        </div>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                <div class="mt-2">
                                    <a href="template-yonetimi.php" class="btn btn-sm btn-outline-success">Template Listesine Git</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-cloud-upload-alt me-2"></i>ZIP Template Yükle</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" enctype="multipart/form-data" id="uploadForm">
                                            <?php echo generateCSRFToken(); ?>
                                            
                                            <div class="upload-area" id="uploadArea">
                                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                                <h4>ZIP Dosyasını Buraya Sürükleyin</h4>
                                                <p class="text-muted">veya dosya seçmek için tıklayın</p>
                                                <input type="file" name="template_zip" id="templateZip" accept=".zip" class="d-none" required>
                                                <div class="mt-3">
                                                    <button type="button" class="btn btn-primary" onclick="document.getElementById('templateZip').click()">
                                                        <i class="fas fa-folder-open me-2"></i>Dosya Seç
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div id="fileInfo" class="mt-3" style="display: none;">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-file-archive me-2"></i>
                                                    <strong>Seçilen Dosya:</strong> <span id="fileName"></span><br>
                                                    <strong>Boyut:</strong> <span id="fileSize"></span>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4">
                                                <button type="submit" name="upload_zip" class="btn btn-success btn-lg" id="uploadBtn" disabled>
                                                    <i class="fas fa-upload me-2"></i>Template Yükle
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-info-circle me-2"></i>Template Yapısı</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="template-structure">
template-name.zip
├── config.json
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── script.js
│   └── images/
│       └── preview.jpg
└── pages/
    ├── index.html
    ├── about.html
    ├── services.html
    ├── contact.html
    └── gallery.html
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Gereksinimler</h6>
                    </div>
                    <div class="card-body">
                        <div class="requirements">
                            <ul class="mb-0">
                                <li><strong>Dosya Formatı:</strong> ZIP (.zip)</li>
                                <li><strong>Maksimum Boyut:</strong> 50MB</li>
                                <li><strong>config.json:</strong> Zorunlu</li>
                                <li><strong>pages/ klasörü:</strong> Zorunlu</li>
                                <li><strong>assets/ klasörü:</strong> Opsiyonel</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="fas fa-code me-2"></i>config.json Örneği</h6>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded"><code>{
  "name": "Modern Hotel",
  "slug": "modern-hotel",
  "version": "1.0.0",
  "author": "Template Author",
  "description": "Modern hotel template",
  "preview_image": "assets/images/preview.jpg",
  "pages": [
    {
      "name": "index",
      "title": "Ana Sayfa",
      "description": "Otel ana sayfası"
    },
    {
      "name": "about",
      "title": "Hakkımızda",
      "description": "Otel hakkında bilgiler"
    }
  ],
  "variables": {
    "primary_color": "#007bff",
    "secondary_color": "#6c757d",
    "font_family": "Arial, sans-serif"
  }
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Drag & Drop functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('templateZip');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const uploadBtn = document.getElementById('uploadBtn');
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        // Handle dropped files
        uploadArea.addEventListener('drop', handleDrop, false);
        
        // Handle file input change
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFiles(this.files);
            }
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlight(e) {
            uploadArea.classList.add('dragover');
        }
        
        function unhighlight(e) {
            uploadArea.classList.remove('dragover');
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                handleFiles(files);
            }
        }
        
        function handleFiles(files) {
            const file = files[0];
            
            if (file.type !== 'application/zip' && !file.name.endsWith('.zip')) {
                alert('Sadece ZIP dosyaları yüklenebilir.');
                return;
            }
            
            if (file.size > 50 * 1024 * 1024) {
                alert('Dosya boyutu 50MB\'dan büyük olamaz.');
                return;
            }
            
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
            uploadBtn.disabled = false;
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Upload form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Yükleniyor...';
            uploadBtn.disabled = true;
        });
    </script>
</body>
</html>