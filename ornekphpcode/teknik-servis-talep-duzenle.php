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
if (!hasDetailedPermission('teknik_servis_talep_duzenle')) {
    $_SESSION['error_message'] = 'Teknik servis talep düzenleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$talep_id = intval($_GET['id'] ?? 0);
if (!$talep_id) {
    $_SESSION['error_message'] = 'Geçersiz talep ID.';
    header('Location: teknik-servis-talep-listesi.php');
    exit;
}

$success_message = '';
$error_message = '';

// Talep detaylarını getir
$talep = fetchOne("
    SELECT tst.*, on.oda_numarasi, ot.oda_tipi_adi as oda_tipi,
           k1.ad as talep_eden_adi, k1.soyad as talep_eden_soyadi,
           k2.ad as teknisyen_adi, k2.soyad as teknisyen_soyadi
    FROM teknik_servis_talepleri tst
    LEFT JOIN oda_numaralari on ON tst.oda_id = on.id
    LEFT JOIN oda_tipleri ot ON on.oda_tipi_id = ot.id
    LEFT JOIN kullanicilar k1 ON tst.talep_eden_id = k1.id
    LEFT JOIN kullanicilar k2 ON tst.atanan_teknisyen_id = k2.id
    WHERE tst.id = ?
", [$talep_id]);

if (!$talep) {
    $_SESSION['error_message'] = 'Talep bulunamadı.';
    header('Location: teknik-servis-talep-listesi.php');
    exit;
}

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
        $atanan_teknisyen_id = !empty($_POST['atanan_teknisyen_id']) ? intval($_POST['atanan_teknisyen_id']) : null;
        $durum = sanitizeString($_POST['durum']);
        
        // Talep güncelle
        $sql = "UPDATE teknik_servis_talepleri SET 
                oda_id = ?, talep_turu = ?, acil_durum = ?, baslik = ?, 
                aciklama = ?, oncelik = ?, tahmini_sure = ?, 
                atanan_teknisyen_id = ?, durum = ?, guncelleme_tarihi = NOW()
                WHERE id = ?";
        
        if (executeQuery($sql, [$oda_id, $talep_turu, $acil_durum, $baslik, $aciklama, $oncelik, $tahmini_sure, $atanan_teknisyen_id, $durum, $talep_id])) {
            $pdo->commit();
            $success_message = 'Teknik servis talebi başarıyla güncellendi.';
        } else {
            throw new Exception('Talep güncellenirken hata oluştu.');
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

// Teknisyenleri getir
$teknisyenler = fetchAll("
    SELECT id, ad, soyad, email
    FROM kullanicilar
    WHERE rol IN ('teknisyen', 'teknik_servis_manager')
    AND durum = 'aktif' AND aktif = 1
    ORDER BY ad, soyad
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

// Durum seviyeleri
$durum_seviyeleri = [
    'beklemede' => 'Beklemede',
    'atanmis' => 'Atanmış',
    'devam_ediyor' => 'Devam Ediyor',
    'tamamlandi' => 'Tamamlandı',
    'iptal' => 'İptal'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknik Servis Talep Düzenle - <?php echo htmlspecialchars($talep['talep_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Teknik Servis Talep Düzenle</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="teknik-servis-talep-listesi.php" class="btn btn-outline-secondary">
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
                                    <i class="fas fa-edit"></i> Talep Bilgilerini Düzenle
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
                                                    <option value="<?php echo $oda['id']; ?>" <?php echo $talep['oda_id'] == $oda['id'] ? 'selected' : ''; ?>>
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
                                                    <option value="<?php echo $key; ?>" <?php echo $talep['talep_turu'] == $key ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Acil Durum Seviyesi</label>
                                            <select name="acil_durum" class="form-select">
                                                <?php foreach ($acil_durum_seviyeleri as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $talep['acil_durum'] == $key ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Öncelik</label>
                                            <select name="oncelik" class="form-select">
                                                <?php foreach ($oncelik_seviyeleri as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $talep['oncelik'] == $key ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Durum</label>
                                            <select name="durum" class="form-select">
                                                <?php foreach ($durum_seviyeleri as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $talep['durum'] == $key ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Başlık <span class="text-danger">*</span></label>
                                        <input type="text" name="baslik" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($talep['baslik']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Açıklama <span class="text-danger">*</span></label>
                                        <textarea name="aciklama" class="form-control" rows="5" required><?php echo htmlspecialchars($talep['aciklama']); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tahmini Süre (Dakika)</label>
                                            <input type="number" name="tahmini_sure" class="form-control" value="<?php echo $talep['tahmini_sure']; ?>" min="1" max="1440">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Atanan Teknisyen</label>
                                            <select name="atanan_teknisyen_id" class="form-select">
                                                <option value="">Teknisyen Seçin</option>
                                                <?php foreach ($teknisyenler as $teknisyen): ?>
                                                    <option value="<?php echo $teknisyen['id']; ?>" <?php echo $talep['atanan_teknisyen_id'] == $teknisyen['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($teknisyen['ad'] . ' ' . $teknisyen['soyad']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Güncelle
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
                                    <i class="fas fa-info-circle"></i> Talep Bilgileri
                                </h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Talep No:</strong> <?php echo htmlspecialchars($talep['talep_no']); ?></p>
                                <p><strong>Talep Eden:</strong> <?php echo htmlspecialchars($talep['talep_eden_adi'] . ' ' . $talep['talep_eden_soyadi']); ?></p>
                                <p><strong>Oluşturma Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($talep['olusturma_tarihi'])); ?></p>
                                <p><strong>Son Güncelleme:</strong> <?php echo date('d.m.Y H:i', strtotime($talep['guncelleme_tarihi'])); ?></p>
                                
                                <?php if ($talep['baslama_tarihi']): ?>
                                    <p><strong>Başlama Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($talep['baslama_tarihi'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($talep['bitis_tarihi']): ?>
                                    <p><strong>Bitiş Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($talep['bitis_tarihi'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($talep['maliyet'] > 0): ?>
                                    <p><strong>Maliyet:</strong> <?php echo number_format($talep['maliyet'], 2); ?>₺</p>
                                <?php endif; ?>
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
