<?php
/**
 * Mock TatilSepeti API
 * Test amaçlı sahte API yanıtları döner
 */

header('Content-Type: application/json; charset=utf-8');

// Debug
error_log("=== TATILSEPETI MOCK API BAŞLATILDI ===");
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log("Full URL: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''));

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['endpoint'] ?? '/';

// Basit kimlik doğrulama kontrolü
// getallheaders() fonksiyonu her sunucuda çalışmayabilir, fallback ekle
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

$headers = getallheaders();
$api_key = $headers['X-Api-Key'] ?? $headers['X-API-Key'] ?? '';
$api_secret = $headers['X-Api-Secret'] ?? $headers['X-API-Secret'] ?? '';

// Test API anahtarları
if ($api_key !== 'test_tatilsepeti_api_key_12345' || $api_secret !== 'test_tatilsepeti_secret_67890') {
    http_response_code(401);
    echo json_encode([
        'basarili' => false,
        'hata' => [
            'kod' => 401,
            'mesaj' => 'Yetkisiz erişim'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint'lere göre yanıtlar
$clean_path = urldecode($path);

// Debug için log
error_log("TatilSepeti Mock API - Gelen istek: {$method} {$clean_path}");

switch ($clean_path) {
    case '/health':
    case '/ping':
    case '/durum':
        // Sağlık kontrolü
        echo json_encode([
            'basarili' => true,
            'sistem_durumu' => 'cevrimiçi',
            'api_versiyonu' => '3.0',
            'sunucu_zamani' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/bookings':
    case '/reservations':
    case '/rezervasyonlar':
        if ($method === 'GET') {
            // Rezervasyon listesi
            echo json_encode([
                'basarili' => true,
                'veri' => [
                    [
                        'id' => 'TS' . rand(100000, 999999),
                        'rezervasyon_no' => 'RES' . rand(100000, 999999),
                        'otel_kodu' => 'HTL001',
                        'oda_adi' => 'Deluxe Deniz Manzaralı',
                        'giris' => date('Y-m-d', strtotime('+6 days')),
                        'cikis' => date('Y-m-d', strtotime('+9 days')),
                        'yetiskin' => 2,
                        'cocuk' => 0,
                        'fiyat' => 3900.00,
                        'doviz' => 'TRY',
                        'statu' => 'onaylandı',
                        'ad_soyad' => 'Mehmet Kaya',
                        'eposta' => 'mehmet@example.com',
                        'telefon' => '+905551112233',
                        'notlar' => 'Yüksek kat tercihi',
                        'odeme_tipi' => 'kredi_kartı',
                        'oluşturma' => date('Y-m-d H:i:s')
                    ],
                    [
                        'id' => 'TS' . rand(100000, 999999),
                        'rezervasyon_no' => 'RES' . rand(100000, 999999),
                        'otel_kodu' => 'HTL001',
                        'oda_adi' => 'Standart Oda',
                        'giris' => date('Y-m-d', strtotime('+10 days')),
                        'cikis' => date('Y-m-d', strtotime('+13 days')),
                        'yetiskin' => 1,
                        'cocuk' => 1,
                        'fiyat' => 2700.00,
                        'doviz' => 'TRY',
                        'statu' => 'bekliyor',
                        'ad_soyad' => 'Fatma Çelik',
                        'eposta' => 'fatma@example.com',
                        'telefon' => '+905559998877',
                        'notlar' => '',
                        'odeme_tipi' => 'havale',
                        'oluşturma' => date('Y-m-d H:i:s')
                    ]
                ],
                'toplam_kayit' => 2,
                'sayfa_no' => 1,
                'sayfa_boyutu' => 20
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($method === 'POST') {
            // Yeni rezervasyon
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode([
                'basarili' => true,
                'veri' => [
                    'id' => 'TS' . rand(100000, 999999),
                    'rezervasyon_no' => 'RES' . rand(100000, 999999),
                    'otel_kodu' => $data['otel_kodu'] ?? 'HTL001',
                    'oda_adi' => $data['oda_adi'] ?? 'Test Oda',
                    'giris' => $data['giris'] ?? date('Y-m-d'),
                    'cikis' => $data['cikis'] ?? date('Y-m-d', strtotime('+3 days')),
                    'yetiskin' => $data['yetiskin'] ?? 2,
                    'cocuk' => $data['cocuk'] ?? 0,
                    'fiyat' => 1950.00,
                    'doviz' => 'TRY',
                    'statu' => 'onaylandı',
                    'ad_soyad' => $data['ad_soyad'] ?? 'Test Kullanıcı',
                    'eposta' => $data['eposta'] ?? 'test@test.com',
                    'telefon' => $data['telefon'] ?? '+905551234567',
                    'odeme_tipi' => 'nakit',
                    'oluşturma' => date('Y-m-d H:i:s')
                ],
                'mesaj' => 'Rezervasyon kaydedildi'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case '/room-types':
    case '/odalar':
    case '/oda-listesi':
        // Oda tipleri
        echo json_encode([
            'basarili' => true,
            'veri' => [
                [
                    'kod' => 'STD',
                    'isim' => 'Standart Oda',
                    'kisi_kapasitesi' => 2,
                    'fiyat' => 850.00,
                    'doviz' => 'TRY',
                    'ozellikler' => ['Klima', 'Minibar', 'TV']
                ],
                [
                    'kod' => 'DLX',
                    'isim' => 'Deluxe Oda',
                    'kisi_kapasitesi' => 3,
                    'fiyat' => 1300.00,
                    'doviz' => 'TRY',
                    'ozellikler' => ['Klima', 'Minibar', 'TV', 'Balkon']
                ],
                [
                    'kod' => 'SUT',
                    'isim' => 'Süit',
                    'kisi_kapasitesi' => 4,
                    'fiyat' => 2200.00,
                    'doviz' => 'TRY',
                    'ozellikler' => ['Klima', 'Minibar', 'TV', 'Balkon', 'Jakuzi']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/availability':
    case '/musaitlik-sorgula':
        // Müsaitlik
        echo json_encode([
            'basarili' => true,
            'veri' => [
                'musait_mi' => true,
                'bos_oda_sayisi' => 12,
                'gunluk_fiyat' => 1300.00,
                'toplam_fiyat' => 3900.00,
                'doviz' => 'TRY',
                'kampanya_var' => true,
                'kampanya_adi' => 'Yaz Sezonu Fırsatı'
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/commission':
    case '/commission-info':
    case '/komisyon-oranlari':
        // Komisyon
        echo json_encode([
            'basarili' => true,
            'veri' => [
                'komisyon_yuzdesi' => 16.5,
                'hesaplama_tipi' => 'brüt',
                'para_birimi' => 'TRY',
                'kdv_dahil' => true
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/promotions':
    case '/aktif-kampanyalar':
        // Kampanyalar
        echo json_encode([
            'basarili' => true,
            'veri' => [
                [
                    'kampanya_id' => 'K001',
                    'baslik' => 'Yaz Sezonu Erken Rezervasyon',
                    'indirim' => 20.0,
                    'baslama' => date('Y-m-d'),
                    'bitis' => date('Y-m-d', strtotime('+45 days')),
                    'durum' => 'aktif'
                ],
                [
                    'kampanya_id' => 'K002',
                    'baslik' => '3 Gece 2 Öde Kampanyası',
                    'indirim' => 33.3,
                    'baslama' => date('Y-m-d'),
                    'bitis' => date('Y-m-d', strtotime('+90 days')),
                    'durum' => 'aktif'
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/rates':
    case '/prices':
    case '/fiyat-gonder':
        // Fiyat güncelleme
        if ($method === 'PUT' || $method === 'POST') {
            echo json_encode([
                'basarili' => true,
                'mesaj' => 'Fiyatlar güncellendi'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case '/inventory':
    case '/stok-gonder':
        // Stok güncelleme
        if ($method === 'PUT' || $method === 'POST') {
            echo json_encode([
                'basarili' => true,
                'mesaj' => 'Stok bilgileri güncellendi'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'basarili' => false,
            'hata' => [
                'kod' => 404,
                'mesaj' => 'İstenilen endpoint mevcut değil: ' . $clean_path
            ]
        ], JSON_UNESCAPED_UNICODE);
}

