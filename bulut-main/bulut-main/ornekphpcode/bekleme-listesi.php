<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/detailed_permission_functions.php';

$page_title = 'Bekleme Listesi Yönetimi';

// Yetki kontrolü
requireDetailedPermission('bekleme_listesi_goruntule', 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Bekleme listesi işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_to_waitlist':
                if (!hasDetailedPermission('bekleme_listesi_ekle')) {
                    $error_message = 'Bekleme listesine ekleme yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $musteri_id = $_POST['musteri_id'];
                $oda_tipi_id = $_POST['oda_tipi_id'];
                $giris_tarihi = $_POST['giris_tarihi'];
                $cikis_tarihi = $_POST['cikis_tarihi'];
                $misafir_sayisi = $_POST['misafir_sayisi'];
                $oncelik = $_POST['oncelik'];
                $notlar = $_POST['notlar'];
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO bekleme_listesi 
                        (musteri_id, oda_tipi_id, giris_tarihi, cikis_tarihi, misafir_sayisi, 
                         oncelik, notlar, durum, olusturma_tarihi) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'beklemede', NOW())
                    ");
                    $stmt->execute([
                        $musteri_id, $oda_tipi_id, $giris_tarihi, $cikis_tarihi, 
                        $misafir_sayisi, $oncelik, $notlar
                    ]);
                    
                    $success_message = "Müşteri bekleme listesine başarıyla eklendi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'update_priority':
                if (!hasDetailedPermission('bekleme_listesi_oncelik_ayarla')) {
                    $error_message = 'Öncelik ayarlama yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $waitlist_id = $_POST['waitlist_id'];
                $new_priority = $_POST['new_priority'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE bekleme_listesi SET oncelik = ? WHERE id = ?");
                    $stmt->execute([$new_priority, $waitlist_id]);
                    $success_message = "Öncelik başarıyla güncellendi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'remove_from_waitlist':
                if (!hasDetailedPermission('bekleme_listesi_sil')) {
                    $error_message = 'Bekleme listesinden çıkarma yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $waitlist_id = $_POST['waitlist_id'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE bekleme_listesi SET durum = 'iptal' WHERE id = ?");
                    $stmt->execute([$waitlist_id]);
                    $success_message = "Müşteri bekleme listesinden çıkarıldı!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'check_availability':
                $oda_tipi_id = $_POST['oda_tipi_id'];
                $giris_tarihi = $_POST['giris_tarihi'];
                $cikis_tarihi = $_POST['cikis_tarihi'];
                
                try {
                    // Bu tarih aralığında müsait oda var mı kontrol et
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as musait_oda_sayisi
                        FROM odalar o
                        WHERE o.oda_tipi_id = ? 
                        AND o.durum = 'musait'
                        AND o.id NOT IN (
                            SELECT DISTINCT r.oda_id 
                            FROM rezervasyonlar r 
                            WHERE r.durum IN ('onaylandi', 'checkin')
                            AND (
                                (r.giris_tarihi <= ? AND r.cikis_tarihi > ?) OR
                                (r.giris_tarihi < ? AND r.cikis_tarihi >= ?) OR
                                (r.giris_tarihi >= ? AND r.cikis_tarihi <= ?)
                            )
                        )
                    ");
                    $stmt->execute([$oda_tipi_id, $giris_tarihi, $giris_tarihi, $cikis_tarihi, $cikis_tarihi, $giris_tarihi, $cikis_tarihi]);
                    $availability = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($availability['musait_oda_sayisi'] > 0) {
                        $success_message = "Bu tarihlerde {$availability['musait_oda_sayisi']} adet müsait oda bulunmaktadır!";
                    } else {
                        $info_message = "Bu tarihlerde müsait oda bulunmamaktadır.";
                    }
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
        }
    }
}

// Bekleme listesini getir
try {
    $stmt = $pdo->prepare("
        SELECT bl.*, m.ad_soyad as musteri_adi, m.telefon, m.email,
               ot.oda_tipi_adi, ot.base_price
        FROM bekleme_listesi bl
        LEFT JOIN musteriler m ON bl.musteri_id = m.id
        LEFT JOIN oda_tipleri ot ON bl.oda_tipi_id = ot.id
        WHERE bl.durum = 'beklemede'
        ORDER BY bl.oncelik DESC, bl.olusturma_tarihi ASC
    ");
    $stmt->execute();
    $waitlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $waitlist = [];
}

// Oda tiplerini getir
try {
    $stmt = $pdo->prepare("SELECT id, oda_tipi_adi FROM oda_tipleri WHERE durum = 'aktif' ORDER BY oda_tipi_adi");
    $stmt->execute();
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $room_types = [];
}

// Müşterileri getir
try {
    $stmt = $pdo->prepare("SELECT id, ad_soyad, telefon, email FROM musteriler ORDER BY ad_soyad");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $customers = [];
}

// İstatistikler
$stats = [
    'toplam_bekleyen' => count($waitlist),
    'yuksek_oncelik' => count(array_filter($waitlist, fn($w) => $w['oncelik'] >= 8)),
    'bugun_eklenen' => count(array_filter($waitlist, fn($w) => date('Y-m-d', strtotime($w['olusturma_tarihi'])) === date('Y-m-d'))),
    'ortalama_bekleme_suresi' => 0
];

include 'header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="page-title">
                        <i class="fas fa-clock me-2"></i>Bekleme Listesi Yönetimi
                    </h1>
                    <p class="text-muted">Dolu dönemler için müşteri bekleme sistemi</p>
                </div>
                <div class="col-auto">
                    <?php if (hasDetailedPermission('bekleme_listesi_ekle')): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToWaitlistModal">
                        <i class="fas fa-plus me-2"></i>Bekleme Listesine Ekle
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#checkAvailabilityModal">
                        <i class="fas fa-search me-2"></i>Müsaitlik Kontrolü
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($info_message)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i><?= $info_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= $stats['toplam_bekleyen'] ?></h4>
                                <p class="mb-0">Toplam Bekleyen</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= $stats['yuksek_oncelik'] ?></h4>
                                <p class="mb-0">Yüksek Öncelik</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= $stats['bugun_eklenen'] ?></h4>
                                <p class="mb-0">Bugün Eklenen</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-day fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= $stats['ortalama_bekleme_suresi'] ?></h4>
                                <p class="mb-0">Ort. Bekleme (Gün)</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-hourglass-half fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bekleme Listesi -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Bekleme Listesi
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Öncelik</th>
                                <th>Müşteri</th>
                                <th>Oda Tipi</th>
                                <th>Tarihler</th>
                                <th>Misafir</th>
                                <th>Bekleme Süresi</th>
                                <th>Notlar</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($waitlist as $item): ?>
                            <tr class="<?= $item['oncelik'] >= 8 ? 'table-warning' : '' ?>">
                                <td>
                                    <span class="badge bg-<?= $item['oncelik'] >= 8 ? 'danger' : ($item['oncelik'] >= 5 ? 'warning' : 'secondary') ?>">
                                        <?= $item['oncelik'] ?>/10
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($item['musteri_adi']) ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-phone me-1"></i><?= htmlspecialchars($item['telefon']) ?><br>
                                            <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($item['email']) ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($item['oda_tipi_adi']) ?></strong><br>
                                        <small class="text-muted"><?= number_format($item['base_price']) ?>₺/gece</small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-calendar-check me-1 text-success"></i><?= date('d.m.Y', strtotime($item['giris_tarihi'])) ?><br>
                                        <i class="fas fa-calendar-times me-1 text-danger"></i><?= date('d.m.Y', strtotime($item['cikis_tarihi'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <i class="fas fa-users me-1"></i><?= $item['misafir_sayisi'] ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= floor((time() - strtotime($item['olusturma_tarihi'])) / 86400) ?> gün
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(substr($item['notlar'], 0, 50)) ?>...
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewWaitlistDetails(<?= $item['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (hasDetailedPermission('bekleme_listesi_oncelik_ayarla')): ?>
                                        <button class="btn btn-sm btn-outline-warning" onclick="updatePriority(<?= $item['id'] ?>, <?= $item['oncelik'] ?>)">
                                            <i class="fas fa-sort-numeric-up"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="checkRoomAvailability(<?= $item['oda_tipi_id'] ?>, '<?= $item['giris_tarihi'] ?>', '<?= $item['cikis_tarihi'] ?>')">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (hasDetailedPermission('bekleme_listesi_sil')): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromWaitlist(<?= $item['id'] ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bekleme Listesine Ekleme Modal -->
<?php if (hasDetailedPermission('bekleme_listesi_ekle')): ?>
<div class="modal fade" id="addToWaitlistModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Bekleme Listesine Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_to_waitlist">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Müşteri *</label>
                                <select class="form-select" name="musteri_id" required>
                                    <option value="">Müşteri Seçin</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>">
                                        <?= htmlspecialchars($customer['ad_soyad']) ?> 
                                        (<?= htmlspecialchars($customer['telefon']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Oda Tipi *</label>
                                <select class="form-select" name="oda_tipi_id" required>
                                    <option value="">Oda Tipi Seçin</option>
                                    <?php foreach ($room_types as $room_type): ?>
                                    <option value="<?= $room_type['id'] ?>">
                                        <?= htmlspecialchars($room_type['oda_tipi_adi']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giriş Tarihi *</label>
                                <input type="date" class="form-control" name="giris_tarihi" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Çıkış Tarihi *</label>
                                <input type="date" class="form-control" name="cikis_tarihi" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Misafir Sayısı *</label>
                                <input type="number" class="form-control" name="misafir_sayisi" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Öncelik (1-10) *</label>
                                <select class="form-select" name="oncelik" required>
                                    <option value="1">1 - Düşük</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5" selected>5 - Orta</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                    <option value="9">9</option>
                                    <option value="10">10 - Yüksek</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control" name="notlar" rows="3" placeholder="Özel notlar, talepler..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Bekleme Listesine Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Müsaitlik Kontrolü Modal -->
<div class="modal fade" id="checkAvailabilityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>Müsaitlik Kontrolü
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="check_availability">
                    
                    <div class="mb-3">
                        <label class="form-label">Oda Tipi *</label>
                        <select class="form-select" name="oda_tipi_id" required>
                            <option value="">Oda Tipi Seçin</option>
                            <?php foreach ($room_types as $room_type): ?>
                            <option value="<?= $room_type['id'] ?>">
                                <?= htmlspecialchars($room_type['oda_tipi_adi']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giriş Tarihi *</label>
                                <input type="date" class="form-control" name="giris_tarihi" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Çıkış Tarihi *</label>
                                <input type="date" class="form-control" name="cikis_tarihi" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-search me-2"></i>Müsaitlik Kontrol Et
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Öncelik Güncelleme Formu -->
<form id="priorityUpdateForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_priority">
    <input type="hidden" name="waitlist_id" id="priorityWaitlistId">
    <input type="hidden" name="new_priority" id="priorityNewValue">
</form>

<!-- Bekleme Listesinden Çıkarma Formu -->
<form id="removeFromWaitlistForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="remove_from_waitlist">
    <input type="hidden" name="waitlist_id" id="removeWaitlistId">
</form>

<script>
function viewWaitlistDetails(waitlistId) {
    // Bekleme listesi detaylarını görüntüleme
    alert('Bekleme listesi detayları: ' + waitlistId);
}

function updatePriority(waitlistId, currentPriority) {
    const newPriority = prompt('Yeni öncelik değeri (1-10):', currentPriority);
    if (newPriority && newPriority >= 1 && newPriority <= 10) {
        document.getElementById('priorityWaitlistId').value = waitlistId;
        document.getElementById('priorityNewValue').value = newPriority;
        document.getElementById('priorityUpdateForm').submit();
    }
}

function removeFromWaitlist(waitlistId) {
    if (confirm('Bu müşteriyi bekleme listesinden çıkarmak istediğinizden emin misiniz?')) {
        document.getElementById('removeWaitlistId').value = waitlistId;
        document.getElementById('removeFromWaitlistForm').submit();
    }
}

function checkRoomAvailability(odaTipiId, girisTarihi, cikisTarihi) {
    // Oda müsaitlik kontrolü
    alert(`Oda müsaitlik kontrolü:\nOda Tipi: ${odaTipiId}\nGiriş: ${girisTarihi}\nÇıkış: ${cikisTarihi}`);
}
</script>

<?php include 'footer.php'; ?>
