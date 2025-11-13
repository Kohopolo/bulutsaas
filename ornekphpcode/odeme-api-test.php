<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/detailed_permission_functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
requireDetailedPermission('odeme_api_test', 'Ödeme API test yetkiniz bulunmamaktadır.');

// Header include
include 'includes/header.php';

// Payment provider sınıflarını dahil et
require_once '../includes/payment/IyzicoPayment.php';
require_once '../includes/payment/PayTRPayment.php';
require_once '../includes/payment/AkbankPayment.php';
require_once '../includes/payment/YapiKrediPayment.php';
require_once '../includes/payment/QNBFinansbankPayment.php';
require_once '../includes/payment/GarantiBBVAPayment.php';
require_once '../includes/payment/IsBankasiPayment.php';
require_once '../includes/payment/ZiraatBankasiPayment.php';
require_once '../includes/payment/VakifBankPayment.php';
require_once '../includes/payment/HalkbankPayment.php';

$test_results = [];
$selected_provider = $_GET['provider'] ?? '';

// Test işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_connection') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $test_results[] = [
            'provider' => 'CSRF',
            'success' => false,
            'message' => 'CSRF hatası. Lütfen sayfayı yenileyip tekrar deneyin.'
        ];
    } else {
        $provider_id = $_POST['provider_id'] ?? null;
        if ($provider_id) {
            $provider = fetchOne("SELECT * FROM odeme_providerlari WHERE id = ?", [$provider_id]);
            if ($provider) {
                $test_result = testPaymentProvider($provider);
                $test_results[] = $test_result;
            }
        }
    }
}

// Tüm sağlayıcıları test et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_all') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $test_results[] = [
            'provider' => 'CSRF',
            'success' => false,
            'message' => 'CSRF hatası. Lütfen sayfayı yenileyip tekrar deneyin.'
        ];
    } else {
        $providers = fetchAll("SELECT * FROM odeme_providerlari WHERE durum = 'aktif'");
        foreach ($providers as $provider) {
            $test_result = testPaymentProvider($provider);
            $test_results[] = $test_result;
        }
    }
}

// Payment provider test fonksiyonu
function testPaymentProvider($provider) {
    try {
        $provider_class = getProviderClassName($provider['slug']);
        if (!$provider_class) {
            return [
                'provider' => $provider['provider_adi'],
                'success' => false,
                'message' => 'Provider sınıfı bulunamadı: ' . $provider['slug']
            ];
        }

        // Provider sınıfını başlat
        $payment_provider = new $provider_class(
            $provider['id'],
            $provider['api_key'],
            $provider['secret_key'],
            $provider['base_url'],
            $provider['test_mode']
        );

        // Bağlantı testi yap
        $result = $payment_provider->testConnection();
        
        return [
            'provider' => $provider['provider_adi'],
            'success' => $result['success'],
            'message' => $result['message'] ?? $result['error_message'] ?? 'Bilinmeyen hata',
            'response_data' => $result['response_data'] ?? null,
            'error_code' => $result['error_code'] ?? null
        ];

    } catch (Exception $e) {
        return [
            'provider' => $provider['provider_adi'],
            'success' => false,
            'message' => 'Test hatası: ' . $e->getMessage()
        ];
    }
}

// Provider slug'ından sınıf adını al
function getProviderClassName($slug) {
    $class_map = [
        'iyzico' => 'IyzicoPayment',
        'paytr' => 'PayTRPayment',
        'akbank' => 'AkbankPayment',
        'yapikredi' => 'YapiKrediPayment',
        'qnb-finansbank' => 'QNBFinansbankPayment',
        'garanti-bbva' => 'GarantiBBVAPayment',
        'isbankasi' => 'IsBankasiPayment',
        'ziraatbankasi' => 'ZiraatBankasiPayment',
        'vakifbank' => 'VakifBankPayment',
        'halkbank' => 'HalkbankPayment'
    ];
    
    return $class_map[$slug] ?? null;
}

$providers = fetchAll("SELECT * FROM odeme_providerlari ORDER BY provider_adi");
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Ödeme API Test</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Ana Sayfa</a></li>
                        <li class="breadcrumb-item"><a href="odeme-yonetimi.php">Ödeme Yönetimi</a></li>
                        <li class="breadcrumb-item active">API Test</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ödeme Sağlayıcı API Testleri</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-success btn-sm" onclick="testAllProviders()">
                        <i class="fas fa-play"></i> Tümünü Test Et
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form action="" method="POST" id="testForm">
                    <?= generateCSRFToken() ?>
                    <input type="hidden" name="action" value="test_connection">
                    <div class="form-group">
                        <label for="provider_id">Test Edilecek Sağlayıcı</label>
                        <select class="form-control" id="provider_id" name="provider_id" required>
                            <option value="">-- Sağlayıcı Seçin --</option>
                            <?php foreach ($providers as $provider): ?>
                                <option value="<?= $provider['id'] ?>" <?= ($selected_provider == $provider['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($provider['provider_adi']) ?> 
                                    (<?= $provider['test_mode'] ? 'Test' : 'Canlı' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play"></i> Bağlantı Testi Yap
                    </button>
                </form>

                <hr>

                <!-- Test Sonuçları -->
                <?php if (!empty($test_results)): ?>
                    <h4>Test Sonuçları</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Sağlayıcı</th>
                                    <th>Durum</th>
                                    <th>Mesaj</th>
                                    <th>Hata Kodu</th>
                                    <th>Detaylar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($test_results as $result): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($result['provider']) ?></td>
                                        <td>
                                            <?php if ($result['success']): ?>
                                                <span class="badge badge-success">Başarılı</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Başarısız</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($result['message']) ?></td>
                                        <td><?= htmlspecialchars($result['error_code'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php if (isset($result['response_data']) && $result['response_data']): ?>
                                                <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#responseModal" data-response='<?= json_encode($result['response_data']) ?>'>
                                                    <i class="fas fa-eye"></i> Görüntüle
                                                </button>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Sağlayıcı Detayları -->
                <h4>Sağlayıcı Detayları</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Sağlayıcı Adı</th>
                                <th>Slug</th>
                                <th>Durum</th>
                                <th>Test Modu</th>
                                <th>Base URL</th>
                                <th>API Key</th>
                                <th>Secret Key</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providers as $provider): ?>
                                <tr>
                                    <td><?= htmlspecialchars($provider['provider_adi']) ?></td>
                                    <td><?= htmlspecialchars($provider['slug']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $provider['durum'] == 'aktif' ? 'success' : 'danger' ?>">
                                            <?= htmlspecialchars($provider['durum']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $provider['test_mode'] ? 'warning' : 'info' ?>">
                                            <?= $provider['test_mode'] ? 'Test' : 'Canlı' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($provider['base_url']) ?></td>
                                    <td>
                                        <?php if ($provider['api_key']): ?>
                                            <?= htmlspecialchars(substr($provider['api_key'], 0, 8)) ?>...
                                        <?php else: ?>
                                            <span class="text-muted">Ayarlanmamış</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($provider['secret_key']): ?>
                                            <?= htmlspecialchars(substr($provider['secret_key'], 0, 8)) ?>...
                                        <?php else: ?>
                                            <span class="text-muted">Ayarlanmamış</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="testProvider(<?= $provider['id'] ?>)">
                                            <i class="fas fa-play"></i> Test Et
                                        </button>
                                        <a href="odeme-provider-duzenle.php?id=<?= $provider['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i> Düzenle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- API Dokümantasyonu -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">API Test Dokümantasyonu</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Test Senaryoları</h5>
                        <ul>
                            <li><strong>Bağlantı Testi:</strong> API endpoint'ine bağlantı kurulabilirliğini test eder</li>
                            <li><strong>Kimlik Doğrulama:</strong> API key ve secret key'in geçerliliğini kontrol eder</li>
                            <li><strong>Yanıt Süresi:</strong> API yanıt süresini ölçer</li>
                            <li><strong>Hata Yönetimi:</strong> Hata durumlarını test eder</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5>Test Sonuçları</h5>
                        <ul>
                            <li><span class="badge badge-success">Başarılı:</span> API bağlantısı ve kimlik doğrulama başarılı</li>
                            <li><span class="badge badge-danger">Başarısız:</span> API bağlantısı veya kimlik doğrulama başarısız</li>
                            <li><span class="badge badge-warning">Uyarı:</span> Bağlantı var ancak yanıt süresi yüksek</li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <h5><i class="icon fas fa-info"></i> Önemli Notlar</h5>
                    <ul>
                        <li>Test modunda gerçek ödeme işlemi yapılmaz</li>
                        <li>API key ve secret key'lerin doğru olduğundan emin olun</li>
                        <li>Base URL'lerin güncel olduğunu kontrol edin</li>
                        <li>Test sonuçları geçici olarak saklanır</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Response Modal -->
<div class="modal fade" id="responseModal" tabindex="-1" role="dialog" aria-labelledby="responseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="responseModalLabel">API Yanıt Detayları</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="responseContent" style="max-height: 400px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Response modal
    $('#responseModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var response = button.data('response');
        var modal = $(this);
        modal.find('#responseContent').text(JSON.stringify(response, null, 2));
    });
});

function testProvider(providerId) {
    $('#provider_id').val(providerId);
    $('#testForm').submit();
}

function testAllProviders() {
    if (confirm('Tüm sağlayıcıları test etmek istediğinizden emin misiniz?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        var csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = 'csrf_token';
        csrfToken.value = '<?= $_SESSION['csrf_token'] ?? '' ?>';
        form.appendChild(csrfToken);
        
        var action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = 'test_all';
        form.appendChild(action);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-refresh test results every 30 seconds if there are results
<?php if (!empty($test_results)): ?>
setTimeout(function() {
    location.reload();
}, 30000);
<?php endif; ?>
</script>