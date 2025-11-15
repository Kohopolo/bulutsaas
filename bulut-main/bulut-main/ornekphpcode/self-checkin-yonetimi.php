<?php
/**
 * Self Check-in/Check-out Yönetimi Dashboard
 * Kiosk'ları, oturumları ve istatistikleri yönetme
 */

require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/self-checkin-system.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('resepsiyon_yonetimi')) {
    $_SESSION['error_message'] = 'Resepsiyon yönetimi yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = sanitizeString($_POST['action'] ?? '');
        
        if ($action == 'create_kiosk') {
            $kiosk_name = sanitizeString($_POST['kiosk_name']);
            $kiosk_code = sanitizeString($_POST['kiosk_code']);
            $kiosk_location = sanitizeString($_POST['kiosk_location']);
            $kiosk_type = sanitizeString($_POST['kiosk_type']);
            $language = sanitizeString($_POST['language']);
            $timezone = sanitizeString($_POST['timezone']);
            
            if (empty($kiosk_name) || empty($kiosk_code)) {
                throw new Exception("Kiosk adı ve kodu zorunludur.");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO self_checkin_kiosklari (
                    kiosk_adi, kiosk_kodu, kiosk_konumu, kiosk_turu, 
                    dil, timezone, olusturan_kullanici_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $kiosk_name,
                $kiosk_code,
                $kiosk_location,
                $kiosk_type,
                $language,
                $timezone,
                $_SESSION['user_id']
            ]);
            
            $success_message = "Self check-in kiosk'u başarıyla oluşturuldu.";
        }
        
        if ($action == 'update_kiosk_status') {
            $kiosk_id = intval($_POST['kiosk_id']);
            $status = sanitizeString($_POST['status']);
            
            $stmt = $pdo->prepare("UPDATE self_checkin_kiosklari SET aktif = ? WHERE id = ?");
            $stmt->execute([$status == 'aktif' ? 1 : 0, $kiosk_id]);
            
            $success_message = "Kiosk durumu başarıyla güncellendi.";
        }
        
        if ($action == 'update_setting') {
            $setting_name = sanitizeString($_POST['setting_name']);
            $setting_value = sanitizeString($_POST['setting_value']);
            $setting_type = sanitizeString($_POST['setting_type']);
            
            $stmt = $pdo->prepare("
                UPDATE self_checkin_ayarlari 
                SET ayar_degeri = ?, ayar_tipi = ?, guncelleyen_kullanici_id = ?
                WHERE ayar_adi = ?
            ");
            
            $stmt->execute([$setting_value, $setting_type, $_SESSION['user_id'], $setting_name]);
            
            $success_message = "Ayar başarıyla güncellendi.";
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// İstatistikleri hesapla
$stats = [];

// Toplam kiosk sayısı
$toplam_kiosk = fetchOne("SELECT COUNT(*) as toplam FROM self_checkin_kiosklari WHERE aktif = 1");
$stats['toplam_kiosk'] = $toplam_kiosk['toplam'] ?? 0;

// Bugünkü oturumlar
$bugun_oturum = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM self_checkin_oturumlari 
    WHERE DATE(olusturma_tarihi) = CURDATE()
");
$stats['bugun_oturum'] = $bugun_oturum['toplam'] ?? 0;

// Bugünkü check-in'ler
$bugun_checkin = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM self_checkin_oturumlari 
    WHERE DATE(checkin_tarihi) = CURDATE() AND oturum_durumu = 'checkin_tamamlandi'
");
$stats['bugun_checkin'] = $bugun_checkin['toplam'] ?? 0;

// Bugünkü check-out'lar
$bugun_checkout = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM self_checkin_oturumlari 
    WHERE DATE(checkout_tarihi) = CURDATE() AND oturum_durumu = 'checkout_tamamlandi'
");
$stats['bugun_checkout'] = $bugun_checkout['toplam'] ?? 0;

// Aktif oturumlar
$aktif_oturum = fetchOne("
    SELECT COUNT(*) as toplam 
    FROM self_checkin_oturumlari 
    WHERE oturum_durumu IN ('basladi', 'kimlik_dogrulandi')
");
$stats['aktif_oturum'] = $aktif_oturum['toplam'] ?? 0;

// Kiosk listesi
$kiosklar = fetchAll("
    SELECT sck.*, 
           COUNT(scio.id) as toplam_oturum,
           COUNT(CASE WHEN scio.oturum_durumu = 'checkin_tamamlandi' THEN 1 END) as basarili_checkin,
           COUNT(CASE WHEN scio.oturum_durumu = 'checkout_tamamlandi' THEN 1 END) as basarili_checkout,
           MAX(scio.olusturma_tarihi) as son_oturum
    FROM self_checkin_kiosklari sck
    LEFT JOIN self_checkin_oturumlari scio ON sck.id = scio.kiosk_id
    GROUP BY sck.id
    ORDER BY sck.kiosk_adi
");

// Son oturumlar
$son_oturumlar = fetchAll("
    SELECT scio.*, sck.kiosk_adi, sck.kiosk_konumu
    FROM self_checkin_oturumlari scio
    LEFT JOIN self_checkin_kiosklari sck ON scio.kiosk_id = sck.id
    ORDER BY scio.olusturma_tarihi DESC
    LIMIT 20
");

// Sistem ayarları
$ayarlar = fetchAll("
    SELECT * FROM self_checkin_ayarlari 
    WHERE aktif = 1 
    ORDER BY kategori, ayar_adi
");

// Kiosk türleri
$kiosk_types = [
    'lobby' => 'Lobby',
    'restaurant' => 'Restoran',
    'bar' => 'Bar',
    'cafe' => 'Kafe',
    'outdoor' => 'Dış Mekan'
];

// Dil seçenekleri
$languages = [
    'tr' => 'Türkçe',
    'en' => 'English',
    'de' => 'Deutsch',
    'fr' => 'Français'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Self Check-in Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-stat {
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
        }
        .kiosk-card {
            border-left: 4px solid;
        }
        .kiosk-lobby {
            border-left-color: #007bff;
        }
        .kiosk-restaurant {
            border-left-color: #28a745;
        }
        .kiosk-bar {
            border-left-color: #e74c3c;
        }
        .kiosk-cafe {
            border-left-color: #ffc107;
        }
        .kiosk-outdoor {
            border-left-color: #6c757d;
        }
        .session-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-basladi { background-color: #ffc107; color: black; }
        .status-kimlik_dogrulandi { background-color: #17a2b8; color: white; }
        .status-checkin_tamamlandi { background-color: #28a745; color: white; }
        .status-checkout_tamamlandi { background-color: #6c757d; color: white; }
        .status-iptal_edildi { background-color: #dc3545; color: white; }
        .setting-group {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                    <h1 class="h2">Self Check-in Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKioskModal">
                                <i class="fas fa-plus"></i> Yeni Kiosk
                            </button>
                            <a href="resepsiyon-ana-ekran.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Resepsiyon Dashboard
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
                                        <h6 class="card-title">Toplam Kiosk</h6>
                                        <h3><?php echo $stats['toplam_kiosk']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-desktop fa-2x"></i>
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
                        <div class="card bg-success text-white card-stat">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Bugünkü Check-in</h6>
                                        <h3><?php echo $stats['bugun_checkin']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-sign-in-alt fa-2x"></i>
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
                                        <h6 class="card-title">Bugünkü Check-out</h6>
                                        <h3><?php echo $stats['bugun_checkout']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-sign-out-alt fa-2x"></i>
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
                                        <h6 class="card-title">Aktif Oturum</h6>
                                        <h3><?php echo $stats['aktif_oturum']; ?></h3>
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
                    <!-- Kiosk Listesi -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-desktop me-2"></i>Self Check-in Kiosk'ları
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kiosk</th>
                                                <th>Tür</th>
                                                <th>Konum</th>
                                                <th>Durum</th>
                                                <th>İstatistikler</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($kiosklar as $kiosk): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($kiosk['kiosk_adi']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($kiosk['kiosk_kodu']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $kiosk['kiosk_turu'] == 'lobby' ? 'primary' : ($kiosk['kiosk_turu'] == 'restaurant' ? 'success' : 'warning'); ?>">
                                                        <?php echo $kiosk_types[$kiosk['kiosk_turu']] ?? ucfirst($kiosk['kiosk_turu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($kiosk['kiosk_konumu']); ?></td>
                                                <td>
                                                    <?php if ($kiosk['aktif']): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-danger">Pasif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>Oturum:</strong> <?php echo $kiosk['toplam_oturum']; ?><br>
                                                        <strong>Check-in:</strong> <?php echo $kiosk['basarili_checkin']; ?><br>
                                                        <strong>Check-out:</strong> <?php echo $kiosk['basarili_checkout']; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="self-checkin.php?kiosk=<?php echo $kiosk['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Kiosk'u Aç" target="_blank">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-<?php echo $kiosk['aktif'] ? 'warning' : 'success'; ?>" 
                                                                onclick="toggleKioskStatus(<?php echo $kiosk['id']; ?>, '<?php echo $kiosk['aktif'] ? 'pasif' : 'aktif'; ?>')" 
                                                                title="<?php echo $kiosk['aktif'] ? 'Pasif Yap' : 'Aktif Yap'; ?>">
                                                            <i class="fas fa-<?php echo $kiosk['aktif'] ? 'pause' : 'play'; ?>"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="viewKioskStats(<?php echo $kiosk['id']; ?>)" title="İstatistikler">
                                                            <i class="fas fa-chart-bar"></i>
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

                    <!-- Son Oturumlar -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-history me-2"></i>Son Oturumlar
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($son_oturumlar)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>Henüz oturum bulunmuyor.</p>
                                </div>
                                <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($son_oturumlar as $oturum): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($oturum['oturum_kodu']); ?></h6>
                                            <small class="text-muted"><?php echo formatTurkishDate($oturum['olusturma_tarihi'], 'H:i'); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <strong><?php echo htmlspecialchars($oturum['kiosk_adi']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($oturum['kiosk_konumu']); ?></small>
                                        </p>
                                        <span class="session-status status-<?php echo $oturum['oturum_durumu']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $oturum['oturum_durumu'])); ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sistem Ayarları -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-cog me-2"></i>Sistem Ayarları
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $currentCategory = '';
                                foreach ($ayarlar as $ayar):
                                    if ($ayar['kategori'] != $currentCategory):
                                        if ($currentCategory != '') echo '</div>';
                                        $currentCategory = $ayar['kategori'];
                                ?>
                                <div class="setting-group">
                                    <h6 class="text-primary"><?php echo ucfirst($ayar['kategori']); ?></h6>
                                <?php endif; ?>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label"><?php echo htmlspecialchars($ayar['ayar_adi']); ?></label>
                                        <small class="form-text text-muted"><?php echo htmlspecialchars($ayar['aciklama']); ?></small>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($ayar['ayar_tipi'] == 'boolean'): ?>
                                        <select class="form-select" onchange="updateSetting('<?php echo $ayar['ayar_adi']; ?>', this.value, '<?php echo $ayar['ayar_tipi']; ?>')">
                                            <option value="true" <?php echo $ayar['ayar_degeri'] == 'true' ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="false" <?php echo $ayar['ayar_degeri'] == 'false' ? 'selected' : ''; ?>>Pasif</option>
                                        </select>
                                        <?php else: ?>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($ayar['ayar_degeri']); ?>" 
                                               onchange="updateSetting('<?php echo $ayar['ayar_adi']; ?>', this.value, '<?php echo $ayar['ayar_tipi']; ?>')">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php endforeach; ?>
                                <?php if ($currentCategory != '') echo '</div>'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Yeni Kiosk Oluşturma Modal -->
    <div class="modal fade" id="createKioskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Self Check-in Kiosk'u</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_kiosk">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="kiosk_name" class="form-label">Kiosk Adı</label>
                            <input type="text" class="form-control" id="kiosk_name" name="kiosk_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kiosk_code" class="form-label">Kiosk Kodu</label>
                            <input type="text" class="form-control" id="kiosk_code" name="kiosk_code" required>
                            <small class="form-text text-muted">Benzersiz bir kod girin (örn: KIOSK-001)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="kiosk_location" class="form-label">Konum</label>
                            <input type="text" class="form-control" id="kiosk_location" name="kiosk_location">
                        </div>
                        
                        <div class="mb-3">
                            <label for="kiosk_type" class="form-label">Kiosk Türü</label>
                            <select class="form-select" id="kiosk_type" name="kiosk_type" required>
                                <?php foreach ($kiosk_types as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="language" class="form-label">Varsayılan Dil</label>
                            <select class="form-select" id="language" name="language" required>
                                <?php foreach ($languages as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $key == 'tr' ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="timezone" class="form-label">Zaman Dilimi</label>
                            <input type="text" class="form-control" id="timezone" name="timezone" value="Europe/Istanbul">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleKioskStatus(kioskId, newStatus) {
            if (confirm('Kiosk durumunu değiştirmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_kiosk_status">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="kiosk_id" value="${kioskId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateSetting(settingName, settingValue, settingType) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_setting">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="setting_name" value="${settingName}">
                <input type="hidden" name="setting_value" value="${settingValue}">
                <input type="hidden" name="setting_type" value="${settingType}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function viewKioskStats(kioskId) {
            // Kiosk istatistikleri görüntüleme
            alert('Kiosk istatistikleri özelliği geliştirilecek. Kiosk ID: ' + kioskId);
        }

        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

