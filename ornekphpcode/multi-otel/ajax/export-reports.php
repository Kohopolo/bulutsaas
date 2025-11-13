<?php
/**
 * Multi Otel - Rapor Export
 * Excel formatında rapor export
 */

require_once '../../csrf_protection.php';
require_once '../../../includes/xss_protection.php';
require_once '../../../includes/session_security.php';
require_once '../../../includes/error_handler.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../includes/multi-otel-functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(401);
    echo 'Yetkisiz erişim';
    exit;
}

// Mevcut otel bilgisini al
$current_otel = getCurrentOtel();
if (!$current_otel) {
    echo 'Otel seçimi gerekli';
    exit;
}

// Tarih filtreleri
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-t');

// Rezervasyon verilerini getir
$rezervasyonlar = fetchAll("
    SELECT 
        r.rezervasyon_kodu,
        r.musteri_adi,
        r.musteri_soyadi,
        r.musteri_email,
        r.musteri_telefon,
        ot.oda_tipi_adi,
        onum.oda_numarasi,
        r.giris_tarihi,
        r.cikis_tarihi,
        r.yetiskin_sayisi,
        r.cocuk_sayisi,
        r.toplam_tutar,
        r.durum,
        r.odeme_durumu,
        r.olusturma_tarihi
    FROM rezervasyonlar r
    LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
    LEFT JOIN oda_numaralari onum ON r.oda_numarasi_id = onum.id
    WHERE r.otel_id = ? 
    AND r.olusturma_tarihi BETWEEN ? AND ?
    ORDER BY r.olusturma_tarihi DESC
", [$current_otel['id'], $baslangic_tarihi . ' 00:00:00', $bitis_tarihi . ' 23:59:59']);

// Excel başlıkları
$headers = [
    'Rezervasyon Kodu',
    'Müşteri Adı',
    'Müşteri Soyadı',
    'Email',
    'Telefon',
    'Oda Tipi',
    'Oda Numarası',
    'Giriş Tarihi',
    'Çıkış Tarihi',
    'Yetişkin Sayısı',
    'Çocuk Sayısı',
    'Toplam Tutar',
    'Durum',
    'Ödeme Durumu',
    'Oluşturma Tarihi'
];

// CSV formatında export
$filename = 'rapor_' . $current_otel['otel_adi'] . '_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM ekle (Excel için Türkçe karakter desteği)
echo "\xEF\xBB\xBF";

// CSV başlıklarını yaz
$output = fopen('php://output', 'w');
fputcsv($output, $headers);

// Verileri yaz
foreach ($rezervasyonlar as $rezervasyon) {
    $row = [
        $rezervasyon['rezervasyon_kodu'],
        $rezervasyon['musteri_adi'],
        $rezervasyon['musteri_soyadi'],
        $rezervasyon['musteri_email'],
        $rezervasyon['musteri_telefon'],
        $rezervasyon['oda_tipi_adi'],
        $rezervasyon['oda_numarasi'],
        $rezervasyon['giris_tarihi'],
        $rezervasyon['cikis_tarihi'],
        $rezervasyon['yetiskin_sayisi'],
        $rezervasyon['cocuk_sayisi'],
        $rezervasyon['toplam_tutar'],
        $rezervasyon['durum'],
        $rezervasyon['odeme_durumu'],
        $rezervasyon['olusturma_tarihi']
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
