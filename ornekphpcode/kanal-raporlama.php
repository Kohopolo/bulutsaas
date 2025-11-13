<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('kanal_raporlar', 'Kanal raporlama yetkiniz bulunmamaktadır.');

$page_title = "Kanal Raporlama Sistemi";
$current_page = "kanal-raporlama";

// Rapor parametreleri
$rapor_tipi = $_GET['rapor_tipi'] ?? 'performans';
$kanal_id = $_GET['kanal_id'] ?? '';
$baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-t');
$export_format = $_GET['export_format'] ?? 'html';

// Export işlemi
if ($_GET['action'] ?? '' === 'export') {
    $rapor_tipi = $_GET['rapor_tipi'] ?? 'performans';
    $kanal_id = $_GET['kanal_id'] ?? '';
    $baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
    $bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-t');
    $export_format = $_GET['export_format'] ?? 'pdf';
    
    // Rapor verilerini hazırla
    $rapor_verileri = getRaporVerileri($rapor_tipi, $kanal_id, $baslangic_tarihi, $bitis_tarihi);
    
    if ($export_format === 'pdf') {
        exportToPDF($rapor_verileri, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi);
    } elseif ($export_format === 'excel') {
        exportToExcel($rapor_verileri, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi);
    } elseif ($export_format === 'csv') {
        exportToCSV($rapor_verileri, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi);
    }
    exit;
}

// Kanalları getir
$kanallar = fetchAll("SELECT * FROM kanallar WHERE aktif = 1 ORDER BY kanal_adi");

// Rapor verilerini getir
$rapor_verileri = getRaporVerileri($rapor_tipi, $kanal_id, $baslangic_tarihi, $bitis_tarihi);

// Rapor verilerini hazırla
function getRaporVerileri($rapor_tipi, $kanal_id, $baslangic_tarihi, $bitis_tarihi) {
    switch ($rapor_tipi) {
        case 'performans':
            return getPerformansRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi);
        case 'komisyon':
            return getKomisyonRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi);
        case 'rezervasyon':
            return getRezervasyonRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi);
        case 'fiyat':
            return getFiyatRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi);
        default:
            return getPerformansRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi);
    }
}

// Performans raporu
function getPerformansRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi) {
    $where_conditions = ["r.giris_tarihi BETWEEN ? AND ?"];
    $params = [$baslangic_tarihi, $bitis_tarihi];
    
    if ($kanal_id) {
        $where_conditions[] = "r.kanal_id = ?";
        $params[] = $kanal_id;
    }
    
    return fetchAll("
        SELECT 
            k.kanal_adi,
            k.kanal_kodu,
            COUNT(r.id) as toplam_rezervasyon,
            SUM(r.toplam_tutar) as toplam_gelir,
            AVG(r.toplam_tutar) as ortalama_rezervasyon_tutari,
            COUNT(CASE WHEN r.durum = 'onaylandi' THEN 1 END) as onaylanan_rezervasyon,
            COUNT(CASE WHEN r.durum = 'check_in' THEN 1 END) as checkin_rezervasyon,
            COUNT(CASE WHEN r.durum = 'check_out' THEN 1 END) as checkout_rezervasyon,
            COUNT(CASE WHEN r.durum = 'iptal' THEN 1 END) as iptal_rezervasyon,
            SUM(CASE WHEN r.durum = 'onaylandi' THEN r.toplam_tutar ELSE 0 END) as onaylanan_gelir,
            SUM(CASE WHEN r.durum = 'check_in' THEN r.toplam_tutar ELSE 0 END) as checkin_gelir,
            SUM(CASE WHEN r.durum = 'check_out' THEN r.toplam_tutar ELSE 0 END) as checkout_gelir,
            ROUND((COUNT(CASE WHEN r.durum = 'iptal' THEN 1 END) / COUNT(r.id)) * 100, 2) as iptal_orani
        FROM kanallar k
        LEFT JOIN rezervasyonlar r ON k.id = r.kanal_id 
        WHERE k.aktif = 1
        " . ($kanal_id ? "AND k.id = ?" : "") . "
        AND " . implode(' AND ', $where_conditions) . "
        GROUP BY k.id
        ORDER BY toplam_gelir DESC
    ", $kanal_id ? array_merge($params, [$kanal_id]) : $params);
}

// Komisyon raporu
function getKomisyonRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi) {
    $where_conditions = ["r.giris_tarihi BETWEEN ? AND ?"];
    $params = [$baslangic_tarihi, $bitis_tarihi];
    
    if ($kanal_id) {
        $where_conditions[] = "r.kanal_id = ?";
        $params[] = $kanal_id;
    }
    
    return fetchAll("
        SELECT 
            k.kanal_adi,
            k.kanal_kodu,
            k.komisyon_orani,
            COUNT(r.id) as toplam_rezervasyon,
            SUM(r.toplam_tutar) as toplam_gelir,
            SUM((r.toplam_tutar * k.komisyon_orani) / 100) as toplam_komisyon,
            SUM(r.toplam_tutar - ((r.toplam_tutar * k.komisyon_orani) / 100)) as net_gelir,
            AVG((r.toplam_tutar * k.komisyon_orani) / 100) as ortalama_komisyon
        FROM kanallar k
        LEFT JOIN rezervasyonlar r ON k.id = r.kanal_id 
        WHERE k.aktif = 1
        " . ($kanal_id ? "AND k.id = ?" : "") . "
        GROUP BY k.id
        ORDER BY toplam_komisyon DESC
    ", $kanal_id ? array_merge($params, [$kanal_id]) : $params);
}

// Rezervasyon raporu
function getRezervasyonRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi) {
    $where_conditions = ["r.giris_tarihi BETWEEN ? AND ?"];
    $params = [$baslangic_tarihi, $bitis_tarihi];
    
    if ($kanal_id) {
        $where_conditions[] = "r.kanal_id = ?";
        $params[] = $kanal_id;
    }
    
    return fetchAll("
        SELECT 
            r.rezervasyon_no,
            r.kanal_rezervasyon_no,
            k.kanal_adi,
            m.ad_soyad as musteri_adi,
            ot.oda_tipi_adi,
            r.giris_tarihi,
            r.cikis_tarihi,
            r.yetiskin_sayisi,
            r.cocuk_sayisi,
            r.toplam_tutar,
            r.durum,
            r.olusturma_tarihi
        FROM rezervasyonlar r
        LEFT JOIN kanallar k ON r.kanal_id = k.id
        LEFT JOIN musteriler m ON r.musteri_id = m.id
        LEFT JOIN oda_tipleri ot ON r.oda_tipi_id = ot.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY r.giris_tarihi DESC
    ", $params);
}

// Fiyat raporu
function getFiyatRaporu($kanal_id, $baslangic_tarihi, $bitis_tarihi) {
    $where_conditions = ["kfsl.baslangic_tarihi BETWEEN ? AND ?"];
    $params = [$baslangic_tarihi, $bitis_tarihi];
    
    if ($kanal_id) {
        $where_conditions[] = "kfsl.kanal_id = ?";
        $params[] = $kanal_id;
    }
    
    return fetchAll("
        SELECT 
            k.kanal_adi,
            k.kanal_kodu,
            ot.oda_tipi_adi,
            kfsl.fiyat_tipi,
            kfsl.baslangic_tarihi,
            kfsl.bitis_tarihi,
            kfsl.basarili_sayisi,
            kfsl.hatali_sayisi,
            kfsl.son_senkronizasyon
        FROM kanal_fiyat_senkronizasyon_log kfsl
        JOIN kanallar k ON kfsl.kanal_id = k.id
        JOIN oda_tipleri ot ON kfsl.oda_tipi_id = ot.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY kfsl.son_senkronizasyon DESC
    ", $params);
}

// PDF export
function exportToPDF($veriler, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi) {
    // Basit HTML to PDF dönüşümü için
    $html = generateReportHTML($veriler, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="kanal_raporu_' . $rapor_tipi . '_' . date('Y-m-d') . '.pdf"');
    
    // Basit PDF oluşturma (gerçek uygulamada TCPDF veya benzeri kullanılmalı)
    echo $html;
}

// Excel export
function exportToExcel($veriler, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="kanal_raporu_' . $rapor_tipi . '_' . date('Y-m-d') . '.xls"');
    
    echo generateReportHTML($veriler, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi);
}

// CSV export
function exportToCSV($veriler, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kanal_raporu_' . $rapor_tipi . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM ekle (Excel için)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($veriler)) {
        // Başlıkları yaz
        fputcsv($output, array_keys($veriler[0]));
        
        // Verileri yaz
        foreach ($veriler as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

// Rapor HTML oluştur
function generateReportHTML($veriler, $rapor_tipi, $baslangic_tarihi, $bitis_tarihi) {
    $html = '<html><head><meta charset="UTF-8"><title>Kanal Raporu</title></head><body>';
    $html .= '<h1>Kanal Raporu - ' . ucfirst($rapor_tipi) . '</h1>';
    $html .= '<p>Tarih Aralığı: ' . $baslangic_tarihi . ' - ' . $bitis_tarihi . '</p>';
    $html .= '<p>Rapor Tarihi: ' . date('d.m.Y H:i:s') . '</p>';
    
    if (!empty($veriler)) {
        $html .= '<table border="1" cellpadding="5" cellspacing="0">';
        $html .= '<tr>';
        foreach (array_keys($veriler[0]) as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr>';
        
        foreach ($veriler as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
    } else {
        $html .= '<p>Rapor için veri bulunamadı.</p>';
    }
    
    $html .= '</body></html>';
    return $html;
}
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
        
        .rapor-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .export-btn {
            min-width: 120px;
        }
        
        .rapor-tablo {
            font-size: 0.9rem;
        }
        
        .rapor-tablo th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .rapor-ozet {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .ozet-deger {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .ozet-label {
            font-size: 0.9rem;
            opacity: 0.9;
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
                            <i class="fas fa-chart-bar me-2"></i>
                            Kanal Raporlama Sistemi
                        </h1>
                        <p class="mb-0 mt-2" style="opacity: 0.9;">
                            <i class="fas fa-clock me-2"></i>
                            <?= date('d.m.Y H:i:s') ?> - Hoş geldiniz, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="kanal-analiz.php" class="btn btn-outline-light">
                                <i class="fas fa-chart-line"></i> Analiz
                            </a>
                            <a href="kanal-performans.php" class="btn btn-outline-light">
                                <i class="fas fa-tachometer-alt"></i> Performans
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
                <!-- Rapor Filtreleme Formu -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Rapor Filtreleme
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="rapor_tipi" class="form-label">Rapor Tipi</label>
                                <select class="form-select" name="rapor_tipi" id="rapor_tipi">
                                    <option value="performans" <?= $rapor_tipi === 'performans' ? 'selected' : '' ?>>Performans Raporu</option>
                                    <option value="komisyon" <?= $rapor_tipi === 'komisyon' ? 'selected' : '' ?>>Komisyon Raporu</option>
                                    <option value="rezervasyon" <?= $rapor_tipi === 'rezervasyon' ? 'selected' : '' ?>>Rezervasyon Raporu</option>
                                    <option value="fiyat" <?= $rapor_tipi === 'fiyat' ? 'selected' : '' ?>>Fiyat Senkronizasyon Raporu</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="kanal_id" class="form-label">Kanal</label>
                                <select class="form-select" name="kanal_id" id="kanal_id">
                                    <option value="">Tüm Kanallar</option>
                                    <?php foreach ($kanallar as $kanal): ?>
                                        <option value="<?= $kanal['id'] ?>" <?= $kanal_id == $kanal['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kanal['kanal_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç</label>
                                <input type="date" class="form-control" name="baslangic_tarihi" 
                                       id="baslangic_tarihi" value="<?= $baslangic_tarihi ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="bitis_tarihi" class="form-label">Bitiş</label>
                                <input type="date" class="form-control" name="bitis_tarihi" 
                                       id="bitis_tarihi" value="<?= $bitis_tarihi ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Rapor Oluştur
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Export Butonları -->
                <?php if (!empty($rapor_verileri)): ?>
                <div class="rapor-card">
                    <h5 class="mb-3">
                        <i class="fas fa-download me-2"></i>
                        Raporu Dışa Aktar
                    </h5>
                    <div class="export-buttons">
                        <a href="?action=export&rapor_tipi=<?= $rapor_tipi ?>&kanal_id=<?= $kanal_id ?>&baslangic_tarihi=<?= $baslangic_tarihi ?>&bitis_tarihi=<?= $bitis_tarihi ?>&export_format=pdf" 
                           class="btn btn-danger export-btn">
                            <i class="fas fa-file-pdf me-2"></i>PDF
                        </a>
                        <a href="?action=export&rapor_tipi=<?= $rapor_tipi ?>&kanal_id=<?= $kanal_id ?>&baslangic_tarihi=<?= $baslangic_tarihi ?>&bitis_tarihi=<?= $bitis_tarihi ?>&export_format=excel" 
                           class="btn btn-success export-btn">
                            <i class="fas fa-file-excel me-2"></i>Excel
                        </a>
                        <a href="?action=export&rapor_tipi=<?= $rapor_tipi ?>&kanal_id=<?= $kanal_id ?>&baslangic_tarihi=<?= $baslangic_tarihi ?>&bitis_tarihi=<?= $bitis_tarihi ?>&export_format=csv" 
                           class="btn btn-info export-btn">
                            <i class="fas fa-file-csv me-2"></i>CSV
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rapor Özeti -->
                <?php if (!empty($rapor_verileri)): ?>
                <div class="rapor-ozet">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="ozet-deger"><?= count($rapor_verileri) ?></div>
                            <div class="ozet-label">Toplam Kayıt</div>
                        </div>
                        <div class="col-md-3">
                            <div class="ozet-deger">
                                <?php
                                if ($rapor_tipi === 'performans' || $rapor_tipi === 'komisyon') {
                                    echo array_sum(array_column($rapor_verileri, 'toplam_gelir'));
                                } else {
                                    echo count($rapor_verileri);
                                }
                                ?>
                            </div>
                            <div class="ozet-label">
                                <?= $rapor_tipi === 'performans' || $rapor_tipi === 'komisyon' ? 'Toplam Gelir (₺)' : 'Toplam Kayıt' ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="ozet-deger"><?= $baslangic_tarihi ?></div>
                            <div class="ozet-label">Başlangıç Tarihi</div>
                        </div>
                        <div class="col-md-3">
                            <div class="ozet-deger"><?= $bitis_tarihi ?></div>
                            <div class="ozet-label">Bitiş Tarihi</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rapor Tablosu -->
                <div class="rapor-card">
                    <h5 class="mb-3">
                        <i class="fas fa-table me-2"></i>
                        <?= ucfirst($rapor_tipi) ?> Raporu
                    </h5>
                    
                    <?php if (!empty($rapor_verileri)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped rapor-tablo">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($rapor_verileri[0]) as $header): ?>
                                            <th><?= htmlspecialchars($header) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapor_verileri as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $cell): ?>
                                                <td><?= htmlspecialchars($cell) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Seçilen kriterlere uygun veri bulunamadı.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Rapor tipi değiştiğinde formu otomatik submit et
        document.getElementById('rapor_tipi').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Kanal seçimi değiştiğinde formu otomatik submit et
        document.getElementById('kanal_id').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Tarih değişikliklerinde formu otomatik submit et
        document.getElementById('baslangic_tarihi').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('bitis_tarihi').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>
