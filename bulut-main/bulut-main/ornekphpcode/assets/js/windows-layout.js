/**
 * Windows Layout JavaScript
 * Otel Yönetim Sistemi - Windows 11 Style Interface
 */

// Global değişkenler
let currentModule = 'dashboard';
let openTabs = ['dashboard'];
let tabCounter = 1;
let isOnline = true;
let syncInterval;
let statusInterval;

// Sayfa yüklendiğinde
$(document).ready(function() {
    initializeWindowsLayout();
    loadDashboardData();
    startClock();
    startStatusUpdates();
    setupEventListeners();
});

// Windows Layout başlatma
function initializeWindowsLayout() {
    console.log('Windows Layout başlatılıyor...');
    
    // Modül butonlarına click event
    $('.module-btn').click(function(e) {
        e.preventDefault();
        const module = $(this).data('module');
        switchModule(module);
    });
    
    // Tab click event
    $(document).on('click', '.tab', function() {
        const tabId = $(this).data('tab');
        switchTab(tabId);
    });
    
    // İlk tab'ı aktif et
    switchTab('dashboard');
}

// Event listener'ları kur
function setupEventListeners() {
    // Klavye kısayolları
    $(document).keydown(function(e) {
        // Ctrl + Tab - Sonraki tab
        if (e.ctrlKey && e.key === 'Tab') {
            e.preventDefault();
            switchToNextTab();
        }
        
        // Ctrl + W - Tab kapat
        if (e.ctrlKey && e.key === 'w') {
            e.preventDefault();
            closeCurrentTab();
        }
        
        // F5 - Yenile
        if (e.key === 'F5') {
            e.preventDefault();
            refreshCurrentModule();
        }
    });
    
    // Window resize
    $(window).resize(function() {
        adjustLayout();
    });
}

// Modül değiştirme
function switchModule(module) {
    console.log('Modül değiştiriliyor:', module);
    
    // Aktif modülü güncelle
    $('.module-btn').removeClass('active');
    $(`.module-btn[data-module="${module}"]`).addClass('active');
    
    currentModule = module;
    
    // Tab oluştur veya mevcut tab'ı aktif et
    if (!openTabs.includes(module)) {
        addTab(module);
    } else {
        switchTab(module);
    }
    
    // İçeriği yükle
    loadModuleContent(module);
}

// Tab ekleme
function addTab(moduleId) {
    const moduleNames = {
        'dashboard': 'Dashboard',
        'reservation': 'Rezervasyon',
        'rooms': 'Odalar',
        'customers': 'Müşteriler',
        'reception': 'Resepsiyon',
        'housekeeping': 'Housekeeping',
        'fnb': 'F&B',
        'technical': 'Teknik',
        'hr': 'İK',
        'accounting': 'Muhasebe',
        'procurement': 'Satın Alma',
        'settings': 'Ayarlar'
    };
    
    const moduleIcons = {
        'dashboard': 'fas fa-tachometer-alt',
        'reservation': 'fas fa-calendar-check',
        'rooms': 'fas fa-bed',
        'customers': 'fas fa-users',
        'reception': 'fas fa-concierge-bell',
        'housekeeping': 'fas fa-broom',
        'fnb': 'fas fa-utensils',
        'technical': 'fas fa-tools',
        'hr': 'fas fa-user-tie',
        'accounting': 'fas fa-calculator',
        'procurement': 'fas fa-shopping-cart',
        'settings': 'fas fa-cog'
    };
    
    const tabHtml = `
        <div class="tab" data-tab="${moduleId}">
            <i class="${moduleIcons[moduleId]}"></i>
            <span>${moduleNames[moduleId]}</span>
            <i class="fas fa-times tab-close" onclick="closeTab('${moduleId}')"></i>
        </div>
    `;
    
    $('.add-tab-btn').before(tabHtml);
    openTabs.push(moduleId);
    switchTab(moduleId);
    
    console.log('Tab eklendi:', moduleId);
}

// Tab değiştirme
function switchTab(tabId) {
    $('.tab').removeClass('active');
    $(`.tab[data-tab="${tabId}"]`).addClass('active');
    
    currentModule = tabId;
    
    // İçeriği yükle
    loadModuleContent(tabId);
    
    console.log('Tab değiştirildi:', tabId);
}

// Tab kapatma
function closeTab(tabId) {
    if (openTabs.length <= 1) {
        showNotification('Son tab kapatılamaz!', 'warning');
        return;
    }
    
    $(`.tab[data-tab="${tabId}"]`).remove();
    openTabs = openTabs.filter(id => id !== tabId);
    
    // Eğer kapatılan tab aktifse, başka bir tab'ı aktif et
    if (currentModule === tabId) {
        const newActiveTab = openTabs[openTabs.length - 1];
        switchTab(newActiveTab);
    }
    
    console.log('Tab kapatıldı:', tabId);
}

// Yeni tab ekleme
function addNewTab() {
    // Yeni tab için modal açılabilir
    showNotification('Yeni tab ekleme özelliği geliştirilecek', 'info');
}

// Modül içeriği yükleme
function loadModuleContent(module) {
    $('#content-title').text(getModuleName(module));
    
    // Loading göster
    showLoading();
    
    // AJAX ile içerik yükle
    $.ajax({
        url: `ajax/load-module-content.php`,
        method: 'POST',
        data: {
            module: module,
            csrf_token: getCSRFToken()
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                $('#content-body').html(response.content).addClass('fade-in');
                console.log('Modül içeriği yüklendi:', module);
            } else {
                showError('İçerik yüklenemedi: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('AJAX hatası:', error);
            showError('Sunucu hatası oluştu: ' + error);
        }
    });
}

// Dashboard verilerini yükle
function loadDashboardData() {
    $.ajax({
        url: 'ajax/dashboard-stats.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                updateDashboardStats(response.data);
                loadRecentReservations();
            }
        },
        error: function() {
            console.error('Dashboard verileri yüklenemedi');
        }
    });
}

// Dashboard istatistiklerini güncelle
function updateDashboardStats(data) {
    $('#total-reservations').text(data.total_reservations);
    $('#occupied-rooms').text(data.occupied_rooms);
    $('#active-customers').text(data.active_customers);
    $('#daily-revenue').text(data.daily_revenue + '₺');
}

// Son rezervasyonları yükle
function loadRecentReservations() {
    $.ajax({
        url: 'ajax/recent-reservations.php',
        method: 'GET',
        data: { limit: 10 },
        success: function(response) {
            if (response.success) {
                updateRecentReservationsTable(response.data);
            }
        },
        error: function() {
            console.error('Son rezervasyonlar yüklenemedi');
        }
    });
}

// Son rezervasyonlar tablosunu güncelle
function updateRecentReservationsTable(reservations) {
    let html = '';
    reservations.forEach(function(reservation) {
        html += `
            <tr>
                <td>${reservation.rezervasyon_no}</td>
                <td>${reservation.musteri_adi} ${reservation.musteri_soyadi}</td>
                <td>${reservation.oda_no}</td>
                <td>${reservation.giris_tarihi}</td>
                <td>${reservation.cikis_tarihi}</td>
                <td><span class="badge bg-${getStatusColor(reservation.durum)}">${getStatusText(reservation.durum)}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewReservation(${reservation.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    $('#recent-reservations').html(html);
}

// Yardımcı fonksiyonlar
function getModuleName(module) {
    const names = {
        'dashboard': 'Dashboard',
        'reservation': 'Rezervasyon Yönetimi',
        'rooms': 'Oda Yönetimi',
        'customers': 'Müşteri Yönetimi',
        'reception': 'Resepsiyon',
        'housekeeping': 'Housekeeping',
        'fnb': 'Yiyecek & İçecek',
        'technical': 'Teknik Servis',
        'hr': 'İnsan Kaynakları',
        'accounting': 'Muhasebe',
        'procurement': 'Satın Alma',
        'settings': 'Sistem Ayarları'
    };
    return names[module] || module;
}

function getStatusColor(status) {
    const colors = {
        'beklemede': 'warning',
        'onaylandi': 'success',
        'check_in': 'primary',
        'check_out': 'secondary',
        'iptal': 'danger'
    };
    return colors[status] || 'secondary';
}

function getStatusText(status) {
    const texts = {
        'beklemede': 'Beklemede',
        'onaylandi': 'Onaylandı',
        'check_in': 'Check-in',
        'check_out': 'Check-out',
        'iptal': 'İptal'
    };
    return texts[status] || status;
}

function getCSRFToken() {
    return $('meta[name="csrf-token"]').attr('content') || '';
}

// Saat güncelleme
function startClock() {
    updateClock();
    setInterval(updateClock, 1000);
}

function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('tr-TR');
    $('#current-time').text('🕐 ' + timeString);
}

// Status güncelleme
function startStatusUpdates() {
    updateSyncStatus();
    statusInterval = setInterval(updateSyncStatus, 30000); // 30 saniyede bir
}

function updateSyncStatus() {
    $.ajax({
        url: 'ajax/check-sync-status.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                updateStatusIndicators(response);
            }
        },
        error: function() {
            // Offline durumu
            $('#sync-status').removeClass('offline').addClass('offline');
            $('#sync-text').text('❌ Offline');
            isOnline = false;
        }
    });
}

function updateStatusIndicators(response) {
    if (response.online) {
        $('#sync-status').removeClass('offline');
        $('#sync-text').text('✅ Online');
        $('#backup-status').removeClass('offline');
        $('#backup-text').text('✅ Aktif');
        isOnline = true;
    } else {
        $('#sync-status').addClass('offline');
        $('#sync-text').text('❌ Offline');
        $('#backup-status').addClass('offline');
        $('#backup-text').text('❌ Pasif');
        isOnline = false;
    }
}

// Kullanıcı menüsü
function showUserMenu() {
    // Kullanıcı menüsü modal'ı açılabilir
    showNotification('Kullanıcı menüsü geliştiriliyor', 'info');
}

// Rezervasyon görüntüleme
function viewReservation(id) {
    // Rezervasyon detay sayfasını yeni tab'da aç
    const tabId = 'reservation-detail-' + id;
    if (!openTabs.includes(tabId)) {
        addTab(tabId);
    } else {
        switchTab(tabId);
    }
}

// Loading göster/gizle
function showLoading() {
    $('#content-body').html(`
        <div class="text-center py-5">
            <div class="spinner"></div>
            <p class="mt-3 text-muted">Yükleniyor...</p>
        </div>
    `);
}

function hideLoading() {
    // Loading otomatik olarak içerik yüklendiğinde gizlenir
}

// Notification göster
function showNotification(message, type = 'info') {
    const alertClass = {
        'success': 'alert-success',
        'warning': 'alert-warning',
        'error': 'alert-danger',
        'info': 'alert-info'
    }[type] || 'alert-info';
    
    const notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(notification);
    
    // 5 saniye sonra otomatik kapat
    setTimeout(function() {
        notification.alert('close');
    }, 5000);
}

// Error göster
function showError(message) {
    showNotification(message, 'error');
}

// Layout ayarlama
function adjustLayout() {
    // Responsive ayarlamalar
    const windowWidth = $(window).width();
    
    if (windowWidth < 768) {
        // Mobil görünüm
        $('.modules-menu').addClass('mobile-view');
    } else {
        // Desktop görünüm
        $('.modules-menu').removeClass('mobile-view');
    }
}

// Tab yönetimi
function switchToNextTab() {
    const currentIndex = openTabs.indexOf(currentModule);
    const nextIndex = (currentIndex + 1) % openTabs.length;
    switchTab(openTabs[nextIndex]);
}

function closeCurrentTab() {
    closeTab(currentModule);
}

function refreshCurrentModule() {
    loadModuleContent(currentModule);
}

// Utility fonksiyonlar
function formatCurrency(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY'
    }).format(amount);
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('tr-TR');
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString('tr-TR');
}

// Export fonksiyonları (global erişim için)
window.switchModule = switchModule;
window.addTab = addTab;
window.closeTab = closeTab;
window.viewReservation = viewReservation;
window.showUserMenu = showUserMenu;
window.addNewTab = addNewTab;

// Console log
console.log('Windows Layout JavaScript yüklendi');
