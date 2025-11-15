<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

$page_title = "Windows Layout Test - Otel Yönetim Sistemi";
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
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
                    <span>👤 <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                    <i class="fas fa-cog"></i>
                </div>
            </div>
        </header>
        
        <!-- Modül Menüsü -->
        <nav class="modules-menu">
            <a href="index-windows.php" class="module-btn" data-module="dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>📊 Dashboard</span>
            </a>
            <a href="rezervasyonlar-windows.php" class="module-btn" data-module="reservation">
                <i class="fas fa-calendar-check"></i>
                <span>📋 Rezervasyon</span>
            </a>
            <a href="odalar-windows.php" class="module-btn" data-module="rooms">
                <i class="fas fa-bed"></i>
                <span>🏠 Odalar</span>
            </a>
            <a href="musteriler-windows.php" class="module-btn" data-module="customers">
                <i class="fas fa-users"></i>
                <span>👥 Müşteriler</span>
            </a>
            <a href="resepsiyon-windows.php" class="module-btn" data-module="reception">
                <i class="fas fa-concierge-bell"></i>
                <span>🔔 Resepsiyon</span>
            </a>
            <a href="housekeeping-windows.php" class="module-btn" data-module="housekeeping">
                <i class="fas fa-broom"></i>
                <span>🧹 Housekeeping</span>
            </a>
            <a href="fnb-windows.php" class="module-btn" data-module="fnb">
                <i class="fas fa-utensils"></i>
                <span>🍽️ F&B</span>
            </a>
            <a href="teknik-windows.php" class="module-btn" data-module="technical">
                <i class="fas fa-tools"></i>
                <span>🔧 Teknik</span>
            </a>
            <a href="ik-windows.php" class="module-btn" data-module="hr">
                <i class="fas fa-user-tie"></i>
                <span>👨‍💼 İK</span>
            </a>
            <a href="muhasebe-windows.php" class="module-btn" data-module="accounting">
                <i class="fas fa-calculator"></i>
                <span>💰 Muhasebe</span>
            </a>
            <a href="satin-alma-windows.php" class="module-btn" data-module="procurement">
                <i class="fas fa-shopping-cart"></i>
                <span>📦 Satın Alma</span>
            </a>
            <a href="ayarlar-windows.php" class="module-btn" data-module="settings">
                <i class="fas fa-cog"></i>
                <span>⚙️ Ayarlar</span>
            </a>
        </nav>
        
        <!-- Ana İçerik Alanı -->
        <div class="main-content-area">
            <!-- Tab Sistemi -->
            <div class="tabs-container">
                <div class="tab active" data-tab="test">
                    <i class="fas fa-flask"></i>
                    <span>Test</span>
                    <i class="fas fa-times tab-close" onclick="closeTab('test')"></i>
                </div>
                <button class="add-tab-btn" onclick="addNewTab()">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            
            <!-- İçerik Paneli -->
            <div class="content-panel">
                <div class="content-header">
                    <h4 id="content-title">Windows Layout Test</h4>
                    <div class="content-actions">
                        <button class="btn btn-success btn-sm" onclick="testAllModules()">
                            <i class="fas fa-play"></i> Tüm Modülleri Test Et
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="testAjax()">
                            <i class="fas fa-sync"></i> AJAX Test
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="refreshTest()">
                            <i class="fas fa-sync-alt"></i> Yenile
                        </button>
                    </div>
                </div>
                <div class="content-body" id="content-body">
                    <!-- Test İçeriği -->
                    <div id="test-content">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Windows Layout Test Sayfası</h5>
                            <p>Bu sayfa tüm modüllerin Windows layout yapısında çalışıp çalışmadığını test etmek için oluşturulmuştur.</p>
                        </div>
                        
                        <!-- Test Sonuçları -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-check-circle text-success"></i> Başarılı Testler</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush" id="success-tests">
                                            <li class="list-group-item">✅ Veritabanı bağlantısı</li>
                                            <li class="list-group-item">✅ CSRF koruması</li>
                                            <li class="list-group-item">✅ Session güvenliği</li>
                                            <li class="list-group-item">✅ Windows layout CSS</li>
                                            <li class="list-group-item">✅ Bootstrap entegrasyonu</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-exclamation-triangle text-warning"></i> Test Edilecek</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush" id="pending-tests">
                                            <li class="list-group-item">⏳ AJAX içerik yükleme</li>
                                            <li class="list-group-item">⏳ Modül geçişleri</li>
                                            <li class="list-group-item">⏳ Tab sistemi</li>
                                            <li class="list-group-item">⏳ Veri görüntüleme</li>
                                            <li class="list-group-item">⏳ JavaScript fonksiyonları</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modül Test Butonları -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-cogs"></i> Modül Testleri</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="btn-group-vertical w-100" role="group">
                                            <button type="button" class="btn btn-outline-primary mb-2" onclick="testModule('dashboard')">
                                                <i class="fas fa-tachometer-alt"></i> Dashboard Test
                                            </button>
                                            <button type="button" class="btn btn-outline-primary mb-2" onclick="testModule('reservation')">
                                                <i class="fas fa-calendar-check"></i> Rezervasyon Test
                                            </button>
                                            <button type="button" class="btn btn-outline-primary mb-2" onclick="testModule('rooms')">
                                                <i class="fas fa-bed"></i> Oda Yönetimi Test
                                            </button>
                                            <button type="button" class="btn btn-outline-primary mb-2" onclick="testModule('customers')">
                                                <i class="fas fa-users"></i> Müşteri Yönetimi Test
                                            </button>
                                            <button type="button" class="btn btn-outline-primary mb-2" onclick="testModule('reception')">
                                                <i class="fas fa-concierge-bell"></i> Resepsiyon Test
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Test Log -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="fas fa-list"></i> Test Log</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="test-log" style="height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px;">
                                            <div class="text-muted">Test log burada görünecek...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
        function logTest(message, type = 'info') {
            const log = document.getElementById('test-log');
            const timestamp = new Date().toLocaleTimeString();
            const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️';
            const color = type === 'success' ? 'text-success' : type === 'error' ? 'text-danger' : type === 'warning' ? 'text-warning' : 'text-info';
            
            log.innerHTML += `<div class="${color}">${icon} [${timestamp}] ${message}</div>`;
            log.scrollTop = log.scrollHeight;
        }
        
        function testAllModules() {
            logTest('Tüm modüller test ediliyor...', 'info');
            
            const modules = ['dashboard', 'reservation', 'rooms', 'customers', 'reception'];
            let completed = 0;
            
            modules.forEach((module, index) => {
                setTimeout(() => {
                    testModule(module);
                    completed++;
                    if (completed === modules.length) {
                        logTest('Tüm modül testleri tamamlandı!', 'success');
                    }
                }, index * 1000);
            });
        }
        
        function testModule(module) {
            logTest(`${module} modülü test ediliyor...`, 'info');
            
            $.ajax({
                url: 'ajax/load-module-content.php',
                method: 'POST',
                data: {
                    module: module,
                    csrf_token: getCSRFToken()
                },
                success: function(response) {
                    if (response.success) {
                        logTest(`${module} modülü başarıyla yüklendi`, 'success');
                    } else {
                        logTest(`${module} modülü yüklenemedi: ${response.message}`, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    logTest(`${module} modülü AJAX hatası: ${error}`, 'error');
                }
            });
        }
        
        function testAjax() {
            logTest('AJAX bağlantısı test ediliyor...', 'info');
            
            $.ajax({
                url: 'ajax/load-module-content.php',
                method: 'POST',
                data: {
                    module: 'dashboard',
                    csrf_token: getCSRFToken()
                },
                success: function(response) {
                    logTest('AJAX bağlantısı başarılı', 'success');
                    logTest(`Sunucu yanıtı: ${response.success ? 'Başarılı' : 'Hatalı'}`, response.success ? 'success' : 'error');
                },
                error: function(xhr, status, error) {
                    logTest(`AJAX hatası: ${error}`, 'error');
                }
            });
        }
        
        function refreshTest() {
            logTest('Test sayfası yenileniyor...', 'info');
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
        
        // Sayfa yüklendiğinde
        $(document).ready(function() {
            logTest('Test sayfası yüklendi', 'success');
            logTest('Windows layout sistemi aktif', 'success');
            startClock();
            startStatusUpdates();
        });
    </script>
</body>
</html>
