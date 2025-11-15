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
if (!hasDetailedPermission('fnb_siparis_yonetimi')) {
    $_SESSION['error_message'] = 'F&B sipariş yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action']);
        $siparis_id = intval($_POST['siparis_id']);
        
        if ($action == 'durum_guncelle') {
            $yeni_durum = sanitizeString($_POST['yeni_durum']);
            
            $sql = "UPDATE fnb_siparisler SET durum = ? WHERE id = ?";
            if (executeQuery($sql, [$yeni_durum, $siparis_id])) {
                // Sipariş hazırlanmaya başlandığında stok düşür
                if ($yeni_durum == 'hazirlaniyor') {
                    require_once '../includes/inventory-automation.php';
                    $inventory = new InventoryAutomation($pdo);
                    $stockResult = $inventory->processFnbOrderStock($siparis_id);
                    
                    if ($stockResult['success']) {
                        $success_message = 'Sipariş durumu güncellendi ve stok hareketleri oluşturuldu.';
                    } else {
                        $success_message = 'Sipariş durumu güncellendi ancak stok işlemi başarısız: ' . $stockResult['message'];
                    }
                } elseif ($yeni_durum == 'hazir') {
                    // Sipariş hazır olduğunda bildirim gönder
                    require_once '../includes/notification-system.php';
                    $notification = new NotificationSystem($pdo);
                    $notificationResult = $notification->sendFnbOrderReadyNotification($siparis_id);
                    
                    $success_message = 'Sipariş durumu güncellendi ve hazır bildirimi gönderildi.';
                } else {
                    $success_message = 'Sipariş durumu başarıyla güncellendi.';
                }
            } else {
                $error_message = 'Sipariş durumu güncellenirken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Siparişleri getir
$siparisler = fetchAll("
    SELECT fs.*, oda_numaralari.oda_numarasi, m.ad as musteri_adi, m.soyad as musteri_soyadi,
           k1.ad as siparis_alan_adi, k1.soyad as siparis_alan_soyadi,
           k2.ad as hazirlayan_adi, k2.soyad as hazirlayan_soyadi
    FROM fnb_siparisler fs
    LEFT JOIN oda_numaralari ON fs.oda_id = oda_numaralari.id
    LEFT JOIN musteriler m ON fs.musteri_id = m.id
    LEFT JOIN kullanicilar k1 ON fs.siparis_alan_id = k1.id
    LEFT JOIN kullanicilar k2 ON fs.hazirlayan_id = k2.id
    ORDER BY fs.siparis_tarihi DESC
    LIMIT 50
");

// Sipariş durumları
$siparis_durumlari = [
    'alindi' => 'Alındı',
    'hazirlaniyor' => 'Hazırlanıyor',
    'hazir' => 'Hazır',
    'servis_edildi' => 'Servis Edildi',
    'iptal' => 'İptal'
];

// Durum renkleri
$durum_renkleri = [
    'alindi' => 'primary',
    'hazirlaniyor' => 'warning',
    'hazir' => 'info',
    'servis_edildi' => 'success',
    'iptal' => 'danger'
];

// Departman renkleri
$departman_renkleri = [
    'mutfak' => 'danger',
    'restoran' => 'success',
    'bar' => 'info',
    'pastane' => 'warning'
];

// Departman metinleri
$departman_metinleri = [
    'mutfak' => 'Mutfak',
    'restoran' => 'Restoran',
    'bar' => 'Bar',
    'pastane' => 'Pastane'
];

error_log("F&B Sipariş Yönetimi - HTML çıktısı başlıyor");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F&B Sipariş Yönetimi - Otel Yönetim Sistemi</title>
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
                    <h1 class="h2"><i class="fas fa-list me-2"></i>F&B Sipariş Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="fnb-dashboard.php" class="btn btn-outline-secondary">
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

                <!-- Sipariş Listesi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Siparişler
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Sipariş No</th>
                                        <th>Oda</th>
                                        <th>Müşteri</th>
                                        <th>Departman</th>
                                        <th>Tutar</th>
                                        <th>Durum</th>
                                        <th>Sipariş Alan</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($siparisler as $siparis): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($siparis['siparis_no']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo $siparis['oda_numarasi'] ? htmlspecialchars($siparis['oda_numarasi']) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $siparis['musteri_adi'] ? htmlspecialchars($siparis['musteri_adi'] . ' ' . $siparis['musteri_soyadi']) : 'Misafir'; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $departman_renkleri[$siparis['departman']]; ?>">
                                                <?php echo $departman_metinleri[$siparis['departman']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($siparis['toplam_tutar'], 2); ?>₺</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $durum_renkleri[$siparis['siparis_durumu']]; ?>">
                                                <?php echo $siparis_durumlari[$siparis['siparis_durumu']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $siparis['siparis_alan_adi'] ? htmlspecialchars($siparis['siparis_alan_adi'] . ' ' . $siparis['siparis_alan_soyadi']) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d.m.Y H:i', strtotime($siparis['siparis_tarihi'])); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#statusModal"
                                                        data-siparis-id="<?php echo $siparis['id']; ?>"
                                                        data-mevcut-durum="<?php echo $siparis['siparis_durumu']; ?>">
                                                    <i class="fas fa-edit"></i> Durum
                                                </button>
                                                <a href="fnb-siparis-detay.php?id=<?php echo $siparis['id']; ?>" 
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
                    <h5 class="modal-title">Sipariş Durumu Değiştir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="durum_guncelle">
                        <input type="hidden" name="siparis_id" id="modal_siparis_id">
                        
                        <div class="mb-3">
                            <label for="yeni_durum" class="form-label">Yeni Durum:</label>
                            <select class="form-select" id="yeni_durum" name="yeni_durum" required>
                                <option value="">Durum Seçin</option>
                                <?php foreach ($siparis_durumlari as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
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
            const siparisId = button.getAttribute('data-siparis-id');
            const mevcutDurum = button.getAttribute('data-mevcut-durum');
            
            document.getElementById('modal_siparis_id').value = siparisId;
            document.getElementById('yeni_durum').value = mevcutDurum;
        });
    </script>
</body>
</html>
