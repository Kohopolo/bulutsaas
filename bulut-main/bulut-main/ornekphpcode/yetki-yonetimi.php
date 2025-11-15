<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth_functions.php';

// Giriş kontrolü ve yetki kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Sadece superadmin bu sayfaya erişebilir
if ($_SESSION['user_role'] !== 'superadmin') {
    header('Location: index.php?error=Bu sayfaya erişim yetkiniz yok.');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('yetki_yonetimi_goruntule', 'Yetki yönetimini görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Yetki atama/kaldırma işlemleri
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['assign_permission'])) {
        // Yetki atama yetkisi kontrolü
        if (!hasDetailedPermission('yetki_yonetimi_ata')) {
            $error_message = 'Yetki atama yetkiniz bulunmamaktadır.';
        } else {
            $user_id = intval($_POST['user_id']);
            $permission_id = intval($_POST['permission_id']);
            
            // Yetki zaten var mı kontrol et
            $existing = fetchOne("SELECT id FROM kullanici_yetkiler WHERE kullanici_id = ? AND yetki_id = ?", [$user_id, $permission_id]);
            
            if (!$existing) {
                if (executeQuery("INSERT INTO kullanici_yetkiler (kullanici_id, yetki_id) VALUES (?, ?)", [$user_id, $permission_id])) {
                    $success_message = 'Yetki başarıyla atandı.';
                    // Cache'i temizle
                    if (function_exists('clearPermissionCache')) {
                        clearPermissionCache();
                    }
                } else {
                    $error_message = 'Yetki atanırken hata oluştu.';
                }
            } else {
                $error_message = 'Bu yetki zaten kullanıcıya atanmış.';
            }
        }
    }
    
    if (isset($_POST['remove_permission'])) {
        // Yetki kaldırma yetkisi kontrolü
        if (!hasDetailedPermission('yetki_yonetimi_kaldir')) {
            $error_message = 'Yetki kaldırma yetkiniz bulunmamaktadır.';
        } else {
            $user_id = intval($_POST['user_id']);
            $permission_id = intval($_POST['permission_id']);
            
            if (executeQuery("DELETE FROM kullanici_yetkiler WHERE kullanici_id = ? AND yetki_id = ?", [$user_id, $permission_id])) {
                $success_message = 'Yetki başarıyla kaldırıldı.';
                // Cache'i temizle
                if (function_exists('clearPermissionCache')) {
                    clearPermissionCache();
                }
            } else {
                $error_message = 'Yetki kaldırılırken hata oluştu.';
            }
        }
    }
    
    if (isset($_POST['module_assign'])) {
        // Modül bazlı yetki atama yetkisi kontrolü
        if (!hasDetailedPermission('yetki_yonetimi_ata')) {
            $error_message = 'Modül bazlı yetki atama yetkiniz bulunmamaktadır.';
        } else {
            $user_id = intval($_POST['user_id']);
            $permissions = $_POST['permissions'] ?? [];
            
            // Önce kullanıcının tüm yetkilerini kaldır
            executeQuery("DELETE FROM kullanici_yetkiler WHERE kullanici_id = ?", [$user_id]);
            
            // Seçilen yetkileri ata
            $success_count = 0;
            foreach ($permissions as $permission_id) {
                if (executeQuery("INSERT INTO kullanici_yetkiler (kullanici_id, yetki_id) VALUES (?, ?)", [$user_id, intval($permission_id)])) {
                    $success_count++;
                }
            }
            
            if ($success_count > 0) {
                $success_message = $success_count . ' yetki başarıyla atandı.';
                // Cache'i temizle
                if (function_exists('clearPermissionCache')) {
                    clearPermissionCache();
                }
            } else {
                $error_message = 'Yetki atanırken hata oluştu.';
            }
        }
    }
    
    if (isset($_POST['bulk_assign'])) {
        // Toplu yetki atama yetkisi kontrolü
        if (!hasDetailedPermission('yetki_yonetimi_ata')) {
            $error_message = 'Toplu yetki atama yetkiniz bulunmamaktadır.';
        } else {
            $user_id = intval($_POST['bulk_user_id']);
            $permissions = $_POST['permissions'] ?? [];
            
            // Önce kullanıcının tüm yetkilerini kaldır
            executeQuery("DELETE FROM kullanici_yetkiler WHERE kullanici_id = ?", [$user_id]);
            
            // Seçilen yetkileri ata
            $success_count = 0;
            foreach ($permissions as $permission_id) {
                if (executeQuery("INSERT INTO kullanici_yetkiler (kullanici_id, yetki_id) VALUES (?, ?)", [$user_id, intval($permission_id)])) {
                    $success_count++;
                }
            }
            
            $success_message = "{$success_count} yetki başarıyla atandı.";
        }
    }
}

// Kullanıcıları getir
$users = fetchAll("SELECT id, ad, soyad, email, rol, durum FROM kullanicilar ORDER BY ad, soyad");

// Yetkileri getir
$permissions = fetchAll("SELECT id, yetki_adi, aciklama FROM yetkiler ORDER BY yetki_adi");

// Kullanıcı yetkilerini getir
$user_permissions = [];
foreach ($users as $user) {
    $user_permissions[$user['id']] = fetchAll("
        SELECT y.id, y.yetki_adi, y.aciklama, m.modul_adi, m.modul_aciklama
        FROM kullanici_yetkiler ky 
        JOIN yetkiler y ON ky.yetki_id = y.id 
        LEFT JOIN yetki_modulleri m ON y.modul_id = m.id
        WHERE ky.kullanici_id = ?
        ORDER BY m.sira, y.yetki_adi
    ", [$user['id']]);
}

// Modül bazlı yetkileri getir
$modulePermissions = getPermissionsByModule();

// Tüm modülleri getir
$allModules = fetchAll("SELECT * FROM yetki_modulleri WHERE aktif = 1 ORDER BY sira");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yetki Yönetimi - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-user-shield me-2"></i>Yetki Yönetimi
                </h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Kullanıcı Yetkileri Tablosu -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Kullanıcı Yetkileri
                    </h5>
                    <?php if (hasDetailedPermission('yetki_yonetimi_ata')): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkAssignModal">
                        <i class="fas fa-plus me-1"></i>Toplu Yetki Ata
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Kullanıcı</th>
                                    <th>Rol</th>
                                    <th>Durum</th>
                                    <th>Yetkiler</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <?php echo strtoupper(substr($user['ad'], 0, 1) . substr($user['soyad'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['rol'] == 'superadmin' ? 'danger' : 
                                                    ($user['rol'] == 'admin' ? 'warning' : 
                                                    ($user['rol'] == 'sales' ? 'info' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($user['rol']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['durum'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($user['durum']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="permission-list">
                                                <?php if (isset($user_permissions[$user['id']]) && count($user_permissions[$user['id']]) > 0): ?>
                                                    <?php 
                                                    $grouped_perms = [];
                                                    foreach ($user_permissions[$user['id']] as $perm) {
                                                        $module = $perm['modul_adi'] ?? 'Diğer';
                                                        if (!isset($grouped_perms[$module])) {
                                                            $grouped_perms[$module] = [];
                                                        }
                                                        $grouped_perms[$module][] = $perm;
                                                    }
                                                    
                                                    $displayed_modules = 0;
                                                    foreach ($grouped_perms as $module => $perms): 
                                                        if ($displayed_modules >= 3) {
                                                            $remaining = count($grouped_perms) - 3;
                                                            echo '<span class="badge bg-secondary">+' . $remaining . ' modül daha</span>';
                                                            break;
                                                        }
                                                    ?>
                                                        <div class="module-permissions mb-2">
                                                            <small class="text-muted fw-bold"><?php echo htmlspecialchars($module); ?> (<?php echo count($perms); ?>)</small>
                                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                                <?php foreach (array_slice($perms, 0, 3) as $perm): ?>
                                                                    <span class="badge bg-light text-dark" title="<?php echo htmlspecialchars($perm['aciklama']); ?>">
                                                                        <?php echo htmlspecialchars($perm['yetki_adi']); ?>
                                                                        <?php if (hasDetailedPermission('yetki_yonetimi_kaldir')): ?>
                                                                        <button type="button" class="btn-close btn-close-sm ms-1" 
                                                                                onclick="removePermission(<?php echo $user['id']; ?>, <?php echo $perm['id']; ?>)"
                                                                                title="Yetkiyi Kaldır"></button>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                                <?php if (count($perms) > 3): ?>
                                                                    <span class="badge bg-secondary">+<?php echo count($perms) - 3; ?> daha</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php 
                                                        $displayed_modules++;
                                                    endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Yetki atanmamış</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if (hasDetailedPermission('yetki_yonetimi_ata')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#assignPermissionModal"
                                                        data-user-id="<?php echo $user['id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>">
                                                    <i class="fas fa-plus me-1"></i>Yetki Ekle
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#moduleAssignModal"
                                                        data-user-id="<?php echo $user['id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>">
                                                    <i class="fas fa-layer-group me-1"></i>Modül Bazlı
                                                </button>
                                                <?php else: ?>
                                                <span class="text-muted">Yetki yok</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Yetki Listesi -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Mevcut Yetkiler
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($permissions as $permission): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-left-primary">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($permission['yetki_adi']); ?></h6>
                                        <p class="card-text text-muted small"><?php echo htmlspecialchars($permission['aciklama']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Yetki Atama Modalı -->
<div class="modal fade" id="assignPermissionModal" tabindex="-1" aria-labelledby="assignPermissionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignPermissionModalLabel">
                    <i class="fas fa-plus me-2"></i>Yetki Ata
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="assign_permission" value="1">
                    <input type="hidden" name="user_id" id="assignUserId">
                    
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı:</label>
                        <div class="form-control-plaintext" id="assignUserName"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="permission_id" class="form-label">Yetki Seçin:</label>
                        <select name="permission_id" id="permission_id" class="form-select" required>
                            <option value="">Yetki seçin...</option>
                            <?php foreach ($permissions as $permission): ?>
                                <option value="<?php echo $permission['id']; ?>">
                                    <?php echo htmlspecialchars($permission['yetki_adi'] . ' - ' . $permission['aciklama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i>Yetki Ata
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modül Bazlı Yetki Atama Modalı -->
<div class="modal fade" id="moduleAssignModal" tabindex="-1" aria-labelledby="moduleAssignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moduleAssignModalLabel">
                    <i class="fas fa-layer-group me-2"></i>Modül Bazlı Yetki Atama
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="module_assign" value="1">
                    <input type="hidden" name="user_id" id="moduleUserId">
                    
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı:</label>
                        <div class="form-control-plaintext" id="moduleUserName"></div>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($modulePermissions as $moduleName => $moduleData): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="form-check">
                                            <input class="form-check-input module-checkbox" type="checkbox" 
                                                   id="module_<?php echo $moduleName; ?>"
                                                   onchange="toggleModulePermissions('<?php echo $moduleName; ?>')">
                                            <label class="form-check-label fw-bold" for="module_<?php echo $moduleName; ?>">
                                                <?php echo htmlspecialchars($moduleData['modul_aciklama']); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="permission-list" id="perms_<?php echo $moduleName; ?>">
                                            <?php foreach ($moduleData['yetkiler'] as $permission): ?>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input permission-checkbox" 
                                                           type="checkbox" 
                                                           name="permissions[]" 
                                                           value="<?php echo $permission['id']; ?>"
                                                           data-module="<?php echo $moduleName; ?>"
                                                           id="perm_<?php echo $permission['id']; ?>">
                                                    <label class="form-check-label" for="perm_<?php echo $permission['id']; ?>">
                                                        <?php echo htmlspecialchars($permission['yetki_adi']); ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($permission['aciklama']); ?></small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Bilgi:</strong> Modül checkbox'ını işaretleyerek o modüldeki tüm yetkileri seçebilirsiniz.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Yetkileri Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toplu Yetki Atama Modalı -->
<div class="modal fade" id="bulkAssignModal" tabindex="-1" aria-labelledby="bulkAssignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkAssignModalLabel">
                    <i class="fas fa-cog me-2"></i>Yetki Yönetimi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="bulk_assign" value="1">
                    
                    <div class="mb-3">
                        <label for="bulk_user_id" class="form-label">Kullanıcı Seçin:</label>
                        <select name="bulk_user_id" id="bulk_user_id" class="form-select" required>
                            <option value="">Kullanıcı seçin...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad'] . ' (' . ucfirst($user['rol']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Yetkiler:</label>
                        <div class="row">
                            <?php foreach ($permissions as $permission): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="permissions[]" 
                                               value="<?php echo $permission['id']; ?>" 
                                               id="perm_<?php echo $permission['id']; ?>">
                                        <label class="form-check-label" for="perm_<?php echo $permission['id']; ?>">
                                            <strong><?php echo htmlspecialchars($permission['yetki_adi']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($permission['aciklama']); ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Uyarı:</strong> Bu işlem kullanıcının mevcut tüm yetkilerini kaldırıp, seçilen yetkileri atayacaktır.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Yetkileri Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Yetki Kaldırma Formu (Gizli) -->
<form id="removePermissionForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="remove_permission" value="1">
    <input type="hidden" name="user_id" id="removeUserId">
    <input type="hidden" name="permission_id" id="removePermissionId">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Yetki atama modalı
    const assignModal = document.getElementById('assignPermissionModal');
    assignModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        const userName = button.getAttribute('data-user-name');
        
        document.getElementById('assignUserId').value = userId;
        document.getElementById('assignUserName').textContent = userName;
    });
    
    // Toplu yetki atama modalı
    const bulkModal = document.getElementById('bulkAssignModal');
    bulkModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const userId = button.getAttribute('data-user-id');
        
        if (userId) {
            document.getElementById('bulk_user_id').value = userId;
            loadUserPermissions(userId);
        }
    });
    
    // Kullanıcı seçimi değiştiğinde yetkilerini yükle
    document.getElementById('bulk_user_id').addEventListener('change', function() {
        const userId = this.value;
        if (userId) {
            loadUserPermissions(userId);
        } else {
            // Tüm checkboxları temizle
            document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);
        }
    });
});

function loadUserPermissions(userId) {
    // AJAX ile kullanıcının mevcut yetkilerini yükle
    fetch('ajax/get_user_permissions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({user_id: userId})
    })
    .then(response => response.json())
    .then(data => {
        // Önce tüm checkboxları temizle
        document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);
        
        // Kullanıcının yetkilerini işaretle
        if (data.success && data.permissions) {
            data.permissions.forEach(permId => {
                const checkbox = document.getElementById('perm_' + permId);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function removePermission(userId, permissionId) {
    if (confirm('Bu yetkiyi kaldırmak istediğinizden emin misiniz?')) {
        document.getElementById('removeUserId').value = userId;
        document.getElementById('removePermissionId').value = permissionId;
        document.getElementById('removePermissionForm').submit();
    }
}

// Modül bazlı yetki atama modalı
const moduleModal = document.getElementById('moduleAssignModal');
moduleModal.addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const userId = button.getAttribute('data-user-id');
    const userName = button.getAttribute('data-user-name');
    
    document.getElementById('moduleUserId').value = userId;
    document.getElementById('moduleUserName').textContent = userName;
    
    // Kullanıcının mevcut yetkilerini yükle
    loadUserPermissionsForModule(userId);
});

// Modül checkbox'ı değiştiğinde
function toggleModulePermissions(moduleName) {
    const moduleCheckbox = document.getElementById('module_' + moduleName);
    const permissionCheckboxes = document.querySelectorAll('input[data-module="' + moduleName + '"]');
    
    permissionCheckboxes.forEach(checkbox => {
        checkbox.checked = moduleCheckbox.checked;
    });
}

// Kullanıcının yetkilerini modül modalı için yükle
function loadUserPermissionsForModule(userId) {
    fetch('ajax/get_user_permissions.php?user_id=' + userId)
    .then(response => response.json())
    .then(data => {
        // Önce tüm checkboxları temizle
        document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = false);
        
        // Kullanıcının yetkilerini işaretle
        if (data.success && data.permissions) {
            data.permissions.forEach(permId => {
                const checkbox = document.getElementById('perm_' + permId);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            
            // Modül checkbox'larını güncelle
            updateModuleCheckboxes();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Modül checkbox'larını güncelle
function updateModuleCheckboxes() {
    document.querySelectorAll('.module-checkbox').forEach(moduleCheckbox => {
        const moduleName = moduleCheckbox.id.replace('module_', '');
        const permissionCheckboxes = document.querySelectorAll('input[data-module="' + moduleName + '"]');
        const checkedPermissions = document.querySelectorAll('input[data-module="' + moduleName + '"]:checked');
        
        if (checkedPermissions.length === permissionCheckboxes.length) {
            moduleCheckbox.checked = true;
        } else if (checkedPermissions.length > 0) {
            moduleCheckbox.indeterminate = true;
        } else {
            moduleCheckbox.checked = false;
            moduleCheckbox.indeterminate = false;
        }
    });
}
</script>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 14px;
}

.permission-list .badge {
    position: relative;
}

.btn-close-sm {
    font-size: 0.7em;
    padding: 0;
    margin: 0;
}

.card.border-left-primary {
    border-left: 4px solid #007bff;
}
</style>

</body>
</html>