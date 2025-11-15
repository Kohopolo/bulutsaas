<?php
// Hata raporlamayı aç (geliştirme aşamasında)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Doğrudan database bağlantısını dahil et
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('sistem_ayarlari', 'Sistem ayarları yetkiniz bulunmamaktadır.');

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// ImageOptimizer sınıfının varlığını kontrol et
if (file_exists('../includes/ImageOptimizer.php')) {
    require_once '../includes/ImageOptimizer.php';
} else {
    die('ImageOptimizer sınıfı bulunamadı!');
}

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// WebP dönüştürme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_webp'])) {
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Güvenlik hatası! Lütfen sayfayı yenileyin.';
        $messageType = 'danger';
    } else {
        try {
            // ImageOptimizer sınıfının varlığını kontrol et
            if (!class_exists('ImageOptimizer')) {
                throw new Exception('ImageOptimizer sınıfı yüklenemedi!');
            }
            
            $imageOptimizer = new ImageOptimizer('cache/images');
            $convertedCount = 0;
            $errorCount = 0;
            
            // Galeri resimlerini dönüştür
            $galeri_resimleri = fetchAll("SELECT * FROM galeri WHERE durum = 'aktif'");
            
            foreach ($galeri_resimleri as $resim) {
                $originalPath = $resim['resim_url'];
                if (file_exists($originalPath)) {
                    try {
                        // WebP formatında optimize edilmiş versiyonları oluştur
                        $imageOptimizer->getOptimizedUrl($originalPath, 'thumbnail', 'webp');
                        $imageOptimizer->getOptimizedUrl($originalPath, 'medium', 'webp');
                        $imageOptimizer->getOptimizedUrl($originalPath, 'large', 'webp');
                        $convertedCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                    }
                }
            }
            
            // Slider resimlerini dönüştür
            $slider_resimleri = fetchAll("SELECT * FROM slider WHERE durum = 'aktif'");
            
            foreach ($slider_resimleri as $resim) {
                $originalPath = $resim['resim_url'];
                if (file_exists($originalPath)) {
                    try {
                        // WebP formatında optimize edilmiş versiyonları oluştur
                        $imageOptimizer->getOptimizedUrl($originalPath, 'large', 'webp');
                        $convertedCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                    }
                }
            }
            
            $message = "$convertedCount resim başarıyla WebP formatına dönüştürüldü.";
            if ($errorCount > 0) {
                $message .= " $errorCount resimde hata oluştu.";
            }
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Dönüştürme işlemi sırasında hata oluştu: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Cache temizleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Güvenlik hatası! Lütfen sayfayı yenileyin.';
        $messageType = 'danger';
    } else {
        try {
            // ImageOptimizer sınıfının varlığını kontrol et
            if (!class_exists('ImageOptimizer')) {
                throw new Exception('ImageOptimizer sınıfı yüklenemedi!');
            }
            
            $imageOptimizer = new ImageOptimizer('cache/images');
            $clearedSize = $imageOptimizer->clearCache();
            $message = "Cache temizlendi. " . formatBytes($clearedSize) . " alan boşaltıldı.";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Cache temizleme işlemi sırasında hata oluştu: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Cache boyutunu hesapla
$cacheSize = 0;
try {
    if (class_exists('ImageOptimizer')) {
        $imageOptimizer = new ImageOptimizer('cache/images');
        $cacheSize = $imageOptimizer->getCacheSize();
    }
} catch (Exception $e) {
    // Cache boyutu hesaplanamadı, varsayılan değer kullan
    $cacheSize = 0;
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebP Dönüştürücü - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-image me-2"></i>WebP Dönüştürücü ve Cache Yönetimi
                                </h5>
                            </div>
                            <div class="card-body">
                            <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0"><i class="fas fa-sync-alt me-2"></i>WebP Dönüştürme</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Tüm galeri ve slider resimlerini WebP formatına dönüştürür. Bu işlem performansı artırır ve disk alanından tasarruf sağlar.</p>
                                            
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">Dönüştürme Durumu:</small>
                                                    <span class="badge bg-success" id="conversion-status">
                                                        <i class="fas fa-check-circle me-1"></i>Hazır
                                                    </span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%" id="conversion-progress"></div>
                                                </div>
                                            </div>
                                            
                                            <form method="post" id="convertForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <button type="submit" name="convert_to_webp" class="btn btn-primary btn-lg" id="convertBtn">
                                <i class="fas fa-magic me-2"></i>WebP'ye Dönüştür
                            </button>
                        </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <h6 class="mb-0"><i class="fas fa-trash-alt me-2"></i>Cache Yönetimi</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Mevcut cache boyutu: <strong><?php echo formatBytes($cacheSize); ?></strong></p>
                                            <p class="text-muted">Cache'i temizleyerek disk alanı boşaltabilirsiniz. Temizlenen resimler tekrar ziyaret edildiğinde yeniden oluşturulacaktır.</p>
                                            
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">Cache Durumu:</small>
                                                    <span class="badge bg-info" id="cache-status">
                                                        <i class="fas fa-database me-1"></i>Aktif
                                                    </span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-info" role="progressbar" style="width: 100%" id="cache-progress"></div>
                                                </div>
                                            </div>
                                            
                                            <form method="POST" id="cache-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                <button type="submit" name="clear_cache" class="btn btn-warning" id="cache-btn" onclick="return confirm('Cache temizlensin mi?')">
                                    <i class="fas fa-broom me-2"></i>Cache'i Temizle
                                </button>
                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>WebP Hakkında</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <h6><i class="fas fa-compress-alt text-success me-2"></i>Dosya Boyutu</h6>
                                                    <p class="text-muted small">WebP formatı, JPEG'e göre %25-35 daha küçük dosya boyutu sağlar.</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6><i class="fas fa-eye text-primary me-2"></i>Görsel Kalite</h6>
                                                    <p class="text-muted small">Aynı kalitede görüntü sunarken daha az yer kaplar.</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <h6><i class="fas fa-tachometer-alt text-warning me-2"></i>Performans</h6>
                                                    <p class="text-muted small">Daha hızlı sayfa yükleme süreleri ve daha az bant genişliği kullanımı.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($message): ?>
        // Auto-hide alert after 5 seconds
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            }
        }, 5000);
        
        // Simulate processing for visual feedback
        setTimeout(function() {
            const convertBtn = document.getElementById('convertBtn');
            const cacheBtn = document.getElementById('cacheBtn');
            
            if (convertBtn) {
                convertBtn.disabled = false;
                convertBtn.innerHTML = '<i class="fas fa-magic me-2"></i>WebP\'ye Dönüştür';
            }
            if (cacheBtn) {
                cacheBtn.disabled = false;
                cacheBtn.innerHTML = '<i class="fas fa-broom me-2"></i>Cache\'i Temizle';
            }
        }, 2000);
        <?php endif; ?>
    });
    </script>
</body>
</html>