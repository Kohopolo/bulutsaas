<?php
require_once '../csrf_protection.php';
require_once '../../includes/xss_protection.php';
require_once '../../includes/session_security.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Detaylı yetki kontrolü
require_once '../../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('dashboard_goruntule')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Dashboard görüntüleme yetkiniz bulunmamaktadır']);
    exit;
}

// CSRF token kontrolü
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Geçersiz CSRF token']);
    exit;
}

$module = $_POST['module'] ?? '';

// Modül içeriklerini yükle
$content = '';

switch ($module) {
    case 'dashboard':
        $content = loadDashboardContent();
        break;
    case 'reservation':
        $content = loadReservationContent();
        break;
    case 'rooms':
        $content = loadRoomsContent();
        break;
    case 'customers':
        $content = loadCustomersContent();
        break;
    case 'reception':
        $content = loadReceptionContent();
        break;
    case 'housekeeping':
        $content = loadHousekeepingContent();
        break;
    case 'fnb':
        $content = loadFNBContent();
        break;
    case 'technical':
        $content = loadTechnicalContent();
        break;
    case 'hr':
        $content = loadHRContent();
        break;
    case 'accounting':
        $content = loadAccountingContent();
        break;
    case 'procurement':
        $content = loadProcurementContent();
        break;
    case 'settings':
        $content = loadSettingsContent();
        break;
    default:
        $content = '<div class="alert alert-warning">Modül bulunamadı</div>';
}

echo json_encode(['success' => true, 'content' => $content]);

// Dashboard içeriği
function loadDashboardContent() {
    $bugun = date('Y-m-d');
    $bu_ay_baslangic = date('Y-m-01');
    $bu_ay_bitis = date('Y-m-t');
    
    // İstatistikler
    $toplam_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar")['sayi'];
    $beklemede_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'beklemede'")['sayi'];
    $onaylanan_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'onaylandi'")['sayi'];
    $aktif_konaklamalar = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'check_in'")['sayi'];
    
    // Bu ay istatistikleri
    $bu_ay_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE DATE(olusturma_tarihi) BETWEEN ? AND ?", [$bu_ay_baslangic, $bu_ay_bitis])['sayi'];
    $bu_ay_gelir = fetchOne("SELECT SUM(toplam_tutar) as toplam FROM rezervasyonlar WHERE durum NOT IN ('iptal') AND DATE(olusturma_tarihi) BETWEEN ? AND ?", [$bu_ay_baslangic, $bu_ay_bitis])['toplam'] ?? 0;
    
    // Bugünkü işlemler
    $bugun_check_in = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE giris_tarihi = ? AND durum = 'onaylandi'", [$bugun])['sayi'];
    $bugun_check_out = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE cikis_tarihi = ? AND durum = 'check_in'", [$bugun])['sayi'];
    
    // Oda doluluk oranı
    $toplam_oda = fetchOne("SELECT COUNT(*) as sayi FROM oda_numaralari WHERE durum = 'aktif'")['sayi'];
    $dolu_oda = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'check_in' AND ? BETWEEN giris_tarihi AND cikis_tarihi", [$bugun])['sayi'];
    $doluluk_orani = $toplam_oda > 0 ? round(($dolu_oda / $toplam_oda) * 100) : 0;
    
    // Günlük gelir
    $gunluk_gelir = fetchOne("SELECT SUM(toplam_tutar) as toplam FROM rezervasyonlar WHERE DATE(olusturma_tarihi) = ? AND durum NOT IN ('iptal')", [$bugun])['toplam'] ?? 0;
    
    // Son rezervasyonlar
    $son_rezervasyonlar = fetchAll("
        SELECT r.*, ot.oda_tipi_adi, onr.oda_no,
               COALESCE(r.musteri_adi, '') as musteri_adi,
               COALESCE(r.musteri_soyadi, '') as musteri_soyadi,
               COALESCE(r.rezervasyon_kodu, CONCAT('RZ', r.id)) as rezervasyon_kodu
        FROM rezervasyonlar r
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        LEFT JOIN oda_numaralari onr ON r.oda_id = onr.id
        ORDER BY r.olusturma_tarihi DESC
        LIMIT 10
    ");
    
    // Bugünkü check-in/check-out listesi
    $bugun_islemleri = fetchAll("
        SELECT r.*, ot.oda_tipi_adi, odn.oda_numarasi,
               CASE 
                   WHEN r.giris_tarihi = ? THEN 'check_in'
                   WHEN r.cikis_tarihi = ? THEN 'check_out'
               END as islem_tipi
        FROM rezervasyonlar r 
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id 
        LEFT JOIN oda_numaralari odn ON r.oda_numarasi_id = odn.id
        WHERE (r.giris_tarihi = ? AND r.durum = 'onaylandi') 
           OR (r.cikis_tarihi = ? AND r.durum = 'check_in')
        ORDER BY r.giris_tarihi ASC
    ", [$bugun, $bugun, $bugun, $bugun]);
    
    // Aylık gelir grafiği için veri
    $aylik_gelir = [];
    for ($i = 11; $i >= 0; $i--) {
        $ay = date('Y-m', strtotime("-$i months"));
        $ay_baslangic = $ay . '-01';
        $ay_bitis = date('Y-m-t', strtotime($ay_baslangic));
        
        $gelir = fetchOne("
            SELECT SUM(toplam_tutar) as toplam 
            FROM rezervasyonlar 
            WHERE durum NOT IN ('iptal') 
            AND DATE(olusturma_tarihi) BETWEEN ? AND ?
        ", [$ay_baslangic, $ay_bitis])['toplam'] ?? 0;
        
        $aylik_gelir[] = [
            'ay' => date('M Y', strtotime($ay_baslangic)),
            'gelir' => $gelir
        ];
    }
    
    // Oda tipi bazında rezervasyon dağılımı
    $oda_tipi_dagilim = fetchAll("
        SELECT ot.oda_tipi_adi, COUNT(r.id) as rezervasyon_sayisi
        FROM oda_tipleri ot
        LEFT JOIN rezervasyonlar r ON ot.id = r.oda_tipi_id AND r.durum NOT IN ('iptal')
        WHERE ot.durum = 'aktif'
        GROUP BY ot.id, ot.oda_tipi_adi
        ORDER BY rezervasyon_sayisi DESC
    ");
    
    ob_start();
    ?>
    <!-- Ana İstatistik Kartları -->
    <div class="cards-grid">
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #007bff;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="card-title">Toplam Rezervasyon</div>
            </div>
            <div class="card-value"><?= $toplam_rezervasyon ?></div>
            <div class="card-description">Bu ay: <?= $bu_ay_rezervasyon ?> rezervasyon</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #28a745;">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="card-title">Doluluk Oranı</div>
            </div>
            <div class="card-value">%<?= $doluluk_orani ?></div>
            <div class="card-description"><?= $dolu_oda ?>/<?= $toplam_oda ?> oda dolu</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #ffc107;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-title">Aktif Konaklamalar</div>
            </div>
            <div class="card-value"><?= $aktif_konaklamalar ?></div>
            <div class="card-description">Şu anda otelde bulunan müşteriler</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #dc3545;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-title">Bu Ay Gelir</div>
            </div>
            <div class="card-value"><?= number_format($bu_ay_gelir, 0, ',', '.') ?>₺</div>
            <div class="card-description">Günlük: <?= number_format($gunluk_gelir, 0, ',', '.') ?>₺</div>
        </div>
    </div>
    
    <!-- Bugünkü İşlemler -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-sign-in-alt text-success"></i> Bugünkü Check-in'ler</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="h4 text-success"><?= $bugun_check_in ?></span>
                        <button class="btn btn-outline-success btn-sm" onclick="switchModule('reception')">
                            <i class="fas fa-eye"></i> Görüntüle
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-sign-out-alt text-warning"></i> Bugünkü Check-out'lar</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="h4 text-warning"><?= $bugun_check_out ?></span>
                        <button class="btn btn-outline-warning btn-sm" onclick="switchModule('reception')">
                            <i class="fas fa-eye"></i> Görüntüle
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Grafikler -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-line"></i> Aylık Gelir Grafiği</h6>
                </div>
                <div class="card-body">
                    <canvas id="monthlyRevenueChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-pie"></i> Oda Tipi Dağılımı</h6>
                </div>
                <div class="card-body">
                    <canvas id="roomTypeChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Son Rezervasyonlar -->
    <div class="table-container">
        <div class="table-header">
            <h5><i class="fas fa-list"></i> Son Rezervasyonlar</h5>
            <div class="btn-group">
                <button class="btn btn-primary btn-sm" onclick="window.location.href='rezervasyon-ekle.php'">
                    <i class="fas fa-plus"></i> Yeni Rezervasyon
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="switchModule('reservation')">
                    <i class="fas fa-list"></i> Tümünü Gör
                </button>
            </div>
        </div>
        <div class="table-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Rezervasyon No</th>
                        <th>Müşteri</th>
                        <th>Oda</th>
                        <th>Giriş</th>
                        <th>Çıkış</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($son_rezervasyonlar as $rezervasyon): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($rezervasyon['rezervasyon_kodu']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']) ?></td>
                        <td>
                            <?= htmlspecialchars($rezervasyon['oda_no'] ?? 'Atanmamış') ?>
                            <?php if ($rezervasyon['oda_tipi_adi']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($rezervasyon['oda_tipi_adi']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])) ?></td>
                        <td><?= date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])) ?></td>
                        <td>
                            <span class="badge bg-<?= getStatusColor($rezervasyon['durum']) ?>">
                                <?= getStatusText($rezervasyon['durum']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="viewReservation(<?= $rezervasyon['id'] ?>)" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="editReservation(<?= $rezervasyon['id'] ?>)" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Bugünkü İşlemler Detayı -->
    <?php if (!empty($bugun_islemleri)): ?>
    <div class="table-container mt-4">
        <div class="table-header">
            <h5><i class="fas fa-calendar-day"></i> Bugünkü İşlemler</h5>
        </div>
        <div class="table-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>İşlem Tipi</th>
                        <th>Müşteri</th>
                        <th>Oda</th>
                        <th>Saat</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bugun_islemleri as $islem): ?>
                    <tr>
                        <td>
                            <?php if ($islem['islem_tipi'] == 'check_in'): ?>
                                <span class="badge bg-success"><i class="fas fa-sign-in-alt"></i> Check-in</span>
                            <?php else: ?>
                                <span class="badge bg-warning"><i class="fas fa-sign-out-alt"></i> Check-out</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($islem['musteri_adi'] . ' ' . $islem['musteri_soyadi']) ?></td>
                        <td><?= htmlspecialchars($islem['oda_numarasi'] ?? 'Atanmamış') ?></td>
                        <td>
                            <?php if ($islem['islem_tipi'] == 'check_in'): ?>
                                <?= date('H:i', strtotime($islem['giris_tarihi'])) ?>
                            <?php else: ?>
                                <?= date('H:i', strtotime($islem['cikis_tarihi'])) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="processCheckInOut(<?= $islem['id'] ?>, '<?= $islem['islem_tipi'] ?>')">
                                <i class="fas fa-cog"></i> İşlem Yap
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Grafik verilerini JavaScript'e aktar
        window.dashboardData = {
            monthlyRevenue: <?= json_encode($aylik_gelir) ?>,
            roomTypeDistribution: <?= json_encode($oda_tipi_dagilim) ?>
        };
    </script>
    <?php
    return ob_get_clean();
}

// Diğer modül içerikleri için placeholder fonksiyonlar
function loadReservationContent() {
    // Filtreleme parametreleri
    $status_filter = $_POST['status'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    
    // Rezervasyon listesi sorgusu
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "r.durum = ?";
        $params[] = $status_filter;
    }
    
    if ($date_from) {
        $where_conditions[] = "r.giris_tarihi >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "r.cikis_tarihi <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Rezervasyonları getir
    $rezervasyonlar = fetchAll("
        SELECT r.*, 
               ot.oda_tipi_adi,
               onr.oda_no,
               COALESCE(r.musteri_adi, '') as musteri_adi,
               COALESCE(r.musteri_soyadi, '') as musteri_soyadi,
               COALESCE(r.rezervasyon_kodu, CONCAT('RZ', r.id)) as rezervasyon_kodu,
               r.toplam_tutar,
               r.giris_tarihi,
               r.cikis_tarihi,
               r.durum,
               r.olusturma_tarihi
        FROM rezervasyonlar r
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        LEFT JOIN oda_numaralari onr ON r.oda_id = onr.id
        $where_clause
        ORDER BY r.olusturma_tarihi DESC
        LIMIT 50
    ", $params);
    
    // İstatistikler
    $toplam_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar")['sayi'];
    $beklemede_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'beklemede'")['sayi'];
    $onaylanan_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'onaylandi'")['sayi'];
    $aktif_rezervasyon = fetchOne("SELECT COUNT(*) as sayi FROM rezervasyonlar WHERE durum = 'check_in'")['sayi'];
    
    ob_start();
    ?>
    <!-- İstatistik Kartları -->
    <div class="cards-grid mb-4">
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #007bff;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="card-title">Toplam Rezervasyon</div>
            </div>
            <div class="card-value"><?= $toplam_rezervasyon ?></div>
            <div class="card-description">Tüm rezervasyonlar</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #ffc107;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-title">Beklemede</div>
            </div>
            <div class="card-value"><?= $beklemede_rezervasyon ?></div>
            <div class="card-description">Onay bekleyen rezervasyonlar</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #28a745;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-title">Onaylanan</div>
            </div>
            <div class="card-value"><?= $onaylanan_rezervasyon ?></div>
            <div class="card-description">Onaylanmış rezervasyonlar</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #17a2b8;">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="card-title">Aktif</div>
            </div>
            <div class="card-value"><?= $aktif_rezervasyon ?></div>
            <div class="card-description">Check-in yapılmış rezervasyonlar</div>
        </div>
    </div>
    
    <!-- Rezervasyon Listesi -->
    <div class="table-container">
        <div class="table-header">
            <h5><i class="fas fa-list"></i> Rezervasyon Listesi</h5>
            <div class="btn-group">
                <button class="btn btn-success btn-sm" onclick="bulkApprove()">
                    <i class="fas fa-check"></i> Toplu Onayla
                </button>
                <button class="btn btn-warning btn-sm" onclick="bulkCancel()">
                    <i class="fas fa-times"></i> Toplu İptal
                </button>
            </div>
        </div>
        <div class="table-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Rezervasyon No</th>
                        <th>Müşteri</th>
                        <th>Oda</th>
                        <th>Giriş</th>
                        <th>Çıkış</th>
                        <th>Gece</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rezervasyonlar as $rezervasyon): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($rezervasyon['rezervasyon_kodu']) ?></strong>
                            <br><small class="text-muted"><?= date('d.m.Y H:i', strtotime($rezervasyon['olusturma_tarihi'])) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($rezervasyon['musteri_adi'] . ' ' . $rezervasyon['musteri_soyadi']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($rezervasyon['oda_no'] ?? 'Atanmamış') ?>
                            <?php if ($rezervasyon['oda_tipi_adi']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($rezervasyon['oda_tipi_adi']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d.m.Y', strtotime($rezervasyon['giris_tarihi'])) ?></td>
                        <td><?= date('d.m.Y', strtotime($rezervasyon['cikis_tarihi'])) ?></td>
                        <td>
                            <?php
                            $checkin = new DateTime($rezervasyon['giris_tarihi']);
                            $checkout = new DateTime($rezervasyon['cikis_tarihi']);
                            $nights = $checkin->diff($checkout)->days;
                            echo $nights;
                            ?>
                        </td>
                        <td>
                            <strong><?= number_format($rezervasyon['toplam_tutar'], 0, ',', '.') ?>₺</strong>
                        </td>
                        <td>
                            <span class="badge bg-<?= getStatusColor($rezervasyon['durum']) ?>">
                                <?= getStatusText($rezervasyon['durum']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="viewReservation(<?= $rezervasyon['id'] ?>)" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="editReservation(<?= $rezervasyon['id'] ?>)" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Eski fonksiyonlar kaldırıldı - yeni detaylı fonksiyonlar aşağıda tanımlı

// Yardımcı fonksiyonlar
function getStatusColor($status) {
    $colors = [
        'beklemede' => 'warning',
        'onaylandi' => 'success',
        'check_in' => 'primary',
        'check_out' => 'secondary',
        'iptal' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

function getStatusText($status) {
    $texts = [
        'beklemede' => 'Beklemede',
        'onaylandi' => 'Onaylandı',
        'check_in' => 'Check-in',
        'check_out' => 'Check-out',
        'iptal' => 'İptal'
    ];
    return $texts[$status] ?? $status;
}

// Oda Yönetimi İçeriği
function loadRoomsContent() {
    // Oda tipleri
    $oda_tipleri = fetchAll("SELECT * FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");
    
    // Oda numaraları
    $oda_numaralari = fetchAll("
        SELECT onr.*, ot.oda_tipi_adi, 
               CASE 
                   -- Öncelik 1: Aktif rezervasyon (check-in yapılmış)
                   WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                                 AND CURDATE() >= DATE(r.giris_tarihi) 
                                 AND CURDATE() < DATE(r.cikis_tarihi) 
                                 THEN 1 END) > 0 THEN 'dolu'
                   
                   -- Öncelik 2: Checkout saati öncesi dolu (bugün checkout olacak)
                   WHEN COUNT(CASE WHEN r.durum = 'check_in' 
                                 AND CURDATE() = DATE(r.cikis_tarihi)
                                 AND TIME(NOW()) < TIME(r.cikis_tarihi)
                                 THEN 1 END) > 0 THEN 'checkout_oncesi_dolu'
                   
                   -- Öncelik 3: Rezerve (onaylanmış ama henüz check-in yapılmamış)
                   WHEN COUNT(CASE WHEN r.durum = 'onaylandi' 
                                 AND CURDATE() < DATE(r.cikis_tarihi)
                                 THEN 1 END) > 0 THEN 'rezerve'
                   
                   -- Öncelik 4: Temizlik bekliyor (checkout yapılmış ama oda hala aktif)
                   WHEN COUNT(CASE WHEN r.durum = 'check_out' 
                                 AND r.gercek_cikis_tarihi IS NOT NULL
                                 AND onr.durum = 'aktif'
                                 THEN 1 END) > 0 THEN 'temizlik_bekliyor'
                   WHEN r.id IS NOT NULL AND r.durum = 'onaylandi' AND r.giris_tarihi = CURDATE() THEN 'check_in_bekliyor'
                   WHEN r.id IS NOT NULL AND r.durum = 'check_in' AND r.cikis_tarihi = CURDATE() THEN 'check_out_bekliyor'
                   ELSE 'bos'
               END as durum_metni
        FROM oda_numaralari onr
        LEFT JOIN oda_tipleri ot ON onr.oda_tipi_id = ot.id
        LEFT JOIN rezervasyonlar r ON onr.id = r.oda_numarasi_id AND r.durum IN ('onaylandi', 'check_in')
        WHERE onr.durum = 'aktif'
        GROUP BY onr.id, onr.oda_numarasi, ot.oda_tipi_adi
        ORDER BY onr.oda_numarasi
    ");
    
    // İstatistikler
    $toplam_oda = count($oda_numaralari);
    $dolu_oda = count(array_filter($oda_numaralari, function($oda) { return $oda['durum_metni'] === 'dolu'; }));
    $bos_oda = $toplam_oda - $dolu_oda;
    $doluluk_orani = $toplam_oda > 0 ? round(($dolu_oda / $toplam_oda) * 100) : 0;
    
    ob_start();
    ?>
    <!-- Oda İstatistikleri -->
    <div class="cards-grid mb-4">
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #007bff;">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="card-title">Toplam Oda</div>
            </div>
            <div class="card-value"><?= $toplam_oda ?></div>
            <div class="card-description">Aktif odalar</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #28a745;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-title">Boş Odalar</div>
            </div>
            <div class="card-value"><?= $bos_oda ?></div>
            <div class="card-description">Müsait odalar</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #dc3545;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="card-title">Dolu Odalar</div>
            </div>
            <div class="card-value"><?= $dolu_oda ?></div>
            <div class="card-description">Dolu odalar</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #ffc107;">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="card-title">Doluluk Oranı</div>
            </div>
            <div class="card-value">%<?= $doluluk_orani ?></div>
            <div class="card-description">Günlük doluluk</div>
        </div>
    </div>
    
    <!-- Oda Listesi -->
    <div class="table-container">
        <div class="table-header">
            <h5><i class="fas fa-list"></i> Oda Listesi</h5>
            <div class="btn-group">
                <button class="btn btn-primary btn-sm" onclick="addRoom()">
                    <i class="fas fa-plus"></i> Oda Ekle
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="addRoomType()">
                    <i class="fas fa-bed"></i> Oda Tipi Ekle
                </button>
            </div>
        </div>
        <div class="table-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Oda No</th>
                        <th>Oda Tipi</th>
                        <th>Kat</th>
                        <th>Durum</th>
                        <th>Son Temizlik</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oda_numaralari as $oda): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($oda['oda_numarasi']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($oda['oda_tipi_adi']) ?></td>
                        <td><?= htmlspecialchars($oda['kat']) ?></td>
                        <td>
                            <?php
                            $durum_class = '';
                            $durum_text = '';
                            switch($oda['durum_metni']) {
                                case 'dolu':
                                    $durum_class = 'bg-danger';
                                    $durum_text = 'Dolu';
                                    break;
                                case 'bos':
                                    $durum_class = 'bg-success';
                                    $durum_text = 'Boş';
                                    break;
                                case 'check_in_bekliyor':
                                    $durum_class = 'bg-warning';
                                    $durum_text = 'Check-in Bekliyor';
                                    break;
                                case 'check_out_bekliyor':
                                    $durum_class = 'bg-info';
                                    $durum_text = 'Check-out Bekliyor';
                                    break;
                                default:
                                    $durum_class = 'bg-secondary';
                                    $durum_text = 'Bilinmiyor';
                            }
                            ?>
                            <span class="badge <?= $durum_class ?>"><?= $durum_text ?></span>
                        </td>
                        <td><?= $oda['son_temizlik'] ? date('d.m.Y H:i', strtotime($oda['son_temizlik'])) : 'Bilinmiyor' ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="editRoom(<?= $oda['id'] ?>)" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="viewRoomDetails(<?= $oda['id'] ?>)" title="Detaylar">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="deleteRoom(<?= $oda['id'] ?>)" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Müşteri Yönetimi İçeriği
function loadCustomersContent() {
    // Müşteri listesi
    $musteriler = fetchAll("
        SELECT m.*, 
               COUNT(r.id) as rezervasyon_sayisi,
               MAX(r.olusturma_tarihi) as son_rezervasyon
        FROM musteriler m
        LEFT JOIN rezervasyonlar r ON m.id = r.musteri_id
        WHERE m.durum = 'aktif'
        GROUP BY m.id
        ORDER BY m.olusturma_tarihi DESC
        LIMIT 50
    ");
    
    // İstatistikler
    $toplam_musteri = fetchOne("SELECT COUNT(*) as sayi FROM musteriler WHERE durum = 'aktif'")['sayi'];
    $yeni_musteri = fetchOne("SELECT COUNT(*) as sayi FROM musteriler WHERE DATE(olusturma_tarihi) = CURDATE() AND durum = 'aktif'")['sayi'];
    $vip_musteri = fetchOne("SELECT COUNT(*) as sayi FROM musteriler WHERE musteri_tipi = 'vip' AND durum = 'aktif'")['sayi'];
    $aktif_musteri = fetchOne("
        SELECT COUNT(DISTINCT m.id) as sayi 
        FROM musteriler m 
        INNER JOIN rezervasyonlar r ON m.id = r.musteri_id 
        WHERE r.durum = 'check_in' AND m.durum = 'aktif'
    ")['sayi'];
    
    ob_start();
    ?>
    <!-- Müşteri İstatistikleri -->
    <div class="cards-grid mb-4">
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #007bff;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-title">Toplam Müşteri</div>
            </div>
            <div class="card-value"><?= $toplam_musteri ?></div>
            <div class="card-description">Kayıtlı müşteriler</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #28a745;">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="card-title">Yeni Müşteri</div>
            </div>
            <div class="card-value"><?= $yeni_musteri ?></div>
            <div class="card-description">Bugün kayıt olan</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #ffc107;">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="card-title">VIP Müşteri</div>
            </div>
            <div class="card-value"><?= $vip_musteri ?></div>
            <div class="card-description">VIP müşteriler</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #17a2b8;">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="card-title">Aktif Müşteri</div>
            </div>
            <div class="card-value"><?= $aktif_musteri ?></div>
            <div class="card-description">Şu anda otelde</div>
        </div>
    </div>
    
    <!-- Müşteri Listesi -->
    <div class="table-container">
        <div class="table-header">
            <h5><i class="fas fa-list"></i> Müşteri Listesi</h5>
            <div class="btn-group">
                <button class="btn btn-primary btn-sm" onclick="addCustomer()">
                    <i class="fas fa-plus"></i> Müşteri Ekle
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="exportCustomers()">
                    <i class="fas fa-download"></i> Dışa Aktar
                </button>
            </div>
        </div>
        <div class="table-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Müşteri</th>
                        <th>Telefon</th>
                        <th>E-posta</th>
                        <th>Tip</th>
                        <th>Rezervasyon</th>
                        <th>Son Rezervasyon</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($musteriler as $musteri): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($musteri['tc_kimlik_no'] ?? '') ?></small>
                        </td>
                        <td><?= htmlspecialchars($musteri['telefon']) ?></td>
                        <td><?= htmlspecialchars($musteri['email']) ?></td>
                        <td>
                            <span class="badge bg-<?= $musteri['musteri_tipi'] === 'vip' ? 'warning' : 'secondary' ?>">
                                <?= ucfirst($musteri['musteri_tipi']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= $musteri['rezervasyon_sayisi'] ?></span>
                        </td>
                        <td>
                            <?= $musteri['son_rezervasyon'] ? date('d.m.Y', strtotime($musteri['son_rezervasyon'])) : 'Yok' ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="viewCustomer(<?= $musteri['id'] ?>)" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="editCustomer(<?= $musteri['id'] ?>)" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Resepsiyon İçeriği
function loadReceptionContent() {
    $bugun = date('Y-m-d');
    
    // Bugünkü check-in'ler
    $bugun_checkin = fetchAll("
        SELECT r.*, ot.oda_tipi_adi, onr.oda_numarasi
        FROM rezervasyonlar r
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        LEFT JOIN oda_numaralari onr ON r.oda_id = onr.id
        WHERE r.giris_tarihi = ? AND r.durum = 'onaylandi'
        ORDER BY r.giris_tarihi ASC
    ", [$bugun]);
    
    // Bugünkü check-out'lar
    $bugun_checkout = fetchAll("
        SELECT r.*, ot.oda_tipi_adi, onr.oda_numarasi
        FROM rezervasyonlar r
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        LEFT JOIN oda_numaralari onr ON r.oda_id = onr.id
        WHERE r.cikis_tarihi = ? AND r.durum = 'check_in'
        ORDER BY r.cikis_tarihi ASC
    ", [$bugun]);
    
    // Aktif konaklamalar
    $aktif_konaklamalar = fetchAll("
        SELECT r.*, ot.oda_tipi_adi, onr.oda_numarasi
        FROM rezervasyonlar r
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        LEFT JOIN oda_numaralari onr ON r.oda_id = onr.id
        WHERE r.durum = 'check_in'
        ORDER BY r.giris_tarihi ASC
    ");
    
    ob_start();
    ?>
    <!-- Resepsiyon İstatistikleri -->
    <div class="cards-grid mb-4">
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #28a745;">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="card-title">Bugünkü Check-in</div>
            </div>
            <div class="card-value"><?= count($bugun_checkin) ?></div>
            <div class="card-description">Giriş yapacaklar</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #ffc107;">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="card-title">Bugünkü Check-out</div>
            </div>
            <div class="card-value"><?= count($bugun_checkout) ?></div>
            <div class="card-description">Çıkış yapacaklar</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #17a2b8;">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="card-title">Aktif Konaklama</div>
            </div>
            <div class="card-value"><?= count($aktif_konaklamalar) ?></div>
            <div class="card-description">Şu anda otelde</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: #007bff;">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="card-title">Hızlı İşlemler</div>
            </div>
            <div class="card-value">4</div>
            <div class="card-description">Mevcut işlemler</div>
        </div>
    </div>
    
    <!-- Bugünkü Check-in'ler -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="table-container">
                <div class="table-header">
                    <h6><i class="fas fa-sign-in-alt text-success"></i> Bugünkü Check-in'ler</h6>
                </div>
                <div class="table-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Müşteri</th>
                                <th>Oda</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bugun_checkin as $checkin): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($checkin['musteri_adi'] . ' ' . $checkin['musteri_soyadi']) ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($checkin['oda_numarasi'] ?? 'Atanmamış') ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($checkin['oda_tipi_adi']) ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-success btn-sm" onclick="processCheckIn(<?= $checkin['id'] ?>)">
                                        <i class="fas fa-check"></i> Check-in
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Bugünkü Check-out'lar -->
        <div class="col-md-6">
            <div class="table-container">
                <div class="table-header">
                    <h6><i class="fas fa-sign-out-alt text-warning"></i> Bugünkü Check-out'lar</h6>
                </div>
                <div class="table-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Müşteri</th>
                                <th>Oda</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bugun_checkout as $checkout): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($checkout['musteri_adi'] . ' ' . $checkout['musteri_soyadi']) ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($checkout['oda_numarasi'] ?? 'Atanmamış') ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($checkout['oda_tipi_adi']) ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="processCheckOut(<?= $checkout['id'] ?>)">
                                        <i class="fas fa-check"></i> Check-out
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Aktif Konaklamalar -->
    <div class="table-container">
        <div class="table-header">
            <h5><i class="fas fa-bed"></i> Aktif Konaklamalar</h5>
        </div>
        <div class="table-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Müşteri</th>
                        <th>Oda</th>
                        <th>Giriş</th>
                        <th>Çıkış</th>
                        <th>Gece</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aktif_konaklamalar as $konaklama): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($konaklama['musteri_adi'] . ' ' . $konaklama['musteri_soyadi']) ?></strong>
                        </td>
                        <td>
                            <?= htmlspecialchars($konaklama['oda_numarasi'] ?? 'Atanmamış') ?>
                            <br><small class="text-muted"><?= htmlspecialchars($konaklama['oda_tipi_adi']) ?></small>
                        </td>
                        <td><?= date('d.m.Y', strtotime($konaklama['giris_tarihi'])) ?></td>
                        <td><?= date('d.m.Y', strtotime($konaklama['cikis_tarihi'])) ?></td>
                        <td>
                            <?php
                            $checkin = new DateTime($konaklama['giris_tarihi']);
                            $checkout = new DateTime($konaklama['cikis_tarihi']);
                            $nights = $checkin->diff($checkout)->days;
                            echo $nights;
                            ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="viewReservation(<?= $konaklama['id'] ?>)" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-warning" onclick="processCheckOut(<?= $konaklama['id'] ?>)" title="Check-out">
                                    <i class="fas fa-sign-out-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>