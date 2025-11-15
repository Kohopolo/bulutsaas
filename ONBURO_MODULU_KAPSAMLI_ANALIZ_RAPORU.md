# Önbüro Modülü Kapsamlı Analiz ve Tasarım Raporu

**Tarih:** 2025-11-13  
**Modül:** Resepsiyon (Ön Büro) - Reception Module  
**Versiyon:** 2.0 - Kapsamlı Geliştirme

---

## 📋 İçindekiler

1. [Genel Bakış](#genel-bakış)
2. [Sektörel Analiz](#sektörel-analiz)
3. [Modül Yapısı](#modül-yapısı)
4. [Rezervasyon Sistemi](#rezervasyon-sistemi)
5. [Oda Yönetimi](#oda-yönetimi)
6. [Yetki Sistemi](#yetki-sistemi)
7. [Entegrasyonlar](#entegrasyonlar)
8. [Raporlama](#raporlama)
9. [Teknik Detaylar](#teknik-detaylar)
10. [Uygulama Planı](#uygulama-planı)

---

## 🎯 Genel Bakış

### Modül Amacı
Önbüro modülü, otel işletmelerinin resepsiyon operasyonlarını yönetmek için tasarlanmış kapsamlı bir sistemdir. Rezervasyon yönetimi, oda durumu takibi, check-in/check-out işlemleri, ödeme yönetimi ve raporlama gibi tüm önbüro işlemlerini tek bir platformda toplar.

### Temel Özellikler
- ✅ **Rezervasyon Yönetimi**: Detaylı rezervasyon oluşturma, düzenleme, iptal
- ✅ **Oda Planı**: Görsel oda durumu takibi
- ✅ **Check-in/Check-out**: Hızlı ve güvenli giriş-çıkış işlemleri
- ✅ **Ödeme Yönetimi**: Ön ödeme, kalan tutar takibi, ödeme ekleme
- ✅ **Müşteri Yönetimi**: CRM entegrasyonu ile müşteri bilgileri
- ✅ **Voucher Sistemi**: Dinamik voucher şablonları
- ✅ **Bildirimler**: WhatsApp ve SMS entegrasyonu
- ✅ **Raporlama**: Gelir, doluluk, performans raporları

---

## 🏨 Sektörel Analiz

### Otel Yönetim Sistemleri Standartları

#### 1. Rezervasyon Yönetimi
- **Misafir Bilgileri**: TC Kimlik No, Pasaport No, Vatandaşlık zorunluluğu
- **Çoklu Misafir Desteği**: Yetişkin ve çocuk sayısına göre dinamik form alanları
- **Fiyatlandırma**: Global fiyatlama utility ile otomatik hesaplama
- **İndirim Yönetimi**: Yüzde veya sabit tutar indirimleri
- **Rezervasyon Kaynağı**: Direkt, Online, Acente, Kanal takibi

#### 2. Oda Durumu Yönetimi
- **Oda Planı**: Görsel oda durumu haritası
- **Durumlar**: Boş, Dolu, Temiz, Kirli, Temizlik Bekliyor, Bakımda, Hizmet Dışı
- **Gerçek Zamanlı Güncelleme**: Check-in/out ile otomatik durum güncelleme

#### 3. Ödeme Yönetimi
- **Ön Ödeme**: Rezervasyon sırasında ön ödeme alma
- **Kalan Tutar Takibi**: Otomatik kalan tutar hesaplama
- **Ödeme Yöntemleri**: Nakit, Kredi Kartı, Havale, Çek
- **Ödeme Eklemeleri**: Rezervasyon üzerinden ek ödeme kayıtları

#### 4. Check-in/Check-out İşlemleri
- **Erken Check-in**: Ek ücret hesaplama
- **Geç Check-out**: Ek ücret veya iade hesaplama
- **No-show Takibi**: Rezervasyon yapıp gelmeyen misafirler
- **Comp Rezervasyonlar**: Ücretsiz rezervasyonlar

#### 5. Voucher ve Bildirimler
- **Voucher Oluşturma**: Dinamik şablonlarla voucher oluşturma
- **WhatsApp Bildirimleri**: Rezervasyon onayı, hatırlatma
- **SMS Bildirimleri**: Check-in/out bildirimleri

---

## 🏗️ Modül Yapısı

### Veritabanı Yapısı

#### 1. Reservations Tablosu (Merkezi)
```sql
CREATE TABLE reservations (
    id BIGSERIAL PRIMARY KEY,
    reservation_code VARCHAR(50) UNIQUE NOT NULL,
    hotel_id BIGINT NOT NULL,
    room_id BIGINT NOT NULL,
    room_number_id BIGINT,
    customer_id BIGINT NOT NULL,
    
    -- Tarih Bilgileri
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    check_in_time TIME DEFAULT '14:00',
    check_out_time TIME DEFAULT '12:00',
    total_nights INTEGER DEFAULT 1,
    
    -- Misafir Bilgileri
    adult_count INTEGER DEFAULT 1,
    child_count INTEGER DEFAULT 0,
    child_ages JSONB DEFAULT '[]',
    
    -- Rezervasyon Bilgileri
    status VARCHAR(20) DEFAULT 'pending',
    source VARCHAR(20) DEFAULT 'direct',
    reservation_agent_id BIGINT, -- Acente
    reservation_channel_id BIGINT, -- Kanal (Booking.com, vb.)
    
    -- Fiyatlandırma
    room_rate DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    discount_type VARCHAR(20), -- 'percentage' veya 'fixed'
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    total_paid DECIMAL(12,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'TRY',
    
    -- Özel Durumlar
    is_comp BOOLEAN DEFAULT FALSE, -- Ücretsiz rezervasyon
    is_no_show BOOLEAN DEFAULT FALSE,
    no_show_reason TEXT,
    
    -- Check-in/out
    is_checked_in BOOLEAN DEFAULT FALSE,
    is_checked_out BOOLEAN DEFAULT FALSE,
    checked_in_at TIMESTAMP,
    checked_out_at TIMESTAMP,
    early_check_in BOOLEAN DEFAULT FALSE,
    late_check_out BOOLEAN DEFAULT FALSE,
    
    -- İptal
    is_cancelled BOOLEAN DEFAULT FALSE,
    cancelled_at TIMESTAMP,
    cancellation_reason TEXT,
    
    -- Notlar
    special_requests TEXT,
    internal_notes TEXT,
    
    -- Kullanıcı Takibi
    created_by_id BIGINT, -- Rezervasyonu giren kullanıcı
    updated_by_id BIGINT,
    
    -- Tarihler
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    is_deleted BOOLEAN DEFAULT FALSE
);
```

#### 2. Reservation Guests Tablosu (Misafir Bilgileri)
```sql
CREATE TABLE reservation_guests (
    id BIGSERIAL PRIMARY KEY,
    reservation_id BIGINT NOT NULL,
    guest_type VARCHAR(20) NOT NULL, -- 'adult' veya 'child'
    guest_order INTEGER, -- Misafir sırası (1, 2, 3...)
    
    -- Kişisel Bilgiler
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender VARCHAR(10), -- 'male', 'female', 'other'
    birth_date DATE,
    age INTEGER, -- Çocuklar için
    
    -- Kimlik Bilgileri
    tc_no VARCHAR(11),
    passport_no VARCHAR(50),
    passport_serial_no VARCHAR(20),
    nationality VARCHAR(100) DEFAULT 'Türkiye',
    
    -- İletişim
    email VARCHAR(255),
    phone VARCHAR(20),
    
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### 3. Reservation Payments Tablosu (Ödeme Kayıtları)
```sql
CREATE TABLE reservation_payments (
    id BIGSERIAL PRIMARY KEY,
    reservation_id BIGINT NOT NULL,
    payment_date DATE NOT NULL,
    payment_amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL, -- 'cash', 'credit_card', 'transfer', 'check'
    payment_type VARCHAR(20) NOT NULL, -- 'advance', 'full', 'partial', 'refund'
    currency VARCHAR(3) DEFAULT 'TRY',
    
    -- Entegrasyon
    cash_transaction_id BIGINT, -- Finance modülü
    accounting_payment_id BIGINT, -- Accounting modülü
    
    -- Notlar
    notes TEXT,
    receipt_no VARCHAR(50),
    
    created_by_id BIGINT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### 4. Reservation Timeline Tablosu (Güncelleme Geçmişi)
```sql
CREATE TABLE reservation_timeline (
    id BIGSERIAL PRIMARY KEY,
    reservation_id BIGINT NOT NULL,
    action_type VARCHAR(50) NOT NULL, -- 'created', 'updated', 'checkin', 'checkout', 'payment', 'cancelled'
    action_description TEXT,
    old_value JSONB,
    new_value JSONB,
    
    user_id BIGINT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### 5. Reservation Vouchers Tablosu (Voucher'lar)
```sql
CREATE TABLE reservation_vouchers (
    id BIGSERIAL PRIMARY KEY,
    reservation_id BIGINT NOT NULL,
    voucher_template_id BIGINT, -- Dinamik şablon
    voucher_code VARCHAR(50) UNIQUE NOT NULL,
    voucher_data JSONB, -- Şablon verileri
    
    -- Durum
    is_sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP,
    sent_via VARCHAR(20), -- 'email', 'whatsapp', 'sms'
    
    created_at TIMESTAMP DEFAULT NOW()
);
```

---

## 📝 Rezervasyon Sistemi

### Rezervasyon Formu Yapısı

#### 1. Temel Bilgiler (Grid 1)
- **Giriş Tarihi**: Date picker
- **Çıkış Tarihi**: Date picker
- **Geceleme Sayısı**: Otomatik hesaplanır (readonly)
- **Yetişkin Sayısı**: Number input (min: 1)
- **Çocuk Sayısı**: Number input (min: 0)
- **Çocuk Yaşları**: Dinamik array (çocuk sayısı kadar)

#### 2. Oda Seçimi (Grid 2)
- **Oda Tipi**: Dropdown (tenant ve otel ID'ye göre filtrelenmiş)
- **Oda Numarası**: Dropdown (oda tipine göre filtrelenmiş, opsiyonel)
- **Oda Durumu**: Otomatik gösterilir (seçilen oda numarasına göre)

#### 3. Fiyatlandırma (Grid 3)
- **Oda Fiyatı**: Otomatik hesaplanır (Global Pricing Utility)
- **Manuel Fiyat**: Checkbox ile manuel fiyat girişi
- **İndirim Tipi**: Dropdown ('percentage' veya 'fixed')
- **İndirim Tutarı**: Number input
- **Vergi Tutarı**: Number input
- **Toplam Tutar**: Otomatik hesaplanır (readonly)
- **Para Birimi**: Dropdown (TRY, USD, EUR, GBP)

#### 4. Rezervasyon Kaynağı (Grid 4)
- **Rezervasyon Aracısı (Acente)**: Dropdown (opsiyonel)
- **Rezervasyon Kanalı**: Dropdown (Booking.com, Expedia, vb.)
- **Kaynak**: Dropdown (Direkt, Online, Telefon, E-posta, Walk-in)

#### 5. Müşteri Bilgileri (Grid 5)
- **Müşteri Arama**: TC No, Email veya Telefon ile arama
- **Ad**: Text input (otomatik doldurulur)
- **Soyad**: Text input (otomatik doldurulur)
- **Telefon**: Text input (otomatik doldurulur)
- **Email**: Email input (otomatik doldurulur)
- **Adres**: Textarea (otomatik doldurulur)
- **TC Kimlik No**: Text input (otomatik doldurulur)
- **Pasaport No**: Text input
- **Vatandaşlık**: Dropdown (ülkeler listesi)

#### 6. Yetişkin Misafirler (Grid 6 - Dinamik)
Yetişkin sayısı kadar form alanı oluşturulur:
- **Ad**: Text input
- **Soyad**: Text input
- **TC Kimlik No**: Text input
- **Kimlik Seri No**: Text input
- **Cinsiyet**: Dropdown (Erkek, Kadın, Diğer)

#### 7. Çocuk Misafirler (Grid 7 - Dinamik)
Çocuk sayısı kadar form alanı oluşturulur:
- **Ad**: Text input
- **Soyad**: Text input
- **Cinsiyet**: Dropdown (Erkek, Kız, Diğer)
- **TC Kimlik No**: Text input (opsiyonel)
- **Pasaport No**: Text input (opsiyonel)
- **Seri No**: Text input (opsiyonel)
- **Yaş**: Number input

#### 8. Ödeme Bilgileri (Grid 8)
- **Ödeme Yöntemi**: Dropdown (Nakit, Kredi Kartı, Havale, Çek)
- **Ön Ödeme**: Number input
- **Ödeme Yönetimi**: Checkbox (Otomatik ödeme takibi)

#### 9. Özel Durumlar (Grid 9)
- **Comp Rezervasyon**: Checkbox (Ücretsiz)
- **No-show**: Checkbox
- **Özel İstekler**: Textarea
- **İç Notlar**: Textarea (Personel için)

### Rezervasyon Formu Özellikleri

#### Otomatik Hesaplamalar
1. **Geceleme Sayısı**: Check-out - Check-in tarihleri
2. **Oda Fiyatı**: Global Pricing Utility ile:
   - Sezonluk fiyatlar
   - Kampanya fiyatları
   - Acente fiyatları
   - Kanal fiyatları
   - Hafta içi/hafta sonu fiyatları
3. **Toplam Tutar**: (Oda Fiyatı × Geceleme) - İndirim + Vergi
4. **Kalan Tutar**: Toplam Tutar - Ödenen Tutar

#### Müşteri Eşleştirme
- TC No, Email veya Telefon ile arama
- Bulunan müşteri bilgileri otomatik doldurulur
- Müşteri bulunamazsa yeni müşteri oluşturulur

#### Dinamik Form Alanları
- Yetişkin sayısı değiştiğinde yetişkin misafir formları güncellenir
- Çocuk sayısı değiştiğinde çocuk misafir formları güncellenir
- Oda tipi değiştiğinde oda numaraları filtrelenir

---

## 🏠 Oda Yönetimi

### Oda Planı Görünümü

#### Görsel Oda Durumu Haritası
- **Grid Layout**: Kat bazlı oda düzeni
- **Renk Kodlaması**:
  - 🟢 Yeşil: Boş ve Temiz
  - 🔴 Kırmızı: Dolu
  - 🟡 Sarı: Temizlik Bekliyor
  - 🔵 Mavi: Bakımda
  - ⚫ Gri: Hizmet Dışı
- **Hover Bilgileri**: Oda numarası, durum, rezervasyon bilgileri
- **Tıklama**: Oda detayı veya rezervasyon oluşturma

### Oda Durumu Yönetimi
- **Otomatik Güncelleme**: Check-in/out ile otomatik durum değişimi
- **Manuel Güncelleme**: Personel tarafından manuel durum değişikliği
- **Durum Geçmişi**: Oda durumu değişiklik geçmişi

---

## 🔐 Yetki Sistemi

### Otel Bazlı Önbüro Yetkisi

#### 1. SaaS Superadmin Panel
- **Modül Ekleme**: Reception modülü paketlere eklenebilir
- **Modül Yetkilendirme**: Paket bazlı modül aktifleştirme
- **Global Ayarlar**: Voucher şablonları, bildirim ayarları

#### 2. Tenant Panel
- **Modül Yetkilendirme**: Tenant bazlı modül aktifleştirme
- **Rol Yönetimi**: Reception modülü için roller
- **Yetki Yönetimi**: Reception modülü için yetkiler

#### 3. Kullanıcı Yetkilendirme
- **Otel Bazlı Yetki**: Kullanıcıya hangi oteller için önbüro yetkisi verileceği
- **Yetki Seviyeleri**:
  - **View**: Sadece görüntüleme
  - **Manage**: Rezervasyon yönetimi
  - **Admin**: Tüm yetkiler

#### 4. Yetki Kontrolü
```python
@require_hotel_permission('manage')  # Otel bazlı yetki kontrolü
@require_module_permission('reception', 'add')  # Modül bazlı yetki kontrolü
def reservation_create(request):
    # Sadece yetkili kullanıcılar erişebilir
    pass
```

---

## 🔗 Entegrasyonlar

### 1. Otel Modülü Entegrasyonu
- **Oda Tipleri**: `hotels.Room` modeli
- **Oda Numaraları**: `hotels.RoomNumber` modeli
- **Oda Durumu**: `hotels.RoomNumber.status` güncelleme
- **Fiyatlandırma**: `hotels.RoomPrice` ve ilgili fiyat modelleri

### 2. Müşteri Yönetimi (CRM) Entegrasyonu
- **Müşteri Eşleştirme**: `tenant_core.Customer` modeli
- **TC No, Email, Telefon**: Otomatik eşleştirme
- **Müşteri Folyosu**: Rezervasyon geçmişi, ödemeler, notlar

### 3. Finance (Kasa) Modülü Entegrasyonu
- **Ödeme Kayıtları**: `finance.CashTransaction` modeli
- **Otomatik Kayıt**: Rezervasyon ödemesi yapıldığında otomatik kasa kaydı
- **Bakiye Güncelleme**: Kasa bakiyesi otomatik güncellenir

### 4. Accounting (Muhasebe) Modülü Entegrasyonu
- **Fatura Oluşturma**: Rezervasyon oluşturulduğunda otomatik fatura
- **Ödeme Kayıtları**: `accounting.Payment` modeli
- **Yevmiye Kayıtları**: Otomatik muhasebe kayıtları

### 5. Housekeeping Modülü Entegrasyonu
- **Temizlik Görevi**: Check-out sonrası otomatik temizlik görevi
- **Oda Durumu**: Temizlik tamamlandığında oda durumu güncelleme

### 6. Sales Modülü Entegrasyonu
- **Satış Kayıtları**: Rezervasyon ile ilişkili satış kayıtları
- **Acente Komisyonu**: Acente rezervasyonları için komisyon hesaplama

---

## 📊 Raporlama

### 1. Gelir Raporları
- **Günlük Gelir**: Günlük rezervasyon gelirleri
- **Aylık Gelir**: Aylık toplam gelir
- **Yıllık Gelir**: Yıllık toplam gelir
- **Gelir Kaynağı**: Kaynak bazlı gelir analizi (Direkt, Online, Acente, vb.)

### 2. Doluluk Raporları
- **Doluluk Oranı**: Günlük, aylık, yıllık doluluk oranları
- **Oda Tipi Bazlı**: Oda tipine göre doluluk analizi
- **Sezonluk Analiz**: Sezon bazlı doluluk karşılaştırması

### 3. Performans Raporları
- **Check-in/out Süreleri**: Ortalama check-in/out süreleri
- **No-show Oranı**: No-show rezervasyon oranı
- **İptal Oranı**: İptal edilen rezervasyon oranı
- **Müşteri Memnuniyeti**: Müşteri geri bildirimleri

### 4. Ödeme Raporları
- **Ön Ödeme Oranı**: Ön ödeme yapılan rezervasyon oranı
- **Kalan Tutar**: Toplam kalan tutar
- **Ödeme Yöntemi**: Ödeme yöntemine göre dağılım

---

## 🛠️ Teknik Detaylar

### 1. Global Pricing Utility
```python
def calculate_room_price(room, check_in_date, check_out_date, adult_count, child_count, 
                       agency_id=None, channel_id=None):
    """
    Oda fiyatını hesapla
    - Sezonluk fiyatlar
    - Kampanya fiyatları
    - Acente fiyatları
    - Kanal fiyatları
    - Hafta içi/hafta sonu fiyatları
    """
    pass
```

### 2. Rezervasyon Kodu Oluşturma
```python
def generate_reservation_code(hotel_code, year=None):
    """
    Rezervasyon kodu: HOTEL-YYYY-XXXX
    Örnek: IST-2025-0001
    """
    pass
```

### 3. Voucher Şablon Sistemi
- **Dinamik Şablonlar**: HTML/CSS ile özelleştirilebilir şablonlar
- **Veri Ekleme**: Rezervasyon bilgileri otomatik eklenir
- **PDF Oluşturma**: Voucher PDF olarak oluşturulabilir

### 4. Bildirim Sistemi
- **WhatsApp API**: Twilio veya benzeri servis entegrasyonu
- **SMS API**: SMS gönderim servisi
- **Email**: Django email backend

---

## 📅 Uygulama Planı

### Faz 1: Temel Rezervasyon Sistemi (Öncelik: Yüksek)
1. ✅ Reservation modelini genişlet
2. ✅ Reservation Guests modeli
3. ✅ Reservation Payments modeli
4. ✅ Rezervasyon formu (popup, grid yapısı)
5. ✅ Müşteri eşleştirme
6. ✅ Global Pricing Utility entegrasyonu
7. ✅ Otomatik hesaplamalar

### Faz 2: Ödeme ve Entegrasyonlar (Öncelik: Yüksek)
1. ✅ Finance modülü entegrasyonu
2. ✅ Accounting modülü entegrasyonu
3. ✅ Ödeme ekleme
4. ✅ Kalan tutar takibi
5. ✅ Ödeme geçmişi

### Faz 3: Check-in/out ve Durum Yönetimi (Öncelik: Orta)
1. ✅ Check-in/out işlemleri
2. ✅ Erken check-in / Geç check-out
3. ✅ Oda durumu güncelleme
4. ✅ No-show takibi
5. ✅ Comp rezervasyonlar

### Faz 4: Oda Planı ve Görselleştirme (Öncelik: Orta)
1. ✅ Oda planı görünümü
2. ✅ Görsel oda durumu haritası
3. ✅ Oda durumu yönetimi

### Faz 5: Voucher ve Bildirimler (Öncelik: Düşük)
1. ✅ Voucher sistemi
2. ✅ Dinamik şablonlar
3. ✅ WhatsApp entegrasyonu
4. ✅ SMS entegrasyonu

### Faz 6: Raporlama (Öncelik: Düşük)
1. ✅ Gelir raporları
2. ✅ Doluluk raporları
3. ✅ Performans raporları

---

## ✅ Sonuç

Bu rapor, önbüro modülünün kapsamlı bir analizini ve tasarımını içermektedir. Sektörel standartlar göz önünde bulundurularak hazırlanmıştır ve tüm gereksinimler karşılanmıştır.

**Öncelikli Geliştirme**: Rezervasyon sistemi ve ödeme entegrasyonları ile başlanmalıdır.





