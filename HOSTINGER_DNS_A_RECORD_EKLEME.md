# Hostinger DNS A Record Ekleme Rehberi

## ✅ Durum

NS kayıtları Hostinger'e yönlendirildi:
- `apollo.dns-parking.com` → Hostinger nameserver'ı
- `athena.dns-parking.com` → Hostinger nameserver'ı

Şimdi A Record eklememiz gerekiyor.

---

## 📋 Adım 1: Hostinger DNS Yönetim Paneline Giriş

### 1.1 Hostinger Ana Panel

1. **https://www.hostinger.com/** → Giriş yapın
2. **Üst menüden "Domains" sekmesine tıklayın**
3. **Domain'inizi bulun**: `bulutacente.com.tr`
4. **"Manage" veya "DNS" butonuna tıklayın**

### 1.2 DNS Yönetim Paneli

DNS yönetim panelinde şunlar görünmeli:
- **A Records**
- **CNAME Records**
- **MX Records**
- **TXT Records**
- **NS Records**

---

## 📋 Adım 2: A Record Ekleme

### 2.1 Ana Domain (@) A Record

1. **"Add Record" veya "Add A Record" butonuna tıklayın**
2. **Formu doldurun:**

```
Type: A
Name: @ (veya boş bırakın, veya "bulutacente.com.tr" yazın)
Value: 88.255.216.16 (veya 72.62.35.155 - VPS IP adresiniz)
TTL: 3600 (veya Auto)
```

3. **"Save" veya "Add Record" butonuna tıklayın**

### 2.2 WWW Subdomain A Record

1. **Yine "Add Record" butonuna tıklayın**
2. **Formu doldurun:**

```
Type: A
Name: www
Value: 88.255.216.16 (veya 72.62.35.155)
TTL: 3600 (veya Auto)
```

3. **"Save" veya "Add Record" butonuna tıklayın**

### 2.3 Wildcard A Record (Opsiyonel - Tenant Subdomain'leri için)

1. **"Add Record" butonuna tıklayın**
2. **Formu doldurun:**

```
Type: A
Name: * (wildcard)
Value: 88.255.216.16 (veya 72.62.35.155)
TTL: 3600 (veya Auto)
```

3. **"Save" veya "Add Record" butonuna tıklayın**

**Not**: Wildcard kaydı, tüm subdomain'leri (örn: `test-otel.bulutacente.com.tr`) aynı IP'ye yönlendirir.

---

## 📋 Adım 3: DNS Kayıtlarını Kontrol Etme

### 3.1 Komut Satırından Kontrol

```bash
# A Record kontrolü
nslookup bulutacente.com.tr

# WWW kontrolü
nslookup www.bulutacente.com.tr

# Beklenen çıktı:
# Name:    bulutacente.com.tr
# Address: 88.255.216.16
```

### 3.2 Online DNS Checker

```
🌐 https://dnschecker.org/

Domain: bulutacente.com.tr
Type: A Record
Check →

✅ Tüm lokasyonlarda yeşil ✓ olmalı
```

---

## 📋 Adım 4: Django'da Domain'i Ekleme

DNS kayıtları eklendikten sonra, Django'da domain'i ekleyin:

```bash
cd /docker/bulutsaas

docker exec saas2026_web python manage.py shell -c "
from django.db import connection
from django_tenants.utils import get_public_schema_name, get_tenant_model
from apps.tenants.models import Domain

connection.set_schema_to_public()

Tenant = get_tenant_model()
public_tenant = Tenant.objects.get(schema_name=get_public_schema_name())

# Ana domain'i ekle
domain, created = Domain.objects.get_or_create(
    domain='bulutacente.com.tr',
    defaults={
        'tenant': public_tenant,
        'domain_type': 'primary',
        'is_primary': True,
    }
)

if created:
    print('✅ Domain eklendi: bulutacente.com.tr')
else:
    print('ℹ️ Domain zaten mevcut: bulutacente.com.tr')

# WWW subdomain'ini de ekle
www_domain, www_created = Domain.objects.get_or_create(
    domain='www.bulutacente.com.tr',
    defaults={
        'tenant': public_tenant,
        'domain_type': 'subdomain',
        'is_primary': False,
    }
)

if www_created:
    print('✅ WWW domain eklendi: www.bulutacente.com.tr')
else:
    print('ℹ️ WWW domain zaten mevcut: www.bulutacente.com.tr')

# Tüm domainleri listele
print('')
print('=== Tüm Domainler ===')
for d in Domain.objects.all():
    print(f'  - {d.domain} -> {d.tenant.name} ({d.tenant.schema_name})')
"
```

---

## 📋 Adım 5: Test Etme

### 5.1 DNS Kontrolü

```bash
# DNS kayıtlarını kontrol et
nslookup bulutacente.com.tr
nslookup www.bulutacente.com.tr
```

### 5.2 HTTP Testi

```bash
# Domain ile test
curl -v http://bulutacente.com.tr/admin/ 2>&1 | head -30

# WWW ile test
curl -v http://www.bulutacente.com.tr/admin/ 2>&1 | head -30

# Health check
curl http://bulutacente.com.tr/health/
```

### 5.3 Tarayıcıdan Test

1. **Tarayıcıdan açın**: `http://bulutacente.com.tr/admin/`
2. **Login sayfası görünmeli**: `/admin/login/` yönlendirmesi yapılmalı
3. **WWW testi**: `http://www.bulutacente.com.tr/admin/`

---

## ⏳ DNS Yayılım Süresi

DNS kayıtlarının yayılması:
- **Minimum**: 1-2 saat
- **Maksimum**: 24-48 saat
- **Genelde**: 2-6 saat

**TTL değerini düşürürseniz** (örn: 300), değişiklikler daha hızlı yayılır.

---

## 🔧 Sorun Giderme

### DNS Yönetim Paneli Bulunamıyor

1. **Hostinger ana panel → Domains → Domain seç → DNS Management**
2. **Eğer görünmüyorsa → Hostinger destek ile iletişime geçin**

### A Record Ekledim Ama Çalışmıyor

1. **DNS yayılımını bekleyin** (1-24 saat)
2. **DNS cache'ini temizleyin** (browser cache, DNS cache)
3. **Farklı DNS sunucularından kontrol edin**: `dig @8.8.8.8 bulutacente.com.tr`

### Domain Bulunamadı Hatası (Django)

1. **Django'da domain'in eklendiğini kontrol edin** (yukarıdaki komutla)
2. **Domain'in public schema'ya bağlı olduğunu kontrol edin**
3. **Nginx config'de domain'in eklendiğini kontrol edin**

---

## 📋 Kontrol Listesi

- [ ] Hostinger DNS yönetim panelinden A Record (@) eklendi
- [ ] Hostinger DNS yönetim panelinden A Record (www) eklendi
- [ ] Wildcard A Record (*) eklendi (opsiyonel)
- [ ] DNS kayıtları yayıldı (nslookup ile kontrol)
- [ ] Django'da domain eklendi (public schema)
- [ ] HTTP testi başarılı (`curl` veya tarayıcı)

---

## 🆘 Yardım

Eğer sorun yaşıyorsanız:

1. **DNS Checker**: https://dnschecker.org/ - DNS kayıtlarının yayılımını kontrol edin
2. **Hostinger Destek**: Hostinger panelinden destek talebi oluşturun
3. **Log Kontrolü**: `docker compose logs web nginx --tail=100`

---

## 📝 Notlar

- **DNS Yayılımı**: DNS kayıtlarının tüm dünyada yayılması 24-48 saat sürebilir
- **TTL Değeri**: TTL değerini düşürürseniz (örn: 300), değişiklikler daha hızlı yayılır
- **Wildcard Domain**: Wildcard kaydı eklemek, tüm subdomain'leri otomatik olarak yönlendirir
- **SSL Sertifikası**: DNS yayıldıktan sonra Let's Encrypt ile SSL sertifikası alabilirsiniz

