<?php
/**
 * Multi Otel Modülü Sidebar
 * Otel yönetimi için özel sidebar
 */

// Mevcut otel bilgisini al
$current_otel = getCurrentOtel();
$user_oteller = getUserOteller($_SESSION['user_id']);
?>

<!-- Multi Otel Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h5 class="text-white">
            <i class="fas fa-hotel me-2"></i>Multi Otel Yönetimi
        </h5>
        <?php if ($current_otel): ?>
        <div class="current-hotel-info">
            <small class="text-light">
                <i class="fas fa-building me-1"></i>
                <?php echo htmlspecialchars($current_otel['otel_adi']); ?>
            </small>
        </div>
        <?php endif; ?>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <!-- Otel Yönetimi -->
            <li class="nav-item">
                <a class="nav-link" href="oteller.php">
                    <i class="fas fa-building me-2"></i>Oteller
                </a>
            </li>
            
            <!-- Otel Seçimi -->
            <?php if (count($user_oteller) > 1): ?>
            <li class="nav-item">
                <div class="nav-link">
                    <i class="fas fa-exchange-alt me-2"></i>Otel Değiştir
                    <select class="form-select form-select-sm mt-2" id="otel-switcher">
                        <option value="">Otel Seçin</option>
                        <?php foreach ($user_oteller as $otel): ?>
                        <option value="<?php echo $otel['id']; ?>" 
                                <?php echo ($current_otel && $current_otel['id'] == $otel['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($otel['otel_adi']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </li>
            <?php endif; ?>
            
            <li class="nav-divider"></li>
            
            <!-- Oda Yönetimi -->
            <li class="nav-item">
                <a class="nav-link" href="oda-tipleri.php">
                    <i class="fas fa-bed me-2"></i>Oda Tipleri
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="oda-numaralari.php">
                    <i class="fas fa-door-open me-2"></i>Oda Numaraları
                </a>
            </li>
            
            <li class="nav-divider"></li>
            
            <!-- Rezervasyon Yönetimi -->
            <li class="nav-item">
                <a class="nav-link" href="rezervasyonlar.php">
                    <i class="fas fa-calendar-alt me-2"></i>Rezervasyonlar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="rezervasyon-ekle.php">
                    <i class="fas fa-plus me-2"></i>Yeni Rezervasyon
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="rezervasyon-ekle-multi.php">
                    <i class="fas fa-hotel me-2"></i>Çoklu Oda Rezervasyon
                </a>
            </li>
            
            <li class="nav-divider"></li>
            
            <!-- Müşteri Yönetimi -->
            <li class="nav-item">
                <a class="nav-link" href="../musteriler.php">
                    <i class="fas fa-users me-2"></i>Müşteriler
                </a>
            </li>
            
            <li class="nav-divider"></li>
            
            <!-- Raporlar -->
            <li class="nav-item">
                <a class="nav-link" href="raporlar.php">
                    <i class="fas fa-chart-bar me-2"></i>Raporlar
                </a>
            </li>
            
            <li class="nav-divider"></li>
            
            <!-- Ana Sisteme Dön -->
            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-arrow-left me-2"></i>Ana Sisteme Dön
                </a>
            </li>
        </ul>
    </nav>
</div>

<style>
.sidebar {
    width: 250px;
    height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    transition: all 0.3s ease;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.current-hotel-info {
    margin-top: 10px;
    padding: 8px 12px;
    background: rgba(255,255,255,0.1);
    border-radius: 6px;
    font-size: 0.85rem;
}

.sidebar-nav {
    padding: 20px 0;
}

.sidebar-nav .nav-link {
    color: rgba(255,255,255,0.8);
    padding: 12px 20px;
    border-radius: 0;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar-nav .nav-link:hover {
    color: white;
    background: rgba(255,255,255,0.1);
    border-left-color: #fff;
}

.sidebar-nav .nav-link.active {
    color: white;
    background: rgba(255,255,255,0.2);
    border-left-color: #fff;
}

.nav-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 15px 20px;
}

#otel-switcher {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
}

#otel-switcher:focus {
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.4);
    box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.25);
}

#otel-switcher option {
    background: #667eea;
    color: white;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}
</style>

<script>
// Otel değiştirici
document.getElementById('otel-switcher')?.addEventListener('change', function() {
    const otelId = this.value;
    if (otelId) {
        // AJAX ile otel değiştir
        fetch('ajax/switch-hotel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'otel_id=' + otelId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Otel değiştirilemedi: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Bir hata oluştu');
        });
    }
});
</script>
