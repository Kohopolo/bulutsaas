/**
 * Çoklu Oda Rezervasyon Yöneticisi
 * 
 * Birden fazla oda için rezervasyon oluşturma modülü
 * - Her oda için ayrı oda numarası seçimi
 * - Her oda için ayrı misafir bilgileri
 * - Müşteri bilgileri ortak kullanılır
 * - Her oda için benzersiz rezervasyon kodu
 */

class MultiRoomManager {
    constructor(options = {}) {
        this.container = options.container || '#roomsContainer';
        this.rooms = [];
        this.roomCounter = 1;
        this.template = options.template || this.getDefaultTemplate();
        this.onRoomAdded = options.onRoomAdded || (() => {});
        this.onRoomRemoved = options.onRoomRemoved || (() => {});
        this.onRoomUpdated = options.onRoomUpdated || (() => {});
        
        this.init();
    }
    
    init() {
        // İlk odayı ekle
        this.addRoom();
        
        // Event listeners
        this.attachEventListeners();
    }
    
    attachEventListeners() {
        // Oda ekleme butonu
        $(document).on('click', '.add-room-btn', (e) => {
            e.preventDefault();
            this.addRoom();
        });
        
        // Oda silme butonu
        $(document).on('click', '.remove-room-btn', (e) => {
            e.preventDefault();
            const roomId = $(e.currentTarget).data('room-id');
            this.removeRoom(roomId);
        });
        
        // Oda tipi değişimi
        $(document).on('change', '.room-type-select', (e) => {
            const roomId = $(e.currentTarget).data('room-id');
            const odaTipiId = $(e.currentTarget).val();
            this.loadAvailableRooms(roomId, odaTipiId);
        });
        
        // Oda numarası değişimi
        $(document).on('change', '.room-number-select', (e) => {
            const roomId = $(e.currentTarget).data('room-id');
            this.updateRoomInfo(roomId);
        });
        
        // Misafir sayısı değişimi
        $(document).on('change', '.guest-count-select', (e) => {
            const roomId = $(e.currentTarget).data('room-id');
            this.updateGuestFields(roomId);
        });
    }
    
    addRoom() {
        const roomId = this.roomCounter++;
        const roomData = {
            id: roomId,
            roomTypeId: null,
            roomNumberId: null,
            adultCount: 1,
            childCount: 0,
            guests: []
        };
        
        this.rooms.push(roomData);
        
        // Şablonu render et
        const roomHtml = this.template(roomData, this.rooms.length);
        $(this.container).append(roomHtml);
        
        // İlk oda tipini seç ve odaları yükle
        const firstRoomType = $(`.room-card[data-room-id="${roomId}"] .room-type-select option:first`).val();
        if (firstRoomType) {
            $(`.room-card[data-room-id="${roomId}"] .room-type-select`).val(firstRoomType).trigger('change');
        }
        
        // Callback
        this.onRoomAdded(roomData);
        
        // Buton durumlarını güncelle
        this.updateButtons();
        
        return roomId;
    }
    
    removeRoom(roomId) {
        if (this.rooms.length <= 1) {
            alert('En az bir oda olmalıdır!');
            return;
        }
        
        // Array'den çıkar
        this.rooms = this.rooms.filter(r => r.id !== roomId);
        
        // DOM'dan çıkar
        $(`.room-card[data-room-id="${roomId}"]`).fadeOut(300, function() {
            $(this).remove();
        });
        
        // Callback
        this.onRoomRemoved(roomId);
        
        // Buton durumlarını güncelle
        this.updateButtons();
        
        // Oda numaralarını güncelle
        this.updateRoomNumbers();
    }
    
    updateButtons() {
        const roomCount = this.rooms.length;
        
        // Tek oda varsa silme butonunu gizle
        if (roomCount === 1) {
            $('.remove-room-btn').hide();
        } else {
            $('.remove-room-btn').show();
        }
    }
    
    updateRoomNumbers() {
        $('.room-card').each((index, card) => {
            $(card).find('.room-number-label').text(`Oda ${index + 1}`);
        });
    }
    
    async loadAvailableRooms(roomId, odaTipiId) {
        const giris = $('#giris_tarihi').val();
        const cikis = $('#cikis_tarihi').val();
        
        if (!giris || !cikis) {
            alert('Lütfen önce giriş ve çıkış tarihlerini seçin!');
            return;
        }
        
        const $select = $(`.room-card[data-room-id="${roomId}"] .room-number-select`);
        const $loading = $(`.room-card[data-room-id="${roomId}"] .loading-rooms`);
        
        $loading.show();
        $select.html('<option value="">Yükleniyor...</option>').prop('disabled', true);
        
        try {
            const response = await $.ajax({
                url: '../ajax/get_musait_odalar.php',
                method: 'POST',
                data: {
                    oda_tipi_id: odaTipiId,
                    giris_tarihi: giris,
                    cikis_tarihi: cikis
                }
            });
            
            const data = typeof response === 'string' ? JSON.parse(response) : response;
            
            if (data.success && data.odalar && data.odalar.length > 0) {
                let options = '<option value="">Oda Numarası Seçin</option>';
                data.odalar.forEach(oda => {
                    options += `<option value="${oda.id}">${oda.oda_numarasi}</option>`;
                });
                $select.html(options).prop('disabled', false);
            } else {
                $select.html('<option value="">Müsait oda yok</option>');
            }
        } catch (error) {
            console.error('Oda yükleme hatası:', error);
            $select.html('<option value="">Hata oluştu</option>');
        } finally {
            $loading.hide();
        }
    }
    
    updateRoomInfo(roomId) {
        const room = this.rooms.find(r => r.id === roomId);
        if (!room) return;
        
        const odaTipiId = $(`.room-card[data-room-id="${roomId}"] .room-type-select`).val();
        const odaNumarasiId = $(`.room-card[data-room-id="${roomId}"] .room-number-select`).val();
        
        room.roomTypeId = odaTipiId;
        room.roomNumberId = odaNumarasiId;
        
        // Callback
        this.onRoomUpdated(room);
    }
    
    updateGuestFields(roomId) {
        const room = this.rooms.find(r => r.id === roomId);
        if (!room) return;
        
        const yetiskinSayisi = parseInt($(`.room-card[data-room-id="${roomId}"] .adult-count`).val()) || 1;
        const cocukSayisi = parseInt($(`.room-card[data-room-id="${roomId}"] .child-count`).val()) || 0;
        
        room.adultCount = yetiskinSayisi;
        room.childCount = cocukSayisi;
        
        // Misafir alanlarını oluştur
        this.renderGuestFields(roomId, yetiskinSayisi, cocukSayisi);
    }
    
    renderGuestFields(roomId, adultCount, childCount) {
        const $container = $(`.room-card[data-room-id="${roomId}"] .guest-details`);
        let html = '';
        
        // Yetişkinler
        html += '<div class="row"><div class="col-12"><h6 class="mt-3 mb-2"><i class="fas fa-user me-2"></i>Yetişkin Misafirler</h6></div></div>';
        for (let i = 0; i < adultCount; i++) {
            html += `
                <div class="row mb-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" 
                               name="room_${roomId}_adult_ad_${i}" 
                               placeholder="${i + 1}. Yetişkin Adı *" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" 
                               name="room_${roomId}_adult_soyad_${i}" 
                               placeholder="Soyadı *" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" 
                               name="room_${roomId}_adult_tc_${i}" 
                               placeholder="TC Kimlik No" 
                               maxlength="11" 
                               pattern="[0-9]{11}">
                    </div>
                </div>
            `;
        }
        
        // Çocuklar
        if (childCount > 0) {
            html += '<div class="row"><div class="col-12"><h6 class="mt-3 mb-2"><i class="fas fa-child me-2"></i>Çocuk Misafirler</h6></div></div>';
            for (let i = 0; i < childCount; i++) {
                html += `
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" 
                                   name="room_${roomId}_child_ad_${i}" 
                                   placeholder="${i + 1}. Çocuk Adı *" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" 
                                   name="room_${roomId}_child_soyad_${i}" 
                                   placeholder="Soyadı *" required>
                        </div>
                        <div class="col-md-4">
                            <input type="number" class="form-control form-control-sm" 
                                   name="room_${roomId}_child_yas_${i}" 
                                   placeholder="Yaş" 
                                   min="0" max="17" required>
                        </div>
                    </div>
                `;
            }
        }
        
        $container.html(html);
    }
    
    getRoomData() {
        return this.rooms.map(room => {
            const roomCard = $(`.room-card[data-room-id="${room.id}"]`);
            
            return {
                id: room.id,
                oda_tipi_id: roomCard.find('.room-type-select').val(),
                oda_numarasi_id: roomCard.find('.room-number-select').val(),
                yetiskin_sayisi: roomCard.find('.adult-count').val(),
                cocuk_sayisi: roomCard.find('.child-count').val(),
                guests: this.getGuestData(room.id)
            };
        });
    }
    
    getGuestData(roomId) {
        const guests = {
            adults: [],
            children: []
        };
        
        const adultCount = parseInt($(`.room-card[data-room-id="${roomId}"] .adult-count`).val()) || 1;
        const childCount = parseInt($(`.room-card[data-room-id="${roomId}"] .child-count`).val()) || 0;
        
        // Yetişkinler
        for (let i = 0; i < adultCount; i++) {
            guests.adults.push({
                ad: $(`input[name="room_${roomId}_adult_ad_${i}"]`).val(),
                soyad: $(`input[name="room_${roomId}_adult_soyad_${i}"]`).val(),
                tc_kimlik: $(`input[name="room_${roomId}_adult_tc_${i}"]`).val()
            });
        }
        
        // Çocuklar
        for (let i = 0; i < childCount; i++) {
            guests.children.push({
                ad: $(`input[name="room_${roomId}_child_ad_${i}"]`).val(),
                soyad: $(`input[name="room_${roomId}_child_soyad_${i}"]`).val(),
                yas: $(`input[name="room_${roomId}_child_yas_${i}"]`).val()
            });
        }
        
        return guests;
    }
    
    validate() {
        const errors = [];
        
        this.rooms.forEach((room, index) => {
            const roomCard = $(`.room-card[data-room-id="${room.id}"]`);
            const odaTipiId = roomCard.find('.room-type-select').val();
            const odaNumarasiId = roomCard.find('.room-number-select').val();
            
            if (!odaTipiId) {
                errors.push(`Oda ${index + 1}: Oda tipi seçilmedi`);
            }
            
            if (!odaNumarasiId) {
                errors.push(`Oda ${index + 1}: Oda numarası seçilmedi`);
            }
        });
        
        return {
            valid: errors.length === 0,
            errors: errors
        };
    }
    
    getDefaultTemplate() {
        return (roomData, roomNumber) => `
            <div class="card room-card mb-3" data-room-id="${roomData.id}">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 room-number-label">
                        <i class="fas fa-bed me-2"></i>Oda ${roomNumber}
                    </h6>
                    <button type="button" class="btn btn-sm btn-danger remove-room-btn" data-room-id="${roomData.id}">
                        <i class="fas fa-times"></i> Odayı Kaldır
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Oda Tipi *</label>
                            <select class="form-select room-type-select" data-room-id="${roomData.id}" name="room_${roomData.id}_oda_tipi" required>
                                <option value="">Oda Tipi Seçin</option>
                                ${this.getRoomTypeOptions()}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Oda Numarası *</label>
                            <div class="loading-rooms" style="display:none;">
                                <small class="text-muted">
                                    <i class="fas fa-spinner fa-spin me-1"></i>Müsait odalar yükleniyor...
                                </small>
                            </div>
                            <select class="form-select room-number-select" data-room-id="${roomData.id}" name="room_${roomData.id}_oda_numarasi" required>
                                <option value="">Önce oda tipi seçin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Yetişkin Sayısı *</label>
                            <select class="form-select adult-count guest-count-select" data-room-id="${roomData.id}" name="room_${roomData.id}_yetiskin_sayisi" required>
                                ${[1, 2, 3, 4, 5, 6].map(i => `<option value="${i}" ${i === 1 ? 'selected' : ''}>${i}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Çocuk Sayısı</label>
                            <select class="form-select child-count guest-count-select" data-room-id="${roomData.id}" name="room_${roomData.id}_cocuk_sayisi">
                                ${[0, 1, 2, 3, 4].map(i => `<option value="${i}">${i}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    
                    <div class="guest-details">
                        <!-- Misafir detayları buraya gelecek -->
                    </div>
                </div>
            </div>
        `;
    }
    
    getRoomTypeOptions() {
        // Global oda tipleri değişkeninden al (sayfa yüklendiğinde set edilecek)
        if (typeof window.odaTipleri !== 'undefined') {
            return window.odaTipleri.map(ot => 
                `<option value="${ot.id}">${ot.oda_tipi_adi}</option>`
            ).join('');
        }
        return '';
    }
}

// Global erişim için
window.MultiRoomManager = MultiRoomManager;



