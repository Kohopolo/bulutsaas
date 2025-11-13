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

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasModulePermission('resepsiyon')) {
    $_SESSION['error_message'] = 'Resepsiyon modülüne erişim yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$page_title = "Resepsiyon Ana Ekran";

// Hızlı istatistikler
$bugun = date('Y-m-d');
$stats = [];

// Bugünkü check-in'ler
$bugun_checkin_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM rezervasyonlar 
    WHERE DATE(giris_tarihi) = ? AND durum = 'check_in'
", [$bugun]);
$stats['bugun_checkin'] = $bugun_checkin_result['toplam'] ?? 0;

// Bugünkü check-out'lar
$bugun_checkout_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM rezervasyonlar 
    WHERE DATE(cikis_tarihi) = ? AND durum = 'check_out'
", [$bugun]);
$stats['bugun_checkout'] = $bugun_checkout_result['toplam'] ?? 0;

// Bekleyen rezervasyonlar
$bekleyen_rezervasyon_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM rezervasyonlar 
    WHERE durum = 'beklemede'
");
$stats['bekleyen_rezervasyon'] = $bekleyen_rezervasyon_result['toplam'] ?? 0;

// Dolu oda sayısı
$dolu_oda_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM oda_numaralari 
    WHERE durum = 'dolu'
");
$stats['dolu_oda'] = $dolu_oda_result['toplam'] ?? 0;

// Müsait oda sayısı
$musait_oda_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM oda_numaralari 
    WHERE durum = 'musait'
");
$stats['musait_oda'] = $musait_oda_result['toplam'] ?? 0;

// Temizlik bekleyen oda sayısı
$temizlik_bekleyen_result = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM oda_numaralari 
    WHERE durum = 'temizlik_bekliyor'
");
$stats['temizlik_bekleyen'] = $temizlik_bekleyen_result['toplam'] ?? 0;

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .desktop-container {
            padding: 20px;
            min-height: 100vh;
        }
        
        .desktop-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        
        .desktop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .category-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .category-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            color: #333;
        }
        
        .desktop-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .desktop-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 20px 15px;
            color: white;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .desktop-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .desktop-button:active {
            transform: translateY(-1px);
        }
        
        .desktop-button i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .desktop-button span {
            font-size: 0.9rem;
            font-weight: 500;
            display: block;
        }
        
        .desktop-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .desktop-button:hover::before {
            left: 100%;
        }
        
        /* Kategori renkleri */
        .category-reservation .desktop-button {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .category-room .desktop-button {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .category-customer .desktop-button {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
        }
        
        .category-payment .desktop-button {
            background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
            box-shadow: 0 4px 15px rgba(156, 39, 176, 0.3);
        }
        
        .category-reports .desktop-button {
            background: linear-gradient(135deg, #F44336 0%, #D32F2F 100%);
            box-shadow: 0 4px 15px rgba(244, 67, 54, 0.3);
        }
        
        .category-quick .desktop-button {
            background: linear-gradient(135deg, #607D8B 0%, #455A64 100%);
            box-shadow: 0 4px 15px rgba(96, 125, 139, 0.3);
        }
        
        .current-time {
            font-size: 1.1rem;
            color: #667eea;
            font-weight: 500;
        }
        
        .welcome-text {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .desktop-grid {
                grid-template-columns: 1fr;
            }
            
            .desktop-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Desktop Header -->
        <div class="desktop-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-text mb-2">
                        <i class="fas fa-concierge-bell me-2"></i>
                        Resepsiyon Ana Ekran
                    </h1>
                    <p class="current-time mb-0">
                        <i class="fas fa-clock me-2"></i>
                        <?= date('d.m.Y H:i') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="resepsiyon.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-arrow-left"></i> Geri
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Çıkış
                    </a>
                </div>
            </div>
        </div>

        <!-- Hızlı İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['bugun_checkin'] ?></div>
                <div class="stat-label">Bugünkü Check-in</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['bugun_checkout'] ?></div>
                <div class="stat-label">Bugünkü Check-out</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['bekleyen_rezervasyon'] ?></div>
                <div class="stat-label">Bekleyen Rezervasyon</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['dolu_oda'] ?></div>
                <div class="stat-label">Dolu Oda</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['musait_oda'] ?></div>
                <div class="stat-label">Müsait Oda</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['temizlik_bekleyen'] ?></div>
                <div class="stat-label">Temizlik Bekleyen</div>
            </div>
        </div>

        <!-- Desktop Grid -->
        <div class="desktop-grid">
            <!-- Rezervasyon İşlemleri -->
            <div class="category-section category-reservation">
                <div class="category-title">
                    <i class="fas fa-calendar-check me-2"></i>
                    Rezervasyon İşlemleri
                </div>
                <div class="desktop-buttons">
                    <?php if (hasDetailedPermission('rezervasyon_ekle')): ?>
                    <a href="rezervasyon-ekle.php" class="desktop-button">
                        <i class="fas fa-plus-circle"></i>
                        <span>Yeni Rezervasyon</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('rezervasyon_goruntule')): ?>
                    <a href="rezervasyonlar.php" class="desktop-button">
                        <i class="fas fa-list"></i>
                        <span>Rezervasyon Listesi</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('resepsiyon_checkin_islemleri')): ?>
                    <a href="resepsiyon-checkin.php" class="desktop-button">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Check-in</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('resepsiyon_checkout_islemleri')): ?>
                    <a href="resepsiyon-checkout.php" class="desktop-button">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Check-out</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('resepsiyon_hizli_rezervasyon')): ?>
                    <a href="resepsiyon-hizli-rezervasyon.php" class="desktop-button">
                        <i class="fas fa-bolt"></i>
                        <span>Hızlı Rezervasyon</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Oda Yönetimi -->
            <div class="category-section category-room">
                <div class="category-title">
                    <i class="fas fa-bed me-2"></i>
                    Oda Yönetimi
                </div>
                <div class="desktop-buttons">
                    <?php if (hasDetailedPermission('resepsiyon_oda_yonetimi')): ?>
                    <a href="resepsiyon-oda-yonetimi.php" class="desktop-button">
                        <i class="fas fa-door-open"></i>
                        <span>Oda Durumları</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('resepsiyon_odalar')): ?>
                    <a href="resepsiyon-odalar.php" class="desktop-button">
                        <i class="fas fa-home"></i>
                        <span>Oda Listesi</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('oda_musaitlik_yonetimi')): ?>
                    <a href="musaitlik-yonetimi.php" class="desktop-button">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Müsaitlik Yönetimi</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('housekeeping_oda_temizlik')): ?>
                    <a href="housekeeping-oda-temizlik.php" class="desktop-button">
                        <i class="fas fa-broom"></i>
                        <span>Oda Temizlik</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Müşteri İşlemleri -->
            <div class="category-section category-customer">
                <div class="category-title">
                    <i class="fas fa-users me-2"></i>
                    Müşteri İşlemleri
                </div>
                <div class="desktop-buttons">
                    <?php if (hasDetailedPermission('musteri_ekle')): ?>
                    <a href="musteri-ekle.php" class="desktop-button">
                        <i class="fas fa-user-plus"></i>
                        <span>Müşteri Kayıt</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('musteri_goruntule')): ?>
                    <a href="musteriler.php" class="desktop-button">
                        <i class="fas fa-address-book"></i>
                        <span>Müşteri Listesi</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('resepsiyon_misafir_hizmetleri')): ?>
                    <a href="resepsiyon-misafir-hizmetleri.php" class="desktop-button">
                        <i class="fas fa-concierge-bell"></i>
                        <span>Misafir Hizmetleri</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ödeme İşlemleri -->
            <div class="category-section category-payment">
                <div class="category-title">
                    <i class="fas fa-credit-card me-2"></i>
                    Ödeme İşlemleri
                </div>
                <div class="desktop-buttons">
                    <a href="rezervasyonlar.php" class="desktop-button">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Ödeme Al</span>
                    </a>
                    
                    <a href="rezervasyon-iadeleri.php" class="desktop-button">
                        <i class="fas fa-undo"></i>
                        <span>İade İşlemleri</span>
                    </a>
                    
                    <a href="raporlar.php" class="desktop-button">
                        <i class="fas fa-receipt"></i>
                        <span>Fatura Kes</span>
                    </a>
                </div>
            </div>

            <!-- Raporlar -->
            <div class="category-section category-reports">
                <div class="category-title">
                    <i class="fas fa-chart-bar me-2"></i>
                    Raporlar
                </div>
                <div class="desktop-buttons">
                    <?php if (hasDetailedPermission('resepsiyon_gunluk_raporlar')): ?>
                    <a href="resepsiyon-raporlar.php" class="desktop-button">
                        <i class="fas fa-calendar-day"></i>
                        <span>Günlük Rapor</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('resepsiyon_gunluk_raporlar')): ?>
                    <a href="resepsiyon-doluluk-orani.php" class="desktop-button">
                        <i class="fas fa-chart-pie"></i>
                        <span>Doluluk Oranı</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasDetailedPermission('raporlar_goruntule')): ?>
                    <a href="raporlar.php" class="desktop-button">
                        <i class="fas fa-chart-pie"></i>
                        <span>Doluluk Raporu</span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="raporlar.php" class="desktop-button">
                        <i class="fas fa-chart-line"></i>
                        <span>Gelir Raporu</span>
                    </a>
                </div>
            </div>

            <!-- Hızlı İşlemler -->
            <div class="category-section category-quick">
                <div class="category-title">
                    <i class="fas fa-bolt me-2"></i>
                    Hızlı İşlemler
                </div>
                <div class="desktop-buttons">
                    <a href="resepsiyon-oda-yonetimi.php" class="desktop-button">
                        <i class="fas fa-edit"></i>
                        <span>Oda Durumu Değiştir</span>
                    </a>
                    
                    <a href="resepsiyon-misafir-hizmetleri.php" class="desktop-button">
                        <i class="fas fa-headset"></i>
                        <span>Misafir Hizmetleri</span>
                    </a>
                    
                    <a href="hizmetler.php" class="desktop-button">
                        <i class="fas fa-tools"></i>
                        <span>Hizmet Yönetimi</span>
                    </a>
                    
                    <a href="mesajlar.php" class="desktop-button">
                        <i class="fas fa-envelope"></i>
                        <span>Mesajlar</span>
                    </a>
                    
                    <a href="self-checkin-yonetimi.php" class="desktop-button">
                        <i class="fas fa-desktop"></i>
                        <span>Self Check-in Yönetimi</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Saat güncelleme
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('tr-TR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.querySelector('.current-time').innerHTML = '<i class="fas fa-clock me-2"></i>' + timeString + ' - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>';
        }
        
        // Her dakika saati güncelle
        setInterval(updateTime, 60000);
        
        // Sayfa yüklendiğinde animasyon
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.desktop-button');
            buttons.forEach((button, index) => {
                button.style.opacity = '0';
                button.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    button.style.transition = 'all 0.5s ease';
                    button.style.opacity = '1';
                    button.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>
