<?php
require_once 'csrf_protection.php';
require_once '../includes/xss_protection.php';
require_once '../includes/session_security.php';
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Giriş kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
if (!hasDetailedPermission('teknik_servis_talep_listesi')) {
    $_SESSION['error_message'] = 'Teknik servis talep listesi görüntüleme yetkiniz bulunmamaktadır.';
    header('Location: /error/403.php');
    exit;
}

// Filtreleme
$filtre_durum = $_GET['durum'] ?? '';
$filtre_tur = $_GET['talep_turu'] ?? '';
$filtre_oncelik = $_GET['oncelik'] ?? '';

// WHERE koşulları
$where_conditions = [];
$params = [];

if ($filtre_durum) {
    $where_conditions[] = "tst.durum = ?";
    $params[] = $filtre_durum;
}

if ($filtre_tur) {
    $where_conditions[] = "tst.talep_turu = ?";
    $params[] = $filtre_tur;
}

if ($filtre_oncelik) {
    $where_conditions[] = "tst.oncelik = ?";
    $params[] = $filtre_oncelik;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Talepleri getir
$talepler = fetchAll("
    SELECT tst.*, on.oda_numarasi, ot.oda_tipi_adi as oda_tipi,
           k1.ad as talep_eden_adi, k1.soyad as talep_eden_soyadi,
           k2.ad as teknisyen_adi, k2.soyad as teknisyen_soyadi
    FROM teknik_servis_talepleri tst
    LEFT JOIN oda_numaralari on ON tst.oda_id = on.id
    LEFT JOIN oda_tipleri ot ON on.oda_tipi_id = ot.id
    LEFT JOIN kullanicilar k1 ON tst.talep_eden_id = k1.id
    LEFT JOIN kullanicilar k2 ON tst.atanan_teknisyen_id = k2.id
    $where_clause
    ORDER BY tst.oncelik DESC, tst.olusturma_tarihi DESC
", $params);

// Talep türleri
$talep_turleri = [
    'elektrik' => 'Elektrik',
    'su' => 'Su Tesisatı',
    'klima' => 'Klima',
    'internet' => 'İnternet',
    'tv' => 'TV',
    'telefon' => 'Telefon',
    'asansor' => 'Asansör',
    'güvenlik' => 'Güvenlik',
    'yangin' => 'Yangın',
    'diger' => 'Diğer'
];

// Öncelik seviyeleri
$oncelik_seviyeleri = [
    'dusuk' => 'Düşük',
    'normal' => 'Normal',
    'yuksek' => 'Yüksek',
    'acil' => 'Acil'
];

// Durum seviyeleri
$durum_seviyeleri = [
    'beklemede' => 'Beklemede',
    'atanmis' => 'Atanmış',
    'devam_ediyor' => 'Devam Ediyor',
    'tamamlandi' => 'Tamamlandı',
    'iptal' => 'İptal'
];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknik Servis Talep Listesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Teknik Servis Talep Listesi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="teknik-servis-dashboard.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Dashboard
                        </a>
                        <a href="teknik-servis-talep-olustur.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Yeni Talep
                        </a>
                    </div>
                </div>

                <!-- Filtreleme -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-filter"></i> Filtreleme
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Durum</label>
                                <select name="durum" class="form-select">
                                    <option value="">Tümü</option>
                                    <?php foreach ($durum_seviyeleri as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filtre_durum == $key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Talep Türü</label>
                                <select name="talep_turu" class="form-select">
                                    <option value="">Tümü</option>
                                    <?php foreach ($talep_turleri as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filtre_tur == $key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Öncelik</label>
                                <select name="oncelik" class="form-select">
                                    <option value="">Tümü</option>
                                    <?php foreach ($oncelik_seviyeleri as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $filtre_oncelik == $key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Filtrele
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Talep Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tools"></i> Teknik Servis Talepleri
                            <span class="badge bg-primary ms-2"><?php echo count($talepler); ?> Talep</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($talepler)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Filtre kriterlerinize uygun talep bulunamadı.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Talep No</th>
                                            <th>Oda</th>
                                            <th>Tür</th>
                                            <th>Başlık</th>
                                            <th>Öncelik</th>
                                            <th>Durum</th>
                                            <th>Teknisyen</th>
                                            <th>Talep Tarihi</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($talepler as $talep): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($talep['talep_no']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($talep['oda_numarasi']): ?>
                                                        <?php echo htmlspecialchars($talep['oda_numarasi'] . ' (' . $talep['oda_tipi'] . ')'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Genel</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($talep_turleri[$talep['talep_turu']] ?? $talep['talep_turu']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($talep['baslik']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $talep['oncelik'] == 'acil' ? 'danger' : 
                                                            ($talep['oncelik'] == 'yuksek' ? 'warning' : 'secondary'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($oncelik_seviyeleri[$talep['oncelik']] ?? $talep['oncelik']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $talep['durum'] == 'tamamlandi' ? 'success' : 
                                                            ($talep['durum'] == 'devam_ediyor' ? 'info' : 
                                                            ($talep['durum'] == 'beklemede' ? 'warning' : 'secondary')); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($durum_seviyeleri[$talep['durum']] ?? $talep['durum']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($talep['teknisyen_adi']): ?>
                                                        <?php echo htmlspecialchars($talep['teknisyen_adi'] . ' ' . $talep['teknisyen_soyadi']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Atanmamış</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d.m.Y H:i', strtotime($talep['olusturma_tarihi'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="teknik-servis-talep-detay.php?id=<?php echo $talep['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if (hasDetailedPermission('teknik_servis_talep_duzenle')): ?>
                                                            <a href="teknik-servis-talep-duzenle.php?id=<?php echo $talep['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>