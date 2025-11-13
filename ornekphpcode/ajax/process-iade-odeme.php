<?php
// Hata gösterimini kapat (AJAX için)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Output buffering başlat
ob_start();

try {
    require_once '../csrf_protection.php';
    require_once '../../includes/xss_protection.php';
    require_once '../../includes/session_security.php';
    require_once '../../config/database.php';
    require_once '../../includes/functions.php';

    // JSON response header
    header('Content-Type: application/json');
    
    // Output buffer'ı temizle
    ob_clean();
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dosya yükleme hatası: ' . $e->getMessage()]);
    exit;
}

// Giriş kontrolü
if (!checkAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('rezervasyon_iade')) {
    echo json_encode(['success' => false, 'message' => 'İade işlemi için yetkiniz bulunmamaktadır']);
    exit;
}

// CSRF kontrolü
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF token hatası']);
    exit;
}

// POST verilerini al
$action = sanitizeString($_POST['action'] ?? '');
$musteri_id = intval($_POST['musteri_id'] ?? 0);

if ($action === 'iade_odeme_ekle') {
    $iade_id = intval($_POST['iade_id'] ?? 0);
    $odeme_tutari = floatval($_POST['odeme_tutari'] ?? 0);
    $odeme_yontemi = sanitizeString($_POST['odeme_yontemi'] ?? '');
    $odeme_tarihi = sanitizeString($_POST['odeme_tarihi'] ?? '');
    $aciklama = sanitizeString($_POST['aciklama'] ?? '');
    
    // Validasyon
    if ($iade_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Geçerli bir iade seçin']);
        exit;
    }
    
    if ($odeme_tutari <= 0) {
        echo json_encode(['success' => false, 'message' => 'Geçerli bir ödeme tutarı girin']);
        exit;
    }
    
    if (empty($odeme_yontemi)) {
        echo json_encode(['success' => false, 'message' => 'Ödeme yöntemi seçin']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // İade bilgilerini kontrol et
        $iade = fetchOne("
            SELECT ri.*, r.musteri_id, r.rezervasyon_kodu
            FROM rezervasyon_iadeleri ri
            LEFT JOIN rezervasyonlar r ON ri.rezervasyon_id = r.id
            WHERE ri.id = ? AND r.musteri_id = ?
        ", [$iade_id, $musteri_id]);
        
        if (!$iade) {
            throw new Exception('İade kaydı bulunamadı');
        }
        
        // Bu iade için toplam ödenen tutarı hesapla
        $toplam_odenen = fetchOne("
            SELECT COALESCE(SUM(odeme_tutari), 0) as toplam
            FROM iade_odemeleri
            WHERE iade_id = ? AND durum = 'tamamlandi'
        ", [$iade_id])['toplam'] ?? 0;
        
        // Kalan iade tutarını hesapla
        $kalan_iade = $iade['iade_tutari'] - $toplam_odenen;
        
        if ($odeme_tutari > $kalan_iade) {
            throw new Exception("Ödeme tutarı kalan iade tutarını geçemez. Kalan: " . number_format($kalan_iade, 2) . "₺");
        }
        
        // Ödeme tarihini ayarla
        if (empty($odeme_tarihi)) {
            $odeme_tarihi = date('Y-m-d H:i:s');
        } else {
            $odeme_tarihi = date('Y-m-d H:i:s', strtotime($odeme_tarihi));
        }
        
        // İade ödemesini kaydet
        $sql = "INSERT INTO iade_odemeleri (
            iade_id, odeme_tutari, odeme_yontemi, odeme_tarihi, 
            aciklama, durum, kullanici_id
        ) VALUES (?, ?, ?, ?, ?, 'tamamlandi', ?)";
        
        executeQuery($sql, [
            $iade_id,
            $odeme_tutari,
            $odeme_yontemi,
            $odeme_tarihi,
            $aciklama,
            $_SESSION['user_id']
        ]);
        
        // Yeni toplam ödenen tutarı hesapla
        $yeni_toplam_odenen = $toplam_odenen + $odeme_tutari;
        
        // Eğer iade tamamen ödendiyse iade durumunu güncelle
        if ($yeni_toplam_odenen >= $iade['iade_tutari']) {
            $update_sql = "UPDATE rezervasyon_iadeleri SET durum = 'odendi' WHERE id = ?";
            executeQuery($update_sql, [$iade_id]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'İade ödemesi başarıyla kaydedildi',
            'kalan_iade' => $iade['iade_tutari'] - $yeni_toplam_odenen
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
}

// Output buffer'ı kapat
ob_end_flush();
?>
