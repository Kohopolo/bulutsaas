<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('kanal_senkronizasyon_manuel', 'Kanal senkronizasyon yetkiniz bulunmamaktadır.');

$page_title = "Kanal Senkronizasyon Yönetimi";
$current_page = "kanal-senkronizasyon";

// Kanal senkronizasyon durumlarını getir
$kanallar = fetchAll("
    SELECT k.*, 
           COUNT(krt.id) as toplam_rezervasyon,
           MAX(krt.senkronizasyon_tarihi) as son_senkronizasyon_tarihi
    FROM kanallar k
    LEFT JOIN kanal_rezervasyon_takip krt ON k.id = krt.kanal_id
    GROUP BY k.id
    ORDER BY k.kanal_adi
");

// Son senkronizasyon loglarını getir
$son_loglar = [];
$log_dosyasi = __DIR__ . '/../logs/kanal_senkronizasyon.log';
if (file_exists($log_dosyasi)) {
    $log_icerik = file_get_contents($log_dosyasi);
    $log_satirlari = explode("\n", $log_icerik);
    $son_loglar = array_slice(array_reverse($log_satirlari), 0, 50);
}

// Manuel senkronizasyon işlemi
if ($_POST['action'] ?? '' === 'manuel_senkronizasyon') {
    $kanal_id = $_POST['kanal_id'] ?? 0;
    
    if ($kanal_id > 0) {
        // Manuel senkronizasyon başlat
        $output = shell_exec("php " . __DIR__ . "/../cron/kanal_senkronizasyon.php --kanal-id={$kanal_id} 2>&1");
        
        if ($output) {
            $_SESSION['success_message'] = "Manuel senkronizasyon başlatıldı: " . htmlspecialchars($output);
        } else {
            $_SESSION['error_message'] = "Senkronizasyon başlatılamadı";
        }
    }
    
    header("Location: kanal-senkronizasyon.php");
    exit;
}

// Otomatik senkronizasyon ayarları
if ($_POST['action'] ?? '' === 'otomatik_ayarlar') {
    $kanal_id = $_POST['kanal_id'] ?? 0;
    $otomatik_senkronizasyon = $_POST['otomatik_senkronizasyon'] ?? 0;
    $senkronizasyon_sikligi = $_POST['senkronizasyon_sikligi'] ?? 'hourly';
    
    if ($kanal_id > 0) {
        $sql = "UPDATE kanallar SET 
                otomatik_senkronizasyon = ?, 
                senkronizasyon_sikligi = ?,
                guncelleme_tarihi = NOW()
                WHERE id = ?";
        
        if (executeQuery($sql, [$otomatik_senkronizasyon, $senkronizasyon_sikligi, $kanal_id])) {
            $_SESSION['success_message'] = "Otomatik senkronizasyon ayarları güncellendi";
        } else {
            $_SESSION['error_message'] = "Ayarlar güncellenemedi";
        }
    }
    
    header("Location: kanal-senkronizasyon.php");
    exit;
}
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
    <!-- Custom CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        .desktop-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .desktop-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-container {
            background: white;
            margin: 0;
            padding: 20px;
            border-radius: 0;
            box-shadow: none;
        }
        
        .kanal-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .kanal-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .kanal-durum {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .durum-aktif { background: #d4edda; color: #155724; }
        .durum-hata { background: #f8d7da; color: #721c24; }
        .durum-pasif { background: #e2e3e5; color: #383d41; }
        
        .senkronizasyon-bilgi {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }
        
        .log-container {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .log-line {
            margin-bottom: 5px;
            word-wrap: break-word;
        }
        
        .log-error { color: #ff6b6b; }
        .log-warning { color: #ffd93d; }
        .log-info { color: #6bcf7f; }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Desktop Header -->
        <div class="desktop-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="mb-0">
                            <i class="fas fa-sync-alt me-2"></i>
                            Kanal Senkronizasyon Yönetimi
                        </h1>
                        <p class="mb-0 mt-2" style="opacity: 0.9;">
                            <i class="fas fa-clock me-2"></i>
                            <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="kanal-listesi.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left"></i> Kanal Listesi
                            </a>
                            <a href="kanal-analiz.php" class="btn btn-outline-light">
                                <i class="fas fa-chart-line"></i> Analiz
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt"></i> Çıkış
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="container-fluid">
                <!-- Başarı/Hata Mesajları -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Kanal Senkronizasyon Kartları -->
                <div class="row">
                    <?php foreach ($kanallar as $kanal): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="kanal-card">
                                <div class="kanal-header">
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($kanal['kanal_adi']) ?></h5>
                                        <small class="text-muted"><?= htmlspecialchars($kanal['kanal_kodu']) ?></small>
                                    </div>
                                    <span class="kanal-durum durum-<?= $kanal['api_durumu'] ?>">
                                        <?= ucfirst($kanal['api_durumu']) ?>
                                    </span>
                                </div>

                                <div class="senkronizasyon-bilgi">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="fw-bold text-primary"><?= $kanal['toplam_rezervasyon'] ?></div>
                                            <small class="text-muted">Toplam Rezervasyon</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold text-success"><?= $kanal['otomatik_senkronizasyon'] ? 'Aktif' : 'Pasif' ?></div>
                                            <small class="text-muted">Otomatik Sync</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="fw-bold text-info"><?= $kanal['senkronizasyon_sikligi'] ?? 'Manuel' ?></div>
                                            <small class="text-muted">Sıklık</small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($kanal['son_senkronizasyon_tarihi']): ?>
                                        <div class="mt-2 text-center">
                                            <small class="text-muted">
                                                Son Sync: <?= date('d.m.Y H:i', strtotime($kanal['son_senkronizasyon_tarihi'])) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Manuel Senkronizasyon -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="manuel_senkronizasyon">
                                    <input type="hidden" name="kanal_id" value="<?= $kanal['id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">
                                        <i class="fas fa-sync me-1"></i> Manuel Senkronizasyon
                                    </button>
                                </form>

                                <!-- Otomatik Senkronizasyon Ayarları -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="otomatik_ayarlar">
                                    <input type="hidden" name="kanal_id" value="<?= $kanal['id'] ?>">
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="otomatik_senkronizasyon" 
                                               value="1" <?= $kanal['otomatik_senkronizasyon'] ? 'checked' : '' ?>>
                                        <label class="form-check-label">
                                            Otomatik Senkronizasyon
                                        </label>
                                    </div>
                                    
                                    <select name="senkronizasyon_sikligi" class="form-select form-select-sm mb-2">
                                        <option value="hourly" <?= ($kanal['senkronizasyon_sikligi'] ?? '') === 'hourly' ? 'selected' : '' ?>>Saatlik</option>
                                        <option value="daily" <?= ($kanal['senkronizasyon_sikligi'] ?? '') === 'daily' ? 'selected' : '' ?>>Günlük</option>
                                        <option value="weekly" <?= ($kanal['senkronizasyon_sikligi'] ?? '') === 'weekly' ? 'selected' : '' ?>>Haftalık</option>
                                    </select>
                                    
                                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                        <i class="fas fa-cog me-1"></i> Ayarları Güncelle
                                    </button>
                                </form>

                                <?php if ($kanal['api_hata_mesaji']): ?>
                                    <div class="alert alert-danger alert-sm mt-2 mb-0">
                                        <small><i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($kanal['api_hata_mesaji']) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Senkronizasyon Logları -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt me-2"></i>
                                    Son Senkronizasyon Logları
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="log-container">
                                    <?php if (empty($son_loglar)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Henüz senkronizasyon logu bulunmuyor
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($son_loglar as $log): ?>
                                            <?php if (trim($log)): ?>
                                                <div class="log-line <?= strpos($log, 'ERROR') !== false ? 'log-error' : (strpos($log, 'WARNING') !== false ? 'log-warning' : 'log-info') ?>">
                                                    <?= htmlspecialchars($log) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sayfa yüklendiğinde logları otomatik yenile
        function refreshLogs() {
            fetch('kanal-senkronizasyon.php?action=refresh_logs')
                .then(response => response.text())
                .then(data => {
                    const logContainer = document.querySelector('.log-container');
                    if (logContainer) {
                        logContainer.innerHTML = data;
                    }
                })
                .catch(error => console.error('Log yenileme hatası:', error));
        }

        // Her 30 saniyede bir logları yenile
        setInterval(refreshLogs, 30000);

        // Manuel senkronizasyon butonları için loading durumu
        document.querySelectorAll('form[action*="manuel_senkronizasyon"]').forEach(form => {
            form.addEventListener('submit', function() {
                const button = this.querySelector('button[type="submit"]');
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Senkronize Ediliyor...';
                button.disabled = true;
            });
        });
    </script>
</body>
</html>
