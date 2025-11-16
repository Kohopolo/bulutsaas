# Hostinger VPS Domain Ekleme Rehberi

## 🌐 Domain: bulutacente.com.tr

NS kayıtları Hostinger'e yönlendirildi. Şimdi Hostinger panelinden domain'i ekleyip DNS kayıtlarını yapılandırmalıyız.

---

## 📋 Adım 1: Hostinger Panel'e Giriş

1. **Hostinger hesabınıza giriş yapın**: https://www.hostinger.com/
2. **VPS yönetim paneline gidin**: "VPS" veya "Cloud" bölümünden VPS'inizi seçin
3. **Domain yönetimine gidin**: "Domains" veya "DNS" sekmesine tıklayın

---

## 📋 Adım 2: Domain Ekleme (Hostinger Panel)

### 2.1 Domain'i VPS'e Bağlama

Hostinger panelinde domain ekleme genellikle şu şekilde yapılır:

1. **"Domains"** veya **"Add Domain"** butonuna tıklayın
2. **Domain adını girin**: `bulutacente.com.tr`
3. **VPS'inizi seçin**: Mevcut VPS'inizi seçin (`srv1132080.hstgr.cloud`)
4. **"Add Domain"** veya **"Connect Domain"** butonuna tıklayın

### 2.2 Alternatif: DNS Yönetimi

Eğer domain zaten eklenmişse veya DNS yönetimi ayrı bir bölümdeyse:

1. **"DNS"** veya **"DNS Management"** sekmesine gidin
2. **Domain'i seçin**: `bulutacente.com.tr`
3. **DNS kayıtlarını düzenleyin**

---

## 📋 Adım 3: DNS Kayıtlarını Yapılandırma

### 3.1 A Record (Ana Domain)

```
Type: A
Name: @ (veya boş bırakın)
Value: 88.255.216.16 (veya 72.62.35.155 - VPS IP adresiniz)
TTL: 3600 (veya Auto)
```

### 3.2 A Record (WWW Subdomain)

```
Type: A
Name: www
Value: 88.255.216.16 (veya 72.62.35.155)
TTL: 3600 (veya Auto)
```

### 3.3 Wildcard A Record (Opsiyonel - Tenant Subdomain'leri için)

```
Type: A
Name: *
Value: 88.255.216.16 (veya 72.62.35.155)
TTL: 3600 (veya Auto)
```

**Not**: Wildcard kaydı, tüm subdomain'leri (örn: `test-otel.bulutacente.com.tr`) aynı IP'ye yönlendirir.

---

## 📋 Adım 4: DNS Kayıtlarını Kontrol Etme

### 4.1 Online DNS Checker

```
🌐 https://dnschecker.org/

Domain: bulutacente.com.tr
Type: A Record
Check →

✅ Tüm lokasyonlarda yeşil ✓ olmalı
```

### 4.2 Komut Satırından Kontrol

```bash
# A Record kontrolü
nslookup bulutacente.com.tr

# WWW kontrolü
nslookup www.bulutacente.com.tr

# Wildcard kontrolü (eğer eklediyseniz)
nslookup test.bulutacente.com.tr
```

**Beklenen Çıktı:**
```
Name:    bulutacente.com.tr
Address: 88.255.216.16  ← VPS IP adresi
```

---

## 📋 Adım 5: Django'da Domain'i Ekleme

DNS kayıtları yapılandırıldıktan sonra, Django'da domain'i public schema'ya eklemeliyiz:

### 5.1 VPS'te Django Shell

```bash
cd /docker/bulutsaas

docker exec saas2026_web python manage.py shell -c "
from django.db import connection
from django_tenants.utils import get_public_schema_name, get_tenant_model
from apps.tenants.models import Domain

# Public schema'ya geç
connection.set_schema_to_public()

# Public tenant'ı bul
Tenant = get_tenant_model()
public_tenant = Tenant.objects.get(schema_name=get_public_schema_name())

# Domain'i ekle
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

### 5.2 Management Command ile Ekleme (Alternatif)

```bash
docker exec saas2026_web python manage.py add_tenant_domain \
    --domain bulutacente.com.tr \
    --domain-type primary \
    --is-primary \
    --schema public
```

---

## 📋 Adım 6: Nginx Yapılandırmasını Güncelleme

Nginx config dosyasına domain'i ekledik, ama container'ı yeniden başlatmak gerekebilir:

```bash
cd /docker/bulutsaas

# GitHub'dan güncellemeleri çek
git pull origin main

# Nginx container'ını yeniden başlat
docker compose restart nginx

# Veya tamamen yeniden oluştur
docker compose down
docker compose up -d --build
```

---

## 📋 Adım 7: Test Etme

### 7.1 DNS Kontrolü

```bash
# DNS kayıtlarını kontrol et
nslookup bulutacente.com.tr
nslookup www.bulutacente.com.tr
```

### 7.2 HTTP Testi

```bash
# Domain ile test
curl -v http://bulutacente.com.tr/admin/ 2>&1 | head -30

# WWW ile test
curl -v http://www.bulutacente.com.tr/admin/ 2>&1 | head -30

# Health check
curl http://bulutacente.com.tr/health/
```

### 7.3 Tarayıcıdan Test

1. **Tarayıcıdan açın**: `http://bulutacente.com.tr/admin/`
2. **Login sayfası görünmeli**: `/admin/login/` yönlendirmesi yapılmalı
3. **WWW testi**: `http://www.bulutacente.com.tr/admin/`

---

## 🔧 Sorun Giderme

### DNS Yayılımı Bekleme

DNS kayıtlarının yayılması **24-48 saat** sürebilir. Hızlı kontrol için:

```bash
# Farklı DNS sunucularından kontrol
dig @8.8.8.8 bulutacente.com.tr
dig @1.1.1.1 bulutacente.com.tr
```

### Domain Bulunamadı Hatası

Eğer Django'da "Domain bulunamadı" hatası alıyorsanız:

```bash
# Domain'in ekli olduğunu kontrol et
docker exec saas2026_web python manage.py shell -c "
from django.db import connection
from django_tenants.utils import get_public_schema_name
from apps.tenants.models import Domain

connection.set_schema_to_public()
domains = Domain.objects.filter(domain__icontains='bulutacente')
for d in domains:
    print(f'{d.domain} -> {d.tenant.schema_name}')
"
```

### Nginx 404 Hatası

Eğer Nginx 404 veriyorsa:

1. **Nginx config'i kontrol et**: `nginx/conf.d/default.conf` dosyasında `server_name` içinde domain var mı?
2. **Container'ı yeniden başlat**: `docker compose restart nginx`
3. **Nginx loglarını kontrol et**: `docker compose logs nginx --tail=50`

---

## 📋 Kontrol Listesi

- [ ] Hostinger panelinde domain eklendi
- [ ] A Record (@) eklendi: `88.255.216.16` veya `72.62.35.155`
- [ ] A Record (www) eklendi: `88.255.216.16` veya `72.62.35.155`
- [ ] Wildcard A Record (*) eklendi (opsiyonel)
- [ ] DNS kayıtları yayıldı (nslookup ile kontrol)
- [ ] Django'da domain eklendi (public schema)
- [ ] Nginx config güncellendi ve container yeniden başlatıldı
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

