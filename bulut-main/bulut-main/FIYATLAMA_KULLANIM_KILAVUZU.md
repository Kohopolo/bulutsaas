# Fiyatlama Modülü Kullanım Kılavuzu

**Tarih:** 11 Kasım 2025  
**Modül:** Otel Yönetimi - Oda Fiyatlama

---

## 📋 İçindekiler

1. [Temel Fiyatlandırma](#temel-fiyatlandırma)
2. [Ücretsiz Çocuk Kuralları](#ücretsiz-çocuk-kuralları)
3. [Sezonluk Fiyatlar](#sezonluk-fiyatlar)
4. [Özel Fiyatlar](#özel-fiyatlar)
5. [Kampanya Fiyatları](#kampanya-fiyatları)
6. [Acente ve Kanal Fiyatları](#acente-ve-kanal-fiyatları)

---

## 1. Temel Fiyatlandırma

### 1.1. Fiyatlandırma Tipleri

**Sabit Oda Fiyatı (FIXED_ROOM):**
- Oda için sabit bir gecelik fiyat
- Kişi sayısından bağımsız
- Örnek: 1000 TL/gece (1-4 kişi için aynı fiyat)

**Kişi Çarpanı (PER_PERSON):**
- Kişi sayısına göre çarpan uygulanır
- Yetişkin çarpanları tanımlanır
- Örnek: 1 kişi = 1.0x, 2 kişi = 1.8x, 3 kişi = 2.5x

### 1.2. Yetişkin Çarpanları (Kişi Çarpanı Modunda)

**Nasıl Kullanılır:**
- "+ Yeni Çift Ekle" butonuna tıklayın
- **Kişi Sayısı:** Kaç yetişkin için bu çarpan geçerli (örn: 1, 2, 3)
- **Çarpan:** Fiyat çarpanı (örn: 1.0, 1.8, 2.5)

**Örnek:**
```
Kişi Sayısı: 1 → Çarpan: 1.0   (1 kişi = 1000 TL)
Kişi Sayısı: 2 → Çarpan: 1.8   (2 kişi = 1800 TL)
Kişi Sayısı: 3 → Çarpan: 2.5   (3 kişi = 2500 TL)
```

### 1.3. Çocuk Ayarları

- **Çocuk Sabit Çarpan:** Çocuk için sabit çarpan (örn: 0.5 = yarı fiyat)
- **Çocuk Yaş Aralığı:** Çocuk sayılan yaş aralığı (örn: 0-12)
- **Ücretsiz Çocuk Sayısı:** Basit ücretsiz çocuk sayısı (genel kural)

---

## 2. Ücretsiz Çocuk Kuralları

### 2.1. Ne İşe Yarar?

Ücretsiz çocuk kuralları, **koşullu ücretsiz çocuk** tanımlamak için kullanılır. Örneğin:
- "0-6 yaş arası 2 çocuk, en az 2 yetişkin yanında ücretsiz"
- "0-12 yaş arası 1 çocuk, en az 1 yetişkin yanında ücretsiz"

### 2.2. Nasıl Kullanılır?

1. **"Yeni Kural Ekle"** butonuna tıklayın
2. Her kural için 3 alan doldurun:

   **a) Çocuk Yaş Aralığı:**
   - Ücretsiz olacak çocukların yaş aralığı
   - Format: `0-6`, `0-12`, `6-12` gibi
   - Örnek: `0-6` (0-6 yaş arası)

   **b) Ücretsiz Çocuk Sayısı:**
   - Bu yaş aralığında kaç çocuk ücretsiz olacak
   - Sayısal değer (örn: 1, 2, 3)
   - Örnek: `2` (2 çocuk ücretsiz)

   **c) Minimum Yetişkin Sayısı:**
   - Bu kuralın aktif olması için gereken minimum yetişkin sayısı
   - Sayısal değer (örn: 1, 2, 3)
   - Örnek: `2` (en az 2 yetişkin yanında)

### 2.3. Örnek Senaryolar

**Senaryo 1: Bebek Ücretsiz (0-2 yaş)**
```
Yaş Aralığı: 0-2
Ücretsiz Çocuk Sayısı: 1
Minimum Yetişkin Sayısı: 1
```
*Sonuç: 1 yetişkin yanında 0-2 yaş arası 1 çocuk ücretsiz*

**Senaryo 2: İki Yetişkin Yanında İki Çocuk Ücretsiz**
```
Yaş Aralığı: 0-6
Ücretsiz Çocuk Sayısı: 2
Minimum Yetişkin Sayısı: 2
```
*Sonuç: En az 2 yetişkin yanında 0-6 yaş arası 2 çocuk ücretsiz*

**Senaryo 3: Çoklu Kural**
```
Kural 1:
  Yaş Aralığı: 0-2
  Ücretsiz Çocuk Sayısı: 1
  Minimum Yetişkin Sayısı: 1

Kural 2:
  Yaş Aralığı: 3-6
  Ücretsiz Çocuk Sayısı: 1
  Minimum Yetişkin Sayısı: 2
```
*Sonuç: 1 yetişkin yanında 0-2 yaş 1 çocuk ücretsiz, 2 yetişkin yanında 3-6 yaş 1 çocuk ücretsiz*

### 2.4. Kural Silme

- Her kuralın sağ üst köşesinde kırmızı çöp kutusu ikonu var
- İkon'a tıklayarak kuralı silebilirsiniz

---

## 3. Sezonluk Fiyatlar

### 3.1. Nereden Erişilir?

1. Oda detay sayfasına gidin
2. "Fiyatlama" sekmesine tıklayın
3. **"Sezonluk Fiyatlar"** bölümünde **"Sezonluk Fiyat Ekle"** butonuna tıklayın

### 3.2. Nasıl Kullanılır?

**Alanlar:**
- **Başlangıç Tarihi:** Sezonun başlangıç tarihi
- **Bitiş Tarihi:** Sezonun bitiş tarihi
- **Kişi Başı Fiyat:** (Kişi Çarpanı modunda) Kişi başı fiyat
- **Sabit Oda Fiyatı:** (Sabit Oda modunda) Sabit oda fiyatı
**Örnek:**
```
Başlangıç: 01.06.2025
Bitiş: 31.08.2025
Kişi Başı: 1500 TL
```
*Yaz sezonu için kişi başı 1500 TL*

---

## 4. Özel Fiyatlar

### 4.1. Nereden Erişilir?

1. Oda detay sayfasına gidin
2. "Fiyatlama" sekmesine tıklayın
3. **"Özel Fiyatlar"** bölümünde **"Özel Fiyat Ekle"** butonuna tıklayın

### 4.2. Nasıl Kullanılır?

**Alanlar:**
- **Başlangıç Tarihi:** Özel fiyatın başlangıç tarihi
- **Bitiş Tarihi:** Özel fiyatın bitiş tarihi
- **Hafta İçi Fiyatları:** Her gün için özel fiyat (Pazartesi-Pazar)
- **Hafta Sonu Fiyatları:** Hafta sonu için özel fiyat

**Örnek:**
```
Başlangıç: 01.12.2025
Bitiş: 31.12.2025
Hafta İçi: 800 TL
Hafta Sonu: 1200 TL
```
*Aralık ayı için hafta içi 800 TL, hafta sonu 1200 TL*

---

## 5. Kampanya Fiyatları

### 5.1. Nereden Erişilir?

1. Oda detay sayfasına gidin
2. "Fiyatlama" sekmesine tıklayın
3. **"Kampanya Fiyatları"** bölümünde **"Kampanya Ekle"** butonuna tıklayın

### 5.2. Kampanya Tipleri

- **X Gece Kal:** Belirli gece sayısı için indirim
- **Erken Rezervasyon:** Erken rezervasyon indirimi
- **Son Dakika:** Son dakika indirimi
- **Grup İndirimi:** Grup rezervasyonları için indirim
- **Özel Kampanya:** Özel kurallarla kampanya

### 5.3. Nasıl Kullanılır?

**Alanlar:**
- **Kampanya Adı:** Kampanyanın adı
- **Açıklama:** Kampanya açıklaması
- **Başlangıç/Bitiş Tarihi:** Kampanya tarih aralığı
- **Kampanya Tipi:** Yukarıdaki tiplerden biri
- **Kampanya Kuralları:** JSON formatında kurallar
- **Fiyat/İndirim:** Kişi başı fiyat, sabit fiyat veya indirim oranı

**Örnek:**
```
Kampanya Adı: "7 Gece Kal, %10 İndirim"
Kampanya Tipi: X Gece Kal
Kampanya Kuralları: {"stay_nights": 7, "discount_percent": 10}
```

---

## 6. Acente ve Kanal Fiyatları

### 6.1. Acente Fiyatları

**Nereden Erişilir:**
- Oda detay → Fiyatlama → **"Acente Fiyatları"** → **"Acente Fiyatı Ekle"**

**Kullanım:**
- Belirli acenteler için özel fiyatlandırma
- Acente ID, Acente Adı, Fiyat, Komisyon oranı

### 6.2. Kanal Fiyatları

**Nereden Erişilir:**
- Oda detay → Fiyatlama → **"Kanal Fiyatları"** → **"Kanal Fiyatı Ekle"**

**Kullanım:**
- Booking.com, Expedia gibi kanallar için özel fiyatlandırma
- Kanal Adı, Fiyat, Komisyon oranı

---

## 📝 Önemli Notlar

1. **Fiyat Önceliği:**
   - Kampanya > Özel > Sezonluk > Temel Fiyat

2. **Ücretsiz Çocuk Kuralları:**
   - Kurallar sırayla kontrol edilir
   - İlk eşleşen kural uygulanır
   - Minimum yetişkin sayısı kontrol edilir

3. **Fiyat Hesaplama:**
   - Sistem otomatik olarak en uygun fiyatı seçer
   - Tarih, kişi sayısı, çocuk yaşları dikkate alınır

---

---

## 7. Sık Karşılaşılan Sorunlar ve Çözümleri

### 7.1. Kişi Çarpanları Boş Görünüyor

**Sorun:** Düzenleme sayfasında kişi çarpanları boş görünüyor.

**Çözüm:**
- `JSONCharField` kullanılıyor, model instance değerleri otomatik olarak JSON string'e dönüştürülüyor
- Widget'lar `format_value` metodu ile dict/list değerlerini işliyor
- Eğer hala boş görünüyorsa, sayfayı yenileyin veya tarayıcı cache'ini temizleyin

### 7.2. Ücretsiz Çocuk Kuralları Boş Görünüyor

**Sorun:** Düzenleme sayfasında ücretsiz çocuk kuralları boş görünüyor.

**Çözüm:**
- `JSONCharField` kullanılıyor, model instance değerleri otomatik olarak JSON string'e dönüştürülüyor
- Widget'lar `format_value` metodu ile list değerlerini işliyor
- Eğer hala boş görünüyorsa, sayfayı yenileyin veya tarayıcı cache'ini temizleyin

### 7.3. Çok Fazla Input Oluşturuluyor

**Sorun:** Ücretsiz çocuk kuralları widget'ında çok fazla input oluşturuluyor (324 input).

**Çözüm:**
- Bu sorun çözüldü! `ObjectListWidget` template'inde `fields_config_list` kullanılıyor
- Artık sadece gerekli alanlar (3 alan) oluşturuluyor
- Eğer hala sorun varsa, template cache'ini temizleyin

---

**Hazırlayan:** AI Assistant  
**Tarih:** 12 Kasım 2025  
**Versiyon:** 1.1

