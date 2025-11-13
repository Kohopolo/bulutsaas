<?php
/**
 * Ultimate AI Page Builder
 * Profesyonel seviye drag & drop editor
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

$pageTitle = $pageData ? htmlspecialchars($pageData['title']) : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Page Builder</title>
    
    <!-- GrapesJS Core -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.7/dist/css/grapes.min.css">
    
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        * { box-sizing: border-box; }
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        #navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 12px 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        #navbar h4 {
            margin: 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        #navbar .btn {
            margin: 0 3px;
            padding: 8px 16px;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        #navbar .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        #navbar input[type="text"] {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            width: 300px;
            transition: all 0.3s;
        }
        
        #navbar input[type="text"]:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
            width: 350px;
        }
        
        #editor-container {
            height: calc(100vh - 55px);
            width: 100%;
            position: relative;
        }
        
        #gjs {
            height: 100%;
            width: 100%;
            border: none !important;
        }
        
        /* Custom GrapesJS Styling */
        .gjs-pn-panel {
            background: #2c3e50;
        }
        
        .gjs-pn-btn {
            color: #ecf0f1 !important;
        }
        
        .gjs-block {
            min-height: 70px;
            transition: all 0.3s;
            border-radius: 8px;
        }
        
        .gjs-block:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .gjs-block-label {
            font-weight: 600;
        }
        
        /* AI Panel */
        #aiPanel {
            position: fixed;
            right: -380px;
            top: 60px;
            width: 380px;
            height: calc(100vh - 70px);
            background: white;
            border-radius: 12px 0 0 12px;
            box-shadow: -5px 0 30px rgba(0,0,0,0.2);
            padding: 25px;
            z-index: 10000;
            overflow-y: auto;
            transition: right 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        #aiPanel.show {
            right: 0;
        }
        
        #aiPanel h5 {
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 1.3em;
        }
        
        .ai-suggestion {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 12px 0;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .ai-suggestion:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(-5px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .ai-suggestion i {
            font-size: 1.5em;
        }
        
        #ai-response {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            transition: all 0.3s;
            z-index: 9999;
        }
        
        .fab:hover {
            transform: scale(1.1) rotate(15deg);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 10001;
        }
        
        .custom-toast {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Templates Library */
        .template-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .template-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .template-card:hover::before {
            left: 100%;
        }
        
        .template-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .template-preview {
            width: 100%;
            height: 150px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            color: #667eea;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div id="navbar">
        <div>
            <a href="page-list.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left"></i> Geri
            </a>
            <h4>
                <i class="fas fa-magic"></i>
                Ultimate Page Builder
                <span style="font-size: 0.6em; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 12px;">Pro</span>
            </h4>
        </div>
        
        <div>
            <input type="text" id="pageTitle" placeholder="✨ Sayfa Başlığı Girin..." value="<?php echo $pageTitle; ?>">
        </div>
        
        <div>
            <button class="btn btn-warning btn-sm" onclick="showTemplateLibrary()">
                <i class="fas fa-layer-group"></i> Şablonlar
            </button>
            <button class="btn btn-info btn-sm" onclick="preview()">
                <i class="fas fa-eye"></i> Önizle
            </button>
            <button class="btn btn-light btn-sm" onclick="save(false)">
                <i class="fas fa-save"></i> Kaydet
            </button>
            <button class="btn btn-success btn-sm" onclick="save(true)">
                <i class="fas fa-check-circle"></i> Yayınla
            </button>
        </div>
    </div>
    
    <!-- Editor Container -->
    <div id="editor-container">
        <div id="gjs"></div>
    </div>
    
    <!-- Floating AI Button -->
    <div class="fab" onclick="toggleAI()" title="AI Asistan">
        <i class="fas fa-robot"></i>
    </div>
    
    <!-- AI Panel -->
    <div id="aiPanel">
        <h5>
            <span><i class="fas fa-robot"></i> AI Asistan Pro</span>
            <button class="btn btn-sm btn-light" onclick="toggleAI()">
                <i class="fas fa-times"></i>
            </button>
        </h5>
        
        <div class="mb-4">
            <label class="form-label fw-bold">🎨 AI İle İçerik Oluştur</label>
            <textarea id="aiPrompt" class="form-control" rows="4" 
                      placeholder="Örn: Lüks bir otel için modern hero bölümü oluştur. Gradient arka plan, büyük başlık ve CTA butonu olsun."></textarea>
            <button class="btn btn-primary w-100 mt-3" onclick="generateWithAI()">
                <i class="fas fa-magic"></i> AI ile Oluştur
            </button>
        </div>
        
        <div id="aiResponse"></div>
        
        <hr>
        
        <h6 class="fw-bold mb-3"><i class="fas fa-bolt"></i> Hızlı Şablonlar</h6>
        
        <div class="ai-suggestion" onclick="addTemplate('modern-hero')">
            <i class="fas fa-rocket"></i>
            <div>
                <strong>Modern Hero</strong>
                <small class="d-block text-muted">Gradient arka plan + CTA</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('feature-grid')">
            <i class="fas fa-th"></i>
            <div>
                <strong>Özellik Grid</strong>
                <small class="d-block text-muted">3 sütun icon kartlar</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('testimonial-slider')">
            <i class="fas fa-quote-left"></i>
            <div>
                <strong>Müşteri Yorumları</strong>
                <small class="d-block text-muted">Slider ile yorumlar</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('contact-form-modern')">
            <i class="fas fa-envelope"></i>
            <div>
                <strong>Modern İletişim Formu</strong>
                <small class="d-block text-muted">Animated form</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('pricing-table')">
            <i class="fas fa-dollar-sign"></i>
            <div>
                <strong>Fiyatlandırma Tablosu</strong>
                <small class="d-block text-muted">3 paket seçeneği</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('team-section')">
            <i class="fas fa-users"></i>
            <div>
                <strong>Ekip Bölümü</strong>
                <small class="d-block text-muted">Fotoğraf + sosyal medya</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('stats-counter')">
            <i class="fas fa-chart-line"></i>
            <div>
                <strong>İstatistik Sayaçları</strong>
                <small class="d-block text-muted">Animated counters</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('gallery-masonry')">
            <i class="fas fa-images"></i>
            <div>
                <strong>Masonry Galeri</strong>
                <small class="d-block text-muted">Pinterest tarzı</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('cta-banner')">
            <i class="fas fa-bullhorn"></i>
            <div>
                <strong>CTA Banner</strong>
                <small class="d-block text-muted">Aksiyon çağrısı</small>
            </div>
        </div>
        
        <div class="ai-suggestion" onclick="addTemplate('footer-modern')">
            <i class="fas fa-grip-horizontal"></i>
            <div>
                <strong>Modern Footer</strong>
                <small class="d-block text-muted">4 kolon + sosyal</small>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container"></div>
    
    <!-- Template Library Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-layer-group"></i> Şablon Kütüphanesi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row" id="templateLibraryContent">
                        <!-- Templates will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- GrapesJS Core -->
    <script src="https://unpkg.com/grapesjs@0.21.7/dist/grapes.min.js"></script>
    
    <script>
        console.log('🚀 Ultimate Page Builder başlatılıyor...');
        
        const pageId = <?php echo $pageId ? $pageId : 'null'; ?>;
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        let editor;
        
        // Ana başlatma fonksiyonu
        function initPageBuilder() {
            if (typeof grapesjs === 'undefined') {
                showToast('error', 'GrapesJS yüklenemedi!');
                return;
            }
            
            console.log('✅ GrapesJS başlatılıyor...');
            
            try {
                editor = grapesjs.init({
                    container: '#gjs',
                    height: '100%',
                    width: 'auto',
                    storageManager: false,
                    canvas: {
                        styles: [
                            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
                            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
                            'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css'
                        ],
                        scripts: [
                            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
                        ]
                    },
                    deviceManager: {
                        devices: [
                            { name: 'Desktop', width: '' },
                            { name: 'Tablet', width: '768px' },
                            { name: 'Mobil', width: '375px' }
                        ]
                    }
                });
                
                console.log('✅ GrapesJS başarıyla başlatıldı!');
                
                // Blokları ekle
                addUltimateBlocks();
                
                // Mevcut içeriği yükle
                <?php if ($pageData): ?>
                    editor.setComponents(<?php echo json_encode($pageData['content_html'] ?? ''); ?>);
                    editor.setStyle(<?php echo json_encode($pageData['content_css'] ?? ''); ?>);
                    showToast('success', 'İçerik yüklendi');
                <?php endif; ?>
                
                showToast('success', 'Page Builder hazır! 🚀');
                
            } catch (error) {
                console.error('Başlatma hatası:', error);
                showToast('error', 'Editor başlatılamadı: ' + error.message);
            }
        }
        
        // Ultimate Blokları Ekle
        function addUltimateBlocks() {
            const bm = editor.BlockManager;
            
            // === TEmel Bloklar ===
            bm.add('text', {
                label: '<i class="fa fa-font"></i><div>Metin</div>',
                category: 'Temel',
                content: '<div style="padding:20px;">Metin yazın veya düzenleyin...</div>'
            });
            
            bm.add('heading', {
                label: '<i class="fa fa-heading"></i><div>Başlık</div>',
                category: 'Temel',
                content: '<h2 style="margin:20px 0;">Başlık</h2>'
            });
            
            bm.add('image', {
                label: '<i class="fa fa-image"></i><div>Resim</div>',
                category: 'Temel',
                content: { type: 'image', src: 'https://via.placeholder.com/800x400' }
            });
            
            bm.add('button', {
                label: '<i class="fa fa-hand-pointer"></i><div>Buton</div>',
                category: 'Temel',
                content: '<a href="#" class="btn btn-primary">Tıklayın</a>'
            });
            
            bm.add('divider', {
                label: '<i class="fa fa-minus"></i><div>Ayırıcı</div>',
                category: 'Temel',
                content: '<hr style="margin:30px 0;">'
            });
            
            // === Layout Blokları ===
            bm.add('container', {
                label: '<i class="fa fa-square"></i><div>Container</div>',
                category: 'Layout',
                content: '<div class="container" style="padding:40px 15px;"><p>İçerik buraya</p></div>'
            });
            
            bm.add('row-2col', {
                label: '<i class="fa fa-columns"></i><div>2 Kolon</div>',
                category: 'Layout',
                content: `<div class="row" style="margin:20px 0;">
                    <div class="col-md-6" style="padding:20px; background:#f8f9fa; min-height:100px;">Kolon 1</div>
                    <div class="col-md-6" style="padding:20px; background:#e9ecef; min-height:100px;">Kolon 2</div>
                </div>`
            });
            
            bm.add('row-3col', {
                label: '<i class="fa fa-th"></i><div>3 Kolon</div>',
                category: 'Layout',
                content: `<div class="row" style="margin:20px 0;">
                    <div class="col-md-4" style="padding:20px; background:#f8f9fa; min-height:100px;">Kolon 1</div>
                    <div class="col-md-4" style="padding:20px; background:#e9ecef; min-height:100px;">Kolon 2</div>
                    <div class="col-md-4" style="padding:20px; background:#f8f9fa; min-height:100px;">Kolon 3</div>
                </div>`
            });
            
            bm.add('row-4col', {
                label: '<i class="fa fa-grip-horizontal"></i><div>4 Kolon</div>',
                category: 'Layout',
                content: `<div class="row" style="margin:20px 0;">
                    <div class="col-md-3" style="padding:15px; background:#f8f9fa; min-height:80px; text-align:center;">1</div>
                    <div class="col-md-3" style="padding:15px; background:#e9ecef; min-height:80px; text-align:center;">2</div>
                    <div class="col-md-3" style="padding:15px; background:#f8f9fa; min-height:80px; text-align:center;">3</div>
                    <div class="col-md-3" style="padding:15px; background:#e9ecef; min-height:80px; text-align:center;">4</div>
                </div>`
            });
            
            // === Modern Bölümler ===
            bm.add('modern-hero', {
                label: '<i class="fa fa-rocket"></i><div>Modern Hero</div>',
                category: 'Modern',
                content: `<section style="padding:120px 20px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align:center; color:white; position:relative; overflow:hidden;">
                    <div class="container animate__animated animate__fadeIn">
                        <h1 style="font-size:3.5em; font-weight:800; margin-bottom:25px; text-shadow:2px 2px 4px rgba(0,0,0,0.2);">Harika Bir Başlık</h1>
                        <p style="font-size:1.4em; margin-bottom:40px; opacity:0.95;">Modern ve etkileyici bir alt başlık yazın</p>
                        <a href="#" class="btn btn-light btn-lg" style="padding:15px 50px; font-size:1.1em; border-radius:50px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">Başlayın</a>
                    </div>
                </section>`
            });
            
            bm.add('feature-cards', {
                label: '<i class="fa fa-th-large"></i><div>Özellik Kartları</div>',
                category: 'Modern',
                content: `<section style="padding:80px 20px; background:#f8f9fa;">
                    <div class="container">
                        <h2 style="text-align:center; margin-bottom:60px; font-size:2.5em; font-weight:700;">Özelliklerimiz</h2>
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div style="background:white; padding:40px; border-radius:15px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.05); transition:all 0.3s; height:100%;">
                                    <div style="width:80px; height:80px; margin:0 auto 25px; background:linear-gradient(135deg, #667eea, #764ba2); border-radius:20px; display:flex; align-items:center; justify-content:center;">
                                        <i class="fas fa-bolt" style="font-size:2.5em; color:white;"></i>
                                    </div>
                                    <h4 style="margin-bottom:15px; font-weight:600;">Hızlı</h4>
                                    <p style="color:#666; line-height:1.8;">Yıldırım hızında performans</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div style="background:white; padding:40px; border-radius:15px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.05); transition:all 0.3s; height:100%;">
                                    <div style="width:80px; height:80px; margin:0 auto 25px; background:linear-gradient(135deg, #f093fb, #f5576c); border-radius:20px; display:flex; align-items:center; justify-content:center;">
                                        <i class="fas fa-shield-alt" style="font-size:2.5em; color:white;"></i>
                                    </div>
                                    <h4 style="margin-bottom:15px; font-weight:600;">Güvenli</h4>
                                    <p style="color:#666; line-height:1.8;">Maksimum güvenlik</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div style="background:white; padding:40px; border-radius:15px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.05); transition:all 0.3s; height:100%;">
                                    <div style="width:80px; height:80px; margin:0 auto 25px; background:linear-gradient(135deg, #4facfe, #00f2fe); border-radius:20px; display:flex; align-items:center; justify-content:center;">
                                        <i class="fas fa-heart" style="font-size:2.5em; color:white;"></i>
                                    </div>
                                    <h4 style="margin-bottom:15px; font-weight:600;">Kolay</h4>
                                    <p style="color:#666; line-height:1.8;">Kullanımı çok kolay</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>`
            });
            
            bm.add('pricing-modern', {
                label: '<i class="fa fa-tags"></i><div>Fiyat Kartları</div>',
                category: 'Modern',
                content: `<section style="padding:80px 20px;">
                    <div class="container">
                        <h2 style="text-align:center; margin-bottom:60px; font-size:2.5em;">Fiyatlandırma</h2>
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div style="background:white; border:2px solid #e0e0e0; border-radius:20px; padding:40px; text-align:center;">
                                    <h4 style="font-weight:600; margin-bottom:20px;">Başlangıç</h4>
                                    <div style="font-size:3em; font-weight:800; color:#667eea; margin:30px 0;">$9<span style="font-size:0.4em;">/ay</span></div>
                                    <ul style="list-style:none; padding:0; margin:30px 0;">
                                        <li style="padding:10px 0;">✓ Özellik 1</li>
                                        <li style="padding:10px 0;">✓ Özellik 2</li>
                                        <li style="padding:10px 0;">✓ Özellik 3</li>
                                    </ul>
                                    <a href="#" class="btn btn-outline-primary btn-lg" style="width:100%; border-radius:50px;">Seç</a>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div style="background:linear-gradient(135deg, #667eea, #764ba2); border-radius:20px; padding:40px; text-align:center; color:white; transform:scale(1.05); box-shadow:0 20px 60px rgba(102,126,234,0.3);">
                                    <div style="background:rgba(255,255,255,0.2); display:inline-block; padding:5px 20px; border-radius:20px; margin-bottom:20px; font-size:0.9em;">Popüler</div>
                                    <h4 style="font-weight:600; margin-bottom:20px;">Pro</h4>
                                    <div style="font-size:3em; font-weight:800; margin:30px 0;">$29<span style="font-size:0.4em;">/ay</span></div>
                                    <ul style="list-style:none; padding:0; margin:30px 0;">
                                        <li style="padding:10px 0;">✓ Tüm Özellikler</li>
                                        <li style="padding:10px 0;">✓ Öncelik Desteği</li>
                                        <li style="padding:10px 0;">✓ API Erişimi</li>
                                    </ul>
                                    <a href="#" class="btn btn-light btn-lg" style="width:100%; border-radius:50px; font-weight:600;">Seç</a>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div style="background:white; border:2px solid #e0e0e0; border-radius:20px; padding:40px; text-align:center;">
                                    <h4 style="font-weight:600; margin-bottom:20px;">Kurumsal</h4>
                                    <div style="font-size:3em; font-weight:800; color:#667eea; margin:30px 0;">$99<span style="font-size:0.4em;">/ay</span></div>
                                    <ul style="list-style:none; padding:0; margin:30px 0;">
                                        <li style="padding:10px 0;">✓ Sınırsız Kullanım</li>
                                        <li style="padding:10px 0;">✓ 7/24 Destek</li>
                                        <li style="padding:10px 0;">✓ Özel Entegrasyon</li>
                                    </ul>
                                    <a href="#" class="btn btn-outline-primary btn-lg" style="width:100%; border-radius:50px;">İletişim</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>`
            });
            
            console.log('✅ Ultimate bloklar eklendi!');
        }
        
        // Toast bildirim göster
        function showToast(type, message) {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle'
            };
            
            const colors = {
                success: '#28a745',
                error: '#dc3545',
                info: '#17a2b8',
                warning: '#ffc107'
            };
            
            const toast = $('<div class="custom-toast">')
                .html(`
                    <i class="fas ${icons[type]}" style="font-size:1.5em; color:${colors[type]};"></i>
                    <div style="flex:1;">${message}</div>
                `)
                .appendTo('.toast-container');
            
            setTimeout(() => toast.fadeOut(() => toast.remove()), 3000);
        }
        
        // AI Panel Toggle
        function toggleAI() {
            $('#aiPanel').toggleClass('show');
        }
        
        // AI ile içerik üret
        function generateWithAI() {
            const prompt = $('#aiPrompt').val().trim();
            if (!prompt) {
                showToast('warning', 'Lütfen bir açıklama girin!');
                return;
            }
            
            $('#aiResponse').html('<div class="text-center"><div class="spinner-border spinner-border-sm text-primary"></div> AI düşünüyor...</div>');
            
            $.post('ajax/ai-generate-content.php', {
                prompt: prompt,
                type: 'page_content',
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    const cleanHtml = response.html.replace(/```html|```/g, '').trim();
                    $('#aiResponse').html(`
                        <div class="alert alert-success">
                            <strong>✅ Başarılı!</strong><br>
                            <small>İçerik oluşturuldu.</small>
                        </div>
                        <button class="btn btn-primary w-100" onclick='applyAIContent(\`${cleanHtml.replace(/`/g, '\\`')}\`)'>
                            <i class="fas fa-plus"></i> Sayfaya Ekle
                        </button>
                    `);
                    showToast('success', 'AI içerik oluşturdu!');
                } else {
                    $('#aiResponse').html(`<div class="alert alert-danger">❌ ${response.message}</div>`);
                    showToast('error', 'AI hatası!');
                }
            }).fail(function() {
                $('#aiResponse').html('<div class="alert alert-danger">❌ Sunucu hatası!</div>');
                showToast('error', 'Sunucu hatası!');
            });
        }
        
        // AI içeriğini ekle
        function applyAIContent(html) {
            editor.addComponents(html);
            showToast('success', 'İçerik eklendi!');
        }
        
        // Şablon ekle
        function addTemplate(templateName) {
            const templates = {
                'modern-hero': editor.BlockManager.get('modern-hero').attributes.content,
                'feature-grid': editor.BlockManager.get('feature-cards').attributes.content,
                'pricing-table': editor.BlockManager.get('pricing-modern').attributes.content,
                // Diğer şablonlar...
            };
            
            if (templates[templateName]) {
                editor.addComponents(templates[templateName]);
                showToast('success', 'Şablon eklendi!');
            }
        }
        
        // Kaydet
        function save(publish = false) {
            const title = $('#pageTitle').val().trim();
            if (!title) {
                showToast('warning', 'Lütfen sayfa başlığı girin!');
                return;
            }
            
            const html = editor.getHtml();
            const css = editor.getCss();
            
            showToast('info', 'Kaydediliyor...');
            
            $.post('ajax/page-builder-save.php', {
                page_id: pageId,
                title: title,
                html: html,
                css: css,
                status: publish ? 'published' : 'draft',
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    showToast('success', response.message);
                    if (!pageId && response.page_id) {
                        setTimeout(() => {
                            window.location.href = 'page-builder-ultimate.php?page_id=' + response.page_id;
                        }, 1000);
                    }
                } else {
                    showToast('error', response.message);
                }
            }).fail(function() {
                showToast('error', 'Kaydetme hatası!');
            });
        }
        
        // Önizle
        function preview() {
            const html = editor.getHtml();
            const css = editor.getCss();
            
            const win = window.open('', '_blank');
            win.document.write(`
                <!DOCTYPE html>
                <html><head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
                <style>${css}</style>
                </head><body>${html}
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                </body></html>
            `);
            win.document.close();
            showToast('info', 'Önizleme açıldı!');
        }
        
        // Şablon kütüphanesini göster
        function showTemplateLibrary() {
            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
            showToast('info', 'Şablon kütüphanesi yakında!');
        }
        
        // Page Builder'ı başlat
        $(document).ready(function() {
            initPageBuilder();
        });
    </script>
</body>
</html>

