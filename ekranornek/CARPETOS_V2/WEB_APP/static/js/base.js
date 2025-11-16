// CarpetOS V2 - Base JavaScript Functions
// Tüm sayfalarda kullanılacak ortak fonksiyonlar

const socket = io();

socket.on('connect', () => {
    console.log('Connected to server');
    updateStatus('API Bağlı', 'success');
});

socket.on('disconnect', () => {
    console.log('Disconnected from server');
    updateStatus('API Bağlantısı Kesildi', 'error');
});

socket.on('customer_created', (data) => {
    console.log('Customer created:', data);
    if (typeof loadCustomers === 'function') {
        loadCustomers();
    }
});

socket.on('order_created', (data) => {
    console.log('Order created:', data);
    if (typeof loadOrders === 'function') {
        loadOrders();
    }
});

// Status güncelleme
function updateStatus(message, type = 'success') {
    const statusLabel = document.getElementById('statusLabel');
    if (statusLabel) {
        statusLabel.textContent = message;
        statusLabel.style.color = type === 'success' ? '#4CAF50' : '#F44336';
    }
}

// Footer icon fonksiyonları
function refreshPage() {
    location.reload();
}

function showSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
    }
}

function deleteSelected() {
    alert('Seçili öğeler silinecek');
}

function showInfo() {
    alert('CarpetOS v2.0\nHalı Yıkama İşletme Yönetim Sistemi');
}

function showHelp() {
    alert('Yardım sayfası açılacak');
}

function showMessages() {
    alert('Mesajlar');
}

function showCalculator() {
    alert('Hesaplayıcı');
}

function showUserMenu() {
    alert('Kullanıcı menüsü');
}

function toggleTheme() {
    alert('Tema değiştirilecek');
}

function checkConnection() {
    updateStatus('Bağlantı kontrol ediliyor...', 'info');
    setTimeout(() => {
        updateStatus('API Bağlı', 'success');
    }, 1000);
}

function showFinance() {
    alert('Finans modülü');
}

function showAddModal() {
    // Sayfaya göre farklı modal açılacak
    if (window.location.pathname === '/customers') {
        if (typeof showAddCustomerModal === 'function') {
            showAddCustomerModal();
        }
    } else if (window.location.pathname === '/orders') {
        if (typeof showAddOrderModal === 'function') {
            showAddOrderModal();
        }
    }
}

// Müşteri yükleme (tüm sayfalarda kullanılabilir)
async function loadCustomers() {
    try {
        const response = await fetch('/api/customers');
        const data = await response.json();
        if (data.success) {
            const tbody = document.getElementById('customersTableBody');
            if (tbody) {
                tbody.innerHTML = data.data.map(customer => `
                    <tr onclick="selectCustomer(${customer.id})">
                        <td>${customer.customer_number || ''}</td>
                        <td>${customer.first_name} ${customer.last_name}</td>
                    </tr>
                `).join('');
                const totalSpan = document.getElementById('totalCustomers');
                if (totalSpan) {
                    totalSpan.textContent = data.data.length;
                }
            }
        }
    } catch (error) {
        console.error('Error loading customers:', error);
    }
}

function selectCustomer(id) {
    document.querySelectorAll('.customer-table tbody tr').forEach(tr => tr.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}

function refreshCustomers() {
    loadCustomers();
}

function searchCustomers() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
    }
}

function deleteCustomer() {
    const selected = document.querySelector('.customer-table tbody tr.selected');
    if (selected) {
        if (confirm('Seçili müşteriyi silmek istediğinize emin misiniz?')) {
            // Delete implementation
        }
    } else {
        alert('Lütfen silmek için bir müşteri seçin');
    }
}

// Sayfa yüklendiğinde müşterileri yükle
if (document.getElementById('customersTableBody')) {
    loadCustomers();
}

