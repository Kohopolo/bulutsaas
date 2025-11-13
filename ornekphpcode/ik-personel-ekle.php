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
if (!hasDetailedPermission('ik_personel_ekle')) {
    $_SESSION['error_message'] = 'İK personel ekleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

$success_message = '';
$error_message = '';

// POST işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Kullanıcı bilgileri
        $ad = sanitizeString($_POST['ad']);
        $soyad = sanitizeString($_POST['soyad']);
        $email = sanitizeString($_POST['email']);
        $telefon = sanitizeString($_POST['telefon']);
        $rol = sanitizeString($_POST['rol']);
        $sifre = password_hash($_POST['sifre'], PASSWORD_DEFAULT);
        
        // Kullanıcı oluştur
        $sql = "INSERT INTO kullanicilar (ad, soyad, email, telefon, sifre, rol, durum, aktif, olusturma_tarihi) VALUES (?, ?, ?, ?, ?, ?, 'aktif', 1, NOW())";
        
        if (executeQuery($sql, [$ad, $soyad, $email, $telefon, $sifre, $rol])) {
            $kullanici_id = $pdo->lastInsertId();
            
            // Personel bilgileri
            $tc_no = sanitizeString($_POST['tc_no']);
            $dogum_tarihi = !empty($_POST['dogum_tarihi']) ? $_POST['dogum_tarihi'] : null;
            $cinsiyet = sanitizeString($_POST['cinsiyet']);
            $medeni_hal = sanitizeString($_POST['medeni_hal']);
            $adres = sanitizeString($_POST['adres']);
            $acil_durum_kisi = sanitizeString($_POST['acil_durum_kisi']);
            $acil_durum_telefon = sanitizeString($_POST['acil_durum_telefon']);
            $kan_grubu = sanitizeString($_POST['kan_grubu']);
            $ehliyet_no = sanitizeString($_POST['ehliyet_no']);
            $ehliyet_sinifi = sanitizeString($_POST['ehliyet_sinifi']);
            $ise_giris_tarihi = !empty($_POST['ise_giris_tarihi']) ? $_POST['ise_giris_tarihi'] : null;
            $maas = floatval($_POST['maas']);
            $departman = sanitizeString($_POST['departman']);
            $pozisyon = sanitizeString($_POST['pozisyon']);
            $calisma_saati = sanitizeString($_POST['calisma_saati']);
            
            // Personel bilgilerini ekle
            $sql = "INSERT INTO personel_bilgileri (kullanici_id, tc_no, dogum_tarihi, cinsiyet, medeni_hal, adres, telefon, acil_durum_kisi, acil_durum_telefon, kan_grubu, ehliyet_no, ehliyet_sinifi, ise_giris_tarihi, maas, departman, pozisyon, calisma_saati) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if (executeQuery($sql, [$kullanici_id, $tc_no, $dogum_tarihi, $cinsiyet, $medeni_hal, $adres, $telefon, $acil_durum_kisi, $acil_durum_telefon, $kan_grubu, $ehliyet_no, $ehliyet_sinifi, $ise_giris_tarihi, $maas, $departman, $pozisyon, $calisma_saati])) {
                $pdo->commit();
                $success_message = 'Personel başarıyla eklendi.';
            } else {
                throw new Exception('Personel bilgileri eklenirken hata oluştu.');
            }
        } else {
            throw new Exception('Kullanıcı oluşturulurken hata oluştu.');
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Rolleri getir
$roller = fetchAll("
    SELECT DISTINCT rol 
    FROM kullanicilar 
    WHERE rol IS NOT NULL AND rol != ''
    ORDER BY rol
");

// Departmanları getir
$departmanlar = fetchAll("
    SELECT DISTINCT departman 
    FROM personel_bilgileri 
    WHERE departman IS NOT NULL AND departman != ''
    ORDER BY departman
");

// Cinsiyet seçenekleri
$cinsiyet_secenekleri = [
    'erkek' => 'Erkek',
    'kadın' => 'Kadın'
];

// Medeni hal seçenekleri
$medeni_hal_secenekleri = [
    'bekar' => 'Bekar',
    'evli' => 'Evli',
    'boşanmış' => 'Boşanmış',
    'dul' => 'Dul'
];

// Çalışma saati seçenekleri
$calisma_saati_secenekleri = [
    'tam_zamanli' => 'Tam Zamanlı',
    'yarim_zamanli' => 'Yarım Zamanlı',
    'gece_vardiyasi' => 'Gece Vardiyası'
];

// Kan grubu seçenekleri
$kan_grubu_secenekleri = [
    'A+' => 'A+',
    'A-' => 'A-',
    'B+' => 'B+',
    'B-' => 'B-',
    'AB+' => 'AB+',
    'AB-' => 'AB-',
    '0+' => '0+',
    '0-' => '0-'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İK Personel Ekle</title>
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
                    <h1 class="h2">İK Personel Ekle</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="ik-personel-listesi.php" class="btn btn-outline-secondary">
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

                <form method="POST">
                    <!-- Temel Bilgiler -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user"></i> Temel Bilgiler
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Ad <span class="text-danger">*</span></label>
                                    <input type="text" name="ad" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Soyad <span class="text-danger">*</span></label>
                                    <input type="text" name="soyad" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">TC Kimlik No</label>
                                    <input type="text" name="tc_no" class="form-control" maxlength="11">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Telefon <span class="text-danger">*</span></label>
                                    <input type="text" name="telefon" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Şifre <span class="text-danger">*</span></label>
                                    <input type="password" name="sifre" class="form-control" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Rol <span class="text-danger">*</span></label>
                                    <select name="rol" class="form-select" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($roller as $rol): ?>
                                            <option value="<?php echo htmlspecialchars($rol['rol']); ?>">
                                                <?php echo htmlspecialchars($rol['rol']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cinsiyet</label>
                                    <select name="cinsiyet" class="form-select">
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($cinsiyet_secenekleri as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Medeni Hal</label>
                                    <select name="medeni_hal" class="form-select">
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($medeni_hal_secenekleri as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Doğum Tarihi</label>
                                    <input type="date" name="dogum_tarihi" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Kan Grubu</label>
                                    <select name="kan_grubu" class="form-select">
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($kan_grubu_secenekleri as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">İşe Giriş Tarihi</label>
                                    <input type="date" name="ise_giris_tarihi" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- İş Bilgileri -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-briefcase"></i> İş Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Departman</label>
                                    <select name="departman" class="form-select">
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($departmanlar as $departman): ?>
                                            <option value="<?php echo htmlspecialchars($departman['departman']); ?>">
                                                <?php echo htmlspecialchars($departman['departman']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Pozisyon</label>
                                    <input type="text" name="pozisyon" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Çalışma Saati</label>
                                    <select name="calisma_saati" class="form-select">
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($calisma_saati_secenekleri as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Maaş</label>
                                    <input type="number" name="maas" class="form-control" step="0.01" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Adres</label>
                                    <textarea name="adres" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acil Durum Bilgileri -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-phone"></i> Acil Durum Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Acil Durum Kişisi</label>
                                    <input type="text" name="acil_durum_kisi" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Acil Durum Telefonu</label>
                                    <input type="text" name="acil_durum_telefon" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ehliyet Bilgileri -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-id-card"></i> Ehliyet Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Ehliyet No</label>
                                    <input type="text" name="ehliyet_no" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Ehliyet Sınıfı</label>
                                    <input type="text" name="ehliyet_sinifi" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Personel Ekle
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
