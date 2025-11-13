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

// Filtre metni
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

// CSV formatında Excel dosyası oluştur
$filename = 'oda_listesi_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// UTF-8 BOM ekle (Excel'de Türkçe karakterlerin doğru görünmesi için)
echo "\xEF\xBB\xBF";

// CSV çıktısı
$output = fopen('php://output', 'w');

// Başlık bilgileri
fputcsv($output, ['Oda Listesi Raporu'], ';');
fputcsv($output, [''], ';'); // Boş satır
fputcsv($output, ['Filtre:', $filterText], ';');
fputcsv($output, ['Rapor Tarihi:', date('d.m.Y H:i')], ';');
fputcsv($output, [''], ';'); // Boş satır

// Tablo başlıkları
fputcsv($output, ['Oda No', 'Oda Tipi', 'Durum', 'Fiyat'], ';');

// Veri satırları
if (empty($rooms_data)) {
    fputcsv($output, ['Seçilen filtreye uygun oda bulunamadı.', '', '', ''], ';');
} else {
    foreach ($rooms_data as $room) {
        fputcsv($output, [
            $room['room_number'],
            $room['room_type'],
            $room['status'],
            $room['price']
        ], ';');
    }
}

// Özet bilgiler
fputcsv($output, [''], ';'); // Boş satır
fputcsv($output, ['Özet Bilgiler'], ';');
fputcsv($output, ['Toplam Oda Sayısı:', count($rooms_data)], ';');

// Durum bazında sayılar
$statusCounts = [];
foreach ($rooms_data as $room) {
    $status = $room['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}

foreach ($statusCounts as $status => $count) {
    fputcsv($output, [$status . ':', $count . ' oda'], ';');
}

fclose($output);
exit;
?>