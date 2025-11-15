/**
 * SaaS 2026 - VB Layout JavaScript
 * VB tarzı masaüstü uygulama davranışları
 */

(function() {
    'use strict';
    
    // ================================
    // Canlı Saat (Status Bar)
    // ================================
    function updateClock() {
        const clockElement = document.getElementById('clock');
        if (clockElement) {
            const now = new Date();
            const timeString = now.toLocaleTimeString('tr-TR');
            const dateString = now.toLocaleDateString('tr-TR');
            clockElement.textContent = `${dateString} ${timeString}`;
        }
    }
    
    // Sayfa yüklendiğinde başlat
    document.addEventListener('DOMContentLoaded', function() {
        updateClock();
        setInterval(updateClock, 1000);
    });
    
    // ================================
    // Menü Aktivasyonu
    // ================================
    document.addEventListener('DOMContentLoaded', function() {
        const menuItems = document.querySelectorAll('.menu-item');
        
        menuItems.forEach(function(item) {
            item.addEventListener('click', function(e) {
                // Eğer menü öğesi link içeriyorsa, normal davranışı engelleme
                if (!this.querySelector('a')) {
                    e.preventDefault();
                }
                
                // Tüm aktif sınıfları kaldır
                menuItems.forEach(function(mi) {
                    mi.classList.remove('active');
                });
                
                // Bu öğeyi aktif yap
                this.classList.add('active');
            });
        });
        
        // Mevcut URL'ye göre aktif menüyü belirle
        const currentPath = window.location.pathname;
        menuItems.forEach(function(item) {
            const link = item.querySelector('a');
            if (link && link.getAttribute('href') === currentPath) {
                item.classList.add('active');
            }
        });
    });
    
    // ================================
    // DataGrid Satır Seçimi
    // ================================
    document.addEventListener('DOMContentLoaded', function() {
        const datagridRows = document.querySelectorAll('.datagrid tbody tr');
        
        datagridRows.forEach(function(row) {
            row.addEventListener('click', function() {
                // Diğer seçilmiş satırları temizle
                datagridRows.forEach(function(r) {
                    r.classList.remove('selected');
                });
                
                // Bu satırı seç
                this.classList.add('selected');
            });
        });
    });
    
    // ================================
    // Mobile Sidebar Toggle
    // ================================
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle button oluştur (mobile için)
        if (window.innerWidth <= 768) {
            const toolbar = document.querySelector('.toolbar');
            if (toolbar) {
                const toggleButton = document.createElement('button');
                toggleButton.className = 'toolbar-button sidebar-toggle';
                toggleButton.innerHTML = '<span>☰</span> Menü';
                toggleButton.addEventListener('click', function() {
                    const sidebar = document.querySelector('.sidebar');
                    if (sidebar) {
                        sidebar.classList.toggle('open');
                    }
                });
                toolbar.insertBefore(toggleButton, toolbar.firstChild);
            }
        }
        
        // Ekran boyutu değiştiğinde kontrol et
        window.addEventListener('resize', function() {
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (window.innerWidth > 768) {
                if (sidebar) sidebar.classList.remove('open');
                if (sidebarToggle) sidebarToggle.style.display = 'none';
            } else {
                if (sidebarToggle) sidebarToggle.style.display = 'flex';
            }
        });
    });
    
    // ================================
    // Form Validation (VB Tarzı)
    // ================================
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form[data-vb-validate]');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');
                
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#d13438';
                        
                        // Hata mesajı göster (VB MessageBox tarzı)
                        if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('error-message')) {
                            const errorMsg = document.createElement('div');
                            errorMsg.className = 'error-message';
                            errorMsg.style.color = '#d13438';
                            errorMsg.style.fontSize = '12px';
                            errorMsg.style.marginTop = '5px';
                            errorMsg.textContent = 'Bu alan zorunludur.';
                            field.parentNode.insertBefore(errorMsg, field.nextSibling);
                        }
                    } else {
                        field.style.borderColor = '#adadad';
                        const errorMsg = field.nextElementSibling;
                        if (errorMsg && errorMsg.classList.contains('error-message')) {
                            errorMsg.remove();
                        }
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Lütfen tüm zorunlu alanları doldurun.');
                }
            });
        });
    });
    
    // ================================
    // Confirm Delete (VB MessageBox Tarzı)
    // ================================
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
        
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm-delete') || 'Bu öğeyi silmek istediğinizden emin misiniz?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    });
    
    // ================================
    // Auto-save Indicator (VB Tarzı)
    // ================================
    window.showSaveIndicator = function(message, type) {
        message = message || 'Kaydedildi';
        type = type || 'success';
        
        const statusBar = document.querySelector('.statusbar');
        if (statusBar) {
            const indicator = document.createElement('div');
            indicator.className = 'status-item save-indicator';
            indicator.style.animation = 'fadeIn 0.3s';
            
            const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');
            indicator.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
            
            statusBar.querySelector('.statusbar-section').appendChild(indicator);
            
            setTimeout(function() {
                indicator.style.animation = 'fadeOut 0.3s';
                setTimeout(function() {
                    indicator.remove();
                }, 300);
            }, 3000);
        }
    };
    
    // ================================
    // Keyboard Shortcuts (VB Tarzı)
    // ================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+S - Kaydet
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const saveButton = document.querySelector('[data-action="save"]');
            if (saveButton) {
                saveButton.click();
            }
        }
        
        // Ctrl+N - Yeni
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            const newButton = document.querySelector('[data-action="new"]');
            if (newButton) {
                newButton.click();
            }
        }
        
        // F5 - Yenile
        if (e.key === 'F5' && !e.ctrlKey) {
            // Varsayılan tarayıcı yenileme yerine custom yenileme
            const refreshButton = document.querySelector('[data-action="refresh"]');
            if (refreshButton) {
                e.preventDefault();
                refreshButton.click();
            }
        }
        
        // ESC - İptal / Kapat
        if (e.key === 'Escape') {
            const modal = document.querySelector('.modal.open');
            if (modal) {
                modal.classList.remove('open');
            }
        }
    });
    
    // ================================
    // Utility Functions
    // ================================
    
    // Console'a güzel bir başlangıç mesajı
    console.log('%cSaaS 2026', 'color: #0078d4; font-size: 24px; font-weight: bold;');
    console.log('%cVisual Basic Tarzı Layout Aktif', 'color: #107c10; font-size: 14px;');
    console.log('DESIGN_STANDARD.md dosyasına göre geliştirilmiştir.');
    
})();



