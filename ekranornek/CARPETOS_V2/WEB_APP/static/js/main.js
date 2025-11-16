// CarpetOS V2 - Main JavaScript

const socket = io();

socket.on('connect', () => {
    console.log('Connected to server');
});

socket.on('customer_created', (data) => {
    console.log('Customer created:', data);
    if (window.location.pathname === '/dashboard') {
        loadCustomers();
    }
});

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('tr-TR');
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY'
    }).format(amount);
}

