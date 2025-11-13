<?php
// Ultimate page builder dosyasını oku
$content = file_get_contents('page-builder-ultimate.php');

// Script bloğunu bul ve değiştir
$pattern = '/<script>(.*?)<\/script>/s';
if (preg_match($pattern, $content, $matches)) {
    $oldScript = $matches[0];
    
    // Yeni temiz script
    $newScript = <<<'EOT'
<script>
        console.log('🚀 Ultimate Page Builder başlatılıyor...');
        
        const pageId = <?php echo $pageId ? $pageId : 'null'; ?>;
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        let editor;
        
        // Ana başlatma fonksiyonu
        function initPageBuilder() {
            if (typeof grapesjs === 'undefined') {
                showToast('error', 'GrapesJS yüklenemedi!');
                return;
            }
            
            console.log('✅ GrapesJS başlatılıyor...');
            
            try {
                editor = grapesjs.init({
                    container: '#gjs',
                    height: '100%',
                    width: 'auto',
                    storageManager: false,
                    canvas: {
                        styles: [
                            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
                            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
                            'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css'
                        ],
                        scripts: [
                            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
                        ]
                    },
                    deviceManager: {
                        devices: [{
                            name: 'Desktop',
                            width: ''
                        }, {
                            name: 'Tablet',
                            width: '768px'
                        }, {
                            name: 'Mobil',
                            width: '375px'
                        }]
                    }
                });
                
                console.log('✅ GrapesJS başarıyla başlatıldı!');
                
                // Blokları ekle
                addUltimateBlocks();
                
                // Mevcut içeriği yükle
                <?php if ($pageData): ?>
                    editor.setComponents(<?php echo json_encode($pageData['content_html'] ?? ''); ?>);
                    editor.setStyle(<?php echo json_encode($pageData['content_css'] ?? ''); ?>);
                    showToast('success', 'İçerik yüklendi');
                <?php endif; ?>
                
                showToast('success', 'Page Builder hazır! 🚀');
                
            } catch (error) {
                console.error('Başlatma hatası:', error);
                showToast('error', 'Editor başlatılamadı: ' + error.message);
            }
        }
    </script>
EOT;
    
    // Değiştir
    $newContent = str_replace($oldScript, $newScript, $content);
    file_put_contents('page-builder-ultimate-fixed.php', $newContent);
    
    echo "✅ Script bloğu temizlendi!<br>";
    echo "Yeni dosya: page-builder-ultimate-fixed.php<br>";
    echo "<a href='page-builder-ultimate-fixed.php'>Test Et</a>";
} else {
    echo "❌ Script bloğu bulunamadı!";
}
?>


