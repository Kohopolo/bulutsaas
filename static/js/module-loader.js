/**
 * Module Loader - Modül sayfalarını center panelde yükler
 */

// Modül URL mapping - Tenant URL'leri
const moduleUrls = {
    // Genel
    'dashboard': '/',
    
    // Müşteri Yönetimi
    'customers': '/customers/',
    'customers/list': '/customers/',
    'customers/form': '/customers/create/',
    
    // Otel Yönetimi
    'hotels': '/hotels/hotels/',
    'hotels/list': '/hotels/hotels/',
    'hotels/form': '/hotels/hotels/create/',
    'hotels/rooms': '/hotels/rooms/',
    'hotels/rooms/list': '/hotels/rooms/',
    'hotels/rooms/form': '/hotels/rooms/create/',
    
    // Resepsiyon
    'reception': '/reception/',
    'reception/reservations': '/reception/reservations/',
    
    // Kat Hizmetleri
    'housekeeping': '/housekeeping/',
    
    // Tur Yönetimi
    'tours': '/tours/',
    'tours/list': '/tours/',
    'tours/form': '/tours/create/',
    'tours/reservations': '/tours/reservations/',
    
    // Finans
    'finance': '/finance/accounts/',
    'finance/accounts': '/finance/accounts/',
    'finance/transactions': '/finance/transactions/',
    'finance/cash-flow': '/finance/cash-flow/',
    
    // Muhasebe
    'accounting': '/accounting/accounts/',
    'accounting/accounts': '/accounting/accounts/',
    'accounting/journal-entries': '/accounting/journal-entries/',
    'accounting/invoices': '/accounting/invoices/',
    'accounting/payments': '/accounting/payments/',
    
    // İadeler
    'refunds': '/refunds/requests/',
    'refunds/requests': '/refunds/requests/',
    'refunds/policies': '/refunds/policies/',
    
    // Raporlar
    'reports': '/reports/',
    
    // Sistem
    'users': '/users/',
    'roles': '/roles/',
    'permissions': '/permissions/',
    'settings': '/settings/',
};

// Aktif modül
let currentModule = null;

/**
 * Modül yükle
 * @param {string} moduleName - Modül adı
 * @param {object} params - Ek parametreler (id, action vb.)
 */
function loadModule(moduleName, params = {}) {
    const centerPanel = document.getElementById('centerPanel');
    if (!centerPanel) {
        console.error('Center panel bulunamadı!');
        return;
    }

    // Aktif modül işaretlemesini güncelle
    updateActiveModule(moduleName);

    // Loading göster
    centerPanel.innerHTML = '<div class="welcome-message"><h2>Yükleniyor...</h2><p>Modül yükleniyor, lütfen bekleyin.</p></div>';

    // URL oluştur
    let url = moduleUrls[moduleName] || `/${moduleName}/`;
    
    // Parametreleri ekle
    if (params.id) {
        url += `${params.id}/`;
    }
    if (params.action) {
        url += `${params.action}/`;
    }

    // AJAX ile modül içeriğini yükle
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(html => {
        // Gelen HTML'den sadece içerik kısmını al
        // Eğer tam sayfa HTML'i geliyorsa, sadece body içeriğini veya belirli container'ı al
        let content = html;
        
        // Eğer HTML'de body tag'i varsa, sadece body içeriğini al
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Body içinde center-panel veya main-content gibi bir container var mı kontrol et
        const bodyContent = doc.body;
        if (bodyContent) {
            // Eğer body içinde center-panel veya main tag'i varsa onu al
            const mainContent = bodyContent.querySelector('main') || 
                               bodyContent.querySelector('.center-panel') ||
                               bodyContent.querySelector('#centerPanel') ||
                               bodyContent.querySelector('.content');
            
            if (mainContent) {
                content = mainContent.innerHTML;
            } else {
                // Body içeriğini al ama sidebar ve diğer layout elementlerini hariç tut
                const bodyHTML = bodyContent.innerHTML;
                // Sidebar, menu-strip, header-panel, footer-panel gibi elementleri kaldır
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = bodyHTML;
                
                // Sidebar ve layout elementlerini kaldır
                tempDiv.querySelectorAll('.left-panel, .right-panel, .menu-strip, .header-panel, .footer-panel, .sidebar, aside').forEach(el => el.remove());
                
                content = tempDiv.innerHTML;
            }
        }
        
        // Center panel içeriğini güncelle (sadece içerik)
        centerPanel.innerHTML = content;
        
        // Header banner'ı güncelle
        updateHeaderBanner(moduleName);
        
        // Script taglerini çalıştır
        executeScripts(centerPanel);
        
        currentModule = moduleName;
        
        // Event dispatch
        const event = new CustomEvent('moduleLoaded', { detail: { module: moduleName, params } });
        document.dispatchEvent(event);
    })
    .catch(error => {
        console.error('Modül yükleme hatası:', error);
        centerPanel.innerHTML = `
            <div class="welcome-message">
                <h2 style="color: #F44336;">Hata!</h2>
                <p>Modül yüklenirken bir hata oluştu.</p>
                <p style="font-size: 8pt; color: #757575;">${error.message}</p>
                <button class="btn btn-primary mt-10" onclick="loadModule('${moduleName}')">Tekrar Dene</button>
            </div>
        `;
    });
}

/**
 * Aktif modül işaretlemesini güncelle
 */
function updateActiveModule(moduleName) {
    // Tüm modül item'larından active class'ını kaldır
    document.querySelectorAll('.module-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // İlgili modül item'ını bul ve active yap
    const moduleItem = Array.from(document.querySelectorAll('.module-item')).find(item => {
        return item.getAttribute('onclick') && item.getAttribute('onclick').includes(moduleName);
    });
    
    if (moduleItem) {
        moduleItem.classList.add('active');
    }
}

/**
 * Header banner'ı güncelle
 */
function updateHeaderBanner(moduleName) {
    const headerBanner = document.getElementById('headerBanner');
    if (!headerBanner) return;
    
    const moduleNames = {
        'dashboard': 'Ana Sayfa',
        'customers': 'Müşteri Yönetimi',
        'hotels': 'Otel Yönetimi',
        'hotels/rooms': 'Oda Yönetimi',
        'reception': 'Resepsiyon',
        'housekeeping': 'Kat Hizmetleri',
        'tours': 'Tur Yönetimi',
        'tours/reservations': 'Tur Rezervasyonları',
        'finance': 'Kasa Yönetimi',
        'accounting': 'Muhasebe',
        'refunds': 'İade Yönetimi',
        'reports': 'Raporlar',
        'users': 'Kullanıcı Yönetimi',
        'roles': 'Rol Yönetimi',
        'permissions': 'Yetki Yönetimi',
        'settings': 'Ayarlar',
    };
    
    headerBanner.textContent = moduleNames[moduleName] || 'Sistem Hazır';
}

/**
 * Script taglerini çalıştır
 */
function executeScripts(container) {
    const scripts = container.querySelectorAll('script');
    scripts.forEach(oldScript => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => {
            newScript.setAttribute(attr.name, attr.value);
        });
        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
        oldScript.parentNode.replaceChild(newScript, oldScript);
    });
}

/**
 * Mevcut modülü yenile
 */
function refreshCurrentModule() {
    if (currentModule) {
        loadModule(currentModule);
    } else {
        location.reload();
    }
}

/**
 * Sub menu göster
 */
function showSubMenu(moduleName) {
    // Şimdilik direkt modülü yükle
    loadModule(moduleName);
}

// Global olarak erişilebilir yap
window.loadModule = loadModule;
window.refreshCurrentModule = refreshCurrentModule;
window.showSubMenu = showSubMenu;

