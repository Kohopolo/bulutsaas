<?php
// Basit giriş kontrolü
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

// Basit veritabanı bağlantısı
try {
    $pdo = new PDO('mysql:host=localhost;dbname=otel_rezervasyon;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

// Tarih parametreleri
$gorunum = $_GET['gorunum'] ?? 'ay'; // gun, hafta, ay
$tarih = $_GET['tarih'] ?? date('Y-m-d');

// Tarih hesaplamaları
$secili_tarih = new DateTime($tarih);
$bugun = new DateTime();

switch ($gorunum) {
    case 'gun':
        $baslangic = clone $secili_tarih;
        $bitis = clone $secili_tarih;
        break;
    case 'hafta':
        $baslangic = clone $secili_tarih;
        $baslangic->modify('monday this week');
        $bitis = clone $baslangic;
        $bitis->modify('+6 days');
        break;
    case 'ay':
    default:
        // Tam ay görünümü için
        $baslangic = clone $secili_tarih;
        $baslangic->modify('first day of this month');
        $bitis = clone $secili_tarih;
        $bitis->modify('last day of this month');
        break;
}

// Toplam oda sayısı
$toplam_oda_sayisi = $pdo->query("SELECT COUNT(*) as toplam FROM oda_numaralari")->fetch()['toplam'];

// Oda listesi
$odalar = $pdo->query("SELECT id, oda_numarasi, oda_tipi_id FROM oda_numaralari ORDER BY oda_numarasi")->fetchAll();

// Oda tipleri
$oda_tipleri = $pdo->query("SELECT id, oda_tipi_adi FROM oda_tipleri")->fetchAll();
$oda_tipi_renkleri = [];
$renkler = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997'];
foreach ($oda_tipleri as $index => $tip) {
    $oda_tipi_renkleri[$tip['id']] = $renkler[$index % count($renkler)];
}

// Takvim günleri oluştur
$takvim_gunleri = [];

// Ay görünümü için boş günleri ekle
if ($gorunum === 'ay') {
    $first_day = clone $baslangic;
    $first_day->modify('monday this week'); // Ayın ilk gününün pazartesi günü
    
    $current = clone $first_day;
    $last_day = clone $bitis;
    $last_day->modify('sunday this week'); // Ayın son gününün pazar günü
    
    while ($current <= $last_day) {
        $gun = $current->format('Y-m-d');
        
        // Bu gün ay içinde mi kontrol et
        $is_in_month = ($current >= $baslangic && $current <= $bitis);
        
        if ($is_in_month) {
            // Bu gün için doluluk hesapla
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT r.oda_numarasi_id) as dolu_oda
                FROM rezervasyonlar r
                WHERE r.durum IN ('check_in', 'onaylandi') 
                AND r.giris_tarihi <= ? AND r.cikis_tarihi > ?
            ");
            $stmt->execute([$gun, $gun]);
            $dolu_oda = $stmt->fetch()['dolu_oda'];
            $doluluk_orani = $toplam_oda_sayisi > 0 ? round(($dolu_oda / $toplam_oda_sayisi) * 100, 2) : 0;
            
            // Bu gün için oda detayları - gelişmiş durum kontrolü
            $stmt = $pdo->prepare("
                SELECT r.oda_numarasi_id, r.durum, r.musteri_adi, r.musteri_soyadi, ot.oda_tipi_adi, onum.oda_numarasi,
                       CASE 
                           -- Öncelik 1: Aktif rezervasyon (check-in yapılmış)
                           WHEN r.durum = 'check_in' 
                                AND ? >= DATE(r.giris_tarihi) 
                                AND ? < DATE(r.cikis_tarihi) 
                                THEN 'dolu'
                           
                           -- Öncelik 2: Checkout saati öncesi dolu (bugün checkout olacak)
                           WHEN r.durum = 'check_in' 
                                AND ? = DATE(r.cikis_tarihi)
                                AND TIME(NOW()) < TIME(r.cikis_tarihi)
                                THEN 'checkout_oncesi_dolu'
                           
                           -- Öncelik 3: Rezerve (onaylanmış ama henüz check-in yapılmamış)
                           WHEN r.durum = 'onaylandi' 
                                AND ? < DATE(r.cikis_tarihi)
                                THEN 'rezerve'
                           
                           -- Varsayılan: Normal durum
                           ELSE r.durum
                       END as final_durum
                FROM rezervasyonlar r
                LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
                LEFT JOIN oda_numaralari onum ON r.oda_numarasi_id = onum.id
                WHERE r.durum IN ('check_in', 'onaylandi') 
                AND r.giris_tarihi <= ? AND r.cikis_tarihi > ?
                ORDER BY onum.oda_numarasi
            ");
            $stmt->execute([$gun, $gun, $gun, $gun, $gun, $gun]);
            $oda_detaylari = $stmt->fetchAll();
        } else {
            $dolu_oda = 0;
            $doluluk_orani = 0;
            $oda_detaylari = [];
        }
        
        $takvim_gunleri[] = [
            'tarih' => $gun,
            'gun_adi' => $current->format('D'),
            'gun_no' => $current->format('j'),
            'dolu_oda' => $dolu_oda,
            'bos_oda' => $toplam_oda_sayisi - $dolu_oda,
            'doluluk_orani' => $doluluk_orani,
            'oda_detaylari' => $oda_detaylari,
            'bugun_mu' => $gun === $bugun->format('Y-m-d'),
            'ay_ici' => $is_in_month
        ];
        
        $current->modify('+1 day');
    }
} else {
    // Gün ve hafta görünümü için
    $current = clone $baslangic;
    while ($current <= $bitis) {
        $gun = $current->format('Y-m-d');
        
        // Bu gün için doluluk hesapla
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT r.oda_numarasi_id) as dolu_oda
            FROM rezervasyonlar r
            WHERE r.durum IN ('check_in', 'onaylandi') 
            AND r.giris_tarihi <= ? AND r.cikis_tarihi > ?
        ");
        $stmt->execute([$gun, $gun]);
        $dolu_oda = $stmt->fetch()['dolu_oda'];
        $doluluk_orani = $toplam_oda_sayisi > 0 ? round(($dolu_oda / $toplam_oda_sayisi) * 100, 2) : 0;
        
        // Bu gün için oda detayları - gelişmiş durum kontrolü
        $stmt = $pdo->prepare("
            SELECT r.oda_numarasi_id, r.durum, r.musteri_adi, r.musteri_soyadi, ot.oda_tipi_adi, onum.oda_numarasi,
                   CASE 
                       -- Öncelik 1: Aktif rezervasyon (check-in yapılmış)
                       WHEN r.durum = 'check_in' 
                            AND ? >= DATE(r.giris_tarihi) 
                            AND ? < DATE(r.cikis_tarihi) 
                            THEN 'dolu'
                       
                       -- Öncelik 2: Checkout saati öncesi dolu (bugün checkout olacak)
                       WHEN r.durum = 'check_in' 
                            AND ? = DATE(r.cikis_tarihi)
                            AND TIME(NOW()) < TIME(r.cikis_tarihi)
                            THEN 'checkout_oncesi_dolu'
                       
                       -- Öncelik 3: Rezerve (onaylanmış ama henüz check-in yapılmamış)
                       WHEN r.durum = 'onaylandi' 
                            AND ? < DATE(r.cikis_tarihi)
                            THEN 'rezerve'
                       
                       -- Varsayılan: Normal durum
                       ELSE r.durum
                   END as final_durum
            FROM rezervasyonlar r
            LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
            LEFT JOIN oda_numaralari onum ON r.oda_numarasi_id = onum.id
            WHERE r.durum IN ('check_in', 'onaylandi') 
            AND r.giris_tarihi <= ? AND r.cikis_tarihi > ?
            ORDER BY onum.oda_numarasi
        ");
        $stmt->execute([$gun, $gun, $gun, $gun, $gun, $gun]);
        $oda_detaylari = $stmt->fetchAll();
        
        $takvim_gunleri[] = [
            'tarih' => $gun,
            'gun_adi' => $current->format('D'),
            'gun_no' => $current->format('j'),
            'dolu_oda' => $dolu_oda,
            'bos_oda' => $toplam_oda_sayisi - $dolu_oda,
            'doluluk_orani' => $doluluk_orani,
            'oda_detaylari' => $oda_detaylari,
            'bugun_mu' => $gun === $bugun->format('Y-m-d'),
            'ay_ici' => true
        ];
        
        $current->modify('+1 day');
    }
}

// Türkçe gün isimleri
$gun_isimleri = [
    'Mon' => 'Pzt', 'Tue' => 'Sal', 'Wed' => 'Çar', 'Thu' => 'Per',
    'Fri' => 'Cum', 'Sat' => 'Cmt', 'Sun' => 'Paz'
];

// Türkçe ay isimleri
$ay_isimleri = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otel Doluluk Oranı - Takvim Görünümü</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .calendar-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .nav-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .nav-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .view-toggle {
            display: flex;
            gap: 5px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 4px;
        }
        
        .view-btn {
            background: transparent;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-btn.active {
            background: rgba(255,255,255,0.3);
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
            overflow-x: auto;
        }
        
        .calendar-grid.ay-view {
            grid-template-columns: repeat(7, 1fr);
        }
        
        .calendar-grid.gun-view {
            grid-template-columns: 1fr;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .calendar-grid.gun-view .calendar-day {
            min-height: 200px;
            min-width: 350px;
            padding: 20px;
            font-size: 14px;
        }
        
        .calendar-grid.gun-view .day-number {
            font-size: 24px;
        }
        
        .calendar-grid.gun-view .day-name {
            font-size: 16px;
        }
        
        .calendar-grid.gun-view .occupancy-stats {
            font-size: 16px;
            margin-top: 10px;
        }
        
        .calendar-grid.hafta-view {
            grid-template-columns: repeat(7, 1fr);
        }
        
        .calendar-day {
            background: white;
            min-height: 80px;
            min-width: 80px;
            padding: 5px;
            position: relative;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 11px;
            pointer-events: auto;
            z-index: 1;
        }
        
        .calendar-day:hover {
            background: #f8f9fa;
            transform: scale(1.02);
            z-index: 10;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .calendar-day.today {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border: 2px solid #ff6b6b;
        }
        
        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
            opacity: 0.5;
        }
        
        .calendar-day.other-month:hover {
            background: #e9ecef;
            opacity: 0.7;
        }
        
        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .day-number {
            font-weight: bold;
            font-size: 12px;
        }
        
        .day-name {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        
        .occupancy-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .occupancy-fill {
            height: 100%;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .occupancy-low { background: #28a745; }
        .occupancy-medium { background: #ffc107; }
        .occupancy-high { background: #dc3545; }
        
        .occupancy-stats {
            font-size: 9px;
            color: #666;
            text-align: center;
        }
        
        .room-list {
            display: none; /* Dar görünümde gizle */
        }
        
        .calendar-grid.gun-view .room-list {
            display: block; /* Gün görünümünde göster */
            max-height: 120px;
            overflow-y: auto;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .room-detail-modal .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .room-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .room-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .room-card.selected {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .room-card.occupied {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        .room-card.available {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .room-number {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .room-type {
            font-size: 11px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .room-status {
            font-size: 10px;
            font-weight: bold;
        }
        
        .room-status.available {
            color: #28a745;
        }
        
        .room-status.occupied {
            color: #dc3545;
        }
        
        .room-item {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 2px;
            padding: 2px 4px;
            border-radius: 3px;
            background: #f8f9fa;
        }
        
        .room-number {
            font-weight: bold;
            color: #495057;
        }
        
        .room-type {
            font-size: 9px;
            color: #6c757d;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 0;
            }
            
            .calendar-day {
                min-height: 80px;
                padding: 5px;
            }
            
            .day-number {
                font-size: 14px;
            }
            
            .room-list {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Başlık -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        Otel Doluluk Oranı
                    </h1>
                    <a href="resepsiyon-ana-ekran.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Ana Ekrana Dön
                    </a>
                </div>
            </div>
        </div>

        <?php
        // Bugünün tarihini bul ve dolu oda sayısını hesapla
        $bugun_tarih = $bugun->format('Y-m-d');
        $bugun_dolu_oda = 0;
        $bugun_doluluk_orani = 0;
        
        foreach ($takvim_gunleri as $gun) {
            if ($gun['tarih'] === $bugun_tarih) {
                $bugun_dolu_oda = $gun['dolu_oda'];
                $bugun_doluluk_orani = $gun['doluluk_orani'];
                break;
            }
        }
        ?>
        
        <!-- İstatistik Kartları -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon text-primary">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="stat-value text-primary"><?= $toplam_oda_sayisi ?></div>
                <div class="stat-label">Toplam Oda</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value text-success"><?= $bugun_dolu_oda ?></div>
                <div class="stat-label">Bugün Dolu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-warning">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-value text-warning"><?= $bugun_doluluk_orani ?>%</div>
                <div class="stat-label">Bugün Doluluk</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-info">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-value text-info"><?= count($takvim_gunleri) ?></div>
                <div class="stat-label">Görüntülenen Gün</div>
            </div>
        </div>

        <!-- Takvim -->
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button class="nav-btn" onclick="changeDate(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    
                    <div class="text-center">
                        <h2 class="mb-0">
                            <?php
                            if ($gorunum === 'ay') {
                                echo $ay_isimleri[$secili_tarih->format('n')] . ' ' . $secili_tarih->format('Y');
                            } elseif ($gorunum === 'hafta') {
                                echo 'Hafta ' . $secili_tarih->format('W') . ', ' . $secili_tarih->format('Y');
                            } else {
                                echo $secili_tarih->format('d') . ' ' . $ay_isimleri[$secili_tarih->format('n')] . ' ' . $secili_tarih->format('Y');
                            }
                            ?>
                        </h2>
                    </div>
                    
                    <button class="nav-btn" onclick="changeDate(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="view-toggle">
                    <button class="view-btn <?= $gorunum === 'gun' ? 'active' : '' ?>" onclick="changeView('gun')">
                        <i class="fas fa-calendar-day"></i> Gün
                    </button>
                    <button class="view-btn <?= $gorunum === 'hafta' ? 'active' : '' ?>" onclick="changeView('hafta')">
                        <i class="fas fa-calendar-week"></i> Hafta
                    </button>
                    <button class="view-btn <?= $gorunum === 'ay' ? 'active' : '' ?>" onclick="changeView('ay')">
                        <i class="fas fa-calendar-alt"></i> Ay
                    </button>
                </div>
            </div>
            
            <div class="calendar-grid <?= $gorunum === 'gun' ? 'gun-view' : ($gorunum === 'hafta' ? 'hafta-view' : 'ay-view') ?>">
                <?php if ($gorunum === 'hafta' || $gorunum === 'ay'): ?>
                    <!-- Hafta ve ay görünümü için gün başlıkları -->
                    <div class="calendar-day text-center fw-bold bg-light py-2">Pzt</div>
                    <div class="calendar-day text-center fw-bold bg-light py-2">Sal</div>
                    <div class="calendar-day text-center fw-bold bg-light py-2">Çar</div>
                    <div class="calendar-day text-center fw-bold bg-light py-2">Per</div>
                    <div class="calendar-day text-center fw-bold bg-light py-2">Cum</div>
                    <div class="calendar-day text-center fw-bold bg-light py-2">Cmt</div>
                    <div class="calendar-day text-center fw-bold bg-light py-2">Paz</div>
                <?php endif; ?>
                
                <?php foreach ($takvim_gunleri as $gun): ?>
                <div class="calendar-day <?= $gun['bugun_mu'] ? 'today' : '' ?> <?= isset($gun['ay_ici']) && !$gun['ay_ici'] ? 'other-month' : '' ?>" 
                     data-date="<?= $gun['tarih'] ?>"
                     onclick="showDayDetails('<?= $gun['tarih'] ?>')">
                        <div class="day-header">
                            <div class="day-number"><?= $gun['gun_no'] ?></div>
                            <div class="day-name"><?= $gun_isimleri[$gun['gun_adi']] ?></div>
                        </div>
                        
                        <div class="occupancy-bar">
                            <div class="occupancy-fill <?php
                                if ($gun['doluluk_orani'] >= 80) echo 'occupancy-high';
                                elseif ($gun['doluluk_orani'] >= 50) echo 'occupancy-medium';
                                else echo 'occupancy-low';
                            ?>" style="width: <?= $gun['doluluk_orani'] ?>%"></div>
                        </div>
                        
                        <div class="occupancy-stats">
                            <?= $gun['dolu_oda'] ?>/<?= $toplam_oda_sayisi ?> (%<?= $gun['doluluk_orani'] ?>)
                        </div>
                        
                        <?php if (!empty($gun['oda_detaylari'])): ?>
                            <div class="room-list">
                                <?php 
                                $max_rooms = $gorunum === 'gun' ? 10 : 3; // Gün görünümünde daha fazla oda göster
                                foreach (array_slice($gun['oda_detaylari'], 0, $max_rooms) as $oda): 
                                    // Durum rengi belirleme
                                    $status_class = '';
                                    $status_text = '';
                                    switch($oda['final_durum']) {
                                        case 'dolu':
                                            $status_class = 'text-danger';
                                            $status_text = 'DOLU';
                                            break;
                                        case 'rezerve':
                                            $status_class = 'text-warning';
                                            $status_text = 'REZERVE';
                                            break;
                                        case 'checkout_oncesi_dolu':
                                            $status_class = 'text-info';
                                            $status_text = 'CHECKOUT';
                                            break;
                                        default:
                                            $status_class = 'text-success';
                                            $status_text = 'MÜSAİT';
                                    }
                                ?>
                                    <div class="room-item">
                                        <span class="room-number"><?= $oda['oda_numarasi'] ?></span>
                                        <span class="room-type"><?= $oda['oda_tipi_adi'] ?></span>
                                        <span class="<?= $status_class ?>" style="font-size: 9px; font-weight: bold;"><?= $status_text ?></span>
                                        <?php if ($gorunum === 'gun'): ?>
                                            <span class="text-muted">- <?= $oda['musteri_adi'] ?> <?= $oda['musteri_soyadi'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($gun['oda_detaylari']) > $max_rooms): ?>
                                    <div class="text-center text-muted" style="font-size: 9px;">
                                        +<?= count($gun['oda_detaylari']) - $max_rooms ?> daha
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Renk Açıklaması -->
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color occupancy-low"></div>
                <span>Düşük Doluluk (0-49%)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color occupancy-medium"></div>
                <span>Orta Doluluk (50-79%)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color occupancy-high"></div>
                <span>Yüksek Doluluk (80-100%)</span>
            </div>
        </div>

        <!-- İstatistik Tablosu -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Detaylı Doluluk İstatistikleri
                        </h5>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i>PDF Döküm
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-1"></i>Excel Döküm
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="statsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Gün</th>
                                        <th>Dolu Oda</th>
                                        <th>Boş Oda</th>
                                        <th>Toplam Oda</th>
                                        <th>Doluluk Oranı</th>
                                        <th>Durum</th>
                                        <th>Gelir (₺)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $toplam_gelir = 0;
                                    $ortalama_doluluk = 0;
                                    $en_yuksek_doluluk = 0;
                                    $en_dusuk_doluluk = 100;
                                    
                                    foreach ($takvim_gunleri as $gun): 
                                        // Bu gün için gelir hesapla
                                        $stmt = $pdo->prepare("
                                            SELECT SUM(r.toplam_tutar) as gunluk_gelir
                                            FROM rezervasyonlar r
                                            WHERE r.durum IN ('check_in', 'onaylandi') 
                                            AND r.giris_tarihi <= ? AND r.cikis_tarihi > ?
                                        ");
                                        $stmt->execute([$gun['tarih'], $gun['tarih']]);
                                        $gunluk_gelir = $stmt->fetch()['gunluk_gelir'] ?? 0;
                                        $toplam_gelir += $gunluk_gelir;
                                        
                                        // İstatistikler
                                        $ortalama_doluluk += $gun['doluluk_orani'];
                                        if ($gun['doluluk_orani'] > $en_yuksek_doluluk) $en_yuksek_doluluk = $gun['doluluk_orani'];
                                        if ($gun['doluluk_orani'] < $en_dusuk_doluluk) $en_dusuk_doluluk = $gun['doluluk_orani'];
                                        
                                        // Durum rengi
                                        $durum_class = '';
                                        $durum_text = '';
                                        if ($gun['doluluk_orani'] >= 80) {
                                            $durum_class = 'text-danger';
                                            $durum_text = 'Yüksek';
                                        } elseif ($gun['doluluk_orani'] >= 50) {
                                            $durum_class = 'text-warning';
                                            $durum_text = 'Orta';
                                        } else {
                                            $durum_class = 'text-success';
                                            $durum_text = 'Düşük';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($gun['tarih'])) ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $gun_isimleri[$gun['gun_adi']] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= $gun['dolu_oda'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= $gun['bos_oda'] ?></span>
                                        </td>
                                        <td><?= $toplam_oda_sayisi ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-2" style="width: 60px; height: 8px;">
                                                    <div class="progress-bar <?php
                                                        if ($gun['doluluk_orani'] >= 80) echo 'bg-danger';
                                                        elseif ($gun['doluluk_orani'] >= 50) echo 'bg-warning';
                                                        else echo 'bg-success';
                                                    ?>" style="width: <?= $gun['doluluk_orani'] ?>%"></div>
                                                </div>
                                                <span class="fw-bold"><?= $gun['doluluk_orani'] ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="<?= $durum_class ?> fw-bold">
                                                <i class="fas fa-circle me-1" style="font-size: 8px;"></i>
                                                <?= $durum_text ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-success fw-bold">
                                                <?= number_format($gunluk_gelir, 2, ',', '.') ?> ₺
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2">TOPLAM</th>
                                        <th>
                                            <span class="badge bg-primary">
                                                <?= array_sum(array_column($takvim_gunleri, 'dolu_oda')) ?>
                                            </span>
                                        </th>
                                        <th>
                                            <span class="badge bg-light text-dark">
                                                <?= array_sum(array_column($takvim_gunleri, 'bos_oda')) ?>
                                            </span>
                                        </th>
                                        <th><?= $toplam_oda_sayisi ?></th>
                                        <th>
                                            <span class="fw-bold text-primary">
                                                <?= count($takvim_gunleri) > 0 ? round($ortalama_doluluk / count($takvim_gunleri), 2) : 0 ?>%
                                            </span>
                                        </th>
                                        <th>
                                            <span class="text-info">
                                                <i class="fas fa-chart-line me-1"></i>
                                                Ortalama
                                            </span>
                                        </th>
                                        <th>
                                            <span class="text-success fw-bold">
                                                <?= number_format($toplam_gelir, 2, ',', '.') ?> ₺
                                            </span>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Özet İstatistikler -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                        <h4><?= count($takvim_gunleri) > 0 ? round($ortalama_doluluk / count($takvim_gunleri), 2) : 0 ?>%</h4>
                        <p class="mb-0">Ortalama Doluluk</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-arrow-up fa-2x mb-2"></i>
                        <h4><?= $en_yuksek_doluluk ?>%</h4>
                        <p class="mb-0">En Yüksek Doluluk</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-arrow-down fa-2x mb-2"></i>
                        <h4><?= $en_dusuk_doluluk ?>%</h4>
                        <p class="mb-0">En Düşük Doluluk</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-lira-sign fa-2x mb-2"></i>
                        <h4><?= number_format($toplam_gelir, 0, ',', '.') ?> ₺</h4>
                        <p class="mb-0">Toplam Gelir</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gün Detay Modal -->
    <div class="modal fade" id="dayModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dayModalTitle">Gün Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body room-detail-modal" id="dayModalBody">
                    <!-- AJAX ile yüklenecek -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" id="selectRoomBtn" onclick="selectRoomForReservation()" style="display: none;">
                        <i class="fas fa-check me-1"></i>Oda Seç ve Rezervasyon Ekle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeDate(direction) {
            const currentDate = '<?= $tarih ?>';
            const currentView = '<?= $gorunum ?>';
            const date = new Date(currentDate);
            
            if (currentView === 'gun') {
                date.setDate(date.getDate() + direction);
            } else if (currentView === 'hafta') {
                date.setDate(date.getDate() + (direction * 7));
            } else if (currentView === 'ay') {
                date.setMonth(date.getMonth() + direction);
            }
            
            const newDate = date.toISOString().split('T')[0];
            window.location.href = '?gorunum=' + currentView + '&tarih=' + newDate;
        }
        
        function changeView(view) {
            const currentDate = '<?= $tarih ?>';
            window.location.href = '?gorunum=' + view + '&tarih=' + currentDate;
        }
        
        function showDayDetails(date) {
            // Modal başlığını güncelle
            document.getElementById('dayModalTitle').textContent = date + ' Tarihi - Oda Durumları';
            
            // Yükleme mesajı
            document.getElementById('dayModalBody').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Yükleniyor...</span></div><p class="mt-2">Oda detayları yükleniyor...</p></div>';
            
            // Modal'ı aç
            const modal = new bootstrap.Modal(document.getElementById('dayModal'));
            modal.show();
            
            // Oda detaylarını yükle
            loadRoomDetails(date);
        }
        
        function loadRoomDetails(date) {
            fetch('ajax/get-room-details.php?date=' + date)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayRoomDetails(data.rooms, data.occupiedRooms, date);
                    } else {
                        document.getElementById('dayModalBody').innerHTML = '<div class="alert alert-danger">Oda detayları yüklenirken hata oluştu: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('dayModalBody').innerHTML = '<div class="alert alert-danger">Oda detayları yüklenirken hata oluştu.</div>';
                });
        }
        
        let selectedDate = null;
        
        function displayRoomDetails(rooms, occupiedRooms, date) {
            selectedDate = date;
            
            const occupiedRoomIds = occupiedRooms.map(room => room.oda_numarasi_id);
            
            // Durum sayılarını hesapla
            let musaitCount = 0;
            let doluCount = 0;
            let rezerveCount = 0;
            let checkoutOncesiCount = 0;
            
            occupiedRooms.forEach(room => {
                switch(room.final_durum) {
                    case 'dolu':
                        doluCount++;
                        break;
                    case 'rezerve':
                        rezerveCount++;
                        break;
                    case 'checkout_oncesi_dolu':
                        checkoutOncesiCount++;
                        break;
                }
            });
            
            musaitCount = rooms.length - occupiedRoomIds.length;
            
            let html = '<div class="row mb-3">';
            html += '<div class="col-md-6"><h6><i class="fas fa-calendar me-2"></i>' + date + ' Tarihi</h6></div>';
            html += '<div class="col-md-6 text-end">';
            html += '<span class="badge bg-success me-1">Müsait: ' + musaitCount + '</span>';
            html += '<span class="badge bg-warning me-1">Rezerve: ' + rezerveCount + '</span>';
            html += '<span class="badge bg-info me-1">Checkout Bekliyor: ' + checkoutOncesiCount + '</span>';
            html += '<span class="badge bg-danger">Dolu: ' + doluCount + '</span>';
            html += '</div></div>';
            html += '<div class="row">';
            
            rooms.forEach(room => {
                const isOccupied = occupiedRoomIds.includes(room.id);
                const occupiedRoom = occupiedRooms.find(or => or.oda_numarasi_id === room.id);
                
                // Durum ve renk belirleme
                let statusClass = 'border-success';
                let statusBadge = 'bg-success';
                let statusText = 'MÜSAİT';
                let statusIcon = 'fas fa-check-circle';
                let canSelect = true;
                
                if (isOccupied && occupiedRoom) {
                    switch(occupiedRoom.final_durum) {
                        case 'dolu':
                            statusClass = 'border-danger';
                            statusBadge = 'bg-danger';
                            statusText = 'DOLU';
                            statusIcon = 'fas fa-times-circle';
                            canSelect = false;
                            break;
                        case 'rezerve':
                            statusClass = 'border-warning';
                            statusBadge = 'bg-warning';
                            statusText = 'REZERVE';
                            statusIcon = 'fas fa-clock';
                            canSelect = false;
                            break;
                        case 'checkout_oncesi_dolu':
                            statusClass = 'border-info';
                            statusBadge = 'bg-info';
                            statusText = 'CHECKOUT BEKLİYOR';
                            statusIcon = 'fas fa-hourglass-half';
                            canSelect = false;
                            break;
                    }
                }
                
                html += '<div class="col-md-3 mb-3">';
                html += '<div class="card ' + statusClass + '" ';
                html += 'data-room-id="' + room.id + '" ';
                html += 'data-room-number="' + room.oda_numarasi + '" ';
                html += 'data-is-occupied="' + isOccupied + '" ';
                html += 'onclick="selectRoom(' + room.id + ', ' + !canSelect + ')" ';
                html += 'style="cursor: ' + (canSelect ? 'pointer' : 'not-allowed') + '">';
                html += '<div class="card-body text-center">';
                html += '<h6 class="card-title">Oda ' + room.oda_numarasi + '</h6>';
                html += '<p class="card-text small">' + room.oda_tipi_adi + '</p>';
                html += '<span class="badge ' + statusBadge + '">';
                html += '<i class="' + statusIcon + ' me-1"></i>' + statusText;
                html += '</span>';
                
                if (isOccupied && occupiedRoom) {
                    html += '<div class="mt-2"><small class="text-muted">' + occupiedRoom.musteri_adi + ' ' + occupiedRoom.musteri_soyadi + '</small></div>';
                    
                    // Uyarı mesajı varsa göster
                    if (occupiedRoom.uyari_mesaji) {
                        html += '<div class="mt-1"><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>' + occupiedRoom.uyari_mesaji + '</small></div>';
                    }
                }
                
                html += '</div></div></div>';
            });
            
            html += '</div>';
            
            // Bilgi mesajı
            html += '<div class="text-center mt-3">';
            html += '<small class="text-muted"><i class="fas fa-info-circle me-1"></i>Müsait odalara tıklayarak direkt rezervasyon oluşturabilirsiniz</small>';
            html += '</div>';
            
            document.getElementById('dayModalBody').innerHTML = html;
        }
        
        function selectRoom(roomId, isOccupied) {
            if (isOccupied) {
                alert('Bu oda dolu! Başka bir oda seçin.');
                return;
            }
            
            // Modal'ı kapat
            const modal = bootstrap.Modal.getInstance(document.getElementById('dayModal'));
            modal.hide();
            
            // Rezervasyon ekleme sayfasına yönlendir
            const url = 'rezervasyon-ekle.php?selected_room=' + roomId + '&selected_date=' + selectedDate;
            window.location.href = url;
        }
        
        
        function exportToPDF() {
            alert('PDF döküm özelliği henüz aktif değil');
        }
        
        function exportToExcel() {
            alert('Excel döküm özelliği henüz aktif değil');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Sayfa yüklendi
        });
    </script>
</body>
</html>