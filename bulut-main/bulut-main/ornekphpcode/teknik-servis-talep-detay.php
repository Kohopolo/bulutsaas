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
requireDetailedPermission('teknik_servis_talep_goruntule', 'Teknik servis talep detayları görüntüleme yetkiniz bulunmamaktadır.');

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
        $action = sanitizeString($_POST['action']);
        
        if ($action == 'durum_guncelle') {
            $yeni_durum = sanitizeString($_POST['yeni_durum']);
            $aciklama = sanitizeString($_POST['aciklama']);
            
            $sql = "UPDATE teknik_servis_talepleri SET durum = ?, guncelleme_tarihi = NOW()";
            $params = [$yeni_durum];
            
            if ($yeni_durum == 'devam_ediyor' && !$talep['baslama_tarihi']) {
                $sql .= ", baslama_tarihi = NOW()";
            }
            
            if ($yeni_durum == 'tamamlandi' && !$talep['bitis_tarihi']) {
                $sql .= ", bitis_tarihi = NOW()";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $talep_id;
            
            if (executeQuery($sql, $params)) {
                $success_message = 'Talep durumu başarıyla güncellendi.';
            } else {
                $error_message = 'Durum güncellenirken hata oluştu.';
            }
        }
        
        if ($action == 'teknisyen_ata') {
            $teknisyen_id = intval($_POST['teknisyen_id']);
            
            $sql = "UPDATE teknik_servis_talepleri SET atanan_teknisyen_id = ?, guncelleme_tarihi = NOW() WHERE id = ?";
            
            if (executeQuery($sql, [$teknisyen_id, $talep_id])) {
                $success_message = 'Teknisyen başarıyla atandı.';
            } else {
                $error_message = 'Teknisyen atanırken hata oluştu.';
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

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
    <title>Teknik Servis Talep Detay - <?php echo htmlspecialchars($talep['talep_no']); ?></title>
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
                    <h1 class="h2">Teknik Servis Talep Detay</h1>
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
                        <!-- Talep Bilgileri -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle"></i> Talep Bilgileri
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Talep No:</strong> <?php echo htmlspecialchars($talep['talep_no']); ?></p>
                                        <p><strong>Oda:</strong> 
                                            <?php if ($talep['oda_numarasi']): ?>
                                                <?php echo htmlspecialchars($talep['oda_numarasi'] . ' (' . $talep['oda_tipi'] . ')'); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Genel</span>
                                            <?php endif; ?>
                                        </p>
                                        <p><strong>Talep Türü:</strong> 
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($talep_turleri[$talep['talep_turu']] ?? $talep['talep_turu']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Öncelik:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $talep['oncelik'] == 'acil' ? 'danger' : 
                                                    ($talep['oncelik'] == 'yuksek' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($oncelik_seviyeleri[$talep['oncelik']] ?? $talep['oncelik']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Durum:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $talep['durum'] == 'tamamlandi' ? 'success' : 
                                                    ($talep['durum'] == 'devam_ediyor' ? 'info' : 
                                                    ($talep['durum'] == 'beklemede' ? 'warning' : 'secondary')); 
                                            ?>">
                                                <?php echo htmlspecialchars($durum_seviyeleri[$talep['durum']] ?? $talep['durum']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Acil Durum:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $talep['acil_durum'] == 'kritik' ? 'danger' : 
                                                    ($talep['acil_durum'] == 'yuksek' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo htmlspecialchars($acil_durum_seviyeleri[$talep['acil_durum']] ?? $talep['acil_durum']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Talep Eden:</strong> <?php echo htmlspecialchars($talep['talep_eden_adi'] . ' ' . $talep['talep_eden_soyadi']); ?></p>
                                        <p><strong>Atanan Teknisyen:</strong> 
                                            <?php if ($talep['teknisyen_adi']): ?>
                                                <?php echo htmlspecialchars($talep['teknisyen_adi'] . ' ' . $talep['teknisyen_soyadi']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Atanmamış</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <h6>Başlık:</h6>
                                    <p><?php echo htmlspecialchars($talep['baslik']); ?></p>
                                    
                                    <h6>Açıklama:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($talep['aciklama'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Durum Güncelleme -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-edit"></i> Durum Güncelle
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="durum_guncelle">
                                    <div class="mb-3">
                                        <label class="form-label">Yeni Durum</label>
                                        <select name="yeni_durum" class="form-select" required>
                                            <?php foreach ($durum_seviyeleri as $key => $value): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $talep['durum'] == $key ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($value); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Açıklama</label>
                                        <textarea name="aciklama" class="form-control" rows="3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save"></i> Güncelle
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Teknisyen Atama -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-user-cog"></i> Teknisyen Ata
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="teknisyen_ata">
                                    <div class="mb-3">
                                        <label class="form-label">Teknisyen</label>
                                        <select name="teknisyen_id" class="form-select" required>
                                            <option value="">Teknisyen Seçin</option>
                                            <?php foreach ($teknisyenler as $teknisyen): ?>
                                                <option value="<?php echo $teknisyen['id']; ?>" <?php echo $talep['atanan_teknisyen_id'] == $teknisyen['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($teknisyen['ad'] . ' ' . $teknisyen['soyad']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-user-plus"></i> Ata
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Talep Geçmişi -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history"></i> Talep Geçmişi
                                </h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Oluşturma:</strong> <?php echo date('d.m.Y H:i', strtotime($talep['olusturma_tarihi'])); ?></p>
                                <?php if ($talep['baslama_tarihi']): ?>
                                    <p><strong>Başlama:</strong> <?php echo date('d.m.Y H:i', strtotime($talep['baslama_tarihi'])); ?></p>
                                <?php endif; ?>
                                <?php if ($talep['bitis_tarihi']): ?>
                                    <p><strong>Bitiş:</strong> <?php echo date('d.m.Y H:i', strtotime($talep['bitis_tarihi'])); ?></p>
                                <?php endif; ?>
                                <p><strong>Son Güncelleme:</strong> <?php echo date('d.m.Y H:i', strtotime($talep['guncelleme_tarihi'])); ?></p>
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