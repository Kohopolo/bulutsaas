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
requireDetailedPermission('kanal_fiyat_senkronizasyon', 'Fiyat senkronizasyon yetkiniz bulunmamaktadır.');

$page_title = "Kanal Fiyat Senkronizasyonu";
$current_page = "kanal-fiyat-senkronizasyon";

// Fiyat senkronizasyon işlemleri
if ($_POST['action'] ?? '' === 'fiyat_senkronize_et') {
    $kanal_id = $_POST['kanal_id'] ?? 0;
    $oda_tipi_id = $_POST['oda_tipi_id'] ?? 0;
    $baslangic_tarihi = $_POST['baslangic_tarihi'] ?? '';
    $bitis_tarihi = $_POST['bitis_tarihi'] ?? '';
    $fiyat_tipi = $_POST['fiyat_tipi'] ?? 'temel';
    
    if ($kanal_id > 0 && $oda_tipi_id > 0 && $baslangic_tarihi && $bitis_tarihi) {
        // Fiyat senkronizasyon işlemini başlat
        $output = shell_exec("php " . __DIR__ . "/../cron/kanal_fiyat_senkronizasyon.php --kanal-id={$kanal_id} --oda-tipi={$oda_tipi_id} --baslangic={$baslangic_tarihi} --bitis={$bitis_tarihi} --tip={$fiyat_tipi} 2>&1");
        
        if ($output) {
            $_SESSION['success_message'] = "Fiyat senkronizasyonu başlatıldı: " . htmlspecialchars($output);
        } else {
            $_SESSION['error_message'] = "Fiyat senkronizasyonu başlatılamadı";
        }
    } else {
        $_SESSION['error_message'] = "Lütfen tüm alanları doldurun";
    }
    
    header("Location: kanal-fiyat-senkronizasyon.php");
    exit;
}

if ($_POST['action'] ?? '' === 'stok_senkronize_et') {
    $kanal_id = $_POST['kanal_id'] ?? 0;
    $oda_tipi_id = $_POST['oda_tipi_id'] ?? 0;
    $baslangic_tarihi = $_POST['baslangic_tarihi'] ?? '';
    $bitis_tarihi = $_POST['bitis_tarihi'] ?? '';
    
    if ($kanal_id > 0 && $oda_tipi_id > 0 && $baslangic_tarihi && $bitis_tarihi) {
        // Stok senkronizasyon işlemini başlat
        $output = shell_exec("php " . __DIR__ . "/../cron/kanal_stok_senkronizasyon.php --kanal-id={$kanal_id} --oda-tipi={$oda_tipi_id} --baslangic={$baslangic_tarihi} --bitis={$bitis_tarihi} 2>&1");
        
        if ($output) {
            $_SESSION['success_message'] = "Stok senkronizasyonu başlatıldı: " . htmlspecialchars($output);
        } else {
            $_SESSION['error_message'] = "Stok senkronizasyonu başlatılamadı";
        }
    } else {
        $_SESSION['error_message'] = "Lütfen tüm alanları doldurun";
    }
    
    header("Location: kanal-fiyat-senkronizasyon.php");
    exit;
}

// Kanalları getir
$kanallar = fetchAll("SELECT * FROM kanallar WHERE aktif = 1 ORDER BY kanal_adi");

// Oda tiplerini getir
$oda_tipleri = fetchAll("SELECT * FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");

// Son senkronizasyon loglarını getir
$son_fiyat_loglari = [];
$fiyat_log_dosyasi = __DIR__ . '/../logs/kanal_fiyat_senkronizasyon.log';
if (file_exists($fiyat_log_dosyasi)) {
    $log_icerik = file_get_contents($fiyat_log_dosyasi);
    $log_satirlari = explode("\n", $log_icerik);
    $son_fiyat_loglari = array_slice(array_reverse($log_satirlari), 0, 30);
}

// Fiyat senkronizasyon istatistikleri
$fiyat_istatistikleri = fetchOne("
    SELECT 
        COUNT(DISTINCT kanal_id) as aktif_kanal_sayisi,
        COUNT(DISTINCT oda_tipi_id) as senkronize_oda_tipi_sayisi,
        MAX(son_senkronizasyon) as son_senkronizasyon_tarihi
    FROM kanal_fiyat_senkronizasyon_log
    WHERE DATE(son_senkronizasyon) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");

// Kanal bazlı fiyat durumu
$kanal_fiyat_durumu = fetchAll("
    SELECT 
        k.kanal_adi,
        k.kanal_kodu,
        COUNT(kfsl.id) as senkronizasyon_sayisi,
        MAX(kfsl.son_senkronizasyon) as son_senkronizasyon,
        COUNT(DISTINCT kfsl.oda_tipi_id) as senkronize_oda_tipi
    FROM kanallar k
    LEFT JOIN kanal_fiyat_senkronizasyon_log kfsl ON k.id = kfsl.kanal_id
    WHERE k.aktif = 1
    GROUP BY k.id
    ORDER BY k.kanal_adi
");
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
        
        .senkronizasyon-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .istatistik-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .istatistik-deger {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .istatistik-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .kanal-durum {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .durum-aktif { background: #d4edda; color: #155724; }
        .durum-pasif { background: #e2e3e5; color: #383d41; }
        .durum-hata { background: #f8d7da; color: #721c24; }
        
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
                            Kanal Fiyat Senkronizasyonu
                        </h1>
                        <p class="mb-0 mt-2" style="opacity: 0.9;">
                            <i class="fas fa-clock me-2"></i>
                            <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="kanal-senkronizasyon.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left"></i> Genel Senkronizasyon
                            </a>
                            <a href="kanal-listesi.php" class="btn btn-outline-light">
                                <i class="fas fa-list"></i> Kanal Listesi
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

                <!-- Fiyat Senkronizasyon İstatistikleri -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="istatistik-card">
                            <div class="istatistik-deger"><?= $fiyat_istatistikleri['aktif_kanal_sayisi'] ?? 0 ?></div>
                            <div class="istatistik-label">Aktif Kanal</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="istatistik-card">
                            <div class="istatistik-deger"><?= $fiyat_istatistikleri['senkronize_oda_tipi_sayisi'] ?? 0 ?></div>
                            <div class="istatistik-label">Senkronize Oda Tipi</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="istatistik-card">
                            <div class="istatistik-deger">
                                <?= $fiyat_istatistikleri['son_senkronizasyon_tarihi'] ? 
                                    date('d.m', strtotime($fiyat_istatistikleri['son_senkronizasyon_tarihi'])) : 
                                    'Yok' ?>
                            </div>
                            <div class="istatistik-label">Son Senkronizasyon</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="istatistik-card">
                            <div class="istatistik-deger"><?= count($kanallar) ?></div>
                            <div class="istatistik-label">Toplam Kanal</div>
                        </div>
                    </div>
                </div>

                <!-- Fiyat Senkronizasyon Formları -->
                <div class="row">
                    <!-- Fiyat Senkronizasyonu -->
                    <div class="col-md-6">
                        <div class="senkronizasyon-card">
                            <h5 class="mb-3">
                                <i class="fas fa-tags me-2"></i>
                                Fiyat Senkronizasyonu
                            </h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="fiyat_senkronize_et">
                                
                                <div class="mb-3">
                                    <label for="kanal_id" class="form-label">Kanal</label>
                                    <select class="form-select" name="kanal_id" id="kanal_id" required>
                                        <option value="">Kanal Seçin</option>
                                        <?php foreach ($kanallar as $kanal): ?>
                                            <option value="<?= $kanal['id'] ?>"><?= htmlspecialchars($kanal['kanal_adi']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="oda_tipi_id" class="form-label">Oda Tipi</label>
                                    <select class="form-select" name="oda_tipi_id" id="oda_tipi_id" required>
                                        <option value="">Oda Tipi Seçin</option>
                                        <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                            <option value="<?= $oda_tipi['id'] ?>"><?= htmlspecialchars($oda_tipi['oda_tipi_adi']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fiyat_tipi" class="form-label">Fiyat Tipi</label>
                                    <select class="form-select" name="fiyat_tipi" id="fiyat_tipi">
                                        <option value="temel">Temel Fiyat</option>
                                        <option value="kampanya">Kampanya Fiyatı</option>
                                        <option value="sezonluk">Sezonluk Fiyat</option>
                                        <option value="ozel">Özel Fiyat</option>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                                            <input type="date" class="form-control" name="baslangic_tarihi" 
                                                   id="baslangic_tarihi" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                            <input type="date" class="form-control" name="bitis_tarihi" 
                                                   id="bitis_tarihi" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-sync me-2"></i>
                                    Fiyat Senkronizasyonu Başlat
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Stok Senkronizasyonu -->
                    <div class="col-md-6">
                        <div class="senkronizasyon-card">
                            <h5 class="mb-3">
                                <i class="fas fa-boxes me-2"></i>
                                Stok Senkronizasyonu
                            </h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="stok_senkronize_et">
                                
                                <div class="mb-3">
                                    <label for="stok_kanal_id" class="form-label">Kanal</label>
                                    <select class="form-select" name="kanal_id" id="stok_kanal_id" required>
                                        <option value="">Kanal Seçin</option>
                                        <?php foreach ($kanallar as $kanal): ?>
                                            <option value="<?= $kanal['id'] ?>"><?= htmlspecialchars($kanal['kanal_adi']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="stok_oda_tipi_id" class="form-label">Oda Tipi</label>
                                    <select class="form-select" name="oda_tipi_id" id="stok_oda_tipi_id" required>
                                        <option value="">Oda Tipi Seçin</option>
                                        <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                            <option value="<?= $oda_tipi['id'] ?>"><?= htmlspecialchars($oda_tipi['oda_tipi_adi']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="stok_baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                                            <input type="date" class="form-control" name="baslangic_tarihi" 
                                                   id="stok_baslangic_tarihi" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="stok_bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                            <input type="date" class="form-control" name="bitis_tarihi" 
                                                   id="stok_bitis_tarihi" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-sync me-2"></i>
                                    Stok Senkronizasyonu Başlat
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Kanal Fiyat Durumu -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Kanal Fiyat Durumu
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kanal</th>
                                                <th>Senkronizasyon Sayısı</th>
                                                <th>Senkronize Oda Tipi</th>
                                                <th>Son Senkronizasyon</th>
                                                <th>Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($kanal_fiyat_durumu as $durum): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($durum['kanal_adi']) ?></strong>
                                                        <br><small class="text-muted"><?= htmlspecialchars($durum['kanal_kodu']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary"><?= $durum['senkronizasyon_sayisi'] ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= $durum['senkronize_oda_tipi'] ?></span>
                                                    </td>
                                                    <td>
                                                        <small>
                                                            <?= $durum['son_senkronizasyon'] ? 
                                                                date('d.m.Y H:i', strtotime($durum['son_senkronizasyon'])) : 
                                                                'Hiç' ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="kanal-durum durum-<?= $durum['senkronizasyon_sayisi'] > 0 ? 'aktif' : 'pasif' ?>">
                                                            <?= $durum['senkronizasyon_sayisi'] > 0 ? 'Aktif' : 'Pasif' ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fiyat Senkronizasyon Logları -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt me-2"></i>
                                    Son Fiyat Senkronizasyon Logları
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="log-container">
                                    <?php if (empty($son_fiyat_loglari)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Henüz fiyat senkronizasyon logu bulunmuyor
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($son_fiyat_loglari as $log): ?>
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
        // Tarih validasyonu
        document.getElementById('baslangic_tarihi').addEventListener('change', function() {
            const bitisTarihi = document.getElementById('bitis_tarihi');
            if (this.value && bitisTarihi.value && this.value > bitisTarihi.value) {
                bitisTarihi.value = this.value;
            }
            bitisTarihi.min = this.value;
        });

        document.getElementById('bitis_tarihi').addEventListener('change', function() {
            const baslangicTarihi = document.getElementById('baslangic_tarihi');
            if (this.value && baslangicTarihi.value && this.value < baslangicTarihi.value) {
                alert('Bitiş tarihi başlangıç tarihinden önce olamaz');
                this.value = baslangicTarihi.value;
            }
        });

        // Stok formu için aynı validasyon
        document.getElementById('stok_baslangic_tarihi').addEventListener('change', function() {
            const bitisTarihi = document.getElementById('stok_bitis_tarihi');
            if (this.value && bitisTarihi.value && this.value > bitisTarihi.value) {
                bitisTarihi.value = this.value;
            }
            bitisTarihi.min = this.value;
        });

        document.getElementById('stok_bitis_tarihi').addEventListener('change', function() {
            const baslangicTarihi = document.getElementById('stok_baslangic_tarihi');
            if (this.value && baslangicTarihi.value && this.value < baslangicTarihi.value) {
                alert('Bitiş tarihi başlangıç tarihinden önce olamaz');
                this.value = baslangicTarihi.value;
            }
        });

        // Form submit butonları için loading durumu
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = this.querySelector('button[type="submit"]');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> İşleniyor...';
                button.disabled = true;
                
                // 5 saniye sonra butonu tekrar aktif et
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 5000);
            });
        });

        // Sayfa yüklendiğinde logları otomatik yenile
        function refreshLogs() {
            fetch('kanal-fiyat-senkronizasyon.php?action=refresh_logs')
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
    </script>
</body>
</html>
