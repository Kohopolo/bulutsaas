<?php
/**
 * Multi Otel Modülü Fonksiyonları
 * Otel bazlı işlemler için yardımcı fonksiyonlar
 */

// Otel bilgilerini getir
function getOtel($otel_id) {
    global $pdo;
    return fetchOne("SELECT * FROM oteller WHERE id = ? AND durum = 'aktif'", [$otel_id]);
}

// Tüm aktif otelleri getir
function getAllOteller() {
    return fetchAll("SELECT * FROM oteller WHERE durum = 'aktif' ORDER BY sira_no ASC, otel_adi ASC");
}

// Kullanıcının yetkili olduğu otelleri getir
function getUserOteller($user_id) {
    // Superadmin tüm otelleri görebilir
    $user = fetchOne("SELECT rol FROM kullanicilar WHERE id = ?", [$user_id]);
    if ($user['rol'] == 'superadmin') {
        return getAllOteller();
    }
    
    // Diğer kullanıcılar sadece yetkili oldukları otelleri görebilir
    return fetchAll("
        SELECT o.* FROM oteller o
        INNER JOIN otel_yoneticileri oy ON o.id = oy.otel_id
        WHERE oy.kullanici_id = ? AND oy.durum = 'aktif' AND o.durum = 'aktif'
        ORDER BY o.sira_no ASC, o.otel_adi ASC
    ", [$user_id]);
}

// Otel bazlı oda tiplerini getir
function getOtelOdaTipleri($otel_id) {
    return fetchAll("
        SELECT * FROM oda_tipleri 
        WHERE otel_id = ? AND durum = 'aktif' 
        ORDER BY sira_no ASC, oda_tipi_adi ASC
    ", [$otel_id]);
}

// Otel bazlı oda numaralarını getir
function getOtelOdaNumaralari($otel_id, $oda_tipi_id = null) {
    $where = "otel_id = ?";
    $params = [$otel_id];
    
    if ($oda_tipi_id) {
        $where .= " AND oda_tipi_id = ?";
        $params[] = $oda_tipi_id;
    }
    
    return fetchAll("
        SELECT onum.*, ot.oda_tipi_adi 
        FROM oda_numaralari onum
        LEFT JOIN oda_tipleri ot ON onum.oda_tipi_id = ot.id
        WHERE $where
        ORDER BY onum.kat ASC, onum.oda_numarasi ASC
    ", $params);
}

// Otel bazlı rezervasyonları getir
function getOtelRezervasyonlar($otel_id, $limit = 50, $offset = 0) {
    return fetchAll("
        SELECT r.*, ot.oda_tipi_adi, onum.oda_numarasi, 
               m.ad as musteri_adi, m.soyad as musteri_soyadi, m.email as musteri_email, m.telefon as musteri_telefon,
               COALESCE(s.ad, 'Web Site') as sales_ad, 
               COALESCE(s.soyad, '') as sales_soyad
        FROM rezervasyonlar r 
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
        LEFT JOIN oda_numaralari onum ON r.oda_numarasi_id = onum.id 
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        LEFT JOIN kullanicilar s ON r.satis_elemani_id = s.id
        WHERE r.otel_id = ?
        ORDER BY r.olusturma_tarihi DESC 
        LIMIT ? OFFSET ?
    ", [$otel_id, $limit, $offset]);
}

// Otel bazlı fiyat hesaplama
function calculateOtelPrice($otel_id, $oda_tipi_id, $giris_tarihi, $cikis_tarihi, $yetiskin_sayisi, $cocuk_yaslari = []) {
    // Önce otel ayarlarını kontrol et
    $otel_ayarlari = fetchAll("SELECT anahtar, deger FROM otel_ayarlari WHERE otel_id = ?", [$otel_id]);
    $ayarlar = [];
    foreach ($otel_ayarlari as $ayar) {
        $ayarlar[$ayar['anahtar']] = $ayar['deger'];
    }
    
    // Oda tipi bilgilerini al
    $oda_tipi = fetchOne("SELECT * FROM oda_tipleri WHERE id = ? AND otel_id = ?", [$oda_tipi_id, $otel_id]);
    if (!$oda_tipi) {
        return ['success' => false, 'message' => 'Oda tipi bulunamadı'];
    }
    
    // Mevcut fiyat hesaplama fonksiyonunu kullan
    return fiyatHesaplaDetayli($oda_tipi_id, $giris_tarihi, $cikis_tarihi, $yetiskin_sayisi, $cocuk_yaslari);
}

// Otel bazlı müsait oda kontrolü
function checkOtelRoomAvailability($otel_id, $oda_tipi_id, $giris_tarihi, $cikis_tarihi) {
    return fetchAll("
        SELECT onum.*, ot.oda_tipi_adi
        FROM oda_numaralari onum
        INNER JOIN oda_tipleri ot ON onum.oda_tipi_id = ot.id
        WHERE onum.otel_id = ? 
        AND onum.oda_tipi_id = ?
        AND onum.durum = 'aktif'
        AND onum.id NOT IN (
            SELECT r.oda_numarasi_id 
            FROM rezervasyonlar r 
            WHERE r.otel_id = ? 
            AND r.oda_numarasi_id IS NOT NULL
            AND r.durum IN ('onaylandi', 'check_in')
            AND (
                (r.giris_tarihi <= ? AND r.cikis_tarihi > ?) OR
                (r.giris_tarihi < ? AND r.cikis_tarihi >= ?) OR
                (r.giris_tarihi >= ? AND r.cikis_tarihi <= ?)
            )
        )
        ORDER BY onum.kat ASC, onum.oda_numarasi ASC
    ", [$otel_id, $oda_tipi_id, $otel_id, $giris_tarihi, $giris_tarihi, $cikis_tarihi, $cikis_tarihi, $giris_tarihi, $cikis_tarihi]);
}

// Multi otel rezervasyon oluşturma
function createMultiHotelReservation($otel_id, $customerData, $roomsData, $commonData) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Müşteriyi ekle veya güncelle
        $musteri = fetchOne("SELECT id FROM musteriler WHERE email = ?", [$customerData['email']]);
        
        if ($musteri) {
            $musteri_id = $musteri['id'];
            // Müşteri bilgilerini güncelle
            executeQuery("UPDATE musteriler SET ad = ?, soyad = ?, telefon = ?, tc_kimlik = ? WHERE id = ?", 
                [$customerData['ad'], $customerData['soyad'], $customerData['telefon'], $customerData['tc_kimlik'], $musteri_id]);
        } else {
            // Yeni müşteri ekle
            executeQuery("INSERT INTO musteriler (ad, soyad, email, telefon, tc_kimlik, ilk_otel_id) VALUES (?, ?, ?, ?, ?, ?)", 
                [$customerData['ad'], $customerData['soyad'], $customerData['email'], $customerData['telefon'], $customerData['tc_kimlik'], $otel_id]);
            $musteri_id = $pdo->lastInsertId();
        }
        
        $rezervasyonlar = [];
        $rezervasyon_kodlari = [];
        
        // Her oda için rezervasyon oluştur
        foreach ($roomsData as $roomData) {
            // Oda tipinin otel ile eşleştiğini kontrol et
            $oda_tipi = fetchOne("SELECT * FROM oda_tipleri WHERE id = ? AND otel_id = ?", [$roomData['oda_tipi_id'], $otel_id]);
            if (!$oda_tipi) {
                throw new Exception("Oda tipi bu otelde bulunamadı");
            }
            
            // Rezervasyon kodu oluştur
            $rezervasyon_kodu = 'RZ' . $otel_id . time() . rand(100, 999);
            $rezervasyon_kodlari[] = $rezervasyon_kodu;
            
            // Rezervasyon oluştur
            $sql = "INSERT INTO rezervasyonlar (
                otel_id, musteri_id, oda_tipi_id, oda_numarasi_id, giris_tarihi, cikis_tarihi,
                yetiskin_sayisi, cocuk_sayisi, cocuk_yaslari, yetiskin_detaylari, cocuk_detaylari,
                toplam_tutar, toplam_fiyat, odenen_tutar, kalan_tutar, durum, odeme_durumu,
                satis_elemani_id, rezervasyon_kodu, musteri_adi, musteri_soyadi, musteri_email, 
                musteri_telefon, musteri_kimlik
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $otel_id, $musteri_id, $roomData['oda_tipi_id'], $roomData['oda_numarasi_id'],
                $roomData['giris_tarihi'], $roomData['cikis_tarihi'], $roomData['yetiskin_sayisi'],
                $roomData['cocuk_sayisi'], json_encode($roomData['cocuk_yaslari'] ?? []),
                json_encode($roomData['yetiskin_detaylari'] ?? []), json_encode($roomData['cocuk_detaylari'] ?? []),
                $roomData['toplam_tutar'], $roomData['toplam_tutar'], $commonData['odeme_miktari'] ?? 0,
                $roomData['toplam_tutar'] - ($commonData['odeme_miktari'] ?? 0), 'onaylandi',
                ($commonData['odeme_miktari'] ?? 0) >= $roomData['toplam_tutar'] ? 'tamamen_odendi' : 'kısmi',
                $_SESSION['user_id'], $rezervasyon_kodu, $customerData['ad'], $customerData['soyad'],
                $customerData['email'], $customerData['telefon'], $customerData['tc_kimlik']
            ];
            
            executeQuery($sql, $params);
            $rezervasyon_id = $pdo->lastInsertId();
            
            $rezervasyonlar[] = [
                'id' => $rezervasyon_id,
                'kod' => $rezervasyon_kodu
            ];
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => count($rezervasyonlar) . ' rezervasyon başarıyla oluşturuldu',
            'reservations' => $rezervasyonlar,
            'codes' => $rezervasyon_kodlari
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => 'Rezervasyon oluşturulurken hata: ' . $e->getMessage()
        ];
    }
}

// Otel istatistikleri
function getOtelStats($otel_id) {
    $stats = [];
    
    // Toplam rezervasyon sayısı
    $stats['toplam_rezervasyon'] = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE otel_id = ?", [$otel_id])['sayi'];
    
    // Bugünkü rezervasyonlar
    $stats['bugun_rezervasyon'] = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE otel_id = ? AND DATE(olusturma_tarihi) = CURDATE()", [$otel_id])['sayi'];
    
    // Aktif konaklamalar
    $stats['aktif_konaklama'] = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE otel_id = ? AND durum = 'check_in'", [$otel_id])['sayi'];
    
    // Toplam gelir
    $stats['toplam_gelir'] = fetchOne("SELECT COALESCE(SUM(toplam_tutar), 0) as tutar FROM rezervasyonlar WHERE otel_id = ? AND durum IN ('onaylandi', 'check_in', 'check_out')", [$otel_id])['tutar'];
    
    // Oda doluluk oranı
    $toplam_oda = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE otel_id = ? AND durum = 'aktif'", [$otel_id])['sayi'];
    $dolu_oda = fetchOne("
        SELECT COUNT(DISTINCT r.oda_numarasi_id) as sayi 
        FROM rezervasyonlar r 
        WHERE r.otel_id = ? AND r.durum = 'check_in' AND r.oda_numarasi_id IS NOT NULL
    ", [$otel_id])['sayi'];
    
    $stats['doluluk_orani'] = $toplam_oda > 0 ? round(($dolu_oda / $toplam_oda) * 100, 2) : 0;
    
    return $stats;
}

// Otel seçimi için dropdown oluştur
function generateOtelSelect($selected_otel_id = null, $user_id = null) {
    $oteller = $user_id ? getUserOteller($user_id) : getAllOteller();
    
    $html = '<select class="form-select" id="otel_id" name="otel_id" required>';
    $html .= '<option value="">Otel Seçin</option>';
    
    foreach ($oteller as $otel) {
        $selected = ($selected_otel_id == $otel['id']) ? 'selected' : '';
        $html .= '<option value="' . $otel['id'] . '" ' . $selected . '>' . htmlspecialchars($otel['otel_adi']) . '</option>';
    }
    
    $html .= '</select>';
    return $html;
}

// Otel bilgilerini session'a kaydet
function setCurrentOtel($otel_id) {
    $otel = getOtel($otel_id);
    if ($otel) {
        $_SESSION['current_otel_id'] = $otel_id;
        $_SESSION['current_otel_adi'] = $otel['otel_adi'];
        return true;
    }
    return false;
}

// Mevcut otel bilgisini al
function getCurrentOtel() {
    if (isset($_SESSION['current_otel_id'])) {
        return getOtel($_SESSION['current_otel_id']);
    }
    return null;
}
