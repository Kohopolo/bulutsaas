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
if (!hasDetailedPermission('housekeeping_kalite_kontrol')) {
    $_SESSION['error_message'] = 'Housekeeping kalite kontrol yetkiniz bulunmamaktadır.';
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
        
        if ($action == 'kalite_kontrol') {
            $temizlik_id = intval($_POST['temizlik_id']);
            $kalite_puani = intval($_POST['kalite_puani']);
            $notlar = sanitizeString($_POST['notlar']);
            $onay_durumu = sanitizeString($_POST['onay_durumu']);
            
            // Kalite kontrol kaydı oluştur
            $sql = "INSERT INTO temizlik_kalite_kontrol (temizlik_id, kalite_puani, notlar, onay_durumu, kontrol_eden_id, kontrol_tarihi) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $result = executeQuery($sql, [$temizlik_id, $kalite_puani, $notlar, $onay_durumu, $_SESSION['user_id']]);
            
            if ($result) {
                // Temizlik durumunu güncelle
                $update_sql = "UPDATE temizlik_kayitlari SET durum = ? WHERE id = ?";
                executeQuery($update_sql, [$onay_durumu, $temizlik_id]);
                
                $pdo->commit();
                $success_message = 'Kalite kontrolü başarıyla tamamlandı.';
            } else {
                throw new Exception('Kalite kontrolü kaydedilirken hata oluştu.');
            }
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Kalite kontrol bekleyen temizlikler
$bekleyen_temizlikler = fetchAll("
    SELECT tk.*, oda_numaralari.oda_numarasi, ot.oda_tipi_adi as oda_tipi, k.ad as housekeeper_adi, k.soyad as housekeeper_soyadi
    FROM temizlik_kayitlari tk
    LEFT JOIN oda_numaralari ON tk.oda_id = oda_numaralari.id
    LEFT JOIN oda_tipleri ot ON oda_numaralari.oda_tipi_id = ot.id
    LEFT JOIN kullanicilar k ON tk.housekeeper_id = k.id
    WHERE tk.durum = 'onay_bekliyor'
    ORDER BY tk.temizlik_tarihi DESC
");

// Kalite kontrol geçmişi
$kalite_gecmisi = fetchAll("
    SELECT kk.*, tk.oda_id, oda_numaralari.oda_numarasi, ot.oda_tipi_adi as oda_tipi, 
           k1.ad as housekeeper_adi, k1.soyad as housekeeper_soyadi,
           k2.ad as kontrol_eden_adi, k2.soyad as kontrol_eden_soyadi
    FROM temizlik_kalite_kontrol kk
    LEFT JOIN temizlik_kayitlari tk ON kk.temizlik_id = tk.id
    LEFT JOIN oda_numaralari ON tk.oda_id = oda_numaralari.id
    LEFT JOIN oda_tipleri ot ON oda_numaralari.oda_tipi_id = ot.id
    LEFT JOIN kullanicilar k1 ON tk.housekeeper_id = k1.id
    LEFT JOIN kullanicilar k2 ON kk.kontrol_eden_id = k2.id
    ORDER BY kk.kontrol_tarihi DESC
    LIMIT 50
");

// Kalite puanları
$kalite_puanlari = [
    1 => 'Çok Kötü',
    2 => 'Kötü',
    3 => 'Orta',
    4 => 'İyi',
    5 => 'Mükemmel'
];

error_log("Housekeeping Kalite Kontrol - HTML çıktısı başlıyor");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalite Kontrol - Housekeeping</title>
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
                    <h1 class="h2"><i class="fas fa-check-circle text-success"></i> Kalite Kontrol</h1>
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

                <!-- Bekleyen Kalite Kontrolleri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock text-warning"></i> Bekleyen Kalite Kontrolleri
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bekleyen_temizlikler)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <p>Bekleyen kalite kontrolü bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Oda</th>
                                            <th>Housekeeper</th>
                                            <th>Temizlik Türü</th>
                                            <th>Tarih</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bekleyen_temizlikler as $temizlik): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($temizlik['oda_numarasi']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($temizlik['oda_tipi']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($temizlik['housekeeper_adi'] . ' ' . $temizlik['housekeeper_soyadi']); ?></td>
                                                <td><?php echo htmlspecialchars($temizlik['temizlik_turu']); ?></td>
                                                <td><?php echo date('d.m.Y H:i', strtotime($temizlik['temizlik_tarihi'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kaliteModal<?php echo $temizlik['id']; ?>">
                                                        <i class="fas fa-check"></i> Kontrol Et
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kalite Kontrol Geçmişi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history text-info"></i> Kalite Kontrol Geçmişi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Oda</th>
                                        <th>Housekeeper</th>
                                        <th>Kalite Puanı</th>
                                        <th>Durum</th>
                                        <th>Kontrol Eden</th>
                                        <th>Tarih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kalite_gecmisi as $kalite): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($kalite['oda_numarasi']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($kalite['oda_tipi']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($kalite['housekeeper_adi'] . ' ' . $kalite['housekeeper_soyadi']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $kalite['kalite_puani'] >= 4 ? 'success' : ($kalite['kalite_puani'] >= 3 ? 'warning' : 'danger'); ?>">
                                                    <?php echo $kalite['kalite_puani']; ?>/5
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $kalite['onay_durumu'] == 'onaylandi' ? 'success' : 'danger'; ?>">
                                                    <?php echo $kalite['onay_durumu'] == 'onaylandi' ? 'Onaylandı' : 'Reddedildi'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($kalite['kontrol_eden_adi'] . ' ' . $kalite['kontrol_eden_soyadi']); ?></td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($kalite['kontrol_tarihi'])); ?></td>
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

    <!-- Kalite Kontrol Modal -->
    <?php foreach ($bekleyen_temizlikler as $temizlik): ?>
        <div class="modal fade" id="kaliteModal<?php echo $temizlik['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Kalite Kontrol - Oda <?php echo htmlspecialchars($temizlik['oda_numarasi']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="kalite_kontrol">
                            <input type="hidden" name="temizlik_id" value="<?php echo $temizlik['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Kalite Puanı</label>
                                <select class="form-select" name="kalite_puani" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($kalite_puanlari as $puan => $aciklama): ?>
                                        <option value="<?php echo $puan; ?>"><?php echo $puan; ?> - <?php echo $aciklama; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Onay Durumu</label>
                                <select class="form-select" name="onay_durumu" required>
                                    <option value="">Seçiniz</option>
                                    <option value="onaylandi">Onaylandı</option>
                                    <option value="reddedildi">Reddedildi</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notlar</label>
                                <textarea class="form-control" name="notlar" rows="3" placeholder="Kalite kontrol notları..."></textarea>
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
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
