
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
requireDetailedPermission('oda_numaralari_goruntule', 'Oda numaraları görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Oda ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $oda_tipi_id = intval($_POST['oda_tipi_id']);
    $oda_numarasi = sanitizeString($_POST['oda_numarasi']);
    $kat = intval($_POST['kat']);
    $durum = $_POST['durum'];
    
    if (empty($oda_numarasi) || $oda_tipi_id <= 0) {
        $error_message = 'Oda numarası ve oda tipi gereklidir.';
    } else {
        // Aynı numarada oda var mı kontrol et
        $kontrol = fetchOne("SELECT id FROM oda_numaralari WHERE oda_numarasi = ?", [$oda_numarasi]);
        
        if ($kontrol) {
            $error_message = 'Bu numarada bir oda zaten mevcut.';
        } else {
            $sql = "INSERT INTO oda_numaralari (oda_tipi_id, oda_numarasi, kat, durum) VALUES (?, ?, ?, ?)";
            if (executeQuery($sql, [$oda_tipi_id, $oda_numarasi, $kat, $durum])) {
                $success_message = 'Oda başarıyla eklendi.';
            } else {
                $error_message = 'Oda eklenirken hata oluştu.';
            }
        }
    }
}

// Oda güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = intval($_POST['id']);
    $oda_tipi_id = intval($_POST['oda_tipi_id']);
    $oda_numarasi = sanitizeString($_POST['oda_numarasi']);
    $kat = intval($_POST['kat']);
    $durum = $_POST['durum'];
    
    if (empty($oda_numarasi) || $oda_tipi_id <= 0) {
        $error_message = 'Oda numarası ve oda tipi gereklidir.';
    } else {
        // Aynı numarada başka oda var mı kontrol et
        $kontrol = fetchOne("SELECT id FROM oda_numaralari WHERE oda_numarasi = ? AND id != ?", [$oda_numarasi, $id]);
        
        if ($kontrol) {
            $error_message = 'Bu numarada başka bir oda zaten mevcut.';
        } else {
            $sql = "UPDATE oda_numaralari SET oda_tipi_id = ?, oda_numarasi = ?, kat = ?, durum = ? WHERE id = ?";
            if (executeQuery($sql, [$oda_tipi_id, $oda_numarasi, $kat, $durum, $id])) {
                $success_message = 'Oda başarıyla güncellendi.';
            } else {
                $error_message = 'Oda güncellenirken hata oluştu.';
            }
        }
    }
}

// Oda silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    // Bu odaya ait aktif rezervasyon var mı kontrol et
    $rezervasyon_kontrol = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE oda_numarasi_id = ? AND durum IN ('beklemede', 'onaylandi', 'check_in')", [$id]);
    
    if ($rezervasyon_kontrol['sayi'] > 0) {
        $error_message = 'Bu odaya ait aktif rezervasyonlar bulunduğu için silinemez.';
    } else {
        $sql = "DELETE FROM oda_numaralari WHERE id = ?";
        if (executeQuery($sql, [$id])) {
            $success_message = 'Oda başarıyla silindi.';
        } else {
            $error_message = 'Oda silinirken hata oluştu.';
        }
    }
}

// Filtreleme
$oda_tipi_filtre = $_GET['oda_tipi'] ?? '';
$durum_filtre = $_GET['durum'] ?? '';
$kat_filtre = $_GET['kat'] ?? '';

// Odaları getir
$where_conditions = [];
$params = [];

if ($oda_tipi_filtre) {
    $where_conditions[] = "on.oda_tipi_id = ?";
    $params[] = $oda_tipi_filtre;
}

if ($durum_filtre) {
    $where_conditions[] = "on.durum = ?";
    $params[] = $durum_filtre;
}

if ($kat_filtre) {
    $where_conditions[] = "on.kat = ?";
    $params[] = $kat_filtre;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$sql = "SELECT odn.*, ot.oda_tipi_adi,
        (SELECT COUNT(*) FROM rezervasyonlar r WHERE r.oda_numarasi_id = odn.id AND r.durum = 'check_in' AND CURDATE() BETWEEN r.giris_tarihi AND r.cikis_tarihi) as dolu,
        CASE 
            -- Öncelik 1: Aktif rezervasyon (check-in yapılmış)
            WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                          AND CURDATE() >= DATE(r.giris_tarihi) 
                          AND CURDATE() < DATE(r.cikis_tarihi) 
                          THEN 1 END) > 0 THEN 'dolu'
            
            -- Öncelik 2: Checkout saati öncesi dolu (bugün checkout olacak)
            WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                          AND CURDATE() = DATE(r.cikis_tarihi)
                          AND TIME(NOW()) < TIME(r.cikis_tarihi)
                          THEN 1 END) > 0 THEN 'checkout_oncesi_dolu'
            
            -- Öncelik 3: Rezerve (onaylanmış ama henüz check-in yapılmamış)
            WHEN COUNT(CASE WHEN r.durum = 'onaylandi' 
                          AND CURDATE() < DATE(r.cikis_tarihi)
                          THEN 1 END) > 0 THEN 'rezerve'
            
            -- Öncelik 4: Temizlik bekliyor (checkout yapılmış ama oda hala aktif)
            WHEN COUNT(CASE WHEN r.durum = 'check_out' 
                          AND r.gercek_cikis_tarihi IS NOT NULL
                          AND odn.durum = 'aktif'
                          THEN 1 END) > 0 THEN 'temizlik_bekliyor'
            
            -- Varsayılan: Oda durumu
            ELSE odn.durum
        END as final_durum,
        CASE 
            WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                          AND CURDATE() >= DATE(r.giris_tarihi) 
                          AND CURDATE() < DATE(r.cikis_tarihi) 
                          THEN 1 END) > 0 THEN 'danger'
            
            WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                          AND CURDATE() = DATE(r.cikis_tarihi)
                          AND TIME(NOW()) < TIME(r.cikis_tarihi)
                          THEN 1 END) > 0 THEN 'info'
            
            WHEN COUNT(CASE WHEN r.durum = 'onaylandi' 
                          AND CURDATE() < DATE(r.cikis_tarihi)
                          THEN 1 END) > 0 THEN 'warning'
            
            WHEN COUNT(CASE WHEN r.durum = 'check_out' 
                          AND r.gercek_cikis_tarihi IS NOT NULL
                          AND odn.durum = 'aktif'
                          THEN 1 END) > 0 THEN 'secondary'
            WHEN odn.durum = 'dolu' THEN 'danger'
            WHEN odn.durum = 'aktif' THEN 'success'
            WHEN odn.durum = 'kirli' THEN 'warning'
            WHEN odn.durum = 'bakimda' THEN 'info'
            WHEN odn.durum = 'temizlik_bekliyor' THEN 'secondary'
            WHEN odn.durum = 'devre_disi' THEN 'dark'
            WHEN odn.durum = 'bakim' THEN 'info'
            WHEN odn.durum = '' OR odn.durum IS NULL THEN 'secondary'
            ELSE 'primary'
        END as durum_renk
        FROM oda_numaralari odn 
        LEFT JOIN oda_tipleri ot ON odn.oda_tipi_id = ot.id 
        LEFT JOIN rezervasyonlar r ON r.oda_numarasi_id = odn.id AND r.durum IN ('onaylandi', 'check_in')
        $where_clause 
        GROUP BY odn.id, odn.oda_numarasi, odn.oda_tipi_id, odn.durum, ot.oda_tipi_adi
        ORDER BY odn.kat ASC, odn.oda_numarasi ASC";

$odalar = fetchAll($sql, $params);

// Oda tipleri
$oda_tipleri = fetchAll("SELECT * FROM oda_tipleri WHERE durum = 'aktif' ORDER BY sira_no ASC");

// Katlar
$katlar = fetchAll("SELECT DISTINCT kat FROM oda_numaralari WHERE kat > 0 ORDER BY kat ASC");

// Düzenleme için oda getir
$edit_oda = null;
if (isset($_GET['duzenle']) && is_numeric($_GET['duzenle'])) {
    $edit_oda = fetchOne("SELECT * FROM oda_numaralari WHERE id = ?", [intval($_GET['duzenle'])]);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oda Numaraları - Admin Paneli</title>
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
                            <h1 class="h3 mb-0">Oda Numaraları</h1>
                            <p class="text-muted">Otel oda numaralarını yönetin</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#odaModal">
                            <i class="fas fa-plus me-2"></i>Yeni Oda Ekle
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

            <!-- Oda Durumu İstatistikleri -->
            <div class="row mb-4">
                <?php
                // Oda durumu istatistikleri
                $durum_istatistikleri = fetchAll("
                    SELECT 
                        CASE 
                            WHEN r.id IS NOT NULL THEN 'dolu'
                            ELSE od.durum
                        END as durum, 
                        COUNT(*) as sayi
                    FROM oda_numaralari od
                    LEFT JOIN rezervasyonlar r ON r.oda_numarasi_id = od.id AND r.durum = 'check_in' AND CURDATE() BETWEEN r.giris_tarihi AND r.cikis_tarihi
                    GROUP BY 
                        CASE 
                            WHEN r.id IS NOT NULL THEN 'dolu'
                            ELSE od.durum
                        END
                    ORDER BY sayi DESC
                ");
                
                $durum_renkleri = [
                    'aktif' => 'success',
                    'dolu' => 'danger', 
                    'kirli' => 'warning',
                    'bakimda' => 'info',
                    'temizlik_bekliyor' => 'secondary',
                    'devre_disi' => 'dark'
                ];
                
                $durum_isimleri = [
                    'aktif' => 'Aktif/Temiz',
                    'dolu' => 'Dolu',
                    'kirli' => 'Kirli', 
                    'bakimda' => 'Bakımda',
                    'temizlik_bekliyor' => 'Temizlik Bekliyor',
                    'devre_disi' => 'Devre Dışı'
                ];
                
                foreach ($durum_istatistikleri as $istatistik):
                    $durum = $istatistik['durum'];
                    $renk = $durum_renkleri[$durum] ?? 'primary';
                    $isim = $durum_isimleri[$durum] ?? ucfirst($durum);
                ?>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="card border-<?php echo $renk; ?>">
                        <div class="card-body text-center">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="h3 mb-0 text-<?php echo $renk; ?>"><?php echo $istatistik['sayi']; ?></div>
                                    <div class="text-muted small"><?php echo $isim; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Filtreler -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="oda_tipi" class="form-label">Oda Tipi</label>
                            <select class="form-select" id="oda_tipi" name="oda_tipi">
                                <option value="">Tüm Oda Tipleri</option>
                                <?php foreach ($oda_tipleri as $tip): ?>
                                <option value="<?php echo $tip['id']; ?>" <?php echo $oda_tipi_filtre == $tip['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tip['oda_tipi_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="kat" class="form-label">Kat</label>
                            <select class="form-select" id="kat" name="kat">
                                <option value="">Tüm Katlar</option>
                                <?php foreach ($katlar as $kat): ?>
                                <option value="<?php echo $kat['kat']; ?>" <?php echo $kat_filtre == $kat['kat'] ? 'selected' : ''; ?>>
                                    <?php echo $kat['kat']; ?>. Kat
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="">Tüm Durumlar</option>
                                <option value="aktif" <?php echo $durum_filtre == 'aktif' ? 'selected' : ''; ?>>Aktif/Temiz</option>
                                <option value="dolu" <?php echo $durum_filtre == 'dolu' ? 'selected' : ''; ?>>Dolu</option>
                                <option value="kirli" <?php echo $durum_filtre == 'kirli' ? 'selected' : ''; ?>>Kirli</option>
                                <option value="bakimda" <?php echo $durum_filtre == 'bakimda' ? 'selected' : ''; ?>>Bakımda</option>
                                <option value="temizlik_bekliyor" <?php echo $durum_filtre == 'temizlik_bekliyor' ? 'selected' : ''; ?>>Temizlik Bekliyor</option>
                                <option value="devre_disi" <?php echo $durum_filtre == 'devre_disi' ? 'selected' : ''; ?>>Devre Dışı</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filtrele
                                </button>
                                <a href="oda-numaralari.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Temizle
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Odalar Grid -->
            <div class="row">
                <?php if (empty($odalar)): ?>
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-door-closed fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Henüz oda eklenmemiş</h5>
                            <p class="text-muted">İlk odanızı eklemek için yukarıdaki butonu kullanın.</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($odalar as $oda): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card shadow h-100 border-<?php echo $oda['durum_renk']; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="room-number">
                                    <h4 class="mb-0 text-primary"><?php echo htmlspecialchars($oda['oda_numarasi']); ?></h4>
                                    <small class="text-muted"><?php echo $oda['kat']; ?>. Kat</small>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="?duzenle=<?php echo $oda['id']; ?>">
                                                <i class="fas fa-edit me-2"></i>Düzenle
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" 
                                               href="?sil=<?php echo $oda['id']; ?>"
                                               onclick="return confirm('Bu odayı silmek istediğinizden emin misiniz?')">
                                                <i class="fas fa-trash me-2"></i>Sil
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <p class="card-text text-muted mb-2"><?php echo htmlspecialchars($oda['oda_tipi_adi']); ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?php echo $oda['durum_renk']; ?> text-white fw-bold">
                                    <?php 
                                        $durum_text = trim($oda['final_durum']);
                                        switch($durum_text) {
                                            case 'aktif': echo 'Aktif/Temiz'; break;
                                            case 'dolu': echo 'Dolu'; break;
                                            case 'kirli': echo 'Kirli'; break;
                                            case 'bakimda': echo 'Bakımda'; break;
                                            case 'temizlik_bekliyor': echo 'Temizlik Bekliyor'; break;
                                            case 'devre_disi': echo 'Devre Dışı'; break;
                                            default: echo ucfirst($durum_text); break;
                                        }
                                    ?>
                                </span>
                                
                                <?php if ($oda['dolu'] > 0): ?>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>Konuk var
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Oda Ekleme/Düzenleme Modal -->
    <div class="modal fade" id="odaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?php echo $edit_oda ? 'Oda Düzenle' : 'Yeni Oda Ekle'; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $edit_oda ? 'update' : 'add'; ?>">
                        <?php if ($edit_oda): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_oda['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="oda_tipi_id" class="form-label">Oda Tipi *</label>
                            <select class="form-select" id="oda_tipi_id" name="oda_tipi_id" required>
                                <option value="">Oda tipi seçin</option>
                                <?php foreach ($oda_tipleri as $tip): ?>
                                <option value="<?php echo $tip['id']; ?>" 
                                        <?php echo ($edit_oda && $edit_oda['oda_tipi_id'] == $tip['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tip['oda_tipi_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="oda_numarasi" class="form-label">Oda Numarası *</label>
                                    <input type="text" class="form-control" id="oda_numarasi" name="oda_numarasi" 
                                           value="<?php echo $edit_oda ? htmlspecialchars($edit_oda['oda_numarasi']) : ''; ?>" 
                                           placeholder="Örn: 101" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kat" class="form-label">Kat</label>
                                    <input type="number" class="form-control" id="kat" name="kat" 
                                           value="<?php echo $edit_oda ? $edit_oda['kat'] : '1'; ?>" min="0" max="50">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="aktif" <?php echo (!$edit_oda || $edit_oda['durum'] == 'aktif') ? 'selected' : ''; ?>>Aktif/Temiz</option>
                                <option value="dolu" <?php echo ($edit_oda && $edit_oda['durum'] == 'dolu') ? 'selected' : ''; ?>>Dolu</option>
                                <option value="kirli" <?php echo ($edit_oda && $edit_oda['durum'] == 'kirli') ? 'selected' : ''; ?>>Kirli</option>
                                <option value="bakimda" <?php echo ($edit_oda && $edit_oda['durum'] == 'bakimda') ? 'selected' : ''; ?>>Bakımda</option>
                                <option value="temizlik_bekliyor" <?php echo ($edit_oda && $edit_oda['durum'] == 'temizlik_bekliyor') ? 'selected' : ''; ?>>Temizlik Bekliyor</option>
                                <option value="devre_disi" <?php echo ($edit_oda && $edit_oda['durum'] == 'devre_disi') ? 'selected' : ''; ?>>Devre Dışı</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_oda ? 'Güncelle' : 'Ekle'; ?>
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
        <?php if ($edit_oda): ?>
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('odaModal')).show();
        });
        <?php endif; ?>

        // Modal kapandığında URL'yi temizle
        document.getElementById('odaModal').addEventListener('hidden.bs.modal', function() {
            if (window.location.search.includes('duzenle=')) {
                window.location.href = 'oda-numaralari.php';
            }
        });
    </script>
</body>
</html>
