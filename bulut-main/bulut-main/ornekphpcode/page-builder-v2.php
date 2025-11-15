<?php
/**
 * AI Powered Page Builder - V2 (Basitleştirilmiş)
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
    <title>Page Builder</title>
    
    <!-- GrapesJS CSS -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
    <link rel="stylesheet" href="https://unpkg.com/grapesjs-preset-webpage/dist/grapesjs-preset-webpage.min.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * { box-sizing: border-box; }
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
            font-family: Arial, sans-serif;
        }
        
        #navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 12px 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        #navbar h4 {
            margin: 0;
            font-size: 18px;
        }
        
        #navbar .btn {
            margin: 0 3px;
            padding: 6px 12px;
            font-size: 13px;
        }
        
        #navbar input[type="text"] {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            width: 300px;
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
        
        /* GrapesJS Panel Düzenlemeleri */
        .gjs-pn-panel {
            background: #f5f5f5;
        }
        
        .gjs-block {
            min-height: 60px;
        }
        
        /* AI Panel */
        #aiPanel {
            position: fixed;
            right: 20px;
            top: 80px;
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.2);
            padding: 20px;
            z-index: 10000;
            display: none;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        #aiPanel.show {
            display: block;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .ai-suggestion {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 12px;
            margin: 10px 0;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .ai-suggestion:hover {
            background: #e9ecef;
            transform: translateX(5px);
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
            <strong class="ms-2">
                <i class="fas fa-magic"></i> Page Builder
            </strong>
        </div>
        
        <div>
            <input type="text" id="pageTitle" placeholder="Sayfa Başlığı" value="<?php echo $pageTitle; ?>">
        </div>
        
        <div>
            <button class="btn btn-warning btn-sm" onclick="toggleAI()">
                <i class="fas fa-robot"></i> AI
            </button>
            <button class="btn btn-info btn-sm" onclick="preview()">
                <i class="fas fa-eye"></i> Önizle
            </button>
            <button class="btn btn-light btn-sm" onclick="save(false)">
                <i class="fas fa-save"></i> Kaydet
            </button>
            <button class="btn btn-success btn-sm" onclick="save(true)">
                <i class="fas fa-check"></i> Yayınla
            </button>
        </div>
    </div>
    
    <!-- Editor Container -->
    <div id="editor-container">
        <div id="gjs"></div>
    </div>
    
    <!-- AI Panel -->
    <div id="aiPanel">
        <h5>
            <i class="fas fa-robot"></i> AI Asistan
            <button class="btn btn-sm btn-light float-end" onclick="toggleAI()">
                <i class="fas fa-times"></i>
            </button>
        </h5>
        <hr>
        
        <div class="mb-3">
            <textarea id="aiPrompt" class="form-control" rows="3" placeholder="Ne istediğinizi yazın..."></textarea>
            <button class="btn btn-primary btn-sm mt-2 w-100" onclick="generateAI()">
                <i class="fas fa-magic"></i> Oluştur
            </button>
        </div>
        
        <div id="aiResponse"></div>
        
        <hr>
        
        <h6>Hızlı Şablonlar</h6>
        <div class="ai-suggestion" onclick="addTemplate('hero')">
            <i class="fas fa-image"></i> Hero Bölümü
        </div>
        <div class="ai-suggestion" onclick="addTemplate('features')">
            <i class="fas fa-th"></i> Özellik Kartları
        </div>
        <div class="ai-suggestion" onclick="addTemplate('contact')">
            <i class="fas fa-envelope"></i> İletişim Formu
        </div>
        <div class="ai-suggestion" onclick="addTemplate('pricing')">
            <i class="fas fa-dollar-sign"></i> Fiyatlandırma
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <!-- GrapesJS Core -->
    <script src="https://unpkg.com/grapesjs@0.21.7/dist/grapes.min.js"></script>
    
    <!-- GrapesJS Basic Blocks Plugin -->
    <script src="https://unpkg.com/grapesjs-blocks-basic"></script>
    
    <script>
        console.log('Page Builder başlatılıyor...');
        console.log('GrapesJS loaded:', typeof grapesjs !== 'undefined');
        
        const pageId = <?php echo $pageId ? $pageId : 'null'; ?>;
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        let editor;
        
        // Ana başlatma fonksiyonu
        function initPageBuilder() {
            // GrapesJS kontrolü
            if (typeof grapesjs === 'undefined') {
                alert('❌ HATA: GrapesJS yüklenemedi! Lütfen internet bağlantınızı kontrol edin.');
                console.error('GrapesJS is not defined. CDN may be blocked or unavailable.');
                document.getElementById('gjs').innerHTML = '<div style="padding:50px; text-align:center;"><h3>⚠️ Editor Yüklenemedi</h3><p>GrapesJS kütüphanesi yüklenemedi. Lütfen:<br>1. İnternet bağlantınızı kontrol edin<br>2. Firewall/Antivirus ayarlarını kontrol edin<br>3. Sayfayı yenileyin (F5)</p></div>';
                return;
            }
            
            // Plugin kontrolü
            console.log('Plugin kontrolü:', typeof window.grapesJSPresetWebpage);
        
        // GrapesJS Editor'ı başlat
        try {
            console.log('GrapesJS init başlatılıyor...');
            
            // Plugin varsa kullan, yoksa pluginsiz başlat
            const plugins = [];
            const pluginsOpts = {};
            
            if (typeof window.grapesJSBlocksBasic !== 'undefined') {
                console.log('✅ Blocks Basic plugin bulundu');
                plugins.push('gjs-blocks-basic');
                pluginsOpts['gjs-blocks-basic'] = {
                    flexGrid: true,
                };
            } else {
                console.log('ℹ️ Plugin yok, manuel bloklar kullanılıyor (normal)');
            }
            
            editor = grapesjs.init({
                container: '#gjs',
                height: '100%',
                width: 'auto',
                storageManager: false,
                plugins: plugins,
                pluginsOpts: pluginsOpts,
                canvas: {
                    styles: [
                        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
                        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
                    ],
                },
                deviceManager: {
                    devices: [{
                        name: 'Desktop',
                        width: '',
                    }, {
                        name: 'Tablet',
                        width: '768px',
                    }, {
                        name: 'Mobil',
                        width: '320px',
                    }]
                },
            });
            
            console.log('GrapesJS başlatıldı!', editor);
            
            // Temel blokları ekle
            addBasicBlocks();
            
            // Özel blokları ekle
            addCustomBlocks();
            
            // Mevcut içeriği yükle
            <?php if ($pageData): ?>
            try {
                editor.setComponents(<?php echo json_encode($pageData['content_html'] ?? ''); ?>);
                editor.setStyle(<?php echo json_encode($pageData['content_css'] ?? ''); ?>);
                console.log('✅ Mevcut içerik yüklendi');
            } catch (e) {
                console.error('İçerik yükleme hatası:', e);
            }
            <?php endif; ?>
            
        } catch (error) {
            console.error('GrapesJS başlatma hatası:', error);
            alert('Editor yüklenirken hata oluştu: ' + error.message);
        }
        }
        
        // Temel bloklar
        function addBasicBlocks() {
            const bm = editor.BlockManager;
            
            // Text Block
            bm.add('text', {
                label: '<i class="fa fa-text-width"></i> Text',
                category: 'Temel',
                content: '<div data-gjs-type="text">Metin yazın...</div>',
                attributes: { class: 'fa fa-text-width' }
            });
            
            // Image Block
            bm.add('image', {
                label: '<i class="fa fa-image"></i> Resim',
                category: 'Temel',
                content: { type: 'image' },
                attributes: { class: 'fa fa-image' }
            });
            
            // Link Block
            bm.add('link', {
                label: '<i class="fa fa-link"></i> Link',
                category: 'Temel',
                content: '<a href="#">Link</a>',
                attributes: { class: 'fa fa-link' }
            });
            
            // 1 Column
            bm.add('column1', {
                label: '<i class="fa fa-square"></i> 1 Kolon',
                category: 'Temel',
                content: '<div class="row"><div class="col-12" style="min-height: 50px;">İçerik</div></div>'
            });
            
            // 2 Columns
            bm.add('column2', {
                label: '<i class="fa fa-columns"></i> 2 Kolon',
                category: 'Temel',
                content: '<div class="row"><div class="col-6" style="min-height: 50px;">Kolon 1</div><div class="col-6" style="min-height: 50px;">Kolon 2</div></div>'
            });
            
            // 3 Columns
            bm.add('column3', {
                label: '<i class="fa fa-th"></i> 3 Kolon',
                category: 'Temel',
                content: '<div class="row"><div class="col-4" style="min-height: 50px;">Kolon 1</div><div class="col-4" style="min-height: 50px;">Kolon 2</div><div class="col-4" style="min-height: 50px;">Kolon 3</div></div>'
            });
            
            console.log('✅ Temel bloklar eklendi');
        }
        
        // Özel bloklar
        function addCustomBlocks() {
            editor.BlockManager.add('hero-section', {
                label: 'Hero',
                category: 'Özel',
                content: `
                    <section style="padding:100px 20px; background:linear-gradient(135deg,#667eea,#764ba2); text-align:center; color:white;">
                        <h1 style="font-size:3em; margin-bottom:20px;">Başlık</h1>
                        <p style="font-size:1.2em; margin-bottom:30px;">Alt başlık</p>
                        <a href="#" class="btn btn-light btn-lg">Başla</a>
                    </section>
                `
            });
        }
        
        // AI Panel Toggle
        function toggleAI() {
            $('#aiPanel').toggleClass('show');
        }
        
        // AI ile İçerik Oluştur
        function generateAI() {
            const prompt = $('#aiPrompt').val().trim();
            if (!prompt) {
                alert('Lütfen bir açıklama girin!');
                return;
            }
            
            $('#aiResponse').html('<div class="text-center"><div class="spinner-border spinner-border-sm"></div> Oluşturuluyor...</div>');
            
            $.post('ajax/ai-generate-content.php', {
                prompt: prompt,
                type: 'page_content',
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    $('#aiResponse').html('<div class="alert alert-success">✅ Başarılı! <button class="btn btn-sm btn-primary w-100 mt-2" onclick="applyAI(\'' + response.html.replace(/'/g, "\\'") + '\')">Ekle</button></div>');
                } else {
                    $('#aiResponse').html('<div class="alert alert-danger">❌ ' + response.message + '</div>');
                }
            }).fail(function() {
                $('#aiResponse').html('<div class="alert alert-danger">❌ Sunucu hatası!</div>');
            });
        }
        
        // AI içeriğini ekle
        function applyAI(html) {
            editor.addComponents(html);
            alert('✅ İçerik eklendi!');
        }
        
        // Şablon ekle
        function addTemplate(type) {
            const templates = {
                hero: `<section style="padding:100px 20px; background:linear-gradient(135deg,#667eea,#764ba2); text-align:center; color:white;"><div class="container"><h1 style="font-size:3em;">Başlık</h1><p>Alt başlık</p><a href="#" class="btn btn-light">Başla</a></div></section>`,
                features: `<section style="padding:80px 20px;"><div class="container"><h2 style="text-align:center; margin-bottom:50px;">Özellikler</h2><div class="row"><div class="col-md-4 text-center"><i class="fas fa-rocket" style="font-size:3em; color:#667eea;"></i><h4>Özellik 1</h4><p>Açıklama</p></div><div class="col-md-4 text-center"><i class="fas fa-shield-alt" style="font-size:3em; color:#667eea;"></i><h4>Özellik 2</h4><p>Açıklama</p></div><div class="col-md-4 text-center"><i class="fas fa-heart" style="font-size:3em; color:#667eea;"></i><h4>Özellik 3</h4><p>Açıklama</p></div></div></div></section>`,
                contact: `<section style="padding:80px 20px;"><div class="container"><h2 style="text-align:center; margin-bottom:50px;">İletişim</h2><div class="row justify-content-center"><div class="col-md-6"><form><div class="mb-3"><input type="text" class="form-control" placeholder="Adınız"></div><div class="mb-3"><input type="email" class="form-control" placeholder="E-posta"></div><div class="mb-3"><textarea class="form-control" rows="5" placeholder="Mesajınız"></textarea></div><button type="submit" class="btn btn-primary w-100">Gönder</button></form></div></div></div></section>`,
                pricing: `<section style="padding:80px 20px;"><div class="container"><h2 style="text-align:center; margin-bottom:50px;">Fiyatlandırma</h2><div class="row"><div class="col-md-4"><div style="border:2px solid #e0e0e0; padding:30px; border-radius:10px; text-align:center;"><h4>Temel</h4><h2 style="color:#667eea;">$9<small>/ay</small></h2><ul style="list-style:none; padding:0;"><li>✓ Özellik 1</li><li>✓ Özellik 2</li></ul><button class="btn btn-outline-primary">Seç</button></div></div></div></div></section>`
            };
            
            if (templates[type]) {
                editor.addComponents(templates[type]);
                alert('✅ Şablon eklendi!');
            }
        }
        
        // Kaydet
        function save(publish = false) {
            const title = $('#pageTitle').val().trim();
            if (!title) {
                alert('❌ Lütfen sayfa başlığı girin!');
                return;
            }
            
            const html = editor.getHtml();
            const css = editor.getCss();
            
            $.post('ajax/page-builder-save.php', {
                page_id: pageId,
                title: title,
                html: html,
                css: css,
                status: publish ? 'published' : 'draft',
                csrf_token: csrfToken
            }).done(function(response) {
                if (response.success) {
                    alert('✅ ' + response.message);
                    if (!pageId && response.page_id) {
                        window.location.href = 'page-builder-v2.php?page_id=' + response.page_id;
                    }
                } else {
                    alert('❌ ' + response.message);
                }
            }).fail(function() {
                alert('❌ Sunucu hatası!');
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
                <style>${css}</style>
                </head><body>${html}</body></html>
            `);
            win.document.close();
        }
        
        // Page Builder'ı başlat
        initPageBuilder();
    </script>
</body>
</html>

