/**
 * Çoklu Oda Rezervasyon - Fiyat Hesaplama Modülü
 * AJAX ile gerçek fiyat hesaplama (sezonluk, kampanya, ücretsiz çocuk vs.)
 */

// Fiyat hesaplama cache (aynı parametreler için tekrar AJAX çağrısı yapma)
const priceCache = {};

/**
 * AJAX ile oda fiyatını hesapla
 * @param {number} roomId - Oda kartı ID
 * @param {function} callback - Callback fonksiyonu (price, priceData)
 */
function calculateRoomPriceAjax(roomId, callback) {
    console.log('=== FİYAT HESAPLAMA (AJAX) ===');
    console.log('Room ID:', roomId);
    
    const odaTipiId = $(`.room-card[data-room-id="${roomId}"] .room-type-select`).val();
    const yetiskinSayisi = $(`.room-card[data-room-id="${roomId}"] .adult-count`).val() || 1;
    const cocukSayisi = $(`.room-card[data-room-id="${roomId}"] .child-count`).val() || 0;
    
    let giris, cikis;
    if (typeof dateMode !== 'undefined' && dateMode === 'separate') {
        giris = $(`.room-card[data-room-id="${roomId}"] .room-giris`).val();
        cikis = $(`.room-card[data-room-id="${roomId}"] .room-cikis`).val();
    } else {
        giris = $('#giris_tarihi').val();
        cikis = $('#cikis_tarihi').val();
    }
    
    console.log('Parametreler:', {odaTipiId, giris, cikis, yetiskinSayisi, cocukSayisi});
    
    if (!odaTipiId || !giris || !cikis) {
        console.log('❌ Eksik veri, fiyat 0');
        if (callback) callback(0, null);
        return;
    }
    
    // Çocuk yaşlarını topla
    const cocukYaslari = [];
    for (let i = 0; i < parseInt(cocukSayisi); i++) {
        const yas = $(`.room-card[data-room-id="${roomId}"] input[name="room_${roomId}_child_yas_${i}"]`).val();
        if (yas && yas > 0) {
            cocukYaslari.push(parseInt(yas));
        }
    }
    
    // Cache key oluştur
    const cacheKey = `${roomId}_${odaTipiId}_${giris}_${cikis}_${yetiskinSayisi}_${cocukSayisi}_${cocukYaslari.join('_')}`;
    
    // Cache'de varsa direkt döndür
    if (priceCache[cacheKey]) {
        console.log('✅ Cache\'den döndürüldü');
        if (callback) callback(priceCache[cacheKey].price, priceCache[cacheKey].data);
        return;
    }
    
    // CSRF token al
    const csrfToken = $('input[name="csrf_token"]').first().val();
    if (!csrfToken) {
        console.error('❌ CSRF token bulunamadı');
        if (callback) callback(0, null);
        return;
    }
    
    // AJAX ile fiyat hesapla
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('oda_tipi_id', odaTipiId);
    formData.append('giris_tarihi', giris);
    formData.append('cikis_tarihi', cikis);
    formData.append('yetiskin_sayisi', yetiskinSayisi);
    formData.append('cocuk_sayisi', cocukSayisi);
    
    // Eğer çocuk varsa ve yaş bilgisi yoksa, varsayılan yaş ekle
    if (cocukSayisi > 0 && cocukYaslari.length === 0) {
        for (let i = 0; i < cocukSayisi; i++) {
            formData.append('cocuk_yaslari[]', 5); // Varsayılan yaş
        }
    } else {
        cocukYaslari.forEach(yas => formData.append('cocuk_yaslari[]', yas));
    }
    
    console.log('AJAX isteği gönderiliyor...');
    
    fetch('../ajax/calculate-price.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Fiyat AJAX Response:', data);
        
        if (data.success) {
            const price = data.data.total_price;
            console.log('✅ TOPLAM FİYAT:', price);
            
            // Cache'e ekle
            priceCache[cacheKey] = {
                price: price,
                data: data.data
            };
            
            if (callback) callback(price, data.data);
        } else {
            console.error('❌ Fiyat hesaplama hatası:', data.error);
            if (callback) callback(0, null);
        }
    })
    .catch(error => {
        console.error('❌ AJAX hatası:', error);
        if (callback) callback(0, null);
    });
}

/**
 * Oda fiyatını güncelle ve göster
 * @param {number} roomId - Oda kartı ID
 */
function updateRoomPrice(roomId) {
    calculateRoomPriceAjax(roomId, function(price, priceData) {
        const $priceDisplay = $(`.room-card[data-room-id="${roomId}"] .room-price-display`);
        
        if (price > 0 && priceData) {
            let html = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-tag me-2"></i>
                        <strong>Oda Tutarı:</strong> 
                        <span class="text-success fw-bold">${price.toLocaleString('tr-TR')} ₺</span>
                    </div>
                </div>
                <small class="text-muted">${priceData.nights} gece - Ortalama: ${priceData.average_price.toLocaleString('tr-TR')} ₺/gece</small>
            `;
            
            // Ücretsiz çocuk varsa göster
            if (priceData.free_children_count > 0) {
                html += `<br><small class="text-info"><i class="fas fa-child me-1"></i>${priceData.free_children_count} çocuk ücretsiz</small>`;
            }
            
            // Minimum yetişkin şartı karşılanmadıysa uyar
            if (priceData.minimum_adult_requirement && !priceData.minimum_adult_requirement_met) {
                html += `<br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Minimum ${priceData.minimum_adult_requirement} yetişkin gerekli</small>`;
            }
            
            $priceDisplay.html(html).show();
        } else {
            $priceDisplay.hide();
        }
        
        // Toplam fiyatı güncelle
        updateTotalPrice();
    });
}

/**
 * Tüm odaların toplam fiyatını hesapla
 */
function calculateTotalPrice() {
    return new Promise((resolve) => {
        const roomCards = $('.room-card');
        if (roomCards.length === 0) {
            resolve(0);
            return;
        }
        
        let total = 0;
        let completed = 0;
        
        roomCards.each(function() {
            const roomId = $(this).data('room-id');
            
            calculateRoomPriceAjax(roomId, function(price) {
                total += price;
                completed++;
                
                // Tüm odalar hesaplandıysa resolve et
                if (completed === roomCards.length) {
                    console.log('Toplam fiyat hesaplandı:', total);
                    resolve(total);
                }
            });
        });
    });
}

/**
 * Toplam fiyat gösterimini güncelle
 */
function updateTotalPrice() {
    calculateTotalPrice().then(total => {
        $('#totalPriceDisplay').html(`
            <i class="fas fa-calculator me-2"></i>
            <strong>TOPLAM TUTAR:</strong> 
            <span class="price-amount">${total.toLocaleString('tr-TR')} ₺</span>
        `);
    });
}



