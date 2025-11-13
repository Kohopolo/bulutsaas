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
requireDetailedPermission('kanal_listele', 'Kanal listeleme yetkiniz bulunmamaktadır.');

$page_title = "Kanal Yönetimi";

// Filtreleme parametreleri
$kanal_tipi = $_GET['kanal_tipi'] ?? '';
$aktif_durum = $_GET['aktif_durum'] ?? '';
$arama = $_GET['arama'] ?? '';

// Kanal listesi sorgusu
$where_conditions = [];
$params = [];

if (!empty($kanal_tipi)) {
    $where_conditions[] = "k.kanal_tipi = ?";
    $params[] = $kanal_tipi;
}

if ($aktif_durum !== '') {
    $where_conditions[] = "k.aktif = ?";
    $params[] = $aktif_durum;
}

if (!empty($arama)) {
    $where_conditions[] = "(k.kanal_adi LIKE ? OR k.kanal_kodu LIKE ? OR k.website LIKE ?)";
    $arama_param = "%$arama%";
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Kanal listesi
$kanallar = fetchAll("
    SELECT 
        k.*,
        (SELECT COUNT(*) FROM kanal_performans kp WHERE kp.kanal_id = k.id) as performans_kayit_sayisi,
        (SELECT SUM(kp.toplam_rezervasyon) FROM kanal_performans kp WHERE kp.kanal_id = k.id) as toplam_rezervasyon,
        (SELECT SUM(kp.brut_gelir) FROM kanal_performans kp WHERE kp.kanal_id = k.id) as toplam_gelir
    FROM kanallar k
    $where_clause
    ORDER BY k.kanal_adi ASC
", $params);

// İstatistikler
$stats = [];

// Toplam kanal sayısı
$stats['toplam_kanal'] = fetchOne("SELECT COUNT(*) as toplam FROM kanallar")['toplam'] ?? 0;

// Aktif kanal sayısı
$stats['aktif_kanal'] = fetchOne("SELECT COUNT(*) as toplam FROM kanallar WHERE aktif = 1")['toplam'] ?? 0;

// OTA kanal sayısı
$stats['ota_kanal'] = fetchOne("SELECT COUNT(*) as toplam FROM kanallar WHERE kanal_tipi = 'ota' AND aktif = 1")['toplam'] ?? 0;

// Bu ay toplam rezervasyon
$stats['bu_ay_rezervasyon'] = fetchOne("
    SELECT SUM(toplam_rezervasyon) as toplam 
    FROM kanal_performans 
    WHERE YEAR(tarih) = YEAR(CURDATE()) AND MONTH(tarih) = MONTH(CURDATE())
")['toplam'] ?? 0;

// Bu ay toplam gelir
$stats['bu_ay_gelir'] = fetchOne("
    SELECT SUM(brut_gelir) as toplam 
    FROM kanal_performans 
    WHERE YEAR(tarih) = YEAR(CURDATE()) AND MONTH(tarih) = MONTH(CURDATE())
")['toplam'] ?? 0;

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
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .kanal-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #3498db;
        }
        
        .kanal-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .kanal-card.ota { border-left-color: #e74c3c; }
        .kanal-card.direct { border-left-color: #27ae60; }
        .kanal-card.corporate { border-left-color: #f39c12; }
        .kanal-card.social { border-left-color: #9b59b6; }
        
        .kanal-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        
        .badge-ota { background: #e74c3c; color: white; }
        .badge-direct { background: #27ae60; color: white; }
        .badge-corporate { background: #f39c12; color: white; }
        .badge-social { background: #9b59b6; color: white; }
        .badge-gds { background: #34495e; color: white; }
        .badge-other { background: #95a5a6; color: white; }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .status-aktif { background: #d4edda; color: #155724; }
        .status-pasif { background: #f8d7da; color: #721c24; }
        
        .api-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 15px;
            font-weight: bold;
        }
        
        .api-aktif { background: #d4edda; color: #155724; }
        .api-pasif { background: #fff3cd; color: #856404; }
        .api-hata { background: #f8d7da; color: #721c24; }
        
        .filter-section {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-action-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .performance-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .metric {
            text-align: center;
            padding: 10px;
            background: rgba(52, 73, 94, 0.05);
            border-radius: 8px;
        }
        
        .metric-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .metric-label {
            font-size: 0.75rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .desktop-container {
                padding: 10px;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .performance-metrics {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Header -->
        <div class="desktop-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="fas fa-network-wired me-3" style="color: #667eea;"></i>
                        Kanal Yönetimi
                    </h1>
                    <p class="text-muted mb-0 mt-2">
                        <i class="fas fa-clock me-2"></i>
                        <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="quick-actions">
                        <a href="kanal-ekle.php" class="quick-action-btn">
                            <i class="fas fa-plus"></i>
                            Yeni Kanal
                        </a>
                        <a href="kanal-performans.php" class="quick-action-btn">
                            <i class="fas fa-chart-line"></i>
                            Performans
                        </a>
                        <a href="kanal-analiz.php" class="quick-action-btn">
                            <i class="fas fa-analytics"></i>
                            Analiz
                        </a>
                        <a href="index.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left"></i> Geri
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt"></i> Çıkış
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div class="stats-number"><?= number_format($stats['toplam_kanal']) ?></div>
                    <div class="stats-label">Toplam Kanal</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?= number_format($stats['aktif_kanal']) ?></div>
                    <div class="stats-label">Aktif Kanal</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="stats-number"><?= number_format($stats['ota_kanal']) ?></div>
                    <div class="stats-label">OTA Kanal</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?= number_format($stats['bu_ay_rezervasyon']) ?></div>
                    <div class="stats-label">Bu Ay Rezervasyon</div>
                </div>
            </div>
        </div>

        <!-- Filtreleme -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Kanal Tipi</label>
                    <select name="kanal_tipi" class="form-select">
                        <option value="">Tümü</option>
                        <option value="ota" <?= $kanal_tipi === 'ota' ? 'selected' : '' ?>>OTA</option>
                        <option value="gds" <?= $kanal_tipi === 'gds' ? 'selected' : '' ?>>GDS</option>
                        <option value="direct" <?= $kanal_tipi === 'direct' ? 'selected' : '' ?>>Direct</option>
                        <option value="corporate" <?= $kanal_tipi === 'corporate' ? 'selected' : '' ?>>Corporate</option>
                        <option value="social" <?= $kanal_tipi === 'social' ? 'selected' : '' ?>>Social Media</option>
                        <option value="other" <?= $kanal_tipi === 'other' ? 'selected' : '' ?>>Diğer</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Durum</label>
                    <select name="aktif_durum" class="form-select">
                        <option value="">Tümü</option>
                        <option value="1" <?= $aktif_durum === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $aktif_durum === '0' ? 'selected' : '' ?>>Pasif</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Arama</label>
                    <input type="text" name="arama" class="form-control" placeholder="Kanal adı, kodu veya website..." value="<?= htmlspecialchars($arama) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrele
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Kanal Listesi -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Kanal Listesi
                </h3>
                <span class="badge bg-primary"><?= count($kanallar) ?> Kanal</span>
            </div>

            <?php if (empty($kanallar)): ?>
                <div class="empty-state">
                    <i class="fas fa-network-wired"></i>
                    <h4>Kanal Bulunamadı</h4>
                    <p>Arama kriterlerinize uygun kanal bulunamadı.</p>
                    <a href="kanal-ekle.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> İlk Kanalı Ekle
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($kanallar as $kanal): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="kanal-card <?= $kanal['kanal_tipi'] ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($kanal['kanal_adi']) ?></h5>
                                        <span class="kanal-badge badge-<?= $kanal['kanal_tipi'] ?>">
                                            <?= strtoupper($kanal['kanal_tipi']) ?>
                                        </span>
                                    </div>
                                    <div class="text-end">
                                        <span class="status-badge status-<?= $kanal['aktif'] ? 'aktif' : 'pasif' ?>">
                                            <?= $kanal['aktif'] ? 'Aktif' : 'Pasif' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-code me-1"></i>
                                        <?= htmlspecialchars($kanal['kanal_kodu']) ?>
                                    </small>
                                    <?php if ($kanal['website']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-globe me-1"></i>
                                            <a href="<?= htmlspecialchars($kanal['website']) ?>" target="_blank" class="text-decoration-none">
                                                <?= htmlspecialchars($kanal['website']) ?>
                                            </a>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <span class="api-status api-<?= $kanal['api_durumu'] ?>">
                                        <i class="fas fa-<?= $kanal['api_durumu'] === 'aktif' ? 'check-circle' : ($kanal['api_durumu'] === 'hata' ? 'exclamation-triangle' : 'clock') ?>"></i>
                                        API <?= ucfirst($kanal['api_durumu']) ?>
                                    </span>
                                    <?php if ($kanal['komisyon_orani'] > 0): ?>
                                        <span class="badge bg-warning text-dark ms-2">
                                            %<?= number_format($kanal['komisyon_orani'], 1) ?> Komisyon
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($kanal['toplam_rezervasyon'] > 0 || $kanal['toplam_gelir'] > 0): ?>
                                    <div class="performance-metrics">
                                        <div class="metric">
                                            <div class="metric-value"><?= number_format($kanal['toplam_rezervasyon']) ?></div>
                                            <div class="metric-label">Rezervasyon</div>
                                        </div>
                                        <div class="metric">
                                            <div class="metric-value"><?= number_format($kanal['toplam_gelir'], 0) ?>₺</div>
                                            <div class="metric-label">Gelir</div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2 mt-3">
                                    <a href="kanal-duzenle.php?id=<?= $kanal['id'] ?>" class="btn-action btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </a>
                                    <a href="kanal-performans.php?kanal_id=<?= $kanal['id'] ?>" class="btn-action btn btn-outline-info btn-sm">
                                        <i class="fas fa-chart-line"></i> Performans
                                    </a>
                                    <?php if ($kanal['api_endpoint']): ?>
                                        <a href="kanal-api-test.php?id=<?= $kanal['id'] ?>" class="btn-action btn btn-outline-success btn-sm">
                                            <i class="fas fa-plug"></i> API Test
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Canlı saat güncelleme
        function updateLiveTime() {
            const now = new Date();
            const day = String(now.getDate()).padStart(2, '0');
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const year = now.getFullYear();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timeString = `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`;
            const timeElement = document.querySelector('.current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        updateLiveTime();
        setInterval(updateLiveTime, 1000);
    </script>
</body>
</html>
