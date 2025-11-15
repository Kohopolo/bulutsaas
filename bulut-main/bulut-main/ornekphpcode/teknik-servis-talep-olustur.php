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
if (!hasDetailedPermission('teknik_servis_talep_olustur')) {
    $_SESSION['error_message'] = 'Teknik servis talep oluşturma yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// POST işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $oda_id = !empty($_POST['oda_id']) ? intval($_POST['oda_id']) : null;
        $talep_turu = sanitizeString($_POST['talep_turu']);
        $acil_durum = sanitizeString($_POST['acil_durum']);
        $baslik = sanitizeString($_POST['baslik']);
        $aciklama = sanitizeString($_POST['aciklama']);
        $oncelik = sanitizeString($_POST['oncelik']);
        $tahmini_sure = intval($_POST['tahmini_sure']);
        
        // Talep numarası oluştur
        $talep_no = 'TS' . date('Ymd') . sprintf('%04d', rand(1, 9999));
        
        // Talep oluştur
        $sql = "INSERT INTO teknik_servis_talepleri (talep_no, oda_id, talep_turu, acil_durum, baslik, aciklama, talep_eden_id, oncelik, tahmini_sure) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if (executeQuery($sql, [$talep_no, $oda_id, $talep_turu, $acil_durum, $baslik, $aciklama, $_SESSION['user_id'], $oncelik, $tahmini_sure])) {
            $pdo->commit();
            $success_message = 'Teknik servis talebi başarıyla oluşturuldu. Talep No: ' . $talep_no;
        } else {
            throw new Exception('Talep oluşturulurken hata oluştu.');
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
    ORDER BY oda_numaralari.oda_numarasi
");

// Talep türleri
$talep_turleri = [
    'elektrik' => 'Elektrik',
    'su' => 'Su Tesisatı',
    'klima' => 'Klima',
    'internet' => 'İnternet',
    'tv' => 'TV',
    'telefon' => 'Telefon',
    'asansor' => 'Asansör',
    'güvenlik' => 'Güvenlik',
    'yangin' => 'Yangın',
    'diger' => 'Diğer'
];

// Acil durum seviyeleri
$acil_durum_seviyeleri = [
    'dusuk' => 'Düşük',
    'orta' => 'Orta',
    'yuksek' => 'Yüksek',
    'kritik' => 'Kritik'
];

// Öncelik seviyeleri
$oncelik_seviyeleri = [
    'dusuk' => 'Düşük',
    'normal' => 'Normal',
    'yuksek' => 'Yüksek',
    'acil' => 'Acil'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknik Servis Talep Oluştur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Teknik Servis Talep Oluştur</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="teknik-servis-dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Geri Dön
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tools"></i> Talep Bilgileri
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Oda <span class="text-muted">(Opsiyonel)</span></label>
                                            <select name="oda_id" class="form-select">
                                                <option value="">Genel Talep</option>
                                                <?php foreach ($odalar as $oda): ?>
                                                    <option value="<?php echo $oda['id']; ?>">
                                                        <?php echo htmlspecialchars($oda['oda_numarasi'] . ' (' . $oda['oda_tipi'] . ') - ' . $oda['durum']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Talep Türü <span class="text-danger">*</span></label>
                                            <select name="talep_turu" class="form-select" required>
                                                <option value="">Seçiniz</option>
                                                <?php foreach ($talep_turleri as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>">
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Acil Durum Seviyesi</label>
                                            <select name="acil_durum" class="form-select">
                                                <?php foreach ($acil_durum_seviyeleri as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $key == 'orta' ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Öncelik</label>
                                            <select name="oncelik" class="form-select">
                                                <?php foreach ($oncelik_seviyeleri as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $key == 'normal' ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Başlık <span class="text-danger">*</span></label>
                                        <input type="text" name="baslik" class="form-control" required maxlength="255" placeholder="Talep başlığını giriniz">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Açıklama <span class="text-danger">*</span></label>
                                        <textarea name="aciklama" class="form-control" rows="5" required placeholder="Talep detaylarını açıklayınız"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Tahmini Süre (Dakika)</label>
                                        <input type="number" name="tahmini_sure" class="form-control" value="60" min="1" max="1440">
                                        <div class="form-text">Talebin tahmini tamamlanma süresi</div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Talep Oluştur
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-info-circle"></i> Bilgilendirme
                                </h6>
                            </div>
                            <div class="card-body">
                                <h6>Acil Durum Seviyeleri:</h6>
                                <ul class="list-unstyled">
                                    <li><span class="badge bg-success">Düşük</span> - Normal işlem</li>
                                    <li><span class="badge bg-warning">Orta</span> - Öncelikli işlem</li>
                                    <li><span class="badge bg-danger">Yüksek</span> - Acil işlem</li>
                                    <li><span class="badge bg-dark">Kritik</span> - Hemen müdahale</li>
                                </ul>
                                
                                <h6 class="mt-3">Öncelik Seviyeleri:</h6>
                                <ul class="list-unstyled">
                                    <li><span class="badge bg-secondary">Düşük</span> - Düşük öncelik</li>
                                    <li><span class="badge bg-primary">Normal</span> - Normal öncelik</li>
                                    <li><span class="badge bg-warning">Yüksek</span> - Yüksek öncelik</li>
                                    <li><span class="badge bg-danger">Acil</span> - Acil öncelik</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
