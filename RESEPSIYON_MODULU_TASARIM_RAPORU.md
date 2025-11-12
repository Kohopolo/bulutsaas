# Resepsiyon (Ön Büro) Modülü - Detaylı Tasarım Raporu

**Tarih:** 12 Kasım 2025  
**Modül Adı:** Resepsiyon (Ön Büro) / Reception (Front Office)  
**Hedef:** Profesyonel, tek ekran, POS benzeri resepsiyon yönetim sistemi

---

## 📋 İçindekiler

1. [Genel Bakış](#genel-bakış)
2. [Yetki ve Erişim Kontrolü](#yetki-ve-erişim-kontrolü)
3. [Ana Ekran Tasarımı](#ana-ekran-tasarımı)
4. [Modül Yapısı ve Özellikler](#modül-yapısı-ve-özellikler)
5. [Teknik Mimari](#teknik-mimari)
6. [Veri Modelleri](#veri-modelleri)
7. [Entegrasyonlar](#entegrasyonlar)
8. [Real-time Güncellemeler](#real-time-güncellemeler)
9. [Yazdırma Sistemi](#yazdırma-sistemi)
10. [Kullanıcı Deneyimi](#kullanıcı-deneyimi)

---

## 🎯 Genel Bakış

### Amaç
Otel resepsiyonunda çalışan personelin, tek ekrandan tüm işlemleri hızlı ve profesyonel bir şekilde yönetebileceği, market POS sistemine benzer bir arayüz.

### Temel Prensipler
- **Tek Ekran:** Tüm işlemler tek ekranda, modal popup'larla
- **Hızlı Erişim:** En sık kullanılan işlemler ön planda
- **Real-time:** Oda durumları, rezervasyonlar anlık güncellenir
- **Profesyonel:** Otelcilik sektör standartlarına uygun
- **Kapsamlı:** Tüm resepsiyon işlemlerini kapsar

---

## 🔐 Yetki ve Erişim Kontrolü

### Otel Bazlı Erişim

**Tek Otel Yetkisi:**
- Kullanıcıya sadece bir otel için resepsiyon yetkisi atanmışsa
- Sadece o otelin resepsiyon modülüne erişebilir
- Diğer otellere geçiş yapamaz
- Sidebar'da sadece o otel için "Resepsiyon" linki görünür

**Çoklu Otel Yetkisi:**
- Kullanıcıya birden fazla otel için resepsiyon yetkisi atanmışsa
- Otel seçici dropdown görünür (header'da)
- Seçilen otelin resepsiyon modülüne erişir
- Her otel için ayrı oturum/context

### Yetki Seviyeleri

**1. Resepsiyonist (Receptionist):**
- Check-in/Check-out
- Rezervasyon görüntüleme/düzenleme
- Müşteri bilgileri görüntüleme/düzenleme
- Ödeme alma
- Fatura yazdırma
- Oda durumu görüntüleme

**2. Resepsiyon Şefi (Front Office Manager):**
- Tüm resepsiyonist yetkileri
- Rezervasyon oluşturma/silme
- Oda değişikliği
- Ücretsiz oda tahsisi (Complimentary)
- Rapor görüntüleme
- Ayarlar

**3. Yönetici (Manager):**
- Tüm yetkiler
- Sistem ayarları
- Kullanıcı yönetimi
- Gelişmiş raporlar

---

## 🖥️ Ana Ekran Tasarımı

### Layout Yapısı

```
┌─────────────────────────────────────────────────────────────┐
│ HEADER: Otel Adı | Tarih/Saat | Kullanıcı | Otel Seçici   │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  REZERVASYON│  │   MÜŞTERİ   │  │    ODA      │        │
│  │   İŞLEMLERİ │  │   İŞLEMLERİ │  │   DURUMU    │        │
│  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   ÖDEME    │  │  HIZLI      │  │   RAPORLAR  │        │
│  │   İŞLEMLERİ│  │  İŞLEMLER   │  │             │        │
│  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                               │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   FATURA   │  │   AYARLAR   │  │   YARDIM    │        │
│  │   YAZDIRMA │  │             │  │             │        │
│  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│ FOOTER: Aktif İşlemler | Bildirimler | Hızlı İstatistikler │
└─────────────────────────────────────────────────────────────┘
```

### Header Bölümü

**Sol Taraf:**
- Otel adı (büyük, belirgin)
- Aktif tarih ve saat (canlı güncellenen)
- Vardiya bilgisi (varsa)

**Sağ Taraf:**
- Kullanıcı adı ve rolü
- Otel seçici (çoklu otel yetkisi varsa)
- Hızlı arama (müşteri, rezervasyon, oda)
- Bildirimler (varsa)
- Çıkış butonu

### Ana Buton Kategorileri

#### 1. REZERVASYON İŞLEMLERİ (Booking Operations)

**📌 Entegrasyon Notu:** Rezervasyonlar ileride ekleyeceğimiz **Satış**, **Call Center** ve **Acente** rezervasyonları ile entegre olacak. Resepsiyon modülü bu kaynaklardan gelen rezervasyonları görüntüleyebilecek ve yönetebilecek.

**Butonlar:**
- **Yeni Rezervasyon** (New Booking)
- **Rezervasyon Listesi** (Booking List)
- **Check-In** (Giriş)
- **Check-Out** (Çıkış)
- **Rezervasyon İptali** (Cancellation)
- **Rezervasyon Düzenle** (Edit Booking)
- **Bekleme Listesi** (Waiting List)
- **No-Show İşlemleri** (No-Show)

#### 2. MÜŞTERİ İŞLEMLERİ (Guest Operations)

**📌 Entegrasyon Notu:** Müşteriler Modülü ile tam entegre. Resepsiyon modülü müşteri bilgilerini Customers modülünden çeker, yeni müşteri eklerken Customers modülüne kaydeder.

**Butonlar:**
- **Yeni Müşteri** (New Guest) - Customers modülüne kaydedilir
- **Müşteri Arama** (Guest Search) - Customers modülünden arama yapar
- **Müşteri Listesi** (Guest List) - Customers modülünden listeler
- **Müşteri Geçmişi** (Guest History) - Customers modülünden geçmiş konaklamalar
- **Müşteri Detayları** (Guest Details) - Customers modülünden detaylar
- **VIP Müşteriler** (VIP Guests) - Customers modülünden VIP işaretli müşteriler

#### 3. ODA DURUMU (Room Status)

**📌 Entegrasyon Notu:** Housekeeping ve Bakım modülleri ile entegre olacak (ileride). Şu an için Hotels modülünden oda bilgileri çekilir, ileride Housekeeping modülü ile temizlik durumu, Bakım modülü ile arıza durumu senkronize edilecek.

**Butonlar:**
- **Oda Durum Panosu** (Room Rack) - Real-time oda durumları
- **Oda Listesi** (Room List) - Tüm odalar listesi
- **Oda Değişikliği** (Room Change) - Müşteri oda değişikliği
- **Oda İnceleme** (Room Inspection) - Oda inceleme raporu
- **Arızalı Odalar** (Out of Order) - Bakım modülü ile entegre (ileride)
- **Temizlik Durumu** (Housekeeping Status) - Housekeeping modülü ile entegre (ileride)

#### 4. ÖDEME İŞLEMLERİ (Payment Operations)

**📌 Entegrasyon Notu:** Ödeme yöntemleri modülü ile entegrasyon yapılacak. Finance modülü ile ödeme kayıtları senkronize edilecek. İleride POS cihazları, kredi kartı terminalleri gibi ödeme yöntemleri ile entegrasyon yapılabilir.

**Butonlar:**
- **Ödeme Alma** (Receive Payment) - Finance modülüne kaydedilir
- **Ödeme Geçmişi** (Payment History) - Finance modülünden çekilir
- **Kredi Kartı İşlemi** (Charge Voucher) - Ödeme yöntemleri ile entegre
- **Seyahat Çeki** (Traveller's Cheque) - Ödeme yöntemleri ile entegre
- **Paid Out İşlemleri** (Paid Out) - Finance modülüne kaydedilir
- **Hesap Düzeltme** (Adjustment) - Finance modülüne kaydedilir

#### 5. HIZLI İŞLEMLER (Quick Operations)
- **Hızlı Check-In** (Quick Check-In)
- **Hızlı Check-Out** (Quick Check-Out)
- **Kapı Müşterisi** (Walk-In)
- **Oda Yükseltme** (Upgrade)
- **Konaklama Uzatma** (Extension)
- **Erken Çıkış** (Early Check-Out)

#### 6. RAPORLAR (Reports)
- **Günlük Rapor** (Daily Report)
- **Doluluk Raporu** (Occupancy Rate)
- **Gelir Raporu** (Revenue Report)
- **Forecast Raporu** (Forecast)
- **Rack Count** (Dolu Oda Sayısı)
- **Grup Raporları** (Group Reports)

#### 7. FATURA YAZDIRMA (Invoice Printing)
- **Fatura Yazdır** (Print Invoice)
- **Alındı Makbuzu** (Receipt)
- **Konaklama Belgesi** (Register Card) - Dijital anahtar bilgileri dahil
- **Ödeme Bildirimi** (Voucher)
- **Hesap Özeti** (Folio)
- **Anahtar Kartı Yazdır** (Key Card Print) - Yeni özellik
- **Toplu Yazdırma** (Bulk Print)

#### 8. AYARLAR (Settings)
- **Resepsiyon Ayarları** (Reception Settings)
- **Yazdırma Ayarları** (Print Settings)
- **Bildirim Ayarları** (Notification Settings)
- **Klavye Kısayolları** (Keyboard Shortcuts)

### Footer Bölümü

**Aktif İşlemler:**
- Açık check-in işlemleri
- Bekleyen check-out işlemleri
- Onay bekleyen rezervasyonlar

**Bildirimler:**
- Yeni rezervasyonlar
- Check-out hatırlatmaları
- Önemli mesajlar

**Hızlı İstatistikler:**
- Bugünkü check-in sayısı
- Bugünkü check-out sayısı
- Dolu oda sayısı
- Doluluk oranı (%)

---

## 🏗️ Modül Yapısı ve Özellikler

### 1. Rezervasyon Yönetimi (Booking Management)

#### Yeni Rezervasyon (New Booking)

**📌 Önemli Not:** Oda tipi seçildiğinde, **Global Fiyatlama Utility** (`apps.tenant_apps.core.utils.calculate_dynamic_price`) kullanılarak otomatik fiyat hesaplama yapılacak. Form modal içeriği bu utility'ye göre dinamik olarak oluşturulacak.

**Modal İçeriği:**

**1. Müşteri Bilgileri:**
- **Müşteri Seçimi:** Customers modülünden kayıtlı müşteriyi bulabilir (entegre)
- **Yeni Müşteri Ekleme:** Customers modülüne yeni müşteri eklenir
- **Müşteri Arama:** Customers modülünden arama yapılır (isim, telefon, email, kimlik no)

**2. Oda ve Tarih Bilgileri:**
- **Oda Tipi Seçimi:** Hotels modülünden çekilir (`RoomType` modeli)
- **Tarih Seçimi:** Giriş ve çıkış tarihleri (tarih picker)
- **Gece Sayısı:** Otomatik hesaplanır (giriş-çıkış farkı)

**3. Kişi ve Pansiyon Bilgileri:**
- **Kişi Sayısı:** Yetişkin ve çocuk sayısı
- **Çocuk Yaşları:** Her çocuk için yaş bilgisi (ücretsiz çocuk hesaplaması için)
- **Pansiyon Tipi:** Hotels modülünden çekilir (`BoardType` modeli)
  - B/B (Bed & Breakfast)
  - H/B (Half Board)
  - F/B (Full Board)
  - EP (European Plan)
  - CP (Continental Plan)

**4. Fiyatlandırma (Global Utility Kullanımı):**
- **Temel Fiyat:** Hotels modülünden `RoomPrice` modelinden çekilir
- **Otomatik Hesaplama:** `calculate_dynamic_price` utility fonksiyonu kullanılır
- **Fiyat Faktörleri:**
  - Sezonluk fiyatlar (Seasonal Prices)
  - Özel fiyatlar (Special Prices)
  - Kampanya fiyatları (Campaign Prices)
  - Acente fiyatları (Agency Prices)
  - Kanal fiyatları (Channel Prices)
  - Yetişkin çarpanları (Adult Multipliers)
  - Ücretsiz çocuk kuralları (Free Children Rules)
- **Toplam Fiyat:** Real-time hesaplanır ve gösterilir
- **Fiyat Detayı:** Hesaplama detayları gösterilir (breakdown)

**5. Müşteri Detay Bilgileri:**
- **Ad, Soyad:** Zorunlu alanlar
- **Kimlik No:** TC Kimlik veya Pasaport No
- **Telefon:** İletişim telefonu
- **Email:** E-posta adresi
- **Adres:** İletişim adresi
- **Çocuk Bilgileri:** Çocuk sayısı > 0 ise:
  - Her çocuk için yaş bilgisi otomatik sorulur
  - Yaş bilgisi ücretsiz çocuk kuralları ile karşılaştırılır
  - Formül kontrolü yapılır (Global fiyatlama utility)

**6. Kaynak Bilgisi:**
- **Rezervasyon Kaynağı:**
  - `reception`: Resepsiyon tarafından yapıldı
  - `sales`: Satış ekibi tarafından yapıldı
  - `call_center`: Call center tarafından yapıldı
  - `agency`: Acente tarafından yapıldı (Acente ID ile)
  - `web`: Web sitesinden self müşteri satışı
  - `channel`: Kanal yönetiminden (Kanal ID ile)
- **Kim Yaptı:** Kullanıcı adı (reception, sales, call_center için)
- **Acente ID:** Acente rezervasyonu ise acente ID kaydedilir
- **Kanal ID:** Kanal rezervasyonu ise kanal ID kaydedilir

**7. Özel İstekler:**
- **Ekstra Yatak** (Additional Bed)
- **Bebek Yatağı** (Baby Cot)
- **Refakatçi** (Accompaniateur)
- **Diğer İstekler:** Serbest metin

**8. Ödeme ve Garanti:**
- **Ödeme Garantisi Seçimi:** Garantili/Garantisiz
- **Ön Ödeme:** İsteğe bağlı ön ödeme tutarı
- **Ödeme Yöntemi:** Nakit, Kredi Kartı, vb.

**9. Rezervasyon Detayları:**
- **Rezervasyon Notları:** Serbest metin
- **Özel İstekler:** JSON formatında saklanır
- **Comp Rezervasyon:** Ücretsiz oda tahsisi (Complimentary) - Yeni özellik

**Özellikler:**
- ✅ **Otomatik Fiyat Hesaplama:** Global fiyatlama utility (`calculate_dynamic_price`) kullanılır
- ✅ **Müsaitlik Kontrolü:** Real-time oda müsaitlik kontrolü
- ✅ **Çift Rezervasyon Kontrolü:** Double Booking önleme
- ✅ **Grup Rezervasyon Desteği:** 11+ kişi için grup rezervasyonu
- ✅ **Acente Rezervasyonu:** Acente ID ile özel fiyatlandırma
- ✅ **Komisyon Hesaplama:** Acente komisyonu otomatik hesaplanır
- ✅ **Dinamik Form:** Oda tipi seçildiğinde form alanları dinamik oluşturulur

#### Rezervasyon Listesi (Booking List)
**Modal İçeriği:**
- **Filtreleme:** Tarih, durum, oda tipi, müşteri, kaynak (acente, web, kanal, resepsiyon)
- **Sıralama:** Tarih, müşteri adı, oda tipi, durum
- **Arama:** Rezervasyon kodu, müşteri adı, telefon, email
- **Liste Görünümü:** Tablo formatında
- **Detay Görüntüleme:** Tek tıkla detay modal'ı
- **Hızlı İşlemler:** Check-in, iptal, düzenle, arşivle

**Görünüm Seçenekleri:**
- **Bugünkü Rezervasyonlar:** Bugün check-in/out olanlar
- **Gelen Rezervasyonlar:** Gelecek tarihli rezervasyonlar
- **Geçmiş Rezervasyonlar:** Tamamlanmış rezervasyonlar
- **İptal Edilen Rezervasyonlar:** İptal edilmiş rezervasyonlar
- **Arşivlenmiş Rezervasyonlar:** Silinen/arşivlenen rezervasyonlar (yeni özellik)

**Rezervasyon Detay Modal'ı:**
- **Temel Bilgiler:** Rezervasyon kodu, tarihler, oda tipi, müşteri
- **Müşteri Bilgileri:** Ad, soyad, kimlik no, telefon, email
- **Çocuk Bilgileri:** Çocuk sayısı, yaşları (0'dan büyükse otomatik sorulur)
- **Kaynak Bilgisi:** Kim rezervasyon yaptı (kullanıcı adı), kaynak (acente, web, kanal, resepsiyon)
- **Acente Bilgisi:** Acente ID, acente adı (varsa)
- **Kanal Bilgisi:** Kanal ID, kanal adı (varsa)
- **Web Rezervasyonu:** Self müşteri satışı işareti
- **Rezervasyon Güncellemeleri:** Tüm değişikliklerin geçmişi (audit log)
- **Ödeme Takibi:** Tüm ödemeler, ödeme geçmişi
- **İade Takibi:** Tüm iadeler, iade geçmişi
- **Oda Değişiklikleri:** Oda değişim geçmişi
- **Notlar:** Rezervasyon notları

#### Check-In İşlemi
**Modal İçeriği:**
- **Rezervasyon Bilgileri:** Otomatik doldurulur (rezervasyon varsa)
- **Müşteri Bilgileri:** Customers modülünden çekilir, kimlik kontrolü yapılır
- **Oda Ataması:** Hotels modülünden müsait oda seçimi
- **Ödeme Bilgileri:** Finance modülüne kaydedilir
- **Özel İstekler:** Rezervasyondan aktarılır
- **Check-in Notları:** Serbest metin
- **Dijital Anahtar:** Kart anahtar oluşturma/yazdırma (yeni özellik)

**Özellikler:**
- **Hızlı Check-In:** Minimum alan (müşteri adı, oda, tarih)
- **Normal Check-In:** Tüm alanlar doldurulur
- **Grup Check-In:** Grup rezervasyonları için toplu check-in
- **Erken Check-In (Early Check-In):** ReceptionSettings'den kontrol edilir
- **Oda Yükseltme (Upgrade):** Daha iyi oda tipine geçiş
- **Ön Kayıt (Pre-Registration):** Rezervasyon öncesi kayıt
- **Dijital Anahtar Sistemi:** Kart anahtar oluşturma ve yazdırma (yeni özellik)

#### Check-Out İşlemi
**Modal İçeriği:**
- **Müşteri Bilgileri:** Customers modülünden çekilir
- **Konaklama Özeti:** Giriş-çıkış tarihleri, gece sayısı
- **Harcamalar (Folio):** Finance modülünden tüm harcamalar
- **Ödeme Durumu:** Toplam ödeme, bakiye
- **Ödeme Alma:** Kalan bakiye ödemesi
- **Check-out Notları:** Serbest metin
- **Dijital Anahtar İptali:** KeyCard otomatik iptal edilir

**Özellikler:**
- **Hızlı Check-Out:** Minimum alan, hızlı işlem
- **Normal Check-Out:** Tüm alanlar, detaylı kontrol
- **Erken Check-Out (Early Check-Out):** Planlanan çıkış tarihinden önce
  - ReceptionSettings'den erken çıkış izni kontrol edilir
  - Erken çıkış ücreti hesaplanabilir (ayarlanabilir)
  - Uyarı mesajı gösterilir
- **Geç Check-Out (Late Check-Out):** Planlanan çıkış saatinden sonra
  - ReceptionSettings'den geç çıkış izni kontrol edilir
  - Geç çıkış ücreti hesaplanır (ayarlanabilir)
  - Uyarı mesajı gösterilir
- **Ödeme Kontrolü:** Tüm harcamalar ödenmiş mi kontrol edilir
- **Fatura Yazdırma:** Otomatik fatura oluşturulur ve yazdırılabilir
- **Anahtar Kartı İptali:** Check-out'ta otomatik iptal edilir

### 2. Müşteri Yönetimi (Guest Management)

#### Müşteri Arama (Guest Search)
**Modal İçeriği:**
- Arama kriterleri (isim, telefon, email, kimlik no)
- Sonuçlar listesi
- Müşteri detayları
- Geçmiş konaklamalar (History Card)
- Hızlı işlemler (yeni rezervasyon, check-in)

**Özellikler:**
- Hızlı arama (debounce)
- Otomatik tamamlama
- VIP müşteri işaretleme
- Müşteri notları
- İletişim geçmişi

#### Müşteri Detayları (Guest Details)
**Modal İçeriği:**
- Kişisel bilgiler
- İletişim bilgileri
- Konaklama geçmişi
- Rezervasyon geçmişi
- Ödeme geçmişi
- Özel istekler
- Notlar

### 3. Oda Durumu (Room Status)

#### Oda Durum Panosu (Room Rack)
**Ana Ekran Görünümü:**
- Grid layout ile oda kartları
- Renk kodlaması:
  - 🟢 Yeşil: Müsait (Available)
  - 🔴 Kırmızı: Dolu (Occupied)
  - 🟡 Sarı: Temizlik (Housekeeping)
  - ⚫ Siyah: Arızalı (Out of Order)
  - 🔵 Mavi: Rezervasyonlu (Reserved)
  - 🟣 Mor: Bakım (Maintenance)

**Oda Kartı Bilgileri:**
- Oda numarası
- Oda tipi
- Durum
- Müşteri adı (varsa)
- Check-in/out tarihleri
- Notlar

**Özellikler:**
- **Real-time Güncelleme:** WebSocket ile anlık güncelleme
- **Filtreleme:** Kat, blok, durum, oda tipi
- **Tek Tıkla Detay:** Oda kartına tıklayınca detay modal'ı açılır
- **Hızlı Durum Değiştirme:** Sağ tık menü ile hızlı durum değişikliği
- **Oda Değişikliği:** Oda değişim işlemi

**Oda Detay Modal'ı (Tek Ekran - YENİ ÖZELLİK):**
Oda kartına tıklayınca açılan modal'da tüm bilgiler tek ekranda toplanır:

**Sol Panel - Oda Bilgileri:**
- Oda numarası, oda tipi, kat, blok
- Oda durumu (değiştirilebilir)
- Oda özellikleri (oda tipi özellikleri)
- Oda fiyatlandırması (RoomPrice bilgileri)
- Oda görselleri (galeri)

**Orta Panel - Rezervasyon Bilgileri:**
- Aktif rezervasyon (varsa)
- Müşteri bilgileri (ad, soyad, kimlik, telefon, email)
- Check-in/out tarihleri
- Kişi sayıları (yetişkin, çocuk, yaşları)
- Pansiyon tipi
- Fiyatlandırma detayı
- Ödeme durumu
- Rezervasyon notları

**Sağ Panel - İşlemler ve Geçmiş:**
- **Hızlı İşlemler:**
  - Check-in (rezervasyon varsa)
  - Check-out (dolu ise)
  - Oda değişikliği
  - Rezervasyon düzenle
  - Rezervasyon iptal
- **Rezervasyon Geçmişi:** Tüm rezervasyonlar (geçmiş, gelecek)
- **Ödeme Geçmişi:** Tüm ödemeler
- **İade Geçmişi:** Tüm iadeler
- **Oda Değişiklik Geçmişi:** Oda değişim kayıtları
- **Notlar:** Oda ve rezervasyon notları

**Alt Panel - Harcamalar (Folio):**
- Tüm harcamalar listesi
- Ödemeler listesi
- Bakiye bilgisi
- Fatura yazdırma

**Özellikler:**
- Tüm bilgiler tek ekranda
- Düzenleme yapılabilir (yetkiye göre)
- Real-time güncelleme
- Yazdırma seçenekleri

#### Oda Listesi (Room List)
**Modal İçeriği:**
- Tüm odalar listesi
- Detaylı bilgiler
- Filtreleme ve sıralama
- Toplu işlemler

### 4. Ödeme İşlemleri (Payment Operations)

#### Ödeme Alma (Receive Payment)
**Modal İçeriği:**
- Müşteri seçimi
- Hesap özeti (Folio)
- Ödeme yöntemi:
  - Nakit (Cash)
  - Kredi Kartı (Credit Card)
  - Seyahat Çeki (Traveller's Cheque)
  - Transfer (Transfer)
  - Diğer (Other)
- Ödeme tutarı
- Para üstü hesaplama
- Makbuz yazdırma

**Özellikler:**
- Kısmi ödeme
- Ön ödeme (Advance Payment)
- Ödeme garantisi
- Kredi kartı işlemi (Charge Voucher)
- Ödeme geçmişi

#### Hesap Düzeltme (Adjustment)
**Modal İçeriği:**
- Düzeltme türü (indirim, ek ücret, vb.)
- Tutar
- Açıklama
- Onay

### 5. Hızlı İşlemler (Quick Operations)

#### Hızlı Check-In
**Özellikler:**
- Minimum alan (müşteri adı, oda, tarih)
- Otomatik fiyat hesaplama
- Hızlı ödeme
- Tek ekranda tamamlanır

#### Kapı Müşterisi (Walk-In)
**Özellikler:**
- Rezervasyonsuz müşteri
- Anlık müsaitlik kontrolü
- Hızlı oda ataması
- Hızlı check-in

### 6. Raporlar (Reports)

#### Günlük Rapor (Daily Report)
**İçerik:**
- Check-in/out sayıları
- Dolu oda sayısı
- Doluluk oranı
- Gelir özeti
- Müşteri sayıları
- Rezervasyon istatistikleri

#### Forecast Raporu
**İçerik:**
- Gelecek tarihler için tahmin
- Rezervasyon durumu
- Beklenen doluluk
- Fiyat önerileri

### 7. Yazdırma Sistemi (Printing System)

#### Fatura Yazdırma (Invoice Printing)
**Şablonlar:**
- Standart fatura
- Detaylı fatura
- Acente faturası
- Grup faturası

**Özellikler:**
- PDF oluşturma
- Email gönderme
- Toplu yazdırma
- Özel şablonlar

#### Diğer Yazdırma İşlemleri
- Alındı Makbuzu (Receipt)
- Konaklama Belgesi (Register Card)
- Ödeme Bildirimi (Voucher)
- Hesap Özeti (Folio)
- Uyandırma Listesi (Wake-up Form)
- Grup Oda Listesi (Rooming List)

---

## 💻 Teknik Mimari

### Django App Yapısı

```
apps/tenant_apps/reception/
├── models.py              # Veri modelleri
├── views.py               # View'lar (ana ekran + AJAX)
├── forms.py               # Form'lar
├── urls.py                # URL yönlendirmeleri
├── decorators.py          # Yetki decorator'ları
├── middleware.py          # Reception middleware (opsiyonel)
├── signals.py             # Signal'lar
├── utils.py               # Yardımcı fonksiyonlar
├── management/
│   └── commands/
│       ├── create_reception_module.py
│       └── create_reception_permissions.py
└── templates/
    └── reception/
        ├── dashboard.html          # Ana ekran
        ├── modals/
        │   ├── booking_form.html
        │   ├── checkin_form.html
        │   ├── checkout_form.html
        │   ├── guest_search.html
        │   ├── room_rack.html
        │   ├── payment_form.html
        │   └── ...
        └── partials/
            ├── booking_list.html
            ├── guest_list.html
            └── ...
```

### Frontend Yapısı

```
static/
├── css/
│   └── reception.css      # Resepsiyon özel stilleri
├── js/
│   ├── reception.js       # Ana JavaScript
│   ├── modal_manager.js   # Modal yönetimi
│   ├── realtime.js        # Real-time güncellemeler
│   └── print_manager.js   # Yazdırma yönetimi
└── images/
    └── reception/         # Resepsiyon görselleri
```

### API Endpoint'leri

**Rezervasyon:**
- `GET /reception/api/bookings/` - Rezervasyon listesi
- `POST /reception/api/bookings/` - Yeni rezervasyon
- `GET /reception/api/bookings/<id>/` - Rezervasyon detayı
- `PUT /reception/api/bookings/<id>/` - Rezervasyon güncelle
- `DELETE /reception/api/bookings/<id>/` - Rezervasyon iptal
- `POST /reception/api/bookings/<id>/checkin/` - Check-in
- `POST /reception/api/bookings/<id>/checkout/` - Check-out

**Müşteri:**
- `GET /reception/api/guests/` - Müşteri listesi
- `POST /reception/api/guests/` - Yeni müşteri
- `GET /reception/api/guests/search/` - Müşteri arama
- `GET /reception/api/guests/<id>/` - Müşteri detayı
- `GET /reception/api/guests/<id>/history/` - Müşteri geçmişi

**Oda:**
- `GET /reception/api/rooms/` - Oda listesi
- `GET /reception/api/rooms/rack/` - Oda durum panosu
- `GET /reception/api/rooms/<id>/` - Oda detayı
- `PUT /reception/api/rooms/<id>/status/` - Oda durumu güncelle
- `POST /reception/api/rooms/change/` - Oda değişikliği

**Ödeme:**
- `POST /reception/api/payments/` - Ödeme alma
- `GET /reception/api/payments/<id>/` - Ödeme detayı
- `GET /reception/api/folio/<guest_id>/` - Hesap özeti

**Dijital Anahtar:**
- `POST /reception/api/keycards/` - Anahtar kartı oluştur
- `GET /reception/api/keycards/<id>/` - Anahtar kartı detayı
- `PUT /reception/api/keycards/<id>/deactivate/` - Anahtar kartı iptal et
- `POST /reception/api/keycards/<id>/print/` - Anahtar kartı yazdır
- `GET /reception/api/keycards/guest/<guest_id>/` - Müşteri anahtar kartları

**Fiyatlandırma:**
- `POST /reception/api/pricing/calculate/` - Fiyat hesaplama (Global utility)
  - Parametreler: `room_type_id`, `check_in`, `check_out`, `adults`, `children`, `child_ages`, `board_type_id`, `agency_id`, `channel_name`
  - Response: `{total_price, adult_price, child_price, breakdown, ...}`

**Real-time (WebSocket):**
- `ws://reception/rooms/` - Oda durumları (WebSocket)
- `ws://reception/bookings/` - Rezervasyon güncellemeleri (WebSocket)
- `ws://reception/notifications/` - Bildirimler (WebSocket)

---

## 📊 Veri Modelleri

### ReceptionSession
**Amaç:** Resepsiyon oturum bilgileri (vardiya takibi)

```python
class ReceptionSession(models.Model):
    user = ForeignKey(User)
    hotel = ForeignKey(Hotel)
    start_time = DateTimeField()
    end_time = DateTimeField(null=True)
    shift_type = CharField()  # Morning, Evening, Night
    notes = TextField()
    is_active = BooleanField()
```

### ReceptionActivity
**Amaç:** Resepsiyon işlem kayıtları (audit log)

```python
class ReceptionActivity(models.Model):
    session = ForeignKey(ReceptionSession)
    activity_type = CharField()  # checkin, checkout, booking, payment
    guest = ForeignKey(Customer, null=True)
    booking = ForeignKey(Reservation, null=True)
    room = ForeignKey(Room, null=True)
    amount = DecimalField(null=True)
    notes = TextField()
    created_at = DateTimeField()
```

### ReceptionSettings
**Amaç:** Resepsiyon ayarları

```python
class ReceptionSettings(models.Model):
    hotel = OneToOneField(Hotel)
    auto_checkout_time = TimeField()  # Otomatik check-out saati
    early_checkin_allowed = BooleanField()
    late_checkout_allowed = BooleanField()
    print_receipt_auto = BooleanField()
    # ... diğer ayarlar
```

### QuickAction
**Amaç:** Hızlı işlem şablonları

```python
class QuickAction(models.Model):
    hotel = ForeignKey(Hotel)
    name = CharField()
    action_type = CharField()  # quick_checkin, quick_checkout, etc.
    template_data = JSONField()  # Şablon verileri
    is_active = BooleanField()
```

### KeyCard (Dijital Anahtar Kartı)
**Amaç:** Oda anahtar kartı yönetimi

```python
class KeyCard(TimeStampedModel, SoftDeleteModel):
    """
    Dijital Anahtar Kartı Modeli
    Check-in sırasında oluşturulur, check-out'ta iptal edilir
    """
    reservation = ForeignKey(Reservation, null=True, blank=True)
    guest = ForeignKey(Customer)
    room = ForeignKey(Room)
    hotel = ForeignKey(Hotel)
    
    # Kart Bilgileri
    card_number = CharField(max_length=50, unique=True)  # Benzersiz kart numarası
    card_code = CharField(max_length=100)  # Şifreli kod (RFID/NFC için)
    access_level = CharField()  # room_only, hotel_access, full_access
    valid_from = DateTimeField()  # Geçerlilik başlangıcı
    valid_until = DateTimeField()  # Geçerlilik bitişi
    
    # Durum
    is_active = BooleanField(default=True)
    is_printed = BooleanField(default=False)  # Yazdırıldı mı?
    printed_at = DateTimeField(null=True, blank=True)
    
    # Notlar
    notes = TextField(blank=True)
    
    class Meta:
        verbose_name = 'Anahtar Kartı'
        verbose_name_plural = 'Anahtar Kartları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['card_number']),
            models.Index(fields=['guest', 'room']),
        ]
    
    def __str__(self):
        return f"{self.card_number} - {self.guest.get_full_name()} - {self.room.number}"
```

---

## 🔗 Entegrasyonlar

### Hotels Modülü
- Oda bilgileri
- Oda tipleri
- Fiyatlandırma
- Oda durumları
- Otel ayarları

### Reservations Modülü
- Rezervasyon yönetimi
- Rezervasyon durumları
- Rezervasyon geçmişi

**Gelecek Entegrasyonlar:**
- **Satış Modülü:** Satış ekibinden gelen rezervasyonlar
- **Call Center Modülü:** Telefon ile gelen rezervasyonlar
- **Acente Modülü:** Acentelerden gelen rezervasyonlar
- **Online Rezervasyon:** Web sitesinden gelen rezervasyonlar

### Customers Modülü
- Müşteri bilgileri
- Müşteri geçmişi
- VIP müşteriler

### Finance Modülü
- Ödeme işlemleri
- Fatura yazdırma
- Hesap yönetimi

### Housekeeping Modülü (Gelecekte)
- Oda temizlik durumu
- Temizlik bildirimleri
- Temizlik tamamlama bildirimleri

### Bakım Modülü (Gelecekte)
- Oda arıza durumu
- Bakım bildirimleri
- Bakım tamamlama bildirimleri

### Ödeme Yöntemleri Modülü (Gelecekte)
- POS cihaz entegrasyonu
- Kredi kartı terminal entegrasyonu
- Nakit ödeme kayıtları
- Ödeme yöntemi yönetimi

---

## 🔄 Real-time Güncellemeler

### WebSocket veya Polling

**Karar: WebSocket (Django Channels) - Baştan Uygulanacak**

**Neden WebSocket?**
- ✅ Anlık güncellemeler (oda durumu, rezervasyonlar)
- ✅ Düşük sunucu yükü (polling'e göre)
- ✅ Daha iyi kullanıcı deneyimi
- ✅ Çoklu kullanıcı senkronizasyonu
- ✅ Gerçek zamanlı bildirimler

**Teknoloji:**
- **Django Channels:** WebSocket desteği
- **Redis:** Channel layer (mesajlaşma)
- **ASGI:** Asenkron sunucu

**Polling (Alternatif - İleride):**
- AJAX ile periyodik sorgu
- Daha basit implementasyon
- Daha yüksek sunucu yükü
- **Not:** İlk aşamada WebSocket kullanılacak, gerekirse polling'e geçiş yapılabilir

### Güncellenecek Veriler

**Oda Durumları:**
- Oda durumu değişiklikleri
- Temizlik durumu
- Arıza durumu

**Rezervasyonlar:**
- Yeni rezervasyonlar
- Rezervasyon iptalleri
- Check-in/out işlemleri

**Bildirimler:**
- Yeni mesajlar
- Önemli uyarılar
- Sistem bildirimleri

---

## 🖨️ Yazdırma Sistemi

### Yazdırma Türleri

**1. Fatura (Invoice)**
- Standart fatura
- Detaylı fatura
- Acente faturası
- Grup faturası

**2. Alındı Makbuzu (Receipt)**
- Ödeme makbuzu
- Kısmi ödeme makbuzu

**3. Konaklama Belgesi (Register Card)**
- Check-in belgesi
- Otel bilgileri
- Müşteri bilgileri
- **Dijital Anahtar Bilgileri:** Kart numarası, geçerlilik tarihleri (yeni özellik)

**4. Ödeme Bildirimi (Voucher)**
- Ödeme onayı
- Kredi kartı işlemi

**5. Hesap Özeti (Folio)**
- Tüm harcamalar
- Ödemeler
- Bakiye

**6. Diğer Belgeler**
- Uyandırma listesi (Wake-up Form)
- Grup oda listesi (Rooming List)
- Rack formu

**7. Anahtar Kartı (Key Card) - YENİ**
- Anahtar kartı bilgileri
- Kart numarası
- Geçerlilik tarihleri
- Erişim seviyesi
- QR kod (opsiyonel)

### Yazdırma Teknolojisi

**Backend:**
- WeasyPrint veya ReportLab (PDF oluşturma)
- Django template'leri (HTML → PDF)

**Frontend:**
- Print.js veya window.print()
- PDF görüntüleme
- Email gönderme

---

## 🎨 Kullanıcı Deneyimi

### Klavye Kısayolları

**Genel:**
- `Ctrl + N` - Yeni rezervasyon
- `Ctrl + S` - Kaydet
- `Ctrl + P` - Yazdır
- `Ctrl + F` - Arama
- `Esc` - Modal kapat

**Hızlı İşlemler:**
- `F1` - Hızlı check-in
- `F2` - Hızlı check-out
- `F3` - Müşteri arama
- `F4` - Oda durumu
- `F5` - Günlük rapor

### Görsel Geri Bildirim

**Renk Kodlaması:**
- 🟢 Yeşil: Başarılı işlem
- 🔴 Kırmızı: Hata/Uyarı
- 🟡 Sarı: Beklemede
- 🔵 Mavi: Bilgi

**Animasyonlar:**
- Modal açılma/kapanma
- Buton hover efektleri
- Loading göstergeleri
- Toast bildirimleri

### Responsive Tasarım

**Desktop (Öncelik):**
- Tam özellikli arayüz
- Tüm butonlar görünür
- Geniş modal'lar

**Tablet:**
- Touch-friendly butonlar
- Responsive grid
- Dokunmatik optimizasyon

---

## 📝 Öncelik Sırası (MVP)

### Faz 1: Temel İşlemler (Yüksek Öncelik)
1. ✅ Ana ekran tasarımı
2. ✅ Rezervasyon ekleme/düzenleme (Global fiyatlama utility entegrasyonu)
3. ✅ Check-in/Check-out (Dijital anahtar sistemi, erken/geç çıkış dahil)
4. ✅ Oda durumu görüntüleme (Real-time WebSocket, tek ekran detay modal)
5. ✅ Müşteri arama/ekleme (Customers modülü entegrasyonu)
6. ✅ Müşteri bilgileri yönetimi (ad, soyad, kimlik, çocuk yaşları)
7. ✅ Çocuk yaş kontrolü (otomatik formül karşılaştırması)
8. ✅ Rezervasyon arşivleme sistemi (soft delete, restore)
9. ✅ Rezervasyon takip sistemi (güncellemeler, ödemeler, iadeler)

### Faz 2: Ödeme ve Yazdırma (Orta Öncelik)
10. ✅ Ödeme alma (Finance modülü entegrasyonu)
11. ✅ Fatura yazdırma
12. ✅ Alındı makbuzu
13. ✅ Hesap özeti (Folio)
14. ✅ Anahtar kartı yazdırma (Dijital anahtar sistemi)

### Faz 3: Kaynak ve Özel Rezervasyonlar (Orta Öncelik)
15. ✅ Kaynak bazlı rezervasyonlar (acente, web, kanal, resepsiyon, satış, call center)
16. ✅ Acente rezervasyonları (acente ID ile kayıt, raporlama)
17. ✅ Web rezervasyonları (self müşteri satışı, web booking reference)
18. ✅ Kanal rezervasyonları (kanal ID ile kayıt, raporlama)
19. ✅ Comp rezervasyon (ücretsiz oda tahsisi, onay sistemi)
20. ✅ Oda değişimi (oda değişiklik kayıtları, fiyat farkı hesaplama)

### Faz 4: SaaS Panel Entegrasyonları (Yüksek Öncelik)
21. ✅ Modül yetkilendirmeleri (Module, PackageModule, Permission)
22. ✅ Sidebar entegrasyonu (accordion yapısı, otel bazlı kontrol)
23. ✅ Kullanıcı yetkileri (UserPermission, HotelUserPermission)
24. ✅ Paket limit kontrolleri (rezervasyon limitleri, decorator)

### Faz 5: Gelişmiş Özellikler (Düşük Öncelik)
25. ✅ Real-time güncellemeler (WebSocket - Django Channels)
26. ✅ Raporlar (acente, kanal, web rezervasyon raporları)
27. ✅ Hızlı işlemler
28. ✅ Grup işlemleri
29. ✅ Waitlist yönetimi
30. ✅ Overbooking yönetimi
31. ✅ No-Show yönetimi
32. ✅ Guest History Tracking
33. ✅ Special Requests yönetimi
34. ✅ Wake-up Call yönetimi
35. ✅ Message Board
36. ✅ Lost & Found
37. ✅ Housekeeping entegrasyonu (ileride)
38. ✅ Bakım modülü entegrasyonu (ileride)
39. ✅ Ödeme yöntemleri entegrasyonu (ileride)
40. ✅ Loyalty Program entegrasyonu (ileride)

---

## ❓ Sorular ve Kararlar

### Teknik Kararlar

1. **Real-time:** WebSocket (Channels) mi yoksa Polling mi?
   - **Karar:** ✅ **WebSocket (Django Channels) - Baştan uygulanacak**
   - **Gerekçe:** Anlık güncellemeler kritik, düşük sunucu yükü, daha iyi kullanıcı deneyimi
   - **Teknoloji:** Django Channels + Redis (Channel Layer)

2. **Modal Yönetimi:** Vanilla JS mi yoksa framework mi?
   - **Öneri:** Vanilla JS (hafif, hızlı)

3. **Yazdırma:** PDF mi yoksa HTML print mi?
   - **Öneri:** Her ikisi de (PDF email için, HTML yazdırma için)

### İş Kuralları

1. **Check-in/out saatleri:** Otel ayarlarından mı alınacak?
   - **Öneri:** Evet, ReceptionSettings'den

2. **Fiyatlandırma:** Hotels modülünden mi alınacak?
   - **Karar:** ✅ **Evet, RoomPrice modelinden + Global Fiyatlama Utility**
   - **Yöntem:** `RoomPrice.calculate_price()` metodu kullanılır
   - **Utility:** `apps.tenant_apps.core.utils.calculate_dynamic_price` fonksiyonu
   - **Özellikler:** Sezonluk, özel, kampanya, acente, kanal fiyatları otomatik hesaplanır

3. **Ödeme garantisi:** Zorunlu mu?
   - **Öneri:** Ayarlanabilir (ReceptionSettings)


### Dijital Anahtar Sistemi (Key Card System)

**Amaç:** Check-in sırasında oda anahtar kartı oluşturma ve yönetimi

**Özellikler:**
- ✅ **Kart Oluşturma:** Check-in sırasında otomatik kart oluşturulur
- ✅ **Benzersiz Kart Numarası:** Her kart için benzersiz numara
- ✅ **Erişim Seviyeleri:**
  - `room_only`: Sadece oda erişimi
  - `hotel_access`: Otel genel erişimi
  - `full_access`: Tüm alanlara erişim
- ✅ **Geçerlilik Süresi:** Check-in'den check-out'a kadar
- ✅ **Kart Yazdırma:** Konaklama belgesi ile birlikte yazdırılabilir
- ✅ **Kart İptali:** Check-out'ta otomatik iptal edilir
- ✅ **RFID/NFC Desteği:** İleride fiziksel kart yazıcıları ile entegrasyon

**Kullanım Senaryoları:**
1. Check-in sırasında kart oluşturulur
2. Kart numarası ve kod bilgileri kaydedilir
3. Konaklama belgesi ile birlikte yazdırılabilir
4. Check-out'ta kart iptal edilir
5. İleride fiziksel kart yazıcıları ile entegrasyon yapılabilir

**Model:** `KeyCard` (yukarıda tanımlanmıştır)

---

## 🎯 Sonuç

Bu tasarım raporu, profesyonel bir resepsiyon yönetim modülü için kapsamlı bir plan sunmaktadır. Tüm otelcilik terimleri ve işlemler göz önünde bulundurulmuştur.

**Önerilen Yaklaşım:**
1. MVP ile başla (Faz 1)
2. Kullanıcı geri bildirimlerini al
3. Faz 2 ve 3'ü ekle
4. Sürekli iyileştirme

**Beklenen Süre:**
- Faz 1: 2-3 hafta (Global fiyatlama, WebSocket, Dijital anahtar dahil)
- Faz 2: 1-2 hafta
- Faz 3: 2-3 hafta (Housekeeping, Bakım, Ödeme yöntemleri entegrasyonları)
- **Toplam:** 5-8 hafta

**Not:** WebSocket ve Global Fiyatlama Utility baştan uygulanacağı için Faz 1 süresi biraz uzayabilir, ancak ileride refactoring gerekmeyecek.

---

**Hazırlayan:** AI Assistant  
**Tarih:** 12 Kasım 2025  
**Durum:** Tasarım Aşaması - Onay Bekliyor

