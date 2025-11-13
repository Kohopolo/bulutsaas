<!DOCTYPE html>
<html>
<head>
    <title>Syntax Test</title>
</head>
<body>
    <h1>Ultimate Page Builder Syntax Test</h1>
    <pre>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$file = 'page-builder-ultimate.php';
$content = file_get_contents($file);

echo "Dosya boyutu: " . strlen($content) . " byte\n";
echo "Satır sayısı: " . substr_count($content, "\n") . "\n\n";

// JavaScript bloğunu bul
preg_match('/<script>(.*?)<\/script>/s', $content, $matches);

if (isset($matches[1])) {
    $js = $matches[1];
    echo "JavaScript bloğu bulundu (" . strlen($js) . " byte)\n\n";
    
    // Fonksiyonları say
    preg_match_all('/function\s+(\w+)/', $js, $funcs);
    echo "Fonksiyonlar:\n";
    foreach ($funcs[1] as $func) {
        echo "  - " . $func . "\n";
    }
    
    echo "\n";
    
    // Parantez dengesi kontrol et
    $openBraces = substr_count($js, '{');
    $closeBraces = substr_count($js, '}');
    $openParens = substr_count($js, '(');
    $closeParens = substr_count($js, ')');
    
    echo "Parantez Dengesi:\n";
    echo "  { : $openBraces\n";
    echo "  } : $closeBraces\n";
    echo "  Fark: " . ($openBraces - $closeBraces) . "\n\n";
    
    echo "  ( : $openParens\n";
    echo "  ) : $closeParens\n";
    echo "  Fark: " . ($openParens - $closeParens) . "\n\n";
    
    if ($openBraces == $closeBraces && $openParens == $closeParens) {
        echo "✅ Syntax dengeli görünüyor!\n";
    } else {
        echo "❌ Syntax DENGESIZ!\n";
    }
} else {
    echo "❌ JavaScript bloğu bulunamadı!\n";
}
?>
    </pre>
    
    <hr>
    <a href="page-builder-ultimate.php">Ultimate Builder'ı Test Et</a>
</body>
</html>


