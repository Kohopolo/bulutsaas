<?php
/**
 * AI Powered Page Builder
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
    header('Location: ../error/403.php');
    exit;
}

// Sayfa ID kontrolü (düzenleme modunda)
$pageId = $_GET['page_id'] ?? null;
$pageData = null;

if ($pageId) {
    $stmt = $pdo->prepare("SELECT * FROM custom_pages WHERE id = ?");
    $stmt->execute([$pageId]);
    $pageData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pageData) {
        header('Location: page-list.php');
        exit;
    }
}

$pageTitle = $pageData ? "Sayfa Düzenle: " . $pageData['title'] : "Yeni Sayfa Oluştur";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Paneli</title>
    
    <!-- GrapesJS Core -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
    
    <!-- GrapesJS Preset Webpage (includes basic web builder blocks) -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs-preset-webpage/dist/grapesjs-preset-webpage.min.css">
    
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }
        
        #navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        #navbar .btn {
            margin: 0 5px;
        }
        
        #gjs {
            height: calc(100vh - 70px);
            border: none;
        }
        
        .gjs-one-bg {
            background-color: #f8f9fa;
        }
        
        .gjs-two-color {
            color: #667eea;
        }
        
        .gjs-three-bg {
            background-color: #667eea;
            color: white;
        }
        
        .gjs-four-color,
        .gjs-four-color-h:hover {
            color: #764ba2;
        }
        
        .ai-panel {
            position: fixed;
            right: 20px;
            top: 100px;
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 20px;
            z-index: 9999;
            max-height: 80vh;
            overflow-y: auto;
            display: none;
        }
        
        .ai-panel.show {
            display: block;
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .ai-panel h5 {
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .ai-suggestion {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #667eea;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .ai-suggestion:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.2);
        }
        
        #ai-response {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div id="navbar">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="page-list.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left"></i> Geri
                </a>
                <strong class="ms-3" style="font-size: 1.2em;">
                    <i class="fas fa-magic"></i> AI Page Builder
                </strong>
            </div>
            
            <div>
                <input type="text" id="pageTitle" class="form-control form-control-sm d-inline-block" 
                       style="width: 300px;" placeholder="Sayfa Başlığı" 
                       value="<?php echo htmlspecialchars($pageData['title'] ?? ''); ?>">
            </div>
            
            <div>
                <button class="btn btn-warning btn-sm" onclick="toggleAIPanel()">
                    <i class="fas fa-robot"></i> AI Asistan
                </button>
                <button class="btn btn-info btn-sm" onclick="previewPage()">
                    <i class="fas fa-eye"></i> Önizle
                </button>
                <button class="btn btn-light btn-sm" onclick="savePage(false)">
                    <i class="fas fa-save"></i> Taslak Kaydet
                </button>
                <button class="btn btn-success btn-sm" onclick="savePage(true)">
                    <i class="fas fa-check"></i> Yayınla
                </button>
            </div>
        </div>
    </div>
    
    <!-- GrapesJS Editor -->
    <div id="gjs"></div>
    
    <!-- AI Panel -->
    <div id="aiPanel" class="ai-panel">
        <h5>
            <span><i class="fas fa-robot"></i> AI Asistan</span>
            <button class="btn btn-sm btn-light" onclick="toggleAIPanel()">
                <i class="fas fa-times"></i>
            </button>
        </h5>
        
        <div class="mb-3">
            <label class="form-label">AI İle İçerik Oluştur</label>
            <textarea id="aiPrompt" class="form-control" rows="3" 
                      placeholder="Örn: Modern bir iletişim formu oluştur"></textarea>
            <button class="btn btn-primary btn-sm mt-2 w-100" onclick="generateWithAI()">
                <i class="fas fa-magic"></i> Oluştur
            </button>
        </div>
        
        <div id="aiResponse"></div>
        
        <hr>
        
        <h6>Hızlı Öneriler</h6>
        <div class="ai-suggestion" onclick="applySuggestion('hero')">
            <i class="fas fa-image"></i> Hero Bölümü Ekle
        </div>
        <div class="ai-suggestion" onclick="applySuggestion('features')">
            <i class="fas fa-th"></i> Özellik Kartları
        </div>
        <div class="ai-suggestion" onclick="applySuggestion('testimonial')">
            <i class="fas fa-quote-left"></i> Müşteri Yorumları
        </div>
        <div class="ai-suggestion" onclick="applySuggestion('contact')">
            <i class="fas fa-envelope"></i> İletişim Formu
        </div>
        <div class="ai-suggestion" onclick="applySuggestion('pricing')">
            <i class="fas fa-dollar-sign"></i> Fiyatlandırma Tablosu
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <!-- GrapesJS Core -->
    <script src="https://unpkg.com/grapesjs"></script>
    
    <!-- GrapesJS Plugins -->
    <script src="https://unpkg.com/grapesjs-preset-webpage"></script>
    <script src="https://unpkg.com/grapesjs-blocks-basic"></script>
    
    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        const pageId = <?php echo $pageId ? $pageId : 'null'; ?>;
        let editor;
        
        // GrapesJS Editor'ı başlat
        editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            width: 'auto',
            storageManager: false,
            plugins: ['gjs-preset-webpage'],
            pluginsOpts: {
                'gjs-preset-webpage': {
                    blocksBasicOpts: {
                        blocks: ['column1', 'column2', 'column3', 'column3-7', 'text', 'link', 'image', 'video', 'map'],
                        flexGrid: 1,
                    },
                    blocks: ['link-block', 'quote', 'text-basic'],
                    modalImportTitle: 'HTML İçe Aktar',
                    modalImportLabel: '<div>HTML kodunuzu buraya yapıştırın</div>',
                    modalImportContent: function(editor) {
                        return editor.getHtml() + '<style>' + editor.getCss() + '</style>'
                    },
                }
            },
            canvas: {
                styles: [
                    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
                    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
                ],
                scripts: [
                    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
                ],
            },
            deviceManager: {
                devices: [{
                    name: 'Desktop',
                    width: '',
                }, {
                    name: 'Tablet',
                    width: '768px',
                    widthMedia: '992px',
                }, {
                    name: 'Mobil',
                    width: '320px',
                    widthMedia: '480px',
                }]
            },
            panels: {
                defaults: [{
                    id: 'panel-devices',
                    el: '.panel__devices',
                    buttons: [{
                        id: 'device-desktop',
                        label: '<i class="fa fa-desktop"></i>',
                        command: 'set-device-desktop',
                        active: true,
                        togglable: false,
                    }, {
                        id: 'device-tablet',
                        label: '<i class="fa fa-tablet"></i>',
                        command: 'set-device-tablet',
                        togglable: false,
                    }, {
                        id: 'device-mobile',
                        label: '<i class="fa fa-mobile"></i>',
                        command: 'set-device-mobile',
                        togglable: false,
                    }],
                }]
            },
        });
        
        // Mevcut sayfa içeriğini yükle
        <?php if ($pageData): ?>
        editor.setComponents(<?php echo json_encode($pageData['content_html'] ?? ''); ?>);
        editor.setStyle(<?php echo json_encode($pageData['content_css'] ?? ''); ?>);
        <?php endif; ?>
        
        // Özel bloklar ekle
        editor.BlockManager.add('custom-hero', {
            label: 'Hero Section',
            category: 'Özel',
            content: `
                <section style="padding: 100px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align: center; color: white;">
                    <div class="container">
                        <h1 style="font-size: 3em; margin-bottom: 20px;">Başlık Buraya</h1>
                        <p style="font-size: 1.2em; margin-bottom: 30px;">Alt başlık veya açıklama metni</p>
                        <a href="#" class="btn btn-light btn-lg">Harekete Geç</a>
                    </div>
                </section>
            `
        });
        
        editor.BlockManager.add('custom-features', {
            label: 'Özellikler',
            category: 'Özel',
            content: `
                <section style="padding: 80px 20px;">
                    <div class="container">
                        <h2 style="text-align: center; margin-bottom: 50px;">Özellikler</h2>
                        <div class="row">
                            <div class="col-md-4 text-center" style="padding: 20px;">
                                <i class="fas fa-rocket" style="font-size: 3em; color: #667eea; margin-bottom: 20px;"></i>
                                <h4>Hızlı</h4>
                                <p>Çok hızlı performans</p>
                            </div>
                            <div class="col-md-4 text-center" style="padding: 20px;">
                                <i class="fas fa-shield-alt" style="font-size: 3em; color: #667eea; margin-bottom: 20px;"></i>
                                <h4>Güvenli</h4>
                                <p>Yüksek güvenlik standartları</p>
                            </div>
                            <div class="col-md-4 text-center" style="padding: 20px;">
                                <i class="fas fa-heart" style="font-size: 3em; color: #667eea; margin-bottom: 20px;"></i>
                                <h4>Güvenilir</h4>
                                <p>Her zaman yanınızda</p>
                            </div>
                        </div>
                    </div>
                </section>
            `
        });
        
        // Commands
        editor.Commands.add('set-device-desktop', {
            run: editor => editor.setDevice('Desktop')
        });
        editor.Commands.add('set-device-tablet', {
            run: editor => editor.setDevice('Tablet')
        });
        editor.Commands.add('set-device-mobile', {
            run: editor => editor.setDevice('Mobil')
        });
        
        // AI Panel Toggle
        function toggleAIPanel() {
            $('#aiPanel').toggleClass('show');
        }
        
        // AI ile içerik oluştur
        function generateWithAI() {
            const prompt = $('#aiPrompt').val().trim();
            
            if (!prompt) {
                alert('Lütfen bir açıklama girin!');
                return;
            }
            
            $('#aiResponse').html('<div class="text-center"><div class="spinner-border spinner-border-sm text-primary"></div> AI düşünüyor...</div>');
            
            $.ajax({
                url: 'ajax/ai-generate-content.php',
                method: 'POST',
                data: {
                    prompt: prompt,
                    type: 'page_content',
                    csrf_token: csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        $('#aiResponse').html(`
                            <div class="alert alert-success">
                                <strong>✅ Başarılı!</strong><br>
                                <small>İçerik oluşturuldu. Sayfaya eklemek için aşağıdaki butona tıklayın.</small>
                            </div>
                            <button class="btn btn-primary btn-sm w-100" onclick="applyAIContent('${escapeHtml(response.html)}')">
                                <i class="fas fa-plus"></i> Sayfaya Ekle
                            </button>
                        `);
                    } else {
                        $('#aiResponse').html(`
                            <div class="alert alert-danger">
                                <strong>❌ Hata:</strong> ${response.message}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#aiResponse').html(`
                        <div class="alert alert-danger">
                            <strong>❌ Hata:</strong> Sunucu hatası!
                        </div>
                    `);
                }
            });
        }
        
        // AI içeriğini uygula
        function applyAIContent(html) {
            editor.addComponents(html);
            alert('✅ İçerik sayfaya eklendi!');
        }
        
        // Hızlı öneriler
        function applySuggestion(type) {
            const templates = {
                hero: `<section style="padding: 100px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align: center; color: white;">
                    <div class="container">
                        <h1 style="font-size: 3em; margin-bottom: 20px;">Başlık Buraya</h1>
                        <p style="font-size: 1.2em; margin-bottom: 30px;">Alt başlık veya açıklama metni</p>
                        <a href="#" class="btn btn-light btn-lg">Harekete Geç</a>
                    </div>
                </section>`,
                features: `<section style="padding: 80px 20px;">
                    <div class="container">
                        <h2 style="text-align: center; margin-bottom: 50px;">Özellikler</h2>
                        <div class="row">
                            <div class="col-md-4 text-center" style="padding: 20px;">
                                <i class="fas fa-rocket" style="font-size: 3em; color: #667eea; margin-bottom: 20px;"></i>
                                <h4>Özellik 1</h4>
                                <p>Açıklama metni</p>
                            </div>
                            <div class="col-md-4 text-center" style="padding: 20px;">
                                <i class="fas fa-shield-alt" style="font-size: 3em; color: #667eea; margin-bottom: 20px;"></i>
                                <h4>Özellik 2</h4>
                                <p>Açıklama metni</p>
                            </div>
                            <div class="col-md-4 text-center" style="padding: 20px;">
                                <i class="fas fa-heart" style="font-size: 3em; color: #667eea; margin-bottom: 20px;"></i>
                                <h4>Özellik 3</h4>
                                <p>Açıklama metni</p>
                            </div>
                        </div>
                    </div>
                </section>`,
                testimonial: `<section style="padding: 80px 20px; background: #f8f9fa;">
                    <div class="container">
                        <h2 style="text-align: center; margin-bottom: 50px;">Müşterilerimiz Ne Diyor?</h2>
                        <div class="row">
                            <div class="col-md-4">
                                <div style="background: white; padding: 30px; border-radius: 10px; margin-bottom: 20px;">
                                    <p style="font-style: italic;">"Harika bir hizmet!"</p>
                                    <strong>- Ahmet Y.</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: white; padding: 30px; border-radius: 10px; margin-bottom: 20px;">
                                    <p style="font-style: italic;">"Çok memnun kaldım."</p>
                                    <strong>- Ayşe K.</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: white; padding: 30px; border-radius: 10px; margin-bottom: 20px;">
                                    <p style="font-style: italic;">"Kesinlikle tavsiye ederim."</p>
                                    <strong>- Mehmet T.</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>`,
                contact: `<section style="padding: 80px 20px;">
                    <div class="container">
                        <h2 style="text-align: center; margin-bottom: 50px;">İletişime Geçin</h2>
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <form>
                                    <div class="mb-3">
                                        <label class="form-label">Adınız</label>
                                        <input type="text" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">E-posta</label>
                                        <input type="email" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Mesajınız</label>
                                        <textarea class="form-control" rows="5" required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Gönder</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>`,
                pricing: `<section style="padding: 80px 20px;">
                    <div class="container">
                        <h2 style="text-align: center; margin-bottom: 50px;">Fiyatlandırma</h2>
                        <div class="row">
                            <div class="col-md-4">
                                <div style="background: white; padding: 30px; border-radius: 10px; border: 2px solid #e0e0e0; text-align: center;">
                                    <h4>Başlangıç</h4>
                                    <h2 style="color: #667eea; margin: 20px 0;">$9<small>/ay</small></h2>
                                    <ul style="list-style: none; padding: 0;">
                                        <li>✓ Özellik 1</li>
                                        <li>✓ Özellik 2</li>
                                        <li>✓ Özellik 3</li>
                                    </ul>
                                    <button class="btn btn-outline-primary mt-3">Seç</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px; text-align: center; color: white;">
                                    <h4>Profesyonel</h4>
                                    <h2 style="margin: 20px 0;">$29<small>/ay</small></h2>
                                    <ul style="list-style: none; padding: 0;">
                                        <li>✓ Tüm özellikler</li>
                                        <li>✓ Öncelikli destek</li>
                                        <li>✓ API erişimi</li>
                                    </ul>
                                    <button class="btn btn-light mt-3">Seç</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: white; padding: 30px; border-radius: 10px; border: 2px solid #e0e0e0; text-align: center;">
                                    <h4>Kurumsal</h4>
                                    <h2 style="color: #667eea; margin: 20px 0;">$99<small>/ay</small></h2>
                                    <ul style="list-style: none; padding: 0;">
                                        <li>✓ Sınırsız kullanıcı</li>
                                        <li>✓ 7/24 destek</li>
                                        <li>✓ Özel entegrasyon</li>
                                    </ul>
                                    <button class="btn btn-outline-primary mt-3">İletişim</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>`
            };
            
            if (templates[type]) {
                editor.addComponents(templates[type]);
                alert('✅ İçerik eklendi!');
            }
        }
        
        // Sayfayı kaydet
        function savePage(publish = false) {
            const title = $('#pageTitle').val().trim();
            
            if (!title) {
                alert('❌ Lütfen sayfa başlığı girin!');
                return;
            }
            
            const html = editor.getHtml();
            const css = editor.getCss();
            const status = publish ? 'published' : 'draft';
            
            $.ajax({
                url: 'ajax/page-builder-save.php',
                method: 'POST',
                data: {
                    page_id: pageId,
                    title: title,
                    html: html,
                    css: css,
                    status: status,
                    csrf_token: csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.message);
                        if (!pageId && response.page_id) {
                            window.location.href = 'page-builder.php?page_id=' + response.page_id;
                        }
                    } else {
                        alert('❌ ' + response.message);
                    }
                },
                error: function() {
                    alert('❌ Sunucu hatası!');
                }
            });
        }
        
        // Önizle
        function previewPage() {
            const html = editor.getHtml();
            const css = editor.getCss();
            
            const previewWindow = window.open('', '_blank');
            previewWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                    <style>${css}</style>
                </head>
                <body>
                    ${html}
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                </body>
                </html>
            `);
            previewWindow.document.close();
        }
        
        // HTML escape
        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>


