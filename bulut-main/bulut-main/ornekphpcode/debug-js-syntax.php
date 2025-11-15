<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$file = 'page-builder-ultimate.php';
$content = file_get_contents($file);

// Script bloğunu bul
if (preg_match('/<script>(.*?)<\/script>/s', $content, $matches)) {
    $js = $matches[1];
    
    // Satır satır kontrol et
    $lines = explode("\n", $js);
    $braceCount = 0;
    $parenCount = 0;
    $bracketCount = 0;
    
    echo "<pre>";
    echo "=== JavaScript Parantez Analizi ===\n\n";
    
    foreach ($lines as $i => $line) {
        $lineNum = $i + 1;
        
        // Parantezleri say
        $braceCount += substr_count($line, '{') - substr_count($line, '}');
        $parenCount += substr_count($line, '(') - substr_count($line, ')');
        $bracketCount += substr_count($line, '[') - substr_count($line, ']');
        
        // Kritik satırları göster
        if (strpos($line, 'function') !== false || 
            $braceCount < 0 || $parenCount < 0 || $bracketCount < 0 ||
            preg_match('/\bif\b|\bfor\b|\bwhile\b|\bcatch\b|\btry\b/', $line)) {
            
            $status = '';
            if ($braceCount < 0) $status .= ' ❌{ }';
            if ($parenCount < 0) $status .= ' ❌( )';
            if ($bracketCount < 0) $status .= ' ❌[ ]';
            
            printf("%4d: [%+2d %+2d %+2d]%s %s\n", 
                $lineNum, 
                $braceCount, 
                $parenCount, 
                $bracketCount,
                $status,
                trim(substr($line, 0, 80))
            );
        }
    }
    
    echo "\n=== SONUÇ ===\n";
    echo "{ } Dengesi: $braceCount " . ($braceCount == 0 ? '✅' : '❌') . "\n";
    echo "( ) Dengesi: $parenCount " . ($parenCount == 0 ? '✅' : '❌') . "\n";
    echo "[ ] Dengesi: $bracketCount " . ($bracketCount == 0 ? '✅' : '❌') . "\n";
    
    if ($braceCount != 0 || $parenCount != 0 || $bracketCount != 0) {
        echo "\n❌ SYNTAX HATASI VAR!\n";
        echo "Eksik: ";
        if ($braceCount > 0) echo "$braceCount adet '}', ";
        if ($braceCount < 0) echo abs($braceCount) . " fazla '}', ";
        if ($parenCount > 0) echo "$parenCount adet ')', ";
        if ($parenCount < 0) echo abs($parenCount) . " fazla ')', ";
        if ($bracketCount > 0) echo "$bracketCount adet ']'";
        if ($bracketCount < 0) echo abs($bracketCount) . " fazla ']'";
    } else {
        echo "\n✅ Syntax dengeli!\n";
    }
    
    echo "</pre>";
} else {
    echo "Script bloğu bulunamadı!";
}
?>


