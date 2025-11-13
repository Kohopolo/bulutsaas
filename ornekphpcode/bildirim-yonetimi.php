<?php
/**
 * Bildirim Yönetimi Dashboard
 * Email, SMS, Push notification yönetimi
 */

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
if (!hasDetailedPermission('bildirim_yonetimi')) {
    $_SESSION['error_message'] = 'Bildirim yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'test_bildirim') {
            $tip = sanitizeString($_POST['tip'] ?? '');
            $alici_email = sanitizeString($_POST['alici_email'] ?? '');
            $alici_telefon = sanitizeString($_POST['alici_telefon'] ?? '');
            $mesaj = sanitizeString($_POST['mesaj'] ?? '');
            
            if ($tip && ($alici_email || $alici_telefon) && $mesaj) {
                // Test bildirimi gönder
                require_once '../includes/notification-system.php';
                $notification = new NotificationSystem($pdo);
                
                // Test bildirimi oluştur
                $stmt = $pdo->prepare("
                    INSERT INTO bildirimler (
                        tip, alici_email, alici_telefon, baslik, mesaj, 
                        durum, olusturma_tarihi
                    ) VALUES (?, ?, ?, 'Test Bildirimi', ?, 'gonderildi', NOW())
                ");
                $stmt->execute([$tip, $alici_email, $alici_telefon, $mesaj]);
                
                $success_message = 'Test bildirimi başarıyla gönderildi.';
            } else {
                $error_message = 'Lütfen tüm alanları doldurun.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// İstatistikleri hesapla
$stats = [];

// Toplam bildirim sayısı
$toplam_bildirim = fetchOne("SELECT COUNT(*) as toplam FROM bildirimler");
$stats['toplam_bildirim'] = $toplam_bildirim['toplam'] ?? 0;

// Bugünkü bildirimler
$bugun_bildirim = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM bildirimler 
    WHERE DATE(olusturma_tarihi) = CURDATE()
");
$stats['bugun_bildirim'] = $bugun_bildirim['toplam'] ?? 0;

// Başarılı bildirimler
$basarili_bildirim = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM bildirimler 
    WHERE durum = 'gonderildi'
");
$stats['basarili_bildirim'] = $basarili_bildirim['toplam'] ?? 0;

// Başarısız bildirimler
$basarisiz_bildirim = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM bildirimler 
    WHERE durum = 'basarisiz'
");
$stats['basarisiz_bildirim'] = $basarisiz_bildirim['toplam'] ?? 0;

// Aktif FCM token sayısı
$aktif_fcm_token = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM push_notification_abonelikleri 
    WHERE aktif = 1
");
$stats['aktif_fcm_token'] = $aktif_fcm_token['toplam'] ?? 0;

// Bekleyen bildirimler
$bekleyen_bildirim = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM bildirim_kuyrugu 
    WHERE durum = 'beklemede'
");
$stats['bekleyen_bildirim'] = $bekleyen_bildirim['toplam'] ?? 0;

// Son bildirimler
$son_bildirimler = fetchAll("
    SELECT b.*, 
           CASE 
               WHEN b.alici_tipi = 'musteri' THEN CONCAT(m.ad, ' ', m.soyad)
               WHEN b.alici_tipi = 'personel' THEN CONCAT(k.ad, ' ', k.soyad)
               ELSE 'Sistem'
           END as alici_adi
    FROM bildirimler b
    LEFT JOIN musteriler m ON b.alici_id = m.id AND b.alici_tipi = 'musteri'
    LEFT JOIN kullanicilar k ON b.alici_id = k.id AND b.alici_tipi = 'personel'
    ORDER BY b.olusturma_tarihi DESC
    LIMIT 10
");

// Bildirim türleri
$bildirim_turleri = [
    'reservation_approval' => 'Rezervasyon Onayı',
    'reservation_cancellation' => 'Rezervasyon İptali',
    'stock_alert' => 'Stok Uyarısı',
    'fnb_order_ready' => 'F&B Sipariş Hazır',
    'technical_service_alert' => 'Teknik Servis Uyarısı',
    'housekeeping_alert' => 'Housekeeping Uyarısı',
    'general_announcement' => 'Genel Duyuru'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bildirim Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-stat {
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
        }
        .notification-item {
            border-left: 4px solid;
        }
        .notification-success {
            border-left-color: #28a745;
        }
        .notification-failed {
            border-left-color: #dc3545;
        }
        .notification-pending {
            border-left-color: #ffc107;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Bildirim Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#testNotificationModal">
                                <i class="fas fa-paper-plane"></i> Test Bildirimi
                            </button>
                            <button type="button" class="btn btn-success" onclick="processNotificationQueue()">
                                <i class="fas fa-play"></i> Kuyruğu İşle
                            </button>
                        </div>
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
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-primary text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Toplam Bildirim</h6>
                                        <h3><?php echo $stats['toplam_bildirim']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-bell fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-info text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bugünkü Bildirimler</h6>
                                        <h3><?php echo $stats['bugun_bildirim']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-day fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-success text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Başarılı</h6>
                                        <h3><?php echo $stats['basarili_bildirim']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-danger text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Başarısız</h6>
                                        <h3><?php echo $stats['basarisiz_bildirim']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-warning text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Aktif FCM Token</h6>
                                        <h3><?php echo $stats['aktif_fcm_token']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-mobile-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                        <div class="card bg-dark text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bekleyen</h6>
                                        <h3><?php echo $stats['bekleyen_bildirim']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son Bildirimler -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-history me-2"></i>Son Bildirimler
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($son_bildirimler)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>Henüz bildirim bulunmuyor.</p>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tür</th>
                                                <th>Alıcı</th>
                                                <th>Başlık</th>
                                                <th>Durum</th>
                                                <th>Tarih</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($son_bildirimler as $bildirim): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $bildirim_turleri[$bildirim['tip']] ?? ucfirst($bildirim['tip']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($bildirim['alici_adi'] ?? 'Sistem'); ?>
                                                    <?php if ($bildirim['alici_email']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($bildirim['alici_email']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($bildirim['baslik']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $bildirim['durum'] == 'gonderildi' ? 'success' : ($bildirim['durum'] == 'basarisiz' ? 'danger' : 'warning'); ?>">
                                                        <?php echo ucfirst($bildirim['durum']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo formatTurkishDate($bildirim['olusturma_tarihi'], 'd.m.Y H:i'); ?></small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-outline-info btn-sm" onclick="viewNotificationDetails(<?php echo $bildirim['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
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
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Test Bildirimi Modal -->
    <div class="modal fade" id="testNotificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Bildirimi Gönder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="test_bildirim">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="tip" class="form-label">Bildirim Türü</label>
                            <select class="form-select" id="tip" name="tip" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($bildirim_turleri as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="alici_email" class="form-label">Alıcı Email</label>
                            <input type="email" class="form-control" id="alici_email" name="alici_email" 
                                   placeholder="test@example.com">
                        </div>
                        
                        <div class="mb-3">
                            <label for="alici_telefon" class="form-label">Alıcı Telefon</label>
                            <input type="tel" class="form-control" id="alici_telefon" name="alici_telefon" 
                                   placeholder="0555 123 45 67">
                        </div>
                        
                        <div class="mb-3">
                            <label for="mesaj" class="form-label">Mesaj</label>
                            <textarea class="form-control" id="mesaj" name="mesaj" rows="3" 
                                      placeholder="Test mesajı..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Gönder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function processNotificationQueue() {
            if (confirm('Bildirim kuyruğunu işlemek istediğinizden emin misiniz?')) {
                // AJAX ile kuyruğu işle
                fetch('ajax/process-notification-queue.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'process_queue'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Kuyruk başarıyla işlendi: ' + data.message);
                        location.reload();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Kuyruk işleme hatası: ' + error);
                });
            }
        }

        function viewNotificationDetails(notificationId) {
            // Bildirim detaylarını göster
            window.open('bildirim-detay.php?id=' + notificationId, '_blank');
        }

        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

