<?php
/**
 * Form Builder - Drag & Drop Form Oluşturucu
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
if (!hasDetailedPermission('form_builder_view')) {
    header('Location: ../error/403.php');
    exit;
}

$pageTitle = "Form Builder";
$current_page = basename($_SERVER['PHP_SELF']);

// Formları çek
$stmt = $pdo->query("
    SELECT 
        cf.*,
        (SELECT COUNT(*) FROM form_submissions WHERE form_id = cf.id) as total_submissions
    FROM custom_forms cf
    ORDER BY cf.updated_at DESC
");

$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php if (file_exists('assets/css/admin.css')): ?>
    <link href="assets/css/admin.css" rel="stylesheet">
    <?php endif; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <style>
        .form-card {
            transition: all 0.3s;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }
        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .form-card.active {
            border-color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e9 100%);
        }
    </style>
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
                                <h1 class="h3 mb-2"><i class="fas fa-wpforms"></i> Form Builder</h1>
                                <p class="text-muted">Özel formlar oluşturun ve yönetin</p>
                            </div>
                            <?php if (hasDetailedPermission('form_builder_create')): ?>
                            <div>
                                <button class="btn btn-primary" onclick="createNewForm()">
                                    <i class="fas fa-plus"></i> Yeni Form Oluştur
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Listesi -->
                <div class="row">
                    <?php if (empty($forms)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <p class="text-muted">Henüz form oluşturulmamış.</p>
                        <?php if (hasDetailedPermission('form_builder_create')): ?>
                        <button class="btn btn-primary" onclick="createNewForm()">
                            <i class="fas fa-plus"></i> İlk Formu Oluştur
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <?php foreach ($forms as $form): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card form-card <?php echo $form['is_active'] ? 'active' : ''; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0">
                                            <?php echo htmlspecialchars($form['form_name']); ?>
                                        </h5>
                                        <?php if ($form['is_active']): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="text-muted small mb-3">
                                        <?php echo htmlspecialchars($form['description'] ?? ''); ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-inbox"></i> 
                                            <?php echo $form['total_submissions']; ?> Gönderi
                                        </small>
                                        <small class="text-muted">
                                            <?php echo date('d.m.Y', strtotime($form['updated_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="btn-group btn-group-sm w-100">
                                        <?php if (hasDetailedPermission('form_submissions_view')): ?>
                                        <a href="form-submissions.php?form_id=<?php echo $form['id']; ?>" 
                                           class="btn btn-info">
                                            <i class="fas fa-inbox"></i> Gönderiler
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasDetailedPermission('form_builder_edit')): ?>
                                        <button class="btn btn-warning" 
                                                onclick="editForm(<?php echo $form['id']; ?>)">
                                            <i class="fas fa-edit"></i> Düzenle
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasDetailedPermission('form_builder_delete')): ?>
                                        <button class="btn btn-danger" 
                                                onclick="deleteForm(<?php echo $form['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" 
                                               value="&lt;iframe src=&quot;<?php echo $_SERVER['HTTP_HOST']; ?>/form/<?php echo $form['id']; ?>&quot; width=&quot;100%&quot; height=&quot;500&quot;&gt;&lt;/iframe&gt;" 
                                               readonly onclick="this.select()">
                                        <button class="btn btn-outline-secondary" 
                                                onclick="copyEmbedCode(<?php echo $form['id']; ?>)">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- Form Oluştur/Düzenle Modal -->
<div class="modal fade" id="formBuilderModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formBuilderModalTitle">
                    <i class="fas fa-wpforms"></i> Form Builder
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formBuilderForm">
                <input type="hidden" name="form_id" id="form_id">
                <div class="modal-body">
                    <div class="row">
                        <!-- Sol Panel - Form Ayarları -->
                        <div class="col-md-4">
                            <h6 class="mb-3"><i class="fas fa-cog"></i> Form Ayarları</h6>
                            
                            <div class="mb-3">
                                <label>Form Adı *</label>
                                <input type="text" class="form-control" name="form_name" id="form_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Açıklama</label>
                                <textarea class="form-control" name="description" id="description" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active">
                                    <label class="form-check-label">Aktif</label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>E-posta Bildirimi</label>
                                <input type="email" class="form-control" name="notification_email" id="notification_email" placeholder="ornek@email.com">
                                <small class="text-muted">Form gönderildiğinde bu adrese bildirim gönderilir</small>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3"><i class="fas fa-plus"></i> Alan Ekle</h6>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('text')">
                                    <i class="fas fa-font"></i> Metin Kutusu
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('textarea')">
                                    <i class="fas fa-align-left"></i> Çok Satırlı Metin
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('email')">
                                    <i class="fas fa-envelope"></i> E-posta
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('tel')">
                                    <i class="fas fa-phone"></i> Telefon
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('number')">
                                    <i class="fas fa-hashtag"></i> Sayı
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('date')">
                                    <i class="fas fa-calendar"></i> Tarih
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('select')">
                                    <i class="fas fa-list"></i> Açılır Liste
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('checkbox')">
                                    <i class="fas fa-check-square"></i> Onay Kutusu
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addField('radio')">
                                    <i class="fas fa-dot-circle"></i> Çoktan Seçmeli
                                </button>
                            </div>
                        </div>
                        
                        <!-- Sağ Panel - Form Önizleme -->
                        <div class="col-md-8">
                            <h6 class="mb-3"><i class="fas fa-eye"></i> Form Önizleme</h6>
                            <div id="formPreview" class="border rounded p-4" style="min-height: 500px; background: #f8f9fa;">
                                <p class="text-center text-muted">Form alanlarını ekleyin</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
let formFields = [];

// Yeni form oluştur
function createNewForm() {
    formFields = [];
    $('#form_id').val('');
    $('#form_name').val('');
    $('#description').val('');
    $('#is_active').prop('checked', true);
    $('#notification_email').val('');
    $('#formPreview').html('<p class="text-center text-muted">Form alanlarını ekleyin</p>');
    
    const modal = new bootstrap.Modal(document.getElementById('formBuilderModal'));
    modal.show();
}

// Form düzenle
function editForm(formId) {
    $.ajax({
        url: 'ajax/form-builder-get.php',
        method: 'GET',
        data: { form_id: formId },
        success: function(response) {
            if (response.success) {
                const form = response.data;
                $('#form_id').val(form.id);
                $('#form_name').val(form.form_name);
                $('#description').val(form.description);
                $('#is_active').prop('checked', form.is_active == 1);
                $('#notification_email').val(form.notification_email);
                
                formFields = JSON.parse(form.form_fields || '[]');
                renderPreview();
                
                const modal = new bootstrap.Modal(document.getElementById('formBuilderModal'));
                modal.show();
            } else {
                alert('❌ ' + response.message);
            }
        }
    });
}

// Form sil
function deleteForm(formId) {
    if (!confirm('Bu formu silmek istediğinize emin misiniz?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/form-builder-delete.php',
        method: 'POST',
        data: {
            form_id: formId,
            csrf_token: csrfToken
        },
        success: function(response) {
            if (response.success) {
                alert('✅ ' + response.message);
                location.reload();
            } else {
                alert('❌ ' + response.message);
            }
        }
    });
}

// Alan ekle
function addField(type) {
    const fieldId = 'field_' + Date.now();
    const field = {
        id: fieldId,
        type: type,
        label: 'Yeni Alan',
        placeholder: '',
        required: false,
        options: type === 'select' || type === 'radio' ? ['Seçenek 1', 'Seçenek 2'] : []
    };
    
    formFields.push(field);
    renderPreview();
}

// Önizlemeyi render et
function renderPreview() {
    if (formFields.length === 0) {
        $('#formPreview').html('<p class="text-center text-muted">Form alanlarını ekleyin</p>');
        return;
    }
    
    let html = '<form>';
    
    formFields.forEach((field, index) => {
        html += `
            <div class="mb-3 border rounded p-3 position-relative" style="background: white;">
                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" onclick="removeField(${index})">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" value="${field.label}" 
                           onchange="updateFieldLabel(${index}, this.value)" placeholder="Alan etiketi">
                </div>
                
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" ${field.required ? 'checked' : ''} 
                           onchange="toggleRequired(${index})">
                    <label class="form-check-label">Zorunlu</label>
                </div>
        `;
        
        switch (field.type) {
            case 'text':
            case 'email':
            case 'tel':
            case 'number':
            case 'date':
                html += `<input type="${field.type}" class="form-control" placeholder="${field.placeholder}" ${field.required ? 'required' : ''}>`;
                break;
            case 'textarea':
                html += `<textarea class="form-control" rows="3" placeholder="${field.placeholder}" ${field.required ? 'required' : ''}></textarea>`;
                break;
            case 'select':
                html += '<select class="form-control" ' + (field.required ? 'required' : '') + '>';
                html += '<option value="">Seçiniz...</option>';
                field.options.forEach(option => {
                    html += `<option>${option}</option>`;
                });
                html += '</select>';
                html += `<button type="button" class="btn btn-sm btn-link" onclick="editOptions(${index})">Seçenekleri Düzenle</button>`;
                break;
            case 'checkbox':
                html += `<div class="form-check"><input class="form-check-input" type="checkbox" ${field.required ? 'required' : ''}><label class="form-check-label">${field.label}</label></div>`;
                break;
            case 'radio':
                field.options.forEach(option => {
                    html += `<div class="form-check"><input class="form-check-input" type="radio" name="${field.id}" ${field.required ? 'required' : ''}><label class="form-check-label">${option}</label></div>`;
                });
                html += `<button type="button" class="btn btn-sm btn-link" onclick="editOptions(${index})">Seçenekleri Düzenle</button>`;
                break;
        }
        
        html += '</div>';
    });
    
    html += '</form>';
    $('#formPreview').html(html);
}

// Alan sil
function removeField(index) {
    formFields.splice(index, 1);
    renderPreview();
}

// Alan etiketini güncelle
function updateFieldLabel(index, label) {
    formFields[index].label = label;
    renderPreview();
}

// Zorunlu durumunu değiştir
function toggleRequired(index) {
    formFields[index].required = !formFields[index].required;
    renderPreview();
}

// Seçenekleri düzenle
function editOptions(index) {
    const field = formFields[index];
    const optionsStr = field.options.join('\n');
    const newOptionsStr = prompt('Her satıra bir seçenek girin:', optionsStr);
    
    if (newOptionsStr !== null) {
        field.options = newOptionsStr.split('\n').filter(o => o.trim() !== '');
        renderPreview();
    }
}

// Formu kaydet
$('#formBuilderForm').submit(function(e) {
    e.preventDefault();
    
    if (formFields.length === 0) {
        alert('❌ Lütfen en az bir alan ekleyin!');
        return;
    }
    
    const formData = {
        form_id: $('#form_id').val(),
        form_name: $('#form_name').val(),
        description: $('#description').val(),
        is_active: $('#is_active').is(':checked') ? 1 : 0,
        notification_email: $('#notification_email').val(),
        form_fields: JSON.stringify(formFields),
        csrf_token: csrfToken
    };
    
    $.ajax({
        url: 'ajax/form-builder-save.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                alert('✅ ' + response.message);
                location.reload();
            } else {
                alert('❌ ' + response.message);
            }
        },
        error: function() {
            alert('❌ Sunucu hatası!');
        }
    });
});

// Embed kodu kopyala
function copyEmbedCode(formId) {
    const embedCode = `<iframe src="${window.location.protocol}//${window.location.host}/form/${formId}" width="100%" height="500"></iframe>`;
    navigator.clipboard.writeText(embedCode).then(() => {
        alert('✅ Embed kodu kopyalandı!');
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

