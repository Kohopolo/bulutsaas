<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/detailed_permission_functions.php';

$page_title = 'İptal Yönetimi';

// Yetki kontrolü
requireDetailedPermission('iptal_yonetimi_goruntule', 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// İptal işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_cancellation_policy':
                if (!hasDetailedPermission('iptal_politikasi_duzenle')) {
                    $error_message = 'İptal politikası düzenleme yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $policy_name = $_POST['policy_name'];
                $description = $_POST['description'];
                $free_cancellation_days = $_POST['free_cancellation_days'];
                $cancellation_fee_percentage = $_POST['cancellation_fee_percentage'];
                $no_show_fee_percentage = $_POST['no_show_fee_percentage'];
                $is_active = $_POST['is_active'];
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO iptal_politikalari 
                        (politika_adi, aciklama, ucretsiz_iptal_gun, iptal_ucreti_yuzde, 
                         no_show_ucreti_yuzde, aktif, olusturma_tarihi) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                        aciklama = VALUES(aciklama),
                        ucretsiz_iptal_gun = VALUES(ucretsiz_iptal_gun),
                        iptal_ucreti_yuzde = VALUES(iptal_ucreti_yuzde),
                        no_show_ucreti_yuzde = VALUES(no_show_ucreti_yuzde),
                        aktif = VALUES(aktif),
                        guncelleme_tarihi = NOW()
                    ");
                    $stmt->execute([
                        $policy_name, $description, $free_cancellation_days, 
                        $cancellation_fee_percentage, $no_show_fee_percentage, $is_active
                    ]);
                    
                    $success_message = "İptal politikası başarıyla güncellendi!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'calculate_cancellation_fee':
                if (!hasDetailedPermission('iptal_ucret_hesapla')) {
                    $error_message = 'İptal ücreti hesaplama yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $rezervasyon_id = $_POST['rezervasyon_id'];
                $iptal_tarihi = $_POST['iptal_tarihi'];
                $iptal_sebebi = $_POST['iptal_sebebi'];
                
                try {
                    // Rezervasyon bilgilerini al
                    $stmt = $pdo->prepare("
                        SELECT r.*, m.ad_soyad as musteri_adi, ot.oda_tipi_adi
                        FROM rezervasyonlar r
                        LEFT JOIN musteriler m ON r.musteri_id = m.id
                        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
                        WHERE r.id = ?
                    ");
                    $stmt->execute([$rezervasyon_id]);
                    $rezervasyon = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$rezervasyon) {
                        $error_message = "Rezervasyon bulunamadı!";
                        break;
                    }
                    
                    // İptal ücreti hesapla
                    $giris_tarihi = new DateTime($rezervasyon['giris_tarihi']);
                    $iptal_tarihi_obj = new DateTime($iptal_tarihi);
                    $gun_farki = $giris_tarihi->diff($iptal_tarihi_obj)->days;
                    
                    $toplam_tutar = $rezervasyon['toplam_tutar'];
                    $iptal_ucreti = 0;
                    $iade_tutari = $toplam_tutar;
                    
                    // İptal politikasına göre ücret hesapla
                    if ($gun_farki >= 7) {
                        // 7 gün öncesi - ücretsiz iptal
                        $iptal_ucreti = 0;
                    } elseif ($gun_farki >= 3) {
                        // 3-6 gün öncesi - %25 ücret
                        $iptal_ucreti = $toplam_tutar * 0.25;
                    } elseif ($gun_farki >= 1) {
                        // 1-2 gün öncesi - %50 ücret
                        $iptal_ucreti = $toplam_tutar * 0.50;
                    } else {
                        // Aynı gün veya no-show - %100 ücret
                        $iptal_ucreti = $toplam_tutar;
                    }
                    
                    $iade_tutari = $toplam_tutar - $iptal_ucreti;
                    
                    // İptal kaydını oluştur
                    $stmt = $pdo->prepare("
                        INSERT INTO iptal_kayitlari 
                        (rezervasyon_id, iptal_tarihi, iptal_sebebi, toplam_tutar, 
                         iptal_ucreti, iade_tutari, durum, olusturma_tarihi) 
                        VALUES (?, ?, ?, ?, ?, ?, 'beklemede', NOW())
                    ");
                    $stmt->execute([
                        $rezervasyon_id, $iptal_tarihi, $iptal_sebebi, 
                        $toplam_tutar, $iptal_ucreti, $iade_tutari
                    ]);
                    
                    $cancellation_calculation = [
                        'rezervasyon' => $rezervasyon,
                        'gun_farki' => $gun_farki,
                        'toplam_tutar' => $toplam_tutar,
                        'iptal_ucreti' => $iptal_ucreti,
                        'iade_tutari' => $iade_tutari
                    ];
                    
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'approve_cancellation':
                if (!hasDetailedPermission('iptal_onayla')) {
                    $error_message = 'İptal onaylama yetkiniz bulunmamaktadır.';
                    break;
                }
                
                $iptal_id = $_POST['iptal_id'];
                $onay_notu = $_POST['onay_notu'];
                
                try {
                    $stmt = $pdo->prepare("
                        UPDATE iptal_kayitlari 
                        SET durum = 'onaylandi', onay_notu = ?, onay_tarihi = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$onay_notu, $iptal_id]);
                    
                    // Rezervasyon durumunu güncelle
                    $stmt = $pdo->prepare("
                        UPDATE rezervasyonlar 
                        SET durum = 'iptal' 
                        WHERE id = (SELECT rezervasyon_id FROM iptal_kayitlari WHERE id = ?)
                    ");
                    $stmt->execute([$iptal_id]);
                    
                    $success_message = "İptal başarıyla onaylandı!";
                } catch (Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
        }
    }
}

// İptal kayıtlarını getir
try {
    $stmt = $pdo->prepare("
        SELECT ik.*, r.rezervasyon_no, m.ad_soyad as musteri_adi, 
               ot.oda_tipi_adi, r.giris_tarihi, r.cikis_tarihi
        FROM iptal_kayitlari ik
        LEFT JOIN rezervasyonlar r ON ik.rezervasyon_id = r.id
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        ORDER BY ik.olusturma_tarihi DESC
        LIMIT 20
    ");
    $stmt->execute();
    $cancellations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cancellations = [];
}

// Rezervasyonları getir (iptal hesaplama için)
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rezervasyon_no, r.giris_tarihi, r.cikis_tarihi, 
               r.toplam_tutar, m.ad_soyad as musteri_adi, ot.oda_tipi_adi
        FROM rezervasyonlar r
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        WHERE r.durum IN ('onaylandi', 'beklemede')
        ORDER BY r.giris_tarihi DESC
        LIMIT 50
    ");
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reservations = [];
}

// İstatistikler
$stats = [
    'toplam_iptal' => count($cancellations),
    'bekleyen_iptal' => count(array_filter($cancellations, fn($c) => $c['durum'] === 'beklemede')),
    'onaylanan_iptal' => count(array_filter($cancellations, fn($c) => $c['durum'] === 'onaylandi')),
    'toplam_iade' => array_sum(array_column($cancellations, 'iade_tutari'))
];

include 'header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="page-title">
                        <i class="fas fa-times-circle me-2"></i>İptal Yönetimi
                    </h1>
                    <p class="text-muted">İptal politikaları ve ücret hesaplama sistemi</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#calculateCancellationModal">
                        <i class="fas fa-calculator me-2"></i>İptal Ücreti Hesapla
                    </button>
                    <?php if (hasDetailedPermission('iptal_politikasi_duzenle')): ?>
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#policyModal">
                        <i class="fas fa-cog me-2"></i>İptal Politikası
                    </button>
                    <?php endif; ?>
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

        <!-- İptal Hesaplama Sonucu -->
        <?php if (isset($cancellation_calculation)): ?>
        <div class="alert alert-info">
            <h5><i class="fas fa-calculator me-2"></i>İptal Ücreti Hesaplama Sonucu</h5>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Müşteri:</strong> <?= htmlspecialchars($cancellation_calculation['rezervasyon']['musteri_adi']) ?></p>
                    <p><strong>Rezervasyon:</strong> <?= htmlspecialchars($cancellation_calculation['rezervasyon']['rezervasyon_no']) ?></p>
                    <p><strong>Gün Farkı:</strong> <?= $cancellation_calculation['gun_farki'] ?> gün</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Toplam Tutar:</strong> <?= number_format($cancellation_calculation['toplam_tutar'], 2) ?>₺</p>
                    <p><strong>İptal Ücreti:</strong> <?= number_format($cancellation_calculation['iptal_ucreti'], 2) ?>₺</p>
                    <p><strong>İade Tutarı:</strong> <?= number_format($cancellation_calculation['iade_tutari'], 2) ?>₺</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?= $stats['toplam_iptal'] ?></h4>
                                <p class="mb-0">Toplam İptal</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-times-circle fa-2x"></i>
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
                                <h4 class="mb-0"><?= $stats['bekleyen_iptal'] ?></h4>
                                <p class="mb-0">Bekleyen İptal</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
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
                                <h4 class="mb-0"><?= $stats['onaylanan_iptal'] ?></h4>
                                <p class="mb-0">Onaylanan İptal</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
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
                                <h4 class="mb-0"><?= number_format($stats['toplam_iade'], 0) ?>₺</h4>
                                <p class="mb-0">Toplam İade</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-lira-sign fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- İptal Kayıtları -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>İptal Kayıtları
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rezervasyon</th>
                                <th>Müşteri</th>
                                <th>Oda Tipi</th>
                                <th>Tarihler</th>
                                <th>İptal Tarihi</th>
                                <th>Toplam Tutar</th>
                                <th>İptal Ücreti</th>
                                <th>İade Tutarı</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cancellations as $cancellation): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($cancellation['rezervasyon_no']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($cancellation['musteri_adi']) ?></td>
                                <td><?= htmlspecialchars($cancellation['oda_tipi_adi']) ?></td>
                                <td>
                                    <div>
                                        <i class="fas fa-calendar-check me-1 text-success"></i><?= date('d.m.Y', strtotime($cancellation['giris_tarihi'])) ?><br>
                                        <i class="fas fa-calendar-times me-1 text-danger"></i><?= date('d.m.Y', strtotime($cancellation['cikis_tarihi'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <small><?= date('d.m.Y', strtotime($cancellation['iptal_tarihi'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= number_format($cancellation['toplam_tutar'], 2) ?>₺</strong>
                                </td>
                                <td>
                                    <span class="text-danger"><?= number_format($cancellation['iptal_ucreti'], 2) ?>₺</span>
                                </td>
                                <td>
                                    <span class="text-success"><?= number_format($cancellation['iade_tutari'], 2) ?>₺</span>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'beklemede' => 'warning',
                                        'onaylandi' => 'success',
                                        'reddedildi' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $status_colors[$cancellation['durum']] ?? 'secondary' ?>">
                                        <?= ucfirst($cancellation['durum']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewCancellationDetails(<?= $cancellation['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (hasDetailedPermission('iptal_onayla') && $cancellation['durum'] === 'beklemede'): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="approveCancellation(<?= $cancellation['id'] ?>)">
                                            <i class="fas fa-check"></i>
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

<!-- İptal Ücreti Hesaplama Modal -->
<div class="modal fade" id="calculateCancellationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calculator me-2"></i>İptal Ücreti Hesapla
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="calculate_cancellation_fee">
                    
                    <div class="mb-3">
                        <label class="form-label">Rezervasyon *</label>
                        <select class="form-select" name="rezervasyon_id" required>
                            <option value="">Rezervasyon Seçin</option>
                            <?php foreach ($reservations as $reservation): ?>
                            <option value="<?= $reservation['id'] ?>">
                                <?= htmlspecialchars($reservation['rezervasyon_no']) ?> - 
                                <?= htmlspecialchars($reservation['musteri_adi']) ?> - 
                                <?= date('d.m.Y', strtotime($reservation['giris_tarihi'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">İptal Tarihi *</label>
                                <input type="date" class="form-control" name="iptal_tarihi" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">İptal Sebebi *</label>
                                <select class="form-select" name="iptal_sebebi" required>
                                    <option value="">Sebep Seçin</option>
                                    <option value="musteri_talebi">Müşteri Talebi</option>
                                    <option value="no_show">No-Show</option>
                                    <option value="hava_durumu">Hava Durumu</option>
                                    <option value="saglik_sorunu">Sağlık Sorunu</option>
                                    <option value="acil_durum">Acil Durum</option>
                                    <option value="diger">Diğer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>İptal Politikası:</h6>
                        <ul class="mb-0">
                            <li><strong>7+ gün öncesi:</strong> Ücretsiz iptal</li>
                            <li><strong>3-6 gün öncesi:</strong> %25 ücret</li>
                            <li><strong>1-2 gün öncesi:</strong> %50 ücret</li>
                            <li><strong>Aynı gün/No-show:</strong> %100 ücret</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator me-2"></i>Ücret Hesapla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İptal Politikası Modal -->
<?php if (hasDetailedPermission('iptal_politikasi_duzenle')): ?>
<div class="modal fade" id="policyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-cog me-2"></i>İptal Politikası Ayarları
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_cancellation_policy">
                    <input type="hidden" name="policy_name" value="default">
                    <input type="hidden" name="is_active" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Politika Açıklaması</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="İptal politikası açıklaması..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Ücretsiz İptal Günü</label>
                                <input type="number" class="form-control" name="free_cancellation_days" value="7" min="0">
                                <small class="text-muted">Bu günden önce ücretsiz iptal</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">İptal Ücreti (%)</label>
                                <input type="number" class="form-control" name="cancellation_fee_percentage" value="25" min="0" max="100">
                                <small class="text-muted">Erken iptal ücreti yüzdesi</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">No-Show Ücreti (%)</label>
                                <input type="number" class="form-control" name="no_show_fee_percentage" value="100" min="0" max="100">
                                <small class="text-muted">No-show ücreti yüzdesi</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Politikayı Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- İptal Onaylama Formu -->
<form id="approveCancellationForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="approve_cancellation">
    <input type="hidden" name="iptal_id" id="approveCancellationId">
    <input type="hidden" name="onay_notu" id="approveCancellationNote">
</form>

<script>
function viewCancellationDetails(cancellationId) {
    // İptal detaylarını görüntüleme
    alert('İptal detayları: ' + cancellationId);
}

function approveCancellation(cancellationId) {
    const note = prompt('Onay notu (opsiyonel):');
    if (note !== null) {
        document.getElementById('approveCancellationId').value = cancellationId;
        document.getElementById('approveCancellationNote').value = note;
        document.getElementById('approveCancellationForm').submit();
    }
}
</script>

<?php include 'footer.php'; ?>
