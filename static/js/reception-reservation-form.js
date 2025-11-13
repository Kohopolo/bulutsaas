/**
 * Rezervasyon Formu JavaScript
 * Popup modal form yönetimi
 */

// Modal Kontrolü
function openReservationModal() {
    const modal = document.getElementById('reservationModal');
    if (!modal) {
        // Modal yoksa AJAX ile yükle
        loadReservationForm();
        return;
    }
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeReservationModal() {
    const modal = document.getElementById('reservationModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Form yükleme
function loadReservationForm() {
    const container = document.querySelector('.content-body');
    const createUrl = container?.dataset.reservationCreateUrl || '/reception/reservations/create/';
    fetch(createUrl, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.html) {
            // Modal HTML'ini ekle
            const container = document.createElement('div');
            container.innerHTML = data.html;
            document.body.appendChild(container);
            
            // Modal'ı aç
            openReservationModal();
            
            // Event listener'ları ekle
            initializeReservationForm();
        }
    })
    .catch(error => {
        console.error('Error loading form:', error);
        alert('Form yüklenirken hata oluştu.');
    });
}

// Form başlatma
function initializeReservationForm() {
    // Tarih değişikliklerinde geceleme hesapla
    const checkInDate = document.getElementById('id_check_in_date');
    const checkOutDate = document.getElementById('id_check_out_date');
    
    if (checkInDate && checkOutDate) {
        checkInDate.addEventListener('change', calculateNights);
        checkOutDate.addEventListener('change', calculateNights);
    }
    
    // Yetişkin sayısı değişikliğinde form alanları oluştur
    const adultCount = document.getElementById('id_adult_count');
    if (adultCount) {
        adultCount.addEventListener('change', updateAdultGuestFields);
    }
    
    // Çocuk sayısı değişikliğinde form alanları oluştur
    const childCount = document.getElementById('id_child_count');
    if (childCount) {
        childCount.addEventListener('change', updateChildGuestFields);
    }
    
    // Oda tipi değişikliğinde oda numaralarını güncelle
    const roomSelect = document.getElementById('id_room');
    if (roomSelect) {
        roomSelect.addEventListener('change', updateRoomNumbers);
    }
    
    // Oda numarası değişikliğinde durum göster
    const roomNumberSelect = document.getElementById('id_room_number');
    if (roomNumberSelect) {
        roomNumberSelect.addEventListener('change', showRoomStatus);
    }
    
    // İndirim tipi değişikliğinde alanları göster/gizle
    const discountType = document.getElementById('id_discount_type');
    if (discountType) {
        discountType.addEventListener('change', updateDiscountFields);
    }
    
    // Comp checkbox değişikliğinde fiyatı sıfırla
    const isComp = document.getElementById('id_is_comp');
    if (isComp) {
        isComp.addEventListener('change', handleCompChange);
    }
    
    // No-show checkbox değişikliğinde neden alanını göster
    const isNoShow = document.getElementById('id_is_no_show');
    if (isNoShow) {
        isNoShow.addEventListener('change', toggleNoShowReason);
    }
    
    // Manuel fiyat checkbox değişikliğinde fiyat alanını aktif/pasif yap
    const isManualPrice = document.getElementById('id_is_manual_price');
    if (isManualPrice) {
        isManualPrice.addEventListener('change', toggleManualPrice);
    }
    
    // Fiyat hesaplama butonu
    const calculateBtn = document.querySelector('button[onclick="calculatePrice()"]');
    if (calculateBtn) {
        calculateBtn.addEventListener('click', calculatePrice);
    }
    
    // Müşteri arama
    const customerSearch = document.getElementById('customer_search');
    if (customerSearch) {
        customerSearch.addEventListener('keyup', debounce(searchCustomer, 500));
    }
    
    // İlk yüklemede alanları güncelle
    calculateNights();
    updateDiscountFields();
}

// Geceleme sayısını hesapla
function calculateNights() {
    const checkIn = document.getElementById('id_check_in_date').value;
    const checkOut = document.getElementById('id_check_out_date').value;
    
    if (checkIn && checkOut) {
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);
        const diffTime = checkOutDate - checkInDate;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        const nights = diffDays > 0 ? diffDays : 0;
        document.getElementById('total_nights_display').value = nights + ' gece';
        
        // Toplam tutarı güncelle
        calculateTotalAmount();
    }
}

// Toplam tutarı hesapla
function calculateTotalAmount() {
    const roomRate = parseFloat(document.getElementById('id_room_rate').value) || 0;
    const nights = parseInt(document.getElementById('total_nights_display').value) || 0;
    const discountType = document.getElementById('id_discount_type').value;
    const discountPercentage = parseFloat(document.getElementById('id_discount_percentage').value) || 0;
    const discountAmount = parseFloat(document.getElementById('id_discount_amount').value) || 0;
    const taxAmount = parseFloat(document.getElementById('id_tax_amount').value) || 0;
    const currency = document.getElementById('id_currency').value || 'TRY';
    
    let baseAmount = roomRate * nights;
    let discount = 0;
    
    if (discountType === 'percentage' && discountPercentage > 0) {
        discount = baseAmount * (discountPercentage / 100);
    } else if (discountType === 'fixed' && discountAmount > 0) {
        discount = discountAmount;
    }
    
    const total = baseAmount - discount + taxAmount;
    document.getElementById('total_amount_display').value = total.toFixed(2) + ' ' + currency;
}

// Fiyat hesapla (Global Pricing Utility)
function calculatePrice() {
    const roomId = document.getElementById('id_room').value;
    const checkIn = document.getElementById('id_check_in_date').value;
    const checkOut = document.getElementById('id_check_out_date').value;
    const adultCount = parseInt(document.getElementById('id_adult_count').value) || 1;
    const childCount = parseInt(document.getElementById('id_child_count').value) || 0;
    const agencyId = document.getElementById('id_reservation_agent').value;
    const channelId = document.getElementById('id_reservation_channel').value;
    
    if (!roomId || !checkIn || !checkOut) {
        alert('Lütfen oda, giriş ve çıkış tarihlerini seçin.');
        return;
    }
    
    // Çocuk yaşları
    const childAges = [];
    const childAgeInputs = document.querySelectorAll('#child_ages_inputs input[type="number"]');
    childAgeInputs.forEach(input => {
        if (input.value) {
            childAges.push(parseInt(input.value));
        }
    });
    
    const params = new URLSearchParams({
        room_id: roomId,
        check_in_date: checkIn,
        check_out_date: checkOut,
        adult_count: adultCount,
        child_count: childCount,
        child_ages: childAges.join(','),
    });
    
    if (agencyId) params.append('agency_id', agencyId);
    if (channelId) params.append('channel_id', channelId);
    
    const container = document.querySelector('.content-body');
    const calculatePriceUrl = container?.dataset.calculatePriceUrl || '/reception/api/calculate-price/';
    fetch(`${calculatePriceUrl}?${params}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('id_room_rate').value = data.avg_nightly_price.toFixed(2);
            calculateTotalAmount();
        } else {
            alert('Fiyat hesaplanamadı: ' + (data.error || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Error calculating price:', error);
        alert('Fiyat hesaplanırken hata oluştu.');
    });
}

// Yetişkin misafir form alanlarını güncelle
function updateAdultGuestFields() {
    const adultCount = parseInt(document.getElementById('id_adult_count').value) || 0;
    const container = document.getElementById('adult_guests_container');
    
    if (adultCount <= 0) {
        container.innerHTML = '<p class="text-muted" style="text-align: center; padding: 20px;">Yetişkin sayısı girildikten sonra form alanları oluşturulacak</p>';
        return;
    }
    
    let html = '';
    for (let i = 1; i <= adultCount; i++) {
        html += `
            <div class="guest-form-group" style="border: 1px solid #dee2e6; padding: 10px; margin-bottom: 10px; border-radius: 3px; background: #fff;">
                <h5 style="margin-bottom: 10px; color: #2c5f8d;">Yetişkin ${i}</h5>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div class="form-group">
                        <label>Ad *</label>
                        <input type="text" name="adult_${i}_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Soyad *</label>
                        <input type="text" name="adult_${i}_last_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>TC Kimlik No</label>
                        <input type="text" name="adult_${i}_tc_no" class="form-control" maxlength="11">
                    </div>
                    <div class="form-group">
                        <label>Kimlik Seri No</label>
                        <input type="text" name="adult_${i}_id_serial_no" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Cinsiyet</label>
                        <select name="adult_${i}_gender" class="form-control">
                            <option value="">Seçiniz...</option>
                            <option value="male">Erkek</option>
                            <option value="female">Kadın</option>
                            <option value="other">Diğer</option>
                        </select>
                    </div>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Çocuk misafir form alanlarını güncelle
function updateChildGuestFields() {
    const childCount = parseInt(document.getElementById('id_child_count').value) || 0;
    const container = document.getElementById('child_guests_container');
    const agesContainer = document.getElementById('child_ages_container');
    const agesInputs = document.getElementById('child_ages_inputs');
    
    if (childCount <= 0) {
        container.innerHTML = '<p class="text-muted" style="text-align: center; padding: 20px;">Çocuk sayısı girildikten sonra form alanları oluşturulacak</p>';
        agesContainer.style.display = 'none';
        return;
    }
    
    agesContainer.style.display = 'block';
    let agesHtml = '';
    let guestsHtml = '';
    
    for (let i = 1; i <= childCount; i++) {
        agesHtml += `
            <div class="form-group" style="margin-bottom: 5px;">
                <label>Çocuk ${i} Yaşı</label>
                <input type="number" class="form-control child-age-input" min="0" max="18" 
                       onchange="updateChildAgesJson()">
            </div>
        `;
        
        guestsHtml += `
            <div class="guest-form-group" style="border: 1px solid #dee2e6; padding: 10px; margin-bottom: 10px; border-radius: 3px; background: #fff;">
                <h5 style="margin-bottom: 10px; color: #2c5f8d;">Çocuk ${i}</h5>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div class="form-group">
                        <label>Ad *</label>
                        <input type="text" name="child_${i}_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Soyad *</label>
                        <input type="text" name="child_${i}_last_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Yaş</label>
                        <input type="number" name="child_${i}_age" class="form-control child-age-input" min="0" max="18" 
                               onchange="updateChildAgesJson()">
                    </div>
                    <div class="form-group">
                        <label>Cinsiyet</label>
                        <select name="child_${i}_gender" class="form-control">
                            <option value="">Seçiniz...</option>
                            <option value="male">Erkek</option>
                            <option value="female">Kız</option>
                            <option value="other">Diğer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>TC Kimlik No</label>
                        <input type="text" name="child_${i}_tc_no" class="form-control" maxlength="11">
                    </div>
                    <div class="form-group">
                        <label>Pasaport No</label>
                        <input type="text" name="child_${i}_passport_no" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Seri No</label>
                        <input type="text" name="child_${i}_passport_serial_no" class="form-control">
                    </div>
                </div>
            </div>
        `;
    }
    
    agesInputs.innerHTML = agesHtml;
    container.innerHTML = guestsHtml;
}

// Çocuk yaşlarını JSON'a çevir
function updateChildAgesJson() {
    const ages = [];
    document.querySelectorAll('.child-age-input').forEach(input => {
        if (input.value) {
            ages.push(parseInt(input.value));
        }
    });
    document.getElementById('child_ages_json').value = JSON.stringify(ages);
}

// Oda numaralarını güncelle
function updateRoomNumbers() {
    const roomId = document.getElementById('id_room').value;
    const roomNumberSelect = document.getElementById('id_room_number');
    
    if (!roomId) {
        roomNumberSelect.innerHTML = '<option value="">Önce oda tipi seçin</option>';
        return;
    }
    
    const container = document.querySelector('.content-body');
    const roomNumbersUrl = container?.dataset.roomNumbersUrl || '/reception/api/room-numbers/';
    fetch(`${roomNumbersUrl}?room_id=${roomId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        roomNumberSelect.innerHTML = '<option value="">Oda Numarası Seçiniz (Opsiyonel)</option>';
        data.room_numbers.forEach(roomNumber => {
            const option = document.createElement('option');
            option.value = roomNumber.id;
            option.textContent = `${roomNumber.number} (${roomNumber.status_display})`;
            roomNumberSelect.appendChild(option);
        });
    })
    .catch(error => {
        console.error('Error loading room numbers:', error);
    });
}

// Oda durumunu göster
function showRoomStatus() {
    const roomNumberId = document.getElementById('id_room_number').value;
    const statusDisplay = document.getElementById('room_status_display');
    const statusText = document.getElementById('room_status_text');
    
    if (!roomNumberId) {
        statusDisplay.style.display = 'none';
        return;
    }
    
    // Oda durumunu API'den al (şimdilik basit gösterim)
    const roomNumberSelect = document.getElementById('id_room_number');
    const selectedOption = roomNumberSelect.options[roomNumberSelect.selectedIndex];
    if (selectedOption) {
        const statusMatch = selectedOption.textContent.match(/\(([^)]+)\)/);
        if (statusMatch) {
            statusText.textContent = statusMatch[1];
            statusDisplay.style.display = 'block';
        }
    }
}

// İndirim alanlarını güncelle
function updateDiscountFields() {
    const discountType = document.getElementById('id_discount_type').value;
    const percentageGroup = document.getElementById('discount_percentage_group');
    const amountGroup = document.getElementById('discount_amount_group');
    
    if (discountType === 'percentage') {
        percentageGroup.style.display = 'block';
        amountGroup.style.display = 'none';
        document.getElementById('id_discount_amount').value = '0';
    } else if (discountType === 'fixed') {
        percentageGroup.style.display = 'none';
        amountGroup.style.display = 'block';
        document.getElementById('id_discount_percentage').value = '0';
    } else {
        percentageGroup.style.display = 'none';
        amountGroup.style.display = 'none';
    }
    
    calculateTotalAmount();
}

// Comp değişikliği
function handleCompChange() {
    const isComp = document.getElementById('id_is_comp').checked;
    const roomRate = document.getElementById('id_room_rate');
    
    if (isComp) {
        roomRate.value = '0.00';
        roomRate.readOnly = true;
        calculateTotalAmount();
    } else {
        roomRate.readOnly = false;
    }
}

// No-show neden alanını göster/gizle
function toggleNoShowReason() {
    const isNoShow = document.getElementById('id_is_no_show').checked;
    const reasonGroup = document.getElementById('no_show_reason_group');
    reasonGroup.style.display = isNoShow ? 'block' : 'none';
}

// Manuel fiyat toggle
function toggleManualPrice() {
    const isManual = document.getElementById('id_is_manual_price').checked;
    const roomRate = document.getElementById('id_room_rate');
    roomRate.readOnly = !isManual;
}

// Müşteri ara
function searchCustomer() {
    const query = document.getElementById('customer_search').value.trim();
    const resultsDiv = document.getElementById('customer_search_results');
    
    if (!query || query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    const container = document.querySelector('.content-body');
    const customerSearchUrl = container?.dataset.customerSearchUrl || '/reception/api/customer-search/';
    fetch(`${customerSearchUrl}?q=${encodeURIComponent(query)}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.customers && data.customers.length > 0) {
            let html = '<div style="max-height: 200px; overflow-y: auto;">';
            data.customers.forEach(customer => {
                html += `
                    <div onclick="selectCustomer(${customer.id}, '${customer.first_name}', '${customer.last_name}', '${customer.email}', '${customer.phone}', '${customer.tc_no || ''}', '${customer.address || ''}', '${customer.city || ''}', '${customer.country || ''}')" 
                         style="padding: 10px; border: 1px solid #dee2e6; margin-bottom: 5px; cursor: pointer; border-radius: 3px; background: #fff;"
                         onmouseover="this.style.background='#e8f4f8'" 
                         onmouseout="this.style.background='#fff'">
                        <strong>${customer.full_name}</strong><br>
                        <small>${customer.email || ''} | ${customer.phone || ''} | ${customer.tc_no || ''}</small>
                    </div>
                `;
            });
            html += '</div>';
            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';
        } else {
            resultsDiv.innerHTML = '<p style="text-align: center; padding: 10px; color: #999;">Müşteri bulunamadı</p>';
            resultsDiv.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error searching customer:', error);
    });
}

// Müşteri seç
function selectCustomer(id, firstName, lastName, email, phone, tcNo, address, city, country) {
    document.getElementById('id_customer').value = id;
    document.getElementById('customer_search').value = `${firstName} ${lastName}`;
    document.getElementById('customer_search_results').style.display = 'none';
    
    const infoDiv = document.getElementById('customer_info_display');
    const infoText = document.getElementById('customer_info_text');
    infoText.innerHTML = `
        <strong>${firstName} ${lastName}</strong><br>
        ${email ? 'Email: ' + email + '<br>' : ''}
        ${phone ? 'Telefon: ' + phone + '<br>' : ''}
        ${tcNo ? 'TC No: ' + tcNo + '<br>' : ''}
        ${address ? 'Adres: ' + address + '<br>' : ''}
        ${city ? city + ', ' : ''}${country || ''}
    `;
    infoDiv.style.display = 'block';
}

// Rezervasyon kaydet
function saveReservation() {
    const form = document.getElementById('reservationForm');
    const formData = new FormData(form);
    
    // Misafir bilgilerini topla
    const adultCount = parseInt(document.getElementById('id_adult_count').value) || 0;
    const childCount = parseInt(document.getElementById('id_child_count').value) || 0;
    
    // Form validation
    if (!formData.get('check_in_date') || !formData.get('check_out_date')) {
        alert('Lütfen giriş ve çıkış tarihlerini seçin.');
        return;
    }
    
    if (!formData.get('room')) {
        alert('Lütfen oda tipi seçin.');
        return;
    }
    
    if (!formData.get('customer')) {
        alert('Lütfen müşteri seçin veya yeni müşteri oluşturun.');
        return;
    }
    
    // AJAX ile gönder
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeReservationModal();
            // Sayfayı yenile veya rezervasyon listesine yönlendir
            const container = document.querySelector('.content-body');
            const reservationListUrl = container?.dataset.reservationListUrl || '/reception/reservations/';
            window.location.href = data.redirect_url || reservationListUrl;
        } else {
            alert(data.message || 'Rezervasyon kaydedilemedi.');
            if (data.errors) {
                console.error('Form errors:', data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Error saving reservation:', error);
        alert('Rezervasyon kaydedilirken hata oluştu.');
    });
}

// Debounce fonksiyonu
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Modal dışına tıklanınca kapat
document.addEventListener('click', function(e) {
    const modal = document.getElementById('reservationModal');
    if (modal && e.target === modal) {
        closeReservationModal();
    }
});

