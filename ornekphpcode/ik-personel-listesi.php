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
if (!hasDetailedPermission('ik_personel_listesi')) {
    $_SESSION['error_message'] = 'İK personel listesi görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

// Filtreleme
$filtre_departman = $_GET['departman'] ?? '';
$filtre_rol = $_GET['rol'] ?? '';
$filtre_durum = $_GET['durum'] ?? '';

// WHERE koşulları
$where_conditions = [];
$params = [];

if ($filtre_departman) {
    $where_conditions[] = "pb.departman = ?";
    $params[] = $filtre_departman;
}

if ($filtre_rol) {
    $where_conditions[] = "k.rol = ?";
    $params[] = $filtre_rol;
}

if ($filtre_durum) {
    $where_conditions[] = "k.durum = ?";
    $params[] = $filtre_durum;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Personelleri getir
$personeller = fetchAll("
    SELECT k.*, pb.*
    FROM kullanicilar k
    LEFT JOIN personel_bilgileri pb ON k.id = pb.kullanici_id
    $where_clause
    ORDER BY k.ad, k.soyad
", $params);

// Departmanları getir
$departmanlar = fetchAll("
    SELECT DISTINCT departman 
    FROM personel_bilgileri 
    WHERE departman IS NOT NULL AND departman != ''
    ORDER BY departman
");

// Rolleri getir
$roller = fetchAll("
    SELECT DISTINCT rol 
    FROM kullanicilar 
    WHERE rol IS NOT NULL AND rol != ''
    ORDER BY rol
");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İK Personel Listesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">İK Personel Listesi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="ik-dashboard.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Dashboard
                        </a>
                        <a href="ik-personel-ekle.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Personel Ekle
                        </a>
                    </div>
                </div>

                <!-- Filtreleme -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter"></i> Filtreleme
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Departman</label>
                                <select name="departman" class="form-select">
                                    <option value="">Tümü</option>
                                    <?php foreach ($departmanlar as $departman): ?>
                                        <option value="<?php echo htmlspecialchars($departman['departman']); ?>" <?php echo $filtre_departman == $departman['departman'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($departman['departman']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Rol</label>
                                <select name="rol" class="form-select">
                                    <option value="">Tümü</option>
                                    <?php foreach ($roller as $rol): ?>
                                        <option value="<?php echo htmlspecialchars($rol['rol']); ?>" <?php echo $filtre_rol == $rol['rol'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rol['rol']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Durum</label>
                                <select name="durum" class="form-select">
                                    <option value="">Tümü</option>
                                    <option value="aktif" <?php echo $filtre_durum == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="pasif" <?php echo $filtre_durum == 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Filtrele
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Personel Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users"></i> Personel Listesi
                            <span class="badge bg-primary ms-2"><?php echo count($personeller); ?> Personel</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($personeller)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Filtre kriterlerinize uygun personel bulunamadı.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ad Soyad</th>
                                            <th>Rol</th>
                                            <th>Departman</th>
                                            <th>Pozisyon</th>
                                            <th>Telefon</th>
                                            <th>Email</th>
                                            <th>İşe Giriş</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($personeller as $personel): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                            <?php echo strtoupper(substr($personel['ad'], 0, 1) . substr($personel['soyad'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?></strong>
                                                            <?php if ($personel['tc_no']): ?>
                                                                <br><small class="text-muted">TC: <?php echo htmlspecialchars($personel['tc_no']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($personel['rol']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($personel['departman'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($personel['pozisyon'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($personel['telefon'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($personel['email'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($personel['ise_giris_tarihi']): ?>
                                                        <?php echo date('d.m.Y', strtotime($personel['ise_giris_tarihi'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $personel['durum'] == 'aktif' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($personel['durum']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="ik-personel-detay.php?id=<?php echo $personel['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if (hasDetailedPermission('ik_personel_duzenle')): ?>
                                                            <a href="ik-personel-duzenle.php?id=<?php echo $personel['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .avatar-sm {
            width: 40px;
            height: 40px;
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</body>
</html>
