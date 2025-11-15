
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
requireDetailedPermission('oda_musaitlik_yonetimi', 'Oda müsaitlik yönetimi yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Müsaitlik güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_availability') {
        $oda_tipi_id = intval($_POST['oda_tipi_id']);
        $tarih = $_POST['tarih'];
        $musait_oda_sayisi = intval($_POST['musait_oda_sayisi']);
        
        if ($oda_tipi_id > 0 && $tarih && $musait_oda_sayisi >= 0) {
            // Mevcut kaydı kontrol et
            $mevcut = fetchOne("SELECT * FROM oda_musaitlik WHERE oda_tipi_id = ? AND tarih = ?", [$oda_tipi_id, $tarih]);
            
            if ($mevcut) {
                // Güncelle
                $sql = "UPDATE oda_musaitlik SET musait_oda_sayisi = ? WHERE oda_tipi_id = ? AND tarih = ?";
                if (executeQuery($sql, [$musait_oda_sayisi, $oda_tipi_id, $tarih])) {
                    $success_message = 'Müsaitlik başarıyla güncellendi.';
                } else {
                    $error_message = 'Müsaitlik güncellenirken hata oluştu.';
                }
            } else {
                // Yeni kayıt ekle
                $sql = "INSERT INTO oda_musaitlik (oda_tipi_id, tarih, musait_oda_sayisi) VALUES (?, ?, ?)";
                if (executeQuery($sql, [$oda_tipi_id, $tarih, $musait_oda_sayisi])) {
                    $success_message = 'Müsaitlik başarıyla eklendi.';
                } else {
                    $error_message = 'Müsaitlik eklenirken hata oluştu.';
                }
            }
        } else {
            $error_message = 'Lütfen tüm alanları doldurun.';
        }
    }
    
    if ($_POST['action'] == 'bulk_update') {
        $oda_tipi_id = intval($_POST['bulk_oda_tipi_id']);
        $baslangic_tarihi = $_POST['baslangic_tarihi'];
        $bitis_tarihi = $_POST['bitis_tarihi'];
        $musait_oda_sayisi = intval($_POST['bulk_musait_oda_sayisi']);
        
        if ($oda_tipi_id > 0 && $baslangic_tarihi && $bitis_tarihi && $musait_oda_sayisi >= 0) {
            $current_date = new DateTime($baslangic_tarihi);
            $end_date = new DateTime($bitis_tarihi);
            $updated_count = 0;
            
            while ($current_date <= $end_date) {
                $tarih = $current_date->format('Y-m-d');
                
                // Mevcut kaydı kontrol et
                $mevcut = fetchOne("SELECT * FROM oda_musaitlik WHERE oda_tipi_id = ? AND tarih = ?", [$oda_tipi_id, $tarih]);
                
                if ($mevcut) {
                    executeQuery("UPDATE oda_musaitlik SET musait_oda_sayisi = ? WHERE oda_tipi_id = ? AND tarih = ?", 
                               [$musait_oda_sayisi, $oda_tipi_id, $tarih]);
                } else {
                    executeQuery("INSERT INTO oda_musaitlik (oda_tipi_id, tarih, musait_oda_sayisi) VALUES (?, ?, ?)", 
                               [$oda_tipi_id, $tarih, $musait_oda_sayisi]);
                }
                
                $updated_count++;
                $current_date->add(new DateInterval('P1D'));
            }
            
            $success_message = "$updated_count gün için müsaitlik başarıyla güncellendi.";
        } else {
            $error_message = 'Lütfen tüm alanları doldurun.';
        }
    }
}

// Filtreleme
$oda_tipi_filtre = $_GET['oda_tipi'] ?? '';
$tarih_filtre = $_GET['tarih'] ?? date('Y-m-d');
$ay_filtre = $_GET['ay'] ?? date('Y-m');

// Oda tiplerini getir
$oda_tipleri = fetchAll("SELECT * FROM oda_tipleri WHERE durum = 'aktif' ORDER BY sira_no ASC");

// Seçilen aya göre müsaitlik verilerini getir
$baslangic_tarihi = $ay_filtre . '-01';
$bitis_tarihi = date('Y-m-t', strtotime($baslangic_tarihi));

$musaitlik_verileri = [];
if ($oda_tipi_filtre) {
    $sql = "SELECT om.*, ot.oda_tipi_adi,
            (SELECT COUNT(*) FROM oda_numaralari on_table WHERE on_table.oda_tipi_id = om.oda_tipi_id AND on_table.durum = 'aktif') as toplam_oda
            FROM oda_musaitlik om
            LEFT JOIN oda_tipleri ot ON om.oda_tipi_id = ot.id
            WHERE om.oda_tipi_id = ? AND om.tarih BETWEEN ? AND ?
            ORDER BY om.tarih ASC";
    $musaitlik_verileri = fetchAll($sql, [$oda_tipi_filtre, $baslangic_tarihi, $bitis_tarihi]);
} else {
    $sql = "SELECT om.*, ot.oda_tipi_adi,
            (SELECT COUNT(*) FROM oda_numaralari odn WHERE odn.oda_tipi_id = om.oda_tipi_id AND odn.durum = 'aktif') as toplam_oda
            FROM oda_musaitlik om
            LEFT JOIN oda_tipleri ot ON om.oda_tipi_id = ot.id
            WHERE om.tarih BETWEEN ? AND ?
            ORDER BY om.oda_tipi_id ASC, om.tarih ASC";
    $musaitlik_verileri = fetchAll($sql, [$baslangic_tarihi, $bitis_tarihi]);
}

// Rezervasyon istatistikleri
$rezervasyon_stats = [];
foreach ($oda_tipleri as $oda_tipi) {
    $stats = fetchOne("
        SELECT 
            COUNT(*) as toplam_rezervasyon,
            SUM(CASE WHEN durum = 'beklemede' THEN 1 ELSE 0 END) as beklemede,
            SUM(CASE WHEN durum = 'onaylandi' THEN 1 ELSE 0 END) as onaylandi,
            SUM(CASE WHEN durum = 'check_in' THEN 1 ELSE 0 END) as check_in
        FROM rezervasyonlar 
        WHERE oda_tipi_id = ? 
        AND giris_tarihi BETWEEN ? AND ?
    ", [$oda_tipi['id'], $baslangic_tarihi, $bitis_tarihi]);
    
    $rezervasyon_stats[$oda_tipi['id']] = $stats;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müsaitlik Yönetimi - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .availability-calendar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .day-cell {
            min-height: 80px;
            border: 1px solid #e9ecef;
            padding: 8px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .day-cell:hover {
            background-color: #f8f9fa;
            border-color: #667eea;
        }
        
        .day-number {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .availability-info {
            font-size: 12px;
            line-height: 1.2;
        }
        
        .available-rooms {
            color: #28a745;
            font-weight: bold;
        }
        
        .reserved-rooms {
            color: #dc3545;
        }
        
        .weekend {
            background-color: #fff3cd;
        }
        
        .past-date {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .quick-actions {
            position: sticky;
            top: 100px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Müsaitlik Yönetimi</h1>
                            <p class="text-muted">Oda müsaitlik durumlarını yönetin</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                                <i class="fas fa-calendar-plus me-2"></i>Toplu Güncelleme
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickUpdateModal">
                                <i class="fas fa-plus me-2"></i>Hızlı Güncelleme
                            </button>
                        </div>
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

            <div class="row">
                <!-- Sol Taraf - Filtreler ve Takvim -->
                <div class="col-lg-9">
                    <!-- Filtreler -->
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-4">
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
                                <div class="col-md-4">
                                    <label for="ay" class="form-label">Ay</label>
                                    <input type="month" class="form-control" id="ay" name="ay" value="<?php echo $ay_filtre; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i>Filtrele
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Müsaitlik Takvimi -->
                    <div class="availability-calendar">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar me-2 text-primary"></i>
                                <?php echo date('F Y', strtotime($baslangic_tarihi)); ?> Müsaitlik Durumu
                            </h5>
                            <div class="d-flex gap-3">
                                <small><span class="badge bg-success me-1"></span>Müsait</small>
                                <small><span class="badge bg-warning me-1"></span>Kısıtlı</small>
                                <small><span class="badge bg-danger me-1"></span>Dolu</small>
                            </div>
                        </div>

                        <?php if ($oda_tipi_filtre): ?>
                        <!-- Tek oda tipi için detaylı görünüm -->
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Gün</th>
                                        <th>Toplam Oda</th>
                                        <th>Müsait Oda</th>
                                        <th>Rezerve Oda</th>
                                        <th>Doluluk Oranı</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_date = new DateTime($baslangic_tarihi);
                                    $end_date = new DateTime($bitis_tarihi);
                                    
                                    while ($current_date <= $end_date):
                                        $tarih = $current_date->format('Y-m-d');
                                        $gun_adi = $current_date->format('l');
                                        $gun_adi_tr = [
                                            'Monday' => 'Pazartesi',
                                            'Tuesday' => 'Salı', 
                                            'Wednesday' => 'Çarşamba',
                                            'Thursday' => 'Perşembe',
                                            'Friday' => 'Cuma',
                                            'Saturday' => 'Cumartesi',
                                            'Sunday' => 'Pazar'
                                        ][$gun_adi];
                                        
                                        // Bu tarih için müsaitlik verisi
                                        $musaitlik = null;
                                        foreach ($musaitlik_verileri as $veri) {
                                            if ($veri['tarih'] == $tarih) {
                                                $musaitlik = $veri;
                                                break;
                                            }
                                        }
                                        
                                        // Toplam oda sayısı
                                        $secili_oda_tipi = null;
                                        foreach ($oda_tipleri as $tip) {
                                            if ($tip['id'] == $oda_tipi_filtre) {
                                                $secili_oda_tipi = $tip;
                                                break;
                                            }
                                        }
                                        
                                        $toplam_oda = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE oda_tipi_id = ? AND durum = 'aktif'", [$oda_tipi_filtre])['sayi'];
                                        
                                        // Rezerve oda sayısı
                                        $rezerve_oda = fetchOne("
                                            SELECT COUNT(*) as sayi 
                                            FROM rezervasyonlar 
                                            WHERE oda_tipi_id = ? 
                                            AND durum NOT IN ('iptal') 
                                            AND ? BETWEEN giris_tarihi AND DATE_SUB(cikis_tarihi, INTERVAL 1 DAY)
                                        ", [$oda_tipi_filtre, $tarih])['sayi'];
                                        
                                        $musait_oda = $musaitlik ? $musaitlik['musait_oda_sayisi'] : ($toplam_oda - $rezerve_oda);
                                        $doluluk_orani = $toplam_oda > 0 ? round((($toplam_oda - $musait_oda) / $toplam_oda) * 100) : 0;
                                        
                                        $row_class = '';
                                        if ($current_date->format('N') >= 6) $row_class = 'table-warning'; // Hafta sonu
                                        if ($current_date < new DateTime()) $row_class = 'table-secondary'; // Geçmiş
                                    ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td><?php echo $current_date->format('d.m.Y'); ?></td>
                                        <td><?php echo $gun_adi_tr; ?></td>
                                        <td><span class="badge bg-info"><?php echo $toplam_oda; ?></span></td>
                                        <td>
                                            <span class="badge bg-<?php echo $musait_oda > 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $musait_oda; ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-warning"><?php echo $rezerve_oda; ?></span></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $doluluk_orani < 50 ? 'success' : ($doluluk_orani < 80 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $doluluk_orani; ?>%">
                                                    <?php echo $doluluk_orani; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="quickEdit('<?php echo $oda_tipi_filtre; ?>', '<?php echo $tarih; ?>', '<?php echo $musait_oda; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                        $current_date->add(new DateInterval('P1D'));
                                    endwhile;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <!-- Tüm oda tipleri için özet görünüm -->
                        <div class="row">
                            <?php foreach ($oda_tipleri as $oda_tipi): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $stats = $rezervasyon_stats[$oda_tipi['id']];
                                        $toplam_oda = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE oda_tipi_id = ? AND durum = 'aktif'", [$oda_tipi['id']])['sayi'];
                                        ?>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">Toplam Oda</small>
                                            <div class="h5 mb-0"><?php echo $toplam_oda; ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">Bu Ay Rezervasyonlar</small>
                                            <div class="d-flex justify-content-between">
                                                <span class="badge bg-warning"><?php echo $stats['beklemede']; ?> Beklemede</span>
                                                <span class="badge bg-success"><?php echo $stats['onaylandi']; ?> Onaylı</span>
                                                <span class="badge bg-info"><?php echo $stats['check_in']; ?> Aktif</span>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <a href="?oda_tipi=<?php echo $oda_tipi['id']; ?>&ay=<?php echo $ay_filtre; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>Detay Görüntüle
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sağ Taraf - Hızlı İşlemler -->
                <div class="col-lg-3">
                    <div class="quick-actions">
                        <h6 class="mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Hızlı İşlemler</h6>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#quickUpdateModal">
                                <i class="fas fa-edit me-1"></i>Tek Tarih Güncelle
                            </button>
                            
                            <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                                <i class="fas fa-calendar-plus me-1"></i>Toplu Güncelleme
                            </button>
                            
                            <a href="rezervasyonlar.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-calendar-check me-1"></i>Rezervasyonları Gör
                            </a>
                            
                            <a href="oda-numaralari.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-door-open me-1"></i>Oda Numaraları
                            </a>
                        </div>
                        
                        <hr>
                        
                        <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-info"></i>Bilgi</h6>
                        <div class="small text-muted">
                            <p><strong>Müsait Oda:</strong> Rezervasyon alınabilir oda sayısı</p>
                            <p><strong>Rezerve Oda:</strong> Aktif rezervasyonlu oda sayısı</p>
                            <p><strong>Doluluk Oranı:</strong> Toplam kapasiteye göre doluluk yüzdesi</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hızlı Güncelleme Modal -->
    <div class="modal fade" id="quickUpdateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Hızlı Müsaitlik Güncelleme</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_availability">
                        
                        <div class="mb-3">
                            <label for="modal_oda_tipi_id" class="form-label">Oda Tipi *</label>
                            <select class="form-select" id="modal_oda_tipi_id" name="oda_tipi_id" required>
                                <option value="">Oda tipi seçin</option>
                                <?php foreach ($oda_tipleri as $tip): ?>
                                <option value="<?php echo $tip['id']; ?>">
                                    <?php echo htmlspecialchars($tip['oda_tipi_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_tarih" class="form-label">Tarih *</label>
                            <input type="date" class="form-control" id="modal_tarih" name="tarih" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_musait_oda_sayisi" class="form-label">Müsait Oda Sayısı *</label>
                            <input type="number" class="form-control" id="modal_musait_oda_sayisi" 
                                   name="musait_oda_sayisi" min="0" max="100" required>
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

    <!-- Toplu Güncelleme Modal -->
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Toplu Müsaitlik Güncelleme</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_update">
                        
                        <div class="mb-3">
                            <label for="bulk_oda_tipi_id" class="form-label">Oda Tipi *</label>
                            <select class="form-select" id="bulk_oda_tipi_id" name="bulk_oda_tipi_id" required>
                                <option value="">Oda tipi seçin</option>
                                <?php foreach ($oda_tipleri as $tip): ?>
                                <option value="<?php echo $tip['id']; ?>">
                                    <?php echo htmlspecialchars($tip['oda_tipi_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi *</label>
                                <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi *</label>
                                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk_musait_oda_sayisi" class="form-label">Müsait Oda Sayısı *</label>
                            <input type="number" class="form-control" id="bulk_musait_oda_sayisi" 
                                   name="bulk_musait_oda_sayisi" min="0" max="100" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Seçilen tarih aralığındaki tüm günler için müsaitlik güncellenir.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">Toplu Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Hızlı düzenleme
        function quickEdit(odaTipiId, tarih, mevcutSayi) {
            document.getElementById('modal_oda_tipi_id').value = odaTipiId;
            document.getElementById('modal_tarih').value = tarih;
            document.getElementById('modal_musait_oda_sayisi').value = mevcutSayi;
            
            new bootstrap.Modal(document.getElementById('quickUpdateModal')).show();
        }
        
        // Tarih validasyonu
        document.getElementById('baslangic_tarihi').addEventListener('change', function() {
            document.getElementById('bitis_tarihi').min = this.value;
        });
    </script>
</body>
</html>
