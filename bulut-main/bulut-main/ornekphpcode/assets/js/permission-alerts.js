/**
 * Yetki Kontrolü ve Alert Sistemi
 * Permission Control and Alert System
 */

// Özel alert fonksiyonu
function showPermissionAlert(message, type = 'error') {
    // Mevcut alert'i kaldır
    const existingAlert = document.getElementById('permission-alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Alert container oluştur
    const alertContainer = document.createElement('div');
    alertContainer.id = 'permission-alert';
    alertContainer.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        line-height: 1.4;
        animation: slideInRight 0.3s ease-out;
    `;
    
    // Tip'e göre stil belirle
    if (type === 'error') {
        alertContainer.style.backgroundColor = '#fee';
        alertContainer.style.border = '1px solid #fcc';
        alertContainer.style.color = '#c33';
    } else if (type === 'warning') {
        alertContainer.style.backgroundColor = '#fff8e1';
        alertContainer.style.border = '1px solid #ffcc02';
        alertContainer.style.color = '#e65100';
    } else if (type === 'success') {
        alertContainer.style.backgroundColor = '#e8f5e8';
        alertContainer.style.border = '1px solid #4caf50';
        alertContainer.style.color = '#2e7d32';
    }
    
    // İçerik oluştur
    alertContainer.innerHTML = `
        <div style="display: flex; align-items: flex-start; gap: 10px;">
            <div style="flex-shrink: 0; margin-top: 2px;">
                ${type === 'error' ? '⚠️' : type === 'warning' ? '⚠️' : '✅'}
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; margin-bottom: 5px;">
                    ${type === 'error' ? 'Yetki Hatası' : type === 'warning' ? 'Uyarı' : 'Başarılı'}
                </div>
                <div>${message}</div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: none; border: none; font-size: 18px; cursor: pointer; color: inherit; opacity: 0.7; padding: 0; margin-left: 10px;">
                ×
            </button>
        </div>
    `;
    
    // Sayfaya ekle
    document.body.appendChild(alertContainer);
    
    // 5 saniye sonra otomatik kaldır
    setTimeout(() => {
        if (alertContainer.parentElement) {
            alertContainer.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (alertContainer.parentElement) {
                    alertContainer.remove();
                }
            }, 300);
        }
    }, 5000);
}

// CSS animasyonları ekle
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// AJAX istekleri için global error handler
document.addEventListener('DOMContentLoaded', function() {
    // Fetch API için error handler
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        if (data.message) {
                            showPermissionAlert(data.message, 'error');
                        }
                        throw new Error(data.message || 'Bir hata oluştu');
                    });
                }
                return response;
            })
            .catch(error => {
                if (error.message && !error.message.includes('Failed to fetch')) {
                    showPermissionAlert(error.message, 'error');
                }
                throw error;
            });
    };
    
    // XMLHttpRequest için error handler
    const originalXHR = window.XMLHttpRequest;
    window.XMLHttpRequest = function() {
        const xhr = new originalXHR();
        const originalOnReadyStateChange = xhr.onreadystatechange;
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status >= 400) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        showPermissionAlert(response.message, 'error');
                    }
                } catch (e) {
                    // JSON parse hatası, normal hata mesajı göster
                }
            }
            
            if (originalOnReadyStateChange) {
                originalOnReadyStateChange.apply(this, arguments);
            }
        };
        
        return xhr;
    };
});

// Global fonksiyon olarak tanımla
window.showPermissionAlert = showPermissionAlert;
