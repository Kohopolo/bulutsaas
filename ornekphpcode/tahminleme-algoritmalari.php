<?php
/**
 * Tahminleme Algoritmaları Dashboard
 * Talep tahmini, fiyat optimizasyonu için gelişmiş algoritmalar
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/prediction-algorithms-system.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('tahminleme_algoritmalari')) {
    $_SESSION['error_message'] = 'Tahminleme algoritmaları görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Tahminleme Algoritmaları sistemi
$predictionSystem = new PredictionAlgorithmsSystem($pdo);

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'predict_demand') {
            $model_id = (int)($_POST['model_id'] ?? 0);
            $start_date = sanitizeString($_POST['start_date']);
            $end_date = sanitizeString($_POST['end_date']);
            $room_type_id = !empty($_POST['room_type_id']) ? (int)$_POST['room_type_id'] : null;
            
            if (empty($start_date) || empty($end_date)) {
                throw new Exception("Başlangıç ve bitiş tarihleri zorunludur.");
            }
            
            $result = $predictionSystem->predictDemand($model_id, $start_date, $end_date, $room_type_id);
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
        if ($action == 'optimize_pricing') {
            $model_id = (int)($_POST['model_id'] ?? 0);
            $date = sanitizeString($_POST['date']);
            $room_type_id = (int)($_POST['room_type_id'] ?? 0);
            
            if (empty($date) || $room_type_id <= 0) {
                throw new Exception("Tarih ve oda tipi zorunludur.");
            }
            
            $result = $predictionSystem->optimizePricing($model_id, $date, $room_type_id);
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
        if ($action == 'predict_revenue') {
            $model_id = (int)($_POST['model_id'] ?? 0);
            $start_date = sanitizeString($_POST['start_date']);
            $end_date = sanitizeString($_POST['end_date']);
            $category = sanitizeString($_POST['category'] ?? 'toplam');
            
            if (empty($start_date) || empty($end_date)) {
                throw new Exception("Başlangıç ve bitiş tarihleri zorunludur.");
            }
            
            $result = $predictionSystem->predictRevenue($model_id, $start_date, $end_date, $category);
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
        if ($action == 'predict_occupancy') {
            $model_id = (int)($_POST['model_id'] ?? 0);
            $start_date = sanitizeString($_POST['start_date']);
            $end_date = sanitizeString($_POST['end_date']);
            $room_type_id = !empty($_POST['room_type_id']) ? (int)$_POST['room_type_id'] : null;
            
            if (empty($start_date) || empty($end_date)) {
                throw new Exception("Başlangıç ve bitiş tarihleri zorunludur.");
            }
            
            $result = $predictionSystem->predictOccupancy($model_id, $start_date, $end_date, $room_type_id);
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
        if ($action == 'analyze_customers') {
            $model_id = (int)($_POST['model_id'] ?? 0);
            $date = sanitizeString($_POST['date']);
            $segment = sanitizeString($_POST['segment'] ?? null);
            
            if (empty($date)) {
                throw new Exception("Tarih zorunludur.");
            }
            
            $result = $predictionSystem->analyzeCustomers($model_id, $date, $segment);
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
        if ($action == 'evaluate_model') {
            $model_id = (int)($_POST['model_id'] ?? 0);
            $start_date = sanitizeString($_POST['start_date']);
            $end_date = sanitizeString($_POST['end_date']);
            
            if (empty($start_date) || empty($end_date)) {
                throw new Exception("Başlangıç ve bitiş tarihleri zorunludur.");
            }
            
            $result = $predictionSystem->evaluateModelPerformance($model_id, $start_date, $end_date);
            
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
        
        if ($action == 'generate_report') {
            $model_id = (int)($_POST['model_id'] ?? 0);
            $start_date = sanitizeString($_POST['start_date']);
            $end_date = sanitizeString($_POST['end_date']);
            $report_type = sanitizeString($_POST['report_type'] ?? 'genel');
            
            if (empty($start_date) || empty($end_date)) {
                throw new Exception("Başlangıç ve bitiş tarihleri zorunludur.");
            }
            
            $result = $predictionSystem->generatePredictionReport($model_id, $start_date, $end_date, $report_type);
            
            if ($result['success']) {
                $success_message = $result['message'];
                $_SESSION['report_id'] = $result['report_id'];
            } else {
                $error_message = $result['message'];
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Bugünkü tarih
$bugun = date('Y-m-d');
$bugun_tarih = date('d.m.Y');

// Tahminleme modelleri
$modeller = fetchAll("
    SELECT *
    FROM tahminleme_modelleri
    WHERE aktif = 1
    ORDER BY model_turu, model_adi
");

// Oda tipleri
$oda_tipleri = fetchAll("
    SELECT id, oda_tipi_adi
    FROM oda_tipleri
    WHERE aktif = 1
    ORDER BY oda_tipi_adi
");

// Son tahminler
$son_talep_tahminleri = fetchAll("
    SELECT 
        tt.*,
        tm.model_adi,
        ot.oda_tipi_adi
    FROM talep_tahmini tt
    LEFT JOIN tahminleme_modelleri tm ON tt.model_id = tm.id
    LEFT JOIN oda_tipleri ot ON tt.oda_tipi_id = ot.id
    ORDER BY tt.tahmin_tarihi DESC, tt.olusturma_tarihi DESC
    LIMIT 20
");

// Son fiyat optimizasyonları
$son_fiyat_optimizasyonlari = fetchAll("
    SELECT 
        fo.*,
        tm.model_adi,
        ot.oda_tipi_adi
    FROM fiyat_optimizasyonu fo
    LEFT JOIN tahminleme_modelleri tm ON fo.model_id = tm.id
    LEFT JOIN oda_tipleri ot ON fo.oda_tipi_id = ot.id
    ORDER BY fo.tarih DESC, fo.olusturma_tarihi DESC
    LIMIT 20
");

// Model performans metrikleri
$model_performanslari = fetchAll("
    SELECT 
        tm.model_adi,
        tm.model_turu,
        tm.dogruluk_orani,
        tm.son_egitim_tarihi,
        tm.son_tahmin_tarihi,
        tm.model_durumu
    FROM tahminleme_modelleri tm
    WHERE tm.aktif = 1
    ORDER BY tm.dogruluk_orani DESC
");

// Tahminleme faktörleri
$tahminleme_faktorleri = fetchAll("
    SELECT *
    FROM tahminleme_faktorleri
    WHERE aktif = 1
    ORDER BY faktor_turu, faktor_adi
");

// Model türleri
$model_turleri = [
    'talep_tahmini' => 'Talep Tahmini',
    'fiyat_optimizasyonu' => 'Fiyat Optimizasyonu',
    'gelir_tahmini' => 'Gelir Tahmini',
    'doluluk_tahmini' => 'Doluluk Tahmini',
    'musteri_analizi' => 'Müşteri Analizi'
];

// Algoritma türleri
$algoritma_turleri = [
    'linear_regression' => 'Doğrusal Regresyon',
    'polynomial_regression' => 'Polinom Regresyon',
    'time_series' => 'Zaman Serisi',
    'neural_network' => 'Yapay Sinir Ağı',
    'decision_tree' => 'Karar Ağacı',
    'random_forest' => 'Rastgele Orman',
    'svm' => 'Destek Vektör Makinesi',
    'clustering' => 'Kümeleme'
];

// Gelir kategorileri
$gelir_kategorileri = [
    'oda' => 'Oda',
    'fnb' => 'F&B',
    'spa' => 'Spa',
    'transfer' => 'Transfer',
    'diger' => 'Diğer',
    'toplam' => 'Toplam'
];

// Müşteri segmentleri
$musteri_segmentleri = [
    'business' => 'İş',
    'leisure' => 'Tatil',
    'group' => 'Grup',
    'corporate' => 'Kurumsal',
    'diger' => 'Diğer'
];

// Rapor türleri
$rapor_turleri = [
    'talep_tahmini' => 'Talep Tahmini',
    'fiyat_optimizasyonu' => 'Fiyat Optimizasyonu',
    'gelir_tahmini' => 'Gelir Tahmini',
    'doluluk_tahmini' => 'Doluluk Tahmini',
    'musteri_analizi' => 'Müşteri Analizi',
    'genel' => 'Genel'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tahminleme Algoritmaları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .model-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .model-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .model-card.talep_tahmini { border-left-color: #007bff; }
        .model-card.fiyat_optimizasyonu { border-left-color: #28a745; }
        .model-card.gelir_tahmini { border-left-color: #17a2b8; }
        .model-card.doluluk_tahmini { border-left-color: #ffc107; }
        .model-card.musteri_analizi { border-left-color: #6f42c1; }
        
        .accuracy-excellent { color: #28a745; }
        .accuracy-good { color: #17a2b8; }
        .accuracy-warning { color: #ffc107; }
        .accuracy-danger { color: #dc3545; }
        
        .status-active { color: #28a745; }
        .status-training { color: #ffc107; }
        .status-passive { color: #6c757d; }
        .status-error { color: #dc3545; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .prediction-card {
            border-left: 4px solid #007bff;
        }
        
        .optimization-card {
            border-left: 4px solid #28a745;
        }
        
        .factor-card {
            border-left: 4px solid #17a2b8;
        }
        
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .algorithm-badge {
            font-size: 0.75rem;
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
                        <i class="fas fa-brain me-2"></i>Tahminleme Algoritmaları
                        <small class="text-muted">Talep Tahmini, Fiyat Optimizasyonu</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#predictDemandModal">
                                <i class="fas fa-chart-line"></i> Talep Tahmini
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#optimizePricingModal">
                                <i class="fas fa-dollar-sign"></i> Fiyat Optimizasyonu
                            </button>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#predictRevenueModal">
                                <i class="fas fa-chart-area"></i> Gelir Tahmini
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-cogs"></i> Model İşlemleri
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#evaluateModelModal">
                                    <i class="fas fa-chart-bar"></i> Model Değerlendir
                                </a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                                    <i class="fas fa-file-alt"></i> Rapor Oluştur
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#analyzeCustomersModal">
                                    <i class="fas fa-users"></i> Müşteri Analizi
                                </a></li>
                            </ul>
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

                <!-- Model Kartları -->
                <div class="row mb-4">
                    <?php foreach ($modeller as $model): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                        <div class="card model-card <?php echo $model['model_turu']; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted mb-1"><?php echo htmlspecialchars($model['model_adi']); ?></h6>
                                        <div class="mb-2">
                                            <span class="badge bg-primary algorithm-badge">
                                                <?php echo $algoritma_turleri[$model['algoritma_turu']] ?? ucfirst($model['algoritma_turu']); ?>
                                            </span>
                                            <span class="badge bg-secondary algorithm-badge">
                                                <?php echo $model_turleri[$model['model_turu']] ?? ucfirst($model['model_turu']); ?>
                                            </span>
                                        </div>
                                        <div class="metric-value <?php 
                                            if ($model['dogruluk_orani'] >= 90) echo 'accuracy-excellent';
                                            elseif ($model['dogruluk_orani'] >= 80) echo 'accuracy-good';
                                            elseif ($model['dogruluk_orani'] >= 70) echo 'accuracy-warning';
                                            else echo 'accuracy-danger';
                                        ?>">
                                            <?php echo $model['dogruluk_orani'] ? $model['dogruluk_orani'] . '%' : 'N/A'; ?>
                                        </div>
                                        <small class="text-muted">Doğruluk Oranı</small>
                                    </div>
                                    <div class="text-end">
                                        <i class="fas fa-brain fa-2x text-primary"></i>
                                        <div class="mt-2">
                                            <span class="badge bg-<?php 
                                                switch($model['model_durumu']) {
                                                    case 'aktif': echo 'success'; break;
                                                    case 'egitim': echo 'warning'; break;
                                                    case 'pasif': echo 'secondary'; break;
                                                    case 'hata': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst($model['model_durumu']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Grafikler -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-line me-2"></i>Model Performansları
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="modelPerformanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-chart-pie me-2"></i>Algoritma Dağılımı
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="algorithmDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son Tahminler -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card prediction-card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-line me-2"></i>Son Talep Tahminleri
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Model</th>
                                                <th>Tarih</th>
                                                <th>Oda Tipi</th>
                                                <th>Tahmin</th>
                                                <th>Gerçek</th>
                                                <th>Hata</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($son_talep_tahminleri as $tahmin): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tahmin['model_adi']); ?></td>
                                                <td><?php echo formatTurkishDate($tahmin['tahmin_tarihi'], 'd.m.Y'); ?></td>
                                                <td><?php echo htmlspecialchars($tahmin['oda_tipi_adi'] ?? 'Tümü'); ?></td>
                                                <td><?php echo $tahmin['tahmin_edilen_talep']; ?></td>
                                                <td><?php echo $tahmin['gercek_talep'] ?? '-'; ?></td>
                                                <td>
                                                    <?php if ($tahmin['tahmin_hatasi']): ?>
                                                        <span class="badge bg-<?php echo $tahmin['tahmin_hatasi'] < 10 ? 'success' : ($tahmin['tahmin_hatasi'] < 20 ? 'warning' : 'danger'); ?>">
                                                            %<?php echo $tahmin['tahmin_hatasi']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card optimization-card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-dollar-sign me-2"></i>Son Fiyat Optimizasyonları
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Model</th>
                                                <th>Tarih</th>
                                                <th>Oda Tipi</th>
                                                <th>Mevcut</th>
                                                <th>Önerilen</th>
                                                <th>Risk</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($son_fiyat_optimizasyonlari as $optimizasyon): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($optimizasyon['model_adi']); ?></td>
                                                <td><?php echo formatTurkishDate($optimizasyon['tarih'], 'd.m.Y'); ?></td>
                                                <td><?php echo htmlspecialchars($optimizasyon['oda_tipi_adi']); ?></td>
                                                <td><?php echo number_format($optimizasyon['mevcut_fiyat'], 0, ',', '.'); ?> ₺</td>
                                                <td><?php echo number_format($optimizasyon['oneri_edilen_fiyat'], 0, ',', '.'); ?> ₺</td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($optimizasyon['risk_seviyesi']) {
                                                            case 'dusuk': echo 'success'; break;
                                                            case 'orta': echo 'warning'; break;
                                                            case 'yuksek': echo 'danger'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($optimizasyon['risk_seviyesi']); ?>
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

                <!-- Tahminleme Faktörleri -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card factor-card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-cogs me-2"></i>Tahminleme Faktörleri
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Faktör Adı</th>
                                                <th>Tür</th>
                                                <th>Açıklama</th>
                                                <th>Ağırlık</th>
                                                <th>Veri Kaynağı</th>
                                                <th>Güncelleme</th>
                                                <th>Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tahminleme_faktorleri as $faktor): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($faktor['faktor_adi']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst($faktor['faktor_turu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($faktor['faktor_aciklamasi']); ?></td>
                                                <td><?php echo $faktor['agirlik']; ?></td>
                                                <td><?php echo htmlspecialchars($faktor['veri_kaynagi']); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst($faktor['guncelleme_sikligi']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $faktor['aktif'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $faktor['aktif'] ? 'Aktif' : 'Pasif'; ?>
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
            </main>
        </div>
    </div>

    <!-- Talep Tahmini Modal -->
    <div class="modal fade" id="predictDemandModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Talep Tahmini Yap</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="predict_demand">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="model_id" class="form-label">Model</label>
                            <select class="form-select" id="model_id" name="model_id" required>
                                <option value="">Model Seçin</option>
                                <?php foreach ($modeller as $model): ?>
                                    <?php if ($model['model_turu'] == 'talep_tahmini'): ?>
                                    <option value="<?php echo $model['id']; ?>">
                                        <?php echo htmlspecialchars($model['model_adi']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room_type_id" class="form-label">Oda Tipi (Opsiyonel)</label>
                            <select class="form-select" id="room_type_id" name="room_type_id">
                                <option value="">Tüm Oda Tipleri</option>
                                <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                <option value="<?php echo $oda_tipi['id']; ?>">
                                    <?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Tahmin Yap</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fiyat Optimizasyonu Modal -->
    <div class="modal fade" id="optimizePricingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fiyat Optimizasyonu Yap</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="optimize_pricing">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="model_id" class="form-label">Model</label>
                            <select class="form-select" id="model_id" name="model_id" required>
                                <option value="">Model Seçin</option>
                                <?php foreach ($modeller as $model): ?>
                                    <?php if ($model['model_turu'] == 'fiyat_optimizasyonu'): ?>
                                    <option value="<?php echo $model['id']; ?>">
                                        <?php echo htmlspecialchars($model['model_adi']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo $bugun; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room_type_id" class="form-label">Oda Tipi</label>
                            <select class="form-select" id="room_type_id" name="room_type_id" required>
                                <option value="">Oda Tipi Seçin</option>
                                <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                <option value="<?php echo $oda_tipi['id']; ?>">
                                    <?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">Optimize Et</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Gelir Tahmini Modal -->
    <div class="modal fade" id="predictRevenueModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gelir Tahmini Yap</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="predict_revenue">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="model_id" class="form-label">Model</label>
                            <select class="form-select" id="model_id" name="model_id" required>
                                <option value="">Model Seçin</option>
                                <?php foreach ($modeller as $model): ?>
                                    <?php if ($model['model_turu'] == 'gelir_tahmini'): ?>
                                    <option value="<?php echo $model['id']; ?>">
                                        <?php echo htmlspecialchars($model['model_adi']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Gelir Kategorisi</label>
                            <select class="form-select" id="category" name="category" required>
                                <?php foreach ($gelir_kategorileri as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-info">Tahmin Yap</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Müşteri Analizi Modal -->
    <div class="modal fade" id="analyzeCustomersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Müşteri Analizi Yap</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="analyze_customers">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="model_id" class="form-label">Model</label>
                            <select class="form-select" id="model_id" name="model_id" required>
                                <option value="">Model Seçin</option>
                                <?php foreach ($modeller as $model): ?>
                                    <?php if ($model['model_turu'] == 'musteri_analizi'): ?>
                                    <option value="<?php echo $model['id']; ?>">
                                        <?php echo htmlspecialchars($model['model_adi']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo $bugun; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="segment" class="form-label">Müşteri Segmenti (Opsiyonel)</label>
                            <select class="form-select" id="segment" name="segment">
                                <option value="">Tüm Segmentler</option>
                                <?php foreach ($musteri_segmentleri as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Analiz Yap</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Model Değerlendirme Modal -->
    <div class="modal fade" id="evaluateModelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Model Performansını Değerlendir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="evaluate_model">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="model_id" class="form-label">Model</label>
                            <select class="form-select" id="model_id" name="model_id" required>
                                <option value="">Model Seçin</option>
                                <?php foreach ($modeller as $model): ?>
                                <option value="<?php echo $model['id']; ?>">
                                    <?php echo htmlspecialchars($model['model_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-warning">Değerlendir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rapor Oluştur Modal -->
    <div class="modal fade" id="generateReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tahminleme Raporu Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_report">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="model_id" class="form-label">Model</label>
                            <select class="form-select" id="model_id" name="model_id" required>
                                <option value="">Model Seçin</option>
                                <?php foreach ($modeller as $model): ?>
                                <option value="<?php echo $model['id']; ?>">
                                    <?php echo htmlspecialchars($model['model_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="report_type" class="form-label">Rapor Türü</label>
                            <select class="form-select" id="report_type" name="report_type" required>
                                <?php foreach ($rapor_turleri as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">Rapor Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Grafikleri oluştur
        document.addEventListener('DOMContentLoaded', function() {
            // Model performans grafiği
            const modelPerformanceCtx = document.getElementById('modelPerformanceChart');
            if (modelPerformanceCtx) {
                new Chart(modelPerformanceCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Linear Regression', 'Time Series', 'Neural Network', 'Decision Tree', 'Random Forest'],
                        datasets: [{
                            label: 'Doğruluk Oranı (%)',
                            data: [85.2, 88.7, 92.1, 79.8, 86.4],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.8)',
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(255, 205, 86, 0.8)',
                                'rgba(75, 192, 192, 0.8)',
                                'rgba(153, 102, 255, 0.8)'
                            ],
                            borderColor: [
                                'rgb(54, 162, 235)',
                                'rgb(255, 99, 132)',
                                'rgb(255, 205, 86)',
                                'rgb(75, 192, 192)',
                                'rgb(153, 102, 255)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
            }

            // Algoritma dağılım grafiği
            const algorithmDistributionCtx = document.getElementById('algorithmDistributionChart');
            if (algorithmDistributionCtx) {
                new Chart(algorithmDistributionCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Linear Regression', 'Time Series', 'Neural Network', 'Decision Tree', 'Random Forest', 'SVM', 'Clustering'],
                        datasets: [{
                            data: [2, 1, 1, 1, 1, 1, 2],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.8)',
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(255, 205, 86, 0.8)',
                                'rgba(75, 192, 192, 0.8)',
                                'rgba(153, 102, 255, 0.8)',
                                'rgba(255, 159, 64, 0.8)',
                                'rgba(199, 199, 199, 0.8)'
                            ],
                            borderColor: [
                                'rgb(54, 162, 235)',
                                'rgb(255, 99, 132)',
                                'rgb(255, 205, 86)',
                                'rgb(75, 192, 192)',
                                'rgb(153, 102, 255)',
                                'rgb(255, 159, 64)',
                                'rgb(199, 199, 199)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        });

        // Tarih aralığı için varsayılan değerler
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const lastWeek = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            
            // Tüm date input'ları için varsayılan değerleri ayarla
            const startDateInputs = document.querySelectorAll('input[name="start_date"]');
            const endDateInputs = document.querySelectorAll('input[name="end_date"]');
            
            startDateInputs.forEach(input => {
                if (!input.value) {
                    input.value = lastWeek.toISOString().split('T')[0];
                }
            });
            
            endDateInputs.forEach(input => {
                if (!input.value) {
                    input.value = today.toISOString().split('T')[0];
                }
            });
        });
    </script>
</body>
</html>

