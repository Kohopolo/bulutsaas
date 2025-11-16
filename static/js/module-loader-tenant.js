/**
 * Module Loader - Tenant Modül sayfalarını center panelde yükler
 */

// Modül URL mapping - Tenant URL'leri (Django URL reverse kullanılabilir)
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
    'hotels/settings': '/hotels/settings/',
    'hotels/settings/regions': '/hotels/settings/regions/',
    'hotels/settings/cities': '/hotels/settings/cities/',
    'hotels/settings/hotel-types': '/hotels/settings/hotel-types/',
    'hotels/settings/room-types': '/hotels/settings/room-types/',
    'hotels/settings/board-types': '/hotels/settings/board-types/',
    'hotels/settings/bed-types': '/hotels/settings/bed-types/',
    'hotels/settings/room-features': '/hotels/settings/room-features/',
    'hotels/settings/hotel-features': '/hotels/settings/hotel-features/',
    'hotels/extra-services': '/hotels/extra-services/',
    'hotels/room-numbers': '/hotels/room-numbers/',
    'hotels/settings/floors': '/hotels/settings/floors/',
    'hotels/settings/blocks': '/hotels/settings/blocks/',
    'hotels/reports/usage': '/hotels/reports/usage/',
    
    // Resepsiyon
    'reception': '/reception/',
    'reception/reservations': '/reception/reservations/',
    'reception/reservations/archived': '/reception/reservations/archived/',
    'reception/voucher-templates': '/reception/voucher-templates/',
    'reception/room-plan': '/reception/room-plan/',
    'reception/room-status': '/reception/room-status/',
    'reception/room-status-dashboard': '/reception/room-status-dashboard/',
    'reception/room-calendar': '/reception/room-calendar/',
    'reception/end-of-day': '/reception/end-of-day/',
    'reception/price-calculator': '/reception/price-calculator/',
    
    // Kat Hizmetleri
    'housekeeping': '/housekeeping/',
    
    // Teknik Servis
    'technical_service': '/technical-service/',
    
    // Kalite Kontrol
    'quality_control': '/quality-control/',
    
    // Tur Yönetimi
    'tours': '/tours/',
    'tours/list': '/tours/',
    'tours/form': '/tours/create/',
    'tours/reservations': '/tours/reservations/',
    'tours/waiting-list': '/tours/waiting-list/',
    'tours/regions': '/tours/regions/',
    'tours/locations': '/tours/locations/',
    'tours/cities': '/tours/cities/',
    'tours/types': '/tours/types/',
    'tours/voucher-templates': '/tours/voucher-templates/',
    'tours/customers': '/tours/customers/',
    'tours/agencies': '/tours/agencies/',
    'tours/campaigns': '/tours/campaigns/',
    'tours/promo-codes': '/tours/promo-codes/',
    'tours/operations': '/tours/operations/',
    'tours/operations/guides': '/tours/operations/guides/',
    'tours/operations/vehicles': '/tours/operations/vehicles/',
    'tours/operations/hotels': '/tours/operations/hotels/',
    'tours/operations/transfers': '/tours/operations/transfers/',
    'tours/notification-templates': '/tours/notification-templates/',
    'tours/reports': '/tours/reports/',
    
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
    
    // Operasyon
    'sales': '/sales/',
    'staff': '/staff/',
    'channel_management': '/channel-management/',
    
    // Ödeme Yönetimi
    'payment_management': '/payment-management/',
    
    // Feribot Bileti
    'ferry_tickets': '/ferry-tickets/',
    'ferry_tickets/tickets': '/ferry-tickets/tickets/',
    'ferry_tickets/tickets/archived': '/ferry-tickets/tickets/archived/',
    'ferry_tickets/voucher-templates': '/ferry-tickets/voucher-templates/',
    'ferry_tickets/ferries': '/ferry-tickets/ferries/',
    'ferry_tickets/routes': '/ferry-tickets/routes/',
    'ferry_tickets/schedules': '/ferry-tickets/schedules/',
    'ferry_tickets/api-configurations': '/ferry-tickets/api-configurations/',
    'ferry_tickets/api-syncs': '/ferry-tickets/api-syncs/',
    
    // Bungalov Yönetimi
    'bungalovs': '/bungalovs/',
    'bungalovs/bungalovs': '/bungalovs/bungalovs/',
    'bungalovs/types': '/bungalovs/types/',
    'bungalovs/features': '/bungalovs/features/',
    'bungalovs/reservations': '/bungalovs/reservations/',
    'bungalovs/voucher-templates': '/bungalovs/voucher-templates/',
    'bungalovs/cleanings': '/bungalovs/cleanings/',
    'bungalovs/maintenances': '/bungalovs/maintenances/',
    'bungalovs/equipments': '/bungalovs/equipments/',
    'bungalovs/prices': '/bungalovs/prices/',
    
    // Raporlar
    'reports': '/reports/',
    'reports/dashboard': '/reports/',
    
    // Sistem
    'users': '/users/',
    'roles': '/roles/',
    'permissions': '/permissions/',
    'settings': '/settings/',
    'subscriptions': '/subscriptions/',
    'backup': '/backup/',
    'ai': '/ai/',
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
        // Content-Type kontrolü - JSON response olabilir
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.error || 'Modül yüklenemedi.');
                }
                throw new Error(data.error || 'Beklenmeyen JSON response.');
            });
        }
        
        if (!response.ok) {
            if (response.status === 403) {
                throw new Error('Bu modüle erişim yetkiniz bulunmamaktadır.');
            } else if (response.status === 404) {
                throw new Error('Modül bulunamadı. Modül aktif olmayabilir.');
            } else {
                throw new Error(`HTTP hatası! Durum: ${response.status}`);
            }
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
        
        // Center panel için özel stil ekle (CarpetOS V2 uyumluluğu için)
        // Padding CSS'te zaten 8px olarak tanımlı
        centerPanel.style.overflowY = 'auto';
        centerPanel.style.height = '100%';
        
        // Header banner'ı güncelle
        updateHeaderBanner(moduleName);
        
        // Script taglerini çalıştır
        executeScripts(centerPanel);
        
        currentModule = moduleName;
        window.currentModule = moduleName; // Global erişim için
        
        // Event dispatch
        const event = new CustomEvent('moduleLoaded', { detail: { module: moduleName, params } });
        document.dispatchEvent(event);
    })
    .catch(error => {
        console.error('Modül yükleme hatası:', error);
        centerPanel.innerHTML = `
            <div class="welcome-message" style="padding: 40px; text-align: center;">
                <h2 style="color: #F44336; margin-bottom: 20px;">⚠️ Modül Yüklenemedi</h2>
                <p style="font-size: 11pt; margin-bottom: 10px; color: #212121;">${error.message}</p>
                <p style="font-size: 9pt; color: #757575; margin-bottom: 20px;">
                    Bu modül paketinizde aktif olmayabilir veya erişim yetkiniz bulunmayabilir.
                </p>
                <button onclick="loadModule('dashboard')" style="
                    padding: 8px 16px;
                    background-color: #1E3A8A;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 9pt;
                ">Ana Sayfaya Dön</button>
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
        'hotels/settings': 'Otel Ayarları',
        'hotels/settings/regions': 'Bölgeler',
        'hotels/settings/cities': 'Şehirler',
        'hotels/settings/hotel-types': 'Otel Türleri',
        'hotels/settings/room-types': 'Oda Tipleri',
        'hotels/settings/board-types': 'Pansiyon Tipleri',
        'hotels/settings/bed-types': 'Yatak Tipleri',
        'hotels/settings/room-features': 'Oda Özellikleri',
        'hotels/settings/hotel-features': 'Otel Özellikleri',
        'hotels/extra-services': 'Ekstra Hizmetler',
        'hotels/room-numbers': 'Oda Numaraları',
        'hotels/settings/floors': 'Kat Yönetimi',
        'hotels/settings/blocks': 'Blok Yönetimi',
        'hotels/reports/usage': 'Raporlar',
        'reception': 'Resepsiyon',
    'reception/reservations': 'Rezervasyonlar',
    'reception/reservations/archived': 'Arşivlenmiş Rezervasyonlar',
    'reception/voucher-templates': 'Voucher Şablonları',
    'reception/room-plan': 'Oda Planı',
    'reception/room-status': 'Oda Durumu',
    'reception/room-status-dashboard': 'Oda Durumu Dashboard',
    'reception/room-calendar': 'Oda Takvimi',
    'reception/end-of-day': 'Gün Sonu İşlemleri',
    'reception/price-calculator': 'Fiyat Hesaplama',
        'housekeeping': 'Kat Hizmetleri',
        'technical_service': 'Teknik Servis',
        'quality_control': 'Kalite Kontrol',
        'tours': 'Tur Yönetimi',
        'tours/reservations': 'Tur Rezervasyonları',
        'tours/waiting-list': 'Bekleme Listesi',
        'tours/regions': 'Bölgeler',
        'tours/locations': 'Lokasyonlar',
        'tours/cities': 'Şehirler',
        'tours/types': 'Tur Tipleri',
        'tours/voucher-templates': 'Voucher Şablonları',
        'tours/customers': 'Müşteriler',
        'tours/agencies': 'Aceler',
        'tours/campaigns': 'Kampanyalar',
        'tours/promo-codes': 'Promo Kodları',
        'tours/operations': 'Operasyonlar',
        'tours/operations/guides': 'Rehberler',
        'tours/operations/vehicles': 'Araçlar',
        'tours/operations/hotels': 'Otel İşletmeleri',
        'tours/operations/transfers': 'Transferler',
        'tours/notification-templates': 'Bildirim Şablonları',
        'tours/reports': 'Raporlar',
        'sales': 'Satış Yönetimi',
        'staff': 'Personel Yönetimi',
        'channel_management': 'Kanal Yönetimi',
        'finance': 'Kasa Yönetimi',
        'accounting': 'Muhasebe',
        'refunds': 'İade Yönetimi',
        'payment_management': 'Ödeme Yönetimi',
        'ferry_tickets': 'Feribot Bileti',
        'ferry_tickets/tickets': 'Biletler',
        'ferry_tickets/tickets/archived': 'Arşivlenmiş Biletler',
        'ferry_tickets/voucher-templates': 'Voucher Şablonları',
        'ferry_tickets/ferries': 'Feribotlar',
        'ferry_tickets/routes': 'Rotalar',
        'ferry_tickets/schedules': 'Seferler',
        'ferry_tickets/api-configurations': 'API Konfigürasyonları',
        'ferry_tickets/api-syncs': 'API Senkronizasyon Kayıtları',
        'bungalovs': 'Bungalov Yönetimi',
        'bungalovs/bungalovs': 'Bungalovlar',
        'bungalovs/types': 'Bungalov Tipleri',
        'bungalovs/features': 'Bungalov Özellikleri',
        'bungalovs/reservations': 'Rezervasyonlar',
        'bungalovs/voucher-templates': 'Voucher Şablonları',
        'bungalovs/cleanings': 'Temizlik Yönetimi',
        'bungalovs/maintenances': 'Bakım Yönetimi',
        'bungalovs/equipments': 'Ekipman Yönetimi',
        'bungalovs/prices': 'Fiyatlandırma',
        'reports': 'Raporlar',
        'reports/dashboard': 'Raporlar Dashboard',
        'users': 'Kullanıcı Yönetimi',
        'roles': 'Rol Yönetimi',
        'permissions': 'Yetki Yönetimi',
        'settings': 'Ayarlar',
        'subscriptions': 'Paket Yönetimi',
        'backup': 'Yedekleme Yönetimi',
        'ai': 'AI Yönetimi',
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

/**
 * Form linklerini intercept et ve center panel'e yükle
 * Event delegation kullanarak tek bir listener ile tüm linkleri handle eder
 */
let formLinkInterceptorInitialized = false;

function interceptFormLinks() {
    // Eğer zaten initialize edildiyse tekrar ekleme
    if (formLinkInterceptorInitialized) {
        return;
    }
    
    formLinkInterceptorInitialized = true;
    
    // Event delegation ile tüm linkleri handle et
    document.addEventListener('click', function(e) {
        // Tüm linkleri kontrol et (daha geniş selector)
        const link = e.target.closest('a[href]');
        
        if (!link) return;
        
        const href = link.getAttribute('href');
        if (!href) return;
        
        // Eğer link yeni sekmede açılacaksa normal davranışı koru
        if (link.target === '_blank' || e.ctrlKey || e.metaKey) {
            return;
        }
        
        // Form sayfası pattern'lerini kontrol et
        const isFormPage = href.includes('/create/') || 
                          href.includes('/edit/') || 
                          href.includes('/update/') || 
                          href.includes('/form/') || 
                          href.includes('/add/') ||
                          href.includes('/renew/') ||
                          href.includes('/upgrade/') ||
                          href.match(/\/\d+\/edit/) ||
                          href.match(/\/\d+\/update/) ||
                          href.match(/\/\d+\/form/);
        
        // Detay sayfalarını hariç tut (sadece form sayfaları)
        const isDetailPage = href.includes('/detail/') || href.includes('/view/') || href.includes('/details/');
        
        if (isFormPage && !isDetailPage) {
            e.preventDefault();
            e.stopPropagation();
            
            // Eğer link center panel'e yüklenmesi için işaretlenmişse (data-center="true")
            if (link.dataset.center === 'true' || link.classList.contains('center-link')) {
                // URL'i center panel'e yükle
                if (typeof loadUrlToCenterPanel === 'function') {
                    loadUrlToCenterPanel(href);
                }
            } else {
                // Varsayılan: Modal olarak aç
                const title = link.getAttribute('title') || 
                             link.textContent.trim() || 
                             link.querySelector('i')?.nextSibling?.textContent?.trim() ||
                             'Form';
                
                
                if (typeof openFormModal === 'function') {
                    openFormModal(href, title);
                } else {
                    console.error('openFormModal fonksiyonu bulunamadı!');
                    // Fallback: center panel'e yükle
                    if (typeof loadUrlToCenterPanel === 'function') {
                        loadUrlToCenterPanel(href);
                    }
                }
            }
        }
    }, true); // Capture phase'de çalıştır
}

/**
 * Herhangi bir URL'yi center panel'e yükle
 */
function loadUrlToCenterPanel(url) {
    const centerPanel = document.getElementById('centerPanel');
    if (!centerPanel) {
        console.error('Center panel bulunamadı!');
        window.location.href = url; // Fallback: normal sayfa yükleme
        return;
    }

    // Loading göster
    centerPanel.innerHTML = '<div class="welcome-message"><h2>Yükleniyor...</h2><p>Sayfa yükleniyor, lütfen bekleyin.</p></div>';

    // AJAX ile içeriği yükle
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            if (response.status === 403) {
                throw new Error('Bu sayfaya erişim yetkiniz bulunmamaktadır.');
            } else if (response.status === 404) {
                throw new Error('Sayfa bulunamadı.');
            } else {
                throw new Error(`HTTP hatası! Durum: ${response.status}`);
            }
        }
        return response.text();
    })
    .then(html => {
        // Gelen HTML'den sadece içerik kısmını al
        let content = html;
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const bodyContent = doc.body;
        
        if (bodyContent) {
            const mainContent = bodyContent.querySelector('main') || 
                               bodyContent.querySelector('.center-panel') ||
                               bodyContent.querySelector('#centerPanel') ||
                               bodyContent.querySelector('.content') ||
                               bodyContent.querySelector('.p-6');
            
            if (mainContent) {
                content = mainContent.innerHTML;
            } else {
                // Body içeriğini al ama sidebar ve diğer layout elementlerini hariç tut
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = bodyContent.innerHTML;
                
                // Sidebar ve layout elementlerini kaldır
                tempDiv.querySelectorAll('.left-panel, .right-panel, .menu-strip, .header-panel, .footer-panel, .sidebar, aside, .standalone-app').forEach(el => el.remove());
                
                content = tempDiv.innerHTML;
            }
        }
        
        // Center panel içeriğini güncelle
        centerPanel.innerHTML = content;
        centerPanel.style.overflowY = 'auto';
        centerPanel.style.height = '100%';
        
        // Script taglerini çalıştır
        executeScripts(centerPanel);
        
        // Sayfa yüklendiğinde scroll'u en üste al
        centerPanel.scrollTop = 0;
        
        // Event dispatch
        const event = new CustomEvent('pageLoaded', { detail: { url } });
        document.dispatchEvent(event);
    })
    .catch(error => {
        console.error('Sayfa yükleme hatası:', error);
        centerPanel.innerHTML = `
            <div class="welcome-message" style="padding: 40px; text-align: center;">
                <h2 style="color: #F44336; margin-bottom: 20px;">⚠️ Sayfa Yüklenemedi</h2>
                <p style="font-size: 11pt; margin-bottom: 10px; color: #212121;">${error.message}</p>
                <p style="font-size: 9pt; color: #757575; margin-bottom: 20px;">
                    Sayfa yüklenirken bir hata oluştu.
                </p>
                <button onclick="loadUrlToCenterPanel('${url}')" style="
                    padding: 8px 16px;
                    background-color: #1E3A8A;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 9pt;
                    margin-right: 10px;
                ">Tekrar Dene</button>
                <a href="${url}" style="
                    padding: 8px 16px;
                    background-color: #757575;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    text-decoration: none;
                    display: inline-block;
                    font-size: 9pt;
                ">Tam Sayfa Aç</a>
            </div>
        `;
    });
}

/**
 * Sayfa yüklendiğinde form linklerini intercept et
 */
document.addEventListener('DOMContentLoaded', function() {
    interceptFormLinks();
});

// Sayfa yüklendiğinde de intercept et (eğer DOMContentLoaded geçtiyse)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', interceptFormLinks);
} else {
    interceptFormLinks();
}

// Global olarak erişilebilir yap
window.loadModule = loadModule;
window.refreshCurrentModule = refreshCurrentModule;
window.showSubMenu = showSubMenu;
window.loadUrlToCenterPanel = loadUrlToCenterPanel;

