# CloudPanel Özel Alan Adı Ayarı

## 🔍 CloudPanel Özel Alan Adı Nedir?

Bu ayar, CloudPanel yönetim paneline özel bir domain vermenizi sağlar.

**Örnek:**
- Varsayılan: `https://VPS_IP:8443`
- Özel Domain: `https://cp.bulutacente.com.tr` veya `https://panel.bulutacente.com.tr`

---

## ✅ Ne Yazmalısınız?

### Seçenek 1: Özel Domain Kullanın (ÖNERİLEN)

**Domain:**
```
cp.bulutacente.com.tr
```

**Veya:**
```
panel.bulutacente.com.tr
```

**Veya:**
```
admin.bulutacente.com.tr
```

**Avantajlar:**
- ✅ Kolay hatırlanır domain
- ✅ SSL sertifikası otomatik
- ✅ Profesyonel görünüm
- ✅ IP adresi yerine domain kullanımı

---

### Seçenek 2: Boş Bırakın (Alternatif)

**Boş bırakabilirsiniz:**
- IP adresi ile erişim: `https://VPS_IP:8443`
- SSL sertifikası olmadan çalışır
- Daha basit kurulum

**Dezavantajlar:**
- ⚠️ IP adresi hatırlamak zor
- ⚠️ SSL sertifikası yok (güvenlik uyarısı)
- ⚠️ Daha az profesyonel

---

## 📋 Önerilen: Özel Domain Kullanın

### Domain Önerisi:

```
cp.bulutacente.com.tr
```

**Neden:**
- ✅ Kısa ve hatırlanabilir
- ✅ `cp` = CloudPanel kısaltması
- ✅ Ana domain'inizin alt domain'i
- ✅ Kolay DNS yapılandırması

---

## 🔧 DNS Yapılandırması

### Adım 1: DNS Kaydı Ekle

Hostinger DNS yönetim panelinden:

**A Record:**
```
Type: A
Name: cp (veya panel, admin)
Value: 88.255.216.16 (veya 72.62.35.155 - VPS IP adresiniz)
TTL: 3600
```

**Örnek:**
- `cp.bulutacente.com.tr` → VPS IP adresi

### Adım 2: CloudPanel'de Domain Ayarlayın

1. **CloudPanel → Settings → Genel**
2. **CloudPanel Özel Alan Adı**: `cp.bulutacente.com.tr`
3. **Kaydet**

### Adım 3: SSL Sertifikası

CloudPanel otomatik olarak Let's Encrypt SSL sertifikası oluşturur:
- DNS kaydı doğruysa
- Domain VPS IP'sine yönlendirilmişse
- SSL otomatik aktif olur

---

## 📝 Form Doldurma

### CloudPanel Özel Alan Adı:

```
cp.bulutacente.com.tr
```

**Veya:**
```
panel.bulutacente.com.tr
```

**Not:** `https://` prefix'i otomatik eklenir, sadece domain adını yazın.

---

## ⚠️ Önemli Notlar

### DNS Kaydı Gerekli:

CloudPanel'in uyarısı:
> "Let's Encrypt SSL sertifikası oluşturmak için bu sunucuya yönlendirilmiş bir DNS kaydı gereklidir."

**Yapılacaklar:**
1. DNS kaydı ekleyin (`cp.bulutacente.com.tr` → VPS IP)
2. DNS yayılımını bekleyin (1-24 saat)
3. CloudPanel'de domain'i kaydedin
4. SSL sertifikası otomatik oluşturulur

---

## 🔄 Adım Adım Kurulum

### Adım 1: DNS Kaydı Ekle

Hostinger DNS yönetim panelinden:

```
Type: A
Name: cp
Value: 88.255.216.16
TTL: 3600
```

### Adım 2: DNS Yayılımını Bekle

```bash
# DNS kontrolü
nslookup cp.bulutacente.com.tr

# Beklenen çıktı:
# cp.bulutacente.com.tr -> 88.255.216.16
```

### Adım 3: CloudPanel'de Domain Ayarla

1. **CloudPanel → Settings → Genel**
2. **CloudPanel Özel Alan Adı**: `cp.bulutacente.com.tr`
3. **Kaydet**

### Adım 4: SSL Sertifikası Kontrolü

1. **CloudPanel → Settings → SSL**
2. **Let's Encrypt** sertifikası otomatik oluşturulur
3. **Test**: `https://cp.bulutacente.com.tr`

---

## ✅ Öneri

### Özel Domain Kullanın:

**Domain:**
```
cp.bulutacente.com.tr
```

**Neden:**
- ✅ Kolay hatırlanır
- ✅ SSL sertifikası otomatik
- ✅ Profesyonel görünüm
- ✅ IP adresi yerine domain

**DNS Kaydı:**
```
Type: A
Name: cp
Value: 88.255.216.16
```

---

## 🆘 Sorun Giderme

### DNS Kaydı Çalışmıyor:

1. **DNS kaydını kontrol edin**: `nslookup cp.bulutacente.com.tr`
2. **DNS yayılımını bekleyin** (1-24 saat)
3. **Farklı DNS sunucularından kontrol edin**: `dig @8.8.8.8 cp.bulutacente.com.tr`

### SSL Sertifikası Oluşturulamıyor:

1. **DNS kaydının doğru olduğunu kontrol edin**
2. **Port 80 ve 443'in açık olduğunu kontrol edin**
3. **CloudPanel loglarını kontrol edin**

---

## 📝 Özet

### Ne Yazmalısınız?

**ÖNERİLEN:**
```
cp.bulutacente.com.tr
```

**Veya:**
```
panel.bulutacente.com.tr
```

**ALTERNATİF:**
- Boş bırakabilirsiniz (IP adresi ile erişim)

### DNS Kaydı:

```
Type: A
Name: cp
Value: 88.255.216.16
```

### Sonuç:

1. **DNS kaydı ekleyin** (`cp.bulutacente.com.tr` → VPS IP)
2. **CloudPanel'de domain'i kaydedin**: `cp.bulutacente.com.tr`
3. **SSL sertifikası otomatik oluşturulur**

**Sonuç:** `cp.bulutacente.com.tr` yazın ve DNS kaydını ekleyin!

