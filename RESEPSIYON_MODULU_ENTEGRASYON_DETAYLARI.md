# Resepsiyon Modülü - Entegrasyon Detayları

**Tarih:** 12 Kasım 2025  
**Amaç:** Resepsiyon modülünün diğer modüllerle entegrasyon detaylarını açıklamak

---

## 🔗 Modül Entegrasyonları

### 1. Hotels Modülü Entegrasyonu

#### Oda Bilgileri
- **Model:** `Room`, `RoomType`, `RoomNumber`
- **Kullanım:** Oda listesi, oda durumları, oda seçimi
- **Filtreleme:** `hotel=active_hotel`, `is_active=True`, `is_deleted=False`

#### Oda Tipleri
- **Model:** `RoomType`
- **Kullanım:** Rezervasyon formunda oda tipi seçimi
- **Filtreleme:** `hotel=active_hotel`, `is_active=True`

#### Pansiyon Tipleri
- **Model:** `BoardType`
- **Kullanım:** Rezervasyon formunda pansiyon tipi seçimi
- **Filtreleme:** `hotel=active_hotel`, `is_active=True`

#### Fiyatlandırma
- **Model:** `RoomPrice`
- **Kullanım:** Rezervasyon fiyat hesaplama
- **Method:** `RoomPrice.calculate_price()`
- **Utility:** `apps.tenant_apps.core.utils.calculate_dynamic_price`

**Fiyat Hesaplama Akışı:**
```python
# 1. RoomPrice modelinden temel fiyat al
room_price = RoomPrice.objects.get(room_type=selected_room_type, is_active=True)

# 2. calculate_price metodunu çağır
price_result = room_price.calculate_price(
    check_date=check_in_date,
    adults=adults_count,
    children=children_count,
    child_ages=child_ages_list,
    board_type_id=selected_board_type_id,
    agency_id=agency_id,  # varsa
    channel_name=channel_name,  # varsa
    nights=night_count
)

# 3. Sonuç: {
#     'total_price': Decimal,
#     'adult_price': Decimal,
#     'child_price': Decimal,
#     'free_children_count': int,
#     'paid_children_count': int,
#     'applied_price_type': str,
#     'breakdown': Dict
# }
```

#### Oda Durumları
- **Model:** `RoomNumber`
- **Kullanım:** Oda durum panosu (Room Rack)
- **Durumlar:** Available, Occupied, Housekeeping, Out of Order, Reserved, Maintenance

---

### 2. Customers Modülü Entegrasyonu

#### Müşteri Bilgileri
- **Model:** `Customer`
- **Kullanım:** Müşteri arama, müşteri ekleme, müşteri detayları
- **Arama Alanları:** İsim, telefon, email, kimlik no

#### Müşteri Geçmişi
- **Model:** `Customer` (ilişkili rezervasyonlar)
- **Kullanım:** History Card görüntüleme
- **Bilgiler:** Geçmiş konaklamalar, rezervasyonlar, ödemeler

#### VIP Müşteriler
- **Model:** `Customer` (VIP işaretli)
- **Kullanım:** VIP müşteri listesi, özel işlemler

**Entegrasyon Noktaları:**
- Rezervasyon formunda müşteri seçimi
- Check-in formunda müşteri bilgileri
- Müşteri arama modal'ı
- Yeni müşteri ekleme (Customers modülüne kaydedilir)

---

### 3. Finance Modülü Entegrasyonu

#### Ödeme İşlemleri
- **Model:** `Transaction`, `Payment`
- **Kullanım:** Ödeme alma, ödeme geçmişi
- **Kayıt:** Tüm ödemeler Finance modülüne kaydedilir

#### Fatura Yazdırma
- **Model:** `Invoice`
- **Kullanım:** Fatura oluşturma ve yazdırma
- **Entegrasyon:** Finance modülü fatura şablonları kullanılır

#### Hesap Yönetimi (Folio)
- **Model:** `Folio`, `FolioItem`
- **Kullanım:** Müşteri hesap özeti, harcamalar, ödemeler
- **Güncelleme:** Check-in/out, ödeme, harcama işlemlerinde otomatik güncellenir

---

### 4. Reservations Modülü Entegrasyonu (Gelecek)

#### Rezervasyon Kaynakları
- **Satış Modülü:** Satış ekibinden gelen rezervasyonlar
- **Call Center Modülü:** Telefon ile gelen rezervasyonlar
- **Acente Modülü:** Acentelerden gelen rezervasyonlar
- **Online Rezervasyon:** Web sitesinden gelen rezervasyonlar

**Entegrasyon Yapısı:**
- Tüm rezervasyon kaynakları ortak bir `Reservation` modeline kaydedilir
- Resepsiyon modülü tüm kaynaklardan gelen rezervasyonları görüntüleyebilir
- Rezervasyon kaynağı bilgisi saklanır (source: 'sales', 'call_center', 'agency', 'online', 'reception')

---

### 5. Housekeeping Modülü Entegrasyonu (Gelecek)

#### Oda Temizlik Durumu
- **Model:** `HousekeepingStatus`
- **Kullanım:** Oda durum panosunda temizlik durumu gösterimi
- **Senkronizasyon:** Real-time güncelleme (WebSocket)

#### Temizlik Bildirimleri
- **Event:** Oda temizlendiğinde bildirim
- **Action:** Oda durumu otomatik "Available" olur

---

### 6. Bakım Modülü Entegrasyonu (Gelecek)

#### Oda Arıza Durumu
- **Model:** `MaintenanceStatus`
- **Kullanım:** Oda durum panosunda arıza durumu gösterimi
- **Senkronizasyon:** Real-time güncelleme (WebSocket)

#### Bakım Bildirimleri
- **Event:** Oda bakımı tamamlandığında bildirim
- **Action:** Oda durumu otomatik "Available" olur

---

### 7. Ödeme Yöntemleri Modülü Entegrasyonu (Gelecek)

#### POS Cihaz Entegrasyonu
- **Kullanım:** Fiziksel POS cihazlarından ödeme alma
- **API:** Ödeme yöntemleri modülü API'si kullanılır

#### Kredi Kartı Terminal Entegrasyonu
- **Kullanım:** Kredi kartı terminalinden ödeme alma
- **API:** Ödeme yöntemleri modülü API'si kullanılır

---

## 🔧 Global Fiyatlama Utility Kullanımı

### Utility Fonksiyonu

**Dosya:** `apps/tenant_apps/core/utils.py`  
**Fonksiyon:** `calculate_dynamic_price()`

### Kullanım Senaryosu

**1. Rezervasyon Formunda:**
```python
# Oda tipi seçildiğinde
room_type = RoomType.objects.get(id=room_type_id, hotel=active_hotel)
room_price = RoomPrice.objects.filter(room_type=room_type, is_active=True).first()

# Tarih ve kişi bilgileri girildiğinde
price_result = room_price.calculate_price(
    check_date=check_in_date,
    adults=adults_count,
    children=children_count,
    child_ages=child_ages_list,
    board_type_id=board_type_id,
    nights=night_count
)

# Sonuç frontend'e gönderilir
return JsonResponse({
    'total_price': str(price_result['total_price']),
    'adult_price': str(price_result['adult_price']),
    'child_price': str(price_result['child_price']),
    'breakdown': price_result['breakdown']
})
```

**2. Fiyat Faktörleri:**
- ✅ Sezonluk fiyatlar (tarih aralığında)
- ✅ Özel fiyatlar (tarih bazlı, gün bazlı)
- ✅ Kampanya fiyatları (tarih aralığında)
- ✅ Acente fiyatları (acente ID ile)
- ✅ Kanal fiyatları (kanal adı ile)
- ✅ Yetişkin çarpanları (kişi sayısına göre)
- ✅ Ücretsiz çocuk kuralları (yaş ve yetişkin sayısına göre)
- ✅ Toplam indirim oranı

**3. Öncelik Sırası:**
1. Campaign Price (en yüksek öncelik)
2. Seasonal Price
3. Special Price
4. Agency Price
5. Channel Price
6. Base Price (en düşük öncelik)

---

## 🔑 Dijital Anahtar Sistemi

### KeyCard Modeli

**Dosya:** `apps/tenant_apps/reception/models.py`

**Özellikler:**
- Benzersiz kart numarası
- Şifreli kart kodu (RFID/NFC için)
- Erişim seviyeleri
- Geçerlilik tarihleri
- Yazdırma durumu

### Kullanım Akışı

**1. Check-In Sırasında:**
```python
# Anahtar kartı oluştur
keycard = KeyCard.objects.create(
    reservation=reservation,
    guest=guest,
    room=room,
    hotel=hotel,
    card_number=generate_unique_card_number(),
    card_code=generate_encrypted_code(),
    access_level='room_only',  # veya 'hotel_access', 'full_access'
    valid_from=check_in_datetime,
    valid_until=check_out_datetime
)
```

**2. Konaklama Belgesi Yazdırma:**
- Anahtar kartı bilgileri konaklama belgesine eklenir
- Kart numarası, geçerlilik tarihleri yazdırılır

**3. Check-Out Sırasında:**
```python
# Anahtar kartı iptal et
keycard.is_active = False
keycard.save()
```

### İleride Fiziksel Kart Yazıcıları

**Entegrasyon:**
- RFID/NFC kart yazıcıları ile entegrasyon
- Kart kodunu fiziksel karta yazma
- Otomatik kart yazdırma

---

## 🔄 Real-time Güncellemeler (WebSocket)

### Django Channels Yapısı

**Kurulum:**
```python
# settings.py
INSTALLED_APPS = [
    ...
    'channels',
    'channels_redis',
]

ASGI_APPLICATION = 'config.asgi.application'

CHANNEL_LAYERS = {
    'default': {
        'BACKEND': 'channels_redis.core.RedisChannelLayer',
        'CONFIG': {
            "hosts": [('127.0.0.1', 6379)],
        },
    },
}
```

### WebSocket Consumer'ları

**1. Room Status Consumer:**
```python
# apps/tenant_apps/reception/consumers.py
class RoomStatusConsumer(AsyncWebsocketConsumer):
    async def connect(self):
        self.room_group_name = f'hotel_{hotel_id}_rooms'
        await self.channel_layer.group_add(
            self.room_group_name,
            self.channel_name
        )
        await self.accept()
    
    async def room_status_update(self, event):
        await self.send(text_data=json.dumps({
            'type': 'room_status_update',
            'room_id': event['room_id'],
            'status': event['status'],
            'guest_name': event.get('guest_name'),
        }))
```

**2. Booking Update Consumer:**
```python
class BookingUpdateConsumer(AsyncWebsocketConsumer):
    async def connect(self):
        self.booking_group_name = f'hotel_{hotel_id}_bookings'
        await self.channel_layer.group_add(
            self.booking_group_name,
            self.channel_name
        )
        await self.accept()
    
    async def booking_update(self, event):
        await self.send(text_data=json.dumps({
            'type': 'booking_update',
            'booking_id': event['booking_id'],
            'action': event['action'],  # created, updated, cancelled, checked_in, checked_out
        }))
```

### Frontend WebSocket Bağlantısı

```javascript
// static/js/reception.js
const roomSocket = new WebSocket(
    'ws://' + window.location.host + '/ws/reception/rooms/'
);

roomSocket.onmessage = function(e) {
    const data = JSON.parse(e.data);
    if (data.type === 'room_status_update') {
        updateRoomStatus(data.room_id, data.status);
    }
};
```

---

## 📊 Veri Akış Diyagramları

### Rezervasyon Oluşturma Akışı

```
1. Kullanıcı "Yeni Rezervasyon" butonuna tıklar
   ↓
2. Modal açılır, form yüklenir
   ↓
3. Müşteri seçimi (Customers modülünden)
   ↓
4. Oda tipi seçimi (Hotels modülünden)
   ↓
5. Tarih ve kişi bilgileri girilir
   ↓
6. AJAX ile fiyat hesaplama (Global utility)
   - RoomPrice.calculate_price() çağrılır
   - calculate_dynamic_price() kullanılır
   ↓
7. Toplam fiyat gösterilir
   ↓
8. Form gönderilir
   ↓
9. Rezervasyon kaydedilir (Reservations modülüne)
   ↓
10. WebSocket ile diğer kullanıcılara bildirim gönderilir
```

### Check-In Akışı

```
1. Kullanıcı "Check-In" butonuna tıklar
   ↓
2. Rezervasyon seçilir veya doğrudan check-in yapılır
   ↓
3. Modal açılır, rezervasyon bilgileri yüklenir
   ↓
4. Müşteri bilgileri kontrol edilir (Customers modülünden)
   ↓
5. Oda ataması yapılır (Hotels modülünden müsait oda)
   ↓
6. Ödeme bilgileri girilir (Finance modülüne kaydedilir)
   ↓
7. Dijital anahtar kartı oluşturulur (KeyCard modeli)
   ↓
8. Check-in kaydedilir
   ↓
9. Oda durumu "Occupied" olarak güncellenir
   ↓
10. WebSocket ile oda durumu güncellenir
   ↓
11. Konaklama belgesi ve anahtar kartı yazdırılabilir
```

---

## 🎯 Sonuç

Bu entegrasyon detayları, Resepsiyon modülünün diğer modüllerle nasıl çalışacağını açıklamaktadır. Tüm entegrasyonlar modüler yapıda tasarlanmıştır, böylece gelecekte yeni modüller eklendiğinde kolayca entegre edilebilir.

**Önemli Noktalar:**
- ✅ Global fiyatlama utility kullanımı zorunludur
- ✅ WebSocket baştan uygulanacak
- ✅ Dijital anahtar sistemi check-in/out ile entegre
- ✅ Tüm modüller arası veri akışı senkronize
- ✅ Real-time güncellemeler kritik

