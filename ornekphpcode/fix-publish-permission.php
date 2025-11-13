<?php
/**
 * Yayınlama yetkisini düzelt
 */

require_once '../config/database.php';

echo "<h2>🔧 Yayınlama Yetkisi Düzeltiliyor...</h2>";

try {
    // page_builder_publish yetkisini kontrol et
    $stmt = $pdo->prepare("SELECT * FROM detailed_permissions WHERE permission_key = 'page_builder_publish'");
    $stmt->execute();
    $permission = $stmt->fetch();
    
    if (!$permission) {
        echo "<p>❌ page_builder_publish yetkisi bulunamadı!</p>";
        
        // Yetkiyi ekle
        $stmt = $pdo->prepare("
            INSERT INTO detailed_permissions 
            (permission_key, permission_name, permission_description, category, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            'page_builder_publish',
            'Sayfa Yayınlama',
            'Sayfaları yayınlama yetkisi',
            'page_builder',
            1
        ]);
        
        echo "<p>✅ page_builder_publish yetkisi eklendi!</p>";
    } else {
        echo "<p>✅ page_builder_publish yetkisi mevcut!</p>";
    }
    
    // Kullanıcıya yetkiyi ver
    $userId = 1; // Admin kullanıcı ID'si
    
    $stmt = $pdo->prepare("
        SELECT * FROM user_detailed_permissions 
        WHERE user_id = ? AND permission_key = 'page_builder_publish'
    ");
    $stmt->execute([$userId]);
    $userPermission = $stmt->fetch();
    
    if (!$userPermission) {
        $stmt = $pdo->prepare("
            INSERT INTO user_detailed_permissions 
            (user_id, permission_key, granted_by, granted_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, 'page_builder_publish', $userId]);
        
        echo "<p>✅ Kullanıcıya page_builder_publish yetkisi verildi!</p>";
    } else {
        echo "<p>✅ Kullanıcı zaten page_builder_publish yetkisine sahip!</p>";
    }
    
    // Tüm page builder yetkilerini listele
    echo "<h3>📋 Mevcut Page Builder Yetkileri:</h3>";
    $stmt = $pdo->query("
        SELECT dp.*, 
               CASE WHEN udp.user_id IS NOT NULL THEN '✅ VAR' ELSE '❌ YOK' END as user_has_permission
        FROM detailed_permissions dp
        LEFT JOIN user_detailed_permissions udp ON dp.permission_key = udp.permission_key AND udp.user_id = 1
        WHERE dp.category = 'page_builder'
        ORDER BY dp.permission_key
    ");
    
    $permissions = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#f0f0f0;'>";
    echo "<th>Yetki</th><th>Ad</th><th>Kullanıcıda</th>";
    echo "</tr>";
    
    foreach ($permissions as $perm) {
        echo "<tr>";
        echo "<td>" . $perm['permission_key'] . "</td>";
        echo "<td>" . $perm['permission_name'] . "</td>";
        echo "<td>" . $perm['user_has_permission'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3>🎉 Düzeltme Tamamlandı!</h3>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Hata: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Yayınlama Yetkisi Düzelt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Yayınlama Yetkisi Düzeltildi!</h4>
            <p>Artık sayfaları yayınlayabilirsiniz.</p>
            <a href="page-builder-ultimate-v3.php" class="btn btn-primary">Page Builder'ı Test Et</a>
        </div>
    </div>
</body>
</html>

