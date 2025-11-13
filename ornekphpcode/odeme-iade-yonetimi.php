<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-iade-yonetimi.php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/payment/PaymentRefund.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_iade_yonetimi', 'Ödeme iade yönetimi yetkiniz bulunmamaktadır.');

$page_title = 'İade Yönetimi';
$active_menu = 'odeme_yonetimi';

// Payment refund'ı başlat
$refund = new PaymentRefund($pdo);

// İade istatistikleri
$refund_stats = fetchOne("
    SELECT 
        COUNT(*) as toplam_iade,
        SUM(CASE WHEN odeme_durumu = 'iade_edildi' THEN 1 ELSE 0 END) as basarili_iade,
        SUM(CASE WHEN odeme_durumu = 'basarisiz' THEN 1 ELSE 0 END) as basarisiz_iade,
        SUM(CASE WHEN iade_onay_durumu = 'beklemede' THEN 1 ELSE 0 END) as onay_bekleyen,
        SUM(CASE WHEN odeme_durumu = 'iade_edildi' THEN odeme_tutari ELSE 0 END) as toplam_iade_tutari,
        AVG(CASE WHEN odeme_durumu = 'iade_edildi' THEN odeme_tutari ELSE NULL END) as ortalama_iade_tutari
    FROM odeme_islemleri 
    WHERE odeme_turu = 'iade' 
    AND islem_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

// Onay bekleyen iadeler
$pending_refunds = fetchAll("
    SELECT 
        oi.*,
        p.provider_adi,
        ot.islem_referans_no as original_transaction_id,
        ot.odeme_tutari as original_amount,
        DATEDIFF(NOW(), oi.islem_tarihi) as bekleme_gunu
    FROM odeme_islemleri oi
    LEFT JOIN odeme_providerlari p ON oi.provider_id = p.id
    LEFT JOIN odeme_islemleri ot ON oi.original_transaction_id = ot.id
    WHERE oi.odeme_turu = 'iade' 
    AND oi.iade_onay_durumu = 'beklemede'
    ORDER BY oi.islem_tarihi ASC
    LIMIT 20
");

// Son iadeler
$recent_refunds = fetchAll("
    SELECT 
        oi.*,
        p.provider_adi,
        ot.islem_referans_no as original_transaction_id,
        ot.odeme_tutari as original_amount,
        k.ad_soyad as onaylayan_kullanici
    FROM odeme_islemleri oi
    LEFT JOIN odeme_providerlari p ON oi.provider_id = p.id
    LEFT JOIN odeme_islemleri ot ON oi.original_transaction_id = ot.id
    LEFT JOIN kullanicilar k ON oi.iade_onaylayan_kullanici_id = k.id
    WHERE oi.odeme_turu = 'iade'
    ORDER BY oi.islem_tarihi DESC
    LIMIT 20
");

// İade sebepleri
$refund_reasons = fetchAll("SELECT * FROM odeme_iade_sebepleri WHERE aktif = 1 ORDER BY sira");

// Form işlemleri
if ($_POST) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error_message = 'CSRF token hatası!';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'process_refund':
                $refund_data = [
                    'original_transaction_id' => $_POST['original_transaction_id'],
                    'amount' => $_POST['amount'],
                    'reason' => $_POST['reason']
                ];
                
                $result = $refund->processRefund($refund_data);
                if ($result['success']) {
                    $success_message = 'İade işlemi başlatıldı!';
                } else {
                    $error_message = 'İade işlemi hatası: ' . $result['error_message'];
                }
                break;
                
            case 'approve_refund':
                $refund_id = $_POST['refund_id'];
                $approval_note = $_POST['approval_note'] ?? '';
                
                try {
                    $stmt = $pdo->prepare("CALL ProcessRefundApproval(?, ?, 'onaylandi', ?)");
                    $stmt->execute([$refund_id, $_SESSION['user_id'], $approval_note]);
                    $success_message = 'İade onaylandı!';
                } catch (Exception $e) {
                    $error_message = 'İade onaylama hatası: ' . $e->getMessage();
                }
                break;
                
            case 'reject_refund':
                $refund_id = $_POST['refund_id'];
                $approval_note = $_POST['approval_note'] ?? '';
                
                try {
                    $stmt = $pdo->prepare("CALL ProcessRefundApproval(?, ?, 'reddedildi', ?)");
                    $stmt->execute([$refund_id, $_SESSION['user_id'], $approval_note]);
                    $success_message = 'İade reddedildi!';
                } catch (Exception $e) {
                    $error_message = 'İade reddetme hatası: ' . $e->getMessage();
                }
                break;
                
            case 'query_refund_status':
                $refund_id = $_POST['refund_id'];
                $result = $refund->queryRefundStatus($refund_id);
                if ($result['success']) {
                    $success_message = 'İade durumu güncellendi!';
                } else {
                    $error_message = 'İade durumu sorgulama hatası: ' . $result['error_message'];
                }
                break;
        }
        
        // Sayfayı yenile
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

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
                        <li class="breadcrumb-item active">İade Yönetimi</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="fas fa-undo me-2"></i>İade Yönetimi
                </h4>
            </div>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- İade İstatistikleri -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-undo text-primary" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($refund_stats['toplam_iade'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Toplam İade</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-success">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($refund_stats['basarili_iade'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Başarılı</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <i class="fas fa-times-circle text-danger" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($refund_stats['basarisiz_iade'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Başarısız</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-clock text-warning" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($refund_stats['onay_bekleyen'] ?? 0) ?></h5>
                    <p class="text-muted mb-0">Onay Bekleyen</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-info">
                <div class="card-body text-center">
                    <i class="fas fa-lira-sign text-info" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($refund_stats['toplam_iade_tutari'] ?? 0, 2) ?>₺</h5>
                    <p class="text-muted mb-0">Toplam Tutar</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-secondary">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line text-secondary" style="font-size: 2rem;"></i>
                    <h5 class="mt-2"><?= number_format($refund_stats['ortalama_iade_tutari'] ?? 0, 2) ?>₺</h5>
                    <p class="text-muted mb-0">Ortalama</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- İade İşlemi -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>İade İşlemi</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="process_refund">
                        
                        <div class="mb-3">
                            <label for="original_transaction_id" class="form-label">Orijinal İşlem ID *</label>
                            <input type="text" class="form-control" id="original_transaction_id" name="original_transaction_id" required>
                            <div class="form-text">İşlem referans numarası veya ID</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">İade Tutarı (₺) *</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">İade Sebebi *</label>
                            <select class="form-select" id="reason" name="reason" required>
                                <option value="">Sebep seçiniz...</option>
                                <?php foreach ($refund_reasons as $reason): ?>
                                    <option value="<?= $reason['sebep_kodu'] ?>"><?= htmlspecialchars($reason['sebep_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-undo me-2"></i>İade İşlemini Başlat
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Onay Bekleyen İadeler -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Onay Bekleyen İadeler</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>İade ID</th>
                                    <th>Sağlayıcı</th>
                                    <th>Orijinal İşlem</th>
                                    <th>İade Tutarı</th>
                                    <th>Sebep</th>
                                    <th>Bekleme</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_refunds as $pending): ?>
                                    <tr>
                                        <td><code><?= $pending['islem_referans_no'] ?></code></td>
                                        <td><?= htmlspecialchars($pending['provider_adi']) ?></td>
                                        <td><code><?= htmlspecialchars($pending['original_transaction_id']) ?></code></td>
                                        <td><?= number_format($pending['odeme_tutari'], 2) ?>₺</td>
                                        <td><?= htmlspecialchars($pending['iade_sebebi']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $pending['bekleme_gunu'] > 3 ? 'danger' : 'warning' ?>">
                                                <?= $pending['bekleme_gunu'] ?> gün
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="approveRefund(<?= $pending['id'] ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="rejectRefund(<?= $pending['id'] ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" 
                                                    onclick="queryRefundStatus(<?= $pending['id'] ?>)">
                                                <i class="fas fa-sync"></i>
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

    <!-- Son İadeler -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Son İadeler</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>İade ID</th>
                                    <th>Sağlayıcı</th>
                                    <th>Orijinal İşlem</th>
                                    <th>İade Tutarı</th>
                                    <th>Sebep</th>
                                    <th>Durum</th>
                                    <th>Onaylayan</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_refunds as $refund_item): ?>
                                    <tr>
                                        <td><code><?= $refund_item['islem_referans_no'] ?></code></td>
                                        <td><?= htmlspecialchars($refund_item['provider_adi']) ?></td>
                                        <td><code><?= htmlspecialchars($refund_item['original_transaction_id']) ?></code></td>
                                        <td><?= number_format($refund_item['odeme_tutari'], 2) ?>₺</td>
                                        <td><?= htmlspecialchars($refund_item['iade_sebebi']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $refund_item['odeme_durumu'] === 'iade_edildi' ? 'success' : ($refund_item['odeme_durumu'] === 'basarisiz' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $refund_item['odeme_durumu'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($refund_item['onaylayan_kullanici'] ?? 'N/A') ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($refund_item['islem_tarihi'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İade Onay Modal -->
<div class="modal fade" id="approveRefundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">İade Onayla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="approve_refund">
                    <input type="hidden" name="refund_id" id="approve_refund_id">
                    
                    <div class="mb-3">
                        <label for="approval_note" class="form-label">Onay Notu</label>
                        <textarea class="form-control" id="approval_note" name="approval_note" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Onayla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İade Red Modal -->
<div class="modal fade" id="rejectRefundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">İade Reddet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="reject_refund">
                    <input type="hidden" name="refund_id" id="reject_refund_id">
                    
                    <div class="mb-3">
                        <label for="rejection_note" class="form-label">Red Sebebi *</label>
                        <textarea class="form-control" id="rejection_note" name="approval_note" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">Reddet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveRefund(refundId) {
    document.getElementById('approve_refund_id').value = refundId;
    new bootstrap.Modal(document.getElementById('approveRefundModal')).show();
}

function rejectRefund(refundId) {
    document.getElementById('reject_refund_id').value = refundId;
    new bootstrap.Modal(document.getElementById('rejectRefundModal')).show();
}

function queryRefundStatus(refundId) {
    if (confirm('İade durumunu sorgulamak istediğinizden emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="query_refund_status">
            <input type="hidden" name="refund_id" value="${refundId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
