<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

$page_title = 'Toplu Rezervasyon Yönetimi';

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Toplu rezervasyon işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_group_booking':
                // Grup rezervasyonu oluştur
                $group_name = $_POST['group_name'];
                $contact_person = $_POST['contact_person'];
                $contact_phone = $_POST['contact_phone'];
                $contact_email = $_POST['contact_email'];
                $check_in = $_POST['check_in'];
                $check_out = $_POST['check_out'];
                $room_count = $_POST['room_count'];
                $guest_count = $_POST['guest_count'];
                $special_requests = $_POST['special_requests'];
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO grup_rezervasyonlari 
                        (grup_adi, iletisim_kisi, iletisim_telefon, iletisim_email, 
                         giris_tarihi, cikis_tarihi, oda_sayisi, misafir_sayisi, 
                         ozel_talepler, durum, olusturma_tarihi) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'beklemede', NOW())
                    ");
                    $stmt->execute([
                        $group_name, $contact_person, $contact_phone, $contact_email,
                        $check_in, $check_out, $room_count, $guest_count, $special_requests
                    ]);
                    
                    $success_message = "Grup rezervasyonu başarıyla oluşturuldu!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'update_status':
                $group_id = $_POST['group_id'];
                $new_status = $_POST['new_status'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE grup_rezervasyonlari SET durum = ? WHERE id = ?");
                    $stmt->execute([$new_status, $group_id]);
                    $success_message = "Durum başarıyla güncellendi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
        }
    }
}

// Grup rezervasyonlarını getir
try {
    $stmt = $pdo->prepare("
        SELECT * FROM grup_rezervasyonlari 
        ORDER BY olusturma_tarihi DESC
    ");
    $stmt->execute();
    $group_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $group_bookings = [];
}

// Oda tiplerini getir
try {
    $stmt = $pdo->prepare("SELECT id, oda_tipi_adi, base_price FROM oda_tipleri WHERE durum = 'aktif'");
    $stmt->execute();
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $room_types = [];
}

include 'header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="page-title">
                        <i class="fas fa-users-cog me-2"></i>Toplu Rezervasyon Yönetimi
                    </h1>
                    <p class="text-muted">Grup rezervasyonları ve toplu rezervasyon işlemleri</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newGroupModal">
                        <i class="fas fa-plus me-2"></i>Yeni Grup Rezervasyonu
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

        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= count($group_bookings) ?></h4>
                                <p class="mb-0">Toplam Grup</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
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
                                <h4 class="mb-0"><?= count(array_filter($group_bookings, fn($g) => $g['durum'] === 'onaylandi')) ?></h4>
                                <p class="mb-0">Onaylanan</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
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
                                <h4 class="mb-0"><?= count(array_filter($group_bookings, fn($g) => $g['durum'] === 'beklemede')) ?></h4>
                                <p class="mb-0">Beklemede</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
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
                                <h4 class="mb-0"><?= array_sum(array_column($group_bookings, 'oda_sayisi')) ?></h4>
                                <p class="mb-0">Toplam Oda</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-bed fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grup Rezervasyonları Tablosu -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Grup Rezervasyonları
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Grup Adı</th>
                                <th>İletişim</th>
                                <th>Tarihler</th>
                                <th>Oda/Misafir</th>
                                <th>Durum</th>
                                <th>Oluşturma</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group_bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($booking['grup_adi']) ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($booking['iletisim_kisi']) ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-phone me-1"></i><?= htmlspecialchars($booking['iletisim_telefon']) ?><br>
                                            <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($booking['iletisim_email']) ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-calendar-check me-1 text-success"></i><?= date('d.m.Y', strtotime($booking['giris_tarihi'])) ?><br>
                                        <i class="fas fa-calendar-times me-1 text-danger"></i><?= date('d.m.Y', strtotime($booking['cikis_tarihi'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-bed me-1"></i><?= $booking['oda_sayisi'] ?> oda<br>
                                        <i class="fas fa-users me-1"></i><?= $booking['misafir_sayisi'] ?> misafir
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'beklemede' => 'warning',
                                        'onaylandi' => 'success',
                                        'iptal' => 'danger',
                                        'tamamlandi' => 'info'
                                    ];
                                    $status_texts = [
                                        'beklemede' => 'Beklemede',
                                        'onaylandi' => 'Onaylandı',
                                        'iptal' => 'İptal',
                                        'tamamlandi' => 'Tamamlandı'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $status_colors[$booking['durum']] ?? 'secondary' ?>">
                                        <?= $status_texts[$booking['durum']] ?? $booking['durum'] ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('d.m.Y H:i', strtotime($booking['olusturma_tarihi'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewGroupDetails(<?= $booking['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="updateGroupStatus(<?= $booking['id'] ?>, 'onaylandi')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="updateGroupStatus(<?= $booking['id'] ?>, 'beklemede')">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="updateGroupStatus(<?= $booking['id'] ?>, 'iptal')">
                                            <i class="fas fa-times"></i>
                                        </button>
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

<!-- Yeni Grup Rezervasyonu Modal -->
<div class="modal fade" id="newGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-users-cog me-2"></i>Yeni Grup Rezervasyonu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_group_booking">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Grup Adı *</label>
                                <input type="text" class="form-control" name="group_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">İletişim Kişisi *</label>
                                <input type="text" class="form-control" name="contact_person" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Telefon *</label>
                                <input type="tel" class="form-control" name="contact_phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">E-posta *</label>
                                <input type="email" class="form-control" name="contact_email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giriş Tarihi *</label>
                                <input type="date" class="form-control" name="check_in" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Çıkış Tarihi *</label>
                                <input type="date" class="form-control" name="check_out" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Oda Sayısı *</label>
                                <input type="number" class="form-control" name="room_count" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Misafir Sayısı *</label>
                                <input type="number" class="form-control" name="guest_count" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Özel Talepler</label>
                        <textarea class="form-control" name="special_requests" rows="3" placeholder="Grup için özel talepler, notlar..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Grup Rezervasyonu Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Durum Güncelleme Formu -->
<form id="statusUpdateForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="group_id" id="statusGroupId">
    <input type="hidden" name="new_status" id="statusNewStatus">
</form>

<script>
function updateGroupStatus(groupId, newStatus) {
    if (confirm('Grup rezervasyon durumunu güncellemek istediğinizden emin misiniz?')) {
        document.getElementById('statusGroupId').value = groupId;
        document.getElementById('statusNewStatus').value = newStatus;
        document.getElementById('statusUpdateForm').submit();
    }
}

function viewGroupDetails(groupId) {
    // Grup detaylarını görüntüleme fonksiyonu
    alert('Grup detayları: ' + groupId);
}
</script>

<?php include 'footer.php'; ?>
