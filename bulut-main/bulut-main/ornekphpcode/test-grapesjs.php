<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrapesJS Test</title>
    
    <!-- GrapesJS Core CSS -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
    
    <style>
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }
        
        #navbar {
            background: #667eea;
            padding: 15px;
            color: white;
        }
        
        #gjs {
            height: calc(100vh - 60px);
            border: 3px solid #444;
        }
    </style>
</head>
<body>
    <div id="navbar">
        <strong>GrapesJS Test - Drag & Drop Editor</strong>
    </div>
    
    <div id="gjs">
        <h1>Merhaba!</h1>
        <p>Bu bir test sayfasıdır.</p>
    </div>
    
    <!-- GrapesJS Core JS -->
    <script src="https://unpkg.com/grapesjs"></script>
    
    <script>
        console.log('GrapesJS yükleniyor...');
        
        const editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            width: 'auto',
            storageManager: false,
            panels: { defaults: [] },
            blockManager: {
                appendTo: '#blocks',
            }
        });
        
        console.log('GrapesJS başlatıldı!', editor);
        
        // Test bloğu ekle
        editor.BlockManager.add('test-block', {
            label: 'Test Block',
            content: '<div style="padding: 20px; background: lightblue;">Test Block</div>',
            category: 'Basic'
        });
    </script>
</body>
</html>


