
<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price-functions.php';
require_once '../includes/sales_functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('rezervasyon_goruntule', 'Rezervasyonları görüntüleme yetkiniz bulunmamaktadır.');

$success_message = '';
$error_message = '';

// Çoklu rezervasyon başarı mesajı
if (isset($_GET['multi_success']) && isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    
    // Rezervasyon kodlarını göster
    if (isset($_SESSION['reservation_codes']) && is_array($_SESSION['reservation_codes'])) {
        $success_message .= '<br><strong>Oluşturulan Rezervasyon Kodları:</strong><br>';
        foreach ($_SESSION['reservation_codes'] as $kod) {
            $success_message .= '<span class="badge bg-success me-1">' . htmlspecialchars($kod) . '</span>';
        }
    }
    
    // Session'ı temizle
    unset($_SESSION['success_message']);
    unset($_SESSION['reservation_codes']);
}

// URL'den gelen success mesajını kontrol et
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Rezervasyon silme
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $id = intval($_GET['sil']);
    
    try {
        $pdo->beginTransaction();
        
        // Rezervasyon bilgilerini al
        $rezervasyon = fetchOne("
            SELECT r.*, k.ad as silen_kullanici_adi, k.soyad as silen_kullanici_soyadi
            FROM rezervasyonlar r
            LEFT JOIN kullanicilar k ON k.id = ?
            WHERE r.id = ?
        ", [$_SESSION['user_id'], $id]);
        
        if (!$rezervasyon) {
            throw new Exception('Rezervasyon bulunamadı.');
        }
        
        // Silinen rezervasyonlar tablosuna kaydet
        $silme_sql = "INSERT INTO silinen_rezervasyonlar (
            orijinal_id, musteri_id, oda_tipi_id, oda_numarasi_id, giris_tarihi, cikis_tarihi,
            yetiskin_sayisi, cocuk_sayisi, cocuk_yaslari, yetiskin_detaylari, cocuk_detaylari,
            toplam_tutar, toplam_fiyat, odenen_tutar, kalan_tutar, durum, odeme_durumu,
            satis_elemani_id, rezervasyon_kodu, musteri_adi, musteri_soyadi, musteri_email,
            musteri_telefon, musteri_kimlik, notlar, ek_hizmetler, ekstra_ucret, indirim,
            son_toplam_fiyat, gercek_giris_tarihi, gercek_cikis_tarihi, erken_checkout,
            olusturma_tarihi, guncelleme_tarihi, silme_nedeni, silen_kullanici_id, silen_kullanici_adi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_silme = $pdo->prepare($silme_sql);
        $stmt_silme->execute([
            $rezervasyon['id'],
            $rezervasyon['musteri_id'],
            $rezervasyon['oda_tipi_id'],
            $rezervasyon['oda_numarasi_id'],
            $rezervasyon['giris_tarihi'],
            $rezervasyon['cikis_tarihi'],
            $rezervasyon['yetiskin_sayisi'],
            $rezervasyon['cocuk_sayisi'],
            $rezervasyon['cocuk_yaslari'] ?: null,
            $rezervasyon['yetiskin_detaylari'] ?: null,
            $rezervasyon['cocuk_detaylari'] ?: null,
            $rezervasyon['toplam_tutar'],
            $rezervasyon['toplam_fiyat'],
            $rezervasyon['odenen_tutar'],
            $rezervasyon['kalan_tutar'],
            $rezervasyon['durum'],
            $rezervasyon['odeme_durumu'],
            $rezervasyon['satis_elemani_id'],
            $rezervasyon['rezervasyon_kodu'],
            $rezervasyon['musteri_adi'],
            $rezervasyon['musteri_soyadi'],
            $rezervasyon['musteri_email'],
            $rezervasyon['musteri_telefon'],
            $rezervasyon['musteri_kimlik'],
            $rezervasyon['notlar'],
            $rezervasyon['ek_hizmetler'] ?: null,
            $rezervasyon['ekstra_ucret'],
            $rezervasyon['indirim'],
            $rezervasyon['son_toplam_fiyat'],
            $rezervasyon['gercek_giris_tarihi'],
            $rezervasyon['gercek_cikis_tarihi'],
            $rezervasyon['erken_checkout'],
            $rezervasyon['olusturma_tarihi'],
            $rezervasyon['guncelleme_tarihi'],
            'Rezervasyon silindi',
            $_SESSION['user_id'],
            $rezervasyon['silen_kullanici_adi'] . ' ' . $rezervasyon['silen_kullanici_soyadi']
        ]);
        
        // Rezervasyonu sil (CASCADE ile geçmiş kayıtları da silinecek)
        $delete_sql = "DELETE FROM rezervasyonlar WHERE id = ?";
        $stmt_delete = $pdo->prepare($delete_sql);
        $stmt_delete->execute([$id]);
        
        // Silme işlemini silinen_rezervasyonlar tablosuna kaydet
        $silme_gecmis_sql = "INSERT INTO silinen_rezervasyonlar (
            orijinal_id, musteri_id, oda_tipi_id, oda_numarasi_id, giris_tarihi, cikis_tarihi,
            yetiskin_sayisi, cocuk_sayisi, cocuk_yaslari, yetiskin_detaylari, cocuk_detaylari,
            toplam_tutar, toplam_fiyat, odenen_tutar, kalan_tutar, durum, odeme_durumu,
            satis_elemani_id, rezervasyon_kodu, musteri_adi, musteri_soyadi, musteri_email,
            musteri_telefon, musteri_kimlik, notlar, ek_hizmetler, ekstra_ucret, indirim,
            son_toplam_fiyat, gercek_giris_tarihi, gercek_cikis_tarihi, erken_checkout,
            olusturma_tarihi, guncelleme_tarihi, silme_nedeni, silen_kullanici_id,
            silen_kullanici_adi, silme_tarihi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt_silme_gecmis = $pdo->prepare($silme_gecmis_sql);
        $stmt_silme_gecmis->execute([
            $rezervasyon['id'], $rezervasyon['musteri_id'], $rezervasyon['oda_tipi_id'], $rezervasyon['oda_numarasi_id'],
            $rezervasyon['giris_tarihi'], $rezervasyon['cikis_tarihi'], $rezervasyon['yetiskin_sayisi'], $rezervasyon['cocuk_sayisi'],
            $rezervasyon['cocuk_yaslari'] ?: null, $rezervasyon['yetiskin_detaylari'] ?: null, $rezervasyon['cocuk_detaylari'] ?: null,
            $rezervasyon['toplam_tutar'], $rezervasyon['toplam_fiyat'], $rezervasyon['odenen_tutar'], $rezervasyon['kalan_tutar'],
            $rezervasyon['durum'], $rezervasyon['odeme_durumu'], $rezervasyon['satis_elemani_id'], $rezervasyon['rezervasyon_kodu'],
            $rezervasyon['musteri_adi'], $rezervasyon['musteri_soyadi'], $rezervasyon['musteri_email'], $rezervasyon['musteri_telefon'],
            $rezervasyon['musteri_kimlik'], $rezervasyon['notlar'], $rezervasyon['ek_hizmetler'] ?: null, $rezervasyon['ekstra_ucret'],
            $rezervasyon['indirim'], $rezervasyon['son_toplam_fiyat'], $rezervasyon['gercek_giris_tarihi'], $rezervasyon['gercek_cikis_tarihi'],
            $rezervasyon['erken_checkout'], $rezervasyon['olusturma_tarihi'], $rezervasyon['guncelleme_tarihi'], 'Rezervasyon silindi',
            $_SESSION['user_id'], $rezervasyon['silen_kullanici_adi'] . ' ' . $rezervasyon['silen_kullanici_soyadi']
        ]);
        
        $pdo->commit();
        $success_message = 'Rezervasyon başarıyla silindi ve arşivlendi.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Rezervasyon silinirken hata oluştu: ' . $e->getMessage();
    }
}

// Oda değiştirme
if (isset($_POST['room_change']) && isset($_POST['rezervasyon_id']) && isset($_POST['new_room_number_id'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $new_room_number_id = intval($_POST['new_room_number_id']);
    
    // Mevcut rezervasyon bilgilerini al
    $rezervasyon = fetchOne("SELECT giris_tarihi, cikis_tarihi, oda_tipi_id, oda_numarasi_id FROM rezervasyonlar WHERE id = ?", [$rezervasyon_id]);
    
    if ($rezervasyon) {
        // Oda tipinin check-in ve check-out saatlerini al
        $oda_tipi_saatleri = fetchOne("SELECT checkin_saati, checkout_saati FROM oda_tipleri WHERE id = ?", [$rezervasyon['oda_tipi_id']]);
        $checkin_saati = $oda_tipi_saatleri['checkin_saati'] ?? '14:00:00';
        $checkout_saati = $oda_tipi_saatleri['checkout_saati'] ?? '12:00:00';
        
        // Yeni odanın müsait olup olmadığını kontrol et - Basit ve doğru saat bazlı kontrol
        $conflict_check = fetchOne("
            SELECT COUNT(*) as count 
            FROM rezervasyonlar r
            INNER JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
            WHERE r.oda_numarasi_id = ? 
            AND r.id != ? 
            AND r.durum IN ('onaylandi', 'check_in')
            AND (
                -- Çakışma kontrolü: Mevcut rezervasyon henüz çıkmamış VE yeni rezervasyon girmiş
                (r.cikis_tarihi > ? AND r.giris_tarihi < ?)
            )
            AND NOT (
                -- Check-out saati geçmişse oda müsait sayılır
                (r.durum = 'check_in' AND DATE(r.cikis_tarihi) = DATE(?) AND TIME(r.cikis_tarihi) <= ?) OR
                -- Check-in saati henüz gelmemişse oda müsait sayılır
                (r.durum = 'onaylandi' AND DATE(r.giris_tarihi) = DATE(?) AND TIME(r.giris_tarihi) > ?)
            )
        ", [
            $new_room_number_id, $rezervasyon_id,
            $rezervasyon['giris_tarihi'], $rezervasyon['cikis_tarihi'],  // Çakışma kontrolü
            $rezervasyon['giris_tarihi'], $checkout_saati,  // Check-out saati kontrolü
            $rezervasyon['giris_tarihi'], $checkin_saati   // Check-in saati kontrolü
        ]);
        
        if ($conflict_check['count'] == 0) {
            // Eski oda bilgisini al
            $old_room_info = fetchOne("SELECT oda_numarasi FROM oda_numaralari WHERE id = ?", [$rezervasyon['oda_numarasi_id']]);
            $new_room_info = fetchOne("SELECT oda_numarasi FROM oda_numaralari WHERE id = ?", [$new_room_number_id]);
            
            $sql = "UPDATE rezervasyonlar SET oda_numarasi_id = ? WHERE id = ?";
            $stmt_oda_degis = $pdo->prepare($sql);
            if ($stmt_oda_degis->execute([$new_room_number_id, $rezervasyon_id])) {
                // Geçmişe kaydet
                $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id) VALUES (?, ?, ?, ?)";
                $stmt_gecmis_oda = $pdo->prepare($gecmis_sql);
                $stmt_gecmis_oda->execute([$rezervasyon_id, 'oda_degistirme', "Oda {$old_room_info['oda_numarasi']}'dan {$new_room_info['oda_numarasi']}'ya değiştirildi", $_SESSION['user_id']]);
                
                $success_message = 'Oda başarıyla değiştirildi.';
            } else {
                $error_message = 'Oda değiştirilirken hata oluştu.';
            }
        } else {
            $error_message = 'Seçilen oda bu tarihler için müsait değil.';
        }
    } else {
        $error_message = 'Rezervasyon bulunamadı.';
    }
}

// Oda atama
if (isset($_POST['room_assign']) && isset($_POST['rezervasyon_id']) && isset($_POST['room_number_id'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $room_number_id = intval($_POST['room_number_id']);
    
    // Odanın müsait olup olmadığını kontrol et
    $rezervasyon = fetchOne("SELECT giris_tarihi, cikis_tarihi, oda_tipi_id FROM rezervasyonlar WHERE id = ?", [$rezervasyon_id]);
    
    if ($rezervasyon) {
        // Oda tipinin check-in ve check-out saatlerini al
        $oda_tipi_saatleri = fetchOne("SELECT checkin_saati, checkout_saati FROM oda_tipleri WHERE id = ?", [$rezervasyon['oda_tipi_id']]);
        $checkin_saati = $oda_tipi_saatleri['checkin_saati'] ?? '14:00:00';
        $checkout_saati = $oda_tipi_saatleri['checkout_saati'] ?? '12:00:00';
        
        $conflict_check = fetchOne("
            SELECT COUNT(*) as count 
            FROM rezervasyonlar r
            INNER JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
            WHERE r.oda_numarasi_id = ? 
            AND r.id != ? 
            AND r.durum IN ('onaylandi', 'check_in')
            AND (
                -- Çakışma kontrolü: Mevcut rezervasyon henüz çıkmamış VE yeni rezervasyon girmiş
                (r.cikis_tarihi > ? AND r.giris_tarihi < ?)
            )
            AND NOT (
                -- Check-out saati geçmişse oda müsait sayılır
                (r.durum = 'check_in' AND DATE(r.cikis_tarihi) = DATE(?) AND TIME(r.cikis_tarihi) <= ?) OR
                -- Check-in saati henüz gelmemişse oda müsait sayılır
                (r.durum = 'onaylandi' AND DATE(r.giris_tarihi) = DATE(?) AND TIME(r.giris_tarihi) > ?)
            )
        ", [
            $room_number_id, $rezervasyon_id,
            $rezervasyon['giris_tarihi'], $rezervasyon['cikis_tarihi'],  // Çakışma kontrolü
            $rezervasyon['giris_tarihi'], $checkout_saati,  // Check-out saati kontrolü
            $rezervasyon['giris_tarihi'], $checkin_saati   // Check-in saati kontrolü
        ]);
        
        if ($conflict_check['count'] == 0) {
            $sql = "UPDATE rezervasyonlar SET oda_numarasi_id = ? WHERE id = ?";
            $stmt_oda_ata = $pdo->prepare($sql);
            if ($stmt_oda_ata->execute([$room_number_id, $rezervasyon_id])) {
                // Geçmişe kaydet
                $oda_info = fetchOne("SELECT oda_numarasi FROM oda_numaralari WHERE id = ?", [$room_number_id]);
                $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id) VALUES (?, ?, ?, ?)";
                $stmt_gecmis_ata = $pdo->prepare($gecmis_sql);
                $stmt_gecmis_ata->execute([$rezervasyon_id, 'oda_atama', "Oda {$oda_info['oda_numarasi']} atandı", $_SESSION['user_id']]);
                
                $success_message = 'Oda başarıyla atandı.';
            } else {
                $error_message = 'Oda atanırken hata oluştu.';
            }
        } else {
            $error_message = 'Seçilen oda bu tarihler için müsait değil.';
        }
    } else {
        $error_message = 'Rezervasyon bulunamadı.';
    }
}

// Satış elemanı atama
if (isset($_POST['sales_assign']) && isset($_POST['rezervasyon_id']) && isset($_POST['sales_user_id'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $sales_user_id = intval($_POST['sales_user_id']);
    
    if (assignSalesToReservation($rezervasyon_id, $sales_user_id)) {
        $success_message = 'Satış elemanı başarıyla atandı.';
    } else {
        $error_message = 'Satış elemanı atanırken hata oluştu.';
    }
}

// Satış elemanı değiştirme (admin ve superadmin için)
if (isset($_POST['sales_change']) && isset($_POST['rezervasyon_id']) && isset($_POST['sales_user_id'])) {
    if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'superadmin') {
        $rezervasyon_id = intval($_POST['rezervasyon_id']);
        $sales_user_id = intval($_POST['sales_user_id']);
        
        $sql = "UPDATE rezervasyonlar SET satis_elemani_id = ? WHERE id = ?";
        $stmt_satis = $pdo->prepare($sql);
        if ($stmt_satis->execute([$sales_user_id, $rezervasyon_id])) {
            // Geçmişe kaydet
            $user_info = fetchOne("SELECT ad, soyad FROM kullanicilar WHERE id = ?", [$sales_user_id]);
            $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem, aciklama, kullanici_id) VALUES (?, ?, ?, ?)";
            $stmt_gecmis_satis = $pdo->prepare($gecmis_sql);
            $stmt_gecmis_satis->execute([$rezervasyon_id, 'satis_degisim', "Satış elemanı {$user_info['ad']} {$user_info['soyad']} olarak değiştirildi", $_SESSION['user_id']]);
            
            $success_message = 'Satış elemanı başarıyla değiştirildi.';
        } else {
            $error_message = 'Satış elemanı değiştirilirken hata oluştu.';
        }
    } else {
        $error_message = 'Bu işlem için yetkiniz yok.';
    }
}

// Durum güncelleme
if (isset($_POST['durum_guncelle']) && isset($_POST['rezervasyon_id'])) {
    $rezervasyon_id = intval($_POST['rezervasyon_id']);
    $yeni_durum = $_POST['yeni_durum'];
    
    $allowed_statuses = ['beklemede', 'onaylandi', 'check_in', 'check_out', 'iptal'];
    if (in_array($yeni_durum, $allowed_statuses)) {
        try {
            $pdo->beginTransaction();
            
            // Rezervasyon bilgilerini al
            $rezervasyon = fetchOne("SELECT oda_numarasi_id, durum FROM rezervasyonlar WHERE id = ?", [$rezervasyon_id]);
            
            // Rezervasyon durumunu güncelle
            $sql = "UPDATE rezervasyonlar SET durum = ?";
            
            // Check-out ise gercek_cikis_tarihi ekle
            if ($yeni_durum == 'check_out') {
                $sql .= ", gercek_cikis_tarihi = NOW()";
            }
            // Check-in ise gercek_giris_tarihi ekle
            elseif ($yeni_durum == 'check_in') {
                $sql .= ", gercek_giris_tarihi = NOW()";
            }
            
            $sql .= " WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$yeni_durum, $rezervasyon_id])) {
                // Oda durumunu güncelle
                if ($rezervasyon['oda_numarasi_id']) {
                    if ($yeni_durum == 'check_out') {
                        // Check-out: Oda durumunu temizlik bekliyor yap
                        $oda_sql = "UPDATE oda_numaralari SET durum = 'temizlik_bekliyor' WHERE id = ?";
                        $stmt_oda = $pdo->prepare($oda_sql);
                        $stmt_oda->execute([$rezervasyon['oda_numarasi_id']]);
                        
                        // Oda geçmişi kaydı
                        $oda_gecmis_sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                                          VALUES (?, 'dolu', 'temizlik_bekliyor', 'Check-out sonrası temizlik bekliyor', ?, NOW())";
                        $stmt_gecmis = $pdo->prepare($oda_gecmis_sql);
                        $stmt_gecmis->execute([$rezervasyon['oda_numarasi_id'], $_SESSION['user_id']]);
                        
                    } elseif ($yeni_durum == 'check_in') {
                        // Check-in: Oda durumunu dolu yap
                        $oda_sql = "UPDATE oda_numaralari SET durum = 'dolu' WHERE id = ?";
                        $stmt_oda2 = $pdo->prepare($oda_sql);
                        $stmt_oda2->execute([$rezervasyon['oda_numarasi_id']]);
                        
                        // Oda geçmişi kaydı
                        $oda_gecmis_sql = "INSERT INTO oda_gecmisi (oda_id, eski_durum, yeni_durum, aciklama, admin_id, islem_tarihi) 
                                          VALUES (?, 'aktif', 'dolu', 'Check-in ile oda dolu olarak işaretlendi', ?, NOW())";
                        $stmt_gecmis2 = $pdo->prepare($oda_gecmis_sql);
                        $stmt_gecmis2->execute([$rezervasyon['oda_numarasi_id'], $_SESSION['user_id']]);
                    }
                }
                
                // Rezervasyon geçmişi kaydı
                $gecmis_sql = "INSERT INTO rezervasyon_gecmisi (rezervasyon_id, islem_tipi, aciklama, kullanici_id, olusturma_tarihi) VALUES (?, ?, ?, ?, NOW())";
                $stmt_gecmis3 = $pdo->prepare($gecmis_sql);
                $stmt_gecmis3->execute([$rezervasyon_id, $yeni_durum, "Durum admin tarafından güncellendi", $_SESSION['user_id']]);
                
                $pdo->commit();
                $success_message = 'Rezervasyon durumu güncellendi.';
            } else {
                $pdo->rollBack();
                $error_message = 'Durum güncellenirken hata oluştu.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'Durum güncellenirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// Filtreleme parametreleri
$durum_filtre = $_GET['durum'] ?? '';
$tarih_filtre = $_GET['tarih'] ?? '';
$oda_tipi_filtre = $_GET['oda_tipi'] ?? '';
$arama = $_GET['arama'] ?? '';

// Sayfalama
$sayfa = intval($_GET['sayfa'] ?? 1);
$limit = 20;
$offset = ($sayfa - 1) * $limit;

// Rezervasyonları getir
$where_conditions = [];
$params = [];

if ($durum_filtre) {
    $where_conditions[] = "r.durum = ?";
    $params[] = $durum_filtre;
}

if ($tarih_filtre) {
    $where_conditions[] = "DATE(r.giris_tarihi) = ?";
    $params[] = $tarih_filtre;
}

if ($oda_tipi_filtre) {
    $where_conditions[] = "r.oda_tipi_id = ?";
    $params[] = $oda_tipi_filtre;
}

if ($arama) {
    $where_conditions[] = "(r.rezervasyon_kodu LIKE ? OR r.musteri_adi LIKE ? OR r.musteri_soyadi LIKE ? OR r.musteri_email LIKE ? OR r.musteri_telefon LIKE ?)";
    $arama_param = '%' . $arama . '%';
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$sql = "SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi, 
               m.ad as musteri_adi, m.soyad as musteri_soyadi, m.email as musteri_email, m.telefon as musteri_telefon,
               COALESCE(s.ad, 'Web Site') as sales_ad, 
               COALESCE(s.soyad, '') as sales_soyad,
               COALESCE(SUM(ro.odeme_tutari), 0) as toplam_odenen
        FROM rezervasyonlar r 
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
        LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id 
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        LEFT JOIN kullanicilar s ON r.satis_elemani_id = s.id AND s.rol IN ('sales', 'admin', 'superadmin', 'ekip')
        LEFT JOIN rezervasyon_odemeleri ro ON r.id = ro.rezervasyon_id AND ro.durum = 'aktif'
        $where_clause 
        GROUP BY r.id
        ORDER BY r.olusturma_tarihi DESC 
        LIMIT $limit OFFSET $offset";

$rezervasyonlar = fetchAll($sql, $params);

// Toplam kayıt sayısı
$count_sql = "SELECT COUNT(*) as toplam FROM rezervasyonlar r 
              LEFT JOIN musteriler m ON r.musteri_id = m.id
              $where_clause";
$toplam_result = fetchOne($count_sql, $params);
$toplam_kayit = $toplam_result['toplam'];
$toplam_sayfa = ceil($toplam_kayit / $limit);

// Oda tiplerini getir (filtre için)
$oda_tipleri = fetchAll("SELECT * FROM oda_tipleri WHERE durum = 'aktif' ORDER BY sira_no ASC");

// Satış elemanlarını getir
$sales_users = getAllSalesUsers();

// Debug: Satış elemanları listesini kontrol et
if (empty($sales_users)) {
    error_log("Sales users listesi boş!");
} else {
    error_log("Sales users sayısı: " . count($sales_users));
    error_log("İlk sales user: " . print_r($sales_users[0], true));
}

// İstatistikler
$beklemede = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'beklemede'");
$onaylandi = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'onaylandi'");
$check_in = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'check_in'");
$bugun_rezervasyonlar = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE DATE(olusturma_tarihi) = CURDATE()");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervasyonlar - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                </button>
                
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">Rezervasyonlar</h1>
                            <p class="text-muted">Tüm rezervasyonları görüntüleyin ve yönetin</p>
                        </div>
                        <div>
                            <a href="rezervasyon-ekle.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Yeni Rezervasyon
                            </a>
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

            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Beklemede
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $beklemede['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Onaylandı
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $onaylandi['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Aktif Konaklamalar
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $check_in['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-bed fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Bugünkü Rezervasyonlar
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $bugun_rezervasyonlar['sayi']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtreler -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-2">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="">Tüm Durumlar</option>
                                <option value="beklemede" <?php echo $durum_filtre == 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                <option value="onaylandi" <?php echo $durum_filtre == 'onaylandi' ? 'selected' : ''; ?>>Onaylandı</option>
                                <option value="check_in" <?php echo $durum_filtre == 'check_in' ? 'selected' : ''; ?>>Check-in</option>
                                <option value="check_out" <?php echo $durum_filtre == 'check_out' ? 'selected' : ''; ?>>Check-out</option>
                                <option value="iptal" <?php echo $durum_filtre == 'iptal' ? 'selected' : ''; ?>>İptal</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="tarih" class="form-label">Giriş Tarihi</label>
                            <input type="date" class="form-control" id="tarih" name="tarih" value="<?php echo htmlspecialchars($tarih_filtre); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="oda_tipi" class="form-label">Oda Tipi</label>
                            <select class="form-select" id="oda_tipi" name="oda_tipi">
                                <option value="">Tüm Oda Tipleri</option>
                                <?php foreach ($oda_tipleri as $oda_tipi): ?>
                                <option value="<?php echo $oda_tipi['id']; ?>" <?php echo $oda_tipi_filtre == $oda_tipi['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($oda_tipi['oda_tipi_adi']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="arama" class="form-label">Arama</label>
                            <input type="text" class="form-control" id="arama" name="arama" 
                                   value="<?php echo htmlspecialchars($arama); ?>" 
                                   placeholder="Rezervasyon kodu, müşteri adı, e-posta veya telefon">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filtrele
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Rezervasyonlar Tablosu -->
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Rezervasyonlar Listesi (<?php echo $toplam_kayit; ?> kayıt)
                    </h6>
                    <?php if ($durum_filtre || $tarih_filtre || $oda_tipi_filtre || $arama): ?>
                    <a href="rezervasyonlar.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Filtreleri Temizle
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Rezervasyon</th>
                                    <th>Müşteri</th>
                                    <th>Oda</th>
                                    <th>Tarihler</th>
                                    <th>Kişi</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>Ödeme Durumu</th>
                                    <th>Satış Elemanı</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rezervasyonlar)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
                                        <?php if ($durum_filtre || $tarih_filtre || $oda_tipi_filtre || $arama): ?>
                                            Filtrelere uygun rezervasyon bulunamadı.
                                        <?php else: ?>
                                            Henüz rezervasyon bulunmuyor.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($rezervasyonlar as $rezervasyon): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu'] ?? ''); ?></strong>
                                            <?php if ($rezervasyon['erken_checkout']): ?>
                                                <span class="badge bg-warning ms-2">
                                                    <i class="fas fa-clock me-1"></i>Erken Check-out
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo formatTurkishDate($rezervasyon['olusturma_tarihi'], 'd.m.Y H:i'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($rezervasyon['musteri_email']); ?>
                                        </small>
                                        <br><small class="text-muted">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($rezervasyon['musteri_telefon']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rezervasyon['oda_tipi_adi']); ?></strong>
                                        </div>
                                        <?php if ($rezervasyon['oda_numarasi']): ?>
                                        <small class="text-success">
                                            <i class="fas fa-door-open me-1"></i>
                                            Oda <?php echo htmlspecialchars($rezervasyon['oda_numarasi']); ?>
                                        </small>
                                        <br>
                                        <button type="button" class="btn btn-sm btn-outline-warning mt-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#roomChangeModal" 
                                                data-reservation-id="<?php echo $rezervasyon['id']; ?>"
                                                data-current-room="<?php echo htmlspecialchars($rezervasyon['oda_numarasi']); ?>"
                                                data-room-type-id="<?php echo $rezervasyon['oda_tipi_id']; ?>"
                                                data-customer-name="<?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?>"
                                                data-checkin-date="<?php echo $rezervasyon['giris_tarihi']; ?>"
                                                data-checkout-date="<?php echo $rezervasyon['cikis_tarihi']; ?>">
                                            <i class="fas fa-exchange-alt me-1"></i>Değiştir
                                        </button>
                                        <?php else: ?>
                                        <small class="text-muted">Oda atanmadı</small>
                                        <br>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#roomAssignModal" 
                                                data-reservation-id="<?php echo $rezervasyon['id']; ?>"
                                                data-room-type-id="<?php echo $rezervasyon['oda_tipi_id']; ?>"
                                                data-customer-name="<?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?>"
                                                data-checkin-date="<?php echo $rezervasyon['giris_tarihi']; ?>"
                                                data-checkout-date="<?php echo $rezervasyon['cikis_tarihi']; ?>">
                                            <i class="fas fa-door-open me-1"></i>Ata
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-sign-in-alt text-success me-1"></i>
                                            <?php echo formatTurkishDate($rezervasyon['giris_tarihi'], 'd.m.Y'); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-sign-out-alt text-warning me-1"></i>
                                            <?php echo formatTurkishDate($rezervasyon['cikis_tarihi'], 'd.m.Y'); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php 
                                            $giris = new DateTime($rezervasyon['giris_tarihi']);
                                            $cikis = new DateTime($rezervasyon['cikis_tarihi']);
                                            echo $giris->diff($cikis)->days . ' gece';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo $rezervasyon['yetiskin_sayisi']; ?> Yetişkin
                                        </div>
                                        <?php if ($rezervasyon['cocuk_sayisi'] > 0): ?>
                                        <div>
                                            <i class="fas fa-child me-1"></i>
                                            <?php echo $rezervasyon['cocuk_sayisi']; ?> Çocuk
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">
                                            <?php echo number_format($rezervasyon['toplam_tutar'] ?? 0, 2) . '₺'; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="rezervasyon_id" value="<?php echo $rezervasyon['id']; ?>">
                                            <select name="yeni_durum" class="form-select form-select-sm" 
                                                    onchange="this.form.submit()">
                                                <option value="beklemede" <?php echo $rezervasyon['durum'] == 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                                <option value="onaylandi" <?php echo $rezervasyon['durum'] == 'onaylandi' ? 'selected' : ''; ?>>Onaylandı</option>
                                                <option value="check_in" <?php echo $rezervasyon['durum'] == 'check_in' ? 'selected' : ''; ?>>Check-in</option>
                                                <option value="check_out" <?php echo $rezervasyon['durum'] == 'check_out' ? 'selected' : ''; ?>>Check-out</option>
                                                <option value="iptal" <?php echo $rezervasyon['durum'] == 'iptal' ? 'selected' : ''; ?>>İptal</option>
                                            </select>
                                            <input type="hidden" name="durum_guncelle" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <?php
                                        // Ödeme durumunu hesapla
                                        $toplam_tutar = $rezervasyon['toplam_tutar'] ?? 0;
                                        $toplam_odenen = $rezervasyon['toplam_odenen'] ?? 0;
                                        
                                        if ($toplam_odenen <= 0) {
                                            $odeme_durumu = 'odenmedi';
                                        } elseif ($toplam_odenen >= $toplam_tutar) {
                                            $odeme_durumu = 'odendi';
                                        } else {
                                            $odeme_durumu = 'kismi_odeme';
                                        }
                                        
                                        $odeme_durumu_class = [
                                            'odenmedi' => 'danger',
                                            'kismi_odeme' => 'warning',
                                            'odendi' => 'success'
                                        ];
                                        $odeme_durumu_text = [
                                            'odenmedi' => 'Ödenmedi',
                                            'kismi_odeme' => 'Kısmi Ödeme',
                                            'odendi' => 'Ödendi'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $odeme_durumu_class[$odeme_durumu]; ?>">
                                            <?php echo $odeme_durumu_text[$odeme_durumu]; ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo number_format($toplam_odenen, 2); ?>₺ / <?php echo number_format($toplam_tutar, 2); ?>₺
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($rezervasyon['sales_ad']): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-<?php echo $rezervasyon['sales_ad'] == 'Web Site' ? 'globe' : 'user-tie'; ?> text-<?php echo $rezervasyon['sales_ad'] == 'Web Site' ? 'info' : 'success'; ?> me-2"></i>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($rezervasyon['sales_ad'] . ($rezervasyon['sales_soyad'] ? ' ' . $rezervasyon['sales_soyad'] : '')); ?></strong>
                                                    <br><small class="text-muted"><?php echo $rezervasyon['sales_ad'] == 'Web Site' ? 'Web Sitesi' : 'Atanmış'; ?></small>
                                                    <?php if (($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'superadmin') && $rezervasyon['sales_ad'] != 'Web Site'): ?>
                                                        <br><button type="button" class="btn btn-xs btn-outline-primary mt-1" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#salesChangeModal" 
                                                                data-reservation-id="<?php echo $rezervasyon['id']; ?>"
                                                                data-customer-name="<?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?>"
                                                                data-current-sales="<?php echo htmlspecialchars($rezervasyon['sales_ad'] . ' ' . $rezervasyon['sales_soyad']); ?>">
                                                            <i class="fas fa-exchange-alt me-1"></i>Ata
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#salesAssignModal" 
                                                    data-reservation-id="<?php echo $rezervasyon['id']; ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']); ?>">
                                                <i class="fas fa-user-plus me-1"></i>Ata
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="rezervasyon-detay.php?id=<?php echo $rezervasyon['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Detay">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="rezervasyon-duzenle.php?id=<?php echo $rezervasyon['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <!-- PDF Butonları -->
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" 
                                                        data-bs-toggle="dropdown" title="PDF İşlemleri">
                                                    <i class="fas fa-file-pdf"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" action="pdf-download.php" class="d-inline" target="_blank">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="voucher" value="1">
                                                            <input type="hidden" name="rezervasyon_id" value="<?php echo $rezervasyon['id']; ?>">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="fas fa-ticket-alt me-2"></i>Voucher İndir
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" action="pdf-download.php" class="d-inline" target="_blank">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="contract" value="1">
                                                            <input type="hidden" name="rezervasyon_id" value="<?php echo $rezervasyon['id']; ?>">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="fas fa-file-contract me-2"></i>Sözleşme İndir
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a href="pdf-archive.php?rezervasyon_id=<?php echo $rezervasyon['id']; ?>" 
                                                           class="dropdown-item" target="_blank">
                                                            <i class="fas fa-archive me-2"></i>PDF Arşivi
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a href="#" class="dropdown-item whatsapp-send" 
                                                           data-rezervasyon-id="<?php echo $rezervasyon['id']; ?>" 
                                                           data-type="voucher" 
                                                           data-phone="<?php echo $rezervasyon['musteri_telefon']; ?>">
                                                            <i class="fab fa-whatsapp me-2 text-success"></i>Voucher WhatsApp Gönder
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="#" class="dropdown-item whatsapp-send" 
                                                           data-rezervasyon-id="<?php echo $rezervasyon['id']; ?>" 
                                                           data-type="contract" 
                                                           data-phone="<?php echo $rezervasyon['musteri_telefon']; ?>">
                                                            <i class="fab fa-whatsapp me-2 text-success"></i>Sözleşme WhatsApp Gönder
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger" 
                                                    onclick="showDeleteConfirm(<?php echo $rezervasyon['id']; ?>, '<?php echo htmlspecialchars($rezervasyon['rezervasyon_kodu']); ?>')"
                                                    title="Sil">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sayfalama -->
                    <?php if ($toplam_sayfa > 1): ?>
                    <nav aria-label="Sayfa navigasyonu">
                        <ul class="pagination justify-content-center">
                            <?php if ($sayfa > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?sayfa=<?php echo ($sayfa - 1); ?>&durum=<?php echo urlencode($durum_filtre); ?>&tarih=<?php echo urlencode($tarih_filtre); ?>&oda_tipi=<?php echo urlencode($oda_tipi_filtre); ?>&arama=<?php echo urlencode($arama); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $sayfa - 2);
                            $end = min($toplam_sayfa, $sayfa + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $sayfa ? 'active' : ''; ?>">
                                <a class="page-link" href="?sayfa=<?php echo $i; ?>&durum=<?php echo urlencode($durum_filtre); ?>&tarih=<?php echo urlencode($tarih_filtre); ?>&oda_tipi=<?php echo urlencode($oda_tipi_filtre); ?>&arama=<?php echo urlencode($arama); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($sayfa < $toplam_sayfa): ?>
                            <li class="page-item">
                                <a class="page-link" href="?sayfa=<?php echo ($sayfa + 1); ?>&durum=<?php echo urlencode($durum_filtre); ?>&tarih=<?php echo urlencode($tarih_filtre); ?>&oda_tipi=<?php echo urlencode($oda_tipi_filtre); ?>&arama=<?php echo urlencode($arama); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Room Assignment Modal -->
    <div class="modal fade" id="roomAssignModal" tabindex="-1" aria-labelledby="roomAssignModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="roomAssignModalLabel">
                        <i class="fas fa-door-open me-2"></i>Ata
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="room_assign" value="1">
                        <input type="hidden" name="rezervasyon_id" id="roomModalReservationId">
                        
                        <div class="mb-3">
                            <label class="form-label">Müşteri:</label>
                            <div class="form-control-plaintext" id="roomModalCustomerName"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Konaklama Tarihleri:</label>
                            <div class="form-control-plaintext" id="roomModalDates"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room_number_id" class="form-label">Oda Seçin:</label>
                            <select name="room_number_id" id="room_number_id" class="form-select" required>
                                <option value="">Oda seçin...</option>
                            </select>
                            <div class="form-text">Sadece uygun odalar gösterilmektedir.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i>Ata
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sales Assignment Modal -->
    <div class="modal fade" id="salesAssignModal" tabindex="-1" aria-labelledby="salesAssignModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="salesAssignModalLabel">
                        <i class="fas fa-user-tie me-2"></i>Ata
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="sales_assign" value="1">
                        <input type="hidden" name="rezervasyon_id" id="modalReservationId">
                        
                        <div class="mb-3">
                            <label class="form-label">Müşteri:</label>
                            <div class="form-control-plaintext" id="modalCustomerName"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sales_user_id" class="form-label">Satış Elemanı Seçin:</label>
                            <select name="sales_user_id" id="sales_user_id" class="form-select" required>
                                <option value="">Satış elemanı seçin...</option>
                                <?php foreach ($sales_users as $sales_user): ?>
                                    <option value="<?php echo $sales_user['id']; ?>">
                                        <?php echo htmlspecialchars($sales_user['ad'] . ' ' . $sales_user['soyad']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i>Ata
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sales Change Modal -->
    <div class="modal fade" id="salesChangeModal" tabindex="-1" aria-labelledby="salesChangeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="salesChangeModalLabel">
                        <i class="fas fa-exchange-alt me-2"></i>Satış Elemanı Değiştir
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="sales_change" value="1">
                        <input type="hidden" name="rezervasyon_id" id="changeModalReservationId">
                        
                        <div class="mb-3">
                            <label class="form-label">Müşteri:</label>
                            <div class="form-control-plaintext" id="changeModalCustomerName"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mevcut Satış Elemanı:</label>
                            <div class="form-control-plaintext text-muted" id="changeModalCurrentSales"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="change_sales_user_id" class="form-label">Yeni Satış Elemanı Seçin:</label>
                            <select name="sales_user_id" id="change_sales_user_id" class="form-select" required>
                                <option value="">Satış elemanı seçin...</option>
                                <?php 
                                // Tüm kullanıcıları getir (admin ve superadmin için)
                                $all_users = fetchAll("SELECT id, ad, soyad, rol FROM kullanicilar WHERE durum = 'aktif' ORDER BY ad, soyad");
                                foreach ($all_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad'] . ' (' . ucfirst($user['rol']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i>Değiştir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Room Change Modal -->
    <div class="modal fade" id="roomChangeModal" tabindex="-1" aria-labelledby="roomChangeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="roomChangeModalLabel">
                        <i class="fas fa-exchange-alt me-2"></i>Değiştir
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="room_change" value="1">
                        <input type="hidden" name="rezervasyon_id" id="roomChangeModalReservationId">
                        
                        <div class="mb-3">
                            <label class="form-label">Müşteri:</label>
                            <div class="form-control-plaintext" id="roomChangeModalCustomerName"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tarih Aralığı:</label>
                            <div class="form-control-plaintext text-muted" id="roomChangeModalDates"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mevcut Oda:</label>
                            <div class="form-control-plaintext text-warning" id="roomChangeModalCurrentRoom"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_room_number_id" class="form-label">Yeni Oda Seçin:</label>
                            <select name="new_room_number_id" id="new_room_number_id" class="form-select" required>
                                <option value="">Oda seçin...</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-exchange-alt me-1"></i>Değiştir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Room assignment modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const roomAssignModal = document.getElementById('roomAssignModal');
            
            roomAssignModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const reservationId = button.getAttribute('data-reservation-id');
                const roomTypeId = button.getAttribute('data-room-type-id');
                const customerName = button.getAttribute('data-customer-name');
                const checkinDate = button.getAttribute('data-checkin-date');
                const checkoutDate = button.getAttribute('data-checkout-date');
                
                document.getElementById('roomModalReservationId').value = reservationId;
                document.getElementById('roomModalCustomerName').textContent = customerName;
                document.getElementById('roomModalDates').textContent = formatDate(checkinDate) + ' - ' + formatDate(checkoutDate);
                
                // Load available rooms for this room type and dates
                loadAvailableRooms(roomTypeId, checkinDate, checkoutDate);
            });
            
            const salesAssignModal = document.getElementById('salesAssignModal');
            
            salesAssignModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const reservationId = button.getAttribute('data-reservation-id');
                const customerName = button.getAttribute('data-customer-name');
                
                document.getElementById('modalReservationId').value = reservationId;
                document.getElementById('modalCustomerName').textContent = customerName;
            });
            
            const salesChangeModal = document.getElementById('salesChangeModal');
            
            salesChangeModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const reservationId = button.getAttribute('data-reservation-id');
                const customerName = button.getAttribute('data-customer-name');
                const currentSales = button.getAttribute('data-current-sales');
                
                document.getElementById('changeModalReservationId').value = reservationId;
                document.getElementById('changeModalCustomerName').textContent = customerName;
                document.getElementById('changeModalCurrentSales').textContent = currentSales;
            });
            
            // Room change modal functionality
            const roomChangeModal = document.getElementById('roomChangeModal');
            
            roomChangeModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const reservationId = button.getAttribute('data-reservation-id');
                const currentRoom = button.getAttribute('data-current-room');
                const roomTypeId = button.getAttribute('data-room-type-id');
                const customerName = button.getAttribute('data-customer-name');
                const checkinDate = button.getAttribute('data-checkin-date');
                const checkoutDate = button.getAttribute('data-checkout-date');
                
                document.getElementById('roomChangeModalReservationId').value = reservationId;
                document.getElementById('roomChangeModalCustomerName').textContent = customerName;
                document.getElementById('roomChangeModalDates').textContent = formatDate(checkinDate) + ' - ' + formatDate(checkoutDate);
                document.getElementById('roomChangeModalCurrentRoom').textContent = 'Oda ' + currentRoom;
                
                // Load available rooms for this room type and dates (excluding current room)
                loadAvailableRoomsForChange(roomTypeId, checkinDate, checkoutDate, reservationId);
            });
        });
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR');
        }
        
        function loadAvailableRooms(roomTypeId, checkinDate, checkoutDate) {
            const roomSelect = document.getElementById('room_number_id');
            roomSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            
            fetch('ajax/get_available_rooms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    room_type_id: roomTypeId,
                    checkin_date: checkinDate,
                    checkout_date: checkoutDate
                })
            })
            .then(response => response.json())
            .then(data => {
                roomSelect.innerHTML = '<option value="">Oda seçin...</option>';
                
                if (data.success && data.rooms.length > 0) {
                    data.rooms.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = `Oda ${room.oda_numarasi} (${room.kat}. Kat)`;
                        roomSelect.appendChild(option);
                    });
                } else {
                    roomSelect.innerHTML = '<option value="">Uygun oda bulunamadı</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                roomSelect.innerHTML = '<option value="">Hata oluştu</option>';
            });
        }
        
        function loadAvailableRoomsForChange(roomTypeId, checkinDate, checkoutDate, currentReservationId) {
            const roomSelect = document.getElementById('new_room_number_id');
            roomSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            
            fetch('ajax/get_available_rooms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    room_type_id: roomTypeId,
                    checkin_date: checkinDate,
                    checkout_date: checkoutDate,
                    exclude_reservation_id: currentReservationId
                })
            })
            .then(response => response.json())
            .then(data => {
                roomSelect.innerHTML = '<option value="">Oda seçin...</option>';
                
                if (data.success && data.rooms.length > 0) {
                    data.rooms.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = `Oda ${room.oda_numarasi} (${room.kat}. Kat)`;
                        roomSelect.appendChild(option);
                    });
                } else {
                    roomSelect.innerHTML = '<option value="">Uygun oda bulunamadı</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                roomSelect.innerHTML = '<option value="">Hata oluştu</option>';
            });
        }
        
        // WhatsApp gönderme fonksiyonu
        document.addEventListener('DOMContentLoaded', function() {
            // Oda değiştirme modalı event listener
            const roomChangeModal = document.getElementById('roomChangeModal');
            if (roomChangeModal) {
                roomChangeModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const reservationId = button.getAttribute('data-reservation-id');
                    const customerName = button.getAttribute('data-customer-name');
                    const checkinDate = button.getAttribute('data-checkin-date');
                    const checkoutDate = button.getAttribute('data-checkout-date');
                    const currentRoom = button.getAttribute('data-current-room');
                    const roomTypeId = button.getAttribute('data-room-type-id');
                    
                    // Modal alanlarını doldur
                    document.getElementById('change_rezervasyon_id').value = reservationId;
                    document.getElementById('change_customer_name').textContent = customerName;
                    document.getElementById('change_date_range').textContent = checkinDate + ' - ' + checkoutDate;
                    document.getElementById('change_current_room').textContent = currentRoom;
                    
                    // Müsait odaları yükle
                    loadAvailableRoomsForChange(roomTypeId, checkinDate, checkoutDate, reservationId);
                });
            }
            
            const whatsappButtons = document.querySelectorAll('.whatsapp-send');
            
            whatsappButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const rezervasyonId = this.getAttribute('data-rezervasyon-id');
                    const type = this.getAttribute('data-type');
                    const phone = this.getAttribute('data-phone');
                    
                    if (!phone || phone.trim() === '') {
                        alert('Bu rezervasyon için telefon numarası bulunamadı!');
                        return;
                    }
                    
                    // Loading göster
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Hazırlanıyor...';
                    this.style.pointerEvents = 'none';
                    
                    // WhatsApp linki oluştur
                    fetch('ajax/generate-whatsapp-link.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            rezervasyon_id: rezervasyonId,
                            type: type,
                            phone: phone
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // WhatsApp'ı aç
                            window.open(data.whatsapp_link, '_blank');
                        } else {
                            alert('Hata: ' + (data.message || 'WhatsApp linki oluşturulamadı'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                    })
                    .finally(() => {
                        // Loading'i kaldır
                        this.innerHTML = originalText;
                        this.style.pointerEvents = 'auto';
                    });
                });
            });
        });
        
        // Rezervasyon durumu değiştirme fonksiyonu
        function changeReservationStatus(selectElement, originalStatus) {
            const newStatus = selectElement.value;
            
            if (newStatus === originalStatus) {
                return; // Durum değişmemişse işlem yapma
            }
            
            // Kullanıcıya onay sor
            const confirmMessage = `Rezervasyon durumunu "${getStatusText(originalStatus)}" den "${getStatusText(newStatus)}" e değiştirmek istediğinizden emin misiniz?`;
            
            if (confirm(confirmMessage)) {
                // Formu gönder
                selectElement.form.submit();
            } else {
                // İptal edilirse eski değere dön
                selectElement.value = originalStatus;
            }
        }
        
        // Durum metinlerini döndüren fonksiyon
        function getStatusText(status) {
            const statusTexts = {
                'beklemede': 'Beklemede',
                'onaylandi': 'Onaylandı',
                'check_in': 'Check-in',
                'check_out': 'Check-out',
                'iptal': 'İptal'
            };
            return statusTexts[status] || status;
        }

        // Özel Silme Onay Sistemi
        function showDeleteConfirm(reservationId, reservationCode) {
            // Mevcut alert varsa kaldır
            const existingAlert = document.getElementById('customDeleteAlert');
            if (existingAlert) {
                existingAlert.remove();
            }

            // Özel alert HTML'i oluştur
            const alertHTML = `
                <div id="customDeleteAlert" class="custom-alert-overlay">
                    <div class="custom-alert-container">
                        <div class="custom-alert-header">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <h5 class="mb-0">Rezervasyon Silme Onayı</h5>
                        </div>
                        <div class="custom-alert-body">
                            <p><strong>Rezervasyon Kodu:</strong> ${reservationCode}</p>
                            <p>Bu rezervasyonu kalıcı olarak silmek istediğinizden emin misiniz?</p>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Dikkat:</strong> Bu işlem geri alınamaz. Rezervasyon veritabanından tamamen silinecektir.
                            </div>
                        </div>
                        <div class="custom-alert-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeDeleteAlert()">
                                <i class="fas fa-times me-1"></i>İptal
                            </button>
                            <button type="button" class="btn btn-danger" onclick="confirmDelete(${reservationId})">
                                <i class="fas fa-trash me-1"></i>Evet, Sil
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Alert'i sayfaya ekle
            document.body.insertAdjacentHTML('beforeend', alertHTML);

            // ESC tuşu ile kapatma
            document.addEventListener('keydown', handleEscapeKey);
        }

        function closeDeleteAlert() {
            const alert = document.getElementById('customDeleteAlert');
            if (alert) {
                alert.remove();
                document.removeEventListener('keydown', handleEscapeKey);
            }
        }

        function confirmDelete(reservationId) {
            // Loading göster
            const confirmBtn = document.querySelector('#customDeleteAlert .btn-danger');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Siliniyor...';
            confirmBtn.disabled = true;

            // Silme işlemini gerçekleştir
            window.location.href = `?sil=${reservationId}`;
        }

        function handleEscapeKey(event) {
            if (event.key === 'Escape') {
                closeDeleteAlert();
            }
        }

        // Overlay'e tıklayınca kapatma
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('custom-alert-overlay')) {
                closeDeleteAlert();
            }
        });
    </script>

    <style>
        /* Özel Alert Sistemi CSS */
        .custom-alert-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .custom-alert-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            animation: slideIn 0.3s ease;
        }

        .custom-alert-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px 10px 0 0;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .custom-alert-header i {
            font-size: 24px;
        }

        .custom-alert-body {
            padding: 25px;
        }

        .custom-alert-body p {
            margin-bottom: 15px;
            color: #495057;
        }

        .custom-alert-footer {
            padding: 20px 25px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .custom-alert-footer .btn {
            min-width: 100px;
            font-weight: 500;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .custom-alert-container {
                width: 95%;
                margin: 20px;
            }
            
            .custom-alert-header,
            .custom-alert-body,
            .custom-alert-footer {
                padding: 15px;
            }
            
            .custom-alert-footer {
                flex-direction: column;
            }
            
            .custom-alert-footer .btn {
                width: 100%;
            }
        }
    </style>
</body>
</html>
