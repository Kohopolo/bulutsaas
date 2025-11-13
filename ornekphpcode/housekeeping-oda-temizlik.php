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
if (!hasDetailedPermission('housekeeping_oda_temizlik')) {
    $_SESSION['error_message'] = 'Oda temizlik yönetimi yetkiniz bulunmamaktadır.';
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
        
        if ($action == 'update_status') {
            $oda_id = intval($_POST['oda_id']);
            $yeni_durum = sanitizeString($_POST['yeni_durum']);
            $aciklama = sanitizeString($_POST['aciklama']);
            
            // Durum güncelleme
            $guncellenecek_durum = $yeni_durum;
            
            // Oda durumunu güncelle
            $sql = "UPDATE oda_numaralari SET durum = ? WHERE id = ?";
            $result = executeQuery($sql, [$guncellenecek_durum, $oda_id]);
            
            if ($result) {
                // Oda geçmişine kaydet (orijinal seçilen durumu kaydet)
                $gecmis_sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                              VALUES (?, (SELECT durum FROM oda_numaralari WHERE id = ?), ?, ?, ?, NOW())";
                $gecmis_aciklama = ($yeni_durum == 'müsait') ? 
                    'Temizlik tamamlandı - Oda müsait (Aktif olarak işaretlendi): ' . $aciklama : 
                    $aciklama;
                executeQuery($gecmis_sql, [$oda_id, $oda_id, $guncellenecek_durum, $gecmis_aciklama, $_SESSION['user_id']]);
                
                $pdo->commit();
                $success_message = ($yeni_durum == 'müsait') ? 
                    'Temizlik tamamlandı! Oda müsait ve aktif olarak işaretlendi.' : 
                    'Oda durumu başarıyla güncellendi.';
            } else {
                throw new Exception('Oda durumu güncellenirken hata oluştu.');
            }
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Oda durumları
$oda_durumlari = [
    'aktif' => 'Aktif',
    'dolu' => 'Dolu',
    'rezerve' => 'Rezerve',
    'temizlik_bekliyor' => 'Temizlik Bekliyor',
    'temizlik_yapiliyor' => 'Temizlik Yapılıyor',
    'bakim' => 'Bakım',
    'kapali' => 'Kapalı',
    'devre_disi' => 'Devre Dışı',
    '' => 'Tanımsız',
    null => 'Tanımsız'
];

// Durum renkleri
$durum_renkleri = [
    'aktif' => 'success',
    'dolu' => 'primary',
    'rezerve' => 'warning',
    'temizlik_bekliyor' => 'warning',
    'temizlik_yapiliyor' => 'info',
    'bakim' => 'danger',
    'kapali' => 'secondary',
    'devre_disi' => 'dark',
    '' => 'secondary',
    null => 'secondary'
];

// Odaları getir
$odalar = fetchAll("
    SELECT oda_numaralari.id, oda_numaralari.oda_numarasi, ot.oda_tipi_adi as oda_tipi, oda_numaralari.durum, 
           COUNT(tk.id) as aktif_temizlik_sayisi,
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
                             AND oda_numaralari.durum = 'aktif'
                             THEN 1 END) > 0 THEN 'temizlik_bekliyor'
               
               -- Varsayılan: Oda durumu
               ELSE oda_numaralari.durum
           END as final_durum
    FROM oda_numaralari
    LEFT JOIN oda_tipleri ot ON oda_numaralari.oda_tipi_id = ot.id
    LEFT JOIN temizlik_kayitlari tk ON oda_numaralari.id = tk.oda_id AND tk.durum IN ('devam_ediyor', 'onay_bekliyor')
    LEFT JOIN rezervasyonlar r ON oda_numaralari.id = r.oda_numarasi_id AND r.durum IN ('onaylandi', 'check_in', 'check_out')
    GROUP BY oda_numaralari.id, oda_numaralari.oda_numarasi, ot.oda_tipi_adi, oda_numaralari.durum
    ORDER BY oda_numaralari.oda_numarasi
");

// Temizlik bekleyen odalar
$temizlik_bekleyen = array_filter($odalar, function($oda) {
    return $oda['final_durum'] == 'temizlik_bekliyor';
});

// Temizlik yapılan odalar
$temizlik_yapilan = array_filter($odalar, function($oda) {
    return $oda['final_durum'] == 'temizlik_yapiliyor';
});

// Müsait odalar (aktif durumundaki odalar)
$musait_odalar = array_filter($odalar, function($oda) {
    return $oda['final_durum'] == 'aktif';
});

error_log("Housekeeping Oda Temizlik - HTML çıktısı başlıyor");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oda Temizlik Yönetimi - Housekeeping</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .temizlik-uyari {
            background: linear-gradient(45deg, #fff3cd, #ffeaa7);
            border-left: 4px solid #f39c12;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        
        .temizlik-uyari h6 {
            color: #856404;
            margin: 0;
        }
        
        .temizlik-uyari small {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-bed me-2"></i>Oda Temizlik Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="housekeeping-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Dashboard
                        </a>
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

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Temizlik Bekleyen</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($temizlik_bekleyen); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Temizlik Yapılan</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($temizlik_yapilan); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-broom fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Müsait Odalar</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($musait_odalar); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Toplam Oda</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($odalar); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-bed fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (count($temizlik_yapilan) > 0): ?>
                <!-- Temizlik Yapılan Odalar Uyarısı -->
                <div class="temizlik-uyari">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Temizlik Tamamlanan Odalar</h6>
                    <small>
                        <strong><?php echo count($temizlik_yapilan); ?></strong> odada temizlik tamamlandı. 
                        Bu odaları müsait duruma geçirmeyi unutmayın!
                    </small>
                </div>
                <?php endif; ?>

                <!-- Oda Listesi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Oda Durumları
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Oda No</th>
                                        <th>Oda Tipi</th>
                                        <th>Mevcut Durum</th>
                                        <th>Aktif Temizlik</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($odalar as $oda): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($oda['oda_numarasi']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($oda['oda_tipi']); ?></td>
                                        <td>
                                            <?php 
                                                $durum_text = trim($oda['final_durum']);
                                                if (empty($durum_text)) {
                                                    $durum_text = 'aktif';
                                                }
                                            ?>
                                            <?php if ($durum_text == 'temizlik_yapiliyor'): ?>
                                                <span class="badge bg-warning text-dark" style="animation: pulse 2s infinite;">
                                                    <i class="fas fa-broom me-1"></i>
                                                    <?php echo $oda_durumlari[$durum_text]; ?>
                                                </span>
                                                <small class="text-warning d-block">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    Temizlik tamamlandığında müsait yapın!
                                                </small>
                                            <?php elseif ($durum_text == 'rezerve'): ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-calendar-check me-1"></i>
                                                    Rezerve
                                                </span>
                                            <?php elseif ($durum_text == 'dolu'): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-user me-1"></i>
                                                    Dolu
                                                </span>
                                            <?php elseif ($durum_text == 'checkout_oncesi_dolu'): ?>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Dolu (Checkout Bekliyor)
                                                </span>
                                            <?php elseif ($durum_text == 'temizlik_bekliyor'): ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-broom me-1"></i>
                                                    Temizlik Bekliyor
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-<?php echo $durum_renkleri[$durum_text] ?? 'success'; ?>">
                                                    <?php echo $oda_durumlari[$durum_text] ?? ucfirst(str_replace('_', ' ', $durum_text)); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($oda['aktif_temizlik_sayisi'] > 0): ?>
                                                <span class="badge bg-info">
                                                    <?php echo $oda['aktif_temizlik_sayisi']; ?> Aktif
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($durum_text == 'temizlik_yapiliyor'): ?>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            data-bs-toggle="modal" data-bs-target="#statusModal"
                                                            data-oda-id="<?php echo $oda['id']; ?>"
                                                            data-oda-numara="<?php echo htmlspecialchars($oda['oda_numarasi']); ?>"
                                                            data-mevcut-durum="müsait"
                                                            data-hizli-temizlik="true">
                                                        <i class="fas fa-check"></i> Müsait Yap
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#statusModal"
                                                            data-oda-id="<?php echo $oda['id']; ?>"
                                                            data-oda-numara="<?php echo htmlspecialchars($oda['oda_numarasi']); ?>"
                                                            data-mevcut-durum="<?php echo $oda['durum']; ?>">
                                                        <i class="fas fa-edit"></i> Diğer
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#statusModal"
                                                        data-oda-id="<?php echo $oda['id']; ?>"
                                                        data-oda-numara="<?php echo htmlspecialchars($oda['oda_numarasi']); ?>"
                                                        data-mevcut-durum="<?php echo $oda['durum']; ?>">
                                                    <i class="fas fa-edit"></i> Durum Değiştir
                                                </button>
                                            <?php endif; ?>
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

    <!-- Durum Değiştirme Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Oda Durumu Değiştir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="oda_id" id="modal_oda_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Oda:</label>
                            <p class="form-control-plaintext" id="modal_oda_numarasi"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="yeni_durum" class="form-label">Yeni Durum:</label>
                            <select class="form-select" id="yeni_durum" name="yeni_durum" required>
                                <option value="">Durum Seçin</option>
                                <?php foreach ($oda_durumlari as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama:</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3" 
                                      placeholder="Durum değişikliği açıklaması..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Durumu Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Modal event listener
        document.getElementById('statusModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const odaId = button.getAttribute('data-oda-id');
            const odaNumara = button.getAttribute('data-oda-numara');
            const mevcutDurum = button.getAttribute('data-mevcut-durum');
            const hizliTemizlik = button.getAttribute('data-hizli-temizlik');
            
            document.getElementById('modal_oda_id').value = odaId;
            document.getElementById('modal_oda_numarasi').textContent = odaNumara;
            document.getElementById('yeni_durum').value = mevcutDurum;
            
            // Hızlı temizlik butonu için özel mesaj
            if (hizliTemizlik === 'true') {
                const modalTitle = document.querySelector('#statusModal .modal-title');
                modalTitle.textContent = 'Oda ' + odaNumara + ' - Temizlik Tamamlandı';
                document.getElementById('yeni_durum').value = 'aktif';
                
                // Form submit butonunu vurgula
                const submitBtn = document.querySelector('#statusModal button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-check me-1"></i>Temizlik Tamamlandı - Müsait Yap';
                submitBtn.className = 'btn btn-success';
            } else {
                // Normal durum için butonu sıfırla
                const submitBtn = document.querySelector('#statusModal button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Durumu Güncelle';
                submitBtn.className = 'btn btn-primary';
            }
        });
        
        // Temizlik yapılan odalar için otomatik uyarı
        document.addEventListener('DOMContentLoaded', function() {
            const temizlikOdalar = document.querySelectorAll('tr');
            let temizlikSayisi = 0;
            
            temizlikOdalar.forEach(function(row) {
                const durumBadge = row.querySelector('.badge.bg-warning');
                if (durumBadge && durumBadge.textContent.includes('Temizlik Yapılıyor')) {
                    temizlikSayisi++;
                    row.style.backgroundColor = '#fff3cd';
                    row.style.borderLeft = '4px solid #f39c12';
                }
            });
            
            // Temizlik yapılan odalar varsa uyarı göster
            if (temizlikSayisi > 0) {
                setTimeout(function() {
                    alert('⚠️ ' + temizlikSayisi + ' odada temizlik tamamlandı!\nBu odaları müsait duruma geçirmeyi unutmayın.');
                }, 3000);
            }
        });
    </script>
</body>
</html>
