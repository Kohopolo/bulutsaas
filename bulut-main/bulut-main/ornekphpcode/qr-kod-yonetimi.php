<?php
/**
 * QR Kod Yönetimi Dashboard
 * Oda QR kodları oluşturma, yönetme ve takip
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/qr-room-control.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('housekeeping_yonetimi')) {
    $_SESSION['error_message'] = 'Housekeeping yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'generate_qr_codes') {
            $room_ids = $_POST['room_ids'] ?? [];
            
            if (empty($room_ids)) {
                throw new Exception("Lütfen en az bir oda seçin.");
            }
            
            $qr_control = new QRRoomControl($pdo);
            $generated_count = 0;
            
            foreach ($room_ids as $room_id) {
                $result = $qr_control->generateQRCodeForRoom($room_id, $_SESSION['user_id']);
                if ($result['success']) {
                    $generated_count++;
                }
            }
            
            $success_message = "{$generated_count} oda için QR kod oluşturuldu.";
        }
        
        if ($action == 'regenerate_qr_code') {
            $room_id = intval($_POST['room_id']);
            
            $qr_control = new QRRoomControl($pdo);
            $result = $qr_control->generateQRCodeForRoom($room_id, $_SESSION['user_id']);
            
            if ($result['success']) {
                $success_message = "QR kod başarıyla yeniden oluşturuldu.";
            } else {
                $error_message = $result['message'];
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// İstatistikleri hesapla
$stats = [];

// Toplam oda sayısı
$toplam_oda = fetchOne("SELECT COUNT(*) as toplam FROM oda_numaralari");
$stats['toplam_oda'] = $toplam_oda['toplam'] ?? 0;

// QR kodlu oda sayısı
$qr_kodlu_oda = fetchOne("
    SELECT COUNT(DISTINCT oqr.oda_id) as toplam 
    FROM oda_qr_kodlari oqr
    WHERE oqr.durum = 'aktif'
");
$stats['qr_kodlu_oda'] = $qr_kodlu_oda['toplam'] ?? 0;

// Bugünkü QR kod okumaları
$bugun_okuma = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM qr_kod_okuma_gecmisi 
    WHERE DATE(okuma_tarihi) = CURDATE()
");
$stats['bugun_okuma'] = $bugun_okuma['toplam'] ?? 0;

// Aktif görevler
$aktif_gorev = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM housekeeping_gorevleri 
    WHERE durum IN ('beklemede', 'atandi', 'basladi')
");
$stats['aktif_gorev'] = $aktif_gorev['toplam'] ?? 0;

// Bekleyen görevler
$bekleyen_gorev = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM housekeeping_gorevleri 
    WHERE durum = 'beklemede'
");
$stats['bekleyen_gorev'] = $bekleyen_gorev['toplam'] ?? 0;

// Oda listesi (QR kod durumu ile)
$odalar = fetchAll("
    SELECT onum.*, 
           oqr.qr_kod, oqr.qr_kod_url, oqr.olusturma_tarihi as qr_olusturma_tarihi,
           oqr.durum as qr_durum,
           (SELECT COUNT(*) FROM housekeeping_gorevleri hg WHERE hg.oda_id = onum.id AND hg.durum IN ('beklemede', 'atandi', 'basladi')) as aktif_gorev_sayisi
    FROM oda_numaralari onum
    LEFT JOIN oda_qr_kodlari oqr ON onum.id = oqr.oda_id AND oqr.durum = 'aktif'
    ORDER BY onum.oda_numarasi
");

// Son QR kod okumaları
$son_okumalar = fetchAll("
    SELECT qr.*, 
           onum.oda_numarasi,
           CONCAT(k.ad, ' ', k.soyad) as okuyan_kullanici
    FROM qr_kod_okuma_gecmisi qr
    LEFT JOIN oda_numaralari onum ON qr.oda_id = onum.id
    LEFT JOIN kullanicilar k ON qr.okuyan_kullanici_id = k.id
    ORDER BY qr.okuma_tarihi DESC
    LIMIT 10
");

// Oda durumları
$oda_durumlari = [
    'bos' => 'Boş',
    'dolu' => 'Dolu',
    'temizlik' => 'Temizlik',
    'bakim' => 'Bakım',
    'arizali' => 'Arızalı',
    'hazir' => 'Hazır',
    'checkout' => 'Checkout'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Kod Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-stat {
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
        }
        .room-card {
            border-left: 4px solid;
        }
        .room-ready {
            border-left-color: #28a745;
        }
        .room-occupied {
            border-left-color: #007bff;
        }
        .room-cleaning {
            border-left-color: #ffc107;
        }
        .room-maintenance {
            border-left-color: #fd7e14;
        }
        .room-out-of-order {
            border-left-color: #dc3545;
        }
        .qr-code-preview {
            max-width: 100px;
            max-height: 100px;
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
                    <h1 class="h2">QR Kod Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateQRModal">
                                <i class="fas fa-qrcode"></i> QR Kod Oluştur
                            </button>
                            <a href="housekeeping-dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Housekeeping
                            </a>
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
                                        <h6 class="card-title">Toplam Oda</h6>
                                        <h3><?php echo $stats['toplam_oda']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-door-open fa-2x"></i>
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
                                        <h6 class="card-title">QR Kodlu Oda</h6>
                                        <h3><?php echo $stats['qr_kodlu_oda']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-qrcode fa-2x"></i>
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
                                        <h6 class="card-title">Bugünkü Okumalar</h6>
                                        <h3><?php echo $stats['bugun_okuma']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-eye fa-2x"></i>
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
                                        <h6 class="card-title">Aktif Görevler</h6>
                                        <h3><?php echo $stats['aktif_gorev']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-tasks fa-2x"></i>
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
                                        <h6 class="card-title">Bekleyen Görevler</h6>
                                        <h3><?php echo $stats['bekleyen_gorev']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Oda Listesi -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-door-open me-2"></i>Oda Listesi ve QR Kod Durumu
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Oda No</th>
                                                <th>Durum</th>
                                                <th>QR Kod</th>
                                                <th>Aktif Görev</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($odalar as $oda): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($oda['oda_numarasi']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $oda['mevcut_durum'] == 'hazir' ? 'success' : ($oda['mevcut_durum'] == 'dolu' ? 'primary' : 'warning'); ?>">
                                                        <?php echo $oda_durumlari[$oda['mevcut_durum']] ?? ucfirst($oda['mevcut_durum']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($oda['qr_kod']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Var
                                                    </span>
                                                    <br><small class="text-muted"><?php echo formatTurkishDate($oda['qr_olusturma_tarihi'], 'd.m.Y'); ?></small>
                                                    <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times"></i> Yok
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($oda['aktif_gorev_sayisi'] > 0): ?>
                                                    <span class="badge bg-warning">
                                                        <?php echo $oda['aktif_gorev_sayisi']; ?> görev
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="badge bg-success">Yok</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($oda['qr_kod']): ?>
                                                        <a href="qr-scan.php?code=<?php echo urlencode($oda['qr_kod']); ?>" 
                                                           class="btn btn-outline-primary" title="QR Kod Tara" target="_blank">
                                                            <i class="fas fa-qrcode"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="regenerateQR(<?php echo $oda['id']; ?>)" title="QR Kod Yenile">
                                                            <i class="fas fa-sync"></i>
                                                        </button>
                                                        <?php else: ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                onclick="generateQR(<?php echo $oda['id']; ?>)" title="QR Kod Oluştur">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        <a href="oda-durum-gecmisi.php?oda_id=<?php echo $oda['id']; ?>" 
                                                           class="btn btn-outline-info" title="Durum Geçmişi">
                                                            <i class="fas fa-history"></i>
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
                    </div>

                    <!-- Son QR Kod Okumaları -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-history me-2"></i>Son QR Kod Okumaları
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($son_okumalar)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>Henüz QR kod okuma bulunmuyor.</p>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($son_okumalar as $okuma): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($okuma['oda_numarasi']); ?></h6>
                                            <small class="text-muted"><?php echo formatTurkishDate($okuma['okuma_tarihi'], 'H:i'); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($okuma['okuma_tipi']); ?>
                                            </span>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($okuma['okuyan_kullanici']); ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- QR Kod Oluşturma Modal -->
    <div class="modal fade" id="generateQRModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">QR Kod Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="generate_qr_codes">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Odaları Seçin</label>
                            <div class="row">
                                <?php foreach ($odalar as $oda): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="room_ids[]" 
                                               value="<?php echo $oda['id']; ?>" 
                                               id="room_<?php echo $oda['id']; ?>"
                                               <?php echo $oda['qr_kod'] ? 'disabled' : ''; ?>>
                                        <label class="form-check-label" for="room_<?php echo $oda['id']; ?>">
                                            <?php echo htmlspecialchars($oda['oda_numarasi']); ?>
                                            <?php if ($oda['qr_kod']): ?>
                                            <span class="badge bg-success ms-1">Var</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">QR Kod Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- QR Kod Yenileme Formu -->
    <form id="regenerateQRForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="regenerate_qr_code">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="room_id" id="regenerate_room_id">
    </form>

    <!-- QR Kod Oluşturma Formu -->
    <form id="generateQRForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="generate_qr_codes">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="room_ids[]" id="generate_room_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function regenerateQR(roomId) {
            if (confirm('Bu oda için QR kod yeniden oluşturulsun mu?')) {
                document.getElementById('regenerate_room_id').value = roomId;
                document.getElementById('regenerateQRForm').submit();
            }
        }

        function generateQR(roomId) {
            if (confirm('Bu oda için QR kod oluşturulsun mu?')) {
                document.getElementById('generate_room_id').value = roomId;
                document.getElementById('generateQRForm').submit();
            }
        }

        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

