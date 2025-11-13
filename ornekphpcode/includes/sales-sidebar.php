<?php
// Sales kullanıcısının yetkilerini kontrol et
$permissions = getSalesPermissions($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebar" class="bg-dark">
    <div class="sidebar-header">
        <h3><i class="fas fa-chart-line me-2"></i>Sales Panel</h3>
    </div>

    <ul class="list-unstyled components">
        <!-- Dashboard -->
        <li class="<?php echo $current_page == 'sales-dashboard.php' ? 'active' : ''; ?>">
            <a href="sales-dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                Dashboard
            </a>
        </li>

        <!-- Rezervasyonlar -->
        <?php if (in_array('rezervasyon_goruntule', $permissions)): ?>
        <li class="<?php echo in_array($current_page, ['sales-rezervasyonlar.php', 'sales-rezervasyon-detay.php']) ? 'active' : ''; ?>">
            <a href="#rezervasyonSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-calendar-check me-2"></i>
                Rezervasyonlar
            </a>
            <ul class="collapse list-unstyled <?php echo in_array($current_page, ['sales-rezervasyonlar.php', 'sales-rezervasyon-detay.php', 'sales-rezervasyon-ekle.php']) ? 'show' : ''; ?>" id="rezervasyonSubmenu">
                <li class="<?php echo $current_page == 'sales-rezervasyonlar.php' ? 'active' : ''; ?>">
                    <a href="sales-rezervasyonlar.php">
                        <i class="fas fa-list me-2"></i>Rezervasyon Listesi
                    </a>
                </li>
                <?php if (in_array('rezervasyon_ekle', $permissions)): ?>
                <li class="<?php echo $current_page == 'sales-rezervasyon-ekle.php' ? 'active' : ''; ?>">
                    <a href="sales-rezervasyon-ekle.php">
                        <i class="fas fa-plus me-2"></i>Yeni Rezervasyon
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Müşteriler -->
        <?php if (in_array('musteri_goruntule', $permissions)): ?>
        <li class="<?php echo in_array($current_page, ['sales-musteriler.php', 'sales-musteri-detay.php']) ? 'active' : ''; ?>">
            <a href="#musteriSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-users me-2"></i>
                Müşteriler
            </a>
            <ul class="collapse list-unstyled <?php echo in_array($current_page, ['sales-musteriler.php', 'sales-musteri-detay.php', 'sales-musteri-ekle.php']) ? 'show' : ''; ?>" id="musteriSubmenu">
                <li class="<?php echo $current_page == 'sales-musteriler.php' ? 'active' : ''; ?>">
                    <a href="sales-musteriler.php">
                        <i class="fas fa-list me-2"></i>Müşteri Listesi
                    </a>
                </li>
                <?php if (in_array('musteri_ekle', $permissions)): ?>
                <li class="<?php echo $current_page == 'sales-musteri-ekle.php' ? 'active' : ''; ?>">
                    <a href="sales-musteri-ekle.php">
                        <i class="fas fa-user-plus me-2"></i>Yeni Müşteri
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Performans -->
        <li class="<?php echo in_array($current_page, ['sales-performans.php', 'sales-raporlar.php']) ? 'active' : ''; ?>">
            <a href="#performansSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-chart-bar me-2"></i>
                Performans & Raporlar
            </a>
            <ul class="collapse list-unstyled <?php echo in_array($current_page, ['sales-performans.php', 'sales-raporlar.php']) ? 'show' : ''; ?>" id="performansSubmenu">
                <li class="<?php echo $current_page == 'sales-performans.php' ? 'active' : ''; ?>">
                    <a href="sales-performans.php">
                        <i class="fas fa-chart-line me-2"></i>Performans Raporu
                    </a>
                </li>
                <li class="<?php echo $current_page == 'sales-raporlar.php' ? 'active' : ''; ?>">
                    <a href="sales-raporlar.php">
                        <i class="fas fa-file-alt me-2"></i>Detaylı Raporlar
                    </a>
                </li>
            </ul>
        </li>

        <!-- Profil -->
        <li class="<?php echo $current_page == 'sales-profil.php' ? 'active' : ''; ?>">
            <a href="sales-profil.php">
                <i class="fas fa-user-cog me-2"></i>
                Profil Ayarları
            </a>
        </li>

        <!-- Yardım -->
        <li class="<?php echo $current_page == 'sales-yardim.php' ? 'active' : ''; ?>">
            <a href="sales-yardim.php">
                <i class="fas fa-question-circle me-2"></i>
                Yardım & Destek
            </a>
        </li>

        <!-- Çıkış -->
        <li>
            <a href="logout.php" onclick="return confirm('Çıkış yapmak istediğinizden emin misiniz?')">
                <i class="fas fa-sign-out-alt me-2"></i>
                Çıkış Yap
            </a>
        </li>
    </ul>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="text-center text-light p-3">
            <small>
                <i class="fas fa-user-tie me-1"></i>
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                <br>
                <span class="badge bg-success">Satış Elemanı</span>
            </small>
        </div>
    </div>
</nav>

<style>
#sidebar {
    min-width: 250px;
    max-width: 250px;
    min-height: 100vh;
    transition: all 0.3s;
}

#sidebar.active {
    margin-left: -250px;
}

#sidebar .sidebar-header {
    padding: 20px;
    background: #343a40;
    border-bottom: 1px solid #495057;
}

#sidebar .sidebar-header h3 {
    color: #fff;
    margin: 0;
    font-size: 1.2rem;
}

#sidebar ul.components {
    padding: 20px 0;
}

#sidebar ul li a {
    padding: 12px 20px;
    font-size: 1rem;
    display: block;
    color: #adb5bd;
    text-decoration: none;
    transition: all 0.3s;
}

#sidebar ul li a:hover {
    color: #fff;
    background: #495057;
}

#sidebar ul li.active > a {
    color: #fff;
    background: #007bff;
}

#sidebar ul ul a {
    font-size: 0.9rem;
    padding-left: 40px;
    background: #2c3034;
}

#sidebar ul ul li.active a {
    background: #007bff;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    width: 100%;
    background: #2c3034;
    border-top: 1px solid #495057;
}

.dropdown-toggle::after {
    display: inline-block;
    margin-left: auto;
    vertical-align: 0.255em;
    content: "";
    border-top: 0.3em solid;
    border-right: 0.3em solid transparent;
    border-bottom: 0;
    border-left: 0.3em solid transparent;
}

.dropdown-toggle[aria-expanded="true"]::after {
    transform: rotate(180deg);
}
</style>