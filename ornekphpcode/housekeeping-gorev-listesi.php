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
if (!hasDetailedPermission('housekeeping_gorev_goruntule')) {
    $_SESSION['error_message'] = 'Housekeeping görev listesi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'gorev_durum_guncelle') {
            $gorev_id = intval($_POST['gorev_id']);
            $yeni_durum = sanitizeString($_POST['yeni_durum']);
            $aciklama = sanitizeString($_POST['aciklama'] ?? '');
            
            $sql = "UPDATE temizlik_kayitlari SET durum = ?, aciklama = ? WHERE id = ?";
            if (executeQuery($sql, [$yeni_durum, $aciklama, $gorev_id])) {
                $success_message = 'Görev durumu başarıyla güncellendi.';
            } else {
                $error_message = 'Görev durumu güncellenirken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Görevleri getir
$gorevler = fetchAll("
    SELECT tk.*, oda_numaralari.oda_numarasi, oda_numaralari.kat, ot.oda_tipi_adi,
           k.ad as housekeeper_adi, k.soyad as housekeeper_soyadi
    FROM temizlik_kayitlari tk
    LEFT JOIN oda_numaralari ON tk.oda_id = oda_numaralari.id
    LEFT JOIN oda_tipleri ot ON oda_numaralari.oda_tipi_id = ot.id
    LEFT JOIN kullanicilar k ON tk.housekeeper_id = k.id
    ORDER BY tk.temizlik_tarihi DESC, tk.olusturma_tarihi DESC
");

// Görev durumları
$gorev_durumlari = [
    'beklemede' => 'Beklemede',
    'devam_ediyor' => 'Devam Ediyor',
    'tamamlandi' => 'Tamamlandı',
    'onay_bekliyor' => 'Onay Bekliyor',
    'reddedildi' => 'Reddedildi'
];

// Durum renkleri
$durum_renkleri = [
    'beklemede' => 'secondary',
    'devam_ediyor' => 'warning',
    'tamamlandi' => 'success',
    'onay_bekliyor' => 'info',
    'reddedildi' => 'danger'
];

// Temizlik türleri
$temizlik_turleri = [
    'checkout' => 'Check-out Temizliği',
    'günlük' => 'Günlük Temizlik',
    'derin' => 'Derin Temizlik',
    'bakım' => 'Bakım Temizliği',
    'özel' => 'Özel Temizlik'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Housekeeping Görev Listesi - Otel Yönetim Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tasks me-2"></i>Housekeeping Görev Listesi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="housekeeping-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Dashboard
                        </a>
                        <a href="housekeeping-gorev-olustur.php" class="btn btn-primary ms-2">
                            <i class="fas fa-plus me-1"></i>Yeni Görev
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

                <!-- Filtreler -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>Görev Filtreleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="durum_filtre" class="form-label">Durum</label>
                                <select class="form-select" id="durum_filtre">
                                    <option value="">Tüm Durumlar</option>
                                    <?php foreach ($gorev_durumlari as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="temizlik_turu_filtre" class="form-label">Temizlik Türü</label>
                                <select class="form-select" id="temizlik_turu_filtre">
                                    <option value="">Tüm Türler</option>
                                    <?php foreach ($temizlik_turleri as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="tarih_filtre" class="form-label">Tarih</label>
                                <input type="date" class="form-control" id="tarih_filtre">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-primary" onclick="filtrele()">
                                        <i class="fas fa-search me-1"></i>Filtrele
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Görev Listesi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Temizlik Görevleri
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Görev ID</th>
                                        <th>Oda</th>
                                        <th>Temizlik Türü</th>
                                        <th>Atanan Personel</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                        <th>Süre</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gorevler as $gorev): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $gorev['id']; ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo $gorev['oda_numarasi']; ?></strong>
                                                <br><small class="text-muted"><?php echo $gorev['oda_tipi_adi']; ?> - Kat: <?php echo $gorev['kat']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $temizlik_turleri[$gorev['temizlik_turu']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($gorev['atanan_adi']): ?>
                                                <?php echo htmlspecialchars($gorev['atanan_adi'] . ' ' . $gorev['atanan_soyadi']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Atanmamış</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $durum_renkleri[$gorev['durum']]; ?>">
                                                <?php echo $gorev_durumlari[$gorev['durum']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo date('d.m.Y', strtotime($gorev['temizlik_tarihi'])); ?></strong>
                                                <br><small class="text-muted"><?php echo date('H:i', strtotime($gorev['olusturma_tarihi'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($gorev['baslama_zamani'] && $gorev['bitis_zamani']): ?>
                                                <?php 
                                                $baslama = new DateTime($gorev['baslama_zamani']);
                                                $bitis = new DateTime($gorev['bitis_zamani']);
                                                $sure = $baslama->diff($bitis);
                                                echo $sure->format('%H:%I');
                                                ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#statusModal"
                                                        data-gorev-id="<?php echo $gorev['id']; ?>"
                                                        data-mevcut-durum="<?php echo $gorev['durum']; ?>">
                                                    <i class="fas fa-edit"></i> Durum
                                                </button>
                                                <a href="housekeeping-gorev-detay.php?id=<?php echo $gorev['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Detay">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
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
                    <h5 class="modal-title">Görev Durumu Değiştir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="gorev_durum_guncelle">
                        <input type="hidden" name="gorev_id" id="modal_gorev_id">
                        
                        <div class="mb-3">
                            <label for="yeni_durum" class="form-label">Yeni Durum:</label>
                            <select class="form-select" id="yeni_durum" name="yeni_durum" required>
                                <option value="">Durum Seçin</option>
                                <?php foreach ($gorev_durumlari as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama:</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3"></textarea>
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
            const gorevId = button.getAttribute('data-gorev-id');
            const mevcutDurum = button.getAttribute('data-mevcut-durum');
            
            document.getElementById('modal_gorev_id').value = gorevId;
            document.getElementById('yeni_durum').value = mevcutDurum;
        });

        // Filtreleme fonksiyonu
        function filtrele() {
            const durum = document.getElementById('durum_filtre').value;
            const temizlikTuru = document.getElementById('temizlik_turu_filtre').value;
            const tarih = document.getElementById('tarih_filtre').value;
            
            // Burada filtreleme mantığı implement edilebilir
            console.log('Filtreleme:', { durum, temizlikTuru, tarih });
        }
    </script>
</body>
</html>
