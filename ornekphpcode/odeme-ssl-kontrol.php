<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-ssl-kontrol.php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/payment/PaymentProcessor.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_ssl_kontrol', 'Ödeme SSL kontrol yetkiniz bulunmamaktadır.');

$page_title = 'SSL Sertifikası Kontrolü';
$active_menu = 'odeme_yonetimi';

// Payment processor'ı başlat
$payment_processor = new PaymentProcessor($pdo);

// SSL kontrolü işlemi
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'check_ssl') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error_message = 'CSRF token hatası!';
    } else {
        $provider_id = $_POST['provider_id'] ?? '';
        if ($provider_id) {
            $ssl_result = $payment_processor->validateSSL($provider_id);
            
            // Sonucu veritabanına kaydet
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO odeme_ssl_loglari (
                            provider_id, url, ssl_valid, certificate_info, error_message
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $provider_id,
                        $ssl_result['certificate']['url'] ?? '',
                        $ssl_result['valid'] ? 1 : 0,
                        json_encode($ssl_result['certificate'] ?? []),
                        $ssl_result['error'] ?? null
                    ]);
                } catch (Exception $e) {
                    error_log("SSL log kaydetme hatası: " . $e->getMessage());
                }
            }
            
            $ssl_check_result = $ssl_result;
        }
    }
}

// Tüm sağlayıcıları getir
$providers = fetchAll("SELECT * FROM odeme_providerlari ORDER BY provider_adi");

// Son SSL kontrolleri
$ssl_logs = fetchAll("
    SELECT ssl.*, p.provider_adi 
    FROM odeme_ssl_loglari ssl 
    LEFT JOIN odeme_providerlari p ON ssl.provider_id = p.id 
    ORDER BY ssl.kontrol_tarihi DESC 
    LIMIT 20
");

// SSL istatistikleri
$ssl_stats = fetchOne("
    SELECT 
        COUNT(*) as toplam_kontrol,
        SUM(CASE WHEN ssl_valid = 1 THEN 1 ELSE 0 END) as gecerli_ssl,
        SUM(CASE WHEN ssl_valid = 0 THEN 1 ELSE 0 END) as gecersiz_ssl,
        AVG(CASE WHEN ssl_valid = 1 THEN 1 ELSE 0 END) * 100 as basari_orani
    FROM odeme_ssl_loglari 
    WHERE kontrol_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="index.php">Ana Sayfa</a></li>
                        <li class="breadcrumb-item"><a href="odeme-yonetimi.php">Ödeme Yönetimi</a></li>
                        <li class="breadcrumb-item"><a href="odeme-guvenlik-yonetimi.php">Güvenlik Yönetimi</a></li>
                        <li class="breadcrumb-item active">SSL Kontrolü</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="fas fa-shield-alt me-2"></i>SSL Sertifikası Kontrolü
                </h4>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- SSL İstatistikleri -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shield-alt text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($ssl_stats['toplam_kontrol'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Toplam Kontrol</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($ssl_stats['gecerli_ssl'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Geçerli SSL</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-times-circle text-danger" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($ssl_stats['gecersiz_ssl'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Geçersiz SSL</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                            <i class="fas fa-chart-line text-info" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($ssl_stats['basari_orani'] ?? 0, 1) ?>%</h5>
                            <p class="text-muted mb-0">Başarı Oranı</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- SSL Kontrolü -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>SSL Sertifikası Kontrolü</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="check_ssl">
                        
                        <div class="mb-3">
                            <label for="provider_id" class="form-label">Ödeme Sağlayıcısı *</label>
                            <select class="form-select" id="provider_id" name="provider_id" required>
                                <option value="">Sağlayıcı seçiniz...</option>
                                <?php foreach ($providers as $provider): ?>
                                    <option value="<?= $provider['id'] ?>"><?= htmlspecialchars($provider['provider_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>SSL Sertifikasını Kontrol Et
                        </button>
                    </form>
                </div>
            </div>

            <?php if (isset($ssl_check_result)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-<?= $ssl_check_result['valid'] ? 'check-circle text-success' : 'times-circle text-danger' ?> me-2"></i>
                            SSL Kontrol Sonucu
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($ssl_check_result['valid']): ?>
                            <div class="alert alert-success">
                                <h6><i class="fas fa-check-circle me-2"></i>SSL Sertifikası Geçerli</h6>
                                <?php if (isset($ssl_check_result['certificate'])): ?>
                                    <div class="mt-3">
                                        <h6>Sertifika Detayları:</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Konu:</strong> <?= htmlspecialchars($ssl_check_result['certificate']['subject']['CN'] ?? 'N/A') ?></li>
                                            <li><strong>Yayıncı:</strong> <?= htmlspecialchars($ssl_check_result['certificate']['issuer']['CN'] ?? 'N/A') ?></li>
                                            <li><strong>Geçerlilik Başlangıcı:</strong> <?= htmlspecialchars($ssl_check_result['certificate']['valid_from'] ?? 'N/A') ?></li>
                                            <li><strong>Geçerlilik Bitişi:</strong> <?= htmlspecialchars($ssl_check_result['certificate']['valid_to'] ?? 'N/A') ?></li>
                                            <li><strong>Seri Numarası:</strong> <?= htmlspecialchars($ssl_check_result['certificate']['serial_number'] ?? 'N/A') ?></li>
                                            <li><strong>İmza Algoritması:</strong> <?= htmlspecialchars($ssl_check_result['certificate']['signature_algorithm'] ?? 'N/A') ?></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-times-circle me-2"></i>SSL Sertifikası Geçersiz</h6>
                                <p class="mb-0"><?= htmlspecialchars($ssl_check_result['error'] ?? 'Bilinmeyen hata') ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Son SSL Kontrolleri -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Son SSL Kontrolleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Sağlayıcı</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ssl_logs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['provider_adi'] ?? 'Bilinmeyen') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $log['ssl_valid'] ? 'success' : 'danger' ?>">
                                                <?= $log['ssl_valid'] ? 'Geçerli' : 'Geçersiz' ?>
                                            </span>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($log['kontrol_tarihi'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="viewSSLDetails(<?= $log['id'] ?>)">
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
        </div>
    </div>

    <!-- SSL Detayları Modal -->
    <div class="modal fade" id="sslDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">SSL Sertifika Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="sslDetailsContent">
                    <!-- İçerik JavaScript ile yüklenecek -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewSSLDetails(logId) {
    // AJAX ile SSL detaylarını getir
    fetch('ajax/get-ssl-details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'log_id=' + logId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('sslDetailsContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('sslDetailsModal')).show();
        } else {
            alert('SSL detayları yüklenirken hata oluştu: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('SSL detayları yüklenirken hata oluştu');
    });
}
</script>

<?php include '../includes/footer.php'; ?>
