/**
 * SaaS 2026 - Admin Panel Özel JavaScript
 * Modern & İnteraktif Özellikler
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Hoş geldiniz animasyonu
    const welcomeSign = document.querySelector('.welcome-sign');
    if (welcomeSign) {
        welcomeSign.style.opacity = '0';
        welcomeSign.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            welcomeSign.style.transition = 'all 0.8s ease';
            welcomeSign.style.opacity = '1';
            welcomeSign.style.transform = 'translateY(0)';
        }, 300);
    }
    
    // Kart hover efektleri
    const cards = document.querySelectorAll('.card, .small-box, .info-box');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s ease';
        });
    });
    
    // Form validasyon iyileştirmeleri
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
                
                // 10 saniye sonra tekrar aktif et (timeout için)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Kaydet';
                }, 10000);
            }
        });
    });
    
    // Başarı mesajlarını otomatik kapat
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.transition = 'all 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }, 5000);
        }
    });
    
    // Tablo satırlarına tıklanabilirlik ekle
    const tableRows = document.querySelectorAll('.results tbody tr');
    tableRows.forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function(e) {
            // Checkbox veya link tıklanmadıysa
            if (!e.target.closest('input') && !e.target.closest('a')) {
                const link = this.querySelector('th a, td a');
                if (link) {
                    link.click();
                }
            }
        });
    });
    
    // Sidebar menü animasyonları
    const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
    sidebarLinks.forEach((link, index) => {
        link.style.opacity = '0';
        link.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            link.style.transition = 'all 0.5s ease';
            link.style.opacity = '1';
            link.style.transform = 'translateX(0)';
        }, 100 + (index * 50));
    });
    
    // Sayfa yüklenme animasyonu
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s ease';
        document.body.style.opacity = '1';
    }, 100);
    
    // Tooltip'leri aktif et
    const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
    
    // Buton loading efektleri
    const actionButtons = document.querySelectorAll('.btn-primary, .btn-success, .btn-danger');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            if (!this.classList.contains('disabled') && this.type !== 'button') {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + originalText;
                this.disabled = true;
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 3000);
            }
        });
    });
    
    // Dashboard istatistiklerini canlandır
    const statNumbers = document.querySelectorAll('.info-box-number, .small-box h3');
    statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        if (!isNaN(finalValue)) {
            let currentValue = 0;
            const increment = finalValue / 50;
            const timer = setInterval(() => {
                currentValue += increment;
                if (currentValue >= finalValue) {
                    stat.textContent = finalValue;
                    clearInterval(timer);
                } else {
                    stat.textContent = Math.floor(currentValue);
                }
            }, 20);
        }
    });
    
    // Arama kutusu otomatik focus
    const searchInput = document.querySelector('#searchbar');
    if (searchInput) {
        // Ctrl+K ile arama kutusuna focus
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
        });
    }
    
    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    console.log('🚀 SaaS 2026 Admin Panel yüklendi!');
});

// Sayfa çıkışı uyarısı (form değişikliklerinde)
let formChanged = false;
document.addEventListener('change', function(e) {
    if (e.target.closest('form')) {
        formChanged = true;
    }
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
        return 'Kaydedilmemiş değişiklikler var. Çıkmak istediğinize emin misiniz?';
    }
});

// Form submit edildiğinde uyarıyı kapat
document.addEventListener('submit', function() {
    formChanged = false;
});


