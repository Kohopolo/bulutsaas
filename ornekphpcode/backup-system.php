<?php
/**
 * Yedekleme Sistemi Dashboard
 * Otomatik veri yedekleme ve kurtarma
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('sistem_yonetimi')) {
    $_SESSION['error_message'] = 'Sistem yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';
$backup_results = [];

// Yedekleme işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'create_backup') {
            $backup_results = createSystemBackup();
            $success_message = 'Sistem yedeği oluşturuldu.';
        }
        
        if ($action == 'create_database_backup') {
            $backup_results['database'] = createDatabaseBackup();
        }
        
        if ($action == 'create_files_backup') {
            $backup_results['files'] = createFilesBackup();
        }
        
        if ($action == 'restore_backup') {
            $backup_file = sanitizeString($_POST['backup_file'] ?? '');
            $backup_results['restore'] = restoreBackup($backup_file);
        }
        
        if ($action == 'schedule_backup') {
            $backup_results['schedule'] = scheduleBackup();
        }
        
        if ($action == 'cleanup_old_backups') {
            $backup_results['cleanup'] = cleanupOldBackups();
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Sistem yedeği oluştur
 */
function createSystemBackup()
{
    $results = [];
    
    // 1. Veritabanı yedeği
    $results['database'] = createDatabaseBackup();
    
    // 2. Dosya yedeği
    $results['files'] = createFilesBackup();
    
    // 3. Yedekleme kaydı
    $results['record'] = recordBackupOperation('full_system', 'completed');
    
    return $results;
}

/**
 * Veritabanı yedeği oluştur
 */
function createDatabaseBackup()
{
    global $pdo;
    
    $results = [];
    
    try {
        // Yedekleme dizini
        $backup_dir = '../backups/database/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        // Yedekleme dosya adı
        $backup_filename = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = $backup_dir . $backup_filename;
        
        // Veritabanı bilgilerini al
        $db_config = include '../config/database.php';
        $host = $db_config['host'];
        $dbname = $db_config['dbname'];
        $username = $db_config['username'];
        $password = $db_config['password'];
        
        // mysqldump komutu
        $command = "mysqldump -h {$host} -u {$username} -p{$password} {$dbname} > {$backup_path}";
        
        // Komutu çalıştır
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($backup_path)) {
            $file_size = filesize($backup_path);
            $file_size_mb = round($file_size / 1024 / 1024, 2);
            
            $results['status'] = 'success';
            $results['filename'] = $backup_filename;
            $results['size_mb'] = $file_size_mb;
            $results['message'] = "Veritabanı yedeği oluşturuldu: {$backup_filename} ({$file_size_mb} MB)";
            
            // Yedekleme kaydı
            recordBackupOperation('database', 'completed', $backup_filename, $file_size);
            
        } else {
            $results['status'] = 'error';
            $results['message'] = 'Veritabanı yedeği oluşturulamadı';
        }
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Veritabanı yedekleme hatası: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Dosya yedeği oluştur
 */
function createFilesBackup()
{
    $results = [];
    
    try {
        // Yedekleme dizini
        $backup_dir = '../backups/files/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        // Yedekleme dosya adı
        $backup_filename = 'files_backup_' . date('Y-m-d_H-i-s') . '.zip';
        $backup_path = $backup_dir . $backup_filename;
        
        // Yedeklenecek dizinler
        $directories_to_backup = [
            '../uploads/',
            '../assets/',
            '../config/',
            '../includes/',
            '../admin/',
            '../api/',
            '../mobile/',
            '../templates/'
        ];
        
        // Geçici dizin oluştur
        $temp_dir = '../backups/temp/' . uniqid();
        mkdir($temp_dir, 0755, true);
        
        $copied_files = 0;
        
        // Dosyaları kopyala
        foreach ($directories_to_backup as $dir) {
            if (is_dir($dir)) {
                $dest_dir = $temp_dir . '/' . basename($dir);
                copyDirectory($dir, $dest_dir);
                $copied_files++;
            }
        }
        
        // ZIP oluştur
        $zip = new ZipArchive();
        if ($zip->open($backup_path, ZipArchive::CREATE) === TRUE) {
            addDirectoryToZip($temp_dir, $zip, '');
            $zip->close();
            
            // Geçici dizini sil
            removeDirectory($temp_dir);
            
            $file_size = filesize($backup_path);
            $file_size_mb = round($file_size / 1024 / 1024, 2);
            
            $results['status'] = 'success';
            $results['filename'] = $backup_filename;
            $results['size_mb'] = $file_size_mb;
            $results['copied_directories'] = $copied_files;
            $results['message'] = "Dosya yedeği oluşturuldu: {$backup_filename} ({$file_size_mb} MB)";
            
            // Yedekleme kaydı
            recordBackupOperation('files', 'completed', $backup_filename, $file_size);
            
        } else {
            $results['status'] = 'error';
            $results['message'] = 'ZIP dosyası oluşturulamadı';
        }
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Dosya yedekleme hatası: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Yedekten geri yükleme
 */
function restoreBackup($backup_file)
{
    $results = [];
    
    try {
        $backup_path = '../backups/' . $backup_file;
        
        if (!file_exists($backup_path)) {
            throw new Exception('Yedekleme dosyası bulunamadı');
        }
        
        if (strpos($backup_file, 'db_backup_') === 0) {
            // Veritabanı yedeği
            $results = restoreDatabaseBackup($backup_path);
        } elseif (strpos($backup_file, 'files_backup_') === 0) {
            // Dosya yedeği
            $results = restoreFilesBackup($backup_path);
        } else {
            throw new Exception('Geçersiz yedekleme dosyası');
        }
        
        // Geri yükleme kaydı
        recordBackupOperation('restore', 'completed', $backup_file);
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Geri yükleme hatası: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Veritabanı yedeğini geri yükle
 */
function restoreDatabaseBackup($backup_path)
{
    global $pdo;
    
    $results = [];
    
    try {
        // Veritabanı bilgilerini al
        $db_config = include '../config/database.php';
        $host = $db_config['host'];
        $dbname = $db_config['dbname'];
        $username = $db_config['username'];
        $password = $db_config['password'];
        
        // mysql komutu
        $command = "mysql -h {$host} -u {$username} -p{$password} {$dbname} < {$backup_path}";
        
        // Komutu çalıştır
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            $results['status'] = 'success';
            $results['message'] = 'Veritabanı başarıyla geri yüklendi';
        } else {
            $results['status'] = 'error';
            $results['message'] = 'Veritabanı geri yükleme başarısız';
        }
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Veritabanı geri yükleme hatası: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Dosya yedeğini geri yükle
 */
function restoreFilesBackup($backup_path)
{
    $results = [];
    
    try {
        $zip = new ZipArchive();
        if ($zip->open($backup_path) === TRUE) {
            $zip->extractTo('../');
            $zip->close();
            
            $results['status'] = 'success';
            $results['message'] = 'Dosyalar başarıyla geri yüklendi';
        } else {
            $results['status'] = 'error';
            $results['message'] = 'ZIP dosyası açılamadı';
        }
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Dosya geri yükleme hatası: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Yedekleme zamanlaması
 */
function scheduleBackup()
{
    $results = [];
    
    try {
        // Cron job dosyası oluştur
        $cron_content = "#!/bin/bash\n";
        $cron_content .= "# Otomatik yedekleme cron job\n";
        $cron_content .= "0 2 * * * /usr/bin/php " . realpath('../cron/backup-system.php') . " >/dev/null 2>&1\n";
        
        $cron_file = '../cron/backup-schedule.sh';
        file_put_contents($cron_file, $cron_content);
        chmod($cron_file, 0755);
        
        $results['status'] = 'success';
        $results['message'] = 'Yedekleme zamanlaması oluşturuldu';
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Zamanlama hatası: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Eski yedekleri temizle
 */
function cleanupOldBackups()
{
    $results = [];
    
    try {
        $backup_dirs = ['../backups/database/', '../backups/files/'];
        $cleaned_files = 0;
        $freed_space = 0;
        
        foreach ($backup_dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $file_age = time() - filemtime($file);
                        
                        // 30 günden eski dosyaları sil
                        if ($file_age > (30 * 24 * 60 * 60)) {
                            $file_size = filesize($file);
                            unlink($file);
                            $cleaned_files++;
                            $freed_space += $file_size;
                        }
                    }
                }
            }
        }
        
        $freed_space_mb = round($freed_space / 1024 / 1024, 2);
        
        $results['status'] = 'success';
        $results['cleaned_files'] = $cleaned_files;
        $results['freed_space_mb'] = $freed_space_mb;
        $results['message'] = "{$cleaned_files} eski yedek dosyası silindi, {$freed_space_mb} MB alan kazanıldı";
        
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'Temizleme hatası: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Yedekleme kaydı oluştur
 */
function recordBackupOperation($type, $status, $filename = null, $file_size = null)
{
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO backup_operations (
                backup_type, status, filename, file_size, 
                created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $type,
            $status,
            $filename,
            $file_size,
            $_SESSION['user_id'] ?? 1
        ]);
        
        return [
            'status' => 'success',
            'message' => 'Yedekleme kaydı oluşturuldu'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Kayıt hatası: ' . $e->getMessage()
        ];
    }
}

/**
 * Mevcut yedekleri listele
 */
function getBackupList()
{
    $backups = [];
    
    $backup_dirs = [
        'database' => '../backups/database/',
        'files' => '../backups/files/'
    ];
    
    foreach ($backup_dirs as $type => $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $backups[] = [
                        'type' => $type,
                        'filename' => basename($file),
                        'size' => filesize($file),
                        'created' => filemtime($file),
                        'path' => $file
                    ];
                }
            }
        }
    }
    
    // Tarihe göre sırala
    usort($backups, function($a, $b) {
        return $b['created'] - $a['created'];
    });
    
    return $backups;
}

/**
 * Dizin kopyalama
 */
function copyDirectory($src, $dst)
{
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            
            if (is_dir($src_file)) {
                copyDirectory($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
    }
    closedir($dir);
}

/**
 * ZIP'e dizin ekleme
 */
function addDirectoryToZip($dir, $zip, $zip_dir)
{
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $file_path = $dir . '/' . $file;
                $zip_path = $zip_dir . $file;
                
                if (is_dir($file_path)) {
                    $zip->addEmptyDir($zip_path);
                    addDirectoryToZip($file_path, $zip, $zip_path . '/');
                } else {
                    $zip->addFile($file_path, $zip_path);
                }
            }
        }
    }
}

/**
 * Dizin silme
 */
function removeDirectory($dir)
{
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $file_path = $dir . '/' . $file;
            if (is_dir($file_path)) {
                removeDirectory($file_path);
            } else {
                unlink($file_path);
            }
        }
        rmdir($dir);
    }
}

// Mevcut yedekleri al
$backup_list = getBackupList();

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yedekleme Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .backup-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .backup-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .backup-card.database { border-left-color: #0d6efd; }
        .backup-card.files { border-left-color: #198754; }
        .backup-card.system { border-left-color: #6f42c1; }
        .backup-card.restore { border-left-color: #fd7e14; }
        
        .backup-result {
            padding: 8px 12px;
            border-radius: 4px;
            margin: 4px 0;
            font-size: 0.9rem;
        }
        .backup-result.success {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        .backup-result.error {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }
        .backup-result.warning {
            background-color: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }
        .backup-result.info {
            background-color: #d1ecf1;
            color: #055160;
            border: 1px solid #bee5eb;
        }
        
        .backup-summary {
            background: linear-gradient(135deg, #6f42c1 0%, #fd7e14 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .backup-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .backup-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .backup-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .backup-type-badge.database { background-color: #cfe2ff; color: #0d6efd; }
        .backup-type-badge.files { background-color: #d1e7dd; color: #198754; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-database me-2"></i>Yedekleme Sistemi
                        <small class="text-muted">Otomatik veri yedekleme ve kurtarma</small>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                                <i class="fas fa-plus"></i> Yedek Oluştur
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-tools"></i> Yedekleme Seçenekleri
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="createSpecificBackup('database')">
                                    <i class="fas fa-database"></i> Veritabanı Yedeği
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="createSpecificBackup('files')">
                                    <i class="fas fa-file"></i> Dosya Yedeği
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="scheduleBackup()">
                                    <i class="fas fa-clock"></i> Zamanlama Ayarla
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="cleanupOldBackups()">
                                    <i class="fas fa-trash"></i> Eski Yedekleri Temizle
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Yedekleme Özeti -->
                <?php if (!empty($backup_results)): ?>
                <div class="backup-summary">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><i class="fas fa-database me-2"></i>Yedekleme İşlemi Tamamlandı</h4>
                            <p class="mb-0">Sistem yedekleme işlemi başarıyla gerçekleştirildi.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="h3 mb-0">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="mt-2">
                                <strong><?php echo count($backup_results); ?></strong>
                                <small>İşlem Tamamlandı</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Yedekleme Sonuçları -->
                <?php if (!empty($backup_results)): ?>
                <div class="row">
                    <?php foreach ($backup_results as $category => $result): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card backup-card <?php echo $category; ?>">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">
                                    <i class="fas fa-<?php echo getBackupCategoryIcon($category); ?> me-2"></i>
                                    <?php echo getBackupCategoryTitle($category); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="backup-result <?php echo $result['status']; ?>">
                                    <i class="fas fa-<?php echo getBackupStatusIcon($result['status']); ?> me-2"></i>
                                    <?php echo $result['message']; ?>
                                </div>
                                <?php if (isset($result['filename'])): ?>
                                <div class="backup-result info">
                                    <i class="fas fa-file me-2"></i>
                                    Dosya: <?php echo $result['filename']; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($result['size_mb'])): ?>
                                <div class="backup-result info">
                                    <i class="fas fa-weight me-2"></i>
                                    Boyut: <?php echo $result['size_mb']; ?> MB
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Mevcut Yedekler -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="m-0">
                                    <i class="fas fa-archive me-2"></i>Mevcut Yedekler
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($backup_list)): ?>
                                <div class="row">
                                    <?php foreach ($backup_list as $backup): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="backup-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="backup-type-badge <?php echo $backup['type']; ?>">
                                                    <?php echo ucfirst($backup['type']); ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?php echo date('d.m.Y H:i', $backup['created']); ?>
                                                </small>
                                            </div>
                                            <h6 class="mb-2"><?php echo $backup['filename']; ?></h6>
                                            <p class="mb-2 text-muted">
                                                <i class="fas fa-weight me-1"></i>
                                                <?php echo round($backup['size'] / 1024 / 1024, 2); ?> MB
                                            </p>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="downloadBackup('<?php echo $backup['filename']; ?>')">
                                                    <i class="fas fa-download"></i> İndir
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="restoreBackup('<?php echo $backup['filename']; ?>')">
                                                    <i class="fas fa-undo"></i> Geri Yükle
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteBackup('<?php echo $backup['filename']; ?>')">
                                                    <i class="fas fa-trash"></i> Sil
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                                    <h5>Henüz yedek bulunmuyor</h5>
                                    <p class="text-muted">İlk yedeğinizi oluşturmak için "Yedek Oluştur" butonuna tıklayın.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Yedek Oluştur Modal -->
    <div class="modal fade" id="createBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yedek Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_backup">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu işlem sistem yedeğini oluşturacak ve verilerinizi güvence altına alacaktır.
                        </div>
                        
                        <h6>Yedeklenecek Veriler:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-primary me-2"></i>Veritabanı (Tüm tablolar ve veriler)</li>
                            <li><i class="fas fa-check text-success me-2"></i>Dosyalar (Uploads, assets, config, includes)</li>
                            <li><i class="fas fa-check text-info me-2"></i>Admin paneli ve API dosyaları</li>
                            <li><i class="fas fa-check text-warning me-2"></i>Mobil uygulamalar ve şablonlar</li>
                        </ul>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeLogs" name="include_logs">
                            <label class="form-check-label" for="includeLogs">
                                Log dosyalarını da dahil et
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Yedek Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function createSpecificBackup(type) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'create_' + type + '_backup';
            form.appendChild(actionInput);
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo generateCSRFToken(); ?>';
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function scheduleBackup() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'schedule_backup';
            form.appendChild(actionInput);
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo generateCSRFToken(); ?>';
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function cleanupOldBackups() {
            if (confirm('Eski yedekler silinecek. Devam etmek istiyor musunuz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'cleanup_old_backups';
                form.appendChild(actionInput);
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo generateCSRFToken(); ?>';
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function downloadBackup(filename) {
            window.open('download-backup.php?file=' + encodeURIComponent(filename), '_blank');
        }
        
        function restoreBackup(filename) {
            if (confirm('Bu yedek geri yüklenecek. Mevcut veriler değişebilir. Devam etmek istiyor musunuz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'restore_backup';
                form.appendChild(actionInput);
                
                const fileInput = document.createElement('input');
                fileInput.type = 'hidden';
                fileInput.name = 'backup_file';
                fileInput.value = filename;
                form.appendChild(fileInput);
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo generateCSRFToken(); ?>';
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteBackup(filename) {
            if (confirm('Bu yedek silinecek. Devam etmek istiyor musunuz?')) {
                // AJAX ile silme işlemi
                fetch('delete-backup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'file=' + encodeURIComponent(filename) + '&csrf_token=<?php echo generateCSRFToken(); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Silme işlemi başarısız: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>

<?php
// Yardımcı fonksiyonlar

function getBackupCategoryIcon($category)
{
    $icons = [
        'database' => 'database',
        'files' => 'file',
        'system' => 'server',
        'restore' => 'undo',
        'record' => 'clipboard-list'
    ];
    
    return $icons[$category] ?? 'archive';
}

function getBackupCategoryTitle($category)
{
    $titles = [
        'database' => 'Veritabanı Yedeği',
        'files' => 'Dosya Yedeği',
        'system' => 'Sistem Yedeği',
        'restore' => 'Geri Yükleme',
        'record' => 'Yedekleme Kaydı'
    ];
    
    return $titles[$category] ?? ucfirst($category);
}

function getBackupStatusIcon($status)
{
    $icons = [
        'success' => 'check-circle',
        'error' => 'times-circle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle'
    ];
    
    return $icons[$status] ?? 'question-circle';
}
?>

