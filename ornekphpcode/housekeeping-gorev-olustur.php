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
if (!hasDetailedPermission('housekeeping_gorev_olustur')) {
    $_SESSION['error_message'] = 'Temizlik görevi oluşturma yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $oda_id = intval($_POST['oda_id']);
        $housekeeper_id = intval($_POST['housekeeper_id']);
        $temizlik_tarihi = sanitizeString($_POST['temizlik_tarihi']);
        $temizlik_turu = sanitizeString($_POST['temizlik_turu']);
        $baslama_saati = sanitizeString($_POST['baslama_saati']);
        $temizlik_detaylari = sanitizeString($_POST['temizlik_detaylari']);
        $notlar = sanitizeString($_POST['notlar']);
        
        // Validasyon
        if (empty($oda_id) || empty($housekeeper_id) || empty($temizlik_tarihi) || empty($temizlik_turu) || empty($baslama_saati)) {
            throw new Exception('Lütfen tüm gerekli alanları doldurun.');
        }
        
        // Oda kontrolü
        $oda = fetchOne("SELECT * FROM oda_numaralari WHERE id = ?", [$oda_id]);
        if (!$oda) {
            throw new Exception('Seçilen oda bulunamadı.');
        }
        
        // Housekeeper kontrolü
        $housekeeper = fetchOne("SELECT * FROM kullanicilar WHERE id = ? AND rol = 'housekeeper'", [$housekeeper_id]);
        if (!$housekeeper) {
            throw new Exception('Seçilen housekeeper bulunamadı.');
        }
        
        // Aynı tarih ve saatte başka görev var mı kontrol et
        $mevcut_gorev = fetchOne("
            SELECT id FROM temizlik_kayitlari 
            WHERE housekeeper_id = ? AND temizlik_tarihi = ? AND baslama_saati = ? AND durum IN ('devam_ediyor', 'onay_bekliyor')
        ", [$housekeeper_id, $temizlik_tarihi, $baslama_saati]);
        
        if ($mevcut_gorev) {
            throw new Exception('Bu housekeeper için aynı tarih ve saatte zaten bir görev bulunuyor.');
        }
        
        // Temizlik görevini oluştur
        $sql = "INSERT INTO temizlik_kayitlari (
            oda_id, housekeeper_id, temizlik_tarihi, temizlik_turu, 
            temizlik_detaylari, baslama_saati, durum, notlar
        ) VALUES (?, ?, ?, ?, ?, ?, 'devam_ediyor', ?)";
        
        $result = executeQuery($sql, [
            $oda_id, $housekeeper_id, $temizlik_tarihi, $temizlik_turu,
            $temizlik_detaylari, $baslama_saati, $notlar
        ]);
        
        if ($result) {
            $pdo->commit();
            $success_message = 'Temizlik görevi başarıyla oluşturuldu.';
            
            // Formu temizle
            $_POST = [];
        } else {
            throw new Exception('Temizlik görevi oluşturulurken hata oluştu.');
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Odaları getir
$odalar = fetchAll("
    SELECT oda_numaralari.id, oda_numaralari.oda_numarasi, ot.oda_tipi_adi as oda_tipi, oda_numaralari.durum
    FROM oda_numaralari
    LEFT JOIN oda_tipleri ot ON oda_numaralari.oda_tipi_id = ot.id
    WHERE oda_numaralari.durum IN ('temizlik_bekliyor', 'musait', 'dolu')
    ORDER BY oda_numaralari.oda_numarasi
");

// Housekeeper'ları getir
$housekeepers = fetchAll("
    SELECT id, ad, soyad, email
    FROM kullanicilar
    WHERE rol = 'housekeeper' AND durum = 'aktif' AND aktif = 1
    ORDER BY ad, soyad
");

// Temizlik türleri
$temizlik_turleri = [
    'genel_temizlik' => 'Genel Temizlik',
    'cikis_temizligi' => 'Çıkış Temizliği',
    'bakim_temizligi' => 'Bakım Temizliği',
    'derin_temizlik' => 'Derin Temizlik'
];

error_log("Housekeeping Görev Oluştur - HTML çıktısı başlıyor");

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Temizlik Görevi - Housekeeping</title>
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
                    <h1 class="h2"><i class="fas fa-plus me-2"></i>Yeni Temizlik Görevi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="housekeeping-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Geri Dön
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

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-broom me-2"></i>Temizlik Görevi Bilgileri
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php echo csrfTokenInput(); ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="oda_id" class="form-label">Oda <span class="text-danger">*</span></label>
                                            <select class="form-select" id="oda_id" name="oda_id" required>
                                                <option value="">Oda Seçin</option>
                                                <?php foreach ($odalar as $oda): ?>
                                                <option value="<?php echo $oda['id']; ?>" 
                                                        <?php echo (isset($_POST['oda_id']) && $_POST['oda_id'] == $oda['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($oda['oda_numarasi'] . ' - ' . $oda['oda_tipi']); ?>
                                                    (<?php echo ucfirst($oda['durum']); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="housekeeper_id" class="form-label">Housekeeper <span class="text-danger">*</span></label>
                                            <select class="form-select" id="housekeeper_id" name="housekeeper_id" required>
                                                <option value="">Housekeeper Seçin</option>
                                                <?php foreach ($housekeepers as $housekeeper): ?>
                                                <option value="<?php echo $housekeeper['id']; ?>"
                                                        <?php echo (isset($_POST['housekeeper_id']) && $_POST['housekeeper_id'] == $housekeeper['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($housekeeper['ad'] . ' ' . $housekeeper['soyad']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="temizlik_tarihi" class="form-label">Temizlik Tarihi <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="temizlik_tarihi" name="temizlik_tarihi" 
                                                   value="<?php echo isset($_POST['temizlik_tarihi']) ? $_POST['temizlik_tarihi'] : date('Y-m-d'); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="baslama_saati" class="form-label">Başlama Saati <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="baslama_saati" name="baslama_saati" 
                                                   value="<?php echo isset($_POST['baslama_saati']) ? $_POST['baslama_saati'] : date('H:i'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="temizlik_turu" class="form-label">Temizlik Türü <span class="text-danger">*</span></label>
                                        <select class="form-select" id="temizlik_turu" name="temizlik_turu" required>
                                            <option value="">Temizlik Türü Seçin</option>
                                            <?php foreach ($temizlik_turleri as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"
                                                    <?php echo (isset($_POST['temizlik_turu']) && $_POST['temizlik_turu'] == $key) ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="temizlik_detaylari" class="form-label">Temizlik Detayları</label>
                                        <textarea class="form-control" id="temizlik_detaylari" name="temizlik_detaylari" rows="4" 
                                                  placeholder="Temizlik ile ilgili özel detaylar..."><?php echo isset($_POST['temizlik_detaylari']) ? htmlspecialchars($_POST['temizlik_detaylari']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notlar" class="form-label">Notlar</label>
                                        <textarea class="form-control" id="notlar" name="notlar" rows="3" 
                                                  placeholder="Ek notlar..."><?php echo isset($_POST['notlar']) ? htmlspecialchars($_POST['notlar']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="housekeeping-dashboard.php" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-times me-1"></i>İptal
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Görevi Oluştur
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Bilgi
                                </h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">Temizlik Türleri:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-circle text-info me-2"></i><strong>Genel Temizlik:</strong> Günlük temizlik</li>
                                    <li><i class="fas fa-circle text-warning me-2"></i><strong>Çıkış Temizliği:</strong> Check-out sonrası</li>
                                    <li><i class="fas fa-circle text-success me-2"></i><strong>Bakım Temizliği:</strong> Haftalık/aylık</li>
                                    <li><i class="fas fa-circle text-danger me-2"></i><strong>Derin Temizlik:</strong> Özel durumlar</li>
                                </ul>
                                
                                <hr>
                                
                                <h6 class="text-primary">Görev Durumları:</h6>
                                <ul class="list-unstyled">
                                    <li><span class="badge bg-warning me-2">Devam Ediyor</span>Temizlik başladı</li>
                                    <li><span class="badge bg-info me-2">Tamamlandı</span>Temizlik bitti</li>
                                    <li><span class="badge bg-primary me-2">Onay Bekliyor</span>Manager onayı bekliyor</li>
                                    <li><span class="badge bg-success me-2">Onaylandı</span>Manager onayladı</li>
                                    <li><span class="badge bg-danger me-2">Reddedildi</span>Temizlik tekrar gerekli</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
