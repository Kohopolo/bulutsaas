<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('slider_goruntule', 'Slider görüntüleme yetkiniz bulunmamaktadır.');

// CSRF token kontrolü
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Slider ekleme/güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası!';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'add' || $action == 'edit') {
            $baslik = trim($_POST['baslik'] ?? '');
            $aciklama = trim($_POST['aciklama'] ?? '');
            $buton_metni = trim($_POST['buton_metni'] ?? '');
            $buton_linki = trim($_POST['buton_linki'] ?? '');
            $sira_no = intval($_POST['sira_no'] ?? 1);
            $durum = $_POST['durum'] ?? 'aktif';
            $id = intval($_POST['id'] ?? 0);
            
            if (empty($baslik)) {
                $error = 'Başlık alanı zorunludur!';
            } else {
                // Resim yükleme işlemi
                $resim_url = '';
                if (isset($_FILES['resim']) && $_FILES['resim']['error'] == 0) {
                    $upload_dir = '../uploads/slider/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['resim']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $file_name = 'slider_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['resim']['tmp_name'], $file_path)) {
                            $resim_url = 'uploads/slider/' . $file_name;
                        } else {
                            $error = 'Resim yüklenirken hata oluştu!';
                        }
                    } else {
                        $error = 'Sadece JPG, JPEG, PNG ve WebP formatları kabul edilir!';
                    }
                }
                
                if (empty($error)) {
                    if ($action == 'add') {
                        if (empty($resim_url)) {
                            $error = 'Resim seçimi zorunludur!';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO slider (baslik, aciklama, resim_url, buton_metni, buton_linki, sira_no, durum) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            if ($stmt->execute([$baslik, $aciklama, $resim_url, $buton_metni, $buton_linki, $sira_no, $durum])) {
                                $message = 'Slider başarıyla eklendi!';
                            } else {
                                $error = 'Slider eklenirken hata oluştu!';
                            }
                        }
                    } else { // edit
                        $update_query = "UPDATE slider SET baslik = ?, aciklama = ?, buton_metni = ?, buton_linki = ?, sira_no = ?, durum = ?";
                        $params = [$baslik, $aciklama, $buton_metni, $buton_linki, $sira_no, $durum];
                        
                        if (!empty($resim_url)) {
                            // Eski resmi sil
                            $stmt = $pdo->prepare("SELECT resim_url FROM slider WHERE id = ?");
                            $stmt->execute([$id]);
                            $old_slider = $stmt->fetch();
                            if ($old_slider && file_exists('../' . $old_slider['resim_url'])) {
                                unlink('../' . $old_slider['resim_url']);
                            }
                            
                            $update_query .= ", resim_url = ?";
                            $params[] = $resim_url;
                        }
                        
                        $update_query .= " WHERE id = ?";
                        $params[] = $id;
                        
                        $stmt = $pdo->prepare($update_query);
                        if ($stmt->execute($params)) {
                            $message = 'Slider başarıyla güncellendi!';
                        } else {
                            $error = 'Slider güncellenirken hata oluştu!';
                        }
                    }
                }
            }
        } elseif ($action == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                // Resmi sil
                $stmt = $pdo->prepare("SELECT resim_url FROM slider WHERE id = ?");
                $stmt->execute([$id]);
                $slider = $stmt->fetch();
                if ($slider && file_exists('../' . $slider['resim_url'])) {
                    unlink('../' . $slider['resim_url']);
                }
                
                $stmt = $pdo->prepare("DELETE FROM slider WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $message = 'Slider başarıyla silindi!';
                } else {
                    $error = 'Slider silinirken hata oluştu!';
                }
            }
        } elseif ($action == 'toggle_status') {
            $id = intval($_POST['id'] ?? 0);
            $durum = $_POST['durum'] ?? 'aktif';
            
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE slider SET durum = ? WHERE id = ?");
                if ($stmt->execute([$durum, $id])) {
                    $message = 'Slider durumu güncellendi!';
                } else {
                    $error = 'Durum güncellenirken hata oluştu!';
                }
            }
        }
    }
}

// Slider listesi
$stmt = $pdo->query("SELECT * FROM slider ORDER BY sira_no ASC, id DESC");
$sliders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slider Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .slider-image {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .status-badge {
            font-size: 0.8em;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3 class="card-title">Slider Yönetimi</h3>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sliderModal" onclick="openAddModal()">
                                        <i class="fas fa-plus"></i> Yeni Slider Ekle
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
                                                    <th>Resim</th>
                                                    <th>Başlık</th>
                                                    <th>Açıklama</th>
                                                    <th>Buton</th>
                                                    <th>Sıra</th>
                                                    <th>Durum</th>
                                                    <th>İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($sliders as $slider): ?>
                                                <tr>
                                                    <td>
                                                        <img src="../<?php echo htmlspecialchars($slider['resim_url']); ?>" 
                                                             alt="<?php echo htmlspecialchars($slider['baslik']); ?>" 
                                                             class="slider-image">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($slider['baslik']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($slider['aciklama'], 0, 50)) . (strlen($slider['aciklama']) > 50 ? '...' : ''); ?></td>
                                                    <td>
                                                        <?php if ($slider['buton_metni']): ?>
                                                            <span class="badge bg-info"><?php echo htmlspecialchars($slider['buton_metni']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $slider['sira_no']; ?></td>
                                                    <td>
                                                        <span class="badge status-badge <?php echo $slider['durum'] == 'aktif' ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo ucfirst($slider['durum']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="editSlider(<?php echo htmlspecialchars(json_encode($slider)); ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-<?php echo $slider['durum'] == 'aktif' ? 'warning' : 'success'; ?>" 
                                                                    onclick="toggleStatus(<?php echo $slider['id']; ?>, '<?php echo $slider['durum'] == 'aktif' ? 'pasif' : 'aktif'; ?>')">
                                                                <i class="fas fa-<?php echo $slider['durum'] == 'aktif' ? 'eye-slash' : 'eye'; ?>"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteSlider(<?php echo $slider['id']; ?>)">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Slider Modal -->
    <div class="modal fade" id="sliderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="sliderForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="sliderId" value="">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Yeni Slider Ekle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="baslik" class="form-label">Başlık *</label>
                                    <input type="text" class="form-control" id="baslik" name="baslik" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="buton_metni" class="form-label">Buton Metni</label>
                                            <input type="text" class="form-control" id="buton_metni" name="buton_metni">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="buton_linki" class="form-label">Buton Linki</label>
                                            <input type="text" class="form-control" id="buton_linki" name="buton_linki">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sira_no" class="form-label">Sıra No</label>
                                            <input type="number" class="form-control" id="sira_no" name="sira_no" value="1" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="durum" class="form-label">Durum</label>
                                            <select class="form-select" id="durum" name="durum">
                                                <option value="aktif">Aktif</option>
                                                <option value="pasif">Pasif</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="resim" class="form-label">Slider Resmi *</label>
                                    <input type="file" class="form-control" id="resim" name="resim" accept="image/*">
                                    <div class="form-text">JPG, JPEG, PNG, WebP formatları kabul edilir.</div>
                                </div>
                                
                                <div id="currentImage" style="display: none;">
                                    <label class="form-label">Mevcut Resim</label>
                                    <img id="currentImagePreview" src="" alt="Mevcut resim" class="img-fluid rounded">
                                </div>
                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Yeni Slider Ekle';
            document.getElementById('formAction').value = 'add';
            document.getElementById('sliderId').value = '';
            document.getElementById('sliderForm').reset();
            document.getElementById('currentImage').style.display = 'none';
            document.getElementById('resim').required = true;
        }
        
        function editSlider(slider) {
            document.getElementById('modalTitle').textContent = 'Slider Düzenle';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('sliderId').value = slider.id;
            document.getElementById('baslik').value = slider.baslik;
            document.getElementById('aciklama').value = slider.aciklama || '';
            document.getElementById('buton_metni').value = slider.buton_metni || '';
            document.getElementById('buton_linki').value = slider.buton_linki || '';
            document.getElementById('sira_no').value = slider.sira_no;
            document.getElementById('durum').value = slider.durum;
            
            // Mevcut resmi göster
            document.getElementById('currentImagePreview').src = '../' + slider.resim_url;
            document.getElementById('currentImage').style.display = 'block';
            document.getElementById('resim').required = false;
            
            new bootstrap.Modal(document.getElementById('sliderModal')).show();
        }
        
        function toggleStatus(id, newStatus) {
            if (confirm('Slider durumunu değiştirmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="durum" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteSlider(id) {
            if (confirm('Bu slider\'ı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>