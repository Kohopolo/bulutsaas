<?php
require_once '../config/database.php';

// Oturum kontrolü
require_once '../includes/functions.php';
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('raporlar_export', 'Rapor export yetkiniz bulunmamaktadır.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: resepsiyon-oda-yonetimi.php');
    exit;
}

$filter = $_POST['filter'] ?? 'all';
$rooms_data = json_decode($_POST['rooms_data'] ?? '[]', true);

// Basit HTML tabanlı PDF oluşturma
$filterText = '';
switch($filter) {
    case 'all': $filterText = 'Tüm Odalar'; break;
    case 'aktif': $filterText = 'Aktif/Temiz Odalar'; break;
    case 'dolu': $filterText = 'Dolu Odalar'; break;
    case 'kirli': $filterText = 'Kirli Odalar'; break;
    case 'bakimda': $filterText = 'Bakımda Olan Odalar'; break;
    case 'temizlik_bekliyor': $filterText = 'Temizlik Bekleyen Odalar'; break;
    case 'devre_disi': $filterText = 'Devre Dışı Odalar'; break;
    default: $filterText = 'Filtrelenmiş Odalar'; break;
}

// HTML içeriği
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Oda Listesi Raporu</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .info { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .summary { margin-top: 30px; }
        .summary h3 { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Oda Listesi Raporu</h1>
    </div>
    
    <div class="info">
        <p><strong>Filtre:</strong> ' . htmlspecialchars($filterText) . '</p>
        <p><strong>Rapor Tarihi:</strong> ' . date('d.m.Y H:i') . '</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Oda No</th>
                <th>Oda Tipi</th>
                <th>Durum</th>
                <th>Fiyat</th>
            </tr>
        </thead>
        <tbody>';

if (empty($rooms_data)) {
    $html .= '<tr><td colspan="4" style="text-align: center;">Seçilen filtreye uygun oda bulunamadı.</td></tr>';
} else {
    foreach ($rooms_data as $room) {
        $html .= '<tr>
            <td>' . htmlspecialchars($room['room_number']) . '</td>
            <td>' . htmlspecialchars($room['room_type']) . '</td>
            <td>' . htmlspecialchars($room['status']) . '</td>
            <td>' . htmlspecialchars($room['price']) . '</td>
        </tr>';
    }
}

$html .= '</tbody>
    </table>
    
    <div class="summary">
        <h3>Özet Bilgiler</h3>
        <p><strong>Toplam Oda Sayısı:</strong> ' . count($rooms_data) . '</p>';

// Durum bazında sayılar
$statusCounts = [];
foreach ($rooms_data as $room) {
    $status = $room['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}

foreach ($statusCounts as $status => $count) {
    $html .= '<p><strong>' . htmlspecialchars($status) . ':</strong> ' . $count . ' oda</p>';
}

$html .= '</div>
</body>
</html>';

// PDF olarak indirme için header ayarları
$filename = 'oda_listesi_' . date('Y-m-d_H-i-s') . '.html';
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $html;
?>