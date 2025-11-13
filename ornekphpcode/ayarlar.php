
<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';

// Güvenli session başlatma
startSecureSession();

// Admin kontrolü
require_once '../includes/functions.php';
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('sistem_ayarlari', 'Sistem ayarları yetkiniz bulunmamaktadır.');

// Türkçe karakter desteği
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// Dinamik yol belirleme fonksiyonu
function getAdminPath($relativePath) {
    $currentDir = dirname(__FILE__);
    $rootDir = dirname($currentDir);
    $fullPath = $rootDir . '/' . $relativePath;
    
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    
    $alternatives = [
        '../' . $relativePath,
        './' . $relativePath
    ];
    
    foreach ($alternatives as $altPath) {
        if (file_exists($altPath)) {
            return $altPath;
        }
    }
    
    return $relativePath;
}

require_once getAdminPath('config/database.php');
require_once getAdminPath('includes/functions.php');
require_once getAdminPath('includes/tema-sistemi.php');

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('sistem_ayarlari_goruntule', 'Sistem ayarlarını görüntüleme yetkiniz bulunmamaktadır.');

// Güvenlik kontrolü
if (!startSecureSession()) {
    logSecurityEvent('SESSION_SECURITY_VIOLATION', 'Güvensiz session tespit edildi');
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// CSRF token kontrolü
if ($_POST && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $error_message = 'Güvenlik hatası: Geçersiz form token.';
    logSecurityEvent('CSRF_TOKEN_INVALID', 'Ayarlar sayfasında geçersiz CSRF token');
}

// Ayarları kaydet
if ($_POST && empty($error_message)) {
    try {
        // Genel ayarlar
        if (isset($_POST['genel_ayarlar'])) {
            $otel_adi = sanitizeString($_POST['otel_adi']);
            $otel_aciklama = sanitizeString($_POST['otel_aciklama']);
            $adres = sanitizeString($_POST['adres']);
            $telefon = sanitizeString($_POST['telefon']);
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            $website = filter_var($_POST['website'], FILTER_VALIDATE_URL);
            
            if (!$email) {
                throw new Exception('Geçersiz e-posta adresi.');
            }
            
            // Ayarları güncelle veya ekle
            $ayarlar = [
                'otel_adi' => $otel_adi,
                'otel_aciklama' => $otel_aciklama,
                'adres' => $adres,
                'telefon' => $telefon,
                'email' => $email,
                'website' => $website ?: ''
            ];
            
            foreach ($ayarlar as $anahtar => $deger) {
                setSistemAyari($anahtar, $deger);
            }
            
            $success_message = 'Genel ayarlar başarıyla güncellendi.';
            logSecurityEvent('SETTINGS_UPDATED', 'Genel ayarlar güncellendi', isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
        }
        
        // Tema ayarları
        if (isset($_POST['tema_ayarlari'])) {
            $yeni_tema = sanitizeString($_POST['aktif_tema']);
            
            if (temaDegistir($yeni_tema)) {
                $success_message = 'Tema başarıyla değiştirildi.';
                logSecurityEvent('THEME_CHANGED', "Tema değiştirildi: $yeni_tema", isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
            } else {
                throw new Exception('Geçersiz tema seçimi.');
            }
        }
        
        // Logo ve favicon yükleme
        if (isset($_POST['logo_favicon_ayarlari'])) {
            $upload_dir = '../assets/images/';
            
            // Logo yükleme
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logo_file = $_FILES['logo'];
                $logo_ext = strtolower(pathinfo($logo_file['name'], PATHINFO_EXTENSION));
                $allowed_logo_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                
                if (in_array($logo_ext, $allowed_logo_types)) {
                    $logo_name = 'logo.' . $logo_ext;
                    $logo_path = $upload_dir . $logo_name;
                    
                    if (move_uploaded_file($logo_file['tmp_name'], $logo_path)) {
                        setSistemAyari('logo_url', 'assets/images/' . $logo_name);
                        $success_message = 'Logo başarıyla yüklendi.';
                    } else {
                        throw new Exception('Logo yüklenirken hata oluştu.');
                    }
                } else {
                    throw new Exception('Geçersiz logo formatı. Sadece JPG, PNG, GIF, WebP ve SVG dosyaları kabul edilir.');
                }
            }
            
            // Favicon yükleme
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $favicon_file = $_FILES['favicon'];
                $favicon_ext = strtolower(pathinfo($favicon_file['name'], PATHINFO_EXTENSION));
                $allowed_favicon_types = ['ico', 'png', 'gif'];
                
                if (in_array($favicon_ext, $allowed_favicon_types)) {
                    $favicon_name = 'favicon.' . $favicon_ext;
                    $favicon_path = $upload_dir . $favicon_name;
                    
                    if (move_uploaded_file($favicon_file['tmp_name'], $favicon_path)) {
                        setSistemAyari('favicon_url', 'assets/images/' . $favicon_name);
                        $success_message = ($success_message ? $success_message . ' ' : '') . 'Favicon başarıyla yüklendi.';
                    } else {
                        throw new Exception('Favicon yüklenirken hata oluştu.');
                    }
                } else {
                    throw new Exception('Geçersiz favicon formatı. Sadece ICO, PNG ve GIF dosyaları kabul edilir.');
                }
            }
            
            logSecurityEvent('LOGO_FAVICON_UPDATED', 'Logo ve favicon güncellendi', isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
        }
        
        // İletişim butonları ayarları
        if (isset($_POST['iletisim_ayarlari'])) {
            $whatsapp_aktif = isset($_POST['whatsapp_aktif']) ? '1' : '0';
            $whatsapp_numara = sanitizeString($_POST['whatsapp_numara']);
            $whatsapp_mesaj = sanitizeString($_POST['whatsapp_mesaj']);
            $telefon_aktif = isset($_POST['telefon_aktif']) ? '1' : '0';
            $telefon_numara = sanitizeString($_POST['telefon_numara']);
            
            // Telefon numarası formatı kontrolü
            if ($telefon_aktif && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $telefon_numara)) {
                throw new Exception('Geçersiz telefon numarası formatı.');
            }
            
            if ($whatsapp_aktif && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $whatsapp_numara)) {
                throw new Exception('Geçersiz WhatsApp numarası formatı.');
            }
            
            $iletisim_ayarlari = [
                'whatsapp_aktif' => $whatsapp_aktif,
                'whatsapp_numara' => $whatsapp_numara,
                'whatsapp_mesaj' => $whatsapp_mesaj,
                'telefon_aktif' => $telefon_aktif,
                'telefon_numara' => $telefon_numara
            ];
            
            foreach ($iletisim_ayarlari as $anahtar => $deger) {
                setSistemAyari($anahtar, $deger);
            }
            
            $success_message = 'İletişim butonları ayarları başarıyla güncellendi.';
            logSecurityEvent('CONTACT_BUTTONS_UPDATED', 'İletişim butonları güncellendi');
        }
        
        // Hakkımızda sayfası ayarları
        if (isset($_POST['hakkimizda_ayarlari'])) {
            $hakkimizda_baslik = sanitizeString($_POST['hakkimizda_baslik']);
            $hakkimizda_aciklama = sanitizeString($_POST['hakkimizda_aciklama']);
            $hakkimizda_detay = sanitizeString($_POST['hakkimizda_detay']);
            $hakkimizda_resim = sanitizeString($_POST['hakkimizda_resim']);
            
            $hakkimizda_ayarlari = [
                'hakkimizda_baslik' => $hakkimizda_baslik,
                'hakkimizda_aciklama' => $hakkimizda_aciklama,
                'hakkimizda_detay' => $hakkimizda_detay,
                'hakkimizda_resim' => $hakkimizda_resim
            ];
            
            foreach ($hakkimizda_ayarlari as $anahtar => $deger) {
                setSistemAyari($anahtar, $deger);
            }
            
            $success_message = 'Hakkımızda sayfası ayarları başarıyla güncellendi.';
            logSecurityEvent('ABOUT_PAGE_UPDATED', 'Hakkımızda sayfası güncellendi');
        }
        
        // Galeri yönetimi
        if (isset($_POST['galeri_ayarlari'])) {
            // Resim yükleme işlemi
            if (isset($_FILES['galeri_resim']) && $_FILES['galeri_resim']['error'] == 0) {
                $upload_dir = '../assets/images/galeri/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['galeri_resim']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $file_name = uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['galeri_resim']['tmp_name'], $file_path)) {
                        $resim_baslik = sanitizeString($_POST['resim_baslik']);
                        $resim_aciklama = sanitizeString($_POST['resim_aciklama']);
                        
                        // Veritabanına kaydet
                         $sql = "INSERT INTO galeri (resim_url, baslik, aciklama, sira_no, durum) VALUES (?, ?, ?, ?, 'aktif')";
                         $sira_no = (int)($_POST['sira_no'] ?? 0);
                         
                         if (executeQuery($sql, ['assets/images/galeri/' . $file_name, $resim_baslik, $resim_aciklama, $sira_no])) {
                            $success_message = 'Galeri resmi başarıyla eklendi.';
                            logSecurityEvent('GALLERY_IMAGE_ADDED', 'Galeri resmi eklendi: ' . $file_name, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
                        } else {
                            throw new Exception('Resim veritabanına kaydedilemedi.');
                        }
                    } else {
                        throw new Exception('Resim yüklenemedi.');
                    }
                } else {
                    throw new Exception('Geçersiz dosya formatı. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.');
                }
            }
        }
        
        // Galeri resmi silme
        if (isset($_POST['galeri_sil'])) {
            $resim_id = (int)$_POST['resim_id'];
            
            // Önce resim bilgilerini al
            $sql = "SELECT resim_url FROM galeri WHERE id = ?";
            $resim = fetchOne($sql, [$resim_id]);
            
            if ($resim) {
                // Dosyayı sil
                $file_path = '../' . $resim['resim_url'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Veritabanından sil
                $sql = "DELETE FROM galeri WHERE id = ?";
                if (executeQuery($sql, [$resim_id])) {
                    $success_message = 'Galeri resmi başarıyla silindi.';
                    logSecurityEvent('GALLERY_IMAGE_DELETED', 'Galeri resmi silindi: ' . $resim_id, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
                } else {
                    throw new Exception('Resim silinemedi.');
                }
            }
        }
        
        // SEO ayarları
        if (isset($_POST['seo_ayarlari'])) {
            $meta_title = sanitizeString($_POST['meta_title']);
            $meta_description = sanitizeString($_POST['meta_description']);
            $meta_keywords = sanitizeString($_POST['meta_keywords']);
            $og_title = sanitizeString($_POST['og_title']);
            $og_description = sanitizeString($_POST['og_description']);
            $og_image = sanitizeString($_POST['og_image']);
            $twitter_title = sanitizeString($_POST['twitter_title']);
            $twitter_description = sanitizeString($_POST['twitter_description']);
            $twitter_image = sanitizeString($_POST['twitter_image']);
            $google_analytics = sanitizeString($_POST['google_analytics']);
            $google_search_console = sanitizeString($_POST['google_search_console']);
            
            $seo_ayarlari = [
                'meta_title' => $meta_title,
                'meta_description' => $meta_description,
                'meta_keywords' => $meta_keywords,
                'og_title' => $og_title,
                'og_description' => $og_description,
                'og_image' => $og_image,
                'twitter_title' => $twitter_title,
                'twitter_description' => $twitter_description,
                'twitter_image' => $twitter_image,
                'google_analytics' => $google_analytics,
                'google_search_console' => $google_search_console
            ];
            
            foreach ($seo_ayarlari as $anahtar => $deger) {
                setSistemAyari($anahtar, $deger);
            }
            
            $success_message = 'SEO ayarları başarıyla güncellendi.';
            logSecurityEvent('SEO_SETTINGS_UPDATED', 'SEO ayarları güncellendi', isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
        }
        
        // Google Harita ayarları
        if (isset($_POST['harita_ayarlari'])) {
            $google_maps_api_key = sanitizeString($_POST['google_maps_api_key']);
            $harita_enlem = sanitizeString($_POST['harita_enlem']);
            $harita_boylam = sanitizeString($_POST['harita_boylam']);
            $harita_zoom = (int)($_POST['harita_zoom'] ?? 15);
            $harita_aktif = isset($_POST['harita_aktif']) ? '1' : '0';
            $harita_stil = sanitizeString($_POST['harita_stil']);
            
            // Koordinat formatı kontrolü
            if ($harita_aktif && (!is_numeric($harita_enlem) || !is_numeric($harita_boylam))) {
                throw new Exception('Geçersiz koordinat formatı. Lütfen geçerli enlem ve boylam değerleri girin.');
            }
            
            $harita_ayarlari = [
                'google_maps_api_key' => $google_maps_api_key,
                'harita_enlem' => $harita_enlem,
                'harita_boylam' => $harita_boylam,
                'harita_zoom' => $harita_zoom,
                'harita_aktif' => $harita_aktif,
                'harita_stil' => $harita_stil
            ];
            
            foreach ($harita_ayarlari as $anahtar => $deger) {
                setSistemAyari($anahtar, $deger);
            }
            
            $success_message = 'Google Harita ayarları başarıyla güncellendi.';
            logSecurityEvent('GOOGLE_MAPS_UPDATED', 'Google Harita ayarları güncellendi', isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
        }
        
    } catch (Exception $e) {
        $error_message = 'Ayarlar güncellenirken hata oluştu: ' . $e->getMessage();
        logSecurityEvent('SETTINGS_UPDATE_ERROR', $e->getMessage(), isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
    }
}

// getAyar fonksiyonu artık includes/functions.php dosyasında tanımlı
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar - Admin Paneli</title>
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
                    <h1 class="h3 mb-0">Sistem Ayarları</h1>
                    <p class="text-muted">Otel yönetim sistemi ayarlarını yapılandırın</p>
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

            <!-- Ayar Sekmeleri -->
            <div class="card shadow">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="ayarTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="genel-tab" data-bs-toggle="tab" data-bs-target="#genel" type="button" role="tab">
                                <i class="fas fa-hotel me-2"></i>Genel Ayarlar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tema-tab" data-bs-toggle="tab" data-bs-target="#tema" type="button" role="tab">
                                <i class="fas fa-palette me-2"></i>Tema Ayarları
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="logo-tab" data-bs-toggle="tab" data-bs-target="#logo" type="button" role="tab">
                                <i class="fas fa-image me-2"></i>Logo & Favicon
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="iletisim-tab" data-bs-toggle="tab" data-bs-target="#iletisim" type="button" role="tab">
                                <i class="fas fa-phone me-2"></i>İletişim Butonları
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="hakkimizda-tab" data-bs-toggle="tab" data-bs-target="#hakkimizda" type="button" role="tab">
                                <i class="fas fa-info-circle me-2"></i>Hakkımızda Sayfası
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="galeri-tab" data-bs-toggle="tab" data-bs-target="#galeri" type="button" role="tab">
                                <i class="fas fa-images me-2"></i>Galeri Yönetimi
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo" type="button" role="tab">
                                <i class="fas fa-search me-2"></i>SEO Ayarları
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="harita-tab" data-bs-toggle="tab" data-bs-target="#harita" type="button" role="tab">
                                <i class="fas fa-map-marker-alt me-2"></i>Google Harita
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="ayarTabContent">
                        <!-- Genel Ayarlar -->
                        <div class="tab-pane fade show active" id="genel" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="otel_adi" class="form-label">Otel Adı</label>
                                            <input type="text" class="form-control" id="otel_adi" name="otel_adi" 
                                                   value="<?php echo htmlspecialchars(getAyar('otel_adi', 'Otelim')); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="website" class="form-label">Website</label>
                                            <input type="url" class="form-control" id="website" name="website" 
                                                   value="<?php echo htmlspecialchars(getAyar('website')); ?>" 
                                                   placeholder="https://www.otelim.com">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="otel_aciklama" class="form-label">Otel Açıklaması</label>
                                    <textarea class="form-control" id="otel_aciklama" name="otel_aciklama" rows="3"><?php echo htmlspecialchars(getAyar('otel_aciklama')); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="adres" class="form-label">Adres</label>
                                    <textarea class="form-control" id="adres" name="adres" rows="2"><?php echo htmlspecialchars(getAyar('adres')); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="telefon" class="form-label">Telefon</label>
                                            <input type="tel" class="form-control" id="telefon" name="telefon" 
                                                   value="<?php echo htmlspecialchars(getAyar('telefon')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">E-posta</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars(getAyar('email')); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="genel_ayarlar" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Kaydet
                                </button>
                            </form>
                        </div>

                        <!-- Tema Ayarları -->
                        <div class="tab-pane fade" id="tema" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-4">
                                    <h5>Aktif Tema Seçimi</h5>
                                    <p class="text-muted">Web sitenizin görünümünü değiştirmek için bir tema seçin.</p>
                                </div>
                                
                                <div class="row">
                                    <?php 
                                    $tumTemalar = getTumTemalar();
                                    $aktifTema = getAktifTema();
                                    if (is_array($tumTemalar)) {
                                        foreach ($tumTemalar as $tema_kodu => $tema_bilgi): 
                                    ?>
                                    <div class="col-lg-4 mb-4">
                                        <div class="card tema-card <?php echo $aktifTema === $tema_kodu ? 'border-primary' : ''; ?>">
                                            <div class="card-body text-center">
                                                <div class="tema-preview mb-3">
                                                    <div class="tema-colors d-flex justify-content-center gap-1">
                                                        <?php 
                                                        if (isset($tema_bilgi['renk_paleti']) && is_array($tema_bilgi['renk_paleti'])) {
                                                            foreach ($tema_bilgi['renk_paleti'] as $renk): 
                                                        ?>
                                                        <div class="tema-color" style="background-color: <?php echo $renk; ?>; width: 20px; height: 20px; border-radius: 50%;"></div>
                                                        <?php 
                                                            endforeach; 
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <h6><?php echo htmlspecialchars($tema_bilgi['ad'] ?? 'Tema Adı'); ?></h6>
                                                <p class="text-muted small"><?php echo htmlspecialchars($tema_bilgi['aciklama'] ?? 'Açıklama yok'); ?></p>
                                                
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="aktif_tema" 
                                                           id="tema_<?php echo $tema_kodu; ?>" value="<?php echo $tema_kodu; ?>"
                                                           <?php echo $aktifTema === $tema_kodu ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="tema_<?php echo $tema_kodu; ?>">
                                                        <?php echo $aktifTema === $tema_kodu ? 'Aktif Tema' : 'Bu Temayı Seç'; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php 
                                        endforeach; 
                                    }
                                    ?>
                                </div>
                                
                                <button type="submit" name="tema_ayarlari" class="btn btn-primary">
                                    <i class="fas fa-palette me-2"></i>Temayı Değiştir
                                </button>
                            </form>
                        </div>

                        <!-- Logo & Favicon -->
                        <div class="tab-pane fade" id="logo" role="tabpanel">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="logo_favicon_ayarlari" value="1">
                                
                                <div class="mb-4">
                                    <h5>Logo & Favicon Ayarları</h5>
                                    <p class="text-muted">Web sitenizin logo ve favicon dosyalarını yönetin.</p>
                                </div>
                                
                                <!-- Logo Ayarları -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-image me-2 text-primary"></i>Logo Yükleme</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="logo" class="form-label">Logo Dosyası</label>
                                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                                    <div class="form-text">PNG, JPG, GIF formatları desteklenir. Maksimum 2MB.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Mevcut Logo</label>
                                                    <div class="border rounded p-3 text-center">
                                                        <?php 
                                                        $mevcut_logo = getAyar('logo_url', '');
                                                        if ($mevcut_logo && file_exists('../' . $mevcut_logo)): 
                                                        ?>
                                                            <img src="../<?= htmlspecialchars($mevcut_logo) ?>" alt="Mevcut Logo" class="img-fluid" style="max-height: 100px;">
                                                        <?php else: ?>
                                                            <div class="text-muted">
                                                                <i class="fas fa-image fa-3x mb-2"></i>
                                                                <p>Logo yüklenmemiş</p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Favicon Ayarları -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-star me-2 text-warning"></i>Favicon Yükleme</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="favicon" class="form-label">Favicon Dosyası</label>
                                                    <input type="file" class="form-control" id="favicon" name="favicon" accept="image/*">
                                                    <div class="form-text">ICO, PNG formatları önerilir. 32x32 veya 16x16 piksel boyutunda.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Mevcut Favicon</label>
                                                    <div class="border rounded p-3 text-center">
                                                        <?php 
                                                        $mevcut_favicon = getAyar('favicon_url', '');
                                                        if ($mevcut_favicon && file_exists('../' . $mevcut_favicon)): 
                                                        ?>
                                                            <img src="../<?= htmlspecialchars($mevcut_favicon) ?>" alt="Mevcut Favicon" style="width: 32px; height: 32px;">
                                                        <?php else: ?>
                                                            <div class="text-muted">
                                                                <i class="fas fa-star fa-2x mb-2"></i>
                                                                <p>Favicon yüklenmemiş</p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="logo_favicon_ayarlari" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Logo & Favicon Güncelle
                                </button>
                            </form>
                        </div>

                        <!-- İletişim Butonları -->
                        <div class="tab-pane fade" id="iletisim" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-4">
                                    <h5>İletişim Butonları Ayarları</h5>
                                    <p class="text-muted">Web sitenizde görünecek WhatsApp ve telefon butonlarını yapılandırın.</p>
                                </div>
                                
                                <!-- WhatsApp Ayarları -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp Butonu</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="whatsapp_aktif" name="whatsapp_aktif" 
                                                   <?php echo getAyar('whatsapp_aktif', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="whatsapp_aktif">
                                                WhatsApp butonunu aktif et
                                            </label>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="whatsapp_numara" class="form-label">WhatsApp Numarası</label>
                                                    <input type="tel" class="form-control" id="whatsapp_numara" name="whatsapp_numara" 
                                                           value="<?php echo htmlspecialchars(getAyar('whatsapp_numara')); ?>"
                                                           placeholder="+90 555 123 45 67">
                                                    <div class="form-text">Ülke kodu ile birlikte yazın (örn: +90 555 123 45 67)</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="whatsapp_mesaj" class="form-label">Varsayılan Mesaj</label>
                                                    <textarea class="form-control" id="whatsapp_mesaj" name="whatsapp_mesaj" rows="3"><?php echo htmlspecialchars(getAyar('whatsapp_mesaj', 'Merhaba, otel hakkında bilgi almak istiyorum.')); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Telefon Ayarları -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-phone me-2 text-primary"></i>Telefon Butonu</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="telefon_aktif" name="telefon_aktif" 
                                                   <?php echo getAyar('telefon_aktif', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="telefon_aktif">
                                                Telefon butonunu aktif et
                                            </label>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="telefon_numara" class="form-label">Telefon Numarası</label>
                                            <input type="tel" class="form-control" id="telefon_numara" name="telefon_numara" 
                                                   value="<?php echo htmlspecialchars(getAyar('telefon_numara')); ?>"
                                                   placeholder="+90 555 123 45 67">
                                            <div class="form-text">Ülke kodu ile birlikte yazın (örn: +90 555 123 45 67)</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Önizleme -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-eye me-2"></i>Buton Önizlemesi</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="position-relative" style="height: 200px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 8px;">
                                            <div class="position-absolute" style="right: 20px; bottom: 20px;">
                                                <div class="d-flex flex-column gap-2">
                                                    <div class="iletisim-btn whatsapp-btn" style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #25d366, #128c7e); display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </div>
                                                    <div class="iletisim-btn telefon-btn" style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #0d6efd, #0056b3); display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);">
                                                        <i class="fas fa-phone"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                                <i class="fas fa-mobile-alt fa-3x text-muted mb-2"></i>
                                                <p class="text-muted">Butonlar bu şekilde görünecek</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="iletisim_ayarlari" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Kaydet
                                </button>
                            </form>
                        </div>
                        
                        <!-- Hakkımızda Sayfası -->
                        <div class="tab-pane fade" id="hakkimizda" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-4">
                                    <h5>Hakkımızda Sayfası İçeriği</h5>
                                    <p class="text-muted">Hakkımızda sayfasında görünecek içerikleri düzenleyin.</p>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hakkimizda_baslik" class="form-label">Sayfa Başlığı</label>
                                    <input type="text" class="form-control" id="hakkimizda_baslik" name="hakkimizda_baslik" 
                                           value="<?php echo htmlspecialchars(getAyar('hakkimizda_baslik', 'Hakkımızda')); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hakkimizda_aciklama" class="form-label">Kısa Açıklama</label>
                                    <textarea class="form-control" id="hakkimizda_aciklama" name="hakkimizda_aciklama" rows="3"><?php echo htmlspecialchars(getAyar('hakkimizda_aciklama', 'İstanbul\'un kalbinde yer alan otelimiz, modern konfor ve geleneksel Türk misafirperverliğini bir araya getiriyor.')); ?></textarea>
                                    <div class="form-text">Bu metin hakkımızda sayfasının üst kısmında görünecektir.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hakkimizda_detay" class="form-label">Detaylı Açıklama</label>
                                    <textarea class="form-control" id="hakkimizda_detay" name="hakkimizda_detay" rows="6"><?php echo htmlspecialchars(getAyar('hakkimizda_detay', 'Taksim Meydanı\'na sadece birkaç dakika yürüme mesafesinde bulunan otelimiz, şehrin en önemli turistik ve ticari merkezlerine kolay erişim imkanı sunmaktadır.')); ?></textarea>
                                    <div class="form-text">Bu metin hakkımızda sayfasının ana içeriğinde görünecektir.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hakkimizda_resim" class="form-label">Ana Resim URL'si</label>
                                    <input type="url" class="form-control" id="hakkimizda_resim" name="hakkimizda_resim" 
                                           value="<?php echo htmlspecialchars(getAyar('hakkimizda_resim', 'https://images.unsplash.com/photo-1566073771259-6a8506099945')); ?>"
                                           placeholder="https://example.com/resim.jpg">
                                    <div class="form-text">Hakkımızda sayfasında görünecek ana resmin URL'sini girin.</div>
                                </div>
                                
                                <button type="submit" name="hakkimizda_ayarlari" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Kaydet
                                </button>
                            </form>
                        </div>

                        <!-- Galeri Yönetimi -->
                        <div class="tab-pane fade" id="galeri" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Yeni Resim Ekle</h6>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="" enctype="multipart/form-data">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="galeri_resim" class="form-label">Resim Dosyası</label>
                                                    <input type="file" class="form-control" id="galeri_resim" name="galeri_resim" 
                                                           accept="image/*" required>
                                                    <div class="form-text">JPG, PNG, GIF veya WebP formatında resim yükleyebilirsiniz.</div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="resim_baslik" class="form-label">Resim Başlığı</label>
                                                    <input type="text" class="form-control" id="resim_baslik" name="resim_baslik" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="resim_aciklama" class="form-label">Resim Açıklaması</label>
                                                    <textarea class="form-control" id="resim_aciklama" name="resim_aciklama" rows="2"></textarea>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="sira_no" class="form-label">Sıra Numarası</label>
                                                    <input type="number" class="form-control" id="sira_no" name="sira_no" value="0" min="0">
                                                    <div class="form-text">Galeride görünme sırası (0 = en son)</div>
                                                </div>
                                                
                                                <button type="submit" name="galeri_ayarlari" class="btn btn-success">
                                                    <i class="fas fa-upload me-2"></i>Resim Yükle
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-images me-2"></i>Mevcut Resimler</h6>
                                        </div>
                                        <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                            <?php
                                            $sql = "SELECT * FROM galeri WHERE durum = 'aktif' ORDER BY sira_no ASC, id DESC";
                                            $galeri_resimleri = fetchAll($sql);
                                            
                                            if (empty($galeri_resimleri)): ?>
                                                <p class="text-muted text-center">Henüz galeri resmi eklenmemiş.</p>
                                            <?php else: ?>
                                                <?php foreach ($galeri_resimleri as $resim): ?>
                                                <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                                    <img src="../<?php echo htmlspecialchars($resim['resim_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($resim['baslik']); ?>" 
                                                         class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;"
                                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTAiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZTwvdGV4dD48L3N2Zz4='; this.alt='Resim yüklenemedi';">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($resim['baslik']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($resim['aciklama']); ?></small>
                                                        <br><small class="text-info">Sıra: <?php echo $resim['sira_no']; ?></small>
                                                    </div>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="resim_id" value="<?php echo $resim['id']; ?>">
                                                        <button type="submit" name="galeri_sil" class="btn btn-sm btn-outline-danger" 
                                                                onclick="return confirm('Bu resmi silmek istediğinizden emin misiniz?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SEO Ayarları -->
                        <div class="tab-pane fade" id="seo" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="seo_ayarlari" value="1">
                                
                                <div class="row">
                                    <div class="col-12">
                                        <h5 class="mb-3"><i class="fas fa-search me-2"></i>Temel SEO Ayarları</h5>
                                        
                                        <div class="mb-3">
                                            <label for="meta_title" class="form-label">Meta Title (Sayfa Başlığı)</label>
                                            <input type="text" class="form-control" id="meta_title" name="meta_title" 
                                                   value="<?php echo htmlspecialchars(getAyar('meta_title')); ?>" 
                                                   placeholder="Otel Adı - Konum | Rezervasyon" maxlength="60">
                                            <div class="form-text">Önerilen uzunluk: 50-60 karakter</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="meta_description" class="form-label">Meta Description (Sayfa Açıklaması)</label>
                                            <textarea class="form-control" id="meta_description" name="meta_description" rows="3" 
                                                      placeholder="Otel hakkında kısa ve çekici açıklama..." maxlength="160"><?php echo htmlspecialchars(getAyar('meta_description')); ?></textarea>
                                            <div class="form-text">Önerilen uzunluk: 150-160 karakter</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="meta_keywords" class="form-label">Meta Keywords (Anahtar Kelimeler)</label>
                                            <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" 
                                                   value="<?php echo htmlspecialchars(getAyar('meta_keywords')); ?>" 
                                                   placeholder="otel, konaklama, rezervasyon, tatil">
                                            <div class="form-text">Virgülle ayırarak yazın (örn: otel, konaklama, rezervasyon)</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="row">
                                    <div class="col-12">
                                        <h5 class="mb-3"><i class="fab fa-facebook me-2"></i>Open Graph (Facebook) Ayarları</h5>
                                        
                                        <div class="mb-3">
                                            <label for="og_title" class="form-label">OG Title</label>
                                            <input type="text" class="form-control" id="og_title" name="og_title" 
                                                   value="<?php echo htmlspecialchars(getAyar('og_title')); ?>" 
                                                   placeholder="Facebook'ta görünecek başlık">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="og_description" class="form-label">OG Description</label>
                                            <textarea class="form-control" id="og_description" name="og_description" rows="2" 
                                                      placeholder="Facebook'ta görünecek açıklama"><?php echo htmlspecialchars(getAyar('og_description')); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="og_image" class="form-label">OG Image URL</label>
                                            <input type="url" class="form-control" id="og_image" name="og_image" 
                                                   value="<?php echo htmlspecialchars(getAyar('og_image')); ?>" 
                                                   placeholder="https://example.com/image.jpg">
                                            <div class="form-text">Önerilen boyut: 1200x630 piksel</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="row">
                                    <div class="col-12">
                                        <h5 class="mb-3"><i class="fab fa-twitter me-2"></i>Twitter Card Ayarları</h5>
                                        
                                        <div class="mb-3">
                                            <label for="twitter_title" class="form-label">Twitter Title</label>
                                            <input type="text" class="form-control" id="twitter_title" name="twitter_title" 
                                                   value="<?php echo htmlspecialchars(getAyar('twitter_title')); ?>" 
                                                   placeholder="Twitter'da görünecek başlık">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="twitter_description" class="form-label">Twitter Description</label>
                                            <textarea class="form-control" id="twitter_description" name="twitter_description" rows="2" 
                                                      placeholder="Twitter'da görünecek açıklama"><?php echo htmlspecialchars(getAyar('twitter_description')); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="twitter_image" class="form-label">Twitter Image URL</label>
                                            <input type="url" class="form-control" id="twitter_image" name="twitter_image" 
                                                   value="<?php echo htmlspecialchars(getAyar('twitter_image')); ?>" 
                                                   placeholder="https://example.com/image.jpg">
                                            <div class="form-text">Önerilen boyut: 1200x600 piksel</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="row">
                                    <div class="col-12">
                                        <h5 class="mb-3"><i class="fab fa-google me-2"></i>Google Analytics & Search Console</h5>
                                        
                                        <div class="mb-3">
                                            <label for="google_analytics" class="form-label">Google Analytics Tracking ID</label>
                                            <input type="text" class="form-control" id="google_analytics" name="google_analytics" 
                                                   value="<?php echo htmlspecialchars(getAyar('google_analytics')); ?>" 
                                                   placeholder="G-XXXXXXXXXX veya UA-XXXXXXXX-X">
                                            <div class="form-text">Google Analytics 4 (GA4) veya Universal Analytics ID'si</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="google_search_console" class="form-label">Google Search Console Verification</label>
                                            <input type="text" class="form-control" id="google_search_console" name="google_search_console" 
                                                   value="<?php echo htmlspecialchars(getAyar('google_search_console')); ?>" 
                                                   placeholder="google-site-verification meta tag content">
                                            <div class="form-text">Sadece content kısmını girin (google-site-verification=... kısmı)</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>SEO Ayarlarını Kaydet
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Google Harita Ayarları -->
                        <div class="tab-pane fade" id="harita" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="harita_ayarlari" value="1">
                                
                                <div class="row">
                                    <div class="col-12">
                                        <h5 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Google Harita Ayarları</h5>
                                        
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="harita_aktif" name="harita_aktif" 
                                                       <?php echo getAyar('harita_aktif') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="harita_aktif">
                                                    Google Harita'yı Aktif Et
                                                </label>
                                            </div>
                                            <div class="form-text">Harita özelliğini web sitesinde göstermek için aktif edin.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="google_maps_api_key" class="form-label">Google Maps API Key</label>
                                            <input type="text" class="form-control" id="google_maps_api_key" name="google_maps_api_key" 
                                                   value="<?php echo htmlspecialchars(getAyar('google_maps_api_key')); ?>" 
                                                   placeholder="AIzaSyBxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                            <div class="form-text">
                                                <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">
                                                    <i class="fas fa-external-link-alt me-1"></i>Google Maps API Key nasıl alınır?
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="harita_enlem" class="form-label">Enlem (Latitude)</label>
                                                    <input type="number" step="any" class="form-control" id="harita_enlem" name="harita_enlem" 
                                                           value="<?php echo htmlspecialchars(getAyar('harita_enlem', '41.0082')); ?>" 
                                                           placeholder="41.0082">
                                                    <div class="form-text">Örnek: İstanbul için 41.0082</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="harita_boylam" class="form-label">Boylam (Longitude)</label>
                                                    <input type="number" step="any" class="form-control" id="harita_boylam" name="harita_boylam" 
                                                           value="<?php echo htmlspecialchars(getAyar('harita_boylam', '28.9784')); ?>" 
                                                           placeholder="28.9784">
                                                    <div class="form-text">Örnek: İstanbul için 28.9784</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="harita_zoom" class="form-label">Zoom Seviyesi</label>
                                            <select class="form-select" id="harita_zoom" name="harita_zoom">
                                                <?php for ($i = 10; $i <= 20; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo getAyar('harita_zoom', 15) == $i ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?> <?php echo $i == 15 ? '(Önerilen)' : ''; ?>
                                                </option>
                                                <?php endfor; ?>
                                            </select>
                                            <div class="form-text">10: Uzak görünüm, 20: Yakın görünüm</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="harita_stil" class="form-label">Harita Stili</label>
                                            <select class="form-select" id="harita_stil" name="harita_stil">
                                                <option value="roadmap" <?php echo getAyar('harita_stil', 'roadmap') == 'roadmap' ? 'selected' : ''; ?>>
                                                    Yol Haritası (Varsayılan)
                                                </option>
                                                <option value="satellite" <?php echo getAyar('harita_stil') == 'satellite' ? 'selected' : ''; ?>>
                                                    Uydu Görünümü
                                                </option>
                                                <option value="hybrid" <?php echo getAyar('harita_stil') == 'hybrid' ? 'selected' : ''; ?>>
                                                    Hibrit (Uydu + Yol)
                                                </option>
                                                <option value="terrain" <?php echo getAyar('harita_stil') == 'terrain' ? 'selected' : ''; ?>>
                                                    Arazi Haritası
                                                </option>
                                            </select>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle me-2"></i>Koordinat Nasıl Bulunur?</h6>
                                            <p class="mb-2">1. <a href="https://www.google.com/maps" target="_blank">Google Maps</a>'i açın</p>
                                            <p class="mb-2">2. Otel konumunuzu arayın ve işaretleyin</p>
                                            <p class="mb-2">3. İşaretlenen noktaya sağ tıklayın</p>
                                            <p class="mb-0">4. Çıkan menüden koordinatları kopyalayın (ilk sayı enlem, ikinci sayı boylamdır)</p>
                                        </div>
                                        
                                        <?php if (getAyar('harita_aktif') && getAyar('google_maps_api_key') && getAyar('harita_enlem') && getAyar('harita_boylam')): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Harita Önizlemesi</label>
                                            <div id="map-preview" style="height: 300px; border: 1px solid #ddd; border-radius: 5px;"></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Harita Ayarlarını Kaydet
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    
    <!-- Google Maps API -->
    <?php if (getAyar('harita_aktif') && getAyar('google_maps_api_key')): ?>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo getAyar('google_maps_api_key'); ?>&callback=initMap"></script>
    <script>
        function initMap() {
            const lat = parseFloat('<?php echo getAyar('harita_enlem', '41.0082'); ?>');
            const lng = parseFloat('<?php echo getAyar('harita_boylam', '28.9784'); ?>');
            const zoom = parseInt('<?php echo getAyar('harita_zoom', '15'); ?>');
            const mapType = '<?php echo getAyar('harita_stil', 'roadmap'); ?>';
            
            if (document.getElementById('map-preview')) {
                const map = new google.maps.Map(document.getElementById('map-preview'), {
                    center: { lat: lat, lng: lng },
                    zoom: zoom,
                    mapTypeId: mapType
                });
                
                new google.maps.Marker({
                    position: { lat: lat, lng: lng },
                    map: map,
                    title: '<?php echo htmlspecialchars(getAyar('otel_adi', 'Otel')); ?>'
                });
            }
        }
    </script>
    <?php endif; ?>
    
    <style>
        .tema-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .tema-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .tema-card.border-primary {
            border-width: 2px !important;
        }
        
        .tema-colors {
            margin-bottom: 1rem;
        }
        
        .tema-color {
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .iletisim-btn {
            transition: all 0.3s ease;
        }
        
        .iletisim-btn:hover {
            transform: scale(1.1);
        }
        
        /* Tab content visibility fix */
        .tab-pane {
            display: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
        }
        
        .tab-pane.active {
            display: block;
            opacity: 1;
            visibility: visible;
        }
        
        .tab-pane.show {
            display: block;
            opacity: 1;
            visibility: visible;
        }
        
        .tab-pane.active.show {
            display: block;
            opacity: 1;
            visibility: visible;
        }
    </style>
    
    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Tab functionality initialized');
            
            // Get all tab buttons and panes
            const tabButtons = document.querySelectorAll('#ayarTabs button[data-bs-toggle="tab"]');
            const tabPanes = document.querySelectorAll('#ayarTabContent .tab-pane');
            
            console.log('Found tab buttons:', tabButtons.length);
            console.log('Found tab panes:', tabPanes.length);
            
            // Add click event listeners to all tab buttons
            tabButtons.forEach((button, index) => {
                console.log(`Button ${index}:`, button.id, button.getAttribute('data-bs-target'));
                
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    const targetId = this.getAttribute('data-bs-target');
                    const targetPane = document.querySelector(targetId);
                    
                    console.log('Tab clicked:', this.id, 'Target:', targetId, 'Pane found:', !!targetPane);
                    
                    // Remove active class from all buttons
                    tabButtons.forEach(btn => {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-selected', 'false');
                    });
                    
                    // Remove active class from all panes
                    tabPanes.forEach(pane => {
                        pane.classList.remove('active', 'show');
                        pane.style.setProperty('display', 'none', 'important');
                        pane.style.setProperty('opacity', '0', 'important');
                        pane.style.setProperty('visibility', 'hidden', 'important');
                    });
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');
                    
                    // Add active class to target pane
                    if (targetPane) {
                        targetPane.classList.add('active', 'show');
                        targetPane.style.setProperty('display', 'block', 'important');
                        targetPane.style.setProperty('opacity', '1', 'important');
                        targetPane.style.setProperty('visibility', 'visible', 'important');
                        console.log('Tab activated:', targetId);
                    } else {
                        console.error('Target pane not found:', targetId);
                    }
                });
            });
            
            // Initialize first tab as active
            if (tabButtons.length > 0) {
                const firstButton = tabButtons[0];
                const firstTarget = firstButton.getAttribute('data-bs-target');
                const firstPane = document.querySelector(firstTarget);
                
                console.log('Initializing first tab:', firstButton.id, firstTarget);
                
                if (firstPane) {
                    firstPane.classList.add('active', 'show');
                    firstPane.style.setProperty('display', 'block', 'important');
                    firstPane.style.setProperty('opacity', '1', 'important');
                    firstPane.style.setProperty('visibility', 'visible', 'important');
                    firstButton.classList.add('active');
                    firstButton.setAttribute('aria-selected', 'true');
                    console.log('First tab activated');
                }
            }
            
            // Debug: Log all tab elements
            console.log('All tab buttons:', tabButtons);
            console.log('All tab panes:', tabPanes);
        });
    </script>
</body>
</html>
