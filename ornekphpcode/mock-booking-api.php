<?php
/**
 * Mock Booking.com API
 * Test amaçlı sahte API yanıtları döner
 */

header('Content-Type: application/json');

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
if ($api_key !== 'test_booking_api_key_12345' || $api_secret !== 'test_booking_secret_67890') {
    http_response_code(401);
    echo json_encode([
        'error' => [
            'code' => 401,
            'message' => 'Invalid API credentials'
        ]
    ]);
    exit;
}

// Endpoint'lere göre yanıtlar
// URL decode için path'i temizle
$clean_path = urldecode($path);

switch ($clean_path) {
    case '/health':
        // Sağlık kontrolü
        echo json_encode([
            'status' => 'ok',
            'version' => '1.0.0',
            'timestamp' => time()
        ]);
        break;
        
    case '/bookings':
        if ($method === 'GET') {
            // Rezervasyon listesi
            echo json_encode([
                'bookings' => [
                    [
                        'id' => 'BK123456',
                        'confirmation_number' => 'CNF789012',
                        'hotel_id' => 'HTL001',
                        'room_type_id' => 'ROOM_DELUXE',
                        'check_in' => date('Y-m-d', strtotime('+7 days')),
                        'check_out' => date('Y-m-d', strtotime('+10 days')),
                        'adults' => 2,
                        'children' => 0,
                        'total_price' => 4500.00,
                        'currency' => 'TRY',
                        'status' => 'confirmed',
                        'guest_name' => 'Test Müşteri',
                        'guest_email' => 'test@example.com',
                        'guest_phone' => '+905551234567',
                        'special_requests' => 'Balkonlu oda tercihi',
                        'payment_status' => 'paid',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    [
                        'id' => 'BK123457',
                        'confirmation_number' => 'CNF789013',
                        'hotel_id' => 'HTL001',
                        'room_type_id' => 'ROOM_STANDARD',
                        'check_in' => date('Y-m-d', strtotime('+14 days')),
                        'check_out' => date('Y-m-d', strtotime('+17 days')),
                        'adults' => 1,
                        'children' => 1,
                        'total_price' => 3200.00,
                        'currency' => 'TRY',
                        'status' => 'confirmed',
                        'guest_name' => 'Örnek Kullanıcı',
                        'guest_email' => 'ornek@example.com',
                        'guest_phone' => '+905559876543',
                        'special_requests' => '',
                        'payment_status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                ],
                'total' => 2,
                'page' => 1,
                'per_page' => 20
            ]);
        } elseif ($method === 'POST') {
            // Yeni rezervasyon oluştur
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode([
                'booking' => [
                    'id' => 'BK' . rand(100000, 999999),
                    'confirmation_number' => 'CNF' . rand(100000, 999999),
                    'hotel_id' => $data['hotel_id'] ?? 'HTL001',
                    'room_type_id' => $data['room_type_id'] ?? 'ROOM_TEST',
                    'check_in' => $data['check_in'] ?? date('Y-m-d'),
                    'check_out' => $data['check_out'] ?? date('Y-m-d', strtotime('+3 days')),
                    'adults' => $data['adults'] ?? 2,
                    'children' => $data['children'] ?? 0,
                    'total_price' => 1500.00,
                    'currency' => 'TRY',
                    'status' => 'confirmed',
                    'guest_name' => $data['guest_name'] ?? 'Test Guest',
                    'guest_email' => $data['guest_email'] ?? 'test@test.com',
                    'guest_phone' => $data['guest_phone'] ?? '+905551234567',
                    'payment_status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ]);
        }
        break;
        
    case '/room-types':
        // Oda tipleri
        echo json_encode([
            'room_types' => [
                [
                    'id' => 'ROOM_STANDARD',
                    'name' => 'Standard Room',
                    'capacity' => 2,
                    'base_price' => 800.00
                ],
                [
                    'id' => 'ROOM_DELUXE',
                    'name' => 'Deluxe Room',
                    'capacity' => 3,
                    'base_price' => 1200.00
                ],
                [
                    'id' => 'ROOM_SUITE',
                    'name' => 'Suite',
                    'capacity' => 4,
                    'base_price' => 2000.00
                ]
            ]
        ]);
        break;
        
    case '/availability':
        // Müsaitlik kontrolü
        echo json_encode([
            'availability' => [
                'available' => true,
                'rooms_available' => 5,
                'price' => 1500.00,
                'currency' => 'TRY'
            ]
        ]);
        break;
        
    case '/commission':
        // Komisyon bilgileri
        echo json_encode([
            'commission' => [
                'rate' => 15.0,
                'type' => 'percentage',
                'currency' => 'TRY'
            ]
        ]);
        break;
        
    case '/campaigns':
        // Kampanyalar
        echo json_encode([
            'campaigns' => [
                [
                    'id' => 'CAMP001',
                    'name' => 'Early Bird Discount',
                    'discount' => 20.0,
                    'start_date' => date('Y-m-d'),
                    'end_date' => date('Y-m-d', strtotime('+30 days'))
                ]
            ]
        ]);
        break;
        
    case '/rates':
        // Fiyat güncelleme
        if ($method === 'PUT') {
            echo json_encode([
                'success' => true,
                'message' => 'Rate updated successfully'
            ]);
        }
        break;
        
    case '/inventory':
        // Stok güncelleme
        if ($method === 'PUT') {
            echo json_encode([
                'success' => true,
                'message' => 'Inventory updated successfully'
            ]);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'error' => [
                'code' => 404,
                'message' => 'Endpoint not found'
            ]
        ]);
}

