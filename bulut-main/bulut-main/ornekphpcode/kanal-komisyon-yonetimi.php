<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('kanal_komisyon', 'Kanal komisyon yönetimi yetkiniz bulunmamaktadır.');

$page_title = "Kanal Komisyon Yönetimi";
$current_page = "kanal-komisyon-yonetimi";

// AJAX istekleri
if ($_GET['action'] ?? '' === 'get_komisyon') {
    $komisyon_id = $_GET['id'] ?? 0;
    
    if ($komisyon_id > 0) {
        $komisyon = fetchOne("
            SELECT * FROM kanal_komisyon 
            WHERE id = ?
        ", [$komisyon_id]);
        
        if ($komisyon) {
            echo json_encode(['success' => true, 'komisyon' => $komisyon]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Komisyon bulunamadı']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Geçersiz ID']);
    }
    exit;
}

// Komisyon yönetimi işlemleri
if ($_POST['action'] ?? '' === 'komisyon_ekle') {
    $kanal_id = $_POST['kanal_id'] ?? 0;
    $komisyon_orani = $_POST['komisyon_orani'] ?? 0;
    $gecerlilik_baslangic = $_POST['gecerlilik_baslangic'] ?? '';
    $gecerlilik_bitis = $_POST['gecerlilik_bitis'] ?? '';
    $aciklama = $_POST['aciklama'] ?? '';
    
    if ($kanal_id > 0 && $komisyon_orani > 0) {
        $sql = "INSERT INTO kanal_komisyon (
            kanal_id, komisyon_orani, gecerlilik_tarihi_baslangic, 
            gecerlilik_tarihi_bitis, aciklama, olusturma_tarihi
        ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        if (executeQuery($sql, [$kanal_id, $komisyon_orani, $gecerlilik_baslangic, $gecerlilik_bitis, $aciklama])) {
            $_SESSION['success_message'] = "Komisyon oranı başarıyla eklendi";
        } else {
            $_SESSION['error_message'] = "Komisyon oranı eklenemedi";
        }
    } else {
        $_SESSION['error_message'] = "Lütfen tüm alanları doldurun";
    }
    
    header("Location: kanal-komisyon-yonetimi.php");
    exit;
}

if ($_POST['action'] ?? '' === 'komisyon_guncelle') {
    $komisyon_id = $_POST['komisyon_id'] ?? 0;
    $komisyon_orani = $_POST['komisyon_orani'] ?? 0;
    $gecerlilik_baslangic = $_POST['gecerlilik_baslangic'] ?? '';
    $gecerlilik_bitis = $_POST['gecerlilik_bitis'] ?? '';
    $aciklama = $_POST['aciklama'] ?? '';
    
    if ($komisyon_id > 0 && $komisyon_orani > 0) {
        $sql = "UPDATE kanal_komisyon SET 
            komisyon_orani = ?, gecerlilik_tarihi_baslangic = ?, 
            gecerlilik_tarihi_bitis = ?, aciklama = ?, guncelleme_tarihi = NOW()
            WHERE id = ?";
        
        if (executeQuery($sql, [$komisyon_orani, $gecerlilik_baslangic, $gecerlilik_bitis, $aciklama, $komisyon_id])) {
            $_SESSION['success_message'] = "Komisyon oranı başarıyla güncellendi";
        } else {
            $_SESSION['error_message'] = "Komisyon oranı güncellenemedi";
        }
    } else {
        $_SESSION['error_message'] = "Lütfen tüm alanları doldurun";
    }
    
    header("Location: kanal-komisyon-yonetimi.php");
    exit;
}

if ($_POST['action'] ?? '' === 'komisyon_sil') {
    $komisyon_id = $_POST['komisyon_id'] ?? 0;
    
    if ($komisyon_id > 0) {
        $sql = "DELETE FROM kanal_komisyon WHERE id = ?";
        
        if (executeQuery($sql, [$komisyon_id])) {
            $_SESSION['success_message'] = "Komisyon oranı başarıyla silindi";
        } else {
            $_SESSION['error_message'] = "Komisyon oranı silinemedi";
        }
    }
    
    header("Location: kanal-komisyon-yonetimi.php");
    exit;
}

// Kanalları getir
$kanallar = fetchAll("SELECT * FROM kanallar WHERE aktif = 1 ORDER BY kanal_adi");

// Komisyon oranlarını getir
$komisyonlar = fetchAll("
    SELECT kk.*, k.kanal_adi, k.kanal_kodu,
           CASE 
               WHEN kk.gecerlilik_tarihi_bitis < CURDATE() THEN 'suresi_dolmus'
               WHEN kk.gecerlilik_tarihi_baslangic > CURDATE() THEN 'henuz_baslamamis'
               ELSE 'aktif'
           END as durum
    FROM kanal_komisyon kk
    JOIN kanallar k ON kk.kanal_id = k.id
    ORDER BY kk.gecerlilik_tarihi_baslangic DESC, k.kanal_adi
");

// Komisyon istatistikleri
$komisyon_istatistikleri = fetchOne("
    SELECT 
        COUNT(*) as toplam_komisyon,
        AVG(komisyon_orani) as ortalama_komisyon,
        MIN(komisyon_orani) as min_komisyon,
        MAX(komisyon_orani) as max_komisyon,
        SUM(CASE WHEN gecerlilik_tarihi_bitis >= CURDATE() AND gecerlilik_tarihi_baslangic <= CURDATE() THEN 1 ELSE 0 END) as aktif_komisyon
    FROM kanal_komisyon
");

// Aylık komisyon trendi
$aylik_trend = fetchAll("
    SELECT 
        DATE_FORMAT(olusturma_tarihi, '%Y-%m') as ay,
        AVG(komisyon_orani) as ortalama_komisyon,
        COUNT(*) as komisyon_sayisi
    FROM kanal_komisyon
    WHERE olusturma_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(olusturma_tarihi, '%Y-%m')
    ORDER BY ay DESC
");
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Otel Yönetim Sistemi</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <style>
        .desktop-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .desktop-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-container {
            background: white;
            margin: 0;
            padding: 20px;
            border-radius: 0;
            box-shadow: none;
        }
        
        .komisyon-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .komisyon-durum {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .durum-aktif { background: #d4edda; color: #155724; }
        .durum-suresi_dolmus { background: #f8d7da; color: #721c24; }
        .durum-henuz_baslamamis { background: #fff3cd; color: #856404; }
        
        .istatistik-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .istatistik-deger {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .istatistik-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="desktop-container">
        <!-- Desktop Header -->
        <div class="desktop-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="mb-0">
                            <i class="fas fa-percentage me-2"></i>
                            Kanal Komisyon Yönetimi
                        </h1>
                        <p class="mb-0 mt-2" style="opacity: 0.9;">
                            <i class="fas fa-clock me-2"></i>
                            <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#komisyonEkleModal">
                                <i class="fas fa-plus"></i> Komisyon Ekle
                            </button>
                            <a href="kanal-listesi.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left"></i> Kanal Listesi
                            </a>
                            <a href="kanal-analiz.php" class="btn btn-outline-light">
                                <i class="fas fa-chart-line"></i> Analiz
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt"></i> Çıkış
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="container-fluid">
                <!-- Başarı/Hata Mesajları -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Komisyon İstatistikleri -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="istatistik-card">
                            <div class="istatistik-deger"><?= $komisyon_istatistikleri['toplam_komisyon'] ?></div>
                            <div class="istatistik-label">Toplam Komisyon</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="istatistik-card">
                            <div class="istatistik-deger"><?= number_format($komisyon_istatistikleri['ortalama_komisyon'] ?? 0, 2) ?>%</div>
                            <div class="istatistik-label">Ortalama Komisyon</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="istatistik-card">
                            <div class="istatistik-deger"><?= $komisyon_istatistikleri['aktif_komisyon'] ?></div>
                            <div class="istatistik-label">Aktif Komisyon</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="istatistik-card">
                            <div class="istatistik-deger"><?= number_format($komisyon_istatistikleri['max_komisyon'] ?? 0, 2) ?>%</div>
                            <div class="istatistik-label">En Yüksek Komisyon</div>
                        </div>
                    </div>
                </div>

                <!-- Komisyon Trend Grafiği -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2"></i>
                        Aylık Komisyon Trendi
                    </h5>
                    <canvas id="komisyonTrendChart" height="100"></canvas>
                </div>

                <!-- Komisyon Listesi -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    Komisyon Oranları
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kanal</th>
                                                <th>Komisyon Oranı</th>
                                                <th>Geçerlilik Tarihi</th>
                                                <th>Durum</th>
                                                <th>Açıklama</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($komisyonlar as $komisyon): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($komisyon['kanal_adi']) ?></strong>
                                                        <br><small class="text-muted"><?= htmlspecialchars($komisyon['kanal_kodu']) ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary fs-6"><?= number_format($komisyon['komisyon_orani'] ?? 0, 2) ?>%</span>
                                                    </td>
                                                    <td>
                                                        <small>
                                                            <?= date('d.m.Y', strtotime($komisyon['gecerlilik_tarihi_baslangic'])) ?> - 
                                                            <?= date('d.m.Y', strtotime($komisyon['gecerlilik_tarihi_bitis'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="komisyon-durum durum-<?= $komisyon['durum'] ?>">
                                                            <?php
                                                            switch ($komisyon['durum']) {
                                                                case 'aktif': echo 'Aktif'; break;
                                                                case 'suresi_dolmus': echo 'Süresi Dolmuş'; break;
                                                                case 'henuz_baslamamis': echo 'Henüz Başlamamış'; break;
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?= htmlspecialchars($komisyon['aciklama'] ?? '') ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="komisyonDuzenle(<?= $komisyon['id'] ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger" onclick="komisyonSil(<?= $komisyon['id'] ?>)">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Komisyon Ekleme Modal -->
    <div class="modal fade" id="komisyonEkleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Yeni Komisyon Oranı
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="komisyon_ekle">
                        
                        <div class="mb-3">
                            <label for="kanal_id" class="form-label">Kanal</label>
                            <select class="form-select" name="kanal_id" id="kanal_id" required>
                                <option value="">Kanal Seçin</option>
                                <?php foreach ($kanallar as $kanal): ?>
                                    <option value="<?= $kanal['id'] ?>"><?= htmlspecialchars($kanal['kanal_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="komisyon_orani" class="form-label">Komisyon Oranı (%)</label>
                            <input type="number" class="form-control" name="komisyon_orani" id="komisyon_orani" 
                                   step="0.01" min="0" max="100" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gecerlilik_baslangic" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" name="gecerlilik_baslangic" 
                                           id="gecerlilik_baslangic" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gecerlilik_bitis" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" name="gecerlilik_bitis" 
                                           id="gecerlilik_bitis" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" id="aciklama" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Komisyon Düzenleme Modal -->
    <div class="modal fade" id="komisyonDuzenleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Komisyon Oranını Düzenle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="komisyonDuzenleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="komisyon_guncelle">
                        <input type="hidden" name="komisyon_id" id="duzenle_komisyon_id">
                        
                        <div class="mb-3">
                            <label for="duzenle_komisyon_orani" class="form-label">Komisyon Oranı (%)</label>
                            <input type="number" class="form-control" name="komisyon_orani" id="duzenle_komisyon_orani" 
                                   step="0.01" min="0" max="100" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="duzenle_gecerlilik_baslangic" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" name="gecerlilik_baslangic" 
                                           id="duzenle_gecerlilik_baslangic" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="duzenle_gecerlilik_bitis" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" name="gecerlilik_bitis" 
                                           id="duzenle_gecerlilik_bitis" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="duzenle_aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" id="duzenle_aciklama" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Komisyon Silme Modal -->
    <div class="modal fade" id="komisyonSilModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>
                        Komisyon Oranını Sil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="komisyonSilForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="komisyon_sil">
                        <input type="hidden" name="komisyon_id" id="sil_komisyon_id">
                        
                        <p>Bu komisyon oranını silmek istediğinizden emin misiniz?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Bu işlem geri alınamaz!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Komisyon trend grafiği
        const trendData = <?= json_encode($aylik_trend) ?>;
        const ctx = document.getElementById('komisyonTrendChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendData.map(item => item.ay),
                datasets: [{
                    label: 'Ortalama Komisyon (%)',
                    data: trendData.map(item => parseFloat(item.ortalama_komisyon)),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Komisyon Oranı (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Ay'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });

        // Komisyon düzenleme fonksiyonu
        function komisyonDuzenle(komisyonId) {
            // AJAX ile komisyon verilerini getir
            fetch(`kanal-komisyon-yonetimi.php?action=get_komisyon&id=${komisyonId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('duzenle_komisyon_id').value = data.komisyon.id;
                        document.getElementById('duzenle_komisyon_orani').value = data.komisyon.komisyon_orani;
                        document.getElementById('duzenle_gecerlilik_baslangic').value = data.komisyon.gecerlilik_tarihi_baslangic;
                        document.getElementById('duzenle_gecerlilik_bitis').value = data.komisyon.gecerlilik_tarihi_bitis;
                        document.getElementById('duzenle_aciklama').value = data.komisyon.aciklama || '';
                        
                        new bootstrap.Modal(document.getElementById('komisyonDuzenleModal')).show();
                    } else {
                        alert('Komisyon verileri alınamadı');
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                    alert('Bir hata oluştu');
                });
        }

        // Komisyon silme fonksiyonu
        function komisyonSil(komisyonId) {
            document.getElementById('sil_komisyon_id').value = komisyonId;
            new bootstrap.Modal(document.getElementById('komisyonSilModal')).show();
        }

        // Tarih validasyonu
        document.getElementById('gecerlilik_baslangic').addEventListener('change', function() {
            const bitisTarihi = document.getElementById('gecerlilik_bitis');
            if (this.value && bitisTarihi.value && this.value > bitisTarihi.value) {
                bitisTarihi.value = this.value;
            }
            bitisTarihi.min = this.value;
        });

        document.getElementById('gecerlilik_bitis').addEventListener('change', function() {
            const baslangicTarihi = document.getElementById('gecerlilik_baslangic');
            if (this.value && baslangicTarihi.value && this.value < baslangicTarihi.value) {
                alert('Bitiş tarihi başlangıç tarihinden önce olamaz');
                this.value = baslangicTarihi.value;
            }
        });
    </script>
</body>
</html>
