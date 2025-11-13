
<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';

// Giriş kontrolü
requireAdminAuth();

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('kullanici_yonetimi_goruntule', 'Kullanıcı yönetimi görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF kontrolü
checkCSRFOnPost();

// Kullanıcı ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $ad = sanitizeString($_POST['ad']);
    $soyad = sanitizeString($_POST['soyad']);
    $email = sanitizeString($_POST['email']);
    $sifre = $_POST['sifre'];
    $rol = $_POST['rol'];
    $durum = $_POST['durum'];
    
    if (empty($ad) || empty($soyad) || empty($email) || empty($sifre)) {
        $error_message = 'Tüm alanlar gereklidir.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta adresi girin.';
    } elseif (strlen($sifre) < 6) {
        $error_message = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        // E-posta kontrolü
        $existing = fetchOne("SELECT id FROM kullanicilar WHERE email = ?", [$email]);
        if ($existing) {
            $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
        } else {
            $hashed_password = password_hash($sifre, PASSWORD_DEFAULT);
            $sql = "INSERT INTO kullanicilar (ad, soyad, email, sifre, rol, durum) VALUES (?, ?, ?, ?, ?, ?)";
            if (executeQuery($sql, [$ad, $soyad, $email, $hashed_password, $rol, $durum])) {
                $success_message = 'Kullanıcı başarıyla eklendi.';
            } else {
                $error_message = 'Kullanıcı eklenirken hata oluştu.';
            }
        }
    }
}

// Kullanıcı güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = intval($_POST['id']);
    $ad = sanitizeString($_POST['ad']);
    $soyad = sanitizeString($_POST['soyad']);
    $email = sanitizeString($_POST['email']);
    $rol = $_POST['rol'];
    $durum = $_POST['durum'];
    
    if (empty($ad) || empty($soyad) || empty($email)) {
        $error_message = 'Ad, soyad ve e-posta gereklidir.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta adresi girin.';
    } else {
        // E-posta kontrolü (kendisi hariç)
        $existing = fetchOne("SELECT id FROM kullanicilar WHERE email = ? AND id != ?", [$email, $id]);
        if ($existing) {
            $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
        } else {
            $sql = "UPDATE kullanicilar SET ad = ?, soyad = ?, email = ?, rol = ?, durum = ? WHERE id = ?";
            if (executeQuery($sql, [$ad, $soyad, $email, $rol, $durum, $id])) {
                $success_message = 'Kullanıcı başarıyla güncellendi.';
                
                // Şifre güncelleme
                if (!empty($_POST['yeni_sifre'])) {
                    if (strlen($_POST['yeni_sifre']) < 6) {
                        $error_message = 'Şifre en az 6 karakter olmalıdır.';
                    } else {
                        $hashed_password = password_hash($_POST['yeni_sifre'], PASSWORD_DEFAULT);
                        executeQuery("UPDATE kullanicilar SET sifre = ? WHERE id = ?", [$hashed_password, $id]);
                    }
                }
            } else {
                $error_message = 'Kullanıcı güncellenirken hata oluştu.';
            }
        }
    }
}

// Kullanıcı silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    // Kendi hesabını silmeye çalışıyor mu?
    if ($id == $_SESSION['user_id']) {
        $error_message = 'Kendi hesabınızı silemezsiniz.';
    } else {
        $sql = "DELETE FROM kullanicilar WHERE id = ?";
        if (executeQuery($sql, [$id])) {
            $success_message = 'Kullanıcı başarıyla silindi.';
        } else {
            $error_message = 'Kullanıcı silinirken hata oluştu.';
        }
    }
}

// Kullanıcıları getir
$kullanicilar = fetchAll("SELECT * FROM kullanicilar ORDER BY olusturma_tarihi DESC");

// Düzenleme için kullanıcı getir
$edit_kullanici = null;
if (isset($_GET['duzenle']) && is_numeric($_GET['duzenle'])) {
    $edit_kullanici = fetchOne("SELECT * FROM kullanicilar WHERE id = ?", [intval($_GET['duzenle'])]);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
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
                            <li><a class="dropdown-item" href="profil.php"><i class="fas fa-user-edit me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Siteyi Görüntüle</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Kullanıcı Yönetimi</h1>
                            <p class="text-muted">Admin paneli kullanıcılarını yönetin</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kullaniciModal">
                            <i class="fas fa-plus me-2"></i>Yeni Kullanıcı Ekle
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Kullanıcılar Tablosu -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Kullanıcılar Listesi</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Ad Soyad</th>
                                    <th>E-posta</th>
                                    <th>Rol</th>
                                    <th>Durum</th>
                                    <th>Son Giriş</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kullanicilar)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-users fa-3x mb-3 d-block"></i>
                                        Henüz kullanıcı bulunmuyor.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($kullanicilar as $kullanici): ?>
                                <tr class="<?php echo $kullanici['id'] == $_SESSION['user_id'] ? 'table-info' : ''; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <?php echo strtoupper(substr($kullanici['ad'], 0, 1) . substr($kullanici['soyad'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($kullanici['ad'] . ' ' . $kullanici['soyad']); ?></strong>
                                                <?php if ($kullanici['id'] == $_SESSION['user_id']): ?>
                                                <small class="text-muted d-block">(Siz)</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($kullanici['email']); ?></td>
                                    <td>
                                        <?php if ($kullanici['rol'] == 'superadmin'): ?>
                                        <span class="badge bg-danger">Süper Admin</span>
                                        <?php elseif ($kullanici['rol'] == 'admin'): ?>
                                        <span class="badge bg-primary">Admin</span>
                                        <?php elseif ($kullanici['rol'] == 'sales'): ?>
                                        <span class="badge bg-success">Satış Elemanı</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($kullanici['rol']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($kullanici['durum'] == 'aktif'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($kullanici['son_giris']): ?>
                                        <small><?php echo formatTurkishDate($kullanici['son_giris'], 'd.m.Y H:i'); ?></small>
                                        <?php else: ?>
                                        <small class="text-muted">Hiç giriş yapmamış</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo formatTurkishDate($kullanici['olusturma_tarihi'], 'd.m.Y'); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="?duzenle=<?php echo $kullanici['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="yetki-yonetimi.php" class="btn btn-sm btn-outline-success" title="Yetki Yönetimi">
                                                <i class="fas fa-user-shield"></i>
                                            </a>
                                            <?php if ($kullanici['id'] != $_SESSION['user_id']): ?>
                                            <a href="?sil=<?php echo $kullanici['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kullanıcı Ekleme/Düzenleme Modal -->
    <div class="modal fade" id="kullaniciModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?php echo $edit_kullanici ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı Ekle'; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_kullanici ? 'update' : 'add'; ?>">
                        <?php if ($edit_kullanici): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_kullanici['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ad" class="form-label">Ad *</label>
                                    <input type="text" class="form-control" id="ad" name="ad" 
                                           value="<?php echo $edit_kullanici ? htmlspecialchars($edit_kullanici['ad']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="soyad" class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" id="soyad" name="soyad" 
                                           value="<?php echo $edit_kullanici ? htmlspecialchars($edit_kullanici['soyad']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $edit_kullanici ? htmlspecialchars($edit_kullanici['email']) : ''; ?>" required>
                        </div>
                        
                        <?php if (!$edit_kullanici): ?>
                        <div class="mb-3">
                            <label for="sifre" class="form-label">Şifre *</label>
                            <input type="password" class="form-control" id="sifre" name="sifre" 
                                   minlength="6" required>
                            <div class="form-text">En az 6 karakter olmalıdır.</div>
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label for="yeni_sifre" class="form-label">Yeni Şifre</label>
                            <input type="password" class="form-control" id="yeni_sifre" name="yeni_sifre" 
                                   minlength="6">
                            <div class="form-text">Değiştirmek istemiyorsanız boş bırakın.</div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rol" class="form-label">Rol</label>
                                    <select class="form-select" id="rol" name="rol">
                                        <?php
                                        // Tüm rolleri tanımla
                                        $roller = [
                                            'superadmin' => 'Süper Admin',
                                            'admin' => 'Admin',
                                            'personel' => 'Personel',
                                            'sales' => 'Satış Elemanı',
                                            'ekip' => 'Ekip Üyesi',
                                            'housekeeper' => 'Temizlik Elemanı',
                                            'housekeeper_manager' => 'Temizlik Müdürü'
                                        ];
                                        
                                        foreach ($roller as $rol_value => $rol_label) {
                                            $selected = (!$edit_kullanici && $rol_value == 'admin') || 
                                                       ($edit_kullanici && $edit_kullanici['rol'] == $rol_value) ? 'selected' : '';
                                            echo "<option value=\"{$rol_value}\" {$selected}>{$rol_label}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="durum" class="form-label">Durum</label>
                                    <select class="form-select" id="durum" name="durum">
                                        <option value="aktif" <?php echo (!$edit_kullanici || $edit_kullanici['durum'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="pasif" <?php echo ($edit_kullanici && $edit_kullanici['durum'] == 'pasif') ? 'selected' : ''; ?>>Pasif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_kullanici ? 'Güncelle' : 'Ekle'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Düzenleme modunu aç
        <?php if ($edit_kullanici): ?>
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('kullaniciModal')).show();
        });
        <?php endif; ?>

        // Modal kapandığında URL'yi temizle
        document.getElementById('kullaniciModal').addEventListener('hidden.bs.modal', function() {
            if (window.location.search.includes('duzenle=')) {
                window.location.href = 'kullanicilar.php';
            }
        });
    </script>

    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
    </style>
</body>
</html>
