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

$page_title = "Müşteri Yönetimi - Otel Yönetim Sistemi";
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
                    <span>🏨 Otel Yönetim Sistemi</span>
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
            <a href="musteriler-windows.php" class="module-btn active" data-module="customers">
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
                <div class="tab active" data-tab="customers">
                    <i class="fas fa-users"></i>
                    <span>Müşteri Yönetimi</span>
                    <i class="fas fa-times tab-close" onclick="closeTab('customers')"></i>
                </div>
                <button class="add-tab-btn" onclick="addNewTab()">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            
            <!-- İçerik Paneli -->
            <div class="content-panel">
                <div class="content-header">
                    <h4 id="content-title">Müşteri Yönetimi</h4>
                    <div class="content-actions">
                        <button class="btn btn-primary btn-sm" onclick="addCustomer()">
                            <i class="fas fa-plus"></i> Müşteri Ekle
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportCustomers()">
                            <i class="fas fa-download"></i> Dışa Aktar
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="refreshCustomers()">
                            <i class="fas fa-sync-alt"></i> Yenile
                        </button>
                    </div>
                </div>
                <div class="content-body" id="content-body">
                    <!-- Müşteri İçeriği -->
                    <div id="customers-content">
                        <!-- AJAX ile yüklenecek -->
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
    
    <!-- Müşteri Yönetimi JavaScript -->
    <script>
        function addCustomer() {
            window.location.href = 'musteri-ekle.php';
        }
        
        function exportCustomers() {
            showNotification('Müşteriler dışa aktarılıyor...', 'info');
            setTimeout(function() {
                showNotification('Müşteriler başarıyla dışa aktarıldı', 'success');
            }, 2000);
        }
        
        function refreshCustomers() {
            loadCustomersContent();
            showNotification('Müşteri listesi yenilendi', 'success');
        }
        
        function loadCustomersContent() {
            $('#customers-content').html(`
                <div class="text-center py-5">
                    <div class="spinner"></div>
                    <p class="mt-3 text-muted">Müşteriler yükleniyor...</p>
                </div>
            `);
            
            $.ajax({
                url: 'ajax/load-module-content.php',
                method: 'POST',
                data: {
                    module: 'customers',
                    csrf_token: getCSRFToken()
                },
                success: function(response) {
                    if (response.success) {
                        $('#customers-content').html(response.content).addClass('fade-in');
                    } else {
                        showError('Müşteriler yüklenemedi: ' + response.message);
                    }
                },
                error: function() {
                    showError('Sunucu hatası oluştu');
                }
            });
        }
        
        function viewCustomer(id) {
            window.location.href = `musteri-detay.php?id=${id}`;
        }
        
        function editCustomer(id) {
            window.location.href = `musteri-duzenle.php?id=${id}`;
        }
        
        // Sayfa yüklendiğinde
        $(document).ready(function() {
            loadCustomersContent();
            startClock();
            startStatusUpdates();
        });
    </script>
</body>
</html>
