<?php
// Türkçe karakter desteği
header('Content-Type: text/html; charset=utf-8');
ini_set('default_charset', 'utf-8');
mb_internal_encoding('UTF-8');

require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'csrf_protection.php';

// Session başlat
startSecureSession();

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('galeri_goruntule', 'Galeri görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Resim ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası! Lütfen sayfayı yenileyin.';
    } else {
        $baslik = sanitizeString($_POST['baslik']);
        $aciklama = sanitizeString($_POST['aciklama']);
        $resim_url = sanitizeString($_POST['resim_url']);
        $kategori = sanitizeString($_POST['kategori']);
        $sira_no = (int)($_POST['sira_no'] ?? 1);
        $durum = sanitizeString($_POST['durum']);

        if (empty($baslik) || empty($resim_url) || empty($kategori)) {
            $error_message = 'Başlık, resim URL ve kategori alanları zorunludur!';
        } else {
            $sql = "INSERT INTO galeri (baslik, aciklama, resim_url, kategori, sira_no, durum) VALUES (?, ?, ?, ?, ?, ?)";
            if (executeQuery($sql, [$baslik, $aciklama, $resim_url, $kategori, $sira_no, $durum])) {
                $success_message = 'Resim başarıyla eklendi!';
            } else {
                $error_message = 'Resim eklenirken bir hata oluştu!';
            }
        }
    }
}

// Resim düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası! Lütfen sayfayı yenileyin.';
    } else {
        $id = (int)$_POST['id'];
        $baslik = sanitizeString($_POST['baslik']);
        $aciklama = sanitizeString($_POST['aciklama']);
        $resim_url = sanitizeString($_POST['resim_url']);
        $kategori = sanitizeString($_POST['kategori']);
        $sira_no = (int)($_POST['sira_no'] ?? 1);
        $durum = sanitizeString($_POST['durum']);

        if (empty($baslik) || empty($resim_url) || empty($kategori)) {
            $error_message = 'Başlık, resim URL ve kategori alanları zorunludur!';
        } else {
            $sql = "UPDATE galeri SET baslik = ?, aciklama = ?, resim_url = ?, kategori = ?, sira_no = ?, durum = ? WHERE id = ?";
            if (executeQuery($sql, [$baslik, $aciklama, $resim_url, $kategori, $sira_no, $durum, $id])) {
                $success_message = 'Resim başarıyla güncellendi!';
            } else {
                $error_message = 'Resim güncellenirken bir hata oluştu!';
            }
        }
    }
}

// Resim silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Güvenlik hatası! Lütfen sayfayı yenileyin.';
    } else {
        $id = (int)$_POST['id'];
        $sql = "DELETE FROM galeri WHERE id = ?";
        if (executeQuery($sql, [$id])) {
            $success_message = 'Resim başarıyla silindi!';
        } else {
            $error_message = 'Resim silinirken bir hata oluştu!';
        }
    }
}

// Düzenleme için resim bilgilerini al
$edit_resim = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $sql = "SELECT * FROM galeri WHERE id = ?";
    $edit_resim = fetchOne($sql, [$edit_id]);
}

// Galeri resimlerini al
$sql = "SELECT * FROM galeri ORDER BY sira_no ASC, id DESC";
$galeri_resimleri = fetchAll($sql);

// Kategoriler
$kategoriler = [
    'otel' => 'Otel',
    'oda' => 'Odalar',
    'restoran' => 'Restoran',
    'aktivite' => 'Aktiviteler',
    'diger' => 'Diğer'
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Yönetimi - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Temel Admin Panel CSS */
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            position: relative;
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
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                </button>
                
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h1 class="h3 mb-0">Galeri Yönetimi</h1>
                                <button type="button" class="btn btn-primary" onclick="openAddImageModal()">
                                    <i class="fas fa-plus me-2"></i>Yeni Resim Ekle
                                </button>
                            </div>

                            <?php if ($success_message): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($success_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($error_message): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($error_message); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <!-- Galeri Listesi -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Galeri Resimleri</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($galeri_resimleri)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-images fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">Henüz galeri resmi eklenmemiş.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($galeri_resimleri as $resim): ?>
                                                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                                    <div class="card h-100">
                                                        <div class="position-relative">
                                                            <img src="<?php echo htmlspecialchars('../' . ($resim['resim_url'] ?? '')); ?>" 
                                                                 class="card-img-top" 
                                                                 alt="<?php echo htmlspecialchars($resim['baslik'] ?? ''); ?>"
                                                                 style="height: 200px; object-fit: cover;"
                                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlPC90ZXh0Pjwvc3ZnPg=='; this.alt='Resim yüklenemedi';">
                                                            <div class="position-absolute top-0 end-0 p-2">
                                                                <span class="badge bg-<?php echo $resim['durum'] === 'aktif' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo $resim['durum'] === 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="card-body d-flex flex-column">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($resim['baslik'] ?? ''); ?></h6>
                                                            <p class="card-text text-muted small flex-grow-1">
                                                                <?php echo htmlspecialchars($resim['aciklama'] ?? 'Açıklama yok'); ?>
                                                            </p>
                                                            <div class="mb-2">
                                                                <small class="text-muted">
                                                                    <i class="fas fa-tag me-1"></i>
                                                                    <?php echo htmlspecialchars($kategoriler[$resim['kategori'] ?? ''] ?? ($resim['kategori'] ?? '')); ?>
                                                                </small>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-sort-numeric-up me-1"></i>
                                                                    Sıra: <?php echo $resim['sira_no']; ?>
                                                                </small>
                                                            </div>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <a href="?edit=<?php echo $resim['id']; ?>" class="btn btn-outline-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-outline-danger" 
                                                                        onclick="deleteImage(<?php echo $resim['id']; ?>, '<?php echo htmlspecialchars($resim['baslik'] ?? ''); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Resim Ekleme/Düzenleme Modal -->
    <div class="modal fade" id="addImageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $edit_resim ? 'Resim Düzenle' : 'Yeni Resim Ekle'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_resim ? 'edit' : 'add'; ?>">
                        <?php if ($edit_resim): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_resim['id']; ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="baslik" class="form-label">Başlık *</label>
                                    <input type="text" class="form-control" id="baslik" name="baslik" 
                                           value="<?php echo $edit_resim ? htmlspecialchars($edit_resim['baslik'] ?? '') : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="sira_no" class="form-label">Sıra No</label>
                                    <input type="number" class="form-control" id="sira_no" name="sira_no" 
                                           value="<?php echo $edit_resim ? $edit_resim['sira_no'] : '1'; ?>" min="1">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?php echo $edit_resim ? htmlspecialchars($edit_resim['aciklama'] ?? '') : ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="resim_url" class="form-label">Resim URL *</label>
                            <input type="url" class="form-control" id="resim_url" name="resim_url" 
                                   value="<?php echo $edit_resim ? htmlspecialchars($edit_resim['resim_url'] ?? '') : ''; ?>" 
                                   placeholder="https://example.com/image.jpg" required>
                            <div class="form-text">Resim URL'sini buraya yapıştırın (JPG, PNG, WebP formatları desteklenir)</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kategori" class="form-label">Kategori *</label>
                                    <select class="form-select" id="kategori" name="kategori" required>
                                        <option value="">Kategori Seçin</option>
                                        <?php foreach ($kategoriler as $kod => $ad): ?>
                                            <option value="<?php echo $kod; ?>" 
                                                    <?php echo ($edit_resim && $edit_resim['kategori'] === $kod) ? 'selected' : ''; ?>>
                                                <?php echo $ad; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="durum" class="form-label">Durum</label>
                                    <select class="form-select" id="durum" name="durum">
                                        <option value="aktif" <?php echo (!$edit_resim || $edit_resim['durum'] === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="pasif" <?php echo ($edit_resim && $edit_resim['durum'] === 'pasif') ? 'selected' : ''; ?>>Pasif</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Resim Önizleme -->
                        <div class="mb-3">
                            <label class="form-label">Resim Önizleme</label>
                            <div id="imagePreview" class="border rounded p-3 text-center" style="min-height: 200px;">
                                <?php if ($edit_resim && $edit_resim['resim_url']): ?>
                                    <img src="<?php echo htmlspecialchars($edit_resim['resim_url'] ?? ''); ?>" 
                                         class="img-fluid rounded" style="max-height: 200px;"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlPC90ZXh0Pjwvc3ZnPg=='; this.alt='Resim yüklenemedi';">
                                <?php else: ?>
                                    <i class="fas fa-image fa-3x text-muted mb-2"></i>
                                    <p class="text-muted">Resim URL'si girildiğinde önizleme burada görünecek</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_resim ? 'Güncelle' : 'Ekle'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resim Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu resmi silmek istediğinizden emin misiniz?</p>
                    <p><strong id="deleteImageTitle"></strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteImageId">
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global fonksiyonları tanımla
        function openAddImageModal() {
            console.log('openAddImageModal called');
            const modal = document.getElementById('addImageModal');
            if (modal) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    new bootstrap.Modal(modal).show();
                } else {
                    // Manuel modal açma
                    modal.style.display = 'block';
                    modal.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    // Modal kapatma fonksiyonu ekle
                    const closeModal = function() {
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                    };
                    
                    // ESC tuşu ile kapatma
                    const handleEscape = function(e) {
                        if (e.key === 'Escape') {
                            closeModal();
                            document.removeEventListener('keydown', handleEscape);
                        }
                    };
                    document.addEventListener('keydown', handleEscape);
                    
                    // Overlay'e tıklayınca kapatma
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeModal();
                        }
                    });
                }
            } else {
                console.error('Add image modal bulunamadı');
            }
        }

        function deleteImage(id, title) {
            console.log('deleteImage called with:', id, title);
            const deleteIdInput = document.getElementById('deleteImageId');
            const deleteTitleElement = document.getElementById('deleteImageTitle');
            const deleteModal = document.getElementById('deleteModal');
            
            if (deleteIdInput && deleteTitleElement && deleteModal) {
                deleteIdInput.value = id;
                deleteTitleElement.textContent = title;
                
                // Bootstrap varsa kullan, yoksa manuel modal aç
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    new bootstrap.Modal(deleteModal).show();
                } else {
                    // Manuel modal açma
                    deleteModal.style.display = 'block';
                    deleteModal.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    // Modal kapatma fonksiyonu ekle
                    const closeModal = function() {
                        deleteModal.style.display = 'none';
                        deleteModal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                    };
                    
                    // ESC tuşu ile kapatma
                    const handleEscape = function(e) {
                        if (e.key === 'Escape') {
                            closeModal();
                            document.removeEventListener('keydown', handleEscape);
                        }
                    };
                    document.addEventListener('keydown', handleEscape);
                    
                    // Overlay'e tıklayınca kapatma
                    deleteModal.addEventListener('click', function(e) {
                        if (e.target === deleteModal) {
                            closeModal();
                        }
                    });
                }
            } else {
                console.error('Delete modal elementleri bulunamadı');
                console.log('deleteIdInput:', deleteIdInput);
                console.log('deleteTitleElement:', deleteTitleElement);
                console.log('deleteModal:', deleteModal);
            }
        }

        function isValidImageUrl(url) {
            return /\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i.test(url) || url.includes('unsplash.com') || url.includes('images.');
        }

        function showImageError() {
            const preview = document.getElementById('imagePreview');
            if (preview) {
                preview.innerHTML = `
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlPC90ZXh0Pjwvc3ZnPg==" class="img-fluid rounded" style="max-height: 200px;" alt="Resim yüklenemedi">
                    <p class="text-warning mt-2">Resim yüklenemedi. URL'yi kontrol edin.</p>
                `;
            }
        }

        // Resim URL değiştiğinde önizleme güncelle
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Galeri sayfası yüklendi');
            
            const resimUrlInput = document.getElementById('resim_url');
            if (resimUrlInput) {
                resimUrlInput.addEventListener('input', function() {
                    const url = this.value;
                    const preview = document.getElementById('imagePreview');
                    
                    if (preview) {
                        if (url && isValidImageUrl(url)) {
                            preview.innerHTML = `<img src="${url}" class="img-fluid rounded" style="max-height: 200px;" onerror="showImageError()">`;
                        } else {
                            preview.innerHTML = `
                                <i class="fas fa-image fa-3x text-muted mb-2"></i>
                                <p class="text-muted">Resim URL'si girildiğinde önizleme burada görünecek</p>
                            `;
                        }
                    }
                });
            }
            
            // Düzenleme modunda modal'ı aç
            <?php if ($edit_resim): ?>
                setTimeout(function() {
                    const modal = document.getElementById('addImageModal');
                    if (modal) {
                        new bootstrap.Modal(modal).show();
                    }
                }, 100);
            <?php endif; ?>
            
            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-success')) {
                    setTimeout(function() {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 3000);
                }
            });
        });

        function isValidImageUrl(url) {
            return /\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i.test(url) || url.includes('unsplash.com') || url.includes('images.');
        }

        function showImageError() {
            const preview = document.getElementById('imagePreview');
            if (preview) {
                preview.innerHTML = `
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlPC90ZXh0Pjwvc3ZnPg==" class="img-fluid rounded" style="max-height: 200px;" alt="Resim yüklenemedi">
                    <p class="text-warning mt-2">Resim yüklenemedi. URL'yi kontrol edin.</p>
                `;
            }
        }

        function openAddImageModal() {
            console.log('openAddImageModal called');
            const modal = document.getElementById('addImageModal');
            if (modal) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    new bootstrap.Modal(modal).show();
                } else {
                    // Manuel modal açma
                    modal.style.display = 'block';
                    modal.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    // Modal kapatma fonksiyonu ekle
                    const closeModal = function() {
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                    };
                    
                    // ESC tuşu ile kapatma
                    const handleEscape = function(e) {
                        if (e.key === 'Escape') {
                            closeModal();
                            document.removeEventListener('keydown', handleEscape);
                        }
                    };
                    document.addEventListener('keydown', handleEscape);
                    
                    // Overlay'e tıklayınca kapatma
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeModal();
                        }
                    });
                }
            } else {
                console.error('Add image modal bulunamadı');
            }
        }

        function deleteImage(id, title) {
            console.log('deleteImage called with:', id, title);
            const deleteIdInput = document.getElementById('deleteImageId');
            const deleteTitleElement = document.getElementById('deleteImageTitle');
            const deleteModal = document.getElementById('deleteModal');
            
            if (deleteIdInput && deleteTitleElement && deleteModal) {
                deleteIdInput.value = id;
                deleteTitleElement.textContent = title;
                
                // Bootstrap varsa kullan, yoksa manuel modal aç
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    new bootstrap.Modal(deleteModal).show();
                } else {
                    // Manuel modal açma
                    deleteModal.style.display = 'block';
                    deleteModal.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    // Modal kapatma fonksiyonu ekle
                    const closeModal = function() {
                        deleteModal.style.display = 'none';
                        deleteModal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                    };
                    
                    // ESC tuşu ile kapatma
                    const handleEscape = function(e) {
                        if (e.key === 'Escape') {
                            closeModal();
                            document.removeEventListener('keydown', handleEscape);
                        }
                    };
                    document.addEventListener('keydown', handleEscape);
                    
                    // Overlay'e tıklayınca kapatma
                    deleteModal.addEventListener('click', function(e) {
                        if (e.target === deleteModal) {
                            closeModal();
                        }
                    });
                }
            } else {
                console.error('Delete modal elementleri bulunamadı');
                console.log('deleteIdInput:', deleteIdInput);
                console.log('deleteTitleElement:', deleteTitleElement);
                console.log('deleteModal:', deleteModal);
            }
        }
    </script>
</body>
</html>