<?php
/**
 * Script Yönetim Sistemi
 * Google Analytics, Facebook Pixel, Chat Widget'ları ve diğer 3. parti scriptleri yönetin
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
if (!hasDetailedPermission('script_yonetimi_goruntule')) {
    header('Location: ../error/403.php');
    exit;
}

$pageTitle = "Script Yönetimi";
$current_page = basename($_SERVER['PHP_SELF']); // script-yonetimi.php
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
    <?php include 'includes/sidebar.php'; ?>
    
    <div id="content">
        <?php include 'includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Başlık -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2"><i class="fas fa-code"></i> Script Yönetimi</h1>
                        <p class="text-muted">3. parti scriptleri kolayca entegre edin ve yönetin</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomScriptModal">
                            <i class="fas fa-plus"></i> Özel Script Ekle
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Menüsü -->
        <ul class="nav nav-tabs mb-4" id="scriptTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="ready-services-tab" data-bs-toggle="tab" href="#ready-services" role="tab">
                    <i class="fas fa-th-large"></i> Hazır Servisler (21)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="custom-scripts-tab" data-bs-toggle="tab" href="#custom-scripts" role="tab">
                    <i class="fas fa-code"></i> Özel Scriptler <span class="badge badge-info" id="customScriptCount">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="logs-tab" data-bs-toggle="tab" href="#logs" role="tab">
                    <i class="fas fa-history"></i> Değişiklik Logları
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="help-tab" data-bs-toggle="tab" href="#help" role="tab">
                    <i class="fas fa-question-circle"></i> Yardım & Rehber
                </a>
            </li>
        </ul>

        <!-- Tab İçerikleri -->
        <div class="tab-content" id="scriptTabsContent">
            <!-- Hazır Servisler Tab -->
            <div class="tab-pane fade show active" id="ready-services" role="tabpanel">
                <!-- Filtreler -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" class="form-control" id="serviceSearch" placeholder="Servis ara...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="categoryFilter">
                            <option value="">Tüm Kategoriler</option>
                            <option value="analytics">📊 Analytics</option>
                            <option value="advertising">🎯 Advertising</option>
                            <option value="chat">💬 Live Chat</option>
                            <option value="seo">🔍 SEO & Verification</option>
                            <option value="other">🔧 Diğer</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="statusFilter">
                            <option value="">Tüm Durumlar</option>
                            <option value="active">✅ Aktif</option>
                            <option value="inactive">⭕ Pasif</option>
                        </select>
                    </div>
                </div>

                <!-- Servis Kartları -->
                <div id="servicesContainer" class="row">
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x text-muted"></i>
                        <p class="mt-3 text-muted">Servisler yükleniyor...</p>
                    </div>
                </div>
            </div>

            <!-- Özel Scriptler Tab -->
            <div class="tab-pane fade" id="custom-scripts" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover" id="customScriptsTable">
                        <thead>
                            <tr>
                                <th>Durum</th>
                                <th>Script Adı</th>
                                <th>Tip</th>
                                <th>Pozisyon</th>
                                <th>Öncelik</th>
                                <th>Son Güncelleme</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="customScriptsBody">
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                    <p class="mt-3 text-muted">Scriptler yükleniyor...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Loglar Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm" id="logsTable">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Script</th>
                                <th>İşlem</th>
                                <th>Kullanıcı</th>
                                <th>IP</th>
                                <th>Detay</th>
                            </tr>
                        </thead>
                        <tbody id="logsBody">
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                    <p class="mt-3 text-muted">Loglar yükleniyor...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Yardım Tab -->
            <div class="tab-pane fade" id="help" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-book"></i> Kullanım Rehberi</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="font-weight-bold">📊 Hazır Servisler</h6>
                                <p>Google Analytics, Facebook Pixel, Live Chat gibi popüler servisleri tek tıkla aktifleştirebilirsiniz.</p>
                                <ol>
                                    <li>İstediğiniz servisi bulun</li>
                                    <li>Toggle'ı aktif konuma getirin</li>
                                    <li>"Ayarlar" butonuna tıklayın</li>
                                    <li>Tracking ID / API Key giriniz</li>
                                    <li>Kaydet</li>
                                </ol>

                                <hr>

                                <h6 class="font-weight-bold mt-4">🔧 Özel Scriptler</h6>
                                <p>Kendi özel JavaScript/HTML kodlarınızı ekleyebilirsiniz.</p>
                                <ul>
                                    <li><code>&lt;head&gt;</code> - Sayfa başında yükle (Analytics, Meta Tags)</li>
                                    <li><code>&lt;body start&gt;</code> - Body etiketinden hemen sonra (GTM)</li>
                                    <li><code>&lt;body end&gt;</code> - Body kapatma etiketinden önce (Chat Widget)</li>
                                </ul>

                                <hr>

                                <h6 class="font-weight-bold mt-4">🍪 KVKK/GDPR Uyumluluğu</h6>
                                <p>Her script için çerez kategorisi belirleyebilirsiniz:</p>
                                <ul>
                                    <li><strong>Necessary:</strong> Her zaman yüklenir (SEO verification)</li>
                                    <li><strong>Analytics:</strong> Kullanıcı izin verirse (Google Analytics)</li>
                                    <li><strong>Marketing:</strong> Pazarlama scriptleri (Facebook Pixel)</li>
                                    <li><strong>Preferences:</strong> Tercih scriptleri (Chat)</li>
                                </ul>

                                <div class="alert alert-info mt-4">
                                    <i class="fas fa-lightbulb"></i> <strong>Pro İpucu:</strong> 
                                    Scriptleri öncelik değerine göre sıralayabilirsiniz. Düşük sayı = önce yüklenir.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> İstatistikler</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <span class="text-muted">Toplam Servis:</span>
                                    <strong class="float-right">21</strong>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted">Aktif Servis:</span>
                                    <strong class="float-right text-success" id="activeServiceCount">0</strong>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted">Özel Script:</span>
                                    <strong class="float-right" id="customScriptCountSidebar">0</strong>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted">Aktif Özel Script:</span>
                                    <strong class="float-right text-success" id="activeCustomCount">0</strong>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Önemli Notlar</h5>
                            </div>
                            <div class="card-body">
                                <ul class="small mb-0">
                                    <li>Çok fazla script sayfa hızını düşürebilir</li>
                                    <li>Async/Defer seçeneklerini kullanın</li>
                                    <li>Test ortamında test edin</li>
                                    <li>Cookie consent aktif olmalı</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Özel Script Ekleme Modal -->
<div class="modal fade" id="addCustomScriptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Özel Script Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="customScriptForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Script Adı *</label>
                        <input type="text" class="form-control" name="script_name" required>
                    </div>
                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea class="form-control" name="script_description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tip *</label>
                                <select class="form-control" name="script_type" required>
                                    <option value="analytics">Analytics</option>
                                    <option value="advertising">Advertising</option>
                                    <option value="chat">Chat</option>
                                    <option value="seo">SEO</option>
                                    <option value="conversion">Conversion</option>
                                    <option value="other">Diğer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Pozisyon *</label>
                                <select class="form-control" name="position" required>
                                    <option value="head">Head</option>
                                    <option value="body_start">Body Start</option>
                                    <option value="body_end">Body End</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Öncelik</label>
                                <input type="number" class="form-control" name="priority" value="50" min="0" max="100">
                                <small class="text-muted">Düşük sayı = önce yüklenir</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Async</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="load_async" name="load_async">
                                    <label class="custom-control-label" for="load_async">Aktif</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Defer</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="load_defer" name="load_defer">
                                    <label class="custom-control-label" for="load_defer">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Script Kodu *</label>
                        <textarea class="form-control" name="script_code" rows="8" required placeholder="<script>...</script>"></textarea>
                        <small class="text-muted">HTML, JavaScript veya her ikisini birden ekleyebilirsiniz</small>
                    </div>
                    <div class="form-group">
                        <label>Cookie Consent Kategorisi</label>
                        <select class="form-control" name="consent_category">
                            <option value="necessary">Necessary (Her zaman yükle)</option>
                            <option value="analytics" selected>Analytics</option>
                            <option value="marketing">Marketing</option>
                            <option value="preferences">Preferences</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Servis Ayarları Modal (Dinamik) -->
<div class="modal fade" id="serviceSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="serviceModalTitle"><i class="fas fa-cog"></i> Servis Ayarları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="serviceSettingsForm">
                <input type="hidden" name="service_id" id="service_id">
                <div class="modal-body" id="serviceModalBody">
                    <!-- Dinamik içerik buraya yüklenecek -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.service-card {
    transition: all 0.3s;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 20px;
}
.service-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.service-card.active {
    border-color: #28a745;
    background: #f8fff9;
}
.service-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}
.badge-category {
    font-size: 0.75rem;
    padding: 4px 8px;
}
</style>

<script>
// CSRF Token
const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

// jQuery yüklü mü kontrol et
if (typeof jQuery === 'undefined') {
    console.error('jQuery yüklü değil! Header kontrol edilmeli.');
}

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined') {
        loadServices();
        loadCustomScripts();
        loadLogs();
        updateStats();
    }
});

// Servisleri yükle
function loadServices() {
    $.ajax({
        url: 'ajax/script-get-services.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                renderServices(response.data);
            } else {
                showToast('error', 'Hata!', response.message);
            }
        },
        error: function() {
            $('#servicesContainer').html('<div class="col-12"><div class="alert alert-danger">Servisler yüklenirken hata oluştu!</div></div>');
        }
    });
}

// Servisleri render et
function renderServices(services) {
    let html = '';
    services.forEach(service => {
        const activeClass = service.is_active == 1 ? 'active' : '';
        const statusBadge = service.is_active == 1 ? 
            '<span class="badge badge-success">Aktif</span>' : 
            '<span class="badge badge-secondary">Pasif</span>';
        
        html += `
            <div class="col-md-4 service-item" data-category="${service.service_category}" data-status="${service.is_active == 1 ? 'active' : 'inactive'}">
                <div class="card service-card ${activeClass}">
                    <div class="card-body text-center">
                        <div class="service-icon text-primary">
                            <i class="${service.service_icon}"></i>
                        </div>
                        <h5 class="card-title">${service.service_label}</h5>
                        <p class="card-text text-muted small">${service.service_description || ''}</p>
                        <div class="mb-2">
                            <span class="badge badge-category badge-info">${getCategoryName(service.service_category)}</span>
                            ${statusBadge}
                        </div>
                        <div class="d-flex justify-content-center gap-2">
                            <div class="custom-control custom-switch d-inline-block mr-3">
                                <input type="checkbox" class="custom-control-input" id="toggle_${service.id}" 
                                    ${service.is_active == 1 ? 'checked' : ''} 
                                    onchange="toggleService(${service.id}, this.checked)">
                                <label class="custom-control-label" for="toggle_${service.id}"></label>
                            </div>
                            <button class="btn btn-sm btn-primary" onclick="openServiceSettings(${service.id})">
                                <i class="fas fa-cog"></i> Ayarlar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#servicesContainer').html(html);
    applyFilters();
}

// Kategori adını al
function getCategoryName(cat) {
    const categories = {
        'analytics': '📊 Analytics',
        'advertising': '🎯 Advertising',
        'chat': '💬 Chat',
        'seo': '🔍 SEO',
        'other': '🔧 Other'
    };
    return categories[cat] || cat;
}

// Servisi aktif/pasif yap
function toggleService(id, isActive) {
    $.ajax({
        url: 'ajax/script-toggle-service.php',
        method: 'POST',
        data: {
            csrf_token: csrfToken,
            service_id: id,
            is_active: isActive ? 1 : 0
        },
        success: function(response) {
            if (response.success) {
                showToast('success', 'Başarılı!', response.message);
                loadServices();
                updateStats();
            } else {
                showToast('error', 'Hata!', response.message);
                $(`#toggle_${id}`).prop('checked', !isActive);
            }
        }
    });
}

// Servis ayarlarını aç
function openServiceSettings(id) {
    $.ajax({
        url: 'ajax/script-get-service-settings.php',
        method: 'GET',
        data: { service_id: id },
        success: function(response) {
            if (response.success) {
                $('#service_id').val(id);
                $('#serviceModalTitle').html(`<i class="${response.data.service_icon}"></i> ${response.data.service_label} Ayarları`);
                $('#serviceModalBody').html(response.settings_html);
                
                // Bootstrap 5 modal
                const modal = new bootstrap.Modal(document.getElementById('serviceSettingsModal'));
                modal.show();
            }
        }
    });
}

// Servis ayarlarını kaydet
$('#serviceSettingsForm').submit(function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'ajax/script-save-service-settings.php',
        method: 'POST',
        data: $(this).serialize() + '&csrf_token=' + csrfToken,
        success: function(response) {
            if (response.success) {
                showToast('success', 'Başarılı!', response.message);
                
                // Bootstrap 5 modal kapatma
                const modal = bootstrap.Modal.getInstance(document.getElementById('serviceSettingsModal'));
                if (modal) modal.hide();
                
                loadServices();
            } else {
                showToast('error', 'Hata!', response.message);
            }
        }
    });
});

// Özel scriptleri yükle
function loadCustomScripts() {
    $.ajax({
        url: 'ajax/script-get-custom.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                renderCustomScripts(response.data);
            }
        }
    });
}

// Özel scriptleri render et
function renderCustomScripts(scripts) {
    let html = '';
    if (scripts.length === 0) {
        html = '<tr><td colspan="7" class="text-center py-4 text-muted">Henüz özel script eklenmemiş</td></tr>';
    } else {
        scripts.forEach(script => {
            const statusIcon = script.is_active == 1 ? 
                '<span class="badge badge-success"><i class="fas fa-check"></i> Aktif</span>' : 
                '<span class="badge badge-secondary"><i class="fas fa-times"></i> Pasif</span>';
                
            html += `
                <tr>
                    <td>${statusIcon}</td>
                    <td><strong>${script.script_name}</strong><br><small class="text-muted">${script.script_description || ''}</small></td>
                    <td><span class="badge badge-info">${script.script_type}</span></td>
                    <td><span class="badge badge-secondary">${script.position}</span></td>
                    <td>${script.priority}</td>
                    <td>${formatDate(script.updated_at)}</td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editCustomScript(${script.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteCustomScript(${script.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#customScriptsBody').html(html);
    $('#customScriptCount').text(scripts.length);
    $('#customScriptCountSidebar').text(scripts.length);
}

// Logları yükle
function loadLogs() {
    $.ajax({
        url: 'ajax/script-get-logs.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                renderLogs(response.data);
            }
        }
    });
}

// Logları render et
function renderLogs(logs) {
    let html = '';
    if (logs.length === 0) {
        html = '<tr><td colspan="6" class="text-center py-4 text-muted">Henüz log kaydı yok</td></tr>';
    } else {
        logs.forEach(log => {
            html += `
                <tr>
                    <td>${formatDate(log.changed_at)}</td>
                    <td>${log.script_name || 'N/A'}</td>
                    <td><span class="badge badge-info">${log.action}</span></td>
                    <td>${log.username || 'System'}</td>
                    <td>${log.ip_address}</td>
                    <td><button class="btn btn-sm btn-link" onclick="viewLogDetail(${log.id})">Detay</button></td>
                </tr>
            `;
        });
    }
    $('#logsBody').html(html);
}

// İstatistikleri güncelle
function updateStats() {
    $.ajax({
        url: 'ajax/script-get-stats.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                $('#activeServiceCount').text(response.data.active_services);
                $('#activeCustomCount').text(response.data.active_custom);
            }
        }
    });
}

// Filtreleme
$('#serviceSearch, #categoryFilter, #statusFilter').on('input change', function() {
    applyFilters();
});

function applyFilters() {
    const searchTerm = $('#serviceSearch').val().toLowerCase();
    const category = $('#categoryFilter').val();
    const status = $('#statusFilter').val();
    
    $('.service-item').each(function() {
        const $item = $(this);
        const text = $item.text().toLowerCase();
        const itemCategory = $item.data('category');
        const itemStatus = $item.data('status');
        
        let show = true;
        
        if (searchTerm && !text.includes(searchTerm)) show = false;
        if (category && itemCategory !== category) show = false;
        if (status && itemStatus !== status) show = false;
        
        $item.toggle(show);
    });
}

// Tarih formatla
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR');
}

// Toast bildirimi
function showToast(type, title, message) {
    // Bootstrap toast veya alert kullanabilirsiniz
    alert(`${title}\n${message}`);
}
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
