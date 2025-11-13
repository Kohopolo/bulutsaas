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
requireDetailedPermission('odeme_webhook', 'Webhook yönetimi yetkiniz bulunmamaktadır.');

$page_title = "Webhook Yönetimi";
$current_page = "odeme-webhook-yonetimi";

$success_message = '';
$error_message = '';

// Filtreleme parametreleri
$provider_id = $_GET['provider_id'] ?? '';
$durum = $_GET['durum'] ?? '';
$tarih_baslangic = $_GET['tarih_baslangic'] ?? '';
$tarih_bitis = $_GET['tarih_bitis'] ?? '';
$sayfa = intval($_GET['sayfa'] ?? 1);
$limit = 50;
$offset = ($sayfa - 1) * $limit;

// Ödeme sağlayıcıları listesi
$providerlar = fetchAll("SELECT id, provider_adi FROM odeme_providerlari WHERE aktif = 1 ORDER BY provider_adi");

// Filtreleme koşulları
$where_conditions = [];
$params = [];

if (!empty($provider_id)) {
    $where_conditions[] = "owl.provider_id = ?";
    $params[] = $provider_id;
}

if (!empty($durum)) {
    $where_conditions[] = "owl.durum = ?";
    $params[] = $durum;
}

if (!empty($tarih_baslangic)) {
    $where_conditions[] = "DATE(owl.islem_tarihi) >= ?";
    $params[] = $tarih_baslangic;
}

if (!empty($tarih_bitis)) {
    $where_conditions[] = "DATE(owl.islem_tarihi) <= ?";
    $params[] = $tarih_bitis;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Toplam kayıt sayısı
$toplam_kayit = fetchOne("
    SELECT COUNT(*) as toplam
    FROM odeme_webhook_loglari owl
    LEFT JOIN odeme_providerlari p ON owl.provider_id = p.id
    $where_clause
", $params)['toplam'];

$toplam_sayfa = ceil($toplam_kayit / $limit);

// Webhook loglarını getir
$webhook_loglari = fetchAll("
    SELECT 
        owl.*,
        p.provider_adi,
        oi.islem_no,
        oi.tutar
    FROM odeme_webhook_loglari owl
    LEFT JOIN odeme_providerlari p ON owl.provider_id = p.id
    LEFT JOIN odeme_islemleri oi ON owl.islem_id = oi.id
    $where_clause
    ORDER BY owl.islem_tarihi DESC
    LIMIT $limit OFFSET $offset
", $params);

// İstatistikler
$istatistikler = fetchOne("
    SELECT 
        COUNT(*) as toplam_webhook,
        COUNT(CASE WHEN durum = 'basarili' THEN 1 END) as basarili_webhook,
        COUNT(CASE WHEN durum = 'basarisiz' THEN 1 END) as basarisiz_webhook,
        COUNT(CASE WHEN durum = 'beklemede' THEN 1 END) as beklemede_webhook,
        AVG(deneme_sayisi) as ortalama_deneme,
        MAX(deneme_sayisi) as maksimum_deneme
    FROM odeme_webhook_loglari owl
    LEFT JOIN odeme_providerlari p ON owl.provider_id = p.id
    $where_clause
", $params);

// Başarı oranı hesapla
$basarili_oran = $istatistikler['toplam_webhook'] > 0 ? 
    ($istatistikler['basarili_webhook'] / $istatistikler['toplam_webhook']) * 100 : 0;

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF kontrolü
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Güvenlik hatası. Lütfen sayfayı yenileyin.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'test_webhook') {
            // Webhook test et
            $webhook_id = intval($_POST['webhook_id'] ?? 0);
            $webhook = fetchOne("SELECT * FROM odeme_webhook_loglari WHERE id = ?", [$webhook_id]);
            
            if (!$webhook) {
                throw new Exception('Webhook bulunamadı.');
            }

            // Test verisi hazırla
            $test_data = [
                'test' => true,
                'webhook_id' => $webhook_id,
                'timestamp' => time(),
                'data' => json_decode($webhook['gelen_veri'], true)
            ];

            // Webhook URL'ine test isteği gönder
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webhook['webhook_url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: PaymentSystem-Webhook/1.0'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Test sonucunu kaydet
            $test_durum = ($http_code >= 200 && $http_code < 300) ? 'basarili' : 'basarisiz';
            $test_hata = $error ?: ($http_code >= 400 ? "HTTP $http_code" : '');

            executeQuery("
                INSERT INTO odeme_webhook_loglari (
                    provider_id, islem_id, webhook_url, gelen_veri, yanit_kodu, 
                    yanit_verisi, durum, deneme_sayisi, hata_mesaji
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
            ", [
                $webhook['provider_id'], $webhook['islem_id'], $webhook['webhook_url'],
                json_encode($test_data), $http_code, $response, $test_durum, $test_hata
            ]);

            $success_message = 'Webhook test edildi. Sonuç: ' . ($test_durum === 'basarili' ? 'Başarılı' : 'Başarısız');

        } elseif ($action === 'retry_webhook') {
            // Webhook'u yeniden dene
            $webhook_id = intval($_POST['webhook_id'] ?? 0);
            $webhook = fetchOne("SELECT * FROM odeme_webhook_loglari WHERE id = ?", [$webhook_id]);
            
            if (!$webhook) {
                throw new Exception('Webhook bulunamadı.');
            }

            // Orijinal veriyi yeniden gönder
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webhook['webhook_url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $webhook['gelen_veri']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: PaymentSystem-Webhook/1.0'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Sonucu kaydet
            $retry_durum = ($http_code >= 200 && $http_code < 300) ? 'basarili' : 'basarisiz';
            $retry_hata = $error ?: ($http_code >= 400 ? "HTTP $http_code" : '');

            executeQuery("
                INSERT INTO odeme_webhook_loglari (
                    provider_id, islem_id, webhook_url, gelen_veri, yanit_kodu, 
                    yanit_verisi, durum, deneme_sayisi, hata_mesaji
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $webhook['provider_id'], $webhook['islem_id'], $webhook['webhook_url'],
                $webhook['gelen_veri'], $http_code, $response, $retry_durum, 
                $webhook['deneme_sayisi'] + 1, $retry_hata
            ]);

            $success_message = 'Webhook yeniden denendi. Sonuç: ' . ($retry_durum === 'basarili' ? 'Başarılı' : 'Başarısız');

        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

include 'header.php';
?>

<div class="desktop-container">
    <div class="desktop-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="page-title">
                    <i class="fas fa-webhook me-2"></i>
                    Webhook Yönetimi
                </h1>
                <p class="page-subtitle mb-0">Webhook URL'leri ve log takibi</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="header-actions">
                    <button class="btn btn-outline-info" onclick="refreshWebhooks()">
                        <i class="fas fa-sync"></i> Yenile
                    </button>
                    <a href="odeme-islemleri.php" class="btn btn-outline-primary">
                        <i class="fas fa-list"></i> İşlemler
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="form-container">
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-webhook"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($istatistikler['toplam_webhook'] ?? 0) ?></div>
                        <div class="stats-label">Toplam Webhook</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($istatistikler['basarili_webhook'] ?? 0) ?></div>
                        <div class="stats-label">Başarılı</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($istatistikler['basarisiz_webhook'] ?? 0) ?></div>
                        <div class="stats-label">Başarısız</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-number"><?= number_format($basarili_oran, 1) ?>%</div>
                        <div class="stats-label">Başarı Oranı</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtreleme -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Filtreleme
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Sağlayıcı</label>
                        <select name="provider_id" class="form-select">
                            <option value="">Tümü</option>
                            <?php foreach ($providerlar as $provider): ?>
                            <option value="<?= $provider['id'] ?>" <?= $provider_id == $provider['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($provider['provider_adi']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Durum</label>
                        <select name="durum" class="form-select">
                            <option value="">Tümü</option>
                            <option value="basarili" <?= $durum === 'basarili' ? 'selected' : '' ?>>Başarılı</option>
                            <option value="basarisiz" <?= $durum === 'basarisiz' ? 'selected' : '' ?>>Başarısız</option>
                            <option value="beklemede" <?= $durum === 'beklemede' ? 'selected' : '' ?>>Beklemede</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" name="tarih_baslangic" class="form-control" value="<?= htmlspecialchars($tarih_baslangic) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" name="tarih_bitis" class="form-control" value="<?= htmlspecialchars($tarih_bitis) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Webhook Logları -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Webhook Logları
                    <span class="badge bg-secondary ms-2"><?= number_format($toplam_kayit) ?> kayıt</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($webhook_loglari)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Webhook logu bulunamadı</h5>
                    <p class="text-muted">Belirtilen kriterlere uygun webhook logu bulunmamaktadır.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sağlayıcı</th>
                                <th>İşlem No</th>
                                <th>Webhook URL</th>
                                <th>Durum</th>
                                <th>Yanıt Kodu</th>
                                <th>Deneme</th>
                                <th>Tarih</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webhook_loglari as $log): ?>
                            <tr>
                                <td>
                                    <code>#<?= $log['id'] ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($log['provider_adi']) ?></span>
                                </td>
                                <td>
                                    <?php if ($log['islem_no']): ?>
                                        <code><?= htmlspecialchars($log['islem_no']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="webhook-url">
                                        <small><?= htmlspecialchars(substr($log['webhook_url'], 0, 50)) ?>...</small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $durum_class = [
                                        'basarili' => 'bg-success',
                                        'basarisiz' => 'bg-danger',
                                        'beklemede' => 'bg-warning'
                                    ];
                                    $class = $durum_class[$log['durum']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $class ?>"><?= ucfirst($log['durum']) ?></span>
                                    <?php if ($log['hata_mesaji']): ?>
                                        <br><small class="text-danger" title="<?= htmlspecialchars($log['hata_mesaji']) ?>">
                                            <i class="fas fa-exclamation-triangle"></i> Hata
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['yanit_kodu']): ?>
                                        <span class="badge <?= $log['yanit_kodu'] >= 200 && $log['yanit_kodu'] < 300 ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $log['yanit_kodu'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $log['deneme_sayisi'] ?></span>
                                </td>
                                <td>
                                    <div>
                                        <small><?= date('d.m.Y', strtotime($log['islem_tarihi'])) ?></small>
                                        <br><small class="text-muted"><?= date('H:i:s', strtotime($log['islem_tarihi'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-info" onclick="showWebhookDetails(<?= $log['id'] ?>)" title="Detaylar">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="testWebhook(<?= $log['id'] ?>)" title="Test Et">
                                            <i class="fas fa-flask"></i>
                                        </button>
                                        <?php if ($log['durum'] === 'basarisiz'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="retryWebhook(<?= $log['id'] ?>)" title="Yeniden Dene">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sayfalama -->
                <?php if ($toplam_sayfa > 1): ?>
                <nav aria-label="Sayfa navigasyonu" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($sayfa > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['sayfa' => $sayfa - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php
                        $baslangic = max(1, $sayfa - 2);
                        $bitis = min($toplam_sayfa, $sayfa + 2);
                        
                        for ($i = $baslangic; $i <= $bitis; $i++):
                        ?>
                        <li class="page-item <?= $i == $sayfa ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['sayfa' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($sayfa < $toplam_sayfa): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['sayfa' => $sayfa + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Webhook Detay Modal -->
<div class="modal fade" id="webhookDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Webhook Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="webhookDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showWebhookDetails(webhookId) {
    const modal = new bootstrap.Modal(document.getElementById('webhookDetailsModal'));
    const content = document.getElementById('webhookDetailsContent');
    
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Yükleniyor...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // AJAX ile detayları getir
    fetch(`ajax/get-webhook-details.php?id=${webhookId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Detaylar yüklenirken hata oluştu.
                </div>
            `;
        });
}

function testWebhook(webhookId) {
    if (confirm('Bu webhook\'u test etmek istediğinizden emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = 'csrf_token';
        csrfToken.value = '<?= generateCSRFToken() ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'test_webhook';
        
        const webhookIdInput = document.createElement('input');
        webhookIdInput.type = 'hidden';
        webhookIdInput.name = 'webhook_id';
        webhookIdInput.value = webhookId;
        
        form.appendChild(csrfToken);
        form.appendChild(actionInput);
        form.appendChild(webhookIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function retryWebhook(webhookId) {
    if (confirm('Bu webhook\'u yeniden denemek istediğinizden emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = 'csrf_token';
        csrfToken.value = '<?= generateCSRFToken() ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'retry_webhook';
        
        const webhookIdInput = document.createElement('input');
        webhookIdInput.type = 'hidden';
        webhookIdInput.name = 'webhook_id';
        webhookIdInput.value = webhookId;
        
        form.appendChild(csrfToken);
        form.appendChild(actionInput);
        form.appendChild(webhookIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function refreshWebhooks() {
    window.location.reload();
}
</script>

<style>
.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 20px;
    color: white;
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stats-icon {
    font-size: 2.5rem;
    margin-right: 15px;
    opacity: 0.8;
}

.stats-number {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.stats-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 5px;
}

.webhook-url {
    max-width: 200px;
    word-break: break-all;
}

.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 10px;
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    border-radius: 10px 10px 0 0 !important;
}

.table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

.pagination .page-link {
    color: #667eea;
    border-color: #dee2e6;
}

.pagination .page-item.active .page-link {
    background-color: #667eea;
    border-color: #667eea;
}

.pagination .page-link:hover {
    color: #5a6fd8;
    background-color: #e9ecef;
    border-color: #dee2e6;
}
</style>

<?php include 'footer.php'; ?>
