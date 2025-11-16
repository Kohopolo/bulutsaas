# Hostinger DNS Yönetimi Alternatif Yöntemler

## 🔍 Sorun

Hostinger VPS panelinde A Record girecek yer bulunamıyor.

## ✅ Çözüm Yöntemleri

### Yöntem 1: Hostinger DNS Yönetim Paneli (Önerilen)

Hostinger'da DNS yönetimi genellikle **ayrı bir panel**'de yapılır:

#### 1.1 Hostinger Ana Panel'e Giriş

1. **Hostinger hesabınıza giriş yapın**: https://www.hostinger.com/
2. **"Domains"** sekmesine gidin (üst menüden)
3. **Domain'inizi seçin**: `bulutacente.com.tr`
4. **"DNS / Nameservers"** veya **"Manage DNS"** butonuna tıklayın

#### 1.2 DNS Kayıtlarını Ekle

DNS yönetim panelinde şu kayıtları ekleyin:

```
Type: A
Name: @ (veya boş)
Value: 88.255.216.16
TTL: 3600

Type: A
Name: www
Value: 88.255.216.16
TTL: 3600
```

---

### Yöntem 2: Domain Kayıt Firmasından DNS Yönetimi

Eğer domain başka bir firmadan alındıysa (örn: Natro, Turhost, GoDaddy):

#### 2.1 NS Kayıtlarını Kontrol Et

NS kayıtları Hostinger'e yönlendirilmişse, DNS yönetimi Hostinger'den yapılmalı.

Eğer NS kayıtları hala domain kayıt firmasında ise:

1. **Domain kayıt firmasının paneline giriş yapın**
2. **DNS Yönetimi** veya **Nameserver Ayarları** sekmesine gidin
3. **A Record** ekleyin:

```
Type: A
Name: @
Value: 88.255.216.16
TTL: 3600

Type: A
Name: www
Value: 88.255.216.16
TTL: 3600
```

---

### Yöntem 3: Hostinger hPanel (Eğer Varsa)

Bazı Hostinger planlarında **hPanel** (cPanel benzeri) bulunur:

1. **VPS yönetim panelinden hPanel'e giriş yapın**
2. **"DNS Zone Editor"** veya **"Advanced DNS Zone Editor"** sekmesine gidin
3. **A Record** ekleyin

---

### Yöntem 4: Hostinger API (Gelişmiş)

Hostinger'in DNS API'si varsa, komut satırından eklenebilir:

```bash
# Örnek (Hostinger API dokümantasyonuna göre değişebilir)
curl -X POST https://api.hostinger.com/v1/dns/records \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -d '{
    "domain": "bulutacente.com.tr",
    "type": "A",
    "name": "@",
    "value": "88.255.216.16",
    "ttl": 3600
  }'
```

---

### Yöntem 5: VPS'te Direkt DNS Yönetimi (Bind9)

Eğer VPS'te kendi DNS sunucunuz varsa:

```bash
# Bind9 DNS zone dosyası düzenle
sudo nano /etc/bind/db.bulutacente.com.tr

# A Record ekle
@    IN    A    88.255.216.16
www  IN    A    88.255.216.16
```

---

## 🔍 Hostinger Panelinde DNS Yönetimini Bulma

### Adım 1: Hostinger Ana Sayfa

1. **https://www.hostinger.com/** → Login
2. **"Domains"** sekmesine tıklayın (üst menü)

### Adım 2: Domain Listesi

1. **Domain'inizi bulun**: `bulutacente.com.tr`
2. **"Manage"** veya **"DNS"** butonuna tıklayın

### Adım 3: DNS Yönetim Paneli

DNS yönetim panelinde şunlar görünmeli:

- **A Records**
- **CNAME Records**
- **MX Records**
- **TXT Records**
- **NS Records**

Eğer bu seçenekler görünmüyorsa:

1. **"Advanced DNS"** veya **"DNS Zone Editor"** sekmesine bakın
2. **"DNS Management"** veya **"DNS Settings"** butonuna tıklayın
3. **Hostinger destek** ile iletişime geçin

---

## 📞 Hostinger Destek

Eğer DNS yönetim panelini bulamıyorsanız:

1. **Hostinger Live Chat**: https://www.hostinger.com/contact
2. **Destek talebi oluşturun**: "DNS yönetimi nerede?" sorusunu sorun
3. **E-posta**: support@hostinger.com

---

## 🔄 Alternatif: Domain Kayıt Firmasından Yönetim

Eğer NS kayıtları henüz Hostinger'e yönlendirilmemişse:

### 1. Domain Kayıt Firmasının Paneline Giriş

### 2. DNS Yönetimi Sekmesine Gidin

### 3. A Record Ekleyin

```
Type: A
Name: @
Value: 88.255.216.16
TTL: 3600

Type: A
Name: www
Value: 88.255.216.16
TTL: 3600
```

---

## ✅ Hızlı Kontrol

DNS kayıtlarının nerede yönetildiğini kontrol edin:

```bash
# NS kayıtlarını kontrol et
nslookup -type=NS bulutacente.com.tr

# Çıktı örneği:
# bulutacente.com.tr nameserver = ns1.hostinger.com
# bulutacente.com.tr nameserver = ns2.hostinger.com
```

**Eğer NS kayıtları Hostinger'de ise:**
→ DNS yönetimi Hostinger panelinden yapılmalı

**Eğer NS kayıtları başka bir firmada ise:**
→ DNS yönetimi o firmadan yapılmalı

---

## 🆘 Sorun Giderme

### DNS Yönetim Paneli Bulunamıyor

1. **Hostinger ana sayfadan "Domains" sekmesine gidin**
2. **Domain'inizin yanındaki "Manage" butonuna tıklayın**
3. **"DNS" veya "DNS Management" sekmesine bakın**
4. **Eğer yoksa, Hostinger destek ile iletişime geçin**

### NS Kayıtları Kontrolü

```bash
# NS kayıtlarını kontrol et
dig NS bulutacente.com.tr

# veya
nslookup -type=NS bulutacente.com.tr
```

---

## 📋 Özet

1. **Hostinger ana panel → Domains → Domain seç → DNS Management**
2. **Eğer bulunamazsa → Domain kayıt firmasından DNS yönetimi**
3. **Hala bulunamazsa → Hostinger destek ile iletişime geçin**

Hangi yöntemi denediniz? Hostinger panelinde hangi sekmeler görünüyor?

