<?php
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
if (!hasDetailedPermission('housekeeping_personel_yonetimi')) {
    $_SESSION['error_message'] = 'Housekeeping personel yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'personel_ekle') {
            $ad = sanitizeString($_POST['ad']);
            $soyad = sanitizeString($_POST['soyad']);
            $email = sanitizeString($_POST['email']);
            $telefon = sanitizeString($_POST['telefon']);
            $rol = 'housekeeper';
            $sifre = password_hash('123456', PASSWORD_DEFAULT); // Varsayılan şifre
            
            // Email kontrolü
            $existing = fetchOne("SELECT id FROM kullanicilar WHERE email = ?", [$email]);
            if ($existing) {
                throw new Exception('Bu email adresi zaten kullanılıyor.');
            }
            
            // Kullanıcı adı oluştur
            $kullanici_adi = strtolower($ad . '.' . $soyad);
            $counter = 1;
            while (fetchOne("SELECT id FROM kullanicilar WHERE kullanici_adi = ?", [$kullanici_adi])) {
                $kullanici_adi = strtolower($ad . '.' . $soyad . $counter);
                $counter++;
            }
            
            $sql = "INSERT INTO kullanicilar (kullanici_adi, ad, soyad, email, sifre, rol, aktif, durum) 
                    VALUES (?, ?, ?, ?, ?, ?, 1, 'aktif')";
            
            $result = executeQuery($sql, [$kullanici_adi, $ad, $soyad, $email, $sifre, $rol]);
            
            if ($result) {
                $pdo->commit();
                $success_message = 'Housekeeper başarıyla eklendi. Kullanıcı adı: ' . $kullanici_adi . ', Şifre: 123456';
            } else {
                throw new Exception('Housekeeper eklenirken hata oluştu.');
            }
        }
        
        if ($action == 'personel_guncelle') {
            $id = intval($_POST['id']);
            $ad = sanitizeString($_POST['ad']);
            $soyad = sanitizeString($_POST['soyad']);
            $email = sanitizeString($_POST['email']);
            $telefon = sanitizeString($_POST['telefon']);
            $aktif = intval($_POST['aktif']);
            
            $sql = "UPDATE kullanicilar SET ad = ?, soyad = ?, email = ?, aktif = ? WHERE id = ? AND rol = 'housekeeper'";
            
            $result = executeQuery($sql, [$ad, $soyad, $email, $aktif, $id]);
            
            if ($result) {
                $pdo->commit();
                $success_message = 'Housekeeper bilgileri başarıyla güncellendi.';
            } else {
                throw new Exception('Housekeeper güncellenirken hata oluştu.');
            }
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Housekeeper'ları getir
$housekeepers = fetchAll("
    SELECT k.*, 
           COUNT(tk.id) as toplam_gorev,
           COUNT(CASE WHEN tk.durum = 'onaylandi' THEN 1 END) as tamamlanan_gorev,
           COUNT(CASE WHEN tk.durum = 'devam_ediyor' THEN 1 END) as devam_eden_gorev
    FROM kullanicilar k
    LEFT JOIN temizlik_kayitlari tk ON k.id = tk.housekeeper_id
    WHERE k.rol = 'housekeeper'
    GROUP BY k.id
    ORDER BY k.ad, k.soyad
");

// İstatistikler
$stats = [
    'toplam_housekeeper' => count($housekeepers),
    'aktif_housekeeper' => count(array_filter($housekeepers, function($h) { return $h['aktif'] == 1; })),
    'pasif_housekeeper' => count(array_filter($housekeepers, function($h) { return $h['aktif'] == 0; }))
];


?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Yönetimi - Housekeeping</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-users text-primary"></i> Personel Yönetimi</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#personelEkleModal">
                        <i class="fas fa-plus"></i> Yeni Housekeeper
                    </button>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h5 class="card-title">Toplam Housekeeper</h5>
                                <h3 class="text-primary"><?php echo $stats['toplam_housekeeper']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                                <h5 class="card-title">Aktif Housekeeper</h5>
                                <h3 class="text-success"><?php echo $stats['aktif_housekeeper']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-user-times fa-2x text-danger mb-2"></i>
                                <h5 class="card-title">Pasif Housekeeper</h5>
                                <h3 class="text-danger"><?php echo $stats['pasif_housekeeper']; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Housekeeper Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list text-info"></i> Housekeeper Listesi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ad Soyad</th>
                                        <th>Email</th>
                                        <th>Durum</th>
                                        <th>Toplam Görev</th>
                                        <th>Tamamlanan</th>
                                        <th>Devam Eden</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($housekeepers as $housekeeper): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($housekeeper['ad'] . ' ' . $housekeeper['soyad']); ?></strong>
                                                <br><small class="text-muted">@<?php echo htmlspecialchars($housekeeper['kullanici_adi']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($housekeeper['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $housekeeper['aktif'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $housekeeper['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $housekeeper['toplam_gorev']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $housekeeper['tamamlanan_gorev']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $housekeeper['devam_eden_gorev']; ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#personelGuncelleModal<?php echo $housekeeper['id']; ?>">
                                                    <i class="fas fa-edit"></i> Düzenle
                                                </button>
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

    <!-- Personel Ekle Modal -->
    <div class="modal fade" id="personelEkleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Housekeeper Ekle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="personel_ekle">
                        
                        <div class="mb-3">
                            <label class="form-label">Ad</label>
                            <input type="text" class="form-control" name="ad" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Soyad</label>
                            <input type="text" class="form-control" name="soyad" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="text" class="form-control" name="telefon">
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Varsayılan şifre: <strong>123456</strong> (İlk girişte değiştirilmelidir)
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Personel Güncelle Modal -->
    <?php foreach ($housekeepers as $housekeeper): ?>
        <div class="modal fade" id="personelGuncelleModal<?php echo $housekeeper['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Housekeeper Düzenle</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="personel_guncelle">
                            <input type="hidden" name="id" value="<?php echo $housekeeper['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Ad</label>
                                <input type="text" class="form-control" name="ad" value="<?php echo htmlspecialchars($housekeeper['ad']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Soyad</label>
                                <input type="text" class="form-control" name="soyad" value="<?php echo htmlspecialchars($housekeeper['soyad']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($housekeeper['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Durum</label>
                                <select class="form-select" name="aktif" required>
                                    <option value="1" <?php echo $housekeeper['aktif'] ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="0" <?php echo !$housekeeper['aktif'] ? 'selected' : ''; ?>>Pasif</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-primary">Güncelle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
