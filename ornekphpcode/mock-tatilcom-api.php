<?php
/**
 * Mock Tatil.com API
 * Test amaçlı sahte API yanıtları döner
 */

header('Content-Type: application/json; charset=utf-8');

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
if ($api_key !== 'test_tatilcom_api_key_12345' || $api_secret !== 'test_tatilcom_secret_67890') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 401,
            'message' => 'Geçersiz API kimlik bilgileri'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Endpoint'lere göre yanıtlar
$clean_path = urldecode($path);

switch ($clean_path) {
    case '/health':
    case '/sistem/durum':
        // Sağlık kontrolü
        echo json_encode([
            'success' => true,
            'durum' => 'aktif',
            'versiyon' => '2.1.0',
            'zaman' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/reservations':
    case '/bookings':
    case '/rezervasyonlar':
    case '/rezervasyon/liste':
        if ($method === 'GET') {
            // Rezervasyon listesi
            echo json_encode([
                'success' => true,
                'data' => [
                    [
                        'rezervasyon_id' => 'TCM' . rand(100000, 999999),
                        'onay_kodu' => 'TK' . rand(10000, 99999),
                        'otel_id' => 'OTL001',
                        'oda_tipi' => 'Deluxe Oda',
                        'giris_tarihi' => date('Y-m-d', strtotime('+5 days')),
                        'cikis_tarihi' => date('Y-m-d', strtotime('+8 days')),
                        'yetiskin_sayisi' => 2,
                        'cocuk_sayisi' => 1,
                        'toplam_fiyat' => 4200.00,
                        'para_birimi' => 'TRY',
                        'durum' => 'onaylandi',
                        'misafir_adi' => 'Ahmet Yılmaz',
                        'misafir_email' => 'ahmet@example.com',
                        'misafir_telefon' => '+905551234567',
                        'ozel_istekler' => 'Deniz manzaralı oda',
                        'odeme_durumu' => 'odendi',
                        'kayit_tarihi' => date('Y-m-d H:i:s'),
                        'guncelleme_tarihi' => date('Y-m-d H:i:s')
                    ],
                    [
                        'rezervasyon_id' => 'TCM' . rand(100000, 999999),
                        'onay_kodu' => 'TK' . rand(10000, 99999),
                        'otel_id' => 'OTL001',
                        'oda_tipi' => 'Standart Oda',
                        'giris_tarihi' => date('Y-m-d', strtotime('+12 days')),
                        'cikis_tarihi' => date('Y-m-d', strtotime('+15 days')),
                        'yetiskin_sayisi' => 2,
                        'cocuk_sayisi' => 0,
                        'toplam_fiyat' => 2850.00,
                        'para_birimi' => 'TRY',
                        'durum' => 'onaylandi',
                        'misafir_adi' => 'Ayşe Demir',
                        'misafir_email' => 'ayse@example.com',
                        'misafir_telefon' => '+905559876543',
                        'ozel_istekler' => '',
                        'odeme_durumu' => 'beklemede',
                        'kayit_tarihi' => date('Y-m-d H:i:s'),
                        'guncelleme_tarihi' => date('Y-m-d H:i:s')
                    ]
                ],
                'toplam' => 2,
                'sayfa' => 1,
                'sayfa_basina' => 20
            ], JSON_UNESCAPED_UNICODE);
        } elseif ($method === 'POST') {
            // Yeni rezervasyon oluştur
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode([
                'success' => true,
                'data' => [
                    'rezervasyon_id' => 'TCM' . rand(100000, 999999),
                    'onay_kodu' => 'TK' . rand(10000, 99999),
                    'otel_id' => $data['otel_id'] ?? 'OTL001',
                    'oda_tipi' => $data['oda_tipi'] ?? 'Test Oda',
                    'giris_tarihi' => $data['giris_tarihi'] ?? date('Y-m-d'),
                    'cikis_tarihi' => $data['cikis_tarihi'] ?? date('Y-m-d', strtotime('+3 days')),
                    'yetiskin_sayisi' => $data['yetiskin_sayisi'] ?? 2,
                    'cocuk_sayisi' => $data['cocuk_sayisi'] ?? 0,
                    'toplam_fiyat' => 1800.00,
                    'para_birimi' => 'TRY',
                    'durum' => 'onaylandi',
                    'misafir_adi' => $data['misafir_adi'] ?? 'Test Misafir',
                    'misafir_email' => $data['misafir_email'] ?? 'test@test.com',
                    'misafir_telefon' => $data['misafir_telefon'] ?? '+905551234567',
                    'odeme_durumu' => 'beklemede',
                    'kayit_tarihi' => date('Y-m-d H:i:s'),
                    'guncelleme_tarihi' => date('Y-m-d H:i:s')
                ],
                'mesaj' => 'Rezervasyon başarıyla oluşturuldu'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case '/room-types':
    case '/oda-tipleri':
    case '/odalar':
        // Oda tipleri
        echo json_encode([
            'success' => true,
            'data' => [
                [
                    'id' => 'ODA_STANDART',
                    'adi' => 'Standart Oda',
                    'kapasite' => 2,
                    'baz_fiyat' => 950.00,
                    'para_birimi' => 'TRY'
                ],
                [
                    'id' => 'ODA_DELUXE',
                    'adi' => 'Deluxe Oda',
                    'kapasite' => 3,
                    'baz_fiyat' => 1400.00,
                    'para_birimi' => 'TRY'
                ],
                [
                    'id' => 'ODA_SUIT',
                    'adi' => 'Süit Oda',
                    'kapasite' => 4,
                    'baz_fiyat' => 2500.00,
                    'para_birimi' => 'TRY'
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/availability':
    case '/musaitlik':
    case '/oda/musaitlik':
        // Müsaitlik kontrolü
        echo json_encode([
            'success' => true,
            'data' => [
                'musait' => true,
                'musait_oda_sayisi' => 8,
                'fiyat' => 1400.00,
                'para_birimi' => 'TRY',
                'indirimli_fiyat' => 1260.00,
                'kampanya' => 'Erken Rezervasyon %10 İndirim'
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/commission':
    case '/komisyon':
    case '/komisyon-bilgisi':
        // Komisyon bilgileri
        echo json_encode([
            'success' => true,
            'data' => [
                'oran' => 18.0,
                'tip' => 'yuzde',
                'para_birimi' => 'TRY',
                'minimum_tutar' => 50.00
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/campaigns':
    case '/kampanyalar':
        // Kampanyalar
        echo json_encode([
            'success' => true,
            'data' => [
                [
                    'id' => 'KAMP001',
                    'adi' => 'Erken Rezervasyon İndirimi',
                    'indirim_orani' => 15.0,
                    'baslangic_tarihi' => date('Y-m-d'),
                    'bitis_tarihi' => date('Y-m-d', strtotime('+60 days')),
                    'aktif' => true
                ],
                [
                    'id' => 'KAMP002',
                    'adi' => 'Hafta Sonu Özel',
                    'indirim_orani' => 10.0,
                    'baslangic_tarihi' => date('Y-m-d'),
                    'bitis_tarihi' => date('Y-m-d', strtotime('+30 days')),
                    'aktif' => true
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case '/rates':
    case '/fiyat-guncelle':
    case '/fiyat':
        // Fiyat güncelleme
        if ($method === 'PUT' || $method === 'POST') {
            echo json_encode([
                'success' => true,
                'mesaj' => 'Fiyat başarıyla güncellendi'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case '/inventory':
    case '/stok-guncelle':
    case '/stok':
        // Stok güncelleme
        if ($method === 'PUT' || $method === 'POST') {
            echo json_encode([
                'success' => true,
                'mesaj' => 'Stok başarıyla güncellendi'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 404,
                'message' => 'Endpoint bulunamadı: ' . $clean_path
            ]
        ], JSON_UNESCAPED_UNICODE);
}

