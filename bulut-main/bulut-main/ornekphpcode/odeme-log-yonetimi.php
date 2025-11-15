<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-log-yonetimi.php
// Ödeme log yönetimi sayfası

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/logging/PaymentLogger.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_log_goruntule', 'Ödeme log yönetimi yetkiniz bulunmamaktadır.');

$logger = new PaymentLogger($database_connection);
$page_title = 'Ödeme Log Yönetimi';

// Filtreler
$filters = [
    'level' => $_GET['level'] ?? '',
    'transaction_id' => $_GET['transaction_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'user_id' => $_GET['user_id'] ?? '',
    'limit' => $_GET['limit'] ?? 100,
    'offset' => $_GET['offset'] ?? 0
];

// Log temizleme
if (isset($_POST['cleanup_logs']) && hasPermission('odeme_log_temizle')) {
    $days = $_POST['cleanup_days'] ?? 90;
    $result = $logger->cleanupOldLogs($days);
    
    // Temizleme geçmişini kaydet
    $stmt = $database_connection->prepare("
        INSERT INTO odeme_log_temizleme_gecmisi 
        (temizleme_tarihi, temizlenen_gun, silinen_kayit_sayisi, silinen_dosya_sayisi, kullanici_id)
        VALUES (NOW(), ?, ?, ?, ?)
    ");
    $stmt->execute([$days, $result['deleted_db_records'], $result['deleted_files'], $_SESSION['kullanici_id']]);
    
    $success_message = "Log temizleme tamamlandı. {$result['deleted_db_records']} veritabanı kaydı ve {$result['deleted_files']} dosya silindi.";
}

// Logları getir
$logs = $logger->getLogs($filters);
$log_stats = $logger->getLogStats('7_days');

// Toplam log sayısı
$total_logs_stmt = $database_connection->prepare("
    SELECT COUNT(*) as total FROM odeme_loglari 
    WHERE DATE(timestamp) BETWEEN ? AND ?
");
$total_logs_stmt->execute([$filters['date_from'], $filters['date_to']]);
$total_logs = $total_logs_stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .log-level-debug { color: #6c757d; }
        .log-level-info { color: #17a2b8; }
        .log-level-warning { color: #ffc107; }
        .log-level-error { color: #dc3545; }
        .log-level-critical { color: #721c24; font-weight: bold; }
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            line-height: 1.4;
        }
        .log-context {
            background-color: #f8f9fa;
            border-left: 3px solid #007bff;
            padding: 10px;
            margin: 5px 0;
            border-radius: 3px;
        }
        .log-table {
            font-size: 0.85em;
        }
        .log-table th {
            background-color: #f8f9fa;
            border-top: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-file-alt me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Yenile
                            </button>
                            <?php if (hasPermission('odeme_log_export')): ?>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="exportLogs()">
                                <i class="fas fa-download"></i> Dışa Aktar
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Log İstatistikleri -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-primary"><?php echo $total_logs; ?></h5>
                                <p class="card-text">Toplam Log</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-info"><?php echo array_sum(array_column($log_stats, 'count')); ?></h5>
                                <p class="card-text">Son 7 Gün</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-warning"><?php echo count(array_filter($log_stats, function($s) { return $s['level'] === 'ERROR'; })); ?></h5>
                                <p class="card-text">Hata Logları</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-danger"><?php echo count(array_filter($log_stats, function($s) { return $s['level'] === 'CRITICAL'; })); ?></h5>
                                <p class="card-text">Kritik Loglar</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log Filtreleri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Log Filtreleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Log Seviyesi</label>
                                <select name="level" class="form-select">
                                    <option value="">Tümü</option>
                                    <option value="DEBUG" <?php echo $filters['level'] === 'DEBUG' ? 'selected' : ''; ?>>DEBUG</option>
                                    <option value="INFO" <?php echo $filters['level'] === 'INFO' ? 'selected' : ''; ?>>INFO</option>
                                    <option value="WARNING" <?php echo $filters['level'] === 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                                    <option value="ERROR" <?php echo $filters['level'] === 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                                    <option value="CRITICAL" <?php echo $filters['level'] === 'CRITICAL' ? 'selected' : ''; ?>>CRITICAL</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tarih Başlangıç</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $filters['date_from']; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tarih Bitiş</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $filters['date_to']; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">İşlem ID</label>
                                <input type="text" name="transaction_id" class="form-control" value="<?php echo $filters['transaction_id']; ?>" placeholder="Transaction ID">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Kullanıcı ID</label>
                                <input type="number" name="user_id" class="form-control" value="<?php echo $filters['user_id']; ?>" placeholder="User ID">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Limit</label>
                                <select name="limit" class="form-select">
                                    <option value="50" <?php echo $filters['limit'] == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $filters['limit'] == 100 ? 'selected' : ''; ?>>100</option>
                                    <option value="200" <?php echo $filters['limit'] == 200 ? 'selected' : ''; ?>>200</option>
                                    <option value="500" <?php echo $filters['limit'] == 500 ? 'selected' : ''; ?>>500</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>
                                    Filtrele
                                </button>
                                <a href="odeme-log-yonetimi.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>
                                    Temizle
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Log Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            Log Kayıtları
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover log-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Seviye</th>
                                        <th>Mesaj</th>
                                        <th>İşlem ID</th>
                                        <th>IP</th>
                                        <th>Kullanıcı</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $this->getLevelColor($log['level']); ?> log-level-<?php echo strtolower($log['level']); ?>">
                                                    <?php echo $log['level']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="log-entry">
                                                    <?php echo htmlspecialchars($log['message']); ?>
                                                    <?php if (!empty($log['context'])): ?>
                                                        <button class="btn btn-sm btn-outline-info ms-2" onclick="showContext(<?php echo $log['id']; ?>)">
                                                            <i class="fas fa-info-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($log['transaction_id']): ?>
                                                    <code><?php echo htmlspecialchars($log['transaction_id']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <span class="badge bg-secondary"><?php echo $log['user_id']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="showLogDetails(<?php echo $log['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sayfalama -->
                <?php if ($total_logs > $filters['limit']): ?>
                <nav aria-label="Log sayfalama" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php
                        $current_page = floor($filters['offset'] / $filters['limit']) + 1;
                        $total_pages = ceil($total_logs / $filters['limit']);
                        $max_pages = 10;
                        
                        $start_page = max(1, $current_page - floor($max_pages / 2));
                        $end_page = min($total_pages, $start_page + $max_pages - 1);
                        
                        if ($current_page > 1):
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['offset' => ($current_page - 2) * $filters['limit']])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['offset' => ($i - 1) * $filters['limit']])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['offset' => $current_page * $filters['limit']])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

                <!-- Log Temizleme -->
                <?php if (hasPermission('odeme_log_temizle')): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-broom me-2"></i>
                            Log Temizleme
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return confirm('Eski logları silmek istediğinizden emin misiniz?')">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Temizlenecek Gün Sayısı</label>
                                    <select name="cleanup_days" class="form-select">
                                        <option value="30">30 Gün</option>
                                        <option value="60">60 Gün</option>
                                        <option value="90" selected>90 Gün</option>
                                        <option value="180">180 Gün</option>
                                        <option value="365">1 Yıl</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="cleanup_logs" class="btn btn-warning">
                                        <i class="fas fa-broom me-1"></i>
                                        Eski Logları Temizle
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Log Detay Modal -->
    <div class="modal fade" id="logDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Log Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="logDetailContent">
                    <!-- Log detayları buraya yüklenecek -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshPage() {
            location.reload();
        }

        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open('odeme-log-yonetimi.php?' + params.toString(), '_blank');
        }

        function showLogDetails(logId) {
            // AJAX ile log detaylarını getir
            fetch(`ajax/get-log-details.php?id=${logId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('logDetailContent').innerHTML = data.html;
                    new bootstrap.Modal(document.getElementById('logDetailModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Log detayları yüklenirken hata oluştu.');
                });
        }

        function showContext(logId) {
            // Context bilgilerini göster
            fetch(`ajax/get-log-context.php?id=${logId}`)
                .then(response => response.json())
                .then(data => {
                    const contextDiv = document.createElement('div');
                    contextDiv.className = 'log-context';
                    contextDiv.innerHTML = '<pre>' + JSON.stringify(data.context, null, 2) + '</pre>';
                    
                    // Context'i log satırının altına ekle
                    const logRow = event.target.closest('tr');
                    const nextRow = logRow.nextElementSibling;
                    if (nextRow && nextRow.classList.contains('context-row')) {
                        nextRow.remove();
                    } else {
                        const contextRow = document.createElement('tr');
                        contextRow.className = 'context-row';
                        contextRow.innerHTML = '<td colspan="7"></td>';
                        contextRow.querySelector('td').appendChild(contextDiv);
                        logRow.parentNode.insertBefore(contextRow, nextRow);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    </script>
</body>
</html>

<?php
// Log seviyesi renk fonksiyonu
function getLevelColor($level) {
    switch ($level) {
        case 'DEBUG': return 'secondary';
        case 'INFO': return 'info';
        case 'WARNING': return 'warning';
        case 'ERROR': return 'danger';
        case 'CRITICAL': return 'dark';
        default: return 'secondary';
    }
}
?>
