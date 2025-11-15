<?php
/**
 * QR Kod Menü Yönetimi Dashboard
 * QR menü tabloları, tasarımları ve siparişleri yönetme
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/qr-menu-system.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('fnb_yonetimi')) {
    $_SESSION['error_message'] = 'F&B yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'create_table') {
            $table_name = sanitizeString($_POST['table_name']);
            $table_code = sanitizeString($_POST['table_code']);
            $table_location = sanitizeString($_POST['table_location']);
            $table_type = sanitizeString($_POST['table_type']);
            $capacity = intval($_POST['capacity']);
            
            if (empty($table_name) || empty($table_code)) {
                throw new Exception("Tablo adı ve kodu zorunludur.");
            }
            
            // QR kod oluştur
            $qr_code = 'QR-' . strtoupper($table_code) . '-' . time();
            $qr_url = getSistemAyari('site_url', 'https://grandhotel.com') . '/qr-menu.php?code=' . urlencode($qr_code);
            
            $stmt = $pdo->prepare("
                INSERT INTO qr_menu_tablolari (
                    tablo_adi, tablo_kodu, qr_kod, qr_kod_url, 
                    tablo_konumu, tablo_turu, kapasite, olusturan_kullanici_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $table_name,
                $table_code,
                $qr_code,
                $qr_url,
                $table_location,
                $table_type,
                $capacity,
                $_SESSION['user_id']
            ]);
            
            $success_message = "QR menü tablosu başarıyla oluşturuldu.";
        }
        
        if ($action == 'update_order_status') {
            $order_id = intval($_POST['order_id']);
            $new_status = sanitizeString($_POST['new_status']);
            $notes = sanitizeString($_POST['notes'] ?? '');
            
            $qr_menu = new QRMenuSystem($pdo);
            $result = $qr_menu->updateOrderStatus($order_id, $new_status, $_SESSION['user_id'], $notes);
            
            if ($result['success']) {
                $success_message = $result['message'];
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

// Toplam tablo sayısı
$toplam_tablo = fetchOne("SELECT COUNT(*) as toplam FROM qr_menu_tablolari WHERE aktif = 1");
$stats['toplam_tablo'] = $toplam_tablo['toplam'] ?? 0;

// Bugünkü oturumlar
$bugun_oturum = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM qr_menu_oturumlari 
    WHERE DATE(baslama_tarihi) = CURDATE()
");
$stats['bugun_oturum'] = $bugun_oturum['toplam'] ?? 0;

// Bugünkü siparişler
$bugun_siparis = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM qr_menu_siparisleri 
    WHERE DATE(siparis_tarihi) = CURDATE()
");
$stats['bugun_siparis'] = $bugun_siparis['toplam'] ?? 0;

// Bugünkü ciro
$bugun_ciro = fetchOne("
    SELECT SUM(toplam_tutar) as toplam 
    FROM qr_menu_siparisleri 
    WHERE DATE(siparis_tarihi) = CURDATE() AND siparis_durumu != 'iptal'
");
$stats['bugun_ciro'] = $bugun_ciro['toplam'] ?? 0;

// Bekleyen siparişler
$bekleyen_siparis = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM qr_menu_siparisleri 
    WHERE siparis_durumu IN ('beklemede', 'hazirlaniyor')
");
$stats['bekleyen_siparis'] = $bekleyen_siparis['toplam'] ?? 0;

// Tablo listesi
$tablolar = fetchAll("
    SELECT qmt.*, 
           COUNT(qmo.id) as toplam_oturum,
           COUNT(CASE WHEN qmo.oturum_durumu = 'aktif' THEN 1 END) as aktif_oturum,
           COUNT(qms.id) as toplam_siparis,
           SUM(CASE WHEN qms.siparis_durumu != 'iptal' THEN qms.toplam_tutar ELSE 0 END) as toplam_ciro
    FROM qr_menu_tablolari qmt
    LEFT JOIN qr_menu_oturumlari qmo ON qmt.id = qmo.tablo_id
    LEFT JOIN qr_menu_siparisleri qms ON qmo.id = qms.oturum_id
    WHERE qmt.aktif = 1
    GROUP BY qmt.id
    ORDER BY qmt.tablo_adi
");

// Son siparişler
$son_siparisler = fetchAll("
    SELECT qms.*, 
           qmo.oturum_kodu,
           qmt.tablo_adi,
           COUNT(qmsd.id) as item_count
    FROM qr_menu_siparisleri qms
    LEFT JOIN qr_menu_oturumlari qmo ON qms.oturum_id = qmo.id
    LEFT JOIN qr_menu_tablolari qmt ON qmo.tablo_id = qmt.id
    LEFT JOIN qr_menu_siparis_detaylari qmsd ON qms.id = qmsd.siparis_id
    GROUP BY qms.id
    ORDER BY qms.siparis_tarihi DESC
    LIMIT 20
");

// Tablo türleri
$table_types = [
    'restaurant' => 'Restoran',
    'bar' => 'Bar',
    'cafe' => 'Kafe',
    'room_service' => 'Oda Servisi',
    'takeaway' => 'Paket Servis'
];

// Sipariş durumları
$order_statuses = [
    'beklemede' => 'Beklemede',
    'hazirlaniyor' => 'Hazırlanıyor',
    'hazir' => 'Hazır',
    'teslim_edildi' => 'Teslim Edildi',
    'iptal' => 'İptal'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Kod Menü Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-stat {
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
        }
        .table-card {
            border-left: 4px solid;
        }
        .table-restaurant {
            border-left-color: #007bff;
        }
        .table-bar {
            border-left-color: #e74c3c;
        }
        .table-cafe {
            border-left-color: #28a745;
        }
        .table-room-service {
            border-left-color: #ffc107;
        }
        .table-takeaway {
            border-left-color: #6c757d;
        }
        .qr-code-preview {
            max-width: 100px;
            max-height: 100px;
        }
        .order-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-beklemede { background-color: #ffc107; color: black; }
        .status-hazirlaniyor { background-color: #17a2b8; color: white; }
        .status-hazir { background-color: #28a745; color: white; }
        .status-teslim_edildi { background-color: #6c757d; color: white; }
        .status-iptal { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">QR Kod Menü Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTableModal">
                                <i class="fas fa-plus"></i> Yeni Tablo
                            </button>
                            <a href="fnb-dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> F&B Dashboard
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
                                        <h6 class="card-title">Toplam Tablo</h6>
                                        <h3><?php echo $stats['toplam_tablo']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-table fa-2x"></i>
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
                                        <h6 class="card-title">Bugünkü Oturum</h6>
                                        <h3><?php echo $stats['bugun_oturum']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
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
                                        <h6 class="card-title">Bugünkü Sipariş</h6>
                                        <h3><?php echo $stats['bugun_siparis']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
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
                                        <h6 class="card-title">Bugünkü Ciro</h6>
                                        <h3><?php echo formatPrice($stats['bugun_ciro']); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-lira-sign fa-2x"></i>
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
                                        <h6 class="card-title">Bekleyen Sipariş</h6>
                                        <h3><?php echo $stats['bekleyen_siparis']; ?></h3>
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
                    <!-- Tablo Listesi -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-table me-2"></i>QR Menü Tabloları
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tablo</th>
                                                <th>Tür</th>
                                                <th>Konum</th>
                                                <th>Oturum</th>
                                                <th>Sipariş</th>
                                                <th>Ciro</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tablolar as $tablo): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($tablo['tablo_adi']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($tablo['tablo_kodu']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $tablo['tablo_turu'] == 'restaurant' ? 'primary' : ($tablo['tablo_turu'] == 'bar' ? 'danger' : 'success'); ?>">
                                                        <?php echo $table_types[$tablo['tablo_turu']] ?? ucfirst($tablo['tablo_turu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($tablo['tablo_konumu']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $tablo['toplam_oturum']; ?></span>
                                                    <?php if ($tablo['aktif_oturum'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $tablo['aktif_oturum']; ?> aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning"><?php echo $tablo['toplam_siparis']; ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatPrice($tablo['toplam_ciro']); ?></strong>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="qr-menu.php?code=<?php echo urlencode($tablo['qr_kod']); ?>" 
                                                           class="btn btn-outline-primary" title="Menüyü Görüntüle" target="_blank">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="showQRCode('<?php echo htmlspecialchars($tablo['qr_kod']); ?>')" title="QR Kod">
                                                            <i class="fas fa-qrcode"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="editTable(<?php echo $tablo['id']; ?>)" title="Düzenle">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
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

                    <!-- Son Siparişler -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-shopping-cart me-2"></i>Son Siparişler
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($son_siparisler)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>Henüz sipariş bulunmuyor.</p>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($son_siparisler as $siparis): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($siparis['siparis_no']); ?></h6>
                                            <small class="text-muted"><?php echo formatTurkishDate($siparis['siparis_tarihi'], 'H:i'); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <strong><?php echo htmlspecialchars($siparis['tablo_adi']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $siparis['item_count']; ?> ürün - <?php echo formatPrice($siparis['toplam_tutar']); ?></small>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="order-status status-<?php echo $siparis['siparis_durumu']; ?>">
                                                <?php echo $order_statuses[$siparis['siparis_durumu']]; ?>
                                            </span>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="updateOrderStatus(<?php echo $siparis['id']; ?>, '<?php echo $siparis['siparis_durumu']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
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

    <!-- Yeni Tablo Oluşturma Modal -->
    <div class="modal fade" id="createTableModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni QR Menü Tablosu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_table">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="table_name" class="form-label">Tablo Adı</label>
                            <input type="text" class="form-control" id="table_name" name="table_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="table_code" class="form-label">Tablo Kodu</label>
                            <input type="text" class="form-control" id="table_code" name="table_code" required>
                            <small class="form-text text-muted">Benzersiz bir kod girin (örn: MASA-001)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="table_location" class="form-label">Konum</label>
                            <input type="text" class="form-control" id="table_location" name="table_location">
                        </div>
                        
                        <div class="mb-3">
                            <label for="table_type" class="form-label">Tablo Türü</label>
                            <select class="form-select" id="table_type" name="table_type" required>
                                <?php foreach ($table_types as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="capacity" class="form-label">Kapasite</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" value="4" min="1" max="20">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sipariş Durumu Güncelleme Modal -->
    <div class="modal fade" id="updateOrderStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sipariş Durumu Güncelle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="order_id" id="update_order_id">
                        
                        <div class="mb-3">
                            <label for="new_status" class="form-label">Yeni Durum</label>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <?php foreach ($order_statuses as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notlar</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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

    <!-- QR Kod Görüntüleme Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">QR Kod</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrCodeDisplay"></div>
                    <p class="mt-3">
                        <strong>QR Kod:</strong> <span id="qrCodeText"></span>
                    </p>
                    <p>
                        <strong>URL:</strong> <span id="qrCodeUrl"></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showQRCode(qrCode) {
            document.getElementById('qrCodeText').textContent = qrCode;
            document.getElementById('qrCodeUrl').textContent = window.location.origin + '/qr-menu.php?code=' + encodeURIComponent(qrCode);
            
            // QR kod görüntüsü oluştur (basit implementasyon)
            const qrDisplay = document.getElementById('qrCodeDisplay');
            qrDisplay.innerHTML = `
                <div style="width: 200px; height: 200px; border: 2px solid #333; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <i class="fas fa-qrcode fa-4x text-muted"></i>
                </div>
                <p class="text-muted mt-2">QR kod görüntüsü burada görünecek</p>
            `;
            
            new bootstrap.Modal(document.getElementById('qrCodeModal')).show();
        }

        function updateOrderStatus(orderId, currentStatus) {
            document.getElementById('update_order_id').value = orderId;
            document.getElementById('new_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateOrderStatusModal')).show();
        }

        function editTable(tableId) {
            // Tablo düzenleme işlemi
            alert('Tablo düzenleme özelliği geliştirilecek. Tablo ID: ' + tableId);
        }

        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

