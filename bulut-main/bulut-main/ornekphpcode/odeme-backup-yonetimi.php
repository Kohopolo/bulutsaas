<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-backup-yonetimi.php
// Ödeme backup yönetimi sayfası

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/backup/PaymentBackup.php';
require_once '../includes/logging/PaymentLogger.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_backup_yonetimi', 'Ödeme backup yönetimi yetkiniz bulunmamaktadır.');

$logger = new PaymentLogger($database_connection);
$backup_manager = new PaymentBackup($database_connection, $logger);

$page_title = 'Ödeme Backup Yönetimi';

// Backup oluşturma
if (isset($_POST['create_backup'])) {
    $backup_type = $_POST['backup_type'];
    $description = $_POST['description'];
    
    try {
        if ($backup_type === 'full') {
            $backup_info = $backup_manager->createFullBackup($description);
        } else {
            $last_backup = $backup_manager->listBackups();
            $last_backup_id = !empty($last_backup) ? $last_backup[0]['backup_id'] : null;
            $backup_info = $backup_manager->createIncrementalBackup($last_backup_id, $description);
        }
        
        $success_message = "Backup başarıyla oluşturuldu: " . $backup_info['backup_id'];
    } catch (Exception $e) {
        $error_message = "Backup oluşturulurken hata: " . $e->getMessage();
    }
}

// Backup geri yükleme
if (isset($_POST['restore_backup'])) {
    $backup_id = $_POST['backup_id'];
    $tables_only = isset($_POST['tables_only']);
    
    try {
        $backup_manager->restoreBackup($backup_id, $tables_only);
        $success_message = "Backup başarıyla geri yüklendi: " . $backup_id;
    } catch (Exception $e) {
        $error_message = "Backup geri yüklenirken hata: " . $e->getMessage();
    }
}

// Backup silme
if (isset($_POST['delete_backup'])) {
    $backup_id = $_POST['backup_id'];
    
    try {
        $backup_manager->deleteBackup($backup_id);
        $success_message = "Backup başarıyla silindi: " . $backup_id;
    } catch (Exception $e) {
        $error_message = "Backup silinirken hata: " . $e->getMessage();
    }
}

// Backup'ları listele
$backups = $backup_manager->listBackups();
$health_status = $backup_manager->checkBackupHealth();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .backup-card {
            transition: transform 0.2s;
        }
        .backup-card:hover {
            transform: translateY(-2px);
        }
        .backup-status-completed { color: #28a745; }
        .backup-status-failed { color: #dc3545; }
        .backup-status-in_progress { color: #ffc107; }
        .backup-type-full { border-left: 4px solid #007bff; }
        .backup-type-incremental { border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-database me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Yenile
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Backup Durumu -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-primary"><?php echo $health_status['total_backups']; ?></h5>
                                <p class="card-text">Toplam Backup</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-success"><?php echo $health_status['healthy_backups']; ?></h5>
                                <p class="card-text">Sağlıklı Backup</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-warning"><?php echo $health_status['old_backups']; ?></h5>
                                <p class="card-text">Eski Backup</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title text-info"><?php echo round($health_status['total_size'] / 1024 / 1024, 2); ?>MB</h5>
                                <p class="card-text">Toplam Boyut</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Yeni Backup Oluştur -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-plus me-2"></i>
                            Yeni Backup Oluştur
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Backup Tipi</label>
                                    <select name="backup_type" class="form-select" required>
                                        <option value="full">Tam Backup</option>
                                        <option value="incremental">İnkremental Backup</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Açıklama</label>
                                    <input type="text" name="description" class="form-control" placeholder="Backup açıklaması">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" name="create_backup" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>
                                        Backup Oluştur
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Backup Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            Backup Listesi
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Backup ID</th>
                                        <th>Tip</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                        <th>Boyut</th>
                                        <th>Açıklama</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td>
                                                <code><?php echo htmlspecialchars($backup['backup_id']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $backup['backup_type'] === 'full' ? 'primary' : 'success'; ?>">
                                                    <?php echo $backup['backup_type'] === 'full' ? 'Tam' : 'İnkremental'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="backup-status-<?php echo $backup['status']; ?>">
                                                    <i class="fas fa-<?php echo $backup['status'] === 'completed' ? 'check-circle' : ($backup['status'] === 'failed' ? 'times-circle' : 'clock'); ?>"></i>
                                                    <?php echo ucfirst($backup['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('d.m.Y H:i:s', strtotime($backup['created_at'])); ?>
                                            </td>
                                            <td>
                                                <?php echo isset($backup['size']) ? round($backup['size'] / 1024 / 1024, 2) . 'MB' : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($backup['description'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info" onclick="showBackupDetails('<?php echo $backup['backup_id']; ?>')" title="Detaylar">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($backup['status'] === 'completed'): ?>
                                                        <button class="btn btn-outline-success" onclick="showRestoreModal('<?php echo $backup['backup_id']; ?>')" title="Geri Yükle">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-danger" onclick="showDeleteModal('<?php echo $backup['backup_id']; ?>')" title="Sil">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Backup Detay Modal -->
    <div class="modal fade" id="backupDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Backup Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="backupDetailContent">
                    <!-- Backup detayları buraya yüklenecek -->
                </div>
            </div>
        </div>
    </div>

    <!-- Geri Yükleme Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Backup Geri Yükle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="restoreForm">
                    <div class="modal-body">
                        <input type="hidden" name="backup_id" id="restoreBackupId">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Bu işlem mevcut verileri değiştirebilir. Devam etmek istediğinizden emin misiniz?
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tables_only" id="tablesOnly">
                            <label class="form-check-label" for="tablesOnly">
                                Sadece veritabanı tablolarını geri yükle (dosyaları değiştirme)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="restore_backup" class="btn btn-success">
                            <i class="fas fa-undo me-1"></i>
                            Geri Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Backup Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteForm">
                    <div class="modal-body">
                        <input type="hidden" name="backup_id" id="deleteBackupId">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Bu backup kalıcı olarak silinecek. Bu işlem geri alınamaz!
                        </div>
                        <p>Backup ID: <code id="deleteBackupIdDisplay"></code></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="delete_backup" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>
                            Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshPage() {
            location.reload();
        }

        function showBackupDetails(backupId) {
            // AJAX ile backup detaylarını getir
            fetch(`ajax/get-backup-details.php?id=${backupId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('backupDetailContent').innerHTML = data.html;
                    new bootstrap.Modal(document.getElementById('backupDetailModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Backup detayları yüklenirken hata oluştu.');
                });
        }

        function showRestoreModal(backupId) {
            document.getElementById('restoreBackupId').value = backupId;
            new bootstrap.Modal(document.getElementById('restoreModal')).show();
        }

        function showDeleteModal(backupId) {
            document.getElementById('deleteBackupId').value = backupId;
            document.getElementById('deleteBackupIdDisplay').textContent = backupId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
