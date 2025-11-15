<?php
/**
 * KPI Yönetimi
 * KPI metrikleri, dashboard'ları ve uyarıları yönetme
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/kpi-dashboard-system.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('kpi_yonetimi')) {
    $_SESSION['error_message'] = 'KPI yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// KPI Dashboard sistemi
$kpiSystem = new KpiDashboardSystem($pdo);

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'create_metric') {
            $metric_name = sanitizeString($_POST['metric_name']);
            $metric_description = sanitizeString($_POST['metric_description']);
            $metric_type = sanitizeString($_POST['metric_type']);
            $category = sanitizeString($_POST['category']);
            $calculation_formula = sanitizeString($_POST['calculation_formula']);
            $data_source = sanitizeString($_POST['data_source']);
            $target_value = floatval($_POST['target_value'] ?? 0);
            $unit = sanitizeString($_POST['unit']);
            $priority = intval($_POST['priority'] ?? 1);
            $real_time = isset($_POST['real_time']) ? 1 : 0;
            $update_frequency = intval($_POST['update_frequency'] ?? 60);
            
            if (empty($metric_name) || empty($category)) {
                throw new Exception("Metrik adı ve kategori zorunludur.");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO kpi_metrikleri (
                    metrik_adi, metrik_aciklamasi, metrik_turu, kategori, 
                    hesaplama_formulu, veri_kaynagi, hedef_deger, birim, 
                    oncelik, gercek_zamanli, guncelleme_sikligi, olusturan_kullanici_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $metric_name,
                $metric_description,
                $metric_type,
                $category,
                $calculation_formula,
                $data_source,
                $target_value,
                $unit,
                $priority,
                $real_time,
                $update_frequency,
                $_SESSION['user_id']
            ]);
            
            $success_message = "KPI metrik başarıyla oluşturuldu.";
        }
        
        if ($action == 'create_dashboard') {
            $dashboard_name = sanitizeString($_POST['dashboard_name']);
            $dashboard_description = sanitizeString($_POST['dashboard_description']);
            $dashboard_type = sanitizeString($_POST['dashboard_type']);
            $module = sanitizeString($_POST['module']);
            $metric_list = $_POST['metric_list'] ?? [];
            $refresh_interval = intval($_POST['refresh_interval'] ?? 30);
            $auto_update = isset($_POST['auto_update']) ? 1 : 0;
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            if (empty($dashboard_name) || empty($metric_list)) {
                throw new Exception("Dashboard adı ve metrik listesi zorunludur.");
            }
            
            // Eğer varsayılan dashboard seçilirse, diğerlerini varsayılan olmaktan çıkar
            if ($is_default) {
                $stmt = $pdo->prepare("UPDATE kpi_dashboardlari SET varsayilan = 0");
                $stmt->execute();
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO kpi_dashboardlari (
                    dashboard_adi, dashboard_aciklamasi, dashboard_turu, modul,
                    metrik_listesi, guncelleme_sikligi, otomatik_guncelleme, varsayilan,
                    olusturan_kullanici_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $dashboard_name,
                $dashboard_description,
                $dashboard_type,
                $module,
                json_encode($metric_list),
                $refresh_interval,
                $auto_update,
                $is_default,
                $_SESSION['user_id']
            ]);
            
            $success_message = "KPI dashboard başarıyla oluşturuldu.";
        }
        
        if ($action == 'create_alert') {
            $metric_id = intval($_POST['metric_id']);
            $alert_name = sanitizeString($_POST['alert_name']);
            $alert_type = sanitizeString($_POST['alert_type']);
            $threshold_value = floatval($_POST['threshold_value']);
            $alert_message = sanitizeString($_POST['alert_message']);
            $alert_color = sanitizeString($_POST['alert_color']);
            $auto_notification = isset($_POST['auto_notification']) ? 1 : 0;
            $notification_channels = $_POST['notification_channels'] ?? [];
            
            if (empty($metric_id) || empty($alert_name) || empty($threshold_value)) {
                throw new Exception("Metrik, uyarı adı ve eşik değeri zorunludur.");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO kpi_uyarilari (
                    metrik_id, uyari_adi, uyari_turu, esik_deger, uyari_mesaji,
                    uyari_renk, otomatik_bildirim, bildirim_kanallari, olusturan_kullanici_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $metric_id,
                $alert_name,
                $alert_type,
                $threshold_value,
                $alert_message,
                $alert_color,
                $auto_notification,
                json_encode($notification_channels),
                $_SESSION['user_id']
            ]);
            
            $success_message = "KPI uyarısı başarıyla oluşturuldu.";
        }
        
        if ($action == 'update_metric_status') {
            $metric_id = intval($_POST['metric_id']);
            $status = sanitizeString($_POST['status']);
            
            $stmt = $pdo->prepare("UPDATE kpi_metrikleri SET aktif = ? WHERE id = ?");
            $stmt->execute([$status == 'aktif' ? 1 : 0, $metric_id]);
            
            $success_message = "Metrik durumu başarıyla güncellendi.";
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Verileri getir
$metrics = fetchAll("
    SELECT km.*, k.ad as olusturan_adi, k.soyad as olusturan_soyadi
    FROM kpi_metrikleri km
    LEFT JOIN kullanicilar k ON km.olusturan_kullanici_id = k.id
    ORDER BY km.kategori, km.oncelik, km.metrik_adi
");

$dashboards = fetchAll("
    SELECT kd.*, k.ad as olusturan_adi, k.soyad as olusturan_soyadi
    FROM kpi_dashboardlari kd
    LEFT JOIN kullanicilar k ON kd.olusturan_kullanici_id = k.id
    ORDER BY kd.dashboard_turu, kd.dashboard_adi
");

$alerts = fetchAll("
    SELECT ku.*, km.metrik_adi, km.kategori, k.ad as olusturan_adi, k.soyad as olusturan_soyadi
    FROM kpi_uyarilari ku
    LEFT JOIN kpi_metrikleri km ON ku.metrik_id = km.id
    LEFT JOIN kullanicilar k ON ku.olusturan_kullanici_id = k.id
    ORDER BY ku.aktif DESC, km.kategori, ku.uyari_adi
");

// Kategoriler
$kategoriler = [
    'rezervasyon' => 'Rezervasyon',
    'gelir' => 'Gelir',
    'musteri' => 'Müşteri',
    'operasyon' => 'Operasyon',
    'stok' => 'Stok',
    'personel' => 'Personel'
];

// Metrik türleri
$metrik_turleri = [
    'sayisal' => 'Sayısal',
    'yuzde' => 'Yüzde',
    'para' => 'Para',
    'sure' => 'Süre',
    'oran' => 'Oran'
];

// Dashboard türleri
$dashboard_turleri = [
    'genel' => 'Genel',
    'modul' => 'Modül',
    'ozel' => 'Özel',
    'yönetim' => 'Yönetim'
];

// Uyarı türleri
$uyari_turleri = [
    'alt_limit' => 'Alt Limit',
    'ust_limit' => 'Üst Limit',
    'degisim' => 'Değişim',
    'hedef' => 'Hedef'
];

// Uyarı renkleri
$uyari_renkleri = [
    'primary' => 'Mavi',
    'success' => 'Yeşil',
    'warning' => 'Sarı',
    'danger' => 'Kırmızı',
    'info' => 'Açık Mavi'
];

// Modüller
$moduller = [
    'rezervasyon' => 'Rezervasyon',
    'fnb' => 'F&B',
    'housekeeping' => 'Housekeeping',
    'muhasebe' => 'Muhasebe',
    'stok' => 'Stok',
    'personel' => 'Personel'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .metric-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-2px);
        }
        .metric-card.rezervasyon { border-left-color: #007bff; }
        .metric-card.gelir { border-left-color: #28a745; }
        .metric-card.musteri { border-left-color: #17a2b8; }
        .metric-card.operasyon { border-left-color: #ffc107; }
        .metric-card.stok { border-left-color: #6c757d; }
        .metric-card.personel { border-left-color: #dc3545; }
        
        .dashboard-card {
            border-left: 4px solid #6f42c1;
        }
        
        .alert-card {
            border-left: 4px solid #dc3545;
        }
        
        .tab-content {
            min-height: 500px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cogs me-2"></i>KPI Yönetimi
                        <small class="text-muted">Metrikler, Dashboard'lar ve Uyarılar</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="kpi-dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-line"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" id="kpiTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="metrics-tab" data-bs-toggle="tab" data-bs-target="#metrics" type="button" role="tab">
                            <i class="fas fa-chart-bar me-1"></i>Metrikler
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="dashboards-tab" data-bs-toggle="tab" data-bs-target="#dashboards" type="button" role="tab">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard'lar
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab">
                            <i class="fas fa-exclamation-triangle me-1"></i>Uyarılar
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="kpiTabContent">
                    <!-- Metrikler Tab -->
                    <div class="tab-pane fade show active" id="metrics" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
                            <h5>KPI Metrikleri</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMetricModal">
                                <i class="fas fa-plus"></i> Yeni Metrik
                            </button>
                        </div>
                        
                        <div class="row">
                            <?php foreach ($metrics as $metric): ?>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card metric-card <?php echo $metric['kategori']; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="card-title"><?php echo htmlspecialchars($metric['metrik_adi']); ?></h6>
                                                <p class="card-text text-muted small"><?php echo htmlspecialchars($metric['metrik_aciklamasi']); ?></p>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-<?php echo $metric['aktif'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $metric['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                                    </span>
                                                    <span class="badge bg-info"><?php echo $metrik_turleri[$metric['metrik_turu']]; ?></span>
                                                    <?php if ($metric['gercek_zamanli']): ?>
                                                    <span class="badge bg-warning">Gerçek Zamanlı</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="editMetric(<?php echo $metric['id']; ?>)">
                                                        <i class="fas fa-edit me-1"></i>Düzenle
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="toggleMetricStatus(<?php echo $metric['id']; ?>, '<?php echo $metric['aktif'] ? 'pasif' : 'aktif'; ?>')">
                                                        <i class="fas fa-<?php echo $metric['aktif'] ? 'pause' : 'play'; ?> me-1"></i><?php echo $metric['aktif'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteMetric(<?php echo $metric['id']; ?>)">
                                                        <i class="fas fa-trash me-1"></i>Sil
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong>Kategori:</strong> <?php echo $kategoriler[$metric['kategori']] ?? ucfirst($metric['kategori']); ?><br>
                                                <strong>Hedef:</strong> <?php echo $metric['hedef_deger'] ? number_format($metric['hedef_deger'], 2) . ' ' . $metric['birim'] : 'Belirtilmemiş'; ?><br>
                                                <strong>Güncelleme:</strong> <?php echo $metric['guncelleme_sikligi']; ?> dakika
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Dashboard'lar Tab -->
                    <div class="tab-pane fade" id="dashboards" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
                            <h5>KPI Dashboard'ları</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDashboardModal">
                                <i class="fas fa-plus"></i> Yeni Dashboard
                            </button>
                        </div>
                        
                        <div class="row">
                            <?php foreach ($dashboards as $dashboard): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="card dashboard-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="card-title"><?php echo htmlspecialchars($dashboard['dashboard_adi']); ?></h6>
                                                <p class="card-text text-muted small"><?php echo htmlspecialchars($dashboard['dashboard_aciklamasi']); ?></p>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-<?php echo $dashboard['aktif'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $dashboard['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                                    </span>
                                                    <span class="badge bg-info"><?php echo $dashboard_turleri[$dashboard['dashboard_turu']]; ?></span>
                                                    <?php if ($dashboard['varsayilan']): ?>
                                                    <span class="badge bg-warning">Varsayılan</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="kpi-dashboard.php?dashboard=<?php echo $dashboard['id']; ?>">
                                                        <i class="fas fa-eye me-1"></i>Görüntüle
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="editDashboard(<?php echo $dashboard['id']; ?>)">
                                                        <i class="fas fa-edit me-1"></i>Düzenle
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteDashboard(<?php echo $dashboard['id']; ?>)">
                                                        <i class="fas fa-trash me-1"></i>Sil
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong>Modül:</strong> <?php echo $dashboard['modul'] ? $moduller[$dashboard['modul']] ?? ucfirst($dashboard['modul']) : 'Genel'; ?><br>
                                                <strong>Metrik Sayısı:</strong> <?php echo count(json_decode($dashboard['metrik_listesi'], true)); ?><br>
                                                <strong>Yenileme:</strong> <?php echo $dashboard['guncelleme_sikligi']; ?> dakika
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Uyarılar Tab -->
                    <div class="tab-pane fade" id="alerts" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
                            <h5>KPI Uyarıları</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAlertModal">
                                <i class="fas fa-plus"></i> Yeni Uyarı
                            </button>
                        </div>
                        
                        <div class="row">
                            <?php foreach ($alerts as $alert): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="card alert-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="card-title"><?php echo htmlspecialchars($alert['uyari_adi']); ?></h6>
                                                <p class="card-text text-muted small"><?php echo htmlspecialchars($alert['uyari_mesaji']); ?></p>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-<?php echo $alert['aktif'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $alert['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                                    </span>
                                                    <span class="badge bg-<?php echo $alert['uyari_renk']; ?>"><?php echo $uyari_turleri[$alert['uyari_turu']]; ?></span>
                                                    <?php if ($alert['otomatik_bildirim']): ?>
                                                    <span class="badge bg-info">Otomatik Bildirim</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="editAlert(<?php echo $alert['id']; ?>)">
                                                        <i class="fas fa-edit me-1"></i>Düzenle
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="toggleAlertStatus(<?php echo $alert['id']; ?>, '<?php echo $alert['aktif'] ? 'pasif' : 'aktif'; ?>')">
                                                        <i class="fas fa-<?php echo $alert['aktif'] ? 'pause' : 'play'; ?> me-1"></i><?php echo $alert['aktif'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteAlert(<?php echo $alert['id']; ?>)">
                                                        <i class="fas fa-trash me-1"></i>Sil
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong>Metrik:</strong> <?php echo htmlspecialchars($alert['metrik_adi']); ?><br>
                                                <strong>Eşik Değer:</strong> <?php echo number_format($alert['esik_deger'], 2); ?><br>
                                                <strong>Kategori:</strong> <?php echo $kategoriler[$alert['kategori']] ?? ucfirst($alert['kategori']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Yeni Metrik Modal -->
    <div class="modal fade" id="createMetricModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni KPI Metrik</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_metric">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="metric_name" class="form-label">Metrik Adı</label>
                                    <input type="text" class="form-control" id="metric_name" name="metric_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Kategori</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Kategori Seçiniz</option>
                                        <?php foreach ($kategoriler as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="metric_description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="metric_description" name="metric_description" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="metric_type" class="form-label">Metrik Türü</label>
                                    <select class="form-select" id="metric_type" name="metric_type" required>
                                        <?php foreach ($metrik_turleri as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="unit" class="form-label">Birim</label>
                                    <input type="text" class="form-control" id="unit" name="unit" placeholder="örn: TL, %, adet">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Öncelik</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="1">1 - Yüksek</option>
                                        <option value="2" selected>2 - Orta</option>
                                        <option value="3">3 - Düşük</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="calculation_formula" class="form-label">Hesaplama Formülü</label>
                                    <input type="text" class="form-control" id="calculation_formula" name="calculation_formula" placeholder="örn: COUNT(*), SUM(toplam_tutar)">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="data_source" class="form-label">Veri Kaynağı</label>
                                    <input type="text" class="form-control" id="data_source" name="data_source" placeholder="örn: rezervasyonlar, fnb_siparisler">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="target_value" class="form-label">Hedef Değer</label>
                            <input type="number" class="form-control" id="target_value" name="target_value" step="0.01">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="update_frequency" class="form-label">Güncelleme Sıklığı (dakika)</label>
                                    <input type="number" class="form-control" id="update_frequency" name="update_frequency" value="60" min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="real_time" name="real_time">
                                        <label class="form-check-label" for="real_time">
                                            Gerçek Zamanlı Güncelleme
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Yeni Dashboard Modal -->
    <div class="modal fade" id="createDashboardModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni KPI Dashboard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_dashboard">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dashboard_name" class="form-label">Dashboard Adı</label>
                                    <input type="text" class="form-control" id="dashboard_name" name="dashboard_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dashboard_type" class="form-label">Dashboard Türü</label>
                                    <select class="form-select" id="dashboard_type" name="dashboard_type" required>
                                        <?php foreach ($dashboard_turleri as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dashboard_description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="dashboard_description" name="dashboard_description" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="module" class="form-label">Modül</label>
                                    <select class="form-select" id="module" name="module">
                                        <option value="">Genel</option>
                                        <?php foreach ($moduller as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="refresh_interval" class="form-label">Yenileme Sıklığı (dakika)</label>
                                    <input type="number" class="form-control" id="refresh_interval" name="refresh_interval" value="30" min="1">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Metrikler</label>
                            <div class="row">
                                <?php foreach ($metrics as $metric): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="metric_list[]" value="<?php echo $metric['metrik_adi']; ?>" id="metric_<?php echo $metric['id']; ?>">
                                        <label class="form-check-label" for="metric_<?php echo $metric['id']; ?>">
                                            <?php echo htmlspecialchars($metric['metrik_adi']); ?>
                                            <small class="text-muted">(<?php echo $kategoriler[$metric['kategori']] ?? ucfirst($metric['kategori']); ?>)</small>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="auto_update" name="auto_update" checked>
                                    <label class="form-check-label" for="auto_update">
                                        Otomatik Güncelleme
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default">
                                    <label class="form-check-label" for="is_default">
                                        Varsayılan Dashboard
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Yeni Uyarı Modal -->
    <div class="modal fade" id="createAlertModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni KPI Uyarısı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_alert">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="alert_name" class="form-label">Uyarı Adı</label>
                            <input type="text" class="form-control" id="alert_name" name="alert_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="metric_id" class="form-label">Metrik</label>
                            <select class="form-select" id="metric_id" name="metric_id" required>
                                <option value="">Metrik Seçiniz</option>
                                <?php foreach ($metrics as $metric): ?>
                                <option value="<?php echo $metric['id']; ?>"><?php echo htmlspecialchars($metric['metrik_adi']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="alert_type" class="form-label">Uyarı Türü</label>
                                    <select class="form-select" id="alert_type" name="alert_type" required>
                                        <?php foreach ($uyari_turleri as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="threshold_value" class="form-label">Eşik Değer</label>
                                    <input type="number" class="form-control" id="threshold_value" name="threshold_value" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alert_message" class="form-label">Uyarı Mesajı</label>
                            <textarea class="form-control" id="alert_message" name="alert_message" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alert_color" class="form-label">Uyarı Rengi</label>
                            <select class="form-select" id="alert_color" name="alert_color">
                                <?php foreach ($uyari_renkleri as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Bildirim Kanalları</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notification_channels[]" value="email" id="notif_email">
                                <label class="form-check-label" for="notif_email">Email</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notification_channels[]" value="dashboard" id="notif_dashboard" checked>
                                <label class="form-check-label" for="notif_dashboard">Dashboard</label>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_notification" name="auto_notification" checked>
                            <label class="form-check-label" for="auto_notification">
                                Otomatik Bildirim Gönder
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleMetricStatus(metricId, newStatus) {
            if (confirm('Metrik durumunu değiştirmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_metric_status">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="metric_id" value="${metricId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editMetric(metricId) {
            // Metrik düzenleme modalını aç
            alert('Metrik düzenleme özelliği geliştirilecek. Metrik ID: ' + metricId);
        }

        function deleteMetric(metricId) {
            if (confirm('Bu metriği silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
                // Metrik silme işlemi
                alert('Metrik silme özelliği geliştirilecek. Metrik ID: ' + metricId);
            }
        }

        function editDashboard(dashboardId) {
            // Dashboard düzenleme modalını aç
            alert('Dashboard düzenleme özelliği geliştirilecek. Dashboard ID: ' + dashboardId);
        }

        function deleteDashboard(dashboardId) {
            if (confirm('Bu dashboard\'u silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
                // Dashboard silme işlemi
                alert('Dashboard silme özelliği geliştirilecek. Dashboard ID: ' + dashboardId);
            }
        }

        function editAlert(alertId) {
            // Uyarı düzenleme modalını aç
            alert('Uyarı düzenleme özelliği geliştirilecek. Uyarı ID: ' + alertId);
        }

        function toggleAlertStatus(alertId, newStatus) {
            if (confirm('Uyarı durumunu değiştirmek istediğinizden emin misiniz?')) {
                // Uyarı durumu değiştirme işlemi
                alert('Uyarı durumu değiştirme özelliği geliştirilecek. Uyarı ID: ' + alertId);
            }
        }

        function deleteAlert(alertId) {
            if (confirm('Bu uyarıyı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
                // Uyarı silme işlemi
                alert('Uyarı silme özelliği geliştirilecek. Uyarı ID: ' + alertId);
            }
        }
    </script>
</body>
</html>

