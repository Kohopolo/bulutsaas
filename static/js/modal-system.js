/**
 * Modal System - Form modallarını popup olarak açar
 */

/**
 * Modal aç
 * @param {string} title - Modal başlığı
 * @param {string} content - Modal içeriği (HTML)
 * @param {object} options - Modal seçenekleri
 */
function openModal(title, content, options = {}) {
    const modalContainer = document.getElementById('modalContainer');
    if (!modalContainer) {
        console.error('Modal container bulunamadı!');
        return;
    }

    const {
        width = '90%',
        maxWidth = '900px',
        height = 'auto',
        onClose = null,
        onSave = null,
        showFooter = false, // Form sayfalarında footer gösterme, form kendi butonlarını içeriyor
        footerButtons = null
    } = options;

    // Modal HTML oluştur
    const modalHTML = `
        <div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
            <div class="modal-content" style="width: ${width}; max-width: ${maxWidth}; max-height: ${height};" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <div class="modal-title">${title}</div>
                    <button type="button" class="modal-close" onclick="closeModal()">×</button>
                </div>
                <div class="modal-body" id="modalBody">
                    ${content}
                </div>
                ${showFooter ? `
                    <div class="modal-footer" id="modalFooter">
                        ${footerButtons || `
                            <button type="button" class="btn" onclick="closeModal()">İptal</button>
                            ${onSave ? `<button type="button" class="btn btn-primary" onclick="saveModal()">Kaydet</button>` : ''}
                        `}
                    </div>
                ` : ''}
            </div>
        </div>
    `;

    // Modal'ı göster
    modalContainer.innerHTML = modalHTML;
    
    // Modal'ı açık yap
    const modal = modalContainer.querySelector('.modal-overlay');
    if (modal) {
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
    }
    
    // Body scroll'u kapat
    document.body.style.overflow = 'hidden';
    
    // Event listener'ları ekle
    if (onSave) {
        window.saveModal = function() {
            onSave();
        };
    }
    
    // ESC tuşu ile kapat
    document.addEventListener('keydown', handleEscKey);
}

/**
 * Modal kapat
 */
function closeModal(event = null) {
    // Overlay'e tıklanmışsa ve modal içeriğine değilse kapat
    if (event && event.target.id === 'modalOverlay') {
        // Modal'ı kapat
        const modalContainer = document.getElementById('modalContainer');
        if (modalContainer) {
            modalContainer.innerHTML = '';
        }
        
        // Body scroll'u aç
        document.body.style.overflow = '';
        
        // ESC event listener'ı kaldır
        document.removeEventListener('keydown', handleEscKey);
    } else if (!event) {
        // Direkt kapat
        const modalContainer = document.getElementById('modalContainer');
        if (modalContainer) {
            modalContainer.innerHTML = '';
        }
        
        // Body scroll'u aç
        document.body.style.overflow = '';
        
        // ESC event listener'ı kaldır
        document.removeEventListener('keydown', handleEscKey);
    }
}

/**
 * ESC tuşu ile modal kapat
 */
function handleEscKey(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
}

/**
 * Form modal aç (AJAX ile form yükle)
 * @param {string} url - Form URL'i
 * @param {string} title - Modal başlığı
 * @param {object} options - Modal seçenekleri
 */
function openFormModal(url, title, options = {}) {
    
    // Modal container'ı kontrol et
    const modalContainer = document.getElementById('modalContainer');
    if (!modalContainer) {
        console.error('Modal container bulunamadı!');
        alert('Modal sistemi yüklenemedi. Lütfen sayfayı yenileyin.');
        return;
    }
    
    // Loading göster
    openModal(title, '<div class="welcome-message"><p>Form yükleniyor...</p></div>', options);
    
    // Form içeriğini yükle
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
        
        // Gelen HTML'den sadece form içeriğini al
        let content = html;
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const bodyContent = doc.body;
        
        if (bodyContent) {
            // Önce form'u bul
            let formContent = bodyContent.querySelector('form');
            
            if (formContent) {
                // Form'un parent container'ını al (p-6 veya benzeri)
                const formContainer = formContent.closest('.p-6') || 
                                    formContent.closest('.content') ||
                                    formContent.closest('main') ||
                                    formContent.closest('.center-panel') ||
                                    formContent.closest('#centerPanel') ||
                                    formContent.parentElement;
                
                if (formContainer && formContainer !== formContent) {
                    content = formContainer.innerHTML;
                } else {
                    // Sadece form'u al
                    content = formContent.outerHTML;
                }
            } else {
                // Form yoksa, içerik container'ını bul
                formContent = bodyContent.querySelector('.p-6') ||
                             bodyContent.querySelector('.content') ||
                             bodyContent.querySelector('main') ||
                             bodyContent.querySelector('.center-panel') ||
                             bodyContent.querySelector('#centerPanel');
                
                if (formContent) {
                    content = formContent.innerHTML;
                } else {
                    // Body içeriğini al ama layout elementlerini kaldır
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = bodyContent.innerHTML;
                    
                    // Sidebar ve layout elementlerini kaldır
                    tempDiv.querySelectorAll('.left-panel, .right-panel, .menu-strip, .header-panel, .footer-panel, .sidebar, aside, .standalone-app').forEach(el => el.remove());
                    
                    content = tempDiv.innerHTML;
                }
            }
        }
        
        // Modal body'yi güncelle
        const modalBody = document.getElementById('modalBody');
        if (modalBody) {
            modalBody.innerHTML = content;
            
            // Script taglerini çalıştır
            executeModalScripts(modalBody);
        }
    })
    .catch(error => {
        console.error('Form yükleme hatası:', error);
        const modalBody = document.getElementById('modalBody');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="welcome-message">
                    <h2 style="color: #F44336;">Hata!</h2>
                    <p>Form yüklenirken bir hata oluştu.</p>
                    <p style="font-size: 8pt; color: #757575;">${error.message}</p>
                    <button class="btn btn-primary mt-10" onclick="openFormModal('${url}', '${title}')">Tekrar Dene</button>
                </div>
            `;
        }
    });
}

/**
 * Script taglerini çalıştır (modal içinde)
 */
function executeModalScripts(container) {
    const scripts = container.querySelectorAll('script');
    scripts.forEach(oldScript => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => {
            newScript.setAttribute(attr.name, attr.value);
        });
        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
        oldScript.parentNode.replaceChild(newScript, oldScript);
    });
    
    // Fiyatlandırma formu için özel kontrol - Kişi Çarpanı toggle
    setTimeout(function() {
        const pricingType = container.querySelector('#id_pricing_type');
        const perPersonSection = container.querySelector('#per-person-section');
        
        if (pricingType && perPersonSection) {
            function togglePerPersonSection() {
                if (pricingType.value === 'per_person') {
                    perPersonSection.style.display = 'block';
                    setTimeout(function() {
                        perPersonSection.style.opacity = '1';
                    }, 10);
                } else {
                    perPersonSection.style.opacity = '0';
                    setTimeout(function() {
                        perPersonSection.style.display = 'none';
                    }, 300);
                }
            }
            
            togglePerPersonSection();
            pricingType.addEventListener('change', togglePerPersonSection);
        }
    }, 100);
}

/**
 * Yeni ekle modal'ı göster
 */
function showAddModal() {
    // Mevcut modüle göre form modal'ı aç
    if (currentModule) {
        const formUrl = `${moduleUrls[currentModule]}form/`;
        openFormModal(formUrl, 'Yeni Ekle', {
            onSave: function() {
                // Form submit işlemi
                const form = document.querySelector('#modalBody form');
                if (form) {
                    form.submit();
                }
            }
        });
    } else {
        alert('Lütfen önce bir modül seçin!');
    }
}

// Global olarak erişilebilir yap
window.openModal = openModal;
window.closeModal = closeModal;
window.openFormModal = openFormModal;
window.showAddModal = showAddModal;

// Form submit'lerini yakala ve modal içinde işle
document.addEventListener('submit', function(event) {
    const form = event.target;
    const modalContent = form.closest('.modal-content');
    const modalOverlay = form.closest('.modal-overlay');
    
    // Eğer form modal içinde değilse, normal submit işlemini devam ettir
    if (!modalContent && !modalOverlay) {
        return; // Normal form submit işlemi devam etsin
    }
    
    // Form modal içindeyse AJAX ile gönder
    if (modalContent || modalOverlay) {
        // Modal içindeki form submit'i
        event.preventDefault();
        
        const formData = new FormData(form);
        const action = form.action || form.getAttribute('data-action') || window.location.pathname;
        const method = form.method || 'POST';
        
        // CSRF token'ı ekle
        const csrfToken = document.querySelector('[name=csrfmiddlewaretoken]');
        if (csrfToken) {
            formData.append('csrfmiddlewaretoken', csrfToken.value);
        }
        
        fetch(action, {
            method: method,
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin'
        })
        .then(response => {
            // Response tipini kontrol et
            const contentType = response.headers.get('content-type') || '';
            
            // Redirect response'u kontrol et
            if (response.redirected || response.status === 302 || response.status === 301) {
                return { success: true, redirected: true };
            }
            
            if (contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    // HTML response gelirse
                    // Eğer HTML'de form hataları varsa, modal içinde göster
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const formErrors = doc.querySelector('.errorlist, .alert-danger, .bg-red-50');
                    
                    if (formErrors) {
                        // Form hataları var, modal içinde göster
                        return { success: false, html: text, hasErrors: true };
                    } else {
                        // Başarılı - modal'ı kapat
                        return { success: true, html: text };
                    }
                });
            }
        })
        .then(data => {
            if (data.hasErrors) {
                // Form hataları var, modal içeriğini güncelle
                const modalBody = document.getElementById('modalBody');
                if (modalBody) {
                    // HTML'den sadece form içeriğini al
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data.html, 'text/html');
                    const formContent = doc.querySelector('form') || doc.querySelector('.p-6') || doc.body;
                    
                    if (formContent) {
                        modalBody.innerHTML = formContent.innerHTML || formContent.outerHTML;
                        executeModalScripts(modalBody);
                    }
                }
            } else if (data.success || data.redirected) {
                // Başarılı - modal'ı kapat ve modülü yenile
                closeModal();
                
                // Eğer currentModule varsa yenile, yoksa sayfayı yenile
                const currentModule = window.currentModule || null;
                if (currentModule && typeof window.loadModule === 'function') {
                    window.loadModule(currentModule);
                } else {
                    // Modül bilgisi yoksa sayfayı yenile
                    window.location.reload();
                }
            } else {
                // Hata - hata mesajını göster
                alert(data.message || 'Bir hata oluştu!');
            }
        })
        .catch(error => {
            console.error('Form submit hatası:', error);
            alert('Form gönderilirken bir hata oluştu: ' + error.message);
        });
    }
});

