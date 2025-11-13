<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Page Builder - Test Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .test-card { background: white; border-radius: 15px; padding: 30px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .test-item { padding: 15px; border-left: 4px solid #28a745; background: #f8f9fa; margin: 10px 0; border-radius: 5px; }
        .test-item.warning { border-color: #ffc107; }
        .test-item.error { border-color: #dc3545; }
        h1 { color: white; text-align: center; margin-bottom: 30px; }
        .btn-test { margin: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-robot"></i> AI PAGE BUILDER - TEST SUITE</h1>
        
        <!-- Test 1: Sistem Kontrolü -->
        <div class="test-card">
            <h3><i class="fas fa-cogs"></i> 1. Sistem Kontrolü</h3>
            <hr>
            <div class="test-item">
                <strong>✅ Database:</strong> Bağlantı testi
                <a href="test-page-builder-system.php" class="btn btn-sm btn-primary btn-test float-end" target="_blank">
                    <i class="fas fa-play"></i> Test Et
                </a>
            </div>
            <div class="test-item">
                <strong>✅ Tablolar:</strong> 8 tablo kontrolü
            </div>
            <div class="test-item">
                <strong>✅ Yetkiler:</strong> 16 yetki tanımı
            </div>
            <div class="test-item">
                <strong>✅ AI Providers:</strong> 6 provider entegre
            </div>
        </div>
        
        <!-- Test 2: AI Ayarları -->
        <div class="test-card">
            <h3><i class="fas fa-brain"></i> 2. AI Ayarları</h3>
            <hr>
            <div class="test-item warning">
                <strong>⚠️ AI Provider Yapılandırması:</strong> API Key tanımlama gerekli
                <a href="ai-settings.php" class="btn btn-sm btn-warning btn-test float-end" target="_blank">
                    <i class="fas fa-cog"></i> Ayarla
                </a>
            </div>
            <div class="test-item">
                <strong>📝 Önerilen Provider:</strong> Groq (Ücretsiz, Hızlı)
            </div>
            <div class="test-item">
                <strong>🔗 API Key:</strong> <a href="https://console.groq.com" target="_blank">console.groq.com</a>
            </div>
        </div>
        
        <!-- Test 3: Page Builder -->
        <div class="test-card">
            <h3><i class="fas fa-magic"></i> 3. Page Builder (GrapesJS)</h3>
            <hr>
            <div class="test-item">
                <strong>✅ GrapesJS Test:</strong> Basit drag & drop testi
                <a href="test-grapesjs.php" class="btn btn-sm btn-info btn-test float-end" target="_blank">
                    <i class="fas fa-play"></i> Test Et
                </a>
            </div>
            <div class="test-item">
                <strong>✅ Page Builder V2:</strong> Tam özellikli editor
                <a href="page-builder-v2.php" class="btn btn-sm btn-success btn-test float-end" target="_blank">
                    <i class="fas fa-edit"></i> Aç
                </a>
            </div>
            <div class="test-item">
                <strong>✅ Sayfa Listesi:</strong> Oluşturulan sayfaları görüntüle
                <a href="page-list.php" class="btn btn-sm btn-primary btn-test float-end" target="_blank">
                    <i class="fas fa-list"></i> Aç
                </a>
            </div>
        </div>
        
        <!-- Test 4: Form Builder -->
        <div class="test-card">
            <h3><i class="fas fa-wpforms"></i> 4. Form Builder</h3>
            <hr>
            <div class="test-item">
                <strong>✅ Form Oluşturucu:</strong> Drag & drop form builder
                <a href="form-builder.php" class="btn btn-sm btn-success btn-test float-end" target="_blank">
                    <i class="fas fa-edit"></i> Aç
                </a>
            </div>
            <div class="test-item">
                <strong>📋 Özellikler:</strong> 9 alan tipi, embed kod, e-posta bildirimi
            </div>
        </div>
        
        <!-- Test 5: AI İçerik Üretimi -->
        <div class="test-card">
            <h3><i class="fas fa-robot"></i> 5. AI İçerik Üretimi</h3>
            <hr>
            <div class="test-item warning">
                <strong>⚠️ Test için AI Provider gerekli!</strong>
            </div>
            <div class="test-item">
                <strong>📝 Test Adımları:</strong>
                <ol>
                    <li>AI Ayarlarından provider yapılandır</li>
                    <li>Page Builder'ı aç</li>
                    <li>"AI Asistan" butonuna tıkla</li>
                    <li>Prompt gir (örn: "Modern bir hero bölümü oluştur")</li>
                    <li>AI'ın ürettiği içeriği sayfaya ekle</li>
                </ol>
            </div>
        </div>
        
        <!-- Test 6: Frontend Render -->
        <div class="test-card">
            <h3><i class="fas fa-globe"></i> 6. Frontend Sayfa Gösterimi</h3>
            <hr>
            <div class="test-item">
                <strong>📝 Test Adımları:</strong>
                <ol>
                    <li>Page Builder'da bir sayfa oluştur</li>
                    <li>"Yayınla" butonuna tıkla</li>
                    <li>URL'ye git: <code>/page/{slug}</code></li>
                    <li>Sayfa render edilip SEO meta etiketlerini kontrol et</li>
                </ol>
            </div>
            <div class="test-item">
                <strong>✅ .htaccess:</strong> URL rewrite kuralı eklendi
            </div>
        </div>
        
        <!-- Hızlı Başlangıç -->
        <div class="test-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <h3><i class="fas fa-rocket"></i> 🚀 Hızlı Başlangıç</h3>
            <hr style="border-color: white;">
            <div style="background: rgba(255,255,255,0.2); padding: 20px; border-radius: 10px;">
                <h5>1️⃣ AI Ayarları</h5>
                <p class="mb-2">Groq API Key al ve yapılandır</p>
                <a href="ai-settings.php" class="btn btn-light" target="_blank">
                    <i class="fas fa-cog"></i> AI Ayarlarına Git
                </a>
                
                <hr style="border-color: white; margin: 20px 0;">
                
                <h5>2️⃣ İlk Sayfayı Oluştur</h5>
                <p class="mb-2">Page Builder ile AI destekli sayfa oluştur</p>
                <a href="page-builder-v2.php" class="btn btn-light" target="_blank">
                    <i class="fas fa-magic"></i> Page Builder'ı Aç
                </a>
                
                <hr style="border-color: white; margin: 20px 0;">
                
                <h5>3️⃣ Form Oluştur</h5>
                <p class="mb-2">Özel formlar oluştur ve sayfana ekle</p>
                <a href="form-builder.php" class="btn btn-light" target="_blank">
                    <i class="fas fa-wpforms"></i> Form Builder'ı Aç
                </a>
            </div>
        </div>
        
        <!-- Dokümantasyon -->
        <div class="test-card">
            <h3><i class="fas fa-book"></i> Dokümantasyon</h3>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h5>AI Providers</h5>
                    <ul>
                        <li><strong>Groq:</strong> console.groq.com (Ücretsiz)</li>
                        <li><strong>Gemini:</strong> makersuite.google.com (Ücretsiz)</li>
                        <li><strong>Hugging Face:</strong> huggingface.co (Ücretsiz)</li>
                        <li><strong>OpenAI:</strong> platform.openai.com (Ücretli)</li>
                        <li><strong>Claude:</strong> console.anthropic.com (Ücretli)</li>
                        <li><strong>Ollama:</strong> Local AI (Ücretsiz)</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Özellikler</h5>
                    <ul>
                        <li>✅ GrapesJS Drag & Drop Editor</li>
                        <li>✅ Bootstrap 5 Entegrasyonu</li>
                        <li>✅ Responsive Tasarım</li>
                        <li>✅ AI İçerik Üretimi</li>
                        <li>✅ SEO Optimizasyonu</li>
                        <li>✅ Form Builder</li>
                        <li>✅ Analytics</li>
                        <li>✅ Revizyon Geçmişi</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <a href="index.php" class="btn btn-light btn-lg">
                <i class="fas fa-home"></i> Admin Dashboard'a Dön
            </a>
        </div>
    </div>
</body>
</html>


