<?php
// C:\xampp\htdocs\otelonofexe\web\admin\odeme-api-dokumantasyon.php
// Ödeme API dokümantasyon sayfası

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Admin kontrolü
if (!checkAdmin()) {
    header('Location: login.php');
    exit;
}

// Detaylı yetki kontrolü
require_once '../includes/detailed_permission_functions.php';
requireDetailedPermission('odeme_api_dokumantasyon', 'Ödeme API dokümantasyon yetkiniz bulunmamaktadır.');

$page_title = 'Ödeme API Dokümantasyonu';

// API dokümantasyonu içeriği
$api_docs = [
    'overview' => [
        'title' => 'Genel Bakış',
        'content' => 'Ödeme Modülü, çoklu sanal POS sağlayıcısı desteği ile güvenli ödeme işlemleri gerçekleştirmenizi sağlar.'
    ],
    'installation' => [
        'title' => 'Kurulum',
        'content' => 'Modülün kurulumu ve yapılandırması hakkında detaylı bilgiler.'
    ],
    'basic_usage' => [
        'title' => 'Temel Kullanım',
        'content' => 'PaymentProcessor sınıfının temel kullanımı ve örnekler.'
    ],
    'api_reference' => [
        'title' => 'API Referansı',
        'content' => 'Tüm API metodları ve parametreleri hakkında detaylı bilgiler.'
    ],
    'examples' => [
        'title' => 'Örnekler',
        'content' => 'Farklı senaryolar için kod örnekleri.'
    ],
    'error_codes' => [
        'title' => 'Hata Kodları',
        'content' => 'Tüm hata kodları ve açıklamaları.'
    ],
    'security' => [
        'title' => 'Güvenlik',
        'content' => 'Güvenlik önlemleri ve en iyi uygulamalar.'
    ],
    'webhooks' => [
        'title' => 'Webhook\'lar',
        'content' => 'Webhook yapılandırması ve işleme.'
    ],
    'testing' => [
        'title' => 'Test Ortamı',
        'content' => 'Test kartları ve test ortamı yapılandırması.'
    ],
    'faq' => [
        'title' => 'SSS',
        'content' => 'Sık sorulan sorular ve cevapları.'
    ]
];

$current_section = $_GET['section'] ?? 'overview';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism.min.css" rel="stylesheet">
    <style>
        .docs-sidebar {
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
            overflow-y: auto;
        }
        .docs-content {
            line-height: 1.6;
        }
        .docs-content h1, .docs-content h2, .docs-content h3 {
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .docs-content h1 {
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
        }
        .docs-content h2 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.25rem;
        }
        .code-block {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            margin: 1rem 0;
        }
        .api-method {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin: 1rem 0;
        }
        .parameter-table {
            font-size: 0.9em;
        }
        .parameter-table th {
            background-color: #f8f9fa;
        }
        .error-code {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 0.375rem;
            padding: 0.5rem;
            margin: 0.5rem 0;
        }
        .success-code {
            background-color: #d1edff;
            border: 1px solid #74c0fc;
            border-radius: 0.375rem;
            padding: 0.5rem;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-book me-2"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printDocs()">
                                <i class="fas fa-print"></i> Yazdır
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="downloadDocs()">
                                <i class="fas fa-download"></i> İndir
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Dokümantasyon Sidebar -->
                    <div class="col-md-3">
                        <div class="docs-sidebar">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i>
                                        İçindekiler
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($api_docs as $key => $doc): ?>
                                            <a href="?section=<?php echo $key; ?>" 
                                               class="list-group-item list-group-item-action <?php echo $current_section === $key ? 'active' : ''; ?>">
                                                <i class="fas fa-<?php echo $this->getSectionIcon($key); ?> me-2"></i>
                                                <?php echo $doc['title']; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dokümantasyon İçeriği -->
                    <div class="col-md-9">
                        <div class="card">
                            <div class="card-body docs-content">
                                <?php
                                switch ($current_section) {
                                    case 'overview':
                                        include 'docs-sections/overview.php';
                                        break;
                                    case 'installation':
                                        include 'docs-sections/installation.php';
                                        break;
                                    case 'basic_usage':
                                        include 'docs-sections/basic_usage.php';
                                        break;
                                    case 'api_reference':
                                        include 'docs-sections/api_reference.php';
                                        break;
                                    case 'examples':
                                        include 'docs-sections/examples.php';
                                        break;
                                    case 'error_codes':
                                        include 'docs-sections/error_codes.php';
                                        break;
                                    case 'security':
                                        include 'docs-sections/security.php';
                                        break;
                                    case 'webhooks':
                                        include 'docs-sections/webhooks.php';
                                        break;
                                    case 'testing':
                                        include 'docs-sections/testing.php';
                                        break;
                                    case 'faq':
                                        include 'docs-sections/faq.php';
                                        break;
                                    default:
                                        include 'docs-sections/overview.php';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js"></script>
    <script>
        function printDocs() {
            window.print();
        }

        function downloadDocs() {
            window.open('docs/payment-api-documentation.md', '_blank');
        }

        // Kod bloklarını vurgula
        document.addEventListener('DOMContentLoaded', function() {
            Prism.highlightAll();
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>

<?php
// Bölüm ikonları
function getSectionIcon($section) {
    $icons = [
        'overview' => 'info-circle',
        'installation' => 'download',
        'basic_usage' => 'play-circle',
        'api_reference' => 'code',
        'examples' => 'lightbulb',
        'error_codes' => 'exclamation-triangle',
        'security' => 'shield-alt',
        'webhooks' => 'webhook',
        'testing' => 'flask',
        'faq' => 'question-circle'
    ];
    
    return $icons[$section] ?? 'file';
}
?>
