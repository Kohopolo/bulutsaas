# NS Kayıtları Kontrol ve Çözüm

## 🔍 Durum

NS kayıtları kontrol edildi:
```
bulutacente.com.tr nameserver = apollo.dns-parking.com
bulutacente.com.tr nameserver = athena.dns-parking.com
```

**Sorun**: NS kayıtları henüz Hostinger'e yönlendirilmemiş veya yayılmamış.

---

## ✅ Çözüm Yöntemleri

### Yöntem 1: NS Kayıtlarının Yayılmasını Bekleme (Önerilen)

NS kayıtlarının yayılması **24-48 saat** sürebilir.

**Kontrol:**
```bash
# NS kayıtlarını kontrol et
nslookup -type=NS bulutacente.com.tr

# Beklenen çıktı (Hostinger nameserver'ları):
# bulutacente.com.tr nameserver = ns1.hostinger.com
# bulutacente.com.tr nameserver = ns2.hostinger.com
```

**Bekleme Süresi:**
- Minimum: 2-6 saat
- Maksimum: 24-48 saat

---

### Yöntem 2: Domain Firmasından NS Kayıtlarını Kontrol Etme

Domain firmasının panelinden NS kayıtlarının doğru yönlendirildiğini kontrol edin:

1. **Domain firmasının paneline giriş yapın**
2. **Domain'inizi seçin**: `bulutacente.com.tr`
3. **"Nameserver Ayarları" veya "NS Kayıtları" sekmesine gidin**
4. **NS kayıtlarını kontrol edin:**

**Doğru NS Kayıtları (Hostinger):**
```
NS1: ns1.dns-parking.com (veya Hostinger'in nameserver'ı)
NS2: ns2.dns-parking.com (veya Hostinger'in nameserver'ı)
```

**Eğer hala eski NS kayıtları görünüyorsa:**
- NS kayıtlarını Hostinger'e yönlendirin
- Değişikliklerin kaydedildiğinden emin olun

---

### Yöntem 3: Geçici Çözüm - Domain Firmasından A Record Ekleme

NS kayıtları yayılana kadar, domain firmasının panelinden A Record ekleyebilirsiniz:

1. **Domain firmasının paneline giriş yapın**
2. **DNS Yönetimi** sekmesine gidin
3. **A Record ekleyin:**

```
Type: A
Name: @ (veya boş)
Value: 88.255.216.16 (veya 72.62.35.155)
TTL: 3600

Type: A
Name: www
Value: 88.255.216.16 (veya 72.62.35.155)
TTL: 3600
```

**Not**: NS kayıtları Hostinger'e yayıldıktan sonra, bu A Record'ları silip Hostinger DNS yönetim panelinden eklemeniz gerekecek.

---

### Yöntem 4: Hostinger Nameserver'larını Öğrenme

Hostinger'in nameserver'larını öğrenmek için:

1. **Hostinger ana panel → Domains → Domain seç**
2. **"DNS / Nameservers" sekmesine bakın**
3. **Nameserver'ları not edin**

**Veya Hostinger destek ile iletişime geçin:**
- Live Chat: https://www.hostinger.com/contact
- "Domain için nameserver'ları öğrenmek istiyorum" diye sorun

---

## 🔄 Adım Adım Çözüm

### Adım 1: Domain Firmasından NS Kayıtlarını Kontrol Et

1. Domain firmasının paneline giriş yapın
2. Domain'inizi seçin
3. NS kayıtlarının Hostinger'e yönlendirildiğini kontrol edin

### Adım 2: NS Kayıtlarının Yayılmasını Bekle

**Süre**: 24-48 saat (genelde 2-6 saat)

**Kontrol:**
```bash
nslookup -type=NS bulutacente.com.tr
```

### Adım 3: Geçici Çözüm (Opsiyonel)

NS kayıtları yayılana kadar domain firmasının panelinden A Record ekleyin.

### Adım 4: NS Kayıtları Yayıldıktan Sonra

1. Hostinger DNS yönetim panelinden A Record ekleyin
2. Domain firmasının panelinden eklediğiniz A Record'ları silin (eğer eklediyseniz)

---

## ⚠️ Önemli Notlar

1. **NS kayıtlarının yayılması 24-48 saat sürebilir**
2. **Geçici çözüm olarak domain firmasının panelinden A Record ekleyebilirsiniz**
3. **NS kayıtları yayıldıktan sonra Hostinger DNS yönetim panelinden A Record ekleyin**
4. **Domain firmasının panelinden eklenen A Record'ları silmeyi unutmayın**

---

## 🆘 Sorun Giderme

### NS Kayıtları Hala Yayılmadı

1. **Domain firmasının panelinden NS kayıtlarını kontrol edin**
2. **NS kayıtlarının Hostinger'e yönlendirildiğinden emin olun**
3. **24-48 saat bekleyin**
4. **Hala yayılmadıysa Hostinger destek ile iletişime geçin**

### Hostinger Nameserver'larını Bulamıyorum

1. **Hostinger ana panel → Domains → Domain seç → DNS / Nameservers**
2. **Hostinger destek ile iletişime geçin**: https://www.hostinger.com/contact

### Geçici A Record Çalışmıyor

1. **DNS cache'ini temizleyin**
2. **Farklı DNS sunucularından kontrol edin**: `dig @8.8.8.8 bulutacente.com.tr`
3. **TTL değerini düşürün** (örn: 300)

---

## 📋 Özet

**Şu Anki Durum:**
- NS kayıtları henüz Hostinger'e yayılmamış
- Hala eski NS kayıtları görünüyor (`apollo.dns-parking.com`)

**Yapılacaklar:**
1. ✅ Domain firmasının panelinden NS kayıtlarını kontrol et
2. ⏳ NS kayıtlarının yayılmasını bekle (24-48 saat)
3. 📝 Geçici çözüm: Domain firmasının panelinden A Record ekle (opsiyonel)
4. 🔄 NS kayıtları yayıldıktan sonra Hostinger DNS yönetim panelinden A Record ekle

---

## 🔍 Hızlı Kontrol Komutları

```bash
# NS kayıtlarını kontrol et
nslookup -type=NS bulutacente.com.tr

# A Record'ları kontrol et
nslookup bulutacente.com.tr

# Farklı DNS sunucularından kontrol et
dig @8.8.8.8 NS bulutacente.com.tr
dig @1.1.1.1 NS bulutacente.com.tr
```

