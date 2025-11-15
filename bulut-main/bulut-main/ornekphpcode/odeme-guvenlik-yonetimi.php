<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-guvenlik-yonetimi.php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/payment/PaymentSecurity.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_guvenlik_yonetimi', 'Ödeme güvenlik yönetimi yetkiniz bulunmamaktadır.');

$page_title = 'Ödeme Güvenlik Yönetimi';
$active_menu = 'odeme_yonetimi';

// Güvenlik ayarlarını yükle
$security_settings = fetchAll("SELECT * FROM odeme_guvenlik_ayarlari ORDER BY ayar_adi");

// Güvenlik istatistikleri
$security_stats = fetchOne("
    SELECT 
        COUNT(*) as toplam_kontrol,
        SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as bloklanan_islem,
        SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as kritik_risk,
        AVG(risk_score) as ortalama_risk_puani
    FROM odeme_guvenlik_loglari 
    WHERE islem_tarihi >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");

// Son güvenlik ihlalleri
$recent_violations = fetchAll("
    SELECT * FROM odeme_guvenlik_ihlalleri 
    WHERE cozum_durumu = 'beklemede' 
    ORDER BY ihlal_tarihi DESC 
    LIMIT 10
");

// Fraud kuralları
$fraud_rules = fetchAll("SELECT * FROM odeme_fraud_kurallari ORDER BY kural_tipi, risk_puani DESC");

// Kara liste IP'leri
$blacklisted_ips = fetchAll("SELECT * FROM odeme_blacklist_ip WHERE durum = 'aktif' ORDER BY ekleme_tarihi DESC LIMIT 10");

// Kara liste kartları
$blacklisted_cards = fetchAll("SELECT * FROM odeme_blacklist_card WHERE durum = 'aktif' ORDER BY ekleme_tarihi DESC LIMIT 10");

// Form işlemleri
if ($_POST) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error_message = 'CSRF token hatası!';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_security_setting':
                $setting_name = $_POST['setting_name'] ?? '';
                $setting_value = $_POST['setting_value'] ?? '';
                
                if ($setting_name && $setting_value !== '') {
                    $stmt = $pdo->prepare("
                        INSERT INTO odeme_guvenlik_ayarlari (ayar_adi, ayar_degeri) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE 
                            ayar_degeri = ?, 
                            guncelleme_tarihi = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$setting_name, $setting_value, $setting_value]);
                    $success_message = 'Güvenlik ayarı güncellendi!';
                }
                break;
                
            case 'add_blacklist_ip':
                $ip_address = $_POST['ip_address'] ?? '';
                $sebep = $_POST['sebep'] ?? '';
                
                if ($ip_address && filter_var($ip_address, FILTER_VALIDATE_IP)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO odeme_blacklist_ip (ip_address, sebep, ekleyen_kullanici_id) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$ip_address, $sebep, $_SESSION['user_id']]);
                    $success_message = 'IP adresi kara listeye eklendi!';
                } else {
                    $error_message = 'Geçersiz IP adresi!';
                }
                break;
                
            case 'add_blacklist_card':
                $card_number = $_POST['card_number'] ?? '';
                $sebep = $_POST['sebep'] ?? '';
                
                if ($card_number && strlen($card_number) >= 13) {
                    $card_hash = hash('sha256', $card_number);
                    $stmt = $pdo->prepare("
                        INSERT INTO odeme_blacklist_card (card_number, card_hash, sebep, ekleyen_kullanici_id) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$card_number, $card_hash, $sebep, $_SESSION['user_id']]);
                    $success_message = 'Kart numarası kara listeye eklendi!';
                } else {
                    $error_message = 'Geçersiz kart numarası!';
                }
                break;
                
            case 'toggle_fraud_rule':
                $rule_id = $_POST['rule_id'] ?? '';
                $aktif = $_POST['aktif'] ?? 0;
                
                if ($rule_id) {
                    $stmt = $pdo->prepare("UPDATE odeme_fraud_kurallari SET aktif = ? WHERE id = ?");
                    $stmt->execute([$aktif, $rule_id]);
                    $success_message = 'Fraud kuralı güncellendi!';
                }
                break;
                
            case 'resolve_violation':
                $violation_id = $_POST['violation_id'] ?? '';
                $cozum_notu = $_POST['cozum_notu'] ?? '';
                
                if ($violation_id) {
                    $stmt = $pdo->prepare("
                        UPDATE odeme_guvenlik_ihlalleri 
                        SET cozum_durumu = 'cozuldu', cozum_notu = ?, cozum_tarihi = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$cozum_notu, $violation_id]);
                    $success_message = 'Güvenlik ihlali çözüldü!';
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
                        <li class="breadcrumb-item active">Güvenlik Yönetimi</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="fas fa-shield-alt me-2"></i>Ödeme Güvenlik Yönetimi
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

    <!-- Güvenlik İstatistikleri -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shield-alt text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($security_stats['toplam_kontrol'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Toplam Kontrol</p>
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
                            <i class="fas fa-ban text-danger" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($security_stats['bloklanan_islem'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Bloklanan İşlem</p>
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
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1"><?= number_format($security_stats['kritik_risk'] ?? 0) ?></h5>
                            <p class="text-muted mb-0">Kritik Risk</p>
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
                            <h5 class="mb-1"><?= number_format($security_stats['ortalama_risk_puani'] ?? 0, 1) ?></h5>
                            <p class="text-muted mb-0">Ortalama Risk</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Güvenlik Ayarları -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Güvenlik Ayarları</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Ayar</th>
                                    <th>Değer</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($security_settings as $setting): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($setting['ayar_adi']) ?></strong>
                                            <?php if ($setting['aciklama']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($setting['aciklama']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($setting['ayar_tipi'] === 'boolean'): ?>
                                                <span class="badge bg-<?= $setting['ayar_degeri'] ? 'success' : 'danger' ?>">
                                                    <?= $setting['ayar_degeri'] ? 'Aktif' : 'Pasif' ?>
                                                </span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($setting['ayar_degeri']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editSetting('<?= $setting['ayar_adi'] ?>', '<?= htmlspecialchars($setting['ayar_degeri']) ?>', '<?= $setting['ayar_tipi'] ?>')">
                                                <i class="fas fa-edit"></i>
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

        <!-- Fraud Kuralları -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Fraud Kuralları</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Kural</th>
                                    <th>Risk Puanı</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fraud_rules as $rule): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($rule['kural_adi']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($rule['kural_degeri']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $rule['risk_puani'] >= 50 ? 'danger' : ($rule['risk_puani'] >= 20 ? 'warning' : 'info') ?>">
                                                <?= $rule['risk_puani'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $rule['aktif'] ? 'success' : 'secondary' ?>">
                                                <?= $rule['aktif'] ? 'Aktif' : 'Pasif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="toggle_fraud_rule">
                                                <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                                                <input type="hidden" name="aktif" value="<?= $rule['aktif'] ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= $rule['aktif'] ? 'outline-danger' : 'outline-success' ?>">
                                                    <i class="fas fa-<?= $rule['aktif'] ? 'pause' : 'play' ?>"></i>
                                                </button>
                                            </form>
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

    <div class="row mt-4">
        <!-- Kara Liste IP'leri -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-ban me-2"></i>Kara Liste IP'leri</h5>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#addIPModal">
                        <i class="fas fa-plus"></i> IP Ekle
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>IP Adresi</th>
                                    <th>Sebep</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blacklisted_ips as $ip): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($ip['ip_address']) ?></code></td>
                                        <td><?= htmlspecialchars($ip['sebep']) ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($ip['ekleme_tarihi'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kara Liste Kartları -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Kara Liste Kartları</h5>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#addCardModal">
                        <i class="fas fa-plus"></i> Kart Ekle
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Kart Numarası</th>
                                    <th>Sebep</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blacklisted_cards as $card): ?>
                                    <tr>
                                        <td><code><?= substr($card['card_number'], 0, 4) ?>****<?= substr($card['card_number'], -4) ?></code></td>
                                        <td><?= htmlspecialchars($card['sebep']) ?></td>
                                        <td><?= date('d.m.Y H:i', strtotime($card['ekleme_tarihi'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Son Güvenlik İhlalleri -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Son Güvenlik İhlalleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>İhlal Tipi</th>
                                    <th>IP Adresi</th>
                                    <th>Risk Puanı</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_violations as $violation): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-danger"><?= htmlspecialchars($violation['ihlal_tipi']) ?></span>
                                        </td>
                                        <td><code><?= htmlspecialchars($violation['ip_address']) ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $violation['risk_score'] >= 80 ? 'danger' : 'warning' ?>">
                                                <?= $violation['risk_score'] ?>
                                            </span>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($violation['ihlal_tarihi'])) ?></td>
                                        <td>
                                            <span class="badge bg-warning"><?= ucfirst($violation['cozum_durumu']) ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="resolveViolation(<?= $violation['id'] ?>)">
                                                <i class="fas fa-check"></i> Çöz
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
</div>

<!-- IP Ekleme Modal -->
<div class="modal fade" id="addIPModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">IP Adresi Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="add_blacklist_ip">
                    
                    <div class="mb-3">
                        <label for="ip_address" class="form-label">IP Adresi *</label>
                        <input type="text" class="form-control" id="ip_address" name="ip_address" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sebep" class="form-label">Sebep</label>
                        <textarea class="form-control" id="sebep" name="sebep" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kart Ekleme Modal -->
<div class="modal fade" id="addCardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Kart Numarası Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="add_blacklist_card">
                    
                    <div class="mb-3">
                        <label for="card_number" class="form-label">Kart Numarası *</label>
                        <input type="text" class="form-control" id="card_number" name="card_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sebep" class="form-label">Sebep</label>
                        <textarea class="form-control" id="sebep" name="sebep" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ayar Düzenleme Modal -->
<div class="modal fade" id="editSettingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Ayar Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="update_security_setting">
                    <input type="hidden" name="setting_name" id="edit_setting_name">
                    
                    <div class="mb-3">
                        <label for="edit_setting_value" class="form-label">Değer</label>
                        <input type="text" class="form-control" id="edit_setting_value" name="setting_value" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İhlal Çözme Modal -->
<div class="modal fade" id="resolveViolationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Güvenlik İhlalini Çöz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="resolve_violation">
                    <input type="hidden" name="violation_id" id="resolve_violation_id">
                    
                    <div class="mb-3">
                        <label for="cozum_notu" class="form-label">Çözüm Notu</label>
                        <textarea class="form-control" id="cozum_notu" name="cozum_notu" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Çöz</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSetting(name, value, type) {
    document.getElementById('edit_setting_name').value = name;
    document.getElementById('edit_setting_value').value = value;
    
    if (type === 'boolean') {
        document.getElementById('edit_setting_value').type = 'checkbox';
        document.getElementById('edit_setting_value').checked = value == '1';
    } else {
        document.getElementById('edit_setting_value').type = 'text';
    }
    
    new bootstrap.Modal(document.getElementById('editSettingModal')).show();
}

function resolveViolation(violationId) {
    document.getElementById('resolve_violation_id').value = violationId;
    new bootstrap.Modal(document.getElementById('resolveViolationModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
