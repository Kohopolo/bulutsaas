<?php
/**
 * Ultimate AI Page Builder v3
 * Düzeltilmiş versiyon
 */

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/detailed_permission_functions.php';
require_once '../config/database.php';

// Yetki kontrolü
if (!hasDetailedPermission('page_builder_view')) {
    die('Yetkiniz yok!');
}

// Sayfa ID kontrolü
$pageId = $_GET['page_id'] ?? null;
$pageData = null;

if ($pageId) {
    $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE id = ?");
    $stmt->execute([$pageId]);
    $pageData = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = $pageData ? htmlspecialchars($pageData['page_title']) : '';

// Veritabanından template'leri çek
$templates = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT page_template FROM custom_pages WHERE page_template IS NOT NULL AND page_template != ''");
    $dbTemplates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Mevcut template'leri ekle
    $templates = array_unique(array_merge($dbTemplates, ['premium-hotel', 'business', 'portfolio', 'ecommerce', 'custom']));
} catch (Exception $e) {
    // Hata durumunda varsayılan template'ler
    $templates = ['premium-hotel', 'business', 'portfolio', 'ecommerce', 'custom'];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Page Builder v3</title>
    
    <!-- GrapesJS Core -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.7/dist/css/grapes.min.css">
    
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        body, html { margin: 0; height: 100%; overflow: hidden; }
        #gjs { height: calc(100vh - 60px); }
        .navbar-ultimate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .ai-float-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            z-index: 9999;
            cursor: pointer;
            transition: all 0.3s;
        }
        .ai-float-btn:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6); }
        .toast-container { position: fixed; top: 80px; right: 20px; z-index: 10001; }
        .toast { min-width: 300px; }
        
        /* Dropdown z-index fix */
        .dropdown-menu { z-index: 9999 !important; }
        .navbar .dropdown-menu { z-index: 9999 !important; }
        
        /* Modal z-index fix */
        .modal { z-index: 10000 !important; }
        .modal-backdrop { z-index: 9999 !important; }
        
        /* Code Editor Styles */
        .code-editor {
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            background: #2d2d2d;
        }
        
        .CodeMirror {
            height: 500px; /* Yüksekliği artır */
            font-family: 'Fira Code', 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 13px; /* Font boyutunu küçült */
            line-height: 1.6; /* Satır aralığını artır */
            background: #2d2d2d;
            color: #f8f8f2;
            word-wrap: break-word; /* Uzun kelimeleri kır */
            white-space: pre-wrap; /* Satır kaydırmayı koru */
        }
        
        .CodeMirror-focused .CodeMirror-cursor {
            border-left: 2px solid #f8f8f2;
            width: 2px;
        }
        
        .CodeMirror-gutters {
            background: #1e1e1e;
            border-right: 1px solid #444;
            width: 50px;
        }
        
        .CodeMirror-linenumber {
            color: #666;
            font-size: 12px;
            padding: 0 8px;
            text-align: right;
        }
        
        .CodeMirror-activeline-background {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .CodeMirror-activeline .CodeMirror-linenumber {
            color: #f8f8f2;
            font-weight: bold;
        }
        
        /* Syntax highlighting improvements */
        .CodeMirror .cm-tag { color: #f92672; }
        .CodeMirror .cm-attribute { color: #a6e22e; }
        .CodeMirror .cm-string { color: #e6db74; }
        .CodeMirror .cm-comment { color: #75715e; font-style: italic; }
        .CodeMirror .cm-keyword { color: #66d9ef; }
        .CodeMirror .cm-property { color: #a6e22e; }
        .CodeMirror .cm-variable { color: #f8f8f2; }
        .CodeMirror .cm-number { color: #ae81ff; }
        
        /* Selection */
        .CodeMirror-selected {
            background: rgba(255, 255, 255, 0.1) !important;
        }
        
        /* Line wrapping indicators */
        .CodeMirror-line {
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        
        /* Wrapped line indicator */
        .CodeMirror-line-wrapped {
            padding-left: 20px;
            border-left: 2px solid #444;
            margin-left: 10px;
        }
        
        /* Line wrapping visual indicator */
        .CodeMirror-line:not(:first-child) {
            position: relative;
        }
        
        .CodeMirror-line:not(:first-child):before {
            content: '↳';
            position: absolute;
            left: -15px;
            color: #666;
            font-size: 12px;
        }
        
        /* Better line wrapping visual */
        .CodeMirror pre {
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        
        /* Scrollbar */
        .CodeMirror-scrollbar-filler {
            background: #2d2d2d;
        }
        
        /* Full screen mode */
        .CodeMirror-fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            height: 100vh;
            z-index: 10001;
            background: #2d2d2d;
        }
        
        /* Modal improvements */
        .modal-xl {
            max-width: 95%;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        /* Tab buttons */
        .btn-group .btn {
            border-radius: 0;
        }
        
        .btn-group .btn:first-child {
            border-top-left-radius: 0.375rem;
            border-bottom-left-radius: 0.375rem;
        }
        
        .btn-group .btn:last-child {
            border-top-right-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }
    </style>
</head>
<body>
    <!-- Professional Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); box-shadow: 0 8px 32px rgba(0,0,0,0.15); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.1); z-index: 9998; position: relative;">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#" style="font-size: 1.4rem;">
                <div class="me-2" style="width: 40px; height: 40px; background: linear-gradient(45deg, #ff6b6b, #4ecdc4); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-magic text-white"></i>
                </div>
                <span>Ultimate Page Builder</span>
                <span class="badge bg-success ms-2" style="font-size: 0.7rem;">PRO</span>
            </a>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Page Title Input -->
                <div class="input-group" style="width: 250px;">
                    <span class="input-group-text bg-white border-0">
                        <i class="fas fa-edit text-primary"></i>
                    </span>
                    <input type="text" class="form-control border-0 shadow-sm" id="pageTitle" placeholder="Sayfa Başlığı" value="<?php echo $pageTitle; ?>" style="background: rgba(255,255,255,0.95);">
                </div>
                
                <!-- Template Selector -->
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" style="border-radius: 25px; padding: 8px 16px;">
                        <i class="fas fa-palette me-2"></i>Şablon
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
                        <li><h6 class="dropdown-header">Premium Şablonlar</h6></li>
                        <li><a class="dropdown-item" href="#" onclick="selectTemplate('premium-hotel')">
                            <i class="fas fa-hotel me-2 text-primary"></i>Premium Hotel
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="selectTemplate('business')">
                            <i class="fas fa-briefcase me-2 text-success"></i>Business
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="selectTemplate('portfolio')">
                            <i class="fas fa-portrait me-2 text-info"></i>Portfolio
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="selectTemplate('ecommerce')">
                            <i class="fas fa-shopping-cart me-2 text-warning"></i>E-commerce
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="selectTemplate('custom')">
                            <i class="fas fa-code me-2 text-secondary"></i>Özel Şablon
                        </a></li>
                    </ul>
                </div>
                
                <!-- Template Select Dropdown -->
                <select id="pageTemplate" class="form-select" style="border-radius: 25px; padding: 8px 16px; background: rgba(255,255,255,0.95); border: 1px solid rgba(255,255,255,0.3); color: #333; min-width: 150px;">
                    <option value="">Template Seç</option>
                    <?php foreach ($templates as $template): ?>
                        <?php 
                        $templateNames = [
                            'premium-hotel' => 'Premium Hotel',
                            'business' => 'Business',
                            'portfolio' => 'Portfolio', 
                            'ecommerce' => 'E-commerce',
                            'custom' => 'Özel Sayfa'
                        ];
                        $templateName = $templateNames[$template] ?? ucfirst(str_replace('-', ' ', $template));
                        ?>
                        <option value="<?php echo $template; ?>" <?php echo ($pageData && $pageData['page_template'] === $template) ? 'selected' : ''; ?>>
                            <?php echo $templateName; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Action Buttons -->
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-light" onclick="showTemplateLibrary()" title="Şablon Galerisi">
                        <i class="fas fa-layer-group"></i>
                    </button>
                    <button class="btn btn-outline-light" onclick="showVariableManager()" title="Dinamik Değişkenler">
                        <i class="fas fa-cogs"></i>
                    </button>
                    <button class="btn btn-outline-light" onclick="showImageManager()" title="Resim Yöneticisi">
                        <i class="fas fa-images"></i>
                    </button>
                    <button class="btn btn-outline-light" onclick="showMenuSettings()" title="Menü Ayarları">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <!-- Preview & Save Buttons -->
                <div class="btn-group" role="group">
                    <button class="btn btn-info" onclick="preview()" style="border-radius: 25px 0 0 25px;">
                        <i class="fas fa-eye me-1"></i>Önizle
                    </button>
                    <button class="btn btn-warning" onclick="save(false)" style="border-radius: 0;">
                        <i class="fas fa-save me-1"></i>Taslak
                    </button>
                    <button class="btn btn-success" onclick="save(true)" id="publishBtn" style="border-radius: 0 25px 25px 0;">
                        <i class="fas fa-rocket me-1"></i>Yayınla
                    </button>
                </div>
                
                <a href="page-list.php" class="btn btn-secondary" style="border-radius: 25px;">
                    <i class="fas fa-arrow-left me-1"></i>Geri
                </a>
            </div>
        </div>
    </nav>

    <!-- Accordion Navigation -->
    <div class="accordion mb-3" id="pageBuilderAccordion">
        <!-- İçerik Düzenleme -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="content-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#content-panel" aria-expanded="true" aria-controls="content-panel">
                    <i class="fas fa-edit me-2"></i>İçerik Düzenle
                </button>
            </h2>
            <div id="content-panel" class="accordion-collapse collapse show" aria-labelledby="content-header" data-bs-parent="#pageBuilderAccordion">
                <div class="accordion-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>İçerik Düzenleme:</strong> Sayfa içeriğini drag & drop ile düzenleyin. Dinamik değişkenler kullanabilirsiniz.
                    </div>
                    <div class="row">
                        <div class="col-md-9">
                            <!-- GrapesJS Editor -->
                            <div id="gjs"></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Element Özellikleri</h6>
                                </div>
                                <div class="card-body p-2">
                                    <div class="traits-container"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Layout Düzenleme -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="layout-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#layout-panel" aria-expanded="false" aria-controls="layout-panel">
                    <i class="fas fa-th-large me-2"></i>Layout Düzenle
                </button>
            </h2>
            <div id="layout-panel" class="accordion-collapse collapse" aria-labelledby="layout-header" data-bs-parent="#pageBuilderAccordion">
                <div class="accordion-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Layout Düzenleme:</strong> Header ve Footer'ı düzenleyin. Dikkatli olun, tüm sayfaları etkiler!
                    </div>
                    <div class="row">
                        <!-- Header Düzenleme -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-header me-2"></i>Header Düzenle</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div id="header-editor" style="height: 400px; border: 1px solid #ddd; border-radius: 4px;"></div>
                                        </div>
                                        <div class="col-3">
                                            <div class="header-blocks-container" style="height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f8f9fa;">
                                                <h6 class="mb-2">Header Blokları</h6>
                                                <div class="header-layers-container mb-3"></div>
                                                <div class="header-traits-container mb-3"></div>
                                                <div class="header-selector-container"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-primary btn-sm" onclick="saveHeader()">
                                            <i class="fas fa-save me-1"></i>Header Kaydet
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="resetHeader()">
                                            <i class="fas fa-undo me-1"></i>Sıfırla
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer Düzenleme -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-footer me-2"></i>Footer Düzenle</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div id="footer-editor" style="height: 400px; border: 1px solid #ddd; border-radius: 4px;"></div>
                                        </div>
                                        <div class="col-3">
                                            <div class="footer-blocks-container" style="height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f8f9fa;">
                                                <h6 class="mb-2">Footer Blokları</h6>
                                                <div class="footer-layers-container mb-3"></div>
                                                <div class="footer-traits-container mb-3"></div>
                                                <div class="footer-selector-container"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-primary btn-sm" onclick="saveFooter()">
                                            <i class="fas fa-save me-1"></i>Footer Kaydet
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="resetFooter()">
                                            <i class="fas fa-undo me-1"></i>Sıfırla
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Template Ayarları -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="template-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#template-panel" aria-expanded="false" aria-controls="template-panel">
                    <i class="fas fa-cog me-2"></i>Template Ayarları
                </button>
            </h2>
            <div id="template-panel" class="accordion-collapse collapse" aria-labelledby="template-header" data-bs-parent="#pageBuilderAccordion">
                <div class="accordion-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Template Ayarları:</strong> Global ayarları düzenleyin. Tüm sayfaları etkiler.
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>Otel Bilgileri</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Otel Adı</label>
                                        <input type="text" class="form-control" id="otelAdi" value="Premium Hotel">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Logo URL</label>
                                        <input type="text" class="form-control" id="logoUrl" value="assets/images/logo.svg">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Adres</label>
                                        <textarea class="form-control" id="otelAdres" rows="2">123 Ocean Drive, Breezie Island</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Telefon</label>
                                        <input type="text" class="form-control" id="otelTelefon" value="(123) 456-7890">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-share-alt me-2"></i>Sosyal Medya</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Facebook URL</label>
                                        <input type="url" class="form-control" id="facebookUrl" value="#">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Twitter URL</label>
                                        <input type="url" class="form-control" id="twitterUrl" value="#">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Instagram URL</label>
                                        <input type="url" class="form-control" id="instagramUrl" value="#">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Pinterest URL</label>
                                        <input type="url" class="form-control" id="pinterestUrl" value="#">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">LinkedIn URL</label>
                                        <input type="url" class="form-control" id="linkedinUrl" value="#">
                                    </div>
                                    <button class="btn btn-success w-100" onclick="saveTemplateSettings()">
                                        <i class="fas fa-save me-2"></i>Template Ayarlarını Kaydet
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Float Button -->
    <button class="ai-float-btn" onclick="toggleAI()" title="AI Asistanı">
        <i class="fas fa-robot fa-2x"></i>
    </button>

    <!-- AI Panel (Offcanvas) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="aiPanel">
        <div class="offcanvas-header bg-primary text-white">
            <h5 class="offcanvas-title"><i class="fas fa-robot me-2"></i>AI Asistanı</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="mb-3">
                <label class="form-label">Ne oluşturmak istersiniz?</label>
                <textarea class="form-control" id="aiPrompt" rows="4" 
                    placeholder="Örnek: Modern bir hero section oluştur, mavi tonlarda..."></textarea>
            </div>
            <button class="btn btn-primary w-100" onclick="generateWithAI()">
                <i class="fas fa-magic me-2"></i>AI ile Oluştur
            </button>
            <div id="aiResponse" class="mt-3"></div>
        </div>
    </div>

    <!-- Resim Yöneticisi Modal -->
    <div class="modal fade" id="imageManagerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-images me-2"></i>Resim Yöneticisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-upload me-2"></i>Resim Yükle</h6>
                                </div>
                                <div class="card-body">
                                    <form id="imageUploadForm" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label">Dosya Seç</label>
                                            <input type="file" class="form-control" id="imageFile" accept="image/*" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-upload me-2"></i>Yükle
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-images me-2"></i>Mevcut Resimler</h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="loadImages()">
                                        <i class="fas fa-sync-alt"></i> Yenile
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="imageGrid" class="row g-2" style="max-height: 400px; overflow-y: auto;">
                                        <!-- Resimler buraya yüklenecek -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <!-- Menü Ayarları Modal -->
    <div class="modal fade" id="menuSettingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-bars me-2"></i>Menü Ayarları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="addToMenu">
                            <label class="form-check-label">Bu sayfayı menüye ekle</label>
                        </div>
                    </div>
                    <div id="menuSettings" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Menü Başlığı</label>
                            <input type="text" class="form-control" id="menuTitle" placeholder="Menüde görünecek başlık">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İkon (Font Awesome)</label>
                            <input type="text" class="form-control" id="menuIcon" placeholder="fas fa-home">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Menü Sırası</label>
                            <input type="number" class="form-control" id="menuOrder" value="0">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="menuFooter">
                                <label class="form-check-label">Footer'da da göster</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="saveMenuSettings()">Kaydet</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Değişken Yöneticisi Modal -->
    <div class="modal fade" id="variableManagerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cogs me-2"></i>Dinamik Değişken Yöneticisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Mevcut Değişkenler</h6>
                            <div id="variablesList" class="list-group mb-3">
                                <!-- Değişkenler buraya yüklenecek -->
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6>Yeni Değişken Oluştur</h6>
                            <form id="variableForm">
                                <div class="mb-3">
                                    <label class="form-label">Değişken Adı</label>
                                    <input type="text" class="form-control" id="variableName" placeholder="ornek_degisken" required>
                                    <small class="form-text text-muted">Sadece harf, rakam ve alt çizgi kullanın</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Başlık</label>
                                    <input type="text" class="form-control" id="variableTitle" placeholder="Örnek Değişken" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="variableDescription" rows="2" placeholder="Bu değişken ne işe yarar?"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tip</label>
                                    <select class="form-select" id="variableType" required>
                                        <option value="text">Metin</option>
                                        <option value="html">HTML</option>
                                        <option value="list">Liste</option>
                                        <option value="form">Form</option>
                                        <option value="gallery">Galeri</option>
                                        <option value="custom">Özel</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">İçerik/SQL</label>
                                    <textarea class="form-control" id="variableContent" rows="4" placeholder="SELECT * FROM tablo_adi WHERE durum = 1" required></textarea>
                                    <small class="form-text text-muted">SQL sorgusu veya HTML içerik</small>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-plus"></i> Değişken Oluştur
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/grapesjs@0.21.7/dist/grapes.min.js"></script>
    <script src="https://unpkg.com/grapesjs-plugin-code@1.0.3/dist/grapesjs-plugin-code.min.js"></script>
    
    <!-- CodeMirror for syntax highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    
    <!-- Real-Time Client (devre dışı - performans için) -->
    <!-- <script src="../assets/js/real-time-client.js"></script> -->

    <script>
        const pageId = <?php echo $pageId ? $pageId : 'null'; ?>;
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        let editor;
        let headerEditor;
        let footerEditor;
        
        // Real-Time Client'ı initialize et (sadece real-time için)
        // Not: real-time-client.js'de zaten global realTimeClient var
        // console.log('Real-time client mevcut mu?', typeof realTimeClient !== 'undefined'); // Debug log'u kaldırıldı

        // Toast göster
        function showToast(type, message) {
            const icons = { success: 'check-circle', error: 'times-circle', warning: 'exclamation-triangle', info: 'info-circle' };
            const colors = { success: 'success', error: 'danger', warning: 'warning', info: 'info' };
            const toast = $(`
                <div class="toast align-items-center text-white bg-${colors[type]} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${icons[type]} me-2"></i>${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            $('.toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast[0], { delay: 3000 });
            bsToast.show();
            toast.on('hidden.bs.toast', function() { $(this).remove(); });
        }

        // AI Panel toggle
        function toggleAI() {
            const panel = new bootstrap.Offcanvas(document.getElementById('aiPanel'));
            panel.show();
        }

        // AI ile içerik üret
        function generateWithAI() {
            const prompt = $('#aiPrompt').val().trim();
            if (!prompt) {
                showToast('warning', 'Lütfen bir açıklama girin!');
                return;
            }

            $('#aiResponse').html('<div class="text-center"><div class="spinner-border spinner-border-sm"></div> AI çalışıyor...</div>');

            $.post('ajax/ai-generate-content.php', {
                prompt: prompt,
                type: 'page_content',
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    const cleanHtml = response.html.replace(/```html|```/g, '').trim();
                    $('#aiResponse').html(`
                        <div class="alert alert-success">✅ Oluşturuldu!</div>
                        <button class="btn btn-primary w-100" onclick='applyAIContent(\`${cleanHtml.replace(/`/g, '\\`')}\`)'>
                            <i class="fas fa-plus"></i> Sayfaya Ekle
                        </button>
                    `);
                    showToast('success', 'AI içerik oluşturdu!');
                } else {
                    $('#aiResponse').html(`<div class="alert alert-danger">❌ ${response.message}</div>`);
                    showToast('error', 'Hata!');
                }
            }).fail(function() {
                $('#aiResponse').html('<div class="alert alert-danger">❌ Sunucu hatası!</div>');
                showToast('error', 'Sunucu hatası!');
            });
        }

        // AI içeriğini ekle
        function applyAIContent(html) {
            if (editor) {
                editor.addComponents(html);
                showToast('success', 'İçerik eklendi!');
            }
        }

        // Kaydet
        function save(publish) {
            console.log('save fonksiyonu çağrıldı, publish:', publish);
            const title = $('#pageTitle').val().trim();
            console.log('Sayfa başlığı:', title);
            
            if (!title) {
                showToast('warning', 'Başlık gerekli!');
                return;
            }

            // Önce slug kontrolü yap
            showToast('info', 'Slug kontrol ediliyor...');
            console.log('Slug kontrolü başlatılıyor...');
            
            $.post('ajax/check-slug-availability.php', {
                title: title,
                page_id: pageId,
                csrf_token: csrfToken
            }).done(function(response) {
                console.log('Slug kontrolü response:', response);
                if (response.success) {
                    if (!response.is_original) {
                        showToast('info', 'Slug otomatik düzeltildi: ' + response.slug);
                    }
                    console.log('Slug kontrolü başarılı, kaydetme işlemi başlatılıyor...');
                    proceedWithSave(publish, title);
                } else {
                    console.error('Slug kontrolü hatası:', response.message);
                    showToast('error', 'Slug kontrolü hatası: ' + response.message);
                }
            }).fail(function(xhr, status, error) {
                console.error('Slug kontrolü AJAX hatası:', status, error);
                showToast('error', 'Slug kontrolü başarısız!');
            });
        }
        
        // Layout kontrolü ve otomatik ekleme
        function checkAndAddLayout(content, template) {
            // Eğer template 'custom' değilse ve content'te layout yapısı yoksa ekle
            if (template && template !== 'custom') {
                // Layout yapısı kontrolü (header, footer, main gibi yapısal elementler)
                const hasLayout = content.includes('<header') || 
                                 content.includes('<footer') || 
                                 content.includes('class="header') || 
                                 content.includes('class="footer') ||
                                 content.includes('id="header') ||
                                 content.includes('id="footer') ||
                                 content.includes('template-layout-wrapper');
                
                if (!hasLayout) {
                    // Template layout yapısını ekle
                    const layoutWrapper = `
                        <div class="template-layout-wrapper" data-template="${template}">
                            <header class="template-header">
                                <!-- Header content will be loaded from template -->
                            </header>
                            <main class="template-content">
                                ${content}
                            </main>
                            <footer class="template-footer">
                                <!-- Footer content will be loaded from template -->
                            </footer>
                        </div>
                    `;
                    showToast('info', `Layout yapısı otomatik eklendi (${template} template)`);
                    return layoutWrapper;
                }
            }
            return content;
        }
        
        function proceedWithSave(publish, title) {
            console.log('proceedWithSave çağrıldı, publish:', publish, 'title:', title);
            let html = editor.getHtml();
            const css = editor.getCss();
            const template = document.getElementById('pageTemplate').value;
            
            console.log('HTML uzunluğu:', html.length);
            console.log('CSS uzunluğu:', css.length);
            console.log('Template:', template);

            showToast('info', publish ? 'Yayınlanıyor...' : 'Taslak kaydediliyor...');

            // Layout kontrolü ve otomatik ekleme
            html = checkAndAddLayout(html, template);

            // Önce değişkenleri işle
            $.post('ajax/process-dynamic-variables.php', {
                html: html,
                variable: 'all',
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    html = response.html;
                    showToast('info', 'Kaydediliyor...');
                } else {
                    showToast('warning', 'Değişken işleme hatası: ' + response.message);
                }
            }).fail(function() {
                showToast('warning', 'Değişken işleme hatası!');
            }).always(function() {
                // Menü ayarlarını al
                const menuSettings = JSON.parse(localStorage.getItem('pageMenuSettings') || '{}');
                
                // Değişken işleme başarılı olsun olmasın kaydet
                console.log('Sayfa kaydetme işlemi başlatılıyor...');
                $.post('ajax/page-builder-save.php', {
                    page_id: pageId,
                    title: title,
                    html: html,
                    css: css,
                    status: publish ? 'published' : 'draft',
                    template: template,
                    menu_settings: JSON.stringify(menuSettings),
                    csrf_token: csrfToken
                }).done(function(response) {
                    console.log('Sayfa kaydetme response:', response);
                    if (response.success) {
                        showToast('success', response.message);
                        if (!pageId && response.page_id) {
                            setTimeout(function() {
                                window.location.href = 'page-builder-ultimate-v3.php?page_id=' + response.page_id;
                            }, 1000);
                        }
                    } else {
                        console.error('Sayfa kaydetme hatası:', response.message);
                        showToast('error', response.message);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Sayfa kaydetme AJAX hatası:', status, error);
                    showToast('error', 'Kaydetme hatası!');
                });
            });
        }

        // Önizle
        function preview() {
            const html = editor.getHtml();
            const css = editor.getCss();
            const win = window.open('', '_blank');
            win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' + css + '</style></head><body>' + html + '</body></html>');
            win.document.close();
            showToast('info', 'Önizleme açıldı!');
        }

        // Şablon kütüphanesi
        function showTemplateLibrary() {
            const templates = {
                'premium-hotel': {
                    name: 'Premium Hotel',
                    description: 'Lüks otel web sitesi',
                    category: 'Hotel',
                    content: `<section style="padding:120px 20px; background:linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); color:white; position:relative; overflow:hidden;">
                        <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="hotel-pattern" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23hotel-pattern)"/></svg>'); opacity:0.3;"></div>
                        <div class="container" style="max-width:1200px; margin:0 auto; position:relative; z-index:2; text-align:center;">
                            <h1 style="font-size:4rem; font-weight:900; margin-bottom:2rem; text-shadow:0 4px 8px rgba(0,0,0,0.3);">Lüks Konaklama Deneyimi</h1>
                            <p style="font-size:1.5rem; margin-bottom:3rem; opacity:0.9; max-width:600px; margin-left:auto; margin-right:auto;">Premium hizmet ve konforun buluştuğu yer</p>
                            <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                                <button class="btn btn-warning btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; box-shadow:0 10px 30px rgba(255,193,7,0.3);">Rezervasyon Yap</button>
                                <button class="btn btn-outline-light btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; border:2px solid white;">Odaları Gör</button>
                            </div>
                        </div>
                    </section>`
                },
                'business': {
                    name: 'Business Landing',
                    description: 'Profesyonel işletme landing page',
                    category: 'Business',
                    content: `<section style="padding:120px 20px; background:linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); text-align:center; color:white; position:relative; overflow:hidden;">
                        <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>'); opacity:0.3;"></div>
                        <div class="container" style="max-width:1200px; margin:0 auto; position:relative; z-index:2;">
                            <h1 style="font-size:4rem; font-weight:900; margin-bottom:2rem; text-shadow:0 4px 8px rgba(0,0,0,0.3);">Profesyonel Çözümler</h1>
                            <p style="font-size:1.5rem; margin-bottom:3rem; opacity:0.9; max-width:600px; margin-left:auto; margin-right:auto;">İşletmenizi dijital dünyada öne çıkarın</p>
                            <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                                <button class="btn btn-light btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; box-shadow:0 10px 30px rgba(0,0,0,0.2);">Hemen Başla</button>
                                <button class="btn btn-outline-light btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; border:2px solid white;">Demo İzle</button>
                            </div>
                        </div>
                    </section>`
                },
                'portfolio': {
                    name: 'Portfolio Showcase',
                    description: 'Yaratıcı portfolyo vitrin',
                    category: 'Portfolio',
                    content: `<section style="padding:100px 20px; background:#f8f9fa;">
                        <div class="container" style="max-width:1200px; margin:0 auto;">
                            <h2 style="text-align:center; margin-bottom:60px; font-size:2.5rem; color:#333;">Çalışmalarımız</h2>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:2rem;">
                                <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1); transition:transform 0.3s ease;">
                                    <div style="width:100%; height:250px; background:linear-gradient(135deg, #667eea, #764ba2); position:relative;">
                                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; text-align:center;">
                                            <i class="fas fa-image" style="font-size:3rem; margin-bottom:1rem;"></i>
                                            <h4>Proje 1</h4>
                                        </div>
                                    </div>
                                    <div style="padding:1.5rem;">
                                        <h3 style="margin-bottom:1rem; color:#333;">Web Tasarım</h3>
                                        <p style="color:#666; line-height:1.6;">Modern ve responsive web tasarım projesi</p>
                                    </div>
                                </div>
                                <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1); transition:transform 0.3s ease;">
                                    <div style="width:100%; height:250px; background:linear-gradient(135deg, #ff6b6b, #4ecdc4); position:relative;">
                                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; text-align:center;">
                                            <i class="fas fa-mobile-alt" style="font-size:3rem; margin-bottom:1rem;"></i>
                                            <h4>Proje 2</h4>
                                        </div>
                                    </div>
                                    <div style="padding:1.5rem;">
                                        <h3 style="margin-bottom:1rem; color:#333;">Mobil Uygulama</h3>
                                        <p style="color:#666; line-height:1.6;">iOS ve Android uygulama geliştirme</p>
                                    </div>
                                </div>
                                <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1); transition:transform 0.3s ease;">
                                    <div style="width:100%; height:250px; background:linear-gradient(135deg, #f093fb, #f5576c); position:relative;">
                                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; text-align:center;">
                                            <i class="fas fa-shopping-cart" style="font-size:3rem; margin-bottom:1rem;"></i>
                                            <h4>Proje 3</h4>
                                        </div>
                                    </div>
                                    <div style="padding:1.5rem;">
                                        <h3 style="margin-bottom:1rem; color:#333;">E-ticaret</h3>
                                        <p style="color:#666; line-height:1.6;">Online mağaza ve ödeme sistemi</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>`
                },
                'ecommerce': {
                    name: 'E-commerce Store',
                    description: 'Online mağaza ana sayfa',
                    category: 'E-commerce',
                    content: `<section style="padding:100px 20px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white;">
                        <div class="container" style="max-width:1200px; margin:0 auto; text-align:center;">
                            <h1 style="font-size:3.5rem; font-weight:800; margin-bottom:2rem;">Premium Ürünler</h1>
                            <p style="font-size:1.3rem; margin-bottom:3rem; opacity:0.9;">Kaliteli ürünler, uygun fiyatlar</p>
                            <div style="display:flex; gap:2rem; justify-content:center; flex-wrap:wrap;">
                                <div style="background:rgba(255,255,255,0.1); padding:2rem; border-radius:15px; backdrop-filter:blur(10px); min-width:250px;">
                                    <i class="fas fa-shipping-fast" style="font-size:3rem; margin-bottom:1rem; color:#ffd700;"></i>
                                    <h3 style="margin-bottom:1rem;">Hızlı Kargo</h3>
                                    <p style="opacity:0.8;">24 saat içinde kargo</p>
                                </div>
                                <div style="background:rgba(255,255,255,0.1); padding:2rem; border-radius:15px; backdrop-filter:blur(10px); min-width:250px;">
                                    <i class="fas fa-shield-alt" style="font-size:3rem; margin-bottom:1rem; color:#ffd700;"></i>
                                    <h3 style="margin-bottom:1rem;">Güvenli Ödeme</h3>
                                    <p style="opacity:0.8;">SSL sertifikalı ödeme</p>
                                </div>
                                <div style="background:rgba(255,255,255,0.1); padding:2rem; border-radius:15px; backdrop-filter:blur(10px); min-width:250px;">
                                    <i class="fas fa-headset" style="font-size:3rem; margin-bottom:1rem; color:#ffd700;"></i>
                                    <h3 style="margin-bottom:1rem;">7/24 Destek</h3>
                                    <p style="opacity:0.8;">Kesintisiz müşteri hizmeti</p>
                                </div>
                            </div>
                        </div>
                    </section>`
                },
                'custom': {
                    name: 'Özel Tasarım',
                    description: 'Boş sayfa - kendi tasarımınızı yapın',
                    category: 'Custom',
                    content: `<section style="padding:100px 20px; background:#f8f9fa; text-align:center;">
                        <div class="container" style="max-width:800px; margin:0 auto;">
                            <h1 style="font-size:3rem; color:#333; margin-bottom:2rem;">Kendi Tasarımınızı Oluşturun</h1>
                            <p style="font-size:1.2rem; color:#666; margin-bottom:3rem;">Bu boş sayfa ile istediğiniz tasarımı yapabilirsiniz</p>
                            <div style="background:white; padding:3rem; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                                <i class="fas fa-paint-brush" style="font-size:4rem; color:#667eea; margin-bottom:2rem;"></i>
                                <h3 style="color:#333; margin-bottom:1rem;">Yaratıcılığınızı Kullanın</h3>
                                <p style="color:#666;">Sol panelden blokları sürükleyip bırakarak sayfanızı oluşturun</p>
                            </div>
                        </div>
                    </section>`
                }
            };
            
            let modalHtml = `
                <div class="modal fade" id="templateModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content" style="border-radius:20px; overflow:hidden;">
                            <div class="modal-header" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; border:none;">
                                <h5 class="modal-title" style="font-size:1.5rem; font-weight:700;">
                                    <i class="fas fa-palette me-2"></i>Profesyonel Şablon Galerisi
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" style="padding:2rem;">
                                <div class="row g-4">
            `;
            
            Object.keys(templates).forEach(key => {
                const template = templates[key];
                modalHtml += `
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100" style="border:none; box-shadow:0 10px 30px rgba(0,0,0,0.1); border-radius:15px; overflow:hidden; transition:transform 0.3s ease;">
                            <div style="height:200px; background:linear-gradient(135deg, #667eea, #764ba2); position:relative; display:flex; align-items:center; justify-content:center;">
                                <div style="position:absolute; top:10px; right:10px; background:rgba(255,255,255,0.2); padding:0.5rem; border-radius:5px; backdrop-filter:blur(10px);">
                                    <span style="color:white; font-size:0.8rem; font-weight:600;">${template.category}</span>
                                </div>
                                <i class="fas fa-eye" style="font-size:3rem; color:white; opacity:0.7;"></i>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title" style="font-weight:700; margin-bottom:0.5rem;">${template.name}</h5>
                                <p class="card-text" style="color:#666; font-size:0.9rem;">${template.description}</p>
                                <button class="btn btn-primary w-100" onclick="loadTemplate('${key}')" style="border-radius:25px; font-weight:600;">
                                    <i class="fas fa-plus me-2"></i>Kullan
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            modalHtml += `
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Eski modal'ı kaldır
            const existingModal = document.getElementById('templateModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Yeni modal'ı ekle
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Modal'ı göster
            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }
        
        function loadTemplate(templateKey) {
            const templates = {
                'premium-hotel': `<section style="padding:120px 20px; background:linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); color:white; position:relative; overflow:hidden;">
                    <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="hotel-pattern" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23hotel-pattern)"/></svg>'); opacity:0.3;"></div>
                    <div class="container" style="max-width:1200px; margin:0 auto; position:relative; z-index:2; text-align:center;">
                        <h1 style="font-size:4rem; font-weight:900; margin-bottom:2rem; text-shadow:0 4px 8px rgba(0,0,0,0.3);">Lüks Konaklama Deneyimi</h1>
                        <p style="font-size:1.5rem; margin-bottom:3rem; opacity:0.9; max-width:600px; margin-left:auto; margin-right:auto;">Premium hizmet ve konforun buluştuğu yer</p>
                        <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                            <button class="btn btn-warning btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; box-shadow:0 10px 30px rgba(255,193,7,0.3);">Rezervasyon Yap</button>
                            <button class="btn btn-outline-light btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; border:2px solid white;">Odaları Gör</button>
                        </div>
                    </div>
                </section>`,
                'business': `<section style="padding:120px 20px; background:linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); text-align:center; color:white; position:relative; overflow:hidden;">
                    <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>'); opacity:0.3;"></div>
                    <div class="container" style="max-width:1200px; margin:0 auto; position:relative; z-index:2;">
                        <h1 style="font-size:4rem; font-weight:900; margin-bottom:2rem; text-shadow:0 4px 8px rgba(0,0,0,0.3);">Profesyonel Çözümler</h1>
                        <p style="font-size:1.5rem; margin-bottom:3rem; opacity:0.9; max-width:600px; margin-left:auto; margin-right:auto;">İşletmenizi dijital dünyada öne çıkarın</p>
                        <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                            <button class="btn btn-light btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; box-shadow:0 10px 30px rgba(0,0,0,0.2);">Hemen Başla</button>
                            <button class="btn btn-outline-light btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; border:2px solid white;">Demo İzle</button>
                        </div>
                    </div>
                </section>`,
                'portfolio': `<section style="padding:100px 20px; background:#f8f9fa;">
                    <div class="container" style="max-width:1200px; margin:0 auto;">
                        <h2 style="text-align:center; margin-bottom:60px; font-size:2.5rem; color:#333;">Çalışmalarımız</h2>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:2rem;">
                            <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1); transition:transform 0.3s ease;">
                                <div style="width:100%; height:250px; background:linear-gradient(135deg, #667eea, #764ba2); position:relative;">
                                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; text-align:center;">
                                        <i class="fas fa-image" style="font-size:3rem; margin-bottom:1rem;"></i>
                                        <h4>Proje 1</h4>
                                    </div>
                                </div>
                                <div style="padding:1.5rem;">
                                    <h3 style="margin-bottom:1rem; color:#333;">Web Tasarım</h3>
                                    <p style="color:#666; line-height:1.6;">Modern ve responsive web tasarım projesi</p>
                                </div>
                            </div>
                            <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1); transition:transform 0.3s ease;">
                                <div style="width:100%; height:250px; background:linear-gradient(135deg, #ff6b6b, #4ecdc4); position:relative;">
                                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; text-align:center;">
                                        <i class="fas fa-mobile-alt" style="font-size:3rem; margin-bottom:1rem;"></i>
                                        <h4>Proje 2</h4>
                                    </div>
                                </div>
                                <div style="padding:1.5rem;">
                                    <h3 style="margin-bottom:1rem; color:#333;">Mobil Uygulama</h3>
                                    <p style="color:#666; line-height:1.6;">iOS ve Android uygulama geliştirme</p>
                                </div>
                            </div>
                            <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1); transition:transform 0.3s ease;">
                                <div style="width:100%; height:250px; background:linear-gradient(135deg, #f093fb, #f5576c); position:relative;">
                                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; text-align:center;">
                                        <i class="fas fa-shopping-cart" style="font-size:3rem; margin-bottom:1rem;"></i>
                                        <h4>Proje 3</h4>
                                    </div>
                                </div>
                                <div style="padding:1.5rem;">
                                    <h3 style="margin-bottom:1rem; color:#333;">E-ticaret</h3>
                                    <p style="color:#666; line-height:1.6;">Online mağaza ve ödeme sistemi</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>`,
                'ecommerce': `<section style="padding:100px 20px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white;">
                    <div class="container" style="max-width:1200px; margin:0 auto; text-align:center;">
                        <h1 style="font-size:3.5rem; font-weight:800; margin-bottom:2rem;">Premium Ürünler</h1>
                        <p style="font-size:1.3rem; margin-bottom:3rem; opacity:0.9;">Kaliteli ürünler, uygun fiyatlar</p>
                        <div style="display:flex; gap:2rem; justify-content:center; flex-wrap:wrap;">
                            <div style="background:rgba(255,255,255,0.1); padding:2rem; border-radius:15px; backdrop-filter:blur(10px); min-width:250px;">
                                <i class="fas fa-shipping-fast" style="font-size:3rem; margin-bottom:1rem; color:#ffd700;"></i>
                                <h3 style="margin-bottom:1rem;">Hızlı Kargo</h3>
                                <p style="opacity:0.8;">24 saat içinde kargo</p>
                            </div>
                            <div style="background:rgba(255,255,255,0.1); padding:2rem; border-radius:15px; backdrop-filter:blur(10px); min-width:250px;">
                                <i class="fas fa-shield-alt" style="font-size:3rem; margin-bottom:1rem; color:#ffd700;"></i>
                                <h3 style="margin-bottom:1rem;">Güvenli Ödeme</h3>
                                <p style="opacity:0.8;">SSL sertifikalı ödeme</p>
                            </div>
                            <div style="background:rgba(255,255,255,0.1); padding:2rem; border-radius:15px; backdrop-filter:blur(10px); min-width:250px;">
                                <i class="fas fa-headset" style="font-size:3rem; margin-bottom:1rem; color:#ffd700;"></i>
                                <h3 style="margin-bottom:1rem;">7/24 Destek</h3>
                                <p style="opacity:0.8;">Kesintisiz müşteri hizmeti</p>
                            </div>
                        </div>
                    </div>
                </section>`,
                'custom': `<section style="padding:100px 20px; background:#f8f9fa; text-align:center;">
                    <div class="container" style="max-width:800px; margin:0 auto;">
                        <h1 style="font-size:3rem; color:#333; margin-bottom:2rem;">Kendi Tasarımınızı Oluşturun</h1>
                        <p style="font-size:1.2rem; color:#666; margin-bottom:3rem;">Bu boş sayfa ile istediğiniz tasarımı yapabilirsiniz</p>
                        <div style="background:white; padding:3rem; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                            <i class="fas fa-paint-brush" style="font-size:4rem; color:#667eea; margin-bottom:2rem;"></i>
                            <h3 style="color:#333; margin-bottom:1rem;">Yaratıcılığınızı Kullanın</h3>
                            <p style="color:#666;">Sol panelden blokları sürükleyip bırakarak sayfanızı oluşturun</p>
                        </div>
                    </div>
                </section>`
            };
            
            if (templates[templateKey] && editor) {
                editor.addComponents(templates[templateKey]);
                showToast('success', 'Şablon başarıyla eklendi!');
                $('#templateModal').modal('hide');
            }
        }
        
        // Template seçimi için fonksiyon
        function selectTemplate(templateName) {
            document.getElementById('pageTemplate').value = templateName;
            showToast('info', `${templateName} template seçildi!`);
        }

        // Değişken yöneticisi
        function showVariableManager() {
            loadVariables();
            const modal = new bootstrap.Modal(document.getElementById('variableManagerModal'));
            modal.show();
        }

        // Değişkenleri yükle
        function loadVariables() {
            $.ajax({
                url: 'ajax/get-dynamic-variables.php',
                method: 'GET',
                data: { csrf_token: csrfToken }
            }).done(function(response) {
                console.log('loadVariables response:', response);
                if (response.success) {
                    displayVariables(response.variables);
                } else {
                    showToast('error', 'Değişkenler yüklenemedi: ' + response.message);
                }
            }).fail(function() {
                showToast('error', 'Sunucu hatası!');
            });
        }

        // Değişkenleri göster
        function displayVariables(variables) {
            const container = document.getElementById('variablesList');
            container.innerHTML = '';

            variables.forEach(variable => {
                const item = document.createElement('div');
                item.className = 'list-group-item d-flex justify-content-between align-items-center';
                item.innerHTML = `
                    <div>
                        <h6 class="mb-1">${variable.variable_title}</h6>
                        <p class="mb-1 text-muted">${variable.variable_description || 'Açıklama yok'}</p>
                        <small class="text-info">{{${variable.variable_name}}}</small>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="addVariableToPage('${variable.variable_name}')">
                            <i class="fas fa-plus"></i> Ekle
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteVariable(${variable.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(item);
            });
        }

        // Değişkeni sayfaya ekle
        function addVariableToPage(variableName) {
            const blockContent = `
                <div class="dynamic-content" data-variable="${variableName}" style="padding:20px; background:#f8f9fa; border:2px dashed #667eea; border-radius:8px; text-align:center;">
                    <i class="fas fa-cogs fa-2x text-primary mb-2"></i>
                    <h5>${variableName}</h5>
                    <p class="text-muted">Bu alan otomatik olarak içerik gösterecek</p>
                    <small class="text-info">{{${variableName}}}</small>
                </div>
            `;
            
            editor.addComponents(blockContent);
            showToast('success', 'Değişken sayfaya eklendi!');
            bootstrap.Modal.getInstance(document.getElementById('variableManagerModal')).hide();
        }

        // Yeni değişken oluştur
        $('#variableForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                variable_name: $('#variableName').val(),
                variable_title: $('#variableTitle').val(),
                variable_description: $('#variableDescription').val(),
                variable_type: $('#variableType').val(),
                variable_content: $('#variableContent').val(),
                csrf_token: csrfToken
            };

            $.post('ajax/save-dynamic-variable.php', formData).done(function(response) {
                if (response.success) {
                    showToast('success', 'Değişken oluşturuldu!');
                    loadVariables();
                    $('#variableForm')[0].reset();
                } else {
                    showToast('error', response.message);
                }
            }).fail(function() {
                showToast('error', 'Sunucu hatası!');
            });
        });

        // Değişken sil
        function deleteVariable(variableId) {
            if (confirm('Bu değişkeni silmek istediğinizden emin misiniz?')) {
                $.post('ajax/delete-dynamic-variable.php', {
                    variable_id: variableId,
                    csrf_token: csrfToken
                }).done(function(response) {
                    if (response.success) {
                        showToast('success', 'Değişken silindi!');
                        loadVariables();
                    } else {
                        showToast('error', response.message);
                    }
                }).fail(function() {
                    showToast('error', 'Sunucu hatası!');
                });
            }
        }

        // Menü ayarları göster
        function showMenuSettings() {
            const modal = new bootstrap.Modal(document.getElementById('menuSettingsModal'));
            modal.show();
        }

        // Menü ayarlarını kaydet
        function saveMenuSettings() {
            const addToMenu = document.getElementById('addToMenu').checked;
            const menuTitle = document.getElementById('menuTitle').value;
            const menuIcon = document.getElementById('menuIcon').value;
            const menuOrder = document.getElementById('menuOrder').value;
            const menuFooter = document.getElementById('menuFooter').checked;

            if (addToMenu && !menuTitle.trim()) {
                showToast('warning', 'Menü başlığı gerekli!');
                return;
            }

            // Menü ayarlarını localStorage'a kaydet
            const menuSettings = {
                addToMenu: addToMenu,
                menuTitle: menuTitle,
                menuIcon: menuIcon,
                menuOrder: menuOrder,
                menuFooter: menuFooter
            };

            localStorage.setItem('pageMenuSettings', JSON.stringify(menuSettings));
            showToast('success', 'Menü ayarları kaydedildi!');
            bootstrap.Modal.getInstance(document.getElementById('menuSettingsModal')).hide();
        }

        // Menü ayarlarını yükle
        function loadMenuSettings() {
            const settings = localStorage.getItem('pageMenuSettings');
            if (settings) {
                const menuSettings = JSON.parse(settings);
                document.getElementById('addToMenu').checked = menuSettings.addToMenu;
                document.getElementById('menuTitle').value = menuSettings.menuTitle || '';
                document.getElementById('menuIcon').value = menuSettings.menuIcon || '';
                document.getElementById('menuOrder').value = menuSettings.menuOrder || 0;
                document.getElementById('menuFooter').checked = menuSettings.menuFooter || false;
                
                // Menü ayarları alanlarını göster/gizle
                const menuSettingsDiv = document.getElementById('menuSettings');
                menuSettingsDiv.style.display = menuSettings.addToMenu ? 'block' : 'none';
            }
        }

        // Menü checkbox değiştiğinde
        document.getElementById('addToMenu').addEventListener('change', function() {
            const menuSettingsDiv = document.getElementById('menuSettings');
            menuSettingsDiv.style.display = this.checked ? 'block' : 'none';
        });

        // Muhteşem Blokları Ekle
        function addUltimateBlocks() {
            // console.log('addUltimateBlocks çağrıldı'); // Debug log'u kaldırıldı
            
            if (!editor || !editor.BlockManager) {
                console.error('Editor veya BlockManager bulunamadı!');
                return;
            }
            
            const bm = editor.BlockManager;
            
            // === TEMEL BLOKLAR ===
            bm.add('text', {
                label: '<i class="fa fa-font"></i><div>Metin</div>',
                category: 'Temel',
                content: '<div style="padding:20px; font-size:16px; line-height:1.6;">Metin yazın veya düzenleyin...</div>',
                traits: [
                    {
                        type: 'bootstrap-grid',
                        name: 'class',
                        label: 'Bootstrap Grid'
                    },
                    {
                        type: 'bootstrap-utility',
                        name: 'utility',
                        label: 'Bootstrap Utility'
                    },
                    {
                        type: 'flexbox',
                        name: 'flex',
                        label: 'Flexbox'
                    }
                ]
            });
            
            bm.add('heading', {
                label: '<i class="fa fa-heading"></i><div>Başlık</div>',
                category: 'Temel',
                content: '<h2 style="margin:20px 0; color:#333; font-weight:700;">Başlık</h2>',
                traits: [
                    {
                        type: 'select',
                        name: 'tagName',
                        label: 'Başlık Seviyesi',
                        options: [
                            {value: 'h1', name: 'H1'},
                            {value: 'h2', name: 'H2'},
                            {value: 'h3', name: 'H3'},
                            {value: 'h4', name: 'H4'},
                            {value: 'h5', name: 'H5'},
                            {value: 'h6', name: 'H6'}
                        ]
                    },
                    {
                        type: 'bootstrap-utility',
                        name: 'utility',
                        label: 'Bootstrap Utility'
                    },
                    {
                        type: 'flexbox',
                        name: 'flex',
                        label: 'Flexbox'
                    }
                ]
            });
            
            bm.add('image', {
                label: '<i class="fa fa-image"></i><div>Resim</div>',
                category: 'Temel',
                content: '<img src="templates/premium-hotel/assets/images/logo.svg" alt="Resim" style="max-width:100%; height:auto; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.1);">',
                attributes: {
                    class: 'gjs-image'
                },
                traits: [
                    {
                        type: 'text',
                        name: 'src',
                        label: 'Resim URL'
                    },
                    {
                        type: 'text',
                        name: 'alt',
                        label: 'Alt Text'
                    },
                    {
                        type: 'bootstrap-grid',
                        name: 'class',
                        label: 'Bootstrap Grid'
                    },
                    {
                        type: 'bootstrap-utility',
                        name: 'utility',
                        label: 'Bootstrap Utility'
                    }
                ]
            });
            
            bm.add('button', {
                label: '<i class="fa fa-mouse-pointer"></i><div>Buton</div>',
                category: 'Temel',
                content: '<a href="#" class="btn btn-primary" style="padding:12px 30px; border-radius:25px; text-decoration:none; display:inline-block; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; border:none; box-shadow:0 4px 15px rgba(102, 126, 234, 0.3);">Buton</a>',
                traits: [
                    {
                        type: 'text',
                        name: 'href',
                        label: 'Link URL'
                    },
                    {
                        type: 'select',
                        name: 'class',
                        label: 'Buton Stili',
                        options: [
                            {value: 'btn btn-primary', name: 'Primary'},
                            {value: 'btn btn-secondary', name: 'Secondary'},
                            {value: 'btn btn-success', name: 'Success'},
                            {value: 'btn btn-danger', name: 'Danger'},
                            {value: 'btn btn-warning', name: 'Warning'},
                            {value: 'btn btn-info', name: 'Info'},
                            {value: 'btn btn-light', name: 'Light'},
                            {value: 'btn btn-dark', name: 'Dark'},
                            {value: 'btn btn-outline-primary', name: 'Outline Primary'},
                            {value: 'btn btn-outline-secondary', name: 'Outline Secondary'}
                        ]
                    },
                    {
                        type: 'select',
                        name: 'size',
                        label: 'Buton Boyutu',
                        options: [
                            {value: 'btn-sm', name: 'Küçük'},
                            {value: '', name: 'Normal'},
                            {value: 'btn-lg', name: 'Büyük'}
                        ]
                    }
                ]
            });
            
            bm.add('divider', {
                label: '<i class="fa fa-minus"></i><div>Ayırıcı</div>',
                category: 'Temel',
                content: '<hr style="border:none; height:2px; background:linear-gradient(90deg, transparent, #667eea, transparent); margin:40px 0;">'
            });

            // === LAYOUT BLOKLARI ===
            bm.add('container', {
                label: '<i class="fa fa-square"></i><div>Container</div>',
                category: 'Layout',
                content: '<div class="container" style="padding:40px 15px; max-width:1200px; margin:0 auto;"><p>İçerik buraya</p></div>'
            });
            
            bm.add('row-2-col', {
                label: '<i class="fa fa-columns"></i><div>2 Sütun</div>',
                category: 'Layout',
                content: '<div class="row" style="display:flex; gap:20px; margin:20px 0;"><div class="col" style="flex:1; padding:20px; background:#f8f9fa; border-radius:8px;"><p>Sol sütun</p></div><div class="col" style="flex:1; padding:20px; background:#f8f9fa; border-radius:8px;"><p>Sağ sütun</p></div></div>'
            });
            
            bm.add('row-3-col', {
                label: '<i class="fa fa-th"></i><div>3 Sütun</div>',
                category: 'Layout',
                content: '<div class="row" style="display:flex; gap:15px; margin:20px 0;"><div class="col" style="flex:1; padding:20px; background:#f8f9fa; border-radius:8px;"><p>Sütun 1</p></div><div class="col" style="flex:1; padding:20px; background:#f8f9fa; border-radius:8px;"><p>Sütun 2</p></div><div class="col" style="flex:1; padding:20px; background:#f8f9fa; border-radius:8px;"><p>Sütun 3</p></div></div>'
            });

            // === MODERN BÖLÜMLER ===
            bm.add('modern-hero', {
                label: '<i class="fa fa-rocket"></i><div>Modern Hero</div>',
                category: 'Modern',
                content: `<section style="padding:120px 20px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align:center; color:white; position:relative; overflow:hidden;">
                    <div class="container animate__animated animate__fadeIn" style="max-width:1200px; margin:0 auto;">
                        <h1 style="font-size:3.5em; font-weight:800; margin-bottom:25px; text-shadow:2px 2px 4px rgba(0,0,0,0.2);">Harika Bir Başlık</h1>
                        <p style="font-size:1.4em; margin-bottom:40px; opacity:0.95;">Modern ve etkileyici bir alt başlık yazın</p>
                        <a href="#" class="btn btn-light btn-lg" style="padding:15px 50px; font-size:1.1em; border-radius:50px; box-shadow:0 10px 30px rgba(0,0,0,0.2); text-decoration:none; display:inline-block;">Başlayın</a>
                    </div>
                </section>`
            });
            
            bm.add('feature-cards', {
                label: '<i class="fa fa-star"></i><div>Özellik Kartları</div>',
                category: 'Modern',
                content: `<section style="padding:80px 20px; background:#f8f9fa;">
                    <div class="container" style="max-width:1200px; margin:0 auto;">
                        <h2 style="text-align:center; margin-bottom:60px; font-size:2.5em; color:#333;">Özelliklerimiz</h2>
                        <div class="row" style="display:flex; gap:30px; flex-wrap:wrap;">
                            <div class="col" style="flex:1; min-width:300px; background:white; padding:40px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); text-align:center;">
                                <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-rocket" style="font-size:2em; color:white;"></i>
                                </div>
                                <h3 style="margin-bottom:15px; color:#333;">Hızlı</h3>
                                <p style="color:#666; line-height:1.6;">Süper hızlı performans</p>
                            </div>
                            <div class="col" style="flex:1; min-width:300px; background:white; padding:40px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); text-align:center;">
                                <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-shield-alt" style="font-size:2em; color:white;"></i>
                                </div>
                                <h3 style="margin-bottom:15px; color:#333;">Güvenli</h3>
                                <p style="color:#666; line-height:1.6;">Maksimum güvenlik</p>
                            </div>
                            <div class="col" style="flex:1; min-width:300px; background:white; padding:40px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); text-align:center;">
                                <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-mobile-alt" style="font-size:2em; color:white;"></i>
                                </div>
                                <h3 style="margin-bottom:15px; color:#333;">Responsive</h3>
                                <p style="color:#666; line-height:1.6;">Tüm cihazlarda mükemmel</p>
                            </div>
                        </div>
                    </div>
                </section>`
            });
            
            bm.add('pricing-modern', {
                label: '<i class="fa fa-tags"></i><div>Fiyat Tablosu</div>',
                category: 'Modern',
                content: `<section style="padding:80px 20px; background:linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
                    <div class="container" style="max-width:1200px; margin:0 auto;">
                        <h2 style="text-align:center; margin-bottom:60px; font-size:2.5em; color:#333;">Fiyatlarımız</h2>
                        <div class="row" style="display:flex; gap:30px; justify-content:center; flex-wrap:wrap;">
                            <div class="pricing-card" style="background:white; padding:40px; border-radius:20px; box-shadow:0 15px 35px rgba(0,0,0,0.1); text-align:center; max-width:300px; position:relative;">
                                <h3 style="color:#333; margin-bottom:20px;">Temel</h3>
                                <div style="font-size:3em; font-weight:bold; color:#667eea; margin-bottom:20px;">₺99<span style="font-size:0.5em;">/ay</span></div>
                                <ul style="list-style:none; padding:0; margin-bottom:30px;">
                                    <li style="padding:10px 0; border-bottom:1px solid #eee;">✓ 5 Proje</li>
                                    <li style="padding:10px 0; border-bottom:1px solid #eee;">✓ 10GB Depolama</li>
                                    <li style="padding:10px 0; border-bottom:1px solid #eee;">✓ Email Desteği</li>
                                </ul>
                                <a href="#" style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:15px 30px; border-radius:25px; text-decoration:none; display:inline-block;">Seç</a>
                            </div>
                            <div class="pricing-card" style="background:linear-gradient(135deg, #667eea, #764ba2); padding:40px; border-radius:20px; box-shadow:0 15px 35px rgba(0,0,0,0.2); text-align:center; max-width:300px; position:relative; color:white; transform:scale(1.05);">
                                <div style="position:absolute; top:-10px; left:50%; transform:translateX(-50%); background:#ff6b6b; color:white; padding:5px 20px; border-radius:15px; font-size:0.9em;">POPÜLER</div>
                                <h3 style="margin-bottom:20px;">Pro</h3>
                                <div style="font-size:3em; font-weight:bold; margin-bottom:20px;">₺199<span style="font-size:0.5em;">/ay</span></div>
                                <ul style="list-style:none; padding:0; margin-bottom:30px;">
                                    <li style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.2);">✓ 25 Proje</li>
                                    <li style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.2);">✓ 100GB Depolama</li>
                                    <li style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.2);">✓ Öncelikli Destek</li>
                                </ul>
                                <a href="#" style="background:white; color:#667eea; padding:15px 30px; border-radius:25px; text-decoration:none; display:inline-block; font-weight:bold;">Seç</a>
                            </div>
                            <div class="pricing-card" style="background:white; padding:40px; border-radius:20px; box-shadow:0 15px 35px rgba(0,0,0,0.1); text-align:center; max-width:300px; position:relative;">
                                <h3 style="color:#333; margin-bottom:20px;">Kurumsal</h3>
                                <div style="font-size:3em; font-weight:bold; color:#667eea; margin-bottom:20px;">₺499<span style="font-size:0.5em;">/ay</span></div>
                                <ul style="list-style:none; padding:0; margin-bottom:30px;">
                                    <li style="padding:10px 0; border-bottom:1px solid #eee;">✓ Sınırsız Proje</li>
                                    <li style="padding:10px 0; border-bottom:1px solid #eee;">✓ 1TB Depolama</li>
                                    <li style="padding:10px 0; border-bottom:1px solid #eee;">✓ 7/24 Destek</li>
                                </ul>
                                <a href="#" style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:15px 30px; border-radius:25px; text-decoration:none; display:inline-block;">Seç</a>
                            </div>
                        </div>
                    </div>
                </section>`
            });
            
            // === PROFESYONEL BLOKLAR ===
            
            // Advanced Hero with Video Background
            bm.add('hero-video', {
                label: '<i class="fa fa-video"></i><div>Video Hero</div>',
                category: 'Profesyonel',
                content: `<section style="height:100vh; position:relative; display:flex; align-items:center; justify-content:center; background:linear-gradient(45deg, #1e3c72, #2a5298); overflow:hidden;">
                    <div style="position:absolute; top:0; left:0; right:0; bottom:0; background:url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>'); opacity:0.3;"></div>
                    <div class="container text-center text-white" style="position:relative; z-index:2;">
                        <h1 style="font-size:4rem; font-weight:900; margin-bottom:2rem; text-shadow:0 4px 8px rgba(0,0,0,0.3);">Next Level Experience</h1>
                        <p style="font-size:1.5rem; margin-bottom:3rem; opacity:0.9; max-width:600px; margin-left:auto; margin-right:auto;">Create stunning websites with our professional page builder</p>
                        <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                            <button class="btn btn-light btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; box-shadow:0 10px 30px rgba(0,0,0,0.2);">Get Started</button>
                            <button class="btn btn-outline-light btn-lg" style="padding:1rem 2.5rem; border-radius:50px; font-weight:600; border:2px solid white;">Learn More</button>
                        </div>
                    </div>
                    <div style="position:absolute; bottom:2rem; left:50%; transform:translateX(-50%); color:white; opacity:0.7;">
                        <i class="fas fa-chevron-down" style="font-size:2rem; animation:bounce 2s infinite;"></i>
                    </div>
                </section>`,
                traits: [
                    {
                        type: 'bootstrap-grid',
                        name: 'class',
                        label: 'Bootstrap Grid'
                    },
                    {
                        type: 'bootstrap-utility',
                        name: 'utility',
                        label: 'Bootstrap Utility'
                    }
                ]
            });
            
            // Statistics Counter
            bm.add('stats-counter', {
                label: '<i class="fa fa-chart-line"></i><div>İstatistik Sayacı</div>',
                category: 'Profesyonel',
                content: `<section style="padding:80px 20px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white;">
                    <div class="container" style="max-width:1200px; margin:0 auto;">
                        <div class="row text-center" style="display:flex; gap:2rem; flex-wrap:wrap;">
                            <div class="col" style="flex:1; min-width:200px;">
                                <div style="font-size:3.5rem; font-weight:900; margin-bottom:1rem; color:#ffd700;">1000+</div>
                                <h3 style="font-size:1.2rem; margin-bottom:0.5rem;">Mutlu Müşteri</h3>
                                <p style="opacity:0.8; margin:0;">Başarılı projeler</p>
                            </div>
                            <div class="col" style="flex:1; min-width:200px;">
                                <div style="font-size:3.5rem; font-weight:900; margin-bottom:1rem; color:#ffd700;">500+</div>
                                <h3 style="font-size:1.2rem; margin-bottom:0.5rem;">Tamamlanan Proje</h3>
                                <p style="opacity:0.8; margin:0;">Kaliteli çözümler</p>
                            </div>
                            <div class="col" style="flex:1; min-width:200px;">
                                <div style="font-size:3.5rem; font-weight:900; margin-bottom:1rem; color:#ffd700;">24/7</div>
                                <h3 style="font-size:1.2rem; margin-bottom:0.5rem;">Destek</h3>
                                <p style="opacity:0.8; margin:0;">Kesintisiz hizmet</p>
                            </div>
                            <div class="col" style="flex:1; min-width:200px;">
                                <div style="font-size:3.5rem; font-weight:900; margin-bottom:1rem; color:#ffd700;">5+</div>
                                <h3 style="font-size:1.2rem; margin-bottom:0.5rem;">Yıllık Deneyim</h3>
                                <p style="opacity:0.8; margin:0;">Profesyonel ekip</p>
                            </div>
                        </div>
                    </div>
                </section>`,
                traits: [
                    {
                        type: 'bootstrap-grid',
                        name: 'class',
                        label: 'Bootstrap Grid'
                    },
                    {
                        type: 'bootstrap-utility',
                        name: 'utility',
                        label: 'Bootstrap Utility'
                    }
                ]
            });
            
            // Testimonial Carousel
            bm.add('testimonial-carousel', {
                label: '<i class="fa fa-quote-left"></i><div>Müşteri Yorumları</div>',
                category: 'Profesyonel',
                content: `<section style="padding:100px 20px; background:#f8f9fa;">
                    <div class="container" style="max-width:1200px; margin:0 auto;">
                        <h2 style="text-align:center; margin-bottom:60px; font-size:2.5rem; color:#333;">Müşterilerimiz Ne Diyor?</h2>
                        <div style="display:flex; gap:2rem; flex-wrap:wrap; justify-content:center;">
                            <div style="background:white; padding:2rem; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:350px; text-align:center;">
                                <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 1.5rem; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-user" style="font-size:2rem; color:white;"></i>
                                </div>
                                <p style="font-style:italic; margin-bottom:1.5rem; color:#666; line-height:1.6;">"Harika bir deneyim! Profesyonel ekiple çalışmak gerçekten fark yaratıyor."</p>
                                <h4 style="color:#333; margin-bottom:0.5rem;">Ahmet Yılmaz</h4>
                                <p style="color:#999; font-size:0.9rem; margin:0;">CEO, TechCorp</p>
                            </div>
                            <div style="background:white; padding:2rem; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:350px; text-align:center;">
                                <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 1.5rem; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-user" style="font-size:2rem; color:white;"></i>
                                </div>
                                <p style="font-style:italic; margin-bottom:1.5rem; color:#666; line-height:1.6;">"Beklentilerimizi aştılar. Kesinlikle tavsiye ederim!"</p>
                                <h4 style="color:#333; margin-bottom:0.5rem;">Elif Demir</h4>
                                <p style="color:#999; font-size:0.9rem; margin:0;">Marketing Director</p>
                            </div>
                            <div style="background:white; padding:2rem; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:350px; text-align:center;">
                                <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 1.5rem; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-user" style="font-size:2rem; color:white;"></i>
                                </div>
                                <p style="font-style:italic; margin-bottom:1.5rem; color:#666; line-height:1.6;">"Mükemmel hizmet ve kaliteli sonuçlar. Teşekkürler!"</p>
                                <h4 style="color:#333; margin-bottom:0.5rem;">Mehmet Kaya</h4>
                                <p style="color:#999; font-size:0.9rem; margin:0;">Founder, StartupX</p>
                            </div>
                        </div>
                    </div>
                </section>`,
                traits: [
                    {
                        type: 'bootstrap-grid',
                        name: 'class',
                        label: 'Bootstrap Grid'
                    },
                    {
                        type: 'bootstrap-utility',
                        name: 'utility',
                        label: 'Bootstrap Utility'
                    }
                ]
            });
            
            // Pricing Table
            bm.add('pricing-table', {
                label: '<i class="fa fa-tags"></i><div>Fiyat Tablosu</div>',
                category: 'Profesyonel',
                content: `<section style="padding:100px 20px; background:#f8f9fa;">
                    <div class="container" style="max-width:1200px; margin:0 auto;">
                        <h2 style="text-align:center; margin-bottom:60px; font-size:2.5rem; color:#333;">Fiyatlarımız</h2>
                        <div style="display:flex; gap:2rem; justify-content:center; flex-wrap:wrap;">
                            <div style="background:white; padding:2.5rem; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); text-align:center; max-width:300px; position:relative;">
                                <h3 style="color:#333; margin-bottom:1rem; font-size:1.5rem;">Temel</h3>
                                <div style="font-size:3rem; font-weight:900; color:#667eea; margin-bottom:1rem;">₺99<span style="font-size:1rem; color:#999;">/ay</span></div>
                                <ul style="list-style:none; padding:0; margin-bottom:2rem; text-align:left;">
                                    <li style="padding:0.5rem 0; border-bottom:1px solid #eee;"><i class="fas fa-check text-success me-2"></i>5 Proje</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid #eee;"><i class="fas fa-check text-success me-2"></i>10GB Depolama</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid #eee;"><i class="fas fa-check text-success me-2"></i>Email Desteği</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid #eee;"><i class="fas fa-check text-success me-2"></i>Temel Özellikler</li>
                                </ul>
                                <button class="btn btn-outline-primary" style="width:100%; padding:0.75rem; border-radius:25px;">Seç</button>
                            </div>
                            <div style="background:linear-gradient(135deg, #667eea, #764ba2); padding:2.5rem; border-radius:15px; box-shadow:0 15px 40px rgba(102, 126, 234, 0.3); text-align:center; max-width:300px; position:relative; color:white; transform:scale(1.05);">
                                <div style="position:absolute; top:-10px; left:50%; transform:translateX(-50%); background:#ff6b6b; color:white; padding:0.5rem 1.5rem; border-radius:15px; font-size:0.9rem; font-weight:600;">POPÜLER</div>
                                <h3 style="margin-bottom:1rem; font-size:1.5rem;">Pro</h3>
                                <div style="font-size:3rem; font-weight:900; margin-bottom:1rem;">₺199<span style="font-size:1rem; opacity:0.8;">/ay</span></div>
                                <ul style="list-style:none; padding:0; margin-bottom:2rem; text-align:left;">
                                    <li style="padding:0.5rem 0; border-bottom:1px solid rgba(255,255,255,0.2);"><i class="fas fa-check me-2"></i>25 Proje</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid rgba(255,255,255,0.2);"><i class="fas fa-check me-2"></i>100GB Depolama</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid rgba(255,255,255,0.2);"><i class="fas fa-check me-2"></i>Öncelikli Destek</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid rgba(255,255,255,0.2);"><i class="fas fa-check me-2"></i>Gelişmiş Özellikler</li>
                                </ul>
                                <button class="btn btn-light" style="width:100%; padding:0.75rem; border-radius:25px; font-weight:600;">Seç</button>
                            </div>
                            <div style="background:white; padding:2.5rem; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); text-align:center; max-width:300px; position:relative;">
                                <h3 style="color:#333; margin-bottom:1rem; font-size:1.5rem;">Kurumsal</h3>
                                <div style="font-size:3rem; font-weight:900; color:#667eea; margin-bottom:1rem;">₺399<span style="font-size:1rem; color:#999;">/ay</span></div>
                                <ul style="list-style:none; padding:0; margin-bottom:2rem; text-align:left;">
                                    <li style="padding:0.5rem 0; border-bottom:1px solid #eee;"><i class="fas fa-check text-success me-2"></i>Sınırsız Proje</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid #eee;"><i class="fas fa-check text-success me-2"></i>1TB Depolama</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid #eee;"><i class="fas fa-check text-success me-2"></i>7/24 Destek</li>
                                    <li style="padding:0.5rem 0; border-bottom:1px solid #eee;"><i class="fas fa-check text-success me-2"></i>Tüm Özellikler</li>
                                </ul>
                                <button class="btn btn-outline-primary" style="width:100%; padding:0.75rem; border-radius:25px;">Seç</button>
                            </div>
                        </div>
                    </div>
                </section>`,
                traits: [
                    {
                        type: 'bootstrap-grid',
                        name: 'class',
                        label: 'Bootstrap Grid'
                    },
                    {
                        type: 'bootstrap-utility',
                        name: 'utility',
                        label: 'Bootstrap Utility'
                    }
                ]
            });
            
            // Contact Form
            bm.add('contact-form', {
                label: '<i class="fa fa-envelope"></i><div>İletişim Formu</div>',
                category: 'Profesyonel',
                content: `<section style="padding:100px 20px; background:linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
                    <div class="container" style="max-width:800px; margin:0 auto;">
                        <div style="background:white; padding:3rem; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,0.1);">
                            <h2 style="text-align:center; margin-bottom:2rem; font-size:2.5rem; color:#333;">İletişime Geçin</h2>
                            <p style="text-align:center; margin-bottom:3rem; color:#666; font-size:1.1rem;">Projeleriniz için bizimle iletişime geçin</p>
                            <form>
                                <div style="display:flex; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
                                    <input type="text" placeholder="Adınız" style="flex:1; min-width:200px; padding:1rem; border:2px solid #eee; border-radius:10px; font-size:1rem;">
                                    <input type="email" placeholder="E-posta" style="flex:1; min-width:200px; padding:1rem; border:2px solid #eee; border-radius:10px; font-size:1rem;">
                                </div>
                                <input type="text" placeholder="Konu" style="width:100%; padding:1rem; border:2px solid #eee; border-radius:10px; font-size:1rem; margin-bottom:1.5rem;">
                                <textarea placeholder="Mesajınız" rows="5" style="width:100%; padding:1rem; border:2px solid #eee; border-radius:10px; font-size:1rem; margin-bottom:2rem; resize:vertical;"></textarea>
                                <button type="submit" style="width:100%; background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:1rem; border:none; border-radius:10px; font-size:1.1rem; font-weight:600; cursor:pointer; transition:transform 0.3s ease;">Mesaj Gönder</button>
                            </form>
                        </div>
                    </div>
                </section>`,
                traits: [
                    {
                        type: 'bootstrap-grid',
                        name: 'class',
                        label: 'Bootstrap Grid'
                    },
                    {
                        type: 'bootstrap-utility',
                        name: 'utility',
                        label: 'Bootstrap Utility'
                    }
                ]
            });
            
            // === ÖZEL BLOKLAR ===
            bm.add('testimonial', {
                label: '<i class="fa fa-quote-left"></i><div>Müşteri Yorumu</div>',
                category: 'Özel',
                content: `<section style="padding:80px 20px; background:#f8f9fa;">
                    <div class="container" style="max-width:800px; margin:0 auto; text-align:center;">
                        <div style="background:white; padding:60px 40px; border-radius:20px; box-shadow:0 15px 35px rgba(0,0,0,0.1); position:relative;">
                            <div style="font-size:4em; color:#667eea; margin-bottom:20px;">"</div>
                            <p style="font-size:1.3em; line-height:1.8; color:#333; margin-bottom:30px; font-style:italic;">Harika bir deneyim yaşadık. Kesinlikle tavsiye ederim!</p>
                            <div style="display:flex; align-items:center; justify-content:center; gap:20px;">
                                <div style="width:60px; height:60px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:18px;">AY</div>
                                <div>
                                    <h4 style="margin:0; color:#333;">Ahmet Yılmaz</h4>
                                    <p style="margin:0; color:#666; font-size:0.9em;">CEO, ABC Şirketi</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>`
            });

            // === DİNAMİK DEĞİŞKENLER ===
            bm.add('oda-tipleri-listesi', {
                label: '<i class="fa fa-bed"></i><div>Oda Tipleri</div>',
                category: 'Değişkenler',
                content: `<div class="dynamic-content" data-variable="oda_tipleri_listesi" style="padding:20px; background:#f8f9fa; border:2px dashed #667eea; border-radius:8px; text-align:center;">
                    <i class="fas fa-bed fa-2x text-primary mb-2"></i>
                    <h5>Oda Tipleri Listesi</h5>
                    <p class="text-muted">Bu alan otomatik olarak oda tiplerini gösterecek</p>
                    <small class="text-info">{{oda_tipleri_listesi}}</small>
                </div>`
            });

            bm.add('rezervasyon-formu', {
                label: '<i class="fa fa-calendar-check"></i><div>Rezervasyon Formu</div>',
                category: 'Değişkenler',
                content: `<div class="dynamic-content" data-variable="rezervasyon_formu" style="padding:20px; background:#f8f9fa; border:2px dashed #667eea; border-radius:8px; text-align:center;">
                    <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                    <h5>Rezervasyon Formu</h5>
                    <p class="text-muted">Bu alan otomatik olarak rezervasyon formunu gösterecek</p>
                    <small class="text-info">{{rezervasyon_formu}}</small>
                </div>`
            });

            bm.add('galeri-resimleri', {
                label: '<i class="fa fa-images"></i><div>Galeri Resimleri</div>',
                category: 'Değişkenler',
                content: `<div class="dynamic-content" data-variable="galeri_resimleri" style="padding:20px; background:#f8f9fa; border:2px dashed #667eea; border-radius:8px; text-align:center;">
                    <i class="fas fa-images fa-2x text-warning mb-2"></i>
                    <h5>Galeri Resimleri</h5>
                    <p class="text-muted">Bu alan otomatik olarak galeri resimlerini gösterecek</p>
                    <small class="text-info">{{galeri_resimleri}}</small>
                </div>`
            });

            bm.add('otel-bilgileri', {
                label: '<i class="fa fa-hotel"></i><div>Otel Bilgileri</div>',
                category: 'Değişkenler',
                content: `<div class="dynamic-content" data-variable="otel_bilgileri" style="padding:20px; background:#f8f9fa; border:2px dashed #667eea; border-radius:8px; text-align:center;">
                    <i class="fas fa-hotel fa-2x text-info mb-2"></i>
                    <h5>Otel Bilgileri</h5>
                    <p class="text-muted">Bu alan otomatik olarak otel bilgilerini gösterecek</p>
                    <small class="text-info">{{otel_bilgileri}}</small>
                </div>`
            });

            bm.add('iletisim-bilgileri', {
                label: '<i class="fa fa-phone"></i><div>İletişim Bilgileri</div>',
                category: 'Değişkenler',
                content: `<div class="dynamic-content" data-variable="iletisim_bilgileri" style="padding:20px; background:#f8f9fa; border:2px dashed #667eea; border-radius:8px; text-align:center;">
                    <i class="fas fa-phone fa-2x text-danger mb-2"></i>
                    <h5>İletişim Bilgileri</h5>
                    <p class="text-muted">Bu alan otomatik olarak iletişim bilgilerini gösterecek</p>
                    <small class="text-info">{{iletisim_bilgileri}}</small>
                </div>`
            });

            bm.add('hizmetler-listesi', {
                label: '<i class="fa fa-concierge-bell"></i><div>Hizmetler Listesi</div>',
                category: 'Değişkenler',
                content: `<div class="dynamic-content" data-variable="hizmetler_listesi" style="padding:20px; background:#f8f9fa; border:2px dashed #667eea; border-radius:8px; text-align:center;">
                    <i class="fas fa-concierge-bell fa-2x text-primary mb-2"></i>
                    <h5>Hizmetler Listesi</h5>
                    <p class="text-muted">Bu alan otomatik olarak hizmetleri gösterecek</p>
                    <small class="text-info">{{hizmetler_listesi}}</small>
                </div>`
            });
            
            bm.add('stats-counter', {
                label: '<i class="fa fa-chart-bar"></i><div>İstatistik Sayacı</div>',
                category: 'Özel',
                content: `<section style="padding:80px 20px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white;">
                    <div class="container" style="max-width:1200px; margin:0 auto;">
                        <div class="row" style="display:flex; gap:40px; justify-content:center; flex-wrap:wrap;">
                            <div class="stat-item" style="text-align:center;">
                                <div style="font-size:3.5em; font-weight:bold; margin-bottom:10px;">1000+</div>
                                <p style="font-size:1.2em; opacity:0.9;">Mutlu Müşteri</p>
                            </div>
                            <div class="stat-item" style="text-align:center;">
                                <div style="font-size:3.5em; font-weight:bold; margin-bottom:10px;">50+</div>
                                <p style="font-size:1.2em; opacity:0.9;">Proje</p>
                            </div>
                            <div class="stat-item" style="text-align:center;">
                                <div style="font-size:3.5em; font-weight:bold; margin-bottom:10px;">5</div>
                                <p style="font-size:1.2em; opacity:0.9;">Yıllık Deneyim</p>
                            </div>
                            <div class="stat-item" style="text-align:center;">
                                <div style="font-size:3.5em; font-weight:bold; margin-bottom:10px;">24/7</div>
                                <p style="font-size:1.2em; opacity:0.9;">Destek</p>
                            </div>
                        </div>
                    </div>
                </section>`
            });

            // console.log('✅ Muhteşem bloklar eklendi!'); // Debug log'u kaldırıldı
        }

        // Şablon Kütüphanesi
        function showTemplateLibrary() {
            const templates = {
                'landing-page': {
                    name: 'Landing Page',
                    description: 'Modern landing page şablonu',
                    content: `
                        <section style="padding:120px 20px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align:center; color:white;">
                            <div class="container" style="max-width:1200px; margin:0 auto;">
                                <h1 style="font-size:3.5em; font-weight:800; margin-bottom:25px;">Harika Ürününüz</h1>
                                <p style="font-size:1.4em; margin-bottom:40px; opacity:0.95;">Modern ve etkileyici bir açıklama</p>
                                <a href="#" style="background:white; color:#667eea; padding:15px 50px; border-radius:50px; text-decoration:none; display:inline-block; font-weight:bold;">Hemen Başla</a>
                            </div>
                        </section>
                        <section style="padding:80px 20px; background:#f8f9fa;">
                            <div class="container" style="max-width:1200px; margin:0 auto;">
                                <h2 style="text-align:center; margin-bottom:60px; font-size:2.5em; color:#333;">Özelliklerimiz</h2>
                                <div style="display:flex; gap:30px; flex-wrap:wrap; justify-content:center;">
                                    <div style="flex:1; min-width:300px; background:white; padding:40px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); text-align:center;">
                                        <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center;">
                                            <i class="fas fa-rocket" style="font-size:2em; color:white;"></i>
                                        </div>
                                        <h3 style="margin-bottom:15px; color:#333;">Hızlı</h3>
                                        <p style="color:#666; line-height:1.6;">Süper hızlı performans</p>
                                    </div>
                                    <div style="flex:1; min-width:300px; background:white; padding:40px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); text-align:center;">
                                        <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center;">
                                            <i class="fas fa-shield-alt" style="font-size:2em; color:white;"></i>
                                        </div>
                                        <h3 style="margin-bottom:15px; color:#333;">Güvenli</h3>
                                        <p style="color:#666; line-height:1.6;">Maksimum güvenlik</p>
                                    </div>
                                    <div style="flex:1; min-width:300px; background:white; padding:40px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); text-align:center;">
                                        <div style="width:80px; height:80px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center;">
                                            <i class="fas fa-mobile-alt" style="font-size:2em; color:white;"></i>
                                        </div>
                                        <h3 style="margin-bottom:15px; color:#333;">Responsive</h3>
                                        <p style="color:#666; line-height:1.6;">Tüm cihazlarda mükemmel</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    `
                },
                'portfolio': {
                    name: 'Portfolio',
                    description: 'Kişisel portfolio şablonu',
                    content: `
                        <section style="padding:80px 20px; background:#f8f9fa;">
                            <div class="container" style="max-width:1200px; margin:0 auto; text-align:center;">
                                <h1 style="font-size:3em; margin-bottom:20px; color:#333;">Portfolio</h1>
                                <p style="font-size:1.2em; color:#666; margin-bottom:60px;">Çalışmalarım</p>
                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:30px;">
                                    <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                                        <div style="width:100%; height:250px; background:linear-gradient(135deg, #667eea, #764ba2); display:flex; align-items:center; justify-content:center; color:white; font-size:24px; font-weight:bold;">Proje 1</div>
                                        <div style="padding:30px;">
                                            <h3 style="margin-bottom:15px; color:#333;">Proje 1</h3>
                                            <p style="color:#666; line-height:1.6;">Proje açıklaması buraya gelecek.</p>
                                        </div>
                                    </div>
                                    <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                                        <div style="width:100%; height:250px; background:linear-gradient(135deg, #764ba2, #667eea); display:flex; align-items:center; justify-content:center; color:white; font-size:24px; font-weight:bold;">Proje 2</div>
                                        <div style="padding:30px;">
                                            <h3 style="margin-bottom:15px; color:#333;">Proje 2</h3>
                                            <p style="color:#666; line-height:1.6;">Proje açıklaması buraya gelecek.</p>
                                        </div>
                                    </div>
                                    <div style="background:white; border-radius:15px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                                        <div style="width:100%; height:250px; background:linear-gradient(135deg, #667eea, #764ba2); display:flex; align-items:center; justify-content:center; color:white; font-size:24px; font-weight:bold;">Proje 3</div>
                                        <div style="padding:30px;">
                                            <h3 style="margin-bottom:15px; color:#333;">Proje 3</h3>
                                            <p style="color:#666; line-height:1.6;">Proje açıklaması buraya gelecek.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    `
                },
                'about-us': {
                    name: 'Hakkımızda',
                    description: 'Şirket hakkında sayfa şablonu',
                    content: `
                        <section style="padding:80px 20px; background:white;">
                            <div class="container" style="max-width:1200px; margin:0 auto;">
                                <div style="display:flex; gap:60px; align-items:center; flex-wrap:wrap;">
                                    <div style="flex:1; min-width:300px;">
                                        <h1 style="font-size:2.5em; margin-bottom:30px; color:#333;">Hakkımızda</h1>
                                        <p style="font-size:1.1em; line-height:1.8; color:#666; margin-bottom:20px;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                                        <p style="font-size:1.1em; line-height:1.8; color:#666; margin-bottom:30px;">Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
                                        <a href="#" style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:15px 30px; border-radius:25px; text-decoration:none; display:inline-block;">Daha Fazla</a>
                                    </div>
                                    <div style="flex:1; min-width:300px;">
                                        <div style="width:100%; height:400px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:15px; box-shadow:0 15px 35px rgba(0,0,0,0.1); display:flex; align-items:center; justify-content:center; color:white; font-size:28px; font-weight:bold;">Hakkımızda</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    `
                }
            };

            // Şablon seçim modalı oluştur
            let modalHtml = `
                <div class="modal fade" id="templateModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Şablon Kütüphanesi</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">`;
            
            Object.keys(templates).forEach(key => {
                const template = templates[key];
                modalHtml += `
                    <div class="col-md-6 mb-4">
                        <div class="card h-100" style="cursor:pointer;" onclick="addTemplate('${key}')">
                            <div class="card-body text-center">
                                <h5 class="card-title">${template.name}</h5>
                                <p class="card-text text-muted">${template.description}</p>
                                <button class="btn btn-primary">Kullan</button>
                            </div>
                        </div>
                    </div>`;
            });
            
            modalHtml += `
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            
            // Modal'ı ekle
            if (!document.getElementById('templateModal')) {
                document.body.insertAdjacentHTML('beforeend', modalHtml);
            }
            
            // Modal'ı göster
            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }

        // Şablon ekle
        function addTemplate(templateName) {
            const templates = {
                'landing-page': `<section style="padding:120px 20px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align:center; color:white;"><div class="container" style="max-width:1200px; margin:0 auto;"><h1 style="font-size:3.5em; font-weight:800; margin-bottom:25px;">Harika Ürününüz</h1><p style="font-size:1.4em; margin-bottom:40px; opacity:0.95;">Modern ve etkileyici bir açıklama</p><a href="#" style="background:white; color:#667eea; padding:15px 50px; border-radius:50px; text-decoration:none; display:inline-block; font-weight:bold;">Hemen Başla</a></div></section>`,
                'portfolio': `<section style="padding:80px 20px; background:#f8f9fa;"><div class="container" style="max-width:1200px; margin:0 auto; text-align:center;"><h1 style="font-size:3em; margin-bottom:20px; color:#333;">Portfolio</h1><p style="font-size:1.2em; color:#666; margin-bottom:60px;">Çalışmalarım</p></div></section>`,
                'about-us': `<section style="padding:80px 20px; background:white;"><div class="container" style="max-width:1200px; margin:0 auto;"><h1 style="font-size:2.5em; margin-bottom:30px; color:#333;">Hakkımızda</h1><p style="font-size:1.1em; line-height:1.8; color:#666;">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p></div></section>`
            };
            
            if (templates[templateName]) {
                editor.addComponents(templates[templateName]);
                showToast('success', 'Şablon eklendi!');
                bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
            }
        }

        // GrapesJS başlat
        $(document).ready(function() {
            console.log('Page Builder yükleniyor...');
            console.log('GrapesJS mevcut mu?', typeof grapesjs !== 'undefined');
            console.log('jQuery mevcut mu?', typeof $ !== 'undefined');
            
            if (typeof grapesjs === 'undefined') {
                showToast('error', 'GrapesJS yüklenemedi!');
                console.error('GrapesJS yüklenemedi!');
                return;
            }
            
            // Accordion değişiminde editörleri başlat
            $('#pageBuilderAccordion').on('shown.bs.collapse', function (e) {
                const target = $(e.target).attr('id');
                // console.log('Accordion açıldı:', target); // Debug log'u kaldırıldı
                
                if (target === 'content-panel') {
                    // İçerik editörünü başlat (eğer yoksa)
                    if (!editor) {
                        // console.log('Content editor başlatılıyor...'); // Debug log'u kaldırıldı
                        initContentEditor();
                    } else {
                        // Editör varsa blokları yenile
                        // console.log('Bloklar yenileniyor...'); // Debug log'u kaldırıldı
                        addUltimateBlocks();
                    }
                } else if (target === 'layout-panel') {
                    console.log('Layout editor başlatılıyor...');
                    initLayoutEditors();
                } else if (target === 'template-panel') {
                    console.log('Template settings yükleniyor...');
                    loadTemplateSettings();
                }
            });
            
            // İlk açılışta content panel'i aç (daha hızlı)
            setTimeout(function() {
                // console.log('İlk açılış - content panel açılıyor...'); // Debug log'u kaldırıldı
                $('#content-panel').collapse('show');
            }, 100); // 500ms'den 100ms'ye düşürdük

            // İlk tab aktif olduğunda editörü başlat (daha hızlı)
            setTimeout(function() {
                if (!editor) {
                    initContentEditor();
                }
            }, 300); // 100ms'den 300ms'ye artırdık (editor için daha güvenli)
            
            // Template seçimi değiştiğinde
            document.getElementById('pageTemplate').addEventListener('change', function() {
                const template = this.value;
                if (template === 'premium-hotel') {
                    showToast('info', 'Premium Hotel template seçildi. Sadece sayfa içeriği düzenlenebilir. Header ve footer otomatik yüklenir.');
                } else if (template === 'custom') {
                    showToast('info', 'Özel sayfa seçildi. Tam sayfa düzenlenebilir.');
                }
            });
        });
        
        // Template içeriği yükle
        function loadTemplateContent(template) {
            if (!template || template === '') return;
            
            showToast('info', 'Template içeriği yükleniyor...');
            
            $.post('ajax/load-template-content.php', {
                template: template,
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success && response.content) {
                    editor.setComponents(response.content);
                    showToast('success', 'Template içeriği yüklendi!');
                } else {
                    showToast('warning', 'Template içeriği yüklenemedi: ' + (response.message || 'Bilinmeyen hata'));
                }
            }).fail(function() {
                showToast('error', 'Template içeriği yüklenirken hata oluştu!');
            });
        }

        // === HİBRİT SİSTEM FONKSİYONLARI ===
        
        // Template ayarlarını kaydet
        function saveTemplateSettings() {
            const settings = {
                otel_adi: document.getElementById('otelAdi').value,
                logo_url: document.getElementById('logoUrl').value,
                otel_adres: document.getElementById('otelAdres').value,
                otel_telefon: document.getElementById('otelTelefon').value,
                facebook_url: document.getElementById('facebookUrl').value,
                twitter_url: document.getElementById('twitterUrl').value,
                instagram_url: document.getElementById('instagramUrl').value,
                pinterest_url: document.getElementById('pinterestUrl').value,
                linkedin_url: document.getElementById('linkedinUrl').value
            };

            showToast('info', 'Template ayarları kaydediliyor...');

            $.post('ajax/save-template-settings.php', {
                settings: JSON.stringify(settings),
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    showToast('success', 'Template ayarları kaydedildi!');
                } else {
                    showToast('error', 'Kaydetme hatası: ' + response.message);
                }
            }).fail(function() {
                showToast('error', 'Sunucu hatası!');
            });
        }

        // Header kaydet
        function saveHeader() {
            if (!headerEditor) {
                showToast('error', 'Header editörü bulunamadı!');
                return;
            }

            const headerHtml = headerEditor.getHtml();
            const headerCss = headerEditor.getCss();

            showToast('info', 'Header kaydediliyor...');

            $.post('ajax/save-layout-component.php', {
                component: 'header',
                html: headerHtml,
                css: headerCss,
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    showToast('success', 'Header kaydedildi!');
                } else {
                    showToast('error', 'Header kaydetme hatası: ' + response.message);
                }
            }).fail(function() {
                showToast('error', 'Sunucu hatası!');
            });
        }

        // Footer kaydet
        function saveFooter() {
            if (!footerEditor) {
                showToast('error', 'Footer editörü bulunamadı!');
                return;
            }

            const footerHtml = footerEditor.getHtml();
            const footerCss = footerEditor.getCss();

            showToast('info', 'Footer kaydediliyor...');

            $.post('ajax/save-layout-component.php', {
                component: 'footer',
                html: footerHtml,
                css: footerCss,
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    showToast('success', 'Footer kaydedildi!');
                } else {
                    showToast('error', 'Footer kaydetme hatası: ' + response.message);
                }
            }).fail(function() {
                showToast('error', 'Sunucu hatası!');
            });
        }

        // Header sıfırla
        function resetHeader() {
            if (confirm('Header\'ı varsayılan haline sıfırlamak istediğinizden emin misiniz?')) {
                loadDefaultHeader();
                showToast('info', 'Header sıfırlandı!');
            }
        }

        // Footer sıfırla
        function resetFooter() {
            if (confirm('Footer\'ı varsayılan haline sıfırlamak istediğinizden emin misiniz?')) {
                loadDefaultFooter();
                showToast('info', 'Footer sıfırlandı!');
            }
        }

        // Varsayılan header yükle
        function loadDefaultHeader() {
            if (headerEditor) {
                const defaultHeader = `
                    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
                        <div class="container">
                            <a class="navbar-brand" href="/">
                                <img src="{{logo_url}}" alt="{{otel_adi}}" height="40">
                            </a>
                            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                                <span class="navbar-toggler-icon"></span>
                            </button>
                            <div class="collapse navbar-collapse" id="navbarNav">
                                <ul class="navbar-nav ms-auto">
                                    <li class="nav-item">
                                        <a class="nav-link" href="/">Ana Sayfa</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="/odalar">Odalar</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="/hizmetler">Hizmetler</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="/iletisim">İletişim</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </nav>
                `;
                headerEditor.setComponents(defaultHeader);
            }
        }

        // Varsayılan footer yükle
        function loadDefaultFooter() {
            if (footerEditor) {
                const defaultFooter = `
                    <footer class="bg-dark text-light py-5">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-4">
                                    <h5>{{otel_adi}}</h5>
                                    <p>{{otel_adres}}</p>
                                    <p>Tel: {{otel_telefon}}</p>
                                </div>
                                <div class="col-md-4">
                                    <h5>Hızlı Linkler</h5>
                                    <ul class="list-unstyled">
                                        <li><a href="/" class="text-light">Ana Sayfa</a></li>
                                        <li><a href="/odalar" class="text-light">Odalar</a></li>
                                        <li><a href="/hizmetler" class="text-light">Hizmetler</a></li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h5>Sosyal Medya</h5>
                                    <div class="d-flex gap-2">
                                        <a href="{{facebook_url}}" class="text-light"><i class="fab fa-facebook"></i></a>
                                        <a href="{{twitter_url}}" class="text-light"><i class="fab fa-twitter"></i></a>
                                        <a href="{{instagram_url}}" class="text-light"><i class="fab fa-instagram"></i></a>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-4">
                            <div class="text-center">
                                <p>&copy; 2024 {{otel_adi}}. Tüm hakları saklıdır.</p>
                            </div>
                        </div>
                    </footer>
                `;
                footerEditor.setComponents(defaultFooter);
            }
        }

        // İçerik editörünü başlat
        function initContentEditor() {
            // console.log('initContentEditor çağrıldı'); // Debug log'u kaldırıldı
            
            // Container'ın var olduğundan emin ol
            if (!document.getElementById('gjs')) {
                console.error('GrapesJS container bulunamadı!');
                return;
            }
            
            // console.log('GrapesJS editor başlatılıyor...'); // Debug log'u kaldırıldı
            editor = grapesjs.init({
                container: '#gjs',
                height: 'calc(100vh - 300px)',
                storageManager: false,
                // Performans optimizasyonu
                avoidInlineStyle: false,
                showOffsets: false,
                showBorders: false,
                canvas: {
                    styles: [
                        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
                        // Font Awesome ve Animate.css'i kaldırdık - performans için
                    ]
                },
                plugins: ['gjs-blocks-basic', 'gjs-plugin-forms', 'gjs-plugin-export', 'gjs-plugin-code'],
                pluginsOpts: {
                    'gjs-plugin-code': {
                        // Kod editörü ayarları
                        modalTitle: 'Kod Düzenleyici',
                        codeViewOptions: {
                            theme: 'hopscotch',
                            readOnly: false,
                            autoBeautify: true,
                            autoCloseTags: true,
                            autoCloseBrackets: true,
                            lineWrapping: true,
                            styleActiveLine: true,
                            smartIndent: true
                        }
                    }
                },
                blockManager: {
                    appendTo: false
                },
                layerManager: {
                    appendTo: false
                },
                traitManager: {
                    appendTo: '.traits-container'
                },
                selectorManager: {
                    appendTo: false
                }
            });

            // Blokları ekle
            addUltimateBlocks();

            // Özel trait'leri ekle
            addCustomTraits();

            // Kod editörü butonlarını ekle
            addCodeEditorButtons();

            // Mevcut içeriği yükle
            <?php if ($pageData): ?>
            editor.setComponents(<?php echo json_encode($pageData['page_content'] ?? ''); ?>);
            <?php endif; ?>

            showToast('success', 'İçerik editörü hazır! 🚀');
        }

        // Kod editörü butonlarını ekle
        function addCodeEditorButtons() {
            const panelManager = editor.Panels;
            
            // HTML Kod Editörü butonu
            panelManager.addButton('options', {
                id: 'edit-html',
                className: 'fa fa-code',
                command: 'open-code-editor',
                attributes: { title: 'HTML Kodunu Düzenle' }
            });
            
            // CSS Kod Editörü butonu
            panelManager.addButton('options', {
                id: 'edit-css',
                className: 'fa fa-paint-brush',
                command: 'open-css-editor',
                attributes: { title: 'CSS Kodunu Düzenle' }
            });
            
            // Kod editörü komutları
            editor.Commands.add('open-code-editor', {
                run: function(editor, sender) {
                    const html = editor.getHtml();
                    const css = editor.getCss();
                    
                    // Modal oluştur
                    const modal = createCodeEditorModal('HTML Kod Düzenleyici', html, css, 'html');
                    modal.show();
                }
            });
            
            editor.Commands.add('open-css-editor', {
                run: function(editor, sender) {
                    const html = editor.getHtml();
                    const css = editor.getCss();
                    
                    // Modal oluştur
                    const modal = createCodeEditorModal('CSS Kod Düzenleyici', html, css, 'css');
                    modal.show();
                }
            });
        }

        // Kod editörü modal'ı oluştur
        function createCodeEditorModal(title, html, css, type) {
            // Mevcut modal'ı kaldır
            const existingModal = document.getElementById('codeEditorModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            const modalHtml = `
                <div class="modal fade" id="codeEditorModal" tabindex="-1" aria-labelledby="codeEditorModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="codeEditorModalLabel">
                                    <i class="fas fa-code me-2"></i>${title}
                                </h5>
                                <div class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    F11: Tam ekran | Ctrl+/: Yorum | Ctrl+Z: Geri al | Satır kaydırma: Açık
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="btn-group mb-3" role="group">
                                            <button type="button" class="btn btn-outline-primary active" id="htmlTab">
                                                <i class="fas fa-code me-1"></i>HTML
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" id="cssTab">
                                                <i class="fas fa-paint-brush me-1"></i>CSS
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div id="htmlEditor" class="code-editor" style="display: block;">
                                            <textarea id="htmlCode">${html}</textarea>
                                        </div>
                                        <div id="cssEditor" class="code-editor" style="display: none;">
                                            <textarea id="cssCode">${css}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" id="formatCode">
                                    <i class="fas fa-magic me-1"></i>Formatla
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>İptal
                                </button>
                                <button type="button" class="btn btn-primary" id="applyCode">
                                    <i class="fas fa-check me-1"></i>Uygula
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Modal'ı DOM'a ekle
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // CodeMirror editörlerini başlat
            const htmlEditor = CodeMirror.fromTextArea(document.getElementById('htmlCode'), {
                mode: 'xml',
                theme: 'monokai',
                lineNumbers: true,
                lineWrapping: true, // Satır kaydırmayı aç
                wrapLineLength: 80, // Satır uzunluğu limiti
                autoCloseTags: true,
                autoCloseBrackets: true,
                styleActiveLine: true,
                smartIndent: true,
                tabSize: 4, // Tab boyutunu artır
                indentUnit: 4, // Girinti boyutunu artır
                indentWithTabs: true, // Tab ile girinti
                electricChars: true,
                matchBrackets: true,
                matchTags: true,
                foldGutter: true,
                gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
                extraKeys: {
                    'Ctrl-Space': 'autocomplete',
                    'F11': function(cm) {
                        cm.setOption('fullScreen', !cm.getOption('fullScreen'));
                    },
                    'Esc': function(cm) {
                        if (cm.getOption('fullScreen')) cm.setOption('fullScreen', false);
                    },
                    'Ctrl-/': 'toggleComment',
                    'Ctrl-A': 'selectAll',
                    'Ctrl-Z': 'undo',
                    'Ctrl-Y': 'redo'
                }
            });
            
            const cssEditor = CodeMirror.fromTextArea(document.getElementById('cssCode'), {
                mode: 'css',
                theme: 'monokai',
                lineNumbers: true,
                lineWrapping: true, // Satır kaydırmayı aç
                wrapLineLength: 80, // Satır uzunluğu limiti
                autoCloseBrackets: true,
                styleActiveLine: true,
                smartIndent: true,
                tabSize: 4, // Tab boyutunu artır
                indentUnit: 4, // Girinti boyutunu artır
                indentWithTabs: true, // Tab ile girinti
                electricChars: true,
                matchBrackets: true,
                foldGutter: true,
                gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
                extraKeys: {
                    'Ctrl-Space': 'autocomplete',
                    'F11': function(cm) {
                        cm.setOption('fullScreen', !cm.getOption('fullScreen'));
                    },
                    'Esc': function(cm) {
                        if (cm.getOption('fullScreen')) cm.setOption('fullScreen', false);
                    },
                    'Ctrl-/': 'toggleComment',
                    'Ctrl-A': 'selectAll',
                    'Ctrl-Z': 'undo',
                    'Ctrl-Y': 'redo'
                }
            });
            
            // Tab değiştirme
            document.getElementById('htmlTab').addEventListener('click', function() {
                document.getElementById('htmlTab').classList.add('active');
                document.getElementById('cssTab').classList.remove('active');
                document.getElementById('htmlEditor').style.display = 'block';
                document.getElementById('cssEditor').style.display = 'none';
                htmlEditor.refresh();
            });
            
            document.getElementById('cssTab').addEventListener('click', function() {
                document.getElementById('cssTab').classList.add('active');
                document.getElementById('htmlTab').classList.remove('active');
                document.getElementById('htmlEditor').style.display = 'none';
                document.getElementById('cssEditor').style.display = 'block';
                cssEditor.refresh();
            });
            
            // Format butonu
            document.getElementById('formatCode').addEventListener('click', function() {
                const currentHtml = htmlEditor.getValue();
                const currentCss = cssEditor.getValue();
                
                // HTML'i formatla
                const formattedHtml = formatHtml(currentHtml);
                htmlEditor.setValue(formattedHtml);
                
                // CSS'i formatla
                const formattedCss = formatCss(currentCss);
                cssEditor.setValue(formattedCss);
                
                showToast('info', 'Kod formatlandı!');
            });
            
            // Uygula butonu
            document.getElementById('applyCode').addEventListener('click', function() {
                let newHtml = htmlEditor.getValue();
                let newCss = cssEditor.getValue();
                
                // HTML'i düzenle (basit formatlama)
                newHtml = formatHtml(newHtml);
                
                // CSS'i düzenle (basit formatlama)
                newCss = formatCss(newCss);
                
                // GrapesJS'e uygula
                editor.setComponents(newHtml);
                editor.setStyle(newCss);
                
                // Modal'ı kapat
                const modal = bootstrap.Modal.getInstance(document.getElementById('codeEditorModal'));
                modal.hide();
                
                showToast('success', 'Kod başarıyla uygulandı!');
            });
            
            // Modal'ı göster
            return new bootstrap.Modal(document.getElementById('codeEditorModal'));
        }

        // HTML formatlama fonksiyonu
        function formatHtml(html) {
            // Boş satırları temizle
            html = html.replace(/\n\s*\n/g, '\n');
            
            // Fazla boşlukları temizle
            html = html.replace(/\s+/g, ' ');
            
            // Tag'lar arasına satır sonu ekle
            html = html.replace(/></g, '>\n<');
            
            // Girinti ekle
            let lines = html.split('\n');
            let indent = 0;
            let formatted = [];
            
            for (let line of lines) {
                line = line.trim();
                if (!line) continue;
                
                // Kapanış tag'ı ise girintiyi azalt
                if (line.startsWith('</')) {
                    indent = Math.max(0, indent - 1);
                }
                
                // Girinti ekle
                formatted.push('    '.repeat(indent) + line);
                
                // Açılış tag'ı ise girintiyi artır (self-closing değilse)
                if (line.startsWith('<') && !line.startsWith('</') && !line.endsWith('/>') && !line.includes('</')) {
                    indent++;
                }
            }
            
            return formatted.join('\n');
        }

        // CSS formatlama fonksiyonu
        function formatCss(css) {
            // Boş satırları temizle
            css = css.replace(/\n\s*\n/g, '\n');
            
            // Fazla boşlukları temizle
            css = css.replace(/\s+/g, ' ');
            
            // Selector'lar arasına satır sonu ekle
            css = css.replace(/}/g, '}\n');
            css = css.replace(/{/g, ' {\n');
            css = css.replace(/;/g, ';\n');
            
            // Girinti ekle
            let lines = css.split('\n');
            let indent = 0;
            let formatted = [];
            
            for (let line of lines) {
                line = line.trim();
                if (!line) continue;
                
                // Kapanış parantezi ise girintiyi azalt
                if (line === '}') {
                    indent = Math.max(0, indent - 1);
                }
                
                // Girinti ekle
                formatted.push('    '.repeat(indent) + line);
                
                // Açılış parantezi ise girintiyi artır
                if (line.endsWith('{')) {
                    indent++;
                }
            }
            
            return formatted.join('\n');
        }

        // Özel trait'leri ekle
        function addCustomTraits() {
            const tm = editor.TraitManager;
            
            // === BOOTSTRAP TRAITS ===
            
            // Margin/Padding trait'leri
            tm.addType('spacing', {
                events: {
                    'change': 'onChange'
                },
                onValueChange() {
                    const { model, el } = this;
                    const value = model.get('value');
                    const type = model.get('type'); // margin veya padding
                    const direction = model.get('direction'); // top, right, bottom, left, all
            
                    if (direction === 'all') {
                        model.get('target').setStyle(`${type}`, value);
                    } else {
                        model.get('target').setStyle(`${type}-${direction}`, value);
                    }
                },
                createInput({ trait }) {
                    const el = document.createElement('div');
                    el.innerHTML = `
                        <div class="row g-1">
                            <div class="col-6">
                                <select class="form-select form-select-sm" data-type>
                                    <option value="margin">Margin</option>
                                    <option value="padding">Padding</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select class="form-select form-select-sm" data-direction>
                                    <option value="all">Tümü</option>
                                    <option value="top">Üst</option>
                                    <option value="right">Sağ</option>
                                    <option value="bottom">Alt</option>
                                    <option value="left">Sol</option>
                                </select>
                            </div>
                            <div class="col-12 mt-1">
                                <input type="text" class="form-control form-control-sm" placeholder="0px, 1rem, auto" data-value>
                            </div>
                        </div>
                    `;
                    return el;
                }
            });

            // Bootstrap Grid trait'i
            tm.addType('bootstrap-grid', {
                events: {
                    'change': 'onChange'
                },
                onValueChange() {
                    const { model } = this;
                    const target = model.get('target');
                    const value = model.get('value');
                    
                    // Mevcut Bootstrap class'larını temizle
                    const classes = target.getClasses();
                    classes.forEach(cls => {
                        if (cls.includes('col-') || cls.includes('row')) {
                            target.removeClass(cls);
                        }
                    });
                    
                    // Yeni class'ı ekle
                    if (value) {
                        target.addClass(value);
                    }
                },
                createInput({ trait }) {
                    const el = document.createElement('div');
                    el.innerHTML = `
                        <select class="form-select form-select-sm">
                            <option value="">Grid Seçin</option>
                            <option value="row">Row (Satır)</option>
                            <option value="col">Col (Sütun)</option>
                            <option value="col-1">Col-1</option>
                            <option value="col-2">Col-2</option>
                            <option value="col-3">Col-3</option>
                            <option value="col-4">Col-4</option>
                            <option value="col-5">Col-5</option>
                            <option value="col-6">Col-6</option>
                            <option value="col-7">Col-7</option>
                            <option value="col-8">Col-8</option>
                            <option value="col-9">Col-9</option>
                            <option value="col-10">Col-10</option>
                            <option value="col-11">Col-11</option>
                            <option value="col-12">Col-12</option>
                            <option value="col-auto">Col-auto</option>
                            <option value="col-sm-1">Col-sm-1</option>
                            <option value="col-sm-2">Col-sm-2</option>
                            <option value="col-sm-3">Col-sm-3</option>
                            <option value="col-sm-4">Col-sm-4</option>
                            <option value="col-sm-5">Col-sm-5</option>
                            <option value="col-sm-6">Col-sm-6</option>
                            <option value="col-sm-7">Col-sm-7</option>
                            <option value="col-sm-8">Col-sm-8</option>
                            <option value="col-sm-9">Col-sm-9</option>
                            <option value="col-sm-10">Col-sm-10</option>
                            <option value="col-sm-11">Col-sm-11</option>
                            <option value="col-sm-12">Col-sm-12</option>
                            <option value="col-md-1">Col-md-1</option>
                            <option value="col-md-2">Col-md-2</option>
                            <option value="col-md-3">Col-md-3</option>
                            <option value="col-md-4">Col-md-4</option>
                            <option value="col-md-5">Col-md-5</option>
                            <option value="col-md-6">Col-md-6</option>
                            <option value="col-md-7">Col-md-7</option>
                            <option value="col-md-8">Col-md-8</option>
                            <option value="col-md-9">Col-md-9</option>
                            <option value="col-md-10">Col-md-10</option>
                            <option value="col-md-11">Col-md-11</option>
                            <option value="col-md-12">Col-md-12</option>
                            <option value="col-lg-1">Col-lg-1</option>
                            <option value="col-lg-2">Col-lg-2</option>
                            <option value="col-lg-3">Col-lg-3</option>
                            <option value="col-lg-4">Col-lg-4</option>
                            <option value="col-lg-5">Col-lg-5</option>
                            <option value="col-lg-6">Col-lg-6</option>
                            <option value="col-lg-7">Col-lg-7</option>
                            <option value="col-lg-8">Col-lg-8</option>
                            <option value="col-lg-9">Col-lg-9</option>
                            <option value="col-lg-10">Col-lg-10</option>
                            <option value="col-lg-11">Col-lg-11</option>
                            <option value="col-lg-12">Col-lg-12</option>
                        </select>
                    `;
                    return el;
                }
            });

            // Bootstrap Utility trait'i
            tm.addType('bootstrap-utility', {
                events: {
                    'change': 'onChange'
                },
                onValueChange() {
                    const { model } = this;
                    const target = model.get('target');
                    const value = model.get('value');
                    const type = model.get('type');
                    
                    // Mevcut utility class'larını temizle
                    const classes = target.getClasses();
                    classes.forEach(cls => {
                        if (cls.startsWith(type + '-')) {
                            target.removeClass(cls);
                        }
                    });
                    
                    // Yeni class'ı ekle
                    if (value) {
                        target.addClass(`${type}-${value}`);
                    }
                },
                createInput({ trait }) {
                    const el = document.createElement('div');
                    el.innerHTML = `
                        <div class="row g-1">
                            <div class="col-6">
                                <select class="form-select form-select-sm" data-type>
                                    <option value="text">Text</option>
                                    <option value="bg">Background</option>
                                    <option value="border">Border</option>
                                    <option value="rounded">Rounded</option>
                                    <option value="shadow">Shadow</option>
                                    <option value="d">Display</option>
                                    <option value="position">Position</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select class="form-select form-select-sm" data-value>
                                    <option value="">Seçin</option>
                                    <option value="primary">Primary</option>
                                    <option value="secondary">Secondary</option>
                                    <option value="success">Success</option>
                                    <option value="danger">Danger</option>
                                    <option value="warning">Warning</option>
                                    <option value="info">Info</option>
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                    <option value="white">White</option>
                                    <option value="muted">Muted</option>
                                    <option value="center">Center</option>
                                    <option value="start">Start</option>
                                    <option value="end">End</option>
                                    <option value="none">None</option>
                                    <option value="sm">Small</option>
                                    <option value="lg">Large</option>
                                    <option value="0">0</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                            </div>
                        </div>
                    `;
                    return el;
                }
            });

            // Flexbox trait'i
            tm.addType('flexbox', {
                events: {
                    'change': 'onChange'
                },
                onValueChange() {
                    const { model } = this;
                    const target = model.get('target');
                    const value = model.get('value');
                    
                    // Mevcut flex class'larını temizle
                    const classes = target.getClasses();
                    classes.forEach(cls => {
                        if (cls.startsWith('d-flex') || cls.startsWith('justify-content-') || 
                            cls.startsWith('align-items-') || cls.startsWith('flex-')) {
                            target.removeClass(cls);
                        }
                    });
                    
                    // Yeni class'ları ekle
                    if (value) {
                        value.split(' ').forEach(cls => {
                            if (cls) target.addClass(cls);
                        });
                    }
                },
                createInput({ trait }) {
                    const el = document.createElement('div');
                    el.innerHTML = `
                        <div class="row g-1">
                            <div class="col-12">
                                <label class="form-label form-label-sm">Display</label>
                                <select class="form-select form-select-sm" data-flex-display>
                                    <option value="">Normal</option>
                                    <option value="d-flex">Flex</option>
                                    <option value="d-inline-flex">Inline Flex</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Justify Content</label>
                                <select class="form-select form-select-sm" data-justify>
                                    <option value="">Varsayılan</option>
                                    <option value="justify-content-start">Start</option>
                                    <option value="justify-content-end">End</option>
                                    <option value="justify-content-center">Center</option>
                                    <option value="justify-content-between">Between</option>
                                    <option value="justify-content-around">Around</option>
                                    <option value="justify-content-evenly">Evenly</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Align Items</label>
                                <select class="form-select form-select-sm" data-align>
                                    <option value="">Varsayılan</option>
                                    <option value="align-items-start">Start</option>
                                    <option value="align-items-end">End</option>
                                    <option value="align-items-center">Center</option>
                                    <option value="align-items-baseline">Baseline</option>
                                    <option value="align-items-stretch">Stretch</option>
                                </select>
                            </div>
                        </div>
                    `;
                    return el;
                }
            });
        }

        // Layout editörlerini başlat
        function initLayoutEditors() {
            // Header editörü
            if (!headerEditor && document.getElementById('header-editor')) {
                headerEditor = grapesjs.init({
                    container: '#header-editor',
                    height: '400px',
                    width: '100%',
                    plugins: [],
                    pluginsOpts: {},
                    blockManager: {
                        appendTo: '.header-blocks-container'
                    },
                    layerManager: {
                        appendTo: '.header-layers-container'
                    },
                    traitManager: {
                        appendTo: '.header-traits-container'
                    },
                    selectorManager: {
                        appendTo: '.header-selector-container'
                    },
                    storageManager: false,
                    canvas: {
                        styles: [
                            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
                            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
                        ]
                    }
                });
                
                // Header blokları ekle
                addHeaderBlocks();
                
                // Header yükle (varsayılan veya kaydedilmiş)
                loadHeader();
            }

            // Footer editörü
            if (!footerEditor && document.getElementById('footer-editor')) {
                footerEditor = grapesjs.init({
                    container: '#footer-editor',
                    height: '400px',
                    width: '100%',
                    plugins: [],
                    pluginsOpts: {},
                    blockManager: {
                        appendTo: '.footer-blocks-container'
                    },
                    layerManager: {
                        appendTo: '.footer-layers-container'
                    },
                    traitManager: {
                        appendTo: '.footer-traits-container'
                    },
                    selectorManager: {
                        appendTo: '.footer-selector-container'
                    },
                    storageManager: false,
                    canvas: {
                        styles: [
                            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
                            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
                        ]
                    }
                });
                
                // Footer blokları ekle
                addFooterBlocks();
                
                // Footer yükle (varsayılan veya kaydedilmiş)
                loadFooter();
            }
        }

        // Header blokları ekle
        function addHeaderBlocks() {
            if (!headerEditor) return;
            
            const blockManager = headerEditor.BlockManager;
            
            // Header özel blokları
            blockManager.add('header-logo', {
                label: 'Logo',
                content: `
                    <div class="header-logo">
                        <img src="https://via.placeholder.com/150x50/007bff/ffffff?text=LOGO" alt="Logo" style="max-height: 50px;">
                    </div>
                `,
                category: 'Header',
                attributes: { class: 'fa fa-image' }
            });
            
            blockManager.add('header-menu', {
                label: 'Menü',
                content: `
                    <nav class="navbar navbar-expand-lg">
                        <div class="container-fluid">
                            <div class="navbar-nav">
                                <a class="nav-link active" href="#">Ana Sayfa</a>
                                <a class="nav-link" href="#">Hakkımızda</a>
                                <a class="nav-link" href="#">Hizmetler</a>
                                <a class="nav-link" href="#">İletişim</a>
                            </div>
                        </div>
                    </nav>
                `,
                category: 'Header',
                attributes: { class: 'fa fa-bars' }
            });
            
            blockManager.add('header-contact', {
                label: 'İletişim Bilgileri',
                content: `
                    <div class="header-contact">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>+90 (212) 123 45 67</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>info@otel.com</span>
                        </div>
                    </div>
                `,
                category: 'Header',
                attributes: { class: 'fa fa-phone' }
            });
            
            blockManager.add('header-social', {
                label: 'Sosyal Medya',
                content: `
                    <div class="header-social">
                        <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                    </div>
                `,
                category: 'Header',
                attributes: { class: 'fa fa-share-alt' }
            });
        }

        // Footer blokları ekle
        function addFooterBlocks() {
            if (!footerEditor) return;
            
            const blockManager = footerEditor.BlockManager;
            
            // Footer özel blokları
            blockManager.add('footer-logo', {
                label: 'Footer Logo',
                content: `
                    <div class="footer-logo">
                        <img src="https://via.placeholder.com/150x50/ffffff/007bff?text=LOGO" alt="Logo" style="max-height: 50px;">
                        <p class="mt-2">Premium konaklama deneyimi</p>
                    </div>
                `,
                category: 'Footer',
                attributes: { class: 'fa fa-image' }
            });
            
            blockManager.add('footer-menu', {
                label: 'Footer Menü',
                content: `
                    <div class="footer-menu">
                        <h5>Hızlı Linkler</h5>
                        <ul class="list-unstyled">
                            <li><a href="#">Ana Sayfa</a></li>
                            <li><a href="#">Hakkımızda</a></li>
                            <li><a href="#">Odalar</a></li>
                            <li><a href="#">Hizmetler</a></li>
                            <li><a href="#">İletişim</a></li>
                        </ul>
                    </div>
                `,
                category: 'Footer',
                attributes: { class: 'fa fa-list' }
            });
            
            blockManager.add('footer-contact', {
                label: 'Footer İletişim',
                content: `
                    <div class="footer-contact">
                        <h5>İletişim</h5>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>123 Otel Caddesi, İstanbul</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>+90 (212) 123 45 67</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>info@otel.com</span>
                        </div>
                    </div>
                `,
                category: 'Footer',
                attributes: { class: 'fa fa-phone' }
            });
            
            blockManager.add('footer-social', {
                label: 'Footer Sosyal Medya',
                content: `
                    <div class="footer-social">
                        <h5>Sosyal Medya</h5>
                        <div class="social-links">
                            <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                `,
                category: 'Footer',
                attributes: { class: 'fa fa-share-alt' }
            });
        }

        // Header yükle
        function loadHeader() {
            if (!headerEditor) return;
            
            // Varsayılan header içeriği
            const defaultHeader = `
                <header class="header-main" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); padding: 1rem 0; color: white;">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="header-logo">
                                    <img src="https://via.placeholder.com/150x50/ffffff/007bff?text=LOGO" alt="Logo" style="max-height: 50px;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <nav class="navbar navbar-expand-lg">
                                    <div class="navbar-nav mx-auto">
                                        <a class="nav-link active" href="#" style="color: white;">Ana Sayfa</a>
                                        <a class="nav-link" href="#" style="color: white;">Hakkımızda</a>
                                        <a class="nav-link" href="#" style="color: white;">Odalar</a>
                                        <a class="nav-link" href="#" style="color: white;">Hizmetler</a>
                                        <a class="nav-link" href="#" style="color: white;">İletişim</a>
                                    </div>
                                </nav>
                            </div>
                            <div class="col-md-3">
                                <div class="header-contact text-end">
                                    <div class="contact-item mb-1">
                                        <i class="fas fa-phone me-2"></i>
                                        <span>+90 (212) 123 45 67</span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-envelope me-2"></i>
                                        <span>info@otel.com</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
            `;
            
            headerEditor.setComponents(defaultHeader);
        }

        // Footer yükle
        function loadFooter() {
            if (!footerEditor) return;
            
            // Varsayılan footer içeriği
            const defaultFooter = `
                <footer class="footer-main" style="background: #1a1a2e; color: white; padding: 3rem 0 1rem;">
                    <div class="container">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="footer-logo">
                                    <img src="https://via.placeholder.com/150x50/ffffff/007bff?text=LOGO" alt="Logo" style="max-height: 50px; margin-bottom: 1rem;">
                                    <p>Premium konaklama deneyimi sunan lüks otelimizde unutulmaz anlar yaşayın.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="footer-menu">
                                    <h5 style="color: #ffd700; margin-bottom: 1rem;">Hızlı Linkler</h5>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><a href="#" style="color: white; text-decoration: none;">Ana Sayfa</a></li>
                                        <li class="mb-2"><a href="#" style="color: white; text-decoration: none;">Hakkımızda</a></li>
                                        <li class="mb-2"><a href="#" style="color: white; text-decoration: none;">Odalar</a></li>
                                        <li class="mb-2"><a href="#" style="color: white; text-decoration: none;">Hizmetler</a></li>
                                        <li class="mb-2"><a href="#" style="color: white; text-decoration: none;">İletişim</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="footer-contact">
                                    <h5 style="color: #ffd700; margin-bottom: 1rem;">İletişim</h5>
                                    <div class="contact-item mb-2">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <span>123 Otel Caddesi, İstanbul</span>
                                    </div>
                                    <div class="contact-item mb-2">
                                        <i class="fas fa-phone me-2"></i>
                                        <span>+90 (212) 123 45 67</span>
                                    </div>
                                    <div class="contact-item mb-2">
                                        <i class="fas fa-envelope me-2"></i>
                                        <span>info@otel.com</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr style="border-color: #333; margin: 2rem 0 1rem;">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-0">&copy; 2024 Premium Hotel. Tüm hakları saklıdır.</p>
                            </div>
                            <div class="col-md-6">
                                <div class="footer-social text-end">
                                    <a href="#" class="social-link me-2" style="color: white; font-size: 1.2rem;"><i class="fab fa-facebook"></i></a>
                                    <a href="#" class="social-link me-2" style="color: white; font-size: 1.2rem;"><i class="fab fa-twitter"></i></a>
                                    <a href="#" class="social-link me-2" style="color: white; font-size: 1.2rem;"><i class="fab fa-instagram"></i></a>
                                    <a href="#" class="social-link" style="color: white; font-size: 1.2rem;"><i class="fab fa-linkedin"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </footer>
            `;
            
            footerEditor.setComponents(defaultFooter);
        }

        // Header sıfırla
        function resetHeader() {
            if (headerEditor) {
                loadHeader();
                showToast('info', 'Header sıfırlandı!');
            }
        }

        // Footer sıfırla
        function resetFooter() {
            if (footerEditor) {
                loadFooter();
                showToast('info', 'Footer sıfırlandı!');
            }
        }

        // Template ayarlarını yükle
        function loadTemplateSettings() {
            $.get('ajax/load-template-settings.php').done(function(response) {
                if (response.success && response.settings) {
                    const settings = response.settings;
                    
                    // Form alanlarını doldur
                    document.getElementById('otelAdi').value = settings.otel_adi || '';
                    document.getElementById('logoUrl').value = settings.logo_url || '';
                    document.getElementById('otelAdres').value = settings.otel_adres || '';
                    document.getElementById('otelTelefon').value = settings.otel_telefon || '';
                    document.getElementById('facebookUrl').value = settings.facebook_url || '';
                    document.getElementById('twitterUrl').value = settings.twitter_url || '';
                    document.getElementById('instagramUrl').value = settings.instagram_url || '';
                    document.getElementById('pinterestUrl').value = settings.pinterest_url || '';
                    document.getElementById('linkedinUrl').value = settings.linkedin_url || '';
                }
            }).fail(function() {
                showToast('warning', 'Template ayarları yüklenemedi!');
            });
        }

        // Header yükle
        function loadHeader() {
            $.get('ajax/load-layout-component.php?component=header').done(function(response) {
                if (response.success && response.html) {
                    headerEditor.setComponents(response.html);
                    if (response.css) {
                        headerEditor.setStyle(response.css);
                    }
                } else {
                    loadDefaultHeader();
                }
            }).fail(function() {
                loadDefaultHeader();
            });
        }

        // Footer yükle
        function loadFooter() {
            $.get('ajax/load-layout-component.php?component=footer').done(function(response) {
                if (response.success && response.html) {
                    footerEditor.setComponents(response.html);
                    if (response.css) {
                        footerEditor.setStyle(response.css);
                    }
                } else {
                    loadDefaultFooter();
                }
            }).fail(function() {
                loadDefaultFooter();
            });
        }

        // Resim Yöneticisi Fonksiyonları
        function showImageManager() {
            $('#imageManagerModal').modal('show');
            loadImages();
        }

        function loadImages() {
            $.get('ajax/get-images.php', {
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    displayImages(response.images);
                } else {
                    showToast('error', 'Resimler yüklenemedi: ' + response.message);
                }
            }).fail(function() {
                showToast('error', 'Resimler yüklenemedi!');
            });
        }

        function displayImages(images) {
            const grid = $('#imageGrid');
            grid.empty();

            images.forEach(function(image) {
                const imageCard = $(`
                    <div class="col-md-3 mb-2">
                        <div class="card image-card" data-url="${image.file_path}" data-name="${image.original_name}">
                            <img src="${image.file_path}" class="card-img-top" style="height: 120px; object-fit: cover;" alt="${image.original_name}">
                            <div class="card-body p-2">
                                <h6 class="card-title" style="font-size: 0.8em; margin: 0;">${image.original_name}</h6>
                                <button class="btn btn-sm btn-primary w-100 mt-1" onclick="selectImage('${image.file_path}')">
                                    <i class="fas fa-check"></i> Seç
                                </button>
                            </div>
                        </div>
                    </div>
                `);
                grid.append(imageCard);
            });
        }

        function selectImage(imageUrl) {
            // Seçilen resmi GrapesJS'deki aktif elemente ekle
            if (editor) {
                const selected = editor.getSelected();
                if (selected && selected.get('type') === 'image') {
                    selected.set('src', imageUrl);
                    showToast('success', 'Resim güncellendi!');
                    $('#imageManagerModal').modal('hide');
                } else {
                    showToast('warning', 'Lütfen önce bir resim elementi seçin!');
                }
            }
        }

        // Resim yükleme formu
        $('#imageUploadForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('imageFile');
            
            if (fileInput.files.length === 0) {
                showToast('warning', 'Lütfen bir dosya seçin!');
                return;
            }
            
            formData.append('image', fileInput.files[0]);
            formData.append('csrf_token', csrfToken);
            
            $.ajax({
                url: 'ajax/upload-image.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showToast('success', 'Resim başarıyla yüklendi!');
                        loadImages(); // Resim listesini yenile
                        $('#imageFile').val(''); // Formu temizle
                    } else {
                        showToast('error', 'Resim yüklenemedi: ' + response.message);
                    }
                },
                error: function() {
                    showToast('error', 'Resim yüklenemedi!');
                }
            });
        });

        // ===== DIRECT DATABASE FUNCTIONS (Admin için optimize edildi) =====

        // Dinamik değişkenleri doğrudan yükle
        function loadDynamicVariables() {
            return $.ajax({
                url: 'ajax/get-dynamic-variables.php',
                method: 'GET',
                data: { csrf_token: csrfToken }
            }).then(function(response) {
                console.log('Dinamik değişkenler response:', response);
                if (response.success) {
                    console.log('Dinamik değişkenler yüklendi:', response.variables);
                    return response.variables;
                } else {
                    console.error('Dinamik değişkenler yüklenemedi:', response.message);
                    return [];
                }
            }).catch(function(error) {
                console.error('Dinamik değişkenler yüklenirken hata:', error);
                return [];
            });
        }

        // Oda tiplerini doğrudan yükle
        function loadOdaTipleri() {
            return $.ajax({
                url: 'ajax/get-oda-tipleri.php',
                method: 'GET',
                data: { durum: 'aktif', csrf_token: csrfToken }
            }).then(function(response) {
                if (response.success) {
                    console.log('Oda tipleri yüklendi:', response.data);
                    return response.data;
                }
                return [];
            }).catch(function(error) {
                console.error('Oda tipleri yüklenirken hata:', error);
                return [];
            });
        }

        // Admin paneli için optimize edildi - gereksiz API fonksiyonları kaldırıldı

        // Admin için optimize edildi - API test kaldırıldı

        // Sayfa yüklendiğinde real-time'ı başlat
        $(document).ready(function() {
            // Real-time test butonu (devre dışı - performans için)
            // if ($('#realtime-test-btn').length === 0) {
            //     $('<button type="button" class="btn btn-success btn-sm" id="realtime-test-btn" style="position: fixed; top: 10px; right: 10px; z-index: 9999;">Real-Time Test</button>')
            //         .appendTo('body')
            //         .click(testRealTime);
            // }
            
            // Real-time event handlers (devre dışı - performans için)
            // setTimeout(function() {
            //     setupRealTimeHandlers();
            // }, 5000); // Real-time devre dışı
        });
        
        // Real-time test fonksiyonu (devre dışı - performans için)
        // async function testRealTime() {
        //     if (typeof realTimeClient === 'undefined') {
        //         console.warn('Real-time client bulunamadı, test yapılamıyor');
        //         showNotification('Real-time client bulunamadı', 'warning');
        //         return;
        //     }
        //     
        //     try {
        //         console.log('Real-time test başlatılıyor...');
        //         
        //         // Test event gönder
        //         const result = await realTimeClient.sendTestEvent('Page Builder Test Event', 'page-builder');
        //         console.log('✅ Real-time test event gönderildi:', result);
        //         
        //         // Sistem bildirimi gönder
        //         await realTimeClient.sendSystemNotification(
        //             'Page Builder Test',
        //             'Real-time sistem test edildi',
        //             'success'
        //         );
        //         console.log('✅ Sistem bildirimi gönderildi');
        //         
        //         // Bağlantı durumunu kontrol et
        //         console.log('Real-time bağlantı durumu:', realTimeClient.isConnected());
        //         
        //     } catch (error) {
        //         console.error('❌ Real-time test hatası:', error);
        //     }
        // }
        
        // Real-time event handlers (devre dışı - performans için)
        /*
        function setupRealTimeHandlers() {
            // Real-time bağlantısını kur (sadece bir kez)
            if (typeof realTimeClient !== 'undefined') {
                // Eğer zaten bağlıysa tekrar bağlanma
                if (realTimeClient.isConnected && realTimeClient.isConnected()) {
                    console.log('Real-time zaten bağlı, tekrar bağlanmıyor');
                    return;
                }
                
                try {
                    realTimeClient.connect('admin', null);
                    console.log('Real-time bağlantısı başlatıldı...');
                } catch (error) {
                    console.error('Real-time bağlantı hatası:', error);
                }
            } else {
                console.warn('Real-time client bulunamadı, bağlantı kurulamıyor');
            }
            
            // Event handler'ları sadece real-time client varsa ekle
            if (typeof realTimeClient !== 'undefined') {
                // Bağlantı kurulduğunda
                realTimeClient.on('connected', function(data) {
                    console.log('🔗 Real-time bağlantısı kuruldu:', data);
                    showNotification('Real-time bağlantısı kuruldu', 'success');
                });
                
                // Test event alındığında
                realTimeClient.on('test', function(data) {
                    console.log('📨 Test event alındı:', data);
                    showNotification('Test event alındı: ' + data.message, 'info');
                });
                
                // Rezervasyon güncellemesi
                realTimeClient.on('reservation_update', function(data) {
                    console.log('🏨 Rezervasyon güncellemesi:', data);
                    showNotification('Rezervasyon güncellendi: #' + data.reservation_id, 'warning');
                });
                
                // Sistem bildirimi
                realTimeClient.on('system_notification', function(data) {
                    console.log('🔔 Sistem bildirimi:', data);
                    showNotification(data.title + ': ' + data.message, data.type);
                });
                
                // Heartbeat (sadece her 10'da bir göster)
                let heartbeatCounter = 0;
                realTimeClient.on('heartbeat', function(data) {
                    heartbeatCounter++;
                    if (heartbeatCounter % 10 === 0) {
                        console.log('💓 Heartbeat alındı:', heartbeatCounter, 'kez');
                    }
                });
                
                // Refresh
                realTimeClient.on('refresh', function(data) {
                    console.log('🔄 Connection refresh:', data);
                    showNotification('Bağlantı yenilendi', 'info');
                });
                
                // Hata durumu
                realTimeClient.on('error', function(error) {
                    console.error('❌ Real-time hatası:', error);
                    showNotification('Real-time bağlantı hatası', 'danger');
                });
            }
        }
        */
        
        // Bildirim gösterme fonksiyonu
        function showNotification(message, type = 'info') {
            const alertClass = `alert-${type}`;
            const notification = $(`
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert" style="position: fixed; top: 100px; right: 10px; z-index: 10000; min-width: 300px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            
            $('body').append(notification);
            
            // 5 saniye sonra otomatik kapat
            setTimeout(() => {
                notification.alert('close');
            }, 5000);
        }
    </script>
</body>
</html>

