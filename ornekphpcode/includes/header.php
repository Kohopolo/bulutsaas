<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <button type="button" id="sidebarCollapse" class="btn btn-info">
            <i class="fas fa-align-left"></i>
        </button>
        
        <div class="ms-auto d-flex align-items-center">
            <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profil.php"><i class="fas fa-user-edit me-2"></i>Profil</a></li>
                    <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Siteyi Görüntüle</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>