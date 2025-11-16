/**
 * Footer Functions - Footer panel fonksiyonları
 */

/**
 * Ara
 */
function showSearch() {
    const centerPanel = document.getElementById('centerPanel');
    if (!centerPanel) return;
    
    // Eğer modül yüklüyse, o modülün arama fonksiyonunu çağır
    if (currentModule) {
        // Modül içindeki arama input'unu focus yap
        const searchInput = centerPanel.querySelector('input[type="text"], input[placeholder*="Ara"], input[placeholder*="ara"]');
        if (searchInput) {
            searchInput.focus();
        } else {
            alert('Bu modülde arama özelliği bulunmuyor.');
        }
    } else {
        alert('Lütfen önce bir modül seçin!');
    }
}

/**
 * Seçili öğeleri sil
 */
function deleteSelected() {
    if (!confirm('Seçili öğeleri silmek istediğinize emin misiniz?')) {
        return;
    }
    
    const centerPanel = document.getElementById('centerPanel');
    if (!centerPanel) return;
    
    // Seçili checkbox'ları bul
    const selectedCheckboxes = centerPanel.querySelectorAll('input[type="checkbox"]:checked');
    if (selectedCheckboxes.length === 0) {
        alert('Lütfen silmek için öğe seçin!');
        return;
    }
    
    // Seçili öğelerin ID'lerini topla
    const ids = Array.from(selectedCheckboxes).map(cb => cb.value || cb.closest('tr').dataset.id);
    
    // Silme işlemi (modül bazlı)
    if (currentModule) {
        // Modül bazlı silme URL'i oluştur
        const deleteUrl = `${moduleUrls[currentModule]}delete/`;
        
        // AJAX ile sil
        fetch(deleteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRFToken': getCookie('csrftoken'),
            },
            body: JSON.stringify({ ids: ids }),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Seçili öğeler başarıyla silindi.');
                refreshCurrentModule();
            } else {
                alert(data.message || 'Silme işlemi başarısız!');
            }
        })
        .catch(error => {
            console.error('Silme hatası:', error);
            alert('Silme işlemi sırasında bir hata oluştu!');
        });
    }
}

/**
 * Bilgi göster
 */
function showInfo() {
    const info = `
        Bulut Acente Yönetim Sistemi
        Versiyon: 2.0
        Tarih: ${new Date().toLocaleDateString('tr-TR')}
    `;
    openModal('Sistem Bilgisi', `<div class="p-10"><pre>${info}</pre></div>`, {
        width: '400px',
        showFooter: true,
        footerButtons: '<button type="button" class="btn btn-primary" onclick="closeModal()">Tamam</button>'
    });
}

/**
 * Yardım göster
 */
function showHelp() {
    const helpContent = `
        <div class="p-10">
            <h3>Yardım</h3>
            <p>Sol menüden modül seçebilir veya sağ paneldeki kısayol butonlarını kullanabilirsiniz.</p>
            <p>Form eklemek için "Yeni Ekle" butonunu kullanın.</p>
            <p>Daha fazla yardım için destek ekibimizle iletişime geçin.</p>
        </div>
    `;
    openModal('Yardım', helpContent, {
        width: '500px',
        showFooter: true,
        footerButtons: '<button type="button" class="btn btn-primary" onclick="closeModal()">Tamam</button>'
    });
}

/**
 * Mesajlar göster
 */
function showMessages() {
    openModal('Mesajlar', '<div class="p-10"><p>Mesaj özelliği yakında eklenecek.</p></div>', {
        width: '600px',
        showFooter: true,
        footerButtons: '<button type="button" class="btn btn-primary" onclick="closeModal()">Tamam</button>'
    });
}

/**
 * Hesaplayıcı göster
 */
function showCalculator() {
    const calculatorHTML = `
        <div class="p-10">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; max-width: 300px; margin: 0 auto;">
                <input type="text" id="calcDisplay" readonly style="grid-column: span 4; padding: 10px; font-size: 18pt; text-align: right; border: 1px solid #E0E0E0;" value="0">
                <button class="btn" onclick="calcInput('C')" style="grid-column: span 2;">C</button>
                <button class="btn" onclick="calcInput('←')">←</button>
                <button class="btn" onclick="calcInput('/')">/</button>
                <button class="btn" onclick="calcInput('7')">7</button>
                <button class="btn" onclick="calcInput('8')">8</button>
                <button class="btn" onclick="calcInput('9')">9</button>
                <button class="btn" onclick="calcInput('*')">*</button>
                <button class="btn" onclick="calcInput('4')">4</button>
                <button class="btn" onclick="calcInput('5')">5</button>
                <button class="btn" onclick="calcInput('6')">6</button>
                <button class="btn" onclick="calcInput('-')">-</button>
                <button class="btn" onclick="calcInput('1')">1</button>
                <button class="btn" onclick="calcInput('2')">2</button>
                <button class="btn" onclick="calcInput('3')">3</button>
                <button class="btn" onclick="calcInput('+')">+</button>
                <button class="btn" onclick="calcInput('0')" style="grid-column: span 2;">0</button>
                <button class="btn" onclick="calcInput('.')">.</button>
                <button class="btn btn-primary" onclick="calcCalculate()">=</button>
            </div>
        </div>
    `;
    
    openModal('Hesaplayıcı', calculatorHTML, {
        width: '400px',
        showFooter: false
    });
    
    window.calcValue = '0';
    window.calcDisplay = document.getElementById('calcDisplay');
}

function calcInput(value) {
    if (value === 'C') {
        window.calcValue = '0';
    } else if (value === '←') {
        window.calcValue = window.calcValue.slice(0, -1) || '0';
    } else if (value === '=') {
        calcCalculate();
        return;
    } else {
        if (window.calcValue === '0' && value !== '.') {
            window.calcValue = value;
        } else {
            window.calcValue += value;
        }
    }
    window.calcDisplay.value = window.calcValue;
}

function calcCalculate() {
    try {
        const result = eval(window.calcValue);
        window.calcValue = result.toString();
        window.calcDisplay.value = window.calcValue;
    } catch (e) {
        alert('Hesaplama hatası!');
        window.calcValue = '0';
        window.calcDisplay.value = window.calcValue;
    }
}

/**
 * Kullanıcı menüsü göster
 */
function showUserMenu() {
    const userMenuHTML = `
        <div class="p-10">
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button class="btn btn-primary" onclick="loadModule('users')">Kullanıcı Yönetimi</button>
                <button class="btn" onclick="loadModule('settings')">Ayarlar</button>
                <button class="btn btn-danger" onclick="logout()">Çıkış Yap</button>
            </div>
        </div>
    `;
    
    openModal('Kullanıcı Menüsü', userMenuHTML, {
        width: '300px',
        showFooter: false
    });
}

/**
 * Tema değiştir
 */
function toggleTheme() {
    alert('Tema değiştirme özelliği yakında eklenecek.');
}

/**
 * Bağlantı kontrolü
 */
function checkConnection() {
    const statusLabel = document.getElementById('statusLabel');
    if (statusLabel) {
        statusLabel.textContent = 'Bağlantı kontrol ediliyor...';
        statusLabel.style.color = '#FF9800';
    }
    
    fetch('/api/health/', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => {
        if (response.ok) {
            if (statusLabel) {
                statusLabel.textContent = '✅ Bağlantı Başarılı';
                statusLabel.style.color = '#4CAF50';
            }
        } else {
            throw new Error('Bağlantı hatası');
        }
    })
    .catch(error => {
        if (statusLabel) {
            statusLabel.textContent = '❌ Bağlantı Hatası';
            statusLabel.style.color = '#F44336';
        }
    });
}

/**
 * Çıkış yap
 */
function logout() {
    if (confirm('Çıkış yapmak istediğinize emin misiniz?')) {
        window.location.href = '/tenant/logout/';
    }
}

/**
 * Cookie oku
 */
function getCookie(name) {
    let cookieValue = null;
    if (document.cookie && document.cookie !== '') {
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i].trim();
            if (cookie.substring(0, name.length + 1) === (name + '=')) {
                cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                break;
            }
        }
    }
    return cookieValue;
}

// Global olarak erişilebilir yap
window.showSearch = showSearch;
window.deleteSelected = deleteSelected;
window.showInfo = showInfo;
window.showHelp = showHelp;
window.showMessages = showMessages;
window.showCalculator = showCalculator;
window.showUserMenu = showUserMenu;
window.toggleTheme = toggleTheme;
window.checkConnection = checkConnection;
window.logout = logout;

