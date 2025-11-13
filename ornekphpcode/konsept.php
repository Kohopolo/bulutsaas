<?php
require_once '../includes/session_security.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('konsept_goruntule', 'Konsept görüntüleme yetkiniz bulunmamaktadır.');

// CSRF koruması
require_once 'csrf_protection.php';

$message = '';
$error = '';

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası. Lütfen tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'add' || $action == 'edit') {
            $baslik = trim($_POST['baslik']);
            $aciklama = trim($_POST['aciklama']);
            $detay = $_POST['detay'];
            $meta_title = trim($_POST['meta_title']);
            $meta_description = trim($_POST['meta_description']);
            $meta_keywords = trim($_POST['meta_keywords']);
            $sira = intval($_POST['sira']);
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            
            // Resim yükleme
            $resim = '';
            $resim_alt = trim($_POST['resim_alt']);
            
            if (isset($_FILES['resim']) && $_FILES['resim']['error'] == 0) {
                $upload_dir = '../assets/images/konsept/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['resim']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'konsept_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['resim']['tmp_name'], $upload_path)) {
                        $resim = '/assets/images/konsept/' . $new_filename;
                    } else {
                        $error = 'Resim yüklenirken hata oluştu.';
                    }
                } else {
                    $error = 'Geçersiz dosya formatı. Sadece JPG, PNG ve WebP dosyaları kabul edilir.';
                }
            }
            
            if (empty($error)) {
                if ($action == 'add') {
                    $sql = "INSERT INTO konsept (baslik, aciklama, detay, resim, resim_alt, meta_title, meta_description, meta_keywords, sira, aktif) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute([$baslik, $aciklama, $detay, $resim, $resim_alt, $meta_title, $meta_description, $meta_keywords, $sira, $aktif])) {
                        $konsept_id = $pdo->lastInsertId();
                        
                        // Konsept özelliklerini ekle
                        if (!empty($_POST['ozellik_baslik'])) {
                            $ozellik_sql = "INSERT INTO konsept_ozellikler (konsept_id, baslik, aciklama, ikon, sira, aktif) VALUES (?, ?, ?, ?, ?, ?)";
                            $ozellik_stmt = $pdo->prepare($ozellik_sql);
                            
                            foreach ($_POST['ozellik_baslik'] as $index => $ozellik_baslik) {
                                if (!empty($ozellik_baslik)) {
                                    $ozellik_aciklama = $_POST['ozellik_aciklama'][$index] ?? '';
                                    $ozellik_ikon = $_POST['ozellik_ikon'][$index] ?? '';
                                    $ozellik_sira = intval($_POST['ozellik_sira'][$index] ?? 0);
                                    $ozellik_aktif = isset($_POST['ozellik_aktif'][$index]) ? 1 : 0;
                                    
                                    $ozellik_stmt->execute([$konsept_id, $ozellik_baslik, $ozellik_aciklama, $ozellik_ikon, $ozellik_sira, $ozellik_aktif]);
                                }
                            }
                        }
                        
                        $message = 'Konsept başarıyla eklendi.';
                    } else {
                        $error = 'Konsept eklenirken hata oluştu.';
                    }
                } else {
                    $id = intval($_POST['id']);
                    
                    // Mevcut resmi al
                    if (empty($resim)) {
                        $current_sql = "SELECT resim FROM konsept WHERE id = ?";
                        $current_stmt = $pdo->prepare($current_sql);
                        $current_stmt->execute([$id]);
                        $current_data = $current_stmt->fetch();
                        $resim = $current_data['resim'] ?? '';
                    }
                    
                    $sql = "UPDATE konsept SET baslik = ?, aciklama = ?, detay = ?, resim = ?, resim_alt = ?, 
                            meta_title = ?, meta_description = ?, meta_keywords = ?, sira = ?, aktif = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute([$baslik, $aciklama, $detay, $resim, $resim_alt, $meta_title, $meta_description, $meta_keywords, $sira, $aktif, $id])) {
                        // Mevcut özellikleri sil
                        $delete_sql = "DELETE FROM konsept_ozellikler WHERE konsept_id = ?";
                        $delete_stmt = $pdo->prepare($delete_sql);
                        $delete_stmt->execute([$id]);
                        
                        // Yeni özellikleri ekle
                        if (!empty($_POST['ozellik_baslik'])) {
                            $ozellik_sql = "INSERT INTO konsept_ozellikler (konsept_id, baslik, aciklama, ikon, sira, aktif) VALUES (?, ?, ?, ?, ?, ?)";
                            $ozellik_stmt = $pdo->prepare($ozellik_sql);
                            
                            foreach ($_POST['ozellik_baslik'] as $index => $ozellik_baslik) {
                                if (!empty($ozellik_baslik)) {
                                    $ozellik_aciklama = $_POST['ozellik_aciklama'][$index] ?? '';
                                    $ozellik_ikon = $_POST['ozellik_ikon'][$index] ?? '';
                                    $ozellik_sira = intval($_POST['ozellik_sira'][$index] ?? 0);
                                    $ozellik_aktif = isset($_POST['ozellik_aktif'][$index]) ? 1 : 0;
                                    
                                    $ozellik_stmt->execute([$id, $ozellik_baslik, $ozellik_aciklama, $ozellik_ikon, $ozellik_sira, $ozellik_aktif]);
                                }
                            }
                        }
                        
                        $message = 'Konsept başarıyla güncellendi.';
                    } else {
                        $error = 'Konsept güncellenirken hata oluştu.';
                    }
                }
            }
        } elseif ($action == 'delete') {
            $id = intval($_POST['id']);
            
            // Önce resmi sil
            $img_sql = "SELECT resim FROM konsept WHERE id = ?";
            $img_stmt = $pdo->prepare($img_sql);
            $img_stmt->execute([$id]);
            $img_data = $img_stmt->fetch();
            
            if ($img_data && !empty($img_data['resim'])) {
                $img_path = '..' . $img_data['resim'];
                if (file_exists($img_path)) {
                    unlink($img_path);
                }
            }
            
            $sql = "DELETE FROM konsept WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$id])) {
                $message = 'Konsept başarıyla silindi.';
            } else {
                $error = 'Konsept silinirken hata oluştu.';
            }
        }
    }
}

// Konseptleri listele
$sql = "SELECT k.*, 
        (SELECT COUNT(*) FROM konsept_ozellikler ko WHERE ko.konsept_id = k.id AND ko.aktif = 1) as ozellik_sayisi
        FROM konsept k ORDER BY k.sira ASC, k.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$konseptler = $stmt->fetchAll();

// Düzenleme için konsept bilgilerini al
$edit_konsept = null;
$edit_ozellikler = [];
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_sql = "SELECT * FROM konsept WHERE id = ?";
    $edit_stmt = $pdo->prepare($edit_sql);
    $edit_stmt->execute([$edit_id]);
    $edit_konsept = $edit_stmt->fetch();
    
    if ($edit_konsept) {
        $ozellik_sql = "SELECT * FROM konsept_ozellikler WHERE konsept_id = ? ORDER BY sira ASC";
        $ozellik_stmt = $pdo->prepare($ozellik_sql);
        $ozellik_stmt->execute([$edit_id]);
        $edit_ozellikler = $ozellik_stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsept Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.css" rel="stylesheet">
    
    <style>
        /* Temel Admin Panel CSS */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Sayfa titremesini önle */
        * {
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            width: 100%;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: #2c3e50;
            color: #ecf0f1;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h4 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .sidebar ul.components {
            padding: 20px 0;
        }
        
        .sidebar ul li {
            margin: 0;
        }
        
        .sidebar ul li a {
            padding: 12px 20px;
            font-size: 14px;
            display: block;
            color: #ecf0f1;
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        
        .sidebar ul li a:hover,
        .sidebar ul li.active > a {
            background: #34495e;
            border-left: 3px solid #3498db;
        }
        
        .wrapper {
            display: flex;
            width: 100%;
        }
        
        #content {
            width: calc(100% - 250px);
            margin-left: 250px;
            min-height: 100vh;
            padding: 20px;
        }
        
        .navbar {
            background: #fff !important;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .form-control {
            border: 1px solid #d1d3e2;
            border-radius: 8px;
            padding: 10px 15px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            font-weight: 600;
            color: #5a5c69;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fc;
        }
        
        .modal-content {
            border-radius: 10px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .badge {
            border-radius: 6px;
        }
        
        .img-thumbnail {
            border-radius: 8px;
        }
        
        .dropdown-menu {
            border-radius: 8px;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .dropdown-item {
            padding: 10px 20px;
        }
        
        .dropdown-item:hover {
            background: #667eea;
            color: white;
        }
        
        /* Summernote Editor Styling */
        .note-editor {
            border-radius: 8px;
            border: 1px solid #d1d3e2;
            position: relative;
            z-index: 1;
        }
        
        .note-toolbar {
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            border-radius: 8px 8px 0 0;
            position: relative;
            z-index: 2;
        }
        
        .note-editing-area {
            border-radius: 0 0 8px 8px;
            position: relative;
            z-index: 1;
        }
        
        .note-editable {
            min-height: 200px;
            padding: 15px;
        }
        
        /* Summernote dropdown fix */
        .note-dropdown-menu {
            z-index: 1050 !important;
        }
        
        /* Summernote popover fix */
        .note-popover {
            z-index: 1050 !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            #content {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div id="content">
            <?php include 'includes/header.php'; ?>
            
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-lightbulb me-2"></i>Konsept Yönetimi</h5>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#konseptModal">
                                    <i class="fas fa-plus me-2"></i>Yeni Konsept
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if ($message): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($message); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($error); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Sıra</th>
                                                <th>Başlık</th>
                                                <th>Açıklama</th>
                                                <th>Resim</th>
                                                <th>Özellik Sayısı</th>
                                                <th>Durum</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($konseptler as $konsept): ?>
                                            <tr>
                                                <td><?php echo $konsept['sira']; ?></td>
                                                <td><?php echo htmlspecialchars($konsept['baslik']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($konsept['aciklama'], 0, 100)) . '...'; ?></td>
                                                <td>
                                                    <?php if ($konsept['resim']): ?>
                                                        <img src="<?php echo htmlspecialchars('../' . $konsept['resim']); ?>" alt="Konsept" style="width: 50px; height: 50px; object-fit: cover;" class="rounded" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTAiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZTwvdGV4dD48L3N2Zz4=';">
                                                    <?php else: ?>
                                                        <span class="text-muted">Resim yok</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $konsept['ozellik_sayisi']; ?> özellik</span>
                                                </td>
                                                <td>
                                                    <?php if ($konsept['aktif']): ?>
                                                        <span class="badge bg-success">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pasif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="?edit=<?php echo $konsept['id']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteKonsept(<?php echo $konsept['id']; ?>)">
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
            </div>
        </div>
    </div>

    <!-- Konsept Modal -->
    <div class="modal fade" id="konseptModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $edit_konsept ? 'Konsept Düzenle' : 'Yeni Konsept Ekle'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_konsept ? 'edit' : 'add'; ?>">
                        <?php if ($edit_konsept): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_konsept['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="baslik" class="form-label">Başlık *</label>
                                    <input type="text" class="form-control" id="baslik" name="baslik" 
                                           value="<?php echo $edit_konsept ? htmlspecialchars($edit_konsept['baslik']) : ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label">Kısa Açıklama *</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" rows="3" required><?php echo $edit_konsept ? htmlspecialchars($edit_konsept['aciklama']) : ''; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="detay" class="form-label">Detaylı Açıklama</label>
                                    <textarea class="form-control summernote" id="detay" name="detay"><?php echo $edit_konsept ? $edit_konsept['detay'] : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="resim" class="form-label">Konsept Resmi</label>
                                    <input type="file" class="form-control" id="resim" name="resim" accept="image/*">
                                    <?php if ($edit_konsept && $edit_konsept['resim']): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars('../' . $edit_konsept['resim']); ?>" alt="Mevcut resim" class="img-thumbnail" style="max-width: 200px;" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlPC90ZXh0Pjwvc3ZnPg==';">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="resim_alt" class="form-label">Resim Alt Metni</label>
                                    <input type="text" class="form-control" id="resim_alt" name="resim_alt" 
                                           value="<?php echo $edit_konsept ? htmlspecialchars($edit_konsept['resim_alt']) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sira" class="form-label">Sıra</label>
                                    <input type="number" class="form-control" id="sira" name="sira" 
                                           value="<?php echo $edit_konsept ? $edit_konsept['sira'] : '0'; ?>">
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="aktif" name="aktif" 
                                           <?php echo (!$edit_konsept || $edit_konsept['aktif']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="aktif">Aktif</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SEO Ayarları -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">SEO Ayarları</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="meta_title" class="form-label">Meta Başlık</label>
                                    <input type="text" class="form-control" id="meta_title" name="meta_title" 
                                           value="<?php echo $edit_konsept ? htmlspecialchars($edit_konsept['meta_title']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="meta_keywords" class="form-label">Meta Anahtar Kelimeler</label>
                                    <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" 
                                           value="<?php echo $edit_konsept ? htmlspecialchars($edit_konsept['meta_keywords']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="meta_description" class="form-label">Meta Açıklama</label>
                                    <textarea class="form-control" id="meta_description" name="meta_description" rows="2"><?php echo $edit_konsept ? htmlspecialchars($edit_konsept['meta_description']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Konsept Özellikleri -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Konsept Özellikleri</h6>
                                <div id="ozellikler">
                                    <?php if (!empty($edit_ozellikler)): ?>
                                        <?php foreach ($edit_ozellikler as $index => $ozellik): ?>
                                            <div class="ozellik-item border p-3 mb-3 rounded">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label class="form-label">Başlık</label>
                                                        <input type="text" class="form-control" name="ozellik_baslik[]" 
                                                               value="<?php echo htmlspecialchars($ozellik['baslik']); ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Açıklama</label>
                                                        <input type="text" class="form-control" name="ozellik_aciklama[]" 
                                                               value="<?php echo htmlspecialchars($ozellik['aciklama']); ?>">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">İkon</label>
                                                        <input type="text" class="form-control" name="ozellik_ikon[]" 
                                                               value="<?php echo htmlspecialchars($ozellik['ikon']); ?>" placeholder="fas fa-star">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Sıra</label>
                                                        <input type="number" class="form-control" name="ozellik_sira[]" 
                                                               value="<?php echo $ozellik['sira']; ?>">
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">Aktif</label>
                                                        <div class="form-check">
                                                            <input type="checkbox" class="form-check-input" name="ozellik_aktif[<?php echo $index; ?>]" 
                                                                   <?php echo $ozellik['aktif'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <label class="form-label">&nbsp;</label>
                                                        <button type="button" class="btn btn-danger btn-sm d-block" onclick="removeOzellik(this)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="addOzellik()">
                                    <i class="fas fa-plus me-2"></i>Özellik Ekle
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_konsept ? 'Güncelle' : 'Ekle'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <!-- jQuery first -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Summernote -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/lang/summernote-tr-TR.min.js"></script>
    
    <script>
        // Sayfa yüklendiğinde çalışacak fonksiyon
        function initializePage() {
            console.log('Konsept sayfası yüklendi');
            
            // jQuery kontrolü
            if (typeof jQuery === 'undefined') {
                console.error('jQuery yüklenmedi!');
                return;
            }
            
            // Summernote editor initialization - sadece bir kez
            if (jQuery('.summernote').length > 0 && !jQuery('.summernote').hasClass('note-editor')) {
                try {
                    jQuery('.summernote').summernote({
                height: 200,
                lang: 'tr-TR',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
                    console.log('Summernote editor başlatıldı');
                } catch (error) {
                    console.error('Summernote editor hatası:', error);
                }
            }
            
            // Modal show for edit mode
            <?php if ($edit_konsept): ?>
                setTimeout(function() {
                    const modal = document.getElementById('konseptModal');
                    if (modal) {
                        try {
                            new bootstrap.Modal(modal).show();
                            console.log('Edit modal açıldı');
                        } catch (error) {
                            console.error('Modal açma hatası:', error);
                        }
                    }
                }, 500);
            <?php endif; ?>
            
            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-success')) {
                    setTimeout(function() {
                        try {
                            const bsAlert = new bootstrap.Alert(alert);
                            bsAlert.close();
                        } catch (error) {
                            console.error('Alert kapatma hatası:', error);
                        }
                    }, 3000);
                }
            });
        }
        
        // jQuery document ready
        jQuery(document).ready(function() {
            initializePage();
        });
        
        // Vanilla JavaScript DOMContentLoaded (yedek)
        document.addEventListener('DOMContentLoaded', function() {
            // jQuery yüklenmemişse vanilla JS kullan
            if (typeof jQuery === 'undefined') {
                console.log('jQuery yüklenmedi, vanilla JS kullanılıyor');
                initializePageWithoutSummernote();
            }
        });
        
        // jQuery olmadan sayfa işlevselliği
        function initializePageWithoutSummernote() {
            console.log('jQuery olmadan sayfa başlatılıyor');
            
            // Modal show for edit mode
            <?php if ($edit_konsept): ?>
                setTimeout(function() {
                    const modal = document.getElementById('konseptModal');
                    if (modal) {
                        try {
                            new bootstrap.Modal(modal).show();
                            console.log('Edit modal açıldı');
                        } catch (error) {
                            console.error('Modal açma hatası:', error);
                        }
                    }
                }, 500);
            <?php endif; ?>
            
            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-success')) {
                    setTimeout(function() {
                        try {
                            const bsAlert = new bootstrap.Alert(alert);
                            bsAlert.close();
                        } catch (error) {
                            console.error('Alert kapatma hatası:', error);
                        }
                    }, 3000);
                }
            });
        }
        
        function addOzellik() {
            const ozelliklerDiv = document.getElementById('ozellikler');
            if (!ozelliklerDiv) {
                console.error('Özellikler div bulunamadı');
                return;
            }
            
            const index = ozelliklerDiv.children.length;
            
            const ozellikHtml = `
                <div class="ozellik-item border p-3 mb-3 rounded">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="ozellik_baslik[]">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Açıklama</label>
                            <input type="text" class="form-control" name="ozellik_aciklama[]">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">İkon</label>
                            <input type="text" class="form-control" name="ozellik_ikon[]" placeholder="fas fa-star">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Sıra</label>
                            <input type="number" class="form-control" name="ozellik_sira[]" value="0">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Aktif</label>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="ozellik_aktif[${index}]" checked>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm d-block" onclick="removeOzellik(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            ozelliklerDiv.insertAdjacentHTML('beforeend', ozellikHtml);
        }
        
        function removeOzellik(button) {
            if (button && button.closest) {
            button.closest('.ozellik-item').remove();
            }
        }
        
        function deleteKonsept(id) {
            if (confirm('Bu konsepti silmek istediğinizden emin misiniz?')) {
                const deleteIdInput = document.getElementById('deleteId');
                const deleteForm = document.getElementById('deleteForm');
                
                if (deleteIdInput && deleteForm) {
                    deleteIdInput.value = id;
                    deleteForm.submit();
                } else {
                    console.error('Delete form elementleri bulunamadı');
                }
            }
        }
    </script>
</body>
</html>