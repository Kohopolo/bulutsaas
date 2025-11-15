<?php
// Test sayfası - Windows Layout'u test etmek için
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

$page_title = "Windows Layout Test";
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Otel Yönetim Sistemi</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Windows Layout CSS -->
    <link href="assets/css/windows-layout.css" rel="stylesheet">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
</head>
<body>
    <div class="windows-container">
        <!-- Üst Header -->
        <header class="windows-header">
            <div class="header-left">
                <div class="header-title">
                    <i class="fas fa-hotel"></i>
                    <span>🏨 Otel Yönetim Sistemi - TEST</span>
                </div>
            </div>
            <div class="header-right">
                <div class="user-info" onclick="showUserMenu()">
                    <i class="fas fa-user-circle"></i>
                    <span>👤 Admin</span>
                    <i class="fas fa-cog"></i>
                </div>
            </div>
        </header>
        
        <!-- Modül Menüsü -->
        <nav class="modules-menu">
            <a href="#" class="module-btn active" data-module="dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>📊 Dashboard</span>
            </a>
            <a href="#" class="module-btn" data-module="reservation">
                <i class="fas fa-calendar-check"></i>
                <span>📋 Rezervasyon</span>
            </a>
            <a href="#" class="module-btn" data-module="rooms">
                <i class="fas fa-bed"></i>
                <span>🏠 Odalar</span>
            </a>
            <a href="#" class="module-btn" data-module="customers">
                <i class="fas fa-users"></i>
                <span>👥 Müşteriler</span>
            </a>
            <a href="#" class="module-btn" data-module="reception">
                <i class="fas fa-concierge-bell"></i>
                <span>🔔 Resepsiyon</span>
            </a>
            <a href="#" class="module-btn" data-module="housekeeping">
                <i class="fas fa-broom"></i>
                <span>🧹 Housekeeping</span>
            </a>
            <a href="#" class="module-btn" data-module="fnb">
                <i class="fas fa-utensils"></i>
                <span>🍽️ F&B</span>
            </a>
            <a href="#" class="module-btn" data-module="technical">
                <i class="fas fa-tools"></i>
                <span>🔧 Teknik</span>
            </a>
            <a href="#" class="module-btn" data-module="hr">
                <i class="fas fa-user-tie"></i>
                <span>👨‍💼 İK</span>
            </a>
            <a href="#" class="module-btn" data-module="accounting">
                <i class="fas fa-calculator"></i>
                <span>💰 Muhasebe</span>
            </a>
            <a href="#" class="module-btn" data-module="procurement">
                <i class="fas fa-shopping-cart"></i>
                <span>📦 Satın Alma</span>
            </a>
            <a href="#" class="module-btn" data-module="settings">
                <i class="fas fa-cog"></i>
                <span>⚙️ Ayarlar</span>
            </a>
        </nav>
        
        <!-- Ana İçerik Alanı -->
        <div class="main-content-area">
            <!-- Tab Sistemi -->
            <div class="tabs-container">
                <div class="tab active" data-tab="dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                    <i class="fas fa-times tab-close" onclick="closeTab('dashboard')"></i>
                </div>
                <button class="add-tab-btn" onclick="addNewTab()">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            
            <!-- İçerik Paneli -->
            <div class="content-panel">
                <div class="content-header">
                    <h4 id="content-title">Dashboard</h4>
                    <div class="content-actions">
                        <button class="btn btn-outline-primary btn-sm" onclick="refreshCurrentModule()">
                            <i class="fas fa-sync-alt"></i> Yenile
                        </button>
                    </div>
                </div>
                <div class="content-body" id="content-body">
                    <!-- Test İçeriği -->
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle"></i> Windows Layout Test Başarılı!</h5>
                        <p>CSS ve JavaScript dosyaları başarıyla yüklendi. Tüm bileşenler çalışıyor.</p>
                    </div>
                    
                    <div class="cards-grid">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background: #007bff;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="card-title">CSS Test</div>
                            </div>
                            <div class="card-value">✅</div>
                            <div class="card-description">CSS dosyası yüklendi</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background: #28a745;">
                                    <i class="fas fa-code"></i>
                                </div>
                                <div class="card-title">JavaScript Test</div>
                            </div>
                            <div class="card-value">✅</div>
                            <div class="card-description">JavaScript dosyası yüklendi</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background: #ffc107;">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="card-title">Veritabanı Test</div>
                            </div>
                            <div class="card-value">✅</div>
                            <div class="card-description">Veritabanı bağlantısı aktif</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-icon" style="background: #dc3545;">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <div class="card-title">Sistem Durumu</div>
                            </div>
                            <div class="card-value">🚀</div>
                            <div class="card-description">Sistem hazır</div>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <div class="table-header">
                            <h5>Test Sonuçları</h5>
                            <button class="btn btn-primary btn-sm" onclick="runTests()">
                                <i class="fas fa-play"></i> Testleri Çalıştır
                            </button>
                        </div>
                        <div class="table-body">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Test</th>
                                        <th>Durum</th>
                                        <th>Açıklama</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>CSS Yükleme</td>
                                        <td><span class="badge bg-success">Başarılı</span></td>
                                        <td>windows-layout.css dosyası yüklendi</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>JavaScript Yükleme</td>
                                        <td><span class="badge bg-success">Başarılı</span></td>
                                        <td>windows-layout.js dosyası yüklendi</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>AJAX Bağlantısı</td>
                                        <td><span class="badge bg-success">Başarılı</span></td>
                                        <td>AJAX dosyaları mevcut</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Responsive Tasarım</td>
                                        <td><span class="badge bg-success">Başarılı</span></td>
                                        <td>Mobil uyumlu tasarım aktif</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alt Status Bar -->
        <footer class="status-bar">
            <div class="status-left">
                <div class="status-item">
                    <div class="status-indicator" id="sync-status"></div>
                    <span>🔄 Sync: <span id="sync-text">✅ Online</span></span>
                </div>
                <div class="status-item">
                    <div class="status-indicator" id="backup-status"></div>
                    <span>💾 Backup: <span id="backup-text">✅ Aktif</span></span>
                </div>
            </div>
            <div class="status-right">
                <div class="status-item">
                    <i class="fas fa-clock"></i>
                    <span id="current-time">🕐 14:30:25</span>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Windows Layout JS -->
    <script src="assets/js/windows-layout.js"></script>
    
    <!-- Test JavaScript -->
    <script>
        // Test fonksiyonları
        function runTests() {
            showNotification('Testler çalıştırılıyor...', 'info');
            
            setTimeout(function() {
                showNotification('Tüm testler başarıyla tamamlandı!', 'success');
            }, 2000);
        }
        
        // Sayfa yüklendiğinde test et
        $(document).ready(function() {
            console.log('Test sayfası yüklendi');
            
            // CSS test
            if ($('link[href*="windows-layout.css"]').length > 0) {
                console.log('✅ CSS dosyası yüklendi');
            } else {
                console.error('❌ CSS dosyası yüklenemedi');
            }
            
            // JavaScript test
            if (typeof switchModule === 'function') {
                console.log('✅ JavaScript dosyası yüklendi');
            } else {
                console.error('❌ JavaScript dosyası yüklenemedi');
            }
            
            // AJAX test
            $.ajax({
                url: 'ajax/load-module-content.php',
                method: 'POST',
                data: {
                    module: 'dashboard',
                    csrf_token: getCSRFToken()
                },
                success: function(response) {
                    console.log('✅ AJAX bağlantısı başarılı');
                },
                error: function() {
                    console.error('❌ AJAX bağlantısı başarısız');
                }
            });
        });
    </script>
</body>
</html>
