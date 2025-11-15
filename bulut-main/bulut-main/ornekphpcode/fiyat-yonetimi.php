
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
requireDetailedPermission('fiyat_yonetimi_goruntule', 'Fiyat yönetimi görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Fiyat güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_pricing') {
    $oda_tipi_id = intval($_POST['oda_tipi_id']);
    $max_yetiskin = intval($_POST['max_yetiskin']);
    $max_cocuk = intval($_POST['max_cocuk']);
    $ucretsiz_cocuk_toplami = intval($_POST['ucretsiz_cocuk_toplami'] ?? 2);
    $minimum_yetiskin_sarti = intval($_POST['minimum_yetiskin_sarti'] ?? 2);
    
    // Yetişkin çarpanları
    $yetiskin_carpanlari = [];
    for ($i = 1; $i <= $max_yetiskin; $i++) {
        $yetiskin_carpanlari[$i] = floatval($_POST["yetiskin_carpan_$i"] ?? 1.0);
    }
    
    // Çocuk yaş aralıkları ve ücretsiz sayıları
    $ucretsiz_cocuk_yaslari = [];
    if (isset($_POST['ucretsiz_yas_araliklari'])) {
        foreach ($_POST['ucretsiz_yas_araliklari'] as $index => $yas_araligi) {
            if (!empty($yas_araligi)) {
                $ucretsiz_cocuk_yaslari[] = [
                    'yas_araligi' => sanitizeString($yas_araligi),
                    'cocuk_sayisi' => intval($_POST['ucretsiz_cocuk_sayilari'][$index] ?? 1)
                ];
            }
        }
    }
    
    try {
        // Çocuk çarpanları verilerini işle
        $cocuk_carpanlari = [];
        if (isset($_POST['cocuk_yas_gruplari']) && is_array($_POST['cocuk_yas_gruplari'])) {
            $yas_gruplari = $_POST['cocuk_yas_gruplari'];
            $carpan_degerleri = $_POST['cocuk_carpan_degerleri'] ?? [];
            $aciklamalar = $_POST['cocuk_carpan_aciklamalari'] ?? [];
            
            for ($i = 0; $i < count($yas_gruplari); $i++) {
                if (!empty($yas_gruplari[$i])) {
                    $cocuk_carpanlari[] = [
                        'yas_araligi' => trim($yas_gruplari[$i]),
                        'carpan' => floatval($carpan_degerleri[$i] ?? 0),
                        'aciklama' => trim($aciklamalar[$i] ?? '')
                    ];
                }
            }
        }

        // Oda tipi temel bilgilerini güncelle (yeni fiyat sistemi için sadece gerekli alanlar)
        $sql = "UPDATE oda_tipleri SET 
                max_yetiskin = ?, 
                max_cocuk = ?, 
                cocuk_yas_araligi = ?,
                ucretsiz_cocuk_toplami = ?,
                minimum_yetiskin_sarti = ?,
                yetiskin_carpanlari = ?,
                ucretsiz_cocuk_yaslari = ?,
                cocuk_carpanlari = ?
                WHERE id = ?";
        
        $result = executeQuery($sql, [
            $max_yetiskin,
            $max_cocuk,
            $cocuk_yas_araligi,
            $ucretsiz_cocuk_toplami,
            $minimum_yetiskin_sarti,
            json_encode($yetiskin_carpanlari, JSON_UNESCAPED_UNICODE),
            json_encode($ucretsiz_cocuk_yaslari, JSON_UNESCAPED_UNICODE),
            json_encode($cocuk_carpanlari, JSON_UNESCAPED_UNICODE),
            $oda_tipi_id
        ]);
        
        if ($result) {
            $success_message = 'Fiyatlandırma ayarları başarıyla güncellendi.';
        } else {
            $error_message = 'Güncelleme sırasında hata oluştu.';
        }
    } catch (Exception $e) {
        $error_message = 'Hata: ' . $e->getMessage();
    }
}

// Oda tiplerini getir
$oda_tipleri = fetchAll("SELECT * FROM oda_tipleri WHERE durum = 'aktif' ORDER BY sira_no ASC");

// Seçili oda tipi
$secili_oda_tipi_id = $_GET['oda_tipi'] ?? ($oda_tipleri[0]['id'] ?? 0);
$secili_oda_tipi = null;

if ($secili_oda_tipi_id) {
    $secili_oda_tipi = fetchOne("SELECT * FROM oda_tipleri WHERE id = ?", [$secili_oda_tipi_id]);
    
    // JSON verilerini çöz
    if ($secili_oda_tipi) {
        $secili_oda_tipi['yetiskin_carpanlari'] = json_decode($secili_oda_tipi['yetiskin_carpanlari'] ?? '{}', true) ?: [];
        $secili_oda_tipi['ucretsiz_cocuk_yaslari'] = json_decode($secili_oda_tipi['ucretsiz_cocuk_yaslari'] ?? '[]', true) ?: [];
        $secili_oda_tipi['cocuk_carpanlari'] = json_decode($secili_oda_tipi['cocuk_carpanlari'] ?? '[]', true) ?: [];
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiyat Yönetimi - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <?php include 'includes/header.php'; ?>

        <!-- Main Content -->
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Fiyat Yönetimi</h1>
                            <p class="text-muted">Oda tipi fiyatlandırma ayarlarını yönetin</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Oda Tipi Seçimi -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-6">
                            <label for="oda_tipi" class="form-label">Oda Tipi Seçin</label>
                            <select class="form-select" id="oda_tipi" name="oda_tipi" onchange="this.form.submit()">
                                <option value="">Oda tipi seçin</option>
                                <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                <option value="<?php echo $oda_tipi['id']; ?>" 
                                        <?php echo $secili_oda_tipi_id == $oda_tipi['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($secili_oda_tipi): ?>
            <!-- Fiyatlandırma Formu -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_pricing">
                <input type="hidden" name="oda_tipi_id" value="<?php echo $secili_oda_tipi['id']; ?>">

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Temel Fiyat Ayarları -->
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-money-bill-wave me-2"></i>Temel Fiyat Ayarları
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Yeni Fiyat Sistemi:</strong> Fiyatlar artık sezonluk fiyatlar, özel tarih fiyatları ve kampanya indirimleri üzerinden yönetilmektedir. 
                                    Bu sayfada sadece oda kapasitesi ve çocuk yaş aralıkları ayarlanabilir.
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_yetiskin" class="form-label">Maksimum Yetişkin Sayısı</label>
                                            <input type="number" class="form-control" id="max_yetiskin" name="max_yetiskin" 
                                                   value="<?php echo $secili_oda_tipi['max_yetiskin']; ?>" 
                                                   min="1" max="10" required onchange="updateAdultMultipliers()">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_cocuk" class="form-label">Maksimum Çocuk Sayısı</label>
                                            <input type="number" class="form-control" id="max_cocuk" name="max_cocuk" 
                                                   value="<?php echo $secili_oda_tipi['max_cocuk']; ?>" 
                                                   min="0" max="6" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Yetişkin Çarpanları -->
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-users me-2"></i>Yetişkin Sayısına Göre Çarpanlar
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="yetiskin-carpanlari">
                                    <?php 
                                    $yetiskin_carpanlari = $secili_oda_tipi['yetiskin_carpanlari'] ?: [];
                                    for ($i = 1; $i <= $secili_oda_tipi['max_yetiskin']; $i++): 
                                    ?>
                                    <div class="row mb-3 yetiskin-carpan-row">
                                        <div class="col-md-6">
                                            <label class="form-label"><?php echo $i; ?> Yetişkin</label>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <input type="number" class="form-control" 
                                                       name="yetiskin_carpan_<?php echo $i; ?>" 
                                                       value="<?php echo $yetiskin_carpanlari[$i] ?? ($i == 1 ? '1.0' : ($i * 0.8)); ?>" 
                                                       step="0.1" min="0.1" max="10">
                                                <span class="input-group-text">x</span>
                                            </div>
                                            <small class="text-muted">
                                                Örnek: 1.5 = %50 artış, 0.8 = %20 indirim
                                            </small>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Çocuk Ayarları -->
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-child me-2"></i>Çocuk Fiyatlandırma Ayarları
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="cocuk_yas_araligi" class="form-label">Çocuk Yaş Aralığı</label>
                                    <input type="text" class="form-control" id="cocuk_yas_araligi" name="cocuk_yas_araligi" 
                                           value="<?php echo htmlspecialchars($secili_oda_tipi['cocuk_yas_araligi'] ?? '0-12'); ?>" 
                                           placeholder="Örn: 0-12">
                                    <div class="form-text">Çocuk olarak kabul edilen yaş aralığını girin (örn: 0-12)</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Ücretsiz Çocuk Yaş Aralıkları</label>
                                    <div id="ucretsiz-cocuk-container">
                                        <?php 
                                        $ucretsiz_cocuk_yaslari = $secili_oda_tipi['ucretsiz_cocuk_yaslari'] ?: [];
                                        if (empty($ucretsiz_cocuk_yaslari)) {
                                            $ucretsiz_cocuk_yaslari = [['yas_araligi' => '0-6', 'cocuk_sayisi' => 1]];
                                        }
                                        foreach ($ucretsiz_cocuk_yaslari as $index => $yas_ayari): 
                                        ?>
                                        <div class="row mb-2 ucretsiz-cocuk-row">
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" 
                                                       name="ucretsiz_yas_araliklari[]" 
                                                       value="<?php echo htmlspecialchars($yas_ayari['yas_araligi']); ?>" 
                                                       placeholder="Örn: 0-6">
                                            </div>
                                            <div class="col-md-5">
                                                <div class="input-group">
                                                    <input type="number" class="form-control" 
                                                           name="ucretsiz_cocuk_sayilari[]" 
                                                           value="<?php echo $yas_ayari['cocuk_sayisi']; ?>" 
                                                           min="1" max="4">
                                                    <span class="input-group-text">çocuk ücretsiz</span>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeAgeRange(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addAgeRange()">
                                        <i class="fas fa-plus me-1"></i>Yaş Aralığı Ekle
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <label for="ucretsiz_cocuk_toplami" class="form-label">Ücretsiz Çocuk Toplamı</label>
                                    <input type="number" class="form-control" id="ucretsiz_cocuk_toplami" name="ucretsiz_cocuk_toplami" 
                                           value="<?php echo $secili_oda_tipi['ucretsiz_cocuk_toplami'] ?? 2; ?>" 
                                           min="0" max="6" placeholder="2">
                                    <div class="form-text">Toplam ücretsiz çocuk sayısını belirtin (tüm yaş aralıkları dahil)</div>
                                </div>

                                <div class="mb-3">
                                    <label for="minimum_yetiskin_sarti" class="form-label">Minimum Yetişkin Sayısı Şartı</label>
                                    <input type="number" class="form-control" id="minimum_yetiskin_sarti" name="minimum_yetiskin_sarti" 
                                           value="<?php echo $secili_oda_tipi['minimum_yetiskin_sarti'] ?? 2; ?>" 
                                           min="1" max="10" placeholder="2">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle text-info me-1"></i>
                                        Bu sayıdan az yetişkin varsa ücretsiz çocuk hakkı kullanılamaz. 
                                        <strong>Örnek:</strong> Minimum 2 yetişkin şartı varsa, 1 yetişkin + 2 çocuk rezervasyonunda tüm çocuklar ücretli olur.
                                    </div>
                                </div>

                                <!-- Çocuk Çarpanları -->
                                <div class="mb-4">
                                    <label class="form-label">Çocuk Yaş Grupları ve Çarpanları</label>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Çocuk Fiyatlandırması:</strong> Her yaş grubu için ayrı çarpan değeri belirleyebilirsiniz. 
                                        Örnek: 0.5 = %50 indirim, 0.75 = %25 indirim
                                    </div>
                                    <div id="cocuk-carpanlari-container">
                                        <?php 
                                        $cocuk_carpanlari = [];
                                        if ($secili_oda_tipi && isset($secili_oda_tipi['cocuk_carpanlari'])) {
                                            if (is_string($secili_oda_tipi['cocuk_carpanlari'])) {
                                                $cocuk_carpanlari = json_decode($secili_oda_tipi['cocuk_carpanlari'], true) ?: [];
                                            } elseif (is_array($secili_oda_tipi['cocuk_carpanlari'])) {
                                                $cocuk_carpanlari = $secili_oda_tipi['cocuk_carpanlari'];
                                            }
                                        }
                                        
                                        if (empty($cocuk_carpanlari) || !is_array($cocuk_carpanlari)) {
                                            $cocuk_carpanlari = [
                                                ['yas_araligi' => '0-2', 'carpan' => '0.0', 'aciklama' => 'Ücretsiz'],
                                                ['yas_araligi' => '3-6', 'carpan' => '0.5', 'aciklama' => '%50 indirim'],
                                                ['yas_araligi' => '7-12', 'carpan' => '0.75', 'aciklama' => '%25 indirim'],
                                                ['yas_araligi' => '13+', 'carpan' => '1.0', 'aciklama' => 'Tam fiyat']
                                            ];
                                        }
                                        foreach ($cocuk_carpanlari as $index => $carpan_ayari): 
                                        ?>
                                        <div class="row mb-3 cocuk-carpan-row">
                                            <div class="col-md-3">
                                                <label class="form-label">Yaş Aralığı</label>
                                                <input type="text" class="form-control" 
                                                       name="cocuk_yas_gruplari[]" 
                                                       value="<?php echo htmlspecialchars($carpan_ayari['yas_araligi']); ?>" 
                                                       placeholder="Örn: 3-6">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Çarpan</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" 
                                                           name="cocuk_carpan_degerleri[]" 
                                                           value="<?php echo $carpan_ayari['carpan']; ?>" 
                                                           step="0.1" min="0" max="2">
                                                    <span class="input-group-text">x</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Açıklama</label>
                                                <input type="text" class="form-control" 
                                                       name="cocuk_carpan_aciklamalari[]" 
                                                       value="<?php echo htmlspecialchars($carpan_ayari['aciklama']); ?>" 
                                                       placeholder="Örn: %50 indirim">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">&nbsp;</label>
                                                <div>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeChildMultiplier(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addChildMultiplier()">
                                        <i class="fas fa-plus me-1"></i>Yaş Grubu Ekle
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Fiyat Önizlemesi -->
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-calculator me-2"></i>Fiyat Önizlemesi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="fiyat-onizleme">
                                    <div class="mb-3">
                                        <strong>Ortalama Fiyat:</strong>
                                        <span id="preview-average-price"><?php echo formatCurrency($secili_oda_tipi['base_price'] ?? 0); ?></span>
                                        <small class="text-muted d-block">Yeni fiyatlandırma sistemi kullanılıyor</small>
                                    </div>

                                    <hr>

                                    <div id="yetiskin-fiyat-ornekleri">
                                        <h6>Yetişkin Kapasitesi:</h6>
                                        <div class="d-flex justify-content-between">
                                            <span>Maksimum Yetişkin:</span>
                                            <span><?php echo $secili_oda_tipi['max_yetiskin']; ?> kişi</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Maksimum Çocuk:</span>
                                            <span><?php echo $secili_oda_tipi['max_cocuk']; ?> kişi</span>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="mb-3">
                                        <h6>Çocuk Ayarları:</h6>
                                        <div><strong>Yaş Aralığı:</strong> <span id="preview-cocuk-yas"><?php echo htmlspecialchars($secili_oda_tipi['cocuk_yas_araligi'] ?? '0-12'); ?></span></div>
                                        <div><strong>Ücretsiz Çocuk Yaş Aralıkları:</strong> 
                                            <div id="preview-ucretsiz-yaslar">
                                                <?php 
                                                if (!empty($secili_oda_tipi['ucretsiz_cocuk_yaslari'])) {
                                                    foreach ($secili_oda_tipi['ucretsiz_cocuk_yaslari'] as $yas_ayari) {
                                                        echo '<small class="d-block">' . htmlspecialchars($yas_ayari['yas_araligi']) . ' yaş: ' . $yas_ayari['cocuk_sayisi'] . ' çocuk</small>';
                                                    }
                                                } else {
                                                    echo '<small class="text-muted">Henüz tanımlanmamış</small>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kaydet Butonu -->
                        <div class="card shadow">
                            <div class="card-body">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Fiyatlandırmayı Kaydet
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <div class="card shadow">
                <div class="card-body text-center py-5">
                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Fiyatlandırma yapmak için bir oda tipi seçin</h5>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Yetişkin çarpanlarını güncelle
        function updateAdultMultipliers() {
            const maxAdult = parseInt(document.getElementById('max_yetiskin').value);
            const container = document.getElementById('yetiskin-carpanlari');
            
            // Mevcut satırları temizle
            container.innerHTML = '';
            
            // Yeni satırlar oluştur
            for (let i = 1; i <= maxAdult; i++) {
                const defaultValue = i === 1 ? '1.0' : (i * 0.8).toFixed(1);
                const row = `
                    <div class="row mb-3 yetiskin-carpan-row">
                        <div class="col-md-6">
                            <label class="form-label">${i} Yetişkin</label>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="number" class="form-control" 
                                       name="yetiskin_carpan_${i}" 
                                       value="${defaultValue}" 
                                       step="0.1" min="0.1" max="10"
                                       onchange="updatePreview()">
                                <span class="input-group-text">x</span>
                            </div>
                            <small class="text-muted">
                                Örnek: 1.5 = %50 artış, 0.8 = %20 indirim
                            </small>
                        </div>
                    </div>
                `;
                container.innerHTML += row;
            }
            
            updatePreview();
        }

        // Yaş aralığı ekle
        function addAgeRange() {
            const container = document.getElementById('ucretsiz-cocuk-container');
            const row = `
                <div class="row mb-2 ucretsiz-cocuk-row">
                    <div class="col-md-5">
                        <input type="text" class="form-control" 
                               name="ucretsiz_yas_araliklari[]" 
                               placeholder="Örn: 7-12">
                    </div>
                    <div class="col-md-5">
                        <div class="input-group">
                            <input type="number" class="form-control" 
                                   name="ucretsiz_cocuk_sayilari[]" 
                                   value="1" min="1" max="4">
                            <span class="input-group-text">çocuk ücretsiz</span>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeAgeRange(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', row);
        }

        // Yaş aralığı sil
        function removeAgeRange(button) {
            button.closest('.ucretsiz-cocuk-row').remove();
        }

        // Çocuk çarpanı ekle
        function addChildMultiplier() {
            const container = document.getElementById('cocuk-carpanlari-container');
            const row = `
                <div class="row mb-3 cocuk-carpan-row">
                    <div class="col-md-3">
                        <label class="form-label">Yaş Aralığı</label>
                        <input type="text" class="form-control" 
                               name="cocuk_yas_gruplari[]" 
                               placeholder="Örn: 3-6">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Çarpan</label>
                        <div class="input-group">
                            <input type="number" class="form-control" 
                                   name="cocuk_carpan_degerleri[]" 
                                   value="0.5" 
                                   step="0.1" min="0" max="2">
                            <span class="input-group-text">x</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Açıklama</label>
                        <input type="text" class="form-control" 
                               name="cocuk_carpan_aciklamalari[]" 
                               placeholder="Örn: %50 indirim">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeChildMultiplier(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', row);
        }

        // Çocuk çarpanı sil
        function removeChildMultiplier(button) {
            button.closest('.cocuk-carpan-row').remove();
        }

        // Önizlemeyi güncelle
        function updatePreview() {
            // Çocuk yaş aralığı önizlemesi
            const cocukYasAraligi = document.getElementById('cocuk_yas_araligi').value;
            document.getElementById('preview-cocuk-yas').textContent = cocukYasAraligi || '0-12';
        }

        // Form değişikliklerini dinle
        document.addEventListener('DOMContentLoaded', function() {
            // Tüm input'ları dinle
            document.querySelectorAll('input, select').forEach(element => {
                element.addEventListener('change', updatePreview);
                element.addEventListener('input', updatePreview);
            });
            
            // İlk yükleme
            updatePreview();
        });
    </script>
</body>
</html>
