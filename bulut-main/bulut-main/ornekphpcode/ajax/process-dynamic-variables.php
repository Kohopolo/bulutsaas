<?php
/**
 * Dinamik değişkenleri işle
 */

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
    exit;
}

$html = $_POST['html'] ?? '';
$variable = $_POST['variable'] ?? '';

if (empty($html) || empty($variable)) {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler!']);
    exit;
}

try {
    $processedHtml = processDynamicVariables($html, $pdo);
    
    echo json_encode([
        'success' => true,
        'html' => $processedHtml,
        'message' => 'Değişkenler işlendi!'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}

/**
 * Dinamik değişkenleri işle
 */
function processDynamicVariables($html, $pdo) {
    // Değişkenleri bul ve işle
    $html = preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($pdo) {
        $variable = trim($matches[1]);
        return getVariableContent($variable, $pdo);
    }, $html);
    
    return $html;
}

/**
 * Değişken içeriğini getir
 */
function getVariableContent($variable, $pdo) {
    // Önce veritabanından dinamik değişkeni kontrol et
    try {
        $stmt = $pdo->prepare("SELECT * FROM dynamic_variables WHERE variable_name = ? AND is_active = 1");
        $stmt->execute([$variable]);
        $dynamicVar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dynamicVar) {
            return processDynamicVariable($dynamicVar, $pdo);
        }
    } catch (Exception $e) {
        // Hata durumunda eski sisteme geç
    }
    
    // Eski sistem (hardcoded değişkenler)
    switch ($variable) {
        case 'oda_tipleri_listesi':
            return getOdaTipleriListesi($pdo);
            
        case 'rezervasyon_formu':
            return getRezervasyonFormu();
            
        case 'galeri_resimleri':
            return getGaleriResimleri($pdo);
            
        case 'otel_bilgileri':
            return getOtelBilgileri($pdo);
            
        case 'iletisim_bilgileri':
            return getIletisimBilgileri($pdo);
            
        case 'hizmetler_listesi':
            return getHizmetlerListesi($pdo);
            
        default:
            return "<!-- Değişken bulunamadı: {$variable} -->";
    }
}

/**
 * Dinamik değişkeni işle
 */
function processDynamicVariable($variable, $pdo) {
    $type = $variable['variable_type'];
    $content = $variable['variable_content'];
    $settings = json_decode($variable['variable_settings'] ?? '{}', true);
    
    switch ($type) {
        case 'text':
            return htmlspecialchars($content);
            
        case 'html':
            return $content;
            
        case 'list':
            return processListVariable($content, $pdo, $settings);
            
        case 'form':
            return processFormVariable($content, $settings);
            
        case 'gallery':
            return processGalleryVariable($content, $pdo, $settings);
            
        case 'custom':
            return processCustomVariable($content, $pdo, $settings);
            
        default:
            return "<!-- Bilinmeyen tip: {$type} -->";
    }
}

/**
 * Liste değişkenini işle
 */
function processListVariable($sql, $pdo, $settings) {
    try {
        $stmt = $pdo->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            return '<div class="alert alert-info">Veri bulunamadı.</div>';
        }
        
        $columns = $settings['columns'] ?? 3;
        $template = $settings['template'] ?? 'card';
        
        $html = '<div class="row">';
        foreach ($items as $item) {
            $html .= '<div class="col-md-' . (12 / $columns) . ' mb-4">';
            
            if ($template === 'card') {
                $html .= '<div class="card h-100">';
                if (isset($item['resim']) && $item['resim']) {
                    $html .= '<img src="' . htmlspecialchars($item['resim']) . '" class="card-img-top" alt="' . htmlspecialchars($item['baslik'] ?? '') . '">';
                }
                $html .= '<div class="card-body">';
                $html .= '<h5 class="card-title">' . htmlspecialchars($item['baslik'] ?? $item['tip_adi'] ?? '') . '</h5>';
                $html .= '<p class="card-text">' . htmlspecialchars($item['aciklama'] ?? '') . '</p>';
                
                if (isset($item['base_price']) && $settings['show_price'] ?? true) {
                    $html .= '<div class="d-flex justify-content-between align-items-center">';
                    $html .= '<span class="text-primary fw-bold">₺' . number_format($item['base_price']) . '/gece</span>';
                    if (isset($item['kapasite'])) {
                        $html .= '<small class="text-muted">' . $item['kapasite'] . ' kişi</small>';
                    }
                    $html .= '</div>';
                }
                
                $html .= '</div></div>';
            } else {
                // Basit liste
                $html .= '<div class="list-group-item">';
                $html .= '<h6>' . htmlspecialchars($item['baslik'] ?? $item['tip_adi'] ?? '') . '</h6>';
                $html .= '<p class="mb-0">' . htmlspecialchars($item['aciklama'] ?? '') . '</p>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        $html .= '</div>';
        
        return $html;
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Veri yüklenirken hata oluştu: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Form değişkenini işle
 */
function processFormVariable($content, $settings) {
    $fields = $settings['fields'] ?? [];
    $action = $settings['action'] ?? '#';
    $method = $settings['method'] ?? 'POST';
    
    $html = '<div class="card"><div class="card-body">';
    $html .= '<form action="' . htmlspecialchars($action) . '" method="' . htmlspecialchars($method) . '">';
    
    foreach ($fields as $field) {
        $html .= '<div class="mb-3">';
        $html .= '<label class="form-label">' . ucfirst(str_replace('_', ' ', $field)) . '</label>';
        
        if (strpos($field, 'tarih') !== false) {
            $html .= '<input type="date" class="form-control" name="' . $field . '" required>';
        } elseif (strpos($field, 'sayi') !== false) {
            $html .= '<select class="form-select" name="' . $field . '" required>';
            for ($i = 1; $i <= 10; $i++) {
                $html .= '<option value="' . $i . '">' . $i . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input type="text" class="form-control" name="' . $field . '" required>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '<button type="submit" class="btn btn-primary w-100">Gönder</button>';
    $html .= '</form></div></div>';
    
    return $html;
}

/**
 * Galeri değişkenini işle
 */
function processGalleryVariable($sql, $pdo, $settings) {
    try {
        $stmt = $pdo->query($sql);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($images)) {
            return '<div class="alert alert-info">Galeri resmi bulunamadı.</div>';
        }
        
        $columns = $settings['columns'] ?? 3;
        $lightbox = $settings['lightbox'] ?? true;
        $showTitle = $settings['show_title'] ?? true;
        
        $html = '<div class="row">';
        foreach ($images as $image) {
            $html .= '<div class="col-md-' . (12 / $columns) . ' mb-3">';
            $html .= '<div class="card">';
            $html .= '<img src="' . htmlspecialchars($image['resim_url'] ?? $image['resim'] ?? '') . '" class="card-img-top" alt="' . htmlspecialchars($image['baslik'] ?? '') . '" style="height: 200px; object-fit: cover;">';
            
            if ($showTitle && isset($image['baslik'])) {
                $html .= '<div class="card-body p-2">';
                $html .= '<h6 class="card-title mb-1">' . htmlspecialchars($image['baslik']) . '</h6>';
                $html .= '</div>';
            }
            
            $html .= '</div></div>';
        }
        $html .= '</div>';
        
        return $html;
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Galeri yüklenirken hata oluştu: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Özel değişkeni işle
 */
function processCustomVariable($content, $pdo, $settings) {
    // Özel değişkenler için PHP kodu çalıştırma (güvenlik için sınırlı)
    try {
        // Sadece belirli fonksiyonlara izin ver
        $allowedFunctions = ['htmlspecialchars', 'number_format', 'date', 'strtotime'];
        
        // Basit değişken işleme
        if (strpos($content, '<?php') === false) {
            return $content;
        }
        
        return '<div class="alert alert-warning">Özel değişken işleme güvenlik nedeniyle sınırlıdır.</div>';
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Özel değişken işlenirken hata oluştu: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Oda tipleri listesi
 */
function getOdaTipleriListesi($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM oda_tipleri WHERE durum = 'aktif' ORDER BY sira_no ASC");
        $odaTipleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($odaTipleri)) {
            return '<div class="alert alert-info">Henüz oda tipi tanımlanmamış.</div>';
        }
        
        $html = '<div class="row">';
        foreach ($odaTipleri as $tip) {
            $html .= '
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="' . ($tip['resim_url'] ?: 'https://via.placeholder.com/300x200') . '" class="card-img-top" alt="' . htmlspecialchars($tip['oda_tipi_adi']) . '">
                    <div class="card-body">
                        <h5 class="card-title">' . htmlspecialchars($tip['oda_tipi_adi']) . '</h5>
                        <p class="card-text">' . htmlspecialchars($tip['aciklama'] ?? $tip['kisa_aciklama'] ?? '') . '</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-primary fw-bold">₺' . number_format($tip['base_price']) . '/gece</span>
                            <small class="text-muted">' . $tip['kapasite'] . ' kişi</small>
                        </div>
                    </div>
                </div>
            </div>';
        }
        $html .= '</div>';
        
        return $html;
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Oda tipleri yüklenirken hata oluştu.</div>';
    }
}

/**
 * Rezervasyon formu
 */
function getRezervasyonFormu() {
    return '
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Rezervasyon Formu</h5>
        </div>
        <div class="card-body">
            <form action="rezervasyon.php" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Giriş Tarihi</label>
                        <input type="date" class="form-control" name="giris_tarihi" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Çıkış Tarihi</label>
                        <input type="date" class="form-control" name="cikis_tarihi" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Yetişkin Sayısı</label>
                        <select class="form-select" name="yetiskin_sayisi" required>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Çocuk Sayısı</label>
                        <select class="form-select" name="cocuk_sayisi">
                            <option value="0">0</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Uygun Odaları Bul
                </button>
            </form>
        </div>
    </div>';
}

/**
 * Galeri resimleri
 */
function getGaleriResimleri($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM galeri WHERE durum = 'aktif' ORDER BY sira_no ASC LIMIT 12");
        $resimler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($resimler)) {
            return '<div class="alert alert-info">Henüz galeri resmi eklenmemiş.</div>';
        }
        
        $html = '<div class="row">';
        foreach ($resimler as $resim) {
            $html .= '
            <div class="col-md-4 mb-3">
                <div class="card">
                    <img src="' . htmlspecialchars($resim['resim_url']) . '" class="card-img-top" alt="' . htmlspecialchars($resim['baslik']) . '" style="height: 200px; object-fit: cover;">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">' . htmlspecialchars($resim['baslik']) . '</h6>
                        <p class="card-text small text-muted">' . htmlspecialchars($resim['aciklama'] ?? '') . '</p>
                    </div>
                </div>
            </div>';
        }
        $html .= '</div>';
        
        return $html;
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Galeri resimleri yüklenirken hata oluştu.</div>';
    }
}

/**
 * Otel bilgileri
 */
function getOtelBilgileri($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM otel_ayarlari LIMIT 1");
        $otel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$otel) {
            return '<div class="alert alert-info">Otel bilgileri bulunamadı.</div>';
        }
        
        return '
        <div class="row">
            <div class="col-md-6">
                <h5><i class="fas fa-hotel text-primary"></i> ' . htmlspecialchars($otel['otel_adi'] ?? 'Otel Adı') . '</h5>
                <p class="text-muted">' . htmlspecialchars($otel['aciklama'] ?? 'Otel açıklaması') . '</p>
            </div>
            <div class="col-md-6">
                <ul class="list-unstyled">
                    <li><i class="fas fa-star text-warning"></i> ' . ($otel['yildiz_sayisi'] ?? '5') . ' Yıldızlı</li>
                    <li><i class="fas fa-bed text-info"></i> ' . ($otel['oda_sayisi'] ?? '50') . ' Oda</li>
                    <li><i class="fas fa-wifi text-success"></i> Ücretsiz WiFi</li>
                    <li><i class="fas fa-parking text-primary"></i> Ücretsiz Otopark</li>
                </ul>
            </div>
        </div>';
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Otel bilgileri yüklenirken hata oluştu.</div>';
    }
}

/**
 * İletişim bilgileri
 */
function getIletisimBilgileri($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM otel_ayarlari LIMIT 1");
        $otel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$otel) {
            return '<div class="alert alert-info">İletişim bilgileri bulunamadı.</div>';
        }
        
        return '
        <div class="row">
            <div class="col-md-4 text-center mb-3">
                <i class="fas fa-map-marker-alt fa-2x text-primary mb-2"></i>
                <h6>Adres</h6>
                <p class="text-muted">' . htmlspecialchars($otel['adres'] ?? 'Adres bilgisi') . '</p>
            </div>
            <div class="col-md-4 text-center mb-3">
                <i class="fas fa-phone fa-2x text-success mb-2"></i>
                <h6>Telefon</h6>
                <p class="text-muted">' . htmlspecialchars($otel['telefon'] ?? 'Telefon numarası') . '</p>
            </div>
            <div class="col-md-4 text-center mb-3">
                <i class="fas fa-envelope fa-2x text-info mb-2"></i>
                <h6>E-posta</h6>
                <p class="text-muted">' . htmlspecialchars($otel['email'] ?? 'E-posta adresi') . '</p>
            </div>
        </div>';
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">İletişim bilgileri yüklenirken hata oluştu.</div>';
    }
}

/**
 * Hizmetler listesi
 */
function getHizmetlerListesi($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM hizmetler WHERE durum = 'aktif' ORDER BY sira_no ASC");
        $hizmetler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($hizmetler)) {
            return '<div class="alert alert-info">Henüz hizmet tanımlanmamış.</div>';
        }
        
        $html = '<div class="row">';
        foreach ($hizmetler as $hizmet) {
            $html .= '
            <div class="col-md-6 mb-3">
                <div class="d-flex align-items-center">
                    <i class="' . htmlspecialchars($hizmet['ikon'] ?? 'fas fa-check') . ' fa-2x text-primary me-3"></i>
                    <div>
                        <h6 class="mb-1">' . htmlspecialchars($hizmet['hizmet_adi']) . '</h6>
                        <p class="text-muted mb-0">' . htmlspecialchars($hizmet['aciklama']) . '</p>
                    </div>
                </div>
            </div>';
        }
        $html .= '</div>';
        
        return $html;
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Hizmetler yüklenirken hata oluştu.</div>';
    }
}
?>
