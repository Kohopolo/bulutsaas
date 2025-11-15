<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>Otel Yönetim Sistemi</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#1e293b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Otel Admin">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/icon-16x16.png">
    <link rel="apple-touch-icon" href="/assets/images/icon-192x192.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Tabler CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/css/tabler.min.css" rel="stylesheet">
    
    <!-- Electron Integration CSS -->
    <link href="/assets/css/electron-integration.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .page {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: #1e293b;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            flex-shrink: 0;
        }
        
        .sidebar .navbar-brand {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #334155;
            margin-bottom: 0;
        }
        
        .sidebar .nav-link {
            color: #94a3b8;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.125rem 0.5rem;
            transition: all 0.2s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #334155;
            color: #fff;
        }
        
        .main-content {
            margin-left: 280px;
            background: #f8fafc;
            min-height: 100vh;
            width: calc(100% - 280px);
            padding: 0;
        }
        
        .card {
            border: none;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .page-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
    
    <!-- Custom Badge Styles -->
    <link href="custom-badge-styles.css" rel="stylesheet">
</head>
<body>
    <div class="page">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="navbar-brand">
                <a href="index.php" class="text-white text-decoration-none">
                    <i class="fas fa-hotel me-2"></i>Otel Yönetimi
                </a>
            </div>
            
            <nav class="navbar-nav pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="resepsiyon.php">
                            <i class="fas fa-concierge-bell me-2"></i>Resepsiyon
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="rezervasyonlar.php">
                            <i class="fas fa-calendar-check me-2"></i>Rezervasyonlar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="musteriler.php">
                            <i class="fas fa-users me-2"></i>Müşteriler
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="odalar.php">
                            <i class="fas fa-bed me-2"></i>Odalar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="raporlar.php">
                            <i class="fas fa-chart-bar me-2"></i>Raporlar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="odeme-yonetimi.php">
                            <i class="fas fa-credit-card me-2"></i>Ödeme Yönetimi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kanal-listesi.php">
                            <i class="fas fa-network-wired me-2"></i>Kanal Yönetimi
                        </a>
                    </li>
                    
                    <!-- Yeni Modüller -->
                    <li class="nav-item">
                        <a class="nav-link" href="toplu-rezervasyon.php">
                            <i class="fas fa-users-cog me-2"></i>Toplu Rezervasyon
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="paket-tur-yonetimi.php">
                            <i class="fas fa-gift me-2"></i>Paket Tur Yönetimi
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="sadakat-programi.php">
                            <i class="fas fa-star me-2"></i>Sadakat Programı
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="ek-hizmet-onerileri.php">
                            <i class="fas fa-lightbulb me-2"></i>Ek Hizmet Önerileri
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="bekleme-listesi.php">
                            <i class="fas fa-clock me-2"></i>Bekleme Listesi
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="iptal-yonetimi.php">
                            <i class="fas fa-times-circle me-2"></i>İptal Yönetimi
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="ayarlar.php">
                            <i class="fas fa-cog me-2"></i>Ayarlar
                        </a>
                    </li>
                    <li class="nav-item mt-auto">
                        <div class="nav-link">
                            <div id="connection-status" class="badge bg-success">
                                <i class="fas fa-wifi me-1"></i>Online
                            </div>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">