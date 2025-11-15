<?php
/**
 * AI Ayarları - Provider Yönetimi
 */

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/detailed_permission_functions.php';
require_once '../config/database.php';

// Yetki kontrolü
if (!hasDetailedPermission('ai_settings_view')) {
    header('Location: ../error/403.php');
    exit;
}

$pageTitle = "AI Ayarları";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
    <?php if (file_exists('includes/sidebar.php')) include 'includes/sidebar.php'; ?>
    
    <div id="content">
        <?php if (file_exists('includes/header.php')) include 'includes/header.php'; ?>

        <div class="main-content">
            <div class="container-fluid">
                <!-- Başlık -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h3 mb-2"><i class="fas fa-robot"></i> AI Ayarları</h1>
                                <p class="text-muted">AI Provider'larını yapılandırın ve yönetin</p>
                            </div>
                            <div>
                                <button class="btn btn-success" onclick="testAllProviders()">
                                    <i class="fas fa-vial"></i> Tümünü Test Et
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="mb-0" id="activeProviderCount">0</h5>
                                <small>Aktif Provider</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="mb-0" id="totalRequestCount">0</h5>
                                <small>Toplam İstek</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="mb-0" id="totalTokenCount">0</h5>
                                <small>Toplam Token</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="mb-0" id="avgResponseTime">0ms</h5>
                                <small>Ort. Yanıt Süresi</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Provider Kartları -->
                <div id="providersContainer" class="row">
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x text-muted"></i>
                        <p class="mt-3 text-muted">Provider'lar yükleniyor...</p>
                    </div>
                </div>

                <!-- Kullanım İstatistikleri -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Son Kullanımlar</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tarih</th>
                                                <th>Provider</th>
                                                <th>İstek Tipi</th>
                                                <th>Token</th>
                                                <th>Süre</th>
                                                <th>Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody id="usageStatsTable">
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <i class="fas fa-spinner fa-spin"></i> Yükleniyor...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Provider Ayarları Modal -->
<div class="modal fade" id="providerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="providerModalTitle">
                    <i class="fas fa-cog"></i> Provider Ayarları
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="providerForm">
                <input type="hidden" name="provider_id" id="provider_id">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Provider Adı</label>
                        <input type="text" class="form-control" id="provider_label" disabled>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>API Key *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="api_key" id="api_key" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleApiKey()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <small class="text-muted">API anahtarınızı güvenli bir şekilde saklayın</small>
                    </div>

                    <div class="form-group mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active">
                            <label class="form-check-label" for="is_active">
                                Provider'ı Aktif Et
                            </label>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_default" id="is_default">
                            <label class="form-check-label" for="is_default">
                                Varsayılan Provider Yap
                            </label>
                        </div>
                        <small class="text-muted">AI işlemleri için varsayılan olarak bu provider kullanılır</small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Nasıl API Key Alınır?</strong>
                        <div id="apiKeyHelp" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-warning" onclick="testProvider()">
                        <i class="fas fa-vial"></i> Test Et
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.provider-card {
    transition: all 0.3s;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 20px;
}
.provider-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.provider-card.active {
    border-color: #28a745;
    background: linear-gradient(135deg, #f8fff9 0%, #e8f5e9 100%);
}
.provider-card.default {
    border-color: #007bff;
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
}
.provider-icon {
    font-size: 3rem;
    margin-bottom: 10px;
}
.badge-free {
    background: linear-gradient(135deg, #4CAF50 0%, #81C784 100%);
}
.badge-paid {
    background: linear-gradient(135deg, #FF9800 0%, #FFB74D 100%);
}
</style>

<script>
const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    loadProviders();
    loadStats();
    loadUsageStats();
});

// Provider'ları yükle
function loadProviders() {
    $.ajax({
        url: 'ajax/ai-get-providers.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                renderProviders(response.data);
            } else {
                showError('Provider\'lar yüklenirken hata: ' + response.message);
            }
        },
        error: function() {
            showError('Sunucu hatası!');
        }
    });
}

// Provider'ları render et
function renderProviders(providers) {
    let html = '';
    
    providers.forEach(provider => {
        const activeClass = provider.is_active == 1 ? 'active' : '';
        const defaultClass = provider.is_default == 1 ? 'default' : '';
        const statusBadge = provider.is_active == 1 
            ? '<span class="badge bg-success"><i class="fas fa-check"></i> Aktif</span>' 
            : '<span class="badge bg-secondary"><i class="fas fa-times"></i> Pasif</span>';
        const costBadge = provider.is_free == 1
            ? '<span class="badge badge-free">🆓 Ücretsiz</span>'
            : '<span class="badge badge-paid">💰 Ücretli</span>';
        const defaultBadge = provider.is_default == 1
            ? '<span class="badge bg-primary"><i class="fas fa-star"></i> Varsayılan</span>'
            : '';
        const hasKey = provider.api_key && provider.api_key.length > 0;
        const keyBadge = hasKey
            ? '<span class="badge bg-info"><i class="fas fa-key"></i> API Key Var</span>'
            : '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> API Key Yok</span>';
        
        html += `
            <div class="col-md-4">
                <div class="card provider-card ${activeClass} ${defaultClass}">
                    <div class="card-body text-center">
                        <div class="provider-icon text-primary">
                            <i class="${provider.provider_icon}"></i>
                        </div>
                        <h5 class="card-title">${provider.provider_label}</h5>
                        <div class="mb-3">
                            ${statusBadge}
                            ${costBadge}
                            ${defaultBadge}
                        </div>
                        <div class="mb-3">
                            ${keyBadge}
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-sm" onclick="openProviderSettings(${provider.id})">
                                <i class="fas fa-cog"></i> Ayarlar
                            </button>
                            ${hasKey ? `
                                <button class="btn btn-outline-warning btn-sm" onclick="quickTest(${provider.id})">
                                    <i class="fas fa-vial"></i> Hızlı Test
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#providersContainer').html(html);
}

// Provider ayarlarını aç
function openProviderSettings(id) {
    $.ajax({
        url: 'ajax/ai-get-provider-details.php',
        method: 'GET',
        data: { provider_id: id },
        success: function(response) {
            if (response.success) {
                const provider = response.data;
                
                $('#provider_id').val(provider.id);
                $('#provider_label').val(provider.provider_label);
                $('#api_key').val(provider.api_key || '');
                $('#is_active').prop('checked', provider.is_active == 1);
                $('#is_default').prop('checked', provider.is_default == 1);
                $('#providerModalTitle').html(`<i class="${provider.provider_icon}"></i> ${provider.provider_label} Ayarları`);
                
                // API Key yardım metni
                const helpTexts = {
                    'groq': '1. <a href="https://console.groq.com" target="_blank">console.groq.com</a> adresine git<br>2. API Keys → Create API Key<br>3. Anahtarı kopyala (gsk_...)',
                    'huggingface': '1. <a href="https://huggingface.co/settings/tokens" target="_blank">huggingface.co/settings/tokens</a><br>2. New token → Create<br>3. Token\'ı kopyala (hf_...)',
                    'gemini': '1. <a href="https://makersuite.google.com/app/apikey" target="_blank">makersuite.google.com/app/apikey</a><br>2. Create API Key<br>3. Key\'i kopyala',
                    'openai': '1. <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a><br>2. Create new secret key<br>3. Key\'i kopyala (sk-...)',
                    'claude': '1. <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a><br>2. API Keys → Create Key<br>3. Key\'i kopyala (sk-ant-...)',
                    'ollama': 'Ollama local\'de çalışır, API key gerekmez.<br>Kurulum: <code>curl https://ollama.ai/install.sh | sh</code>'
                };
                
                $('#apiKeyHelp').html(helpTexts[provider.provider_name] || 'API key için provider dokümantasyonuna bakın.');
                
                const modal = new bootstrap.Modal(document.getElementById('providerModal'));
                modal.show();
            }
        }
    });
}

// API Key görünürlüğünü değiştir
function toggleApiKey() {
    const input = document.getElementById('api_key');
    const icon = document.getElementById('toggleIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Provider'ı kaydet
$('#providerForm').submit(function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'ajax/ai-save-provider.php',
        method: 'POST',
        data: $(this).serialize() + '&csrf_token=' + csrfToken,
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                const modal = bootstrap.Modal.getInstance(document.getElementById('providerModal'));
                modal.hide();
                loadProviders();
                loadStats();
            } else {
                showError(response.message);
            }
        }
    });
});

// Provider'ı test et
function testProvider() {
    const providerId = $('#provider_id').val();
    const apiKey = $('#api_key').val();
    
    if (!apiKey) {
        showError('Lütfen önce API Key girin!');
        return;
    }
    
    showInfo('Test ediliyor...');
    
    $.ajax({
        url: 'ajax/ai-test-provider.php',
        method: 'POST',
        data: {
            provider_id: providerId,
            api_key: apiKey,
            csrf_token: csrfToken
        },
        success: function(response) {
            if (response.success) {
                showSuccess('✅ Test başarılı! Yanıt: ' + response.response.substring(0, 100) + '...');
            } else {
                showError('❌ Test başarısız: ' + response.message);
            }
        }
    });
}

// Hızlı test
function quickTest(providerId) {
    showInfo('Test ediliyor...');
    
    $.ajax({
        url: 'ajax/ai-test-provider.php',
        method: 'POST',
        data: {
            provider_id: providerId,
            csrf_token: csrfToken
        },
        success: function(response) {
            if (response.success) {
                showSuccess('✅ Test başarılı!');
            } else {
                showError('❌ Test başarısız: ' + response.message);
            }
        }
    });
}

// Tümünü test et
function testAllProviders() {
    showInfo('Tüm provider\'lar test ediliyor...');
    
    $.ajax({
        url: 'ajax/ai-test-all-providers.php',
        method: 'POST',
        data: { csrf_token: csrfToken },
        success: function(response) {
            if (response.success) {
                let msg = 'Test Sonuçları:\n\n';
                response.results.forEach(r => {
                    msg += (r.success ? '✅' : '❌') + ' ' + r.provider + '\n';
                });
                alert(msg);
            }
        }
    });
}

// İstatistikleri yükle
function loadStats() {
    $.ajax({
        url: 'ajax/ai-get-stats.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                $('#activeProviderCount').text(response.data.active_providers);
                $('#totalRequestCount').text(response.data.total_requests);
                $('#totalTokenCount').text(response.data.total_tokens.toLocaleString());
                $('#avgResponseTime').text(response.data.avg_response_time + 'ms');
            }
        }
    });
}

// Kullanım istatistiklerini yükle
function loadUsageStats() {
    $.ajax({
        url: 'ajax/ai-get-usage-stats.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                renderUsageStats(response.data);
            }
        }
    });
}

// Kullanım istatistiklerini render et
function renderUsageStats(stats) {
    let html = '';
    
    if (stats.length === 0) {
        html = '<tr><td colspan="6" class="text-center py-4 text-muted">Henüz kullanım kaydı yok</td></tr>';
    } else {
        stats.forEach(stat => {
            const statusBadge = stat.success == 1
                ? '<span class="badge bg-success">Başarılı</span>'
                : '<span class="badge bg-danger">Başarısız</span>';
            
            html += `
                <tr>
                    <td>${formatDate(stat.created_at)}</td>
                    <td><strong>${stat.provider_label}</strong></td>
                    <td><span class="badge bg-info">${stat.request_type}</span></td>
                    <td>${stat.total_tokens}</td>
                    <td>${stat.response_time_ms}ms</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });
    }
    
    $('#usageStatsTable').html(html);
}

// Tarih formatla
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString('tr-TR');
}

// Bildirimler
function showSuccess(msg) {
    alert('✅ ' + msg);
}

function showError(msg) {
    alert('❌ ' + msg);
}

function showInfo(msg) {
    console.log('ℹ️ ' + msg);
}
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

