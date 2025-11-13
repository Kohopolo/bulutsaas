<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-komisyon-yonetimi.php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/payment/PaymentCommission.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_komisyon_yonetimi', 'Ödeme komisyon yönetimi yetkiniz bulunmamaktadır.');

$page_title = 'Komisyon Yönetimi';
$active_menu = 'odeme_yonetimi';

// Payment commission'ı başlat
$commission = new PaymentCommission($pdo);

// Komisyon kuralları
$commission_rules = fetchAll("
    SELECT kk.*, p.provider_adi 
    FROM odeme_komisyon_kurallari kk 
    LEFT JOIN odeme_providerlari p ON kk.provider_id = p.id 
    ORDER BY kk.sira, kk.provider_id, kk.kart_tipi, kk.taksit_sayisi
");

// Sağlayıcılar
$providers = fetchAll("SELECT * FROM odeme_providerlari WHERE durum = 'aktif' ORDER BY provider_adi");

// Komisyon istatistikleri
$commission_stats = fetchOne("
    SELECT 
        COUNT(*) as toplam_kural,
        SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) as aktif_kural,
        COUNT(DISTINCT provider_id) as provider_sayisi,
        AVG(komisyon_orani) as ortalama_komisyon_orani
    FROM odeme_komisyon_kurallari
");

// Son komisyon hesaplamaları
$recent_calculations = fetchAll("
    SELECT kl.*, p.provider_adi, oi.islem_referans_no
    FROM odeme_komisyon_loglari kl
    LEFT JOIN odeme_providerlari p ON kl.provider_id = p.id
    LEFT JOIN odeme_islemleri oi ON kl.islem_id = oi.id
    ORDER BY kl.hesaplama_tarihi DESC
    LIMIT 20
");

// Form işlemleri
if ($_POST) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error_message = 'CSRF token hatası!';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_commission_rule':
                $rule_data = [
                    'provider_id' => $_POST['provider_id'] ?: null,
                    'kural_adi' => $_POST['kural_adi'],
                    'kart_tipi' => $_POST['kart_tipi'] ?: null,
                    'taksit_sayisi' => $_POST['taksit_sayisi'] ?: null,
                    'islem_tipi' => $_POST['islem_tipi'],
                    'min_tutar' => $_POST['min_tutar'] ?: 0,
                    'max_tutar' => $_POST['max_tutar'] ?: null,
                    'komisyon_orani' => $_POST['komisyon_orani'],
                    'sabit_komisyon' => $_POST['sabit_komisyon'] ?: 0,
                    'ek_uyeler' => $_POST['ek_uyeler'] ?: null,
                    'aktif' => isset($_POST['aktif']) ? 1 : 0,
                    'sira' => $_POST['sira'] ?: 0
                ];
                
                if ($commission->addCommissionRule($rule_data)) {
                    $success_message = 'Komisyon kuralı eklendi!';
                } else {
                    $error_message = 'Komisyon kuralı eklenirken hata oluştu!';
                }
                break;
                
            case 'update_commission_rule':
                $rule_id = $_POST['rule_id'];
                $rule_data = [
                    'provider_id' => $_POST['provider_id'] ?: null,
                    'kural_adi' => $_POST['kural_adi'],
                    'kart_tipi' => $_POST['kart_tipi'] ?: null,
                    'taksit_sayisi' => $_POST['taksit_sayisi'] ?: null,
                    'islem_tipi' => $_POST['islem_tipi'],
                    'min_tutar' => $_POST['min_tutar'] ?: 0,
                    'max_tutar' => $_POST['max_tutar'] ?: null,
                    'komisyon_orani' => $_POST['komisyon_orani'],
                    'sabit_komisyon' => $_POST['sabit_komisyon'] ?: 0,
                    'ek_uyeler' => $_POST['ek_uyeler'] ?: null,
                    'aktif' => isset($_POST['aktif']) ? 1 : 0,
                    'sira' => $_POST['sira'] ?: 0
                ];
                
                if ($commission->updateCommissionRule($rule_id, $rule_data)) {
                    $success_message = 'Komisyon kuralı güncellendi!';
                } else {
                    $error_message = 'Komisyon kuralı güncellenirken hata oluştu!';
                }
                break;
                
            case 'delete_commission_rule':
                $rule_id = $_POST['rule_id'];
                if ($commission->deleteCommissionRule($rule_id)) {
                    $success_message = 'Komisyon kuralı silindi!';
                } else {
                    $error_message = 'Komisyon kuralı silinirken hata oluştu!';
                }
                break;
                
            case 'calculate_commission':
                $provider_id = $_POST['provider_id'];
                $amount = $_POST['amount'];
                $card_type = $_POST['card_type'];
                $installment = $_POST['installment'];
                
                $result = $commission->calculateCommission($provider_id, $amount, $card_type, $installment);
                if ($result['success']) {
                    $calculation_result = $result;
                } else {
                    $error_message = 'Komisyon hesaplama hatası: ' . $result['error'];
                }
                break;
                
            case 'compare_commissions':
                $amount = $_POST['amount'];
                $card_type = $_POST['card_type'];
                $installment = $_POST['installment'];
                
                $result = $commission->compareCommissions($amount, $card_type, $installment);
                if ($result['success']) {
                    $comparison_result = $result;
                } else {
                    $error_message = 'Komisyon karşılaştırma hatası: ' . $result['error'];
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
                        <li class="breadcrumb-item active">Komisyon Yönetimi</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="fas fa-percentage me-2"></i>Komisyon Yönetimi
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

    <!-- Komisyon İstatistikleri -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-list text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($commission_stats['toplam_kural'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Toplam Kural</p>
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
                            <h5 class="mb-1"><?= number_format($commission_stats['aktif_kural'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Aktif Kural</p>
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
                            <i class="fas fa-building text-info" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($commission_stats['provider_sayisi'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Sağlayıcı</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-percentage text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($commission_stats['ortalama_komisyon_orani'] ?? 0, 2) ?>%</h5>
                            <p class="text-muted mb-0">Ort. Komisyon</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Komisyon Hesaplama -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Komisyon Hesaplama</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="calculate_commission">
                        
                        <div class="mb-3">
                            <label for="provider_id" class="form-label">Sağlayıcı *</label>
                            <select class="form-select" id="provider_id" name="provider_id" required>
                                <option value="">Sağlayıcı seçiniz...</option>
                                <?php foreach ($providers as $provider): ?>
                                    <option value="<?= $provider['id'] ?>"><?= htmlspecialchars($provider['provider_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Tutar (₺) *</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="card_type" class="form-label">Kart Tipi</label>
                            <select class="form-select" id="card_type" name="card_type">
                                <option value="">Tüm Kartlar</option>
                                <option value="Visa">Visa</option>
                                <option value="MasterCard">MasterCard</option>
                                <option value="American Express">American Express</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="installment" class="form-label">Taksit Sayısı</label>
                            <select class="form-select" id="installment" name="installment">
                                <option value="1">Peşin</option>
                                <option value="2">2 Taksit</option>
                                <option value="3">3 Taksit</option>
                                <option value="6">6 Taksit</option>
                                <option value="9">9 Taksit</option>
                                <option value="12">12 Taksit</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-calculator me-2"></i>Komisyon Hesapla
                        </button>
                    </form>
                </div>
            </div>

            <!-- Komisyon Karşılaştırma -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Komisyon Karşılaştırma</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="compare_commissions">
                        
                        <div class="mb-3">
                            <label for="compare_amount" class="form-label">Tutar (₺) *</label>
                            <input type="number" class="form-control" id="compare_amount" name="amount" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="compare_card_type" class="form-label">Kart Tipi</label>
                            <select class="form-select" id="compare_card_type" name="card_type">
                                <option value="">Tüm Kartlar</option>
                                <option value="Visa">Visa</option>
                                <option value="MasterCard">MasterCard</option>
                                <option value="American Express">American Express</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="compare_installment" class="form-label">Taksit Sayısı</label>
                            <select class="form-select" id="compare_installment" name="installment">
                                <option value="1">Peşin</option>
                                <option value="2">2 Taksit</option>
                                <option value="3">3 Taksit</option>
                                <option value="6">6 Taksit</option>
                                <option value="9">9 Taksit</option>
                                <option value="12">12 Taksit</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-info w-100">
                            <i class="fas fa-balance-scale me-2"></i>Karşılaştır
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Komisyon Kuralları -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Komisyon Kuralları</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                        <i class="fas fa-plus"></i> Kural Ekle
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Sağlayıcı</th>
                                    <th>Kural Adı</th>
                                    <th>Kart Tipi</th>
                                    <th>Taksit</th>
                                    <th>Komisyon</th>
                                    <th>Sabit</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commission_rules as $rule): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($rule['provider_adi'] ?? 'Tümü') ?></td>
                                        <td><?= htmlspecialchars($rule['kural_adi']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($rule['kart_tipi'] ?? 'Tümü') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $rule['taksit_sayisi'] ? $rule['taksit_sayisi'] . ' Taksit' : 'Tümü' ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($rule['komisyon_orani'], 2) ?>%</td>
                                        <td><?= number_format($rule['sabit_komisyon'], 2) ?>₺</td>
                                        <td>
                                            <span class="badge bg-<?= $rule['aktif'] ? 'success' : 'secondary' ?>">
                                                <?= $rule['aktif'] ? 'Aktif' : 'Pasif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editRule(<?= htmlspecialchars(json_encode($rule)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteRule(<?= $rule['id'] ?>)">
                                                <i class="fas fa-trash"></i>
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

    <!-- Son Komisyon Hesaplamaları -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Son Komisyon Hesaplamaları</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Sağlayıcı</th>
                                    <th>İşlem No</th>
                                    <th>Tutar</th>
                                    <th>Kart Tipi</th>
                                    <th>Taksit</th>
                                    <th>Komisyon Oranı</th>
                                    <th>Toplam Komisyon</th>
                                    <th>Net Tutar</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_calculations as $calc): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($calc['provider_adi']) ?></td>
                                        <td><code><?= htmlspecialchars($calc['islem_referans_no'] ?? 'N/A') ?></code></td>
                                        <td><?= number_format($calc['tutar'], 2) ?>₺</td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($calc['kart_tipi'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $calc['taksit_sayisi'] . ' Taksit' ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($calc['komisyon_orani'], 2) ?>%</td>
                                        <td><?= number_format($calc['toplam_komisyon'], 2) ?>₺</td>
                                        <td><?= number_format($calc['net_tutar'], 2) ?>₺</td>
                                        <td><?= date('d.m.Y H:i', strtotime($calc['hesaplama_tarihi'])) ?></td>
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

<!-- Kural Ekleme Modal -->
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Komisyon Kuralı Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="add_commission_rule">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="provider_id" class="form-label">Sağlayıcı</label>
                                <select class="form-select" id="provider_id" name="provider_id">
                                    <option value="">Tüm Sağlayıcılar</option>
                                    <?php foreach ($providers as $provider): ?>
                                        <option value="<?= $provider['id'] ?>"><?= htmlspecialchars($provider['provider_adi']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="kural_adi" class="form-label">Kural Adı *</label>
                                <input type="text" class="form-control" id="kural_adi" name="kural_adi" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="kart_tipi" class="form-label">Kart Tipi</label>
                                <select class="form-select" id="kart_tipi" name="kart_tipi">
                                    <option value="">Tüm Kartlar</option>
                                    <option value="Visa">Visa</option>
                                    <option value="MasterCard">MasterCard</option>
                                    <option value="American Express">American Express</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="taksit_sayisi" class="form-label">Taksit Sayısı</label>
                                <select class="form-select" id="taksit_sayisi" name="taksit_sayisi">
                                    <option value="">Tüm Taksitler</option>
                                    <option value="1">Peşin</option>
                                    <option value="2">2 Taksit</option>
                                    <option value="3">3 Taksit</option>
                                    <option value="6">6 Taksit</option>
                                    <option value="9">9 Taksit</option>
                                    <option value="12">12 Taksit</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="islem_tipi" class="form-label">İşlem Tipi</label>
                                <select class="form-select" id="islem_tipi" name="islem_tipi">
                                    <option value="sale">Satış</option>
                                    <option value="refund">İade</option>
                                    <option value="void">İptal</option>
                                    <option value="capture">Tahsilat</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="min_tutar" class="form-label">Min. Tutar (₺)</label>
                                <input type="number" class="form-control" id="min_tutar" name="min_tutar" 
                                       step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_tutar" class="form-label">Max. Tutar (₺)</label>
                                <input type="number" class="form-control" id="max_tutar" name="max_tutar" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="sira" class="form-label">Sıra</label>
                                <input type="number" class="form-control" id="sira" name="sira" 
                                       min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="komisyon_orani" class="form-label">Komisyon Oranı (%) *</label>
                                <input type="number" class="form-control" id="komisyon_orani" name="komisyon_orani" 
                                       step="0.01" min="0" max="100" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sabit_komisyon" class="form-label">Sabit Komisyon (₺)</label>
                                <input type="number" class="form-control" id="sabit_komisyon" name="sabit_komisyon" 
                                       step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ek_uyeler" class="form-label">Ek Ücretler (JSON)</label>
                        <textarea class="form-control" id="ek_uyeler" name="ek_uyeler" rows="2" 
                                  placeholder='{"fixed_fee": 0.25, "monthly_fee": 0.10}'></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="aktif" name="aktif" checked>
                        <label class="form-check-label" for="aktif">
                            Aktif
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRule(rule) {
    // Modal'ı doldur ve göster
    document.getElementById('provider_id').value = rule.provider_id || '';
    document.getElementById('kural_adi').value = rule.kural_adi;
    document.getElementById('kart_tipi').value = rule.kart_tipi || '';
    document.getElementById('taksit_sayisi').value = rule.taksit_sayisi || '';
    document.getElementById('islem_tipi').value = rule.islem_tipi;
    document.getElementById('min_tutar').value = rule.min_tutar;
    document.getElementById('max_tutar').value = rule.max_tutar || '';
    document.getElementById('komisyon_orani').value = rule.komisyon_orani;
    document.getElementById('sabit_komisyon').value = rule.sabit_komisyon;
    document.getElementById('ek_uyeler').value = rule.ek_uyeler || '';
    document.getElementById('aktif').checked = rule.aktif == 1;
    document.getElementById('sira').value = rule.sira;
    
    // Form action'ını güncelle
    const form = document.querySelector('#addRuleModal form');
    form.querySelector('input[name="action"]').value = 'update_commission_rule';
    form.innerHTML += '<input type="hidden" name="rule_id" value="' + rule.id + '">';
    
    new bootstrap.Modal(document.getElementById('addRuleModal')).show();
}

function deleteRule(ruleId) {
    if (confirm('Bu komisyon kuralını silmek istediğinizden emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="delete_commission_rule">
            <input type="hidden" name="rule_id" value="${ruleId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
