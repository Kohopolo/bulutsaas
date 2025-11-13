/**
 * Rezervasyon Form JavaScript
 * Popup modal form için dinamik işlevler
 */

// Modal Açma/Kapama
function openReservationModal(reservationId = null) {
    console.log('openReservationModal çağrıldı, reservationId:', reservationId);
    // Önce düzenleme modal container'ını temizle
    const editContainer = document.getElementById('reservationEditModalContainer');
    if (editContainer) {
        editContainer.innerHTML = '';
    }
    
    // Yeni rezervasyon modal'ını bul
    const modal = document.getElementById('reservationModal');
    console.log('Modal bulundu:', modal);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Form'u resetle (yeni rezervasyon için)
        const form = document.getElementById('reservationForm');
        if (form) {
            form.reset();
            // Form action'ı yeni rezervasyon için ayarla
            const createUrl = form.action.replace(/\/\d+\/edit\//, '/create/');
            if (!form.action.includes('/create/')) {
                form.action = createUrl;
            }
        }
        
        // Eğer rezervasyon ID varsa, formu doldur (gelecekte implement edilecek)
        if (reservationId) {
            // loadReservationData(reservationId);
            console.log('Rezervasyon yükleme henüz implement edilmedi:', reservationId);
        } else {
            resetForm();
        }
        
        // Event listener'ları bağla (modal açıldığında)
        setTimeout(() => {
            attachEventListeners();
        }, 100);
        
        // İlk hesaplamaları yap
        if (typeof calculateNights === 'function') {
            calculateNights();
        }
        if (typeof updateGuestForms === 'function') {
            updateGuestForms();
        }
    } else {
        console.error('reservationModal elementi bulunamadı!');
        alert('Rezervasyon formu yüklenemedi. Lütfen sayfayı yenileyin.');
    }
}

function closeReservationModal() {
    // Tüm modal'ları kapat
    const modals = document.querySelectorAll('#reservationModal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
    
    // Container'ı temizle (düzenleme modal'ı için)
    const container = document.getElementById('reservationEditModalContainer');
    if (container) {
        container.innerHTML = '';
    }
    
    // Body overflow'u düzelt
    document.body.style.overflow = '';
}

// Form Reset
function resetForm() {
    document.getElementById('reservationForm').reset();
    document.getElementById('total_nights_display').value = '0';
    document.getElementById('total_amount_display').value = '0.00';
    document.getElementById('remaining_amount_display').value = '0.00';
    document.getElementById('adult_guests_container').innerHTML = '';
    document.getElementById('child_guests_container').innerHTML = '';
    document.getElementById('child_ages_container').innerHTML = '';
    document.getElementById('child_guests_section').style.display = 'none';
}

// Geceleme Hesaplama
function calculateNights() {
    const checkIn = document.getElementById('id_check_in_date').value;
    const checkOut = document.getElementById('id_check_out_date').value;
    
    if (checkIn && checkOut) {
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);
        const diffTime = Math.abs(checkOutDate - checkInDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        const nights = diffDays > 0 ? diffDays : 0;
        document.getElementById('total_nights_display').value = nights;
        
        // Toplam tutarı yeniden hesapla
        calculateTotalAmount();
    }
}

// Toplam Tutar Hesaplama
function calculateTotalAmount() {
    const roomRate = parseFloat(document.getElementById('id_room_rate').value) || 0;
    const nights = parseInt(document.getElementById('total_nights_display').value) || 0;
    const discountType = document.getElementById('id_discount_type').value;
    const discountPercentage = parseFloat(document.getElementById('id_discount_percentage').value) || 0;
    const discountAmount = parseFloat(document.getElementById('id_discount_amount').value) || 0;
    const taxAmount = parseFloat(document.getElementById('id_tax_amount').value) || 0;
    
    let baseAmount = roomRate * nights;
    let finalDiscount = 0;
    
    // İndirim hesaplama
    if (discountType === 'percentage' && discountPercentage > 0) {
        finalDiscount = baseAmount * (discountPercentage / 100);
        document.getElementById('id_discount_amount').value = finalDiscount.toFixed(2);
    } else if (discountType === 'fixed') {
        finalDiscount = discountAmount;
    }
    
    const totalAmount = baseAmount - finalDiscount + taxAmount;
    document.getElementById('total_amount_display').value = totalAmount.toFixed(2);
    
    // Kalan tutarı güncelle
    updateRemainingAmount();
}

// Kalan Tutar Güncelleme
function updateRemainingAmount() {
    const totalAmount = parseFloat(document.getElementById('total_amount_display').value) || 0;
    const advancePayment = parseFloat(document.getElementById('advance_payment').value) || 0;
    const remaining = totalAmount - advancePayment;
    document.getElementById('remaining_amount_display').value = remaining.toFixed(2);
}

// Misafir Formlarını Güncelle
function updateGuestForms() {
    const adultCount = parseInt(document.getElementById('id_adult_count').value) || 1;
    const childCount = parseInt(document.getElementById('id_child_count').value) || 0;
    
    // Yetişkin formları
    updateAdultGuestForms(adultCount);
    
    // Çocuk formları
    if (childCount > 0) {
        updateChildGuestForms(childCount);
        document.getElementById('child_guests_section').style.display = 'block';
    } else {
        document.getElementById('child_guests_section').style.display = 'none';
    }
    
    // Çocuk yaşları
    updateChildAges(childCount);
}

// Yetişkin Misafir Formları
function updateAdultGuestForms(count) {
    const container = document.getElementById('adult_guests_container');
    container.innerHTML = '';
    
    for (let i = 1; i <= count; i++) {
        const guestHtml = `
            <div class="guest-form-row" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 10px; padding: 10px; background: white; border: 1px solid #d4d4d4; border-radius: 3px;">
                <div class="form-group">
                    <label>Yetişkin ${i} - Ad <span class="text-red-500">*</span></label>
                    <input type="text" name="adult_guest_${i}_first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Soyad <span class="text-red-500">*</span></label>
                    <input type="text" name="adult_guest_${i}_last_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>TC Kimlik No</label>
                    <input type="text" name="adult_guest_${i}_tc_no" class="form-control" maxlength="11">
                </div>
                <div class="form-group">
                    <label>Kimlik Seri No</label>
                    <input type="text" name="adult_guest_${i}_id_serial_no" class="form-control">
                </div>
                <div class="form-group">
                    <label>Cinsiyet</label>
                    <select name="adult_guest_${i}_gender" class="form-control">
                        <option value="">Seçiniz...</option>
                        <option value="male">Erkek</option>
                        <option value="female">Kadın</option>
                        <option value="other">Diğer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pasaport No</label>
                    <input type="text" name="adult_guest_${i}_passport_no" class="form-control">
                </div>
            </div>
        `;
        container.innerHTML += guestHtml;
    }
}

// Çocuk Misafir Formları
function updateChildGuestForms(count) {
    const container = document.getElementById('child_guests_container');
    container.innerHTML = '';
    
    for (let i = 1; i <= count; i++) {
        const guestHtml = `
            <div class="guest-form-row" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-bottom: 10px; padding: 10px; background: white; border: 1px solid #d4d4d4; border-radius: 3px;">
                <div class="form-group">
                    <label>Çocuk ${i} - Ad <span class="text-red-500">*</span></label>
                    <input type="text" name="child_guest_${i}_first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Soyad <span class="text-red-500">*</span></label>
                    <input type="text" name="child_guest_${i}_last_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Cinsiyet</label>
                    <select name="child_guest_${i}_gender" class="form-control">
                        <option value="">Seçiniz...</option>
                        <option value="male">Erkek</option>
                        <option value="female">Kız</option>
                        <option value="other">Diğer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Yaş</label>
                    <input type="number" name="child_guest_${i}_age" class="form-control" min="0" max="18">
                </div>
                <div class="form-group">
                    <label>TC Kimlik No</label>
                    <input type="text" name="child_guest_${i}_tc_no" class="form-control" maxlength="11">
                </div>
                <div class="form-group">
                    <label>Pasaport No</label>
                    <input type="text" name="child_guest_${i}_passport_no" class="form-control">
                </div>
                <div class="form-group">
                    <label>Seri No</label>
                    <input type="text" name="child_guest_${i}_passport_serial_no" class="form-control">
                </div>
            </div>
        `;
        container.innerHTML += guestHtml;
    }
}

// Çocuk Yaşları
function updateChildAges(count) {
    const container = document.getElementById('child_ages_container');
    container.innerHTML = '';
    
    for (let i = 1; i <= count; i++) {
        const ageInput = document.createElement('input');
        ageInput.type = 'number';
        ageInput.name = `child_age_${i}`;
        ageInput.className = 'form-control';
        ageInput.style.width = '80px';
        ageInput.placeholder = `Yaş ${i}`;
        ageInput.min = 0;
        ageInput.max = 18;
        container.appendChild(ageInput);
    }
}

// Müşteri Arama
function searchCustomer(searchTerm = null) {
    if (!searchTerm) {
        searchTerm = document.getElementById('customer_search')?.value.trim();
    }
    
    if (!searchTerm) {
        const tcNo = document.getElementById('customer_tc_no')?.value.trim();
        if (tcNo && tcNo.length === 11) {
            searchTerm = tcNo;
        } else {
            alert('Lütfen TC No, Email veya Telefon giriniz.');
            return;
        }
    }
    
    // AJAX ile müşteri ara
    fetch(`/reception/api/search-customer/?q=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            const resultsDiv = document.getElementById('customer_search_results');
            if (data.customer) {
                // Müşteri bulundu, formu doldur
                fillCustomerForm(data.customer);
                if (resultsDiv) {
                    resultsDiv.innerHTML = `<div style="color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 3px;">
                        <i class="fas fa-check-circle"></i> Müşteri bulundu: ${data.customer.first_name} ${data.customer.last_name}
                    </div>`;
                }
            } else {
                // Müşteri bulunamadı
                if (resultsDiv) {
                    resultsDiv.innerHTML = `<div style="color: orange; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 3px;">
                        <i class="fas fa-exclamation-triangle"></i> Müşteri bulunamadı. Yeni müşteri olarak kaydedilecek.
                    </div>`;
                }
            }
        })
        .catch(error => {
            console.error('Müşteri arama hatası:', error);
            // Sessizce hata göster (otomatik arama için)
        });
}

// TC Kimlik otomatik arama handler
function handleTcNoAutoSearch() {
    const tcNo = this.value.trim();
    // TC No 11 karakter ise otomatik ara
    if (tcNo.length === 11 && /^\d+$/.test(tcNo)) {
        // Kısa bir gecikme ile ara (kullanıcı yazmayı bitirsin)
        clearTimeout(window.tcNoSearchTimeout);
        window.tcNoSearchTimeout = setTimeout(() => {
            searchCustomer(tcNo);
        }, 500);
    }
}

// Customer search input otomatik arama handler
function handleCustomerSearchAuto() {
    const searchTerm = this.value.trim();
    // Eğer TC No formatındaysa (11 karakter, sadece rakam) otomatik ara
    if (searchTerm.length === 11 && /^\d+$/.test(searchTerm)) {
        clearTimeout(window.customerSearchTimeout);
        window.customerSearchTimeout = setTimeout(() => {
            searchCustomer(searchTerm);
        }, 500);
    }
}

// Müşteri Formunu Doldur
function fillCustomerForm(customer) {
    document.getElementById('customer_first_name').value = customer.first_name || '';
    document.getElementById('customer_last_name').value = customer.last_name || '';
    document.getElementById('customer_phone').value = customer.phone || '';
    document.getElementById('customer_email').value = customer.email || '';
    document.getElementById('customer_address').value = customer.address || '';
    document.getElementById('customer_tc_no').value = customer.tc_no || '';
    document.getElementById('customer_passport_no').value = customer.passport_no || '';
    document.getElementById('customer_nationality').value = customer.nationality || 'Türkiye';
    
    // Hidden customer field'ı set et
    const customerInput = document.querySelector('input[name="customer"]');
    if (customerInput) {
        customerInput.value = customer.id;
    }
}

// Oda Tipi Değiştiğinde Oda Numaralarını Filtrele
function filterRoomNumbers() {
    const roomId = document.getElementById('id_room').value;
    if (!roomId) {
        return;
    }
    
    // AJAX ile oda numaralarını getir
    const roomNumbersUrl = document.querySelector('[data-room-numbers-url]')?.dataset.roomNumbersUrl || 
                          '/reception/api/room-numbers/';
    fetch(`${roomNumbersUrl}?room_id=${roomId}`)
        .then(response => response.json())
        .then(data => {
            const roomNumberSelect = document.getElementById('id_room_number');
            roomNumberSelect.innerHTML = '<option value="">Seçiniz...</option>';
            
            data.room_numbers.forEach(roomNumber => {
                const option = document.createElement('option');
                option.value = roomNumber.id;
                option.textContent = `${roomNumber.number} (${roomNumber.status_display})`;
                roomNumberSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Oda numarası yükleme hatası:', error);
        });
}

// Fiyat Hesaplama (Global Pricing Utility)
function calculatePrice() {
    const roomId = document.getElementById('id_room')?.value;
    const checkInDate = document.getElementById('id_check_in_date')?.value;
    const checkOutDate = document.getElementById('id_check_out_date')?.value;
    const adultCount = parseInt(document.getElementById('id_adult_count')?.value) || 1;
    const childCount = parseInt(document.getElementById('id_child_count')?.value) || 0;
    const agencyId = document.getElementById('id_reservation_agent')?.value;
    const channelId = document.getElementById('id_reservation_channel')?.value;
    
    if (!roomId || !checkInDate || !checkOutDate) {
        console.log('Fiyat hesaplama için gerekli alanlar eksik:', { roomId, checkInDate, checkOutDate });
        return;
    }
    
    // Çocuk yaşları
    const childAges = [];
    const childAgeInputs = document.querySelectorAll('#child_ages_container input[type="number"]');
    childAgeInputs.forEach(input => {
        if (input.value) {
            childAges.push(parseInt(input.value));
        }
    });
    
    // AJAX ile fiyat hesapla (GET request)
    const calculatePriceUrl = '/reception/api/calculate-price/';
    const params = new URLSearchParams({
        room_id: roomId,
        check_in_date: checkInDate,
        check_out_date: checkOutDate,
        adult_count: adultCount,
        child_count: childCount,
    });
    
    if (childAges.length > 0) {
        params.append('child_ages', childAges.join(','));
    }
    if (agencyId) {
        params.append('agency_id', agencyId);
    }
    if (channelId) {
        params.append('channel_id', channelId);
    }
    
    fetch(`${calculatePriceUrl}?${params.toString()}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        // Response tipini kontrol et
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            // HTML döndüyse (hata sayfası, login redirect, vs.)
            return response.text().then(text => {
                console.error('API HTML döndü (muhtemelen yetki hatası veya 404):', text.substring(0, 200));
                throw new Error('API endpoint\'i JSON döndürmedi. Yetki kontrolü veya URL hatası olabilir.');
            });
        }
        
        // HTTP status kontrolü
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
            });
        }
        
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const price = data.avg_nightly_price || data.price || 0;
            const roomRateField = document.getElementById('id_room_rate');
            if (roomRateField) {
                // Readonly özelliğini geçici olarak kaldır
                const wasReadonly = roomRateField.readOnly;
                roomRateField.readOnly = false;
                roomRateField.value = price.toFixed(2);
                // Readonly özelliğini geri ekle (eğer manuel fiyat işaretli değilse)
                const isManualPrice = document.getElementById('id_is_manual_price');
                if (isManualPrice && !isManualPrice.checked) {
                    roomRateField.readOnly = true;
                } else if (wasReadonly) {
                    roomRateField.readOnly = true;
                }
            }
            calculateTotalAmount();
        } else {
            console.error('Fiyat hesaplama hatası:', data.error || 'Bilinmeyen hata');
        }
    })
    .catch(error => {
        console.error('Fiyat hesaplama hatası:', error.message || error);
        // Kullanıcıya sessizce hata göster (sürekli alert göstermemek için)
        // Sadece console'da log tutuyoruz
    });
}

// Event Listener'ları Bağla
function attachEventListeners() {
    // Tarih değişikliklerinde geceleme hesapla ve otomatik fiyat hesapla
    const checkInDate = document.getElementById('id_check_in_date');
    const checkOutDate = document.getElementById('id_check_out_date');
    if (checkInDate) {
        checkInDate.removeEventListener('change', handleDateChange);
        checkInDate.addEventListener('change', handleDateChange);
    }
    if (checkOutDate) {
        checkOutDate.removeEventListener('change', handleDateChange);
        checkOutDate.addEventListener('change', handleDateChange);
    }
    
    // Oda tipi değişikliğinde oda numaralarını filtrele ve otomatik fiyat hesapla
    const roomSelect = document.getElementById('id_room');
    if (roomSelect) {
        roomSelect.removeEventListener('change', handleRoomChange);
        roomSelect.addEventListener('change', handleRoomChange);
    }
    
    // Yetişkin/çocuk sayısı değişikliklerinde formları güncelle
    const adultCount = document.getElementById('id_adult_count');
    const childCount = document.getElementById('id_child_count');
    if (adultCount) {
        adultCount.removeEventListener('change', handleGuestCountChange);
        adultCount.addEventListener('change', handleGuestCountChange);
    }
    if (childCount) {
        childCount.removeEventListener('change', handleGuestCountChange);
        childCount.addEventListener('change', handleGuestCountChange);
    }
    
    // Fiyatlandırma alanları değişikliklerinde toplam tutarı hesapla
    const priceFields = ['id_room_rate', 'id_discount_type', 'id_discount_percentage', 'id_discount_amount', 'id_tax_amount'];
    priceFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            // Önceki listener'ları kaldırmak için yeni bir handler fonksiyonu oluştur
            const handler = function() {
                calculateTotalAmount();
            };
            field.addEventListener('change', handler);
            field.addEventListener('input', handler);
        }
    });
    
    // Ön ödeme değişikliğinde kalan tutarı güncelle
    const advancePayment = document.getElementById('advance_payment');
    if (advancePayment) {
        advancePayment.removeEventListener('change', updateRemainingAmount);
        advancePayment.removeEventListener('input', updateRemainingAmount);
        advancePayment.addEventListener('change', updateRemainingAmount);
        advancePayment.addEventListener('input', updateRemainingAmount);
    }
    
    // Müşteri arama butonu
    const searchBtn = document.getElementById('searchCustomerBtn');
    if (searchBtn) {
        searchBtn.removeEventListener('click', searchCustomer);
        searchBtn.addEventListener('click', searchCustomer);
    }
    
    // TC Kimlik otomatik arama
    const customerTcNo = document.getElementById('customer_tc_no');
    if (customerTcNo) {
        customerTcNo.removeEventListener('input', handleTcNoAutoSearch);
        customerTcNo.addEventListener('input', handleTcNoAutoSearch);
    }
    
    // Customer search input otomatik arama
    const customerSearch = document.getElementById('customer_search');
    if (customerSearch) {
        customerSearch.removeEventListener('input', handleCustomerSearchAuto);
        customerSearch.addEventListener('input', handleCustomerSearchAuto);
    }
    
    // Fiyat hesaplama butonu
    const calculateBtn = document.getElementById('calculatePriceBtn');
    if (calculateBtn) {
        calculateBtn.removeEventListener('click', calculatePrice);
        calculateBtn.addEventListener('click', calculatePrice);
    }
    
    // Comp rezervasyon checkbox
    const isComp = document.getElementById('id_is_comp');
    if (isComp) {
        isComp.removeEventListener('change', handleCompChange);
        isComp.addEventListener('change', handleCompChange);
    }
    
    // Manuel fiyat checkbox
    const isManualPrice = document.getElementById('id_is_manual_price');
    if (isManualPrice) {
        isManualPrice.removeEventListener('change', handleManualPriceChange);
        isManualPrice.addEventListener('change', handleManualPriceChange);
    }
}

// Tarih değişikliği handler'ı
function handleDateChange() {
    calculateNights();
    // Tarih değiştiğinde otomatik fiyat hesapla (eğer oda seçiliyse)
    const roomId = document.getElementById('id_room').value;
    if (roomId) {
        autoCalculatePrice();
    }
}

// Oda değişikliği handler'ı
function handleRoomChange() {
    filterRoomNumbers();
    // Oda değiştiğinde otomatik fiyat hesapla (eğer tarihler seçiliyse)
    const checkIn = document.getElementById('id_check_in_date').value;
    const checkOut = document.getElementById('id_check_out_date').value;
    if (checkIn && checkOut) {
        autoCalculatePrice();
    }
}

// Misafir sayısı değişikliği handler'ı
function handleGuestCountChange() {
    updateGuestForms();
    // Misafir sayısı değiştiğinde otomatik fiyat hesapla (eğer gerekli alanlar doluysa)
    const roomId = document.getElementById('id_room').value;
    const checkIn = document.getElementById('id_check_in_date').value;
    const checkOut = document.getElementById('id_check_out_date').value;
    if (roomId && checkIn && checkOut) {
        autoCalculatePrice();
    }
}

// Comp rezervasyon handler'ı
function handleCompChange() {
    const roomRateField = document.getElementById('id_room_rate');
    if (this.checked) {
        if (roomRateField) {
            roomRateField.readOnly = false;
            roomRateField.value = '0.00';
            roomRateField.readOnly = true;
        }
        document.getElementById('total_amount_display').value = '0.00';
        calculateTotalAmount();
    } else {
        // Comp işaretini kaldırınca fiyatı yeniden hesapla
        autoCalculatePrice();
    }
}

// Manuel fiyat handler'ı
function handleManualPriceChange() {
    const roomRateField = document.getElementById('id_room_rate');
    if (roomRateField) {
        if (this.checked) {
            // Manuel fiyat işaretlendi, readonly kaldır
            roomRateField.readOnly = false;
        } else {
            // Manuel fiyat işareti kaldırıldı, readonly yap ve otomatik hesapla
            roomRateField.readOnly = true;
            autoCalculatePrice();
        }
    }
}

// Otomatik Fiyat Hesaplama (Gerekli alanlar doluysa)
function autoCalculatePrice() {
    const roomId = document.getElementById('id_room')?.value;
    const checkIn = document.getElementById('id_check_in_date')?.value;
    const checkOut = document.getElementById('id_check_out_date')?.value;
    const isComp = document.getElementById('id_is_comp')?.checked;
    const isManualPrice = document.getElementById('id_is_manual_price')?.checked;
    
    // Comp rezervasyon veya manuel fiyat ise otomatik hesaplama yapma
    if (isComp || isManualPrice) {
        return;
    }
    
    // Gerekli alanlar doluysa otomatik hesapla
    if (roomId && checkIn && checkOut) {
        // Kısa bir gecikme ile hesapla (kullanıcı yazmayı bitirsin)
        clearTimeout(window.autoCalculateTimeout);
        window.autoCalculateTimeout = setTimeout(() => {
            calculatePrice();
        }, 500);
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    attachEventListeners();
});

// Global scope'a fonksiyonları ekle (list.html'den erişilebilir olması için)
// Eğer zaten tanımlı değilse ekle (sonsuz döngüyü önlemek için)
if (typeof window.openReservationModal === 'undefined' || window.openReservationModal === window.openReservationModalLocal) {
    window.openReservationModal = openReservationModal;
}
if (typeof window.closeReservationModal === 'undefined' || window.closeReservationModal === window.closeReservationModalLocal) {
    window.closeReservationModal = closeReservationModal;
}

