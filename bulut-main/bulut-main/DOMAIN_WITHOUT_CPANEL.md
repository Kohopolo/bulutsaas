# 🌐 cPanel Olmadan Domain Yönetimi - Tüm Yöntemler

> **cPanel'e ihtiyacınız yok! 3 farklı yöntem, hangisi size uygun?**

---

## 📊 Yöntem Karşılaştırması

| Özellik | Cloudflare | Registrar DNS | VPS DNS Server |
|---------|------------|---------------|----------------|
| **Zorluk** | ⭐ Kolay | ⭐⭐ Orta | ⭐⭐⭐ Zor |
| **Maliyet** | Ücretsiz | Ücretsiz | Ücretsiz |
| **SSL** | Otomatik | Manuel | Manuel |
| **DDoS Koruması** | ✅ Var | ❌ Yok | ❌ Yok |
| **CDN** | ✅ Var | ❌ Yok | ❌ Yok |
| **Wildcard** | ✅ Kolay | ⚠️ Desteklerse | ✅ Var |
| **Web UI** | ✅ Modern | ⚠️ Eski | ❌ Terminal |
| **Kurulum Süresi** | 15 dk | 10 dk | 1-2 saat |
| **ÖNERİLEN** | 🏆 **EVET** | ⚠️ Küçük projeler | ❌ İleri seviye |

---

## 🏆 Yöntem 1: Cloudflare (ÖNERİLEN)

Detaylı rehber: [`CLOUDFLARE_SETUP.md`](CLOUDFLARE_SETUP.md)

### Hızlı Özet:

```bash
1. Cloudflare.com → Hesap aç (ücretsiz)
2. Domain ekle → saas2026.com
3. Nameserver'ları al (örn: april.ns.cloudflare.com)
4. Domain registrar'da nameserver değiştir
5. DNS kayıtları ekle:
   - A record: @ → VPS IP (proxied)
   - A record: www → VPS IP (proxied)
   - A record: * → VPS IP (DNS only)
6. SSL/TLS: Full (strict)
7. BITTI! ✅
```

**Avantajları:**
- ✅ Ücretsiz DDoS koruması
- ✅ Otomatik SSL
- ✅ CDN (hız artışı)
- ✅ Modern web interface
- ✅ Analytics
- ✅ Wildcard domain kolay

**Dezavantajları:**
- ⚠️ Nameserver değişikliği gerekir (1-48 saat bekleme)
- ⚠️ Üçüncü parti servis (Cloudflare çökerse etkilenirsiniz)

---

## ⚙️ Yöntem 2: Domain Registrar DNS Yönetimi

**Ne zaman kullanılır?**
- Domain Cloudflare'e taşımak istemiyorsanız
- Basit proje (wildcard domain gerekmiyorsa)
- Registrar DNS yönetimi iyiyse

### 2.1 GoDaddy ile DNS Yönetimi

#### Adım 1: GoDaddy Paneline Giriş

```
1. GoDaddy.com → Login
2. My Products → Domains
3. Domain yanında ... → Manage DNS
```

#### Adım 2: A Record Ekle

```
┌─────────────────────────────────────────────────┐
│ DNS Management                                  │
│                                                 │
│ Records:                                        │
│                                                 │
│ [Add Record ▼]                                  │
│                                                 │
│ Type:     A                                  ▼  │
│ Host:     @                                     │
│ Points to: 123.45.67.89  ← VPS IP              │
│ TTL:      1 Hour                             ▼  │
│                                                 │
│             [Save]                              │
└─────────────────────────────────────────────────┘
```

**Eklenecek Kayıtlar:**

```
Type: A
Host: @
Points to: [VPS_IP]
TTL: 1 Hour

Type: A
Host: www
Points to: [VPS_IP]
TTL: 1 Hour

Type: A
Host: *
Points to: [VPS_IP]
TTL: 1 Hour
```

#### Adım 3: SSL Sertifikası (Let's Encrypt)

```bash
# VPS'te SSH ile bağlan

# Certbot kur
sudo apt install certbot python3-certbot-nginx -y

# SSL sertifikası al (HTTP challenge)
sudo certbot certonly --standalone \
  -d saas2026.com \
  -d www.saas2026.com \
  --email info@saas2026.com \
  --agree-tos

# Wildcard için DNS challenge gerekir
# (GoDaddy API kullanımı karmaşık)
```

**Sonuç:**
- ✅ Domain çalışır: saas2026.com, www.saas2026.com
- ⚠️ Wildcard (*.saas2026.com) zor

---

### 2.2 Namecheap ile DNS Yönetimi

#### Adım 1: Advanced DNS

```
1. Namecheap.com → Login
2. Domain List → Manage
3. Advanced DNS tab
```

#### Adım 2: Host Records Ekle

```
┌─────────────────────────────────────────────────┐
│ Host Records                                    │
├──────┬──────────┬────────────────┬──────────────┤
│ Type │   Host   │     Value      │      TTL     │
├──────┼──────────┼────────────────┼──────────────┤
│  A   │    @     │ 123.45.67.89   │  Automatic   │
│  A   │   www    │ 123.45.67.89   │  Automatic   │
│  A   │    *     │ 123.45.67.89   │  Automatic   │
└──────┴──────────┴────────────────┴──────────────┘

[Add New Record]
```

**Wildcard Desteği:** ✅ Var (Namecheap destekler)

---

### 2.3 Türkiye Registrar'ları (Natro, Turhost, vb.)

#### Genel Adımlar:

```
1. Registrar paneline giriş
2. Domain Yönetimi → DNS Ayarları
3. A kayıtları ekle:
   
   @ → VPS_IP
   www → VPS_IP
   * → VPS_IP (destekliyorsa)

4. Kaydet
```

**Wildcard Desteği:** ⚠️ Registrar'a bağlı (çoğunda yok)

---

### 2.4 SSL Wildcard (Registrar DNS ile)

**Sorun:** Let's Encrypt wildcard DNS-01 challenge gerektirir.

**Çözüm:**

```bash
# Certbot DNS plugin kur (Registrar'a göre)
# GoDaddy:
sudo apt install python3-certbot-dns-godaddy -y

# Namecheap:
# API desteği yok, manuel TXT record eklemen gerekir

# Manuel DNS challenge:
sudo certbot certonly --manual \
  --preferred-challenges dns \
  -d saas2026.com \
  -d *.saas2026.com

# Certbot size TXT record verecek:
# _acme-challenge.saas2026.com → "ABC123..."

# Bu TXT record'u registrar paneline ekle
# Sonra Certbot'a devam et
```

**Zorluk:** ⭐⭐⭐ (Manuel işlem gerekir)

---

## 🖥️ Yöntem 3: VPS'te Kendi DNS Server'ınızı Kurun

**Ne zaman kullanılır?**
- Tam kontrol istiyorsanız
- Cloudflare kullanmak istemiyorsanız
- İleri seviye kullanıcılar için

### 3.1 BIND9 DNS Server Kurulumu

#### Adım 1: BIND9 Kur

```bash
# VPS'te SSH ile bağlan

# BIND9 kur
sudo apt update
sudo apt install bind9 bind9utils bind9-doc -y
```

#### Adım 2: Zone Dosyası Oluştur

```bash
# Zone dosyası
sudo nano /etc/bind/db.saas2026.com
```

**İçeriği:**

```bind
; Zone file for saas2026.com
$TTL    3600
@       IN      SOA     ns1.saas2026.com. admin.saas2026.com. (
                              2025110901 ; Serial
                              3600       ; Refresh
                              1800       ; Retry
                              604800     ; Expire
                              86400 )    ; Minimum TTL

; Name servers
@       IN      NS      ns1.saas2026.com.
@       IN      NS      ns2.saas2026.com.

; A records
@       IN      A       123.45.67.89
www     IN      A       123.45.67.89
ns1     IN      A       123.45.67.89
ns2     IN      A       123.45.67.89

; Wildcard
*       IN      A       123.45.67.89
```

#### Adım 3: Named.conf Güncelle

```bash
sudo nano /etc/bind/named.conf.local
```

**Ekle:**

```bind
zone "saas2026.com" {
    type master;
    file "/etc/bind/db.saas2026.com";
    allow-transfer { any; };
};
```

#### Adım 4: BIND9'u Başlat

```bash
# Syntax kontrolü
sudo named-checkconf
sudo named-checkzone saas2026.com /etc/bind/db.saas2026.com

# Servis başlat
sudo systemctl restart bind9
sudo systemctl enable bind9

# Firewall
sudo ufw allow 53/tcp
sudo ufw allow 53/udp
```

#### Adım 5: Domain Registrar'da Nameserver Değiştir

```
Nameserver 1: ns1.saas2026.com
Nameserver 2: ns2.saas2026.com
```

**Sorun:** Circular dependency! 
- ns1.saas2026.com domain'inin kendisi tarafından çözümleniyor.

**Çözüm:** Glue Record ekle (registrar panelinde)

```
Glue Records:
ns1.saas2026.com → 123.45.67.89
ns2.saas2026.com → 123.45.67.89
```

**Zorluk:** ⭐⭐⭐⭐⭐ (Çok karmaşık!)

---

## 🔄 Domain Yönlendirme Mantığı (VPS'te)

cPanel olmadan nasıl çalışır?

### Klasik cPanel:

```
Tarayıcı
    ↓
Domain (DNS)
    ↓
VPS IP
    ↓
cPanel (Port 80/443)
    ↓
Apache/LiteSpeed
    ↓
PHP/Python
```

### Docker + Nginx:

```
Tarayıcı
    ↓
Domain (DNS - Cloudflare veya Registrar)
    ↓
VPS IP
    ↓
Nginx (Port 80/443)
    ↓
Docker Container (web:8000)
    ↓
Django
```

### VPS'te Port Dinleme:

```bash
# VPS'te kontrol:
sudo netstat -tulpn | grep :80
sudo netstat -tulpn | grep :443

# Sonuç:
tcp  0.0.0.0:80   LISTEN  docker-proxy
tcp  0.0.0.0:443  LISTEN  docker-proxy
```

**cPanel yok ama Nginx var!** Nginx her şeyi hallediyor.

---

## 🧪 Test & Doğrulama

### 1. DNS Propagasyonu

```bash
# Windows (PowerShell)
nslookup saas2026.com

# Sonuç:
Name:    saas2026.com
Address: 123.45.67.89  ← VPS IP'niz
```

### 2. Wildcard Test

```bash
nslookup otel1.saas2026.com
nslookup otel2.saas2026.com

# Sonuç (hepsi aynı IP):
Address: 123.45.67.89
```

### 3. HTTP Test

```bash
curl -I http://saas2026.com

# Sonuç:
HTTP/1.1 301 Moved Permanently
Location: https://saas2026.com/
```

### 4. HTTPS Test

```bash
curl -I https://saas2026.com

# Sonuç:
HTTP/2 200
server: nginx
```

### 5. Tenant Domain Test

```bash
curl -I https://otel1.saas2026.com

# Django tenant middleware devreye girer
# otel1 subdomain'i → tenant_otel1 schema
```

---

## 📋 Kapsamlı Checklist

### DNS Ayarları:

- [ ] DNS yöneticisi seçildi (Cloudflare/Registrar/BIND)
- [ ] A record: @ → VPS IP
- [ ] A record: www → VPS IP
- [ ] A record: * → VPS IP (wildcard)
- [ ] TTL ayarlandı (3600 veya Auto)
- [ ] DNS propagasyonu tamamlandı (24-48 saat)
- [ ] nslookup ile test edildi

### SSL Sertifikası:

- [ ] Let's Encrypt kuruldu
- [ ] Ana domain sertifikası alındı (saas2026.com)
- [ ] WWW sertifikası alındı (www.saas2026.com)
- [ ] Wildcard sertifikası alındı (*.saas2026.com)
- [ ] Nginx SSL yapılandırıldı
- [ ] Otomatik yenileme ayarlandı (cron)
- [ ] SSL Labs ile test edildi (Grade A+)

### Nginx Yapılandırma:

- [ ] nginx/conf.d/default.conf güncellendi
- [ ] server_name: saas2026.com www.saas2026.com
- [ ] server_name: *.saas2026.com (wildcard)
- [ ] SSL paths doğru
- [ ] HTTP → HTTPS redirect
- [ ] proxy_pass http://web:8000
- [ ] docker-compose restart nginx

### Django Ayarları:

- [ ] .env: SITE_URL=https://saas2026.com
- [ ] .env: ALLOWED_HOSTS=saas2026.com,*.saas2026.com
- [ ] .env: DEBUG=False
- [ ] Admin panel erişilebilir
- [ ] Static files yüklü
- [ ] Media files yüklü

### Test:

- [ ] https://saas2026.com açılıyor
- [ ] https://www.saas2026.com açılıyor
- [ ] https://test.saas2026.com açılıyor (tenant)
- [ ] Admin panel: https://saas2026.com/admin
- [ ] API docs: https://saas2026.com/api/docs

---

## 🆘 Sık Karşılaşılan Sorunlar

### 1. Domain açılmıyor (404 / Connection refused)

**Kontrol:**
```bash
# DNS çözümleniyor mu?
nslookup saas2026.com

# Nginx çalışıyor mu?
docker-compose ps nginx

# Port dinleniyor mu?
sudo netstat -tulpn | grep :80
```

**Çözüm:**
```bash
# Nginx'i yeniden başlat
docker-compose restart nginx

# Firewall kontrol
sudo ufw status
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

---

### 2. SSL hatası (ERR_SSL_PROTOCOL_ERROR)

**Kontrol:**
```bash
# Sertifika var mı?
sudo ls -la /etc/letsencrypt/live/saas2026.com/

# Nginx config doğru mu?
sudo nginx -t
```

**Çözüm:**
```bash
# Sertifikayı yeniden al
sudo certbot certonly --standalone -d saas2026.com -d www.saas2026.com

# Nginx'i yeniden başlat
docker-compose restart nginx
```

---

### 3. Wildcard domain çalışmıyor

**Kontrol:**
```bash
# DNS wildcard var mı?
nslookup test.saas2026.com

# Wildcard SSL var mı?
sudo certbot certificates

# Nginx wildcard server var mı?
grep "*.saas2026.com" nginx/conf.d/default.conf
```

**Çözüm:**
```bash
# DNS: Cloudflare'de * A record ekle (DNS only)
# SSL: Wildcard sertifika al (DNS challenge)
# Nginx: *.saas2026.com server_name ekle
```

---

### 4. Nameserver değişikliği geçerli olmadı

**Kontrol:**
```bash
# Hangi nameserver kullanılıyor?
nslookup -type=ns saas2026.com
```

**Çözüm:**
- Domain registrar panelinde tekrar kontrol et
- 24-48 saat bekle (propagasyon)
- Cache temizle: `ipconfig /flushdns` (Windows)

---

## 💡 Pratik İpuçları

### 1. DNS Propagasyonunu Hızlandır

```bash
# Local DNS cache temizle
ipconfig /flushdns  # Windows
sudo systemd-resolve --flush-caches  # Linux

# Public DNS kullan
# Google: 8.8.8.8
# Cloudflare: 1.1.1.1
```

### 2. SSL Sertifika Hatalarını Önle

```bash
# Cron job ile otomatik yenileme
0 3 * * * certbot renew --quiet && docker-compose restart nginx

# Manuel test:
sudo certbot renew --dry-run
```

### 3. Multi-Tenant Domain Testi

```python
# Django shell
docker-compose exec web python manage.py shell

from apps.tenants.models import Tenant, Domain

# Tenant oluştur
tenant = Tenant.objects.create(
    schema_name='tenant_test',
    name='Test Oteli',
    slug='test-oteli'
)

# Domain ekle
domain = Domain.objects.create(
    domain='test.saas2026.com',
    tenant=tenant,
    is_primary=True
)

# Test: https://test.saas2026.com
```

---

## 🎯 Hangi Yöntemi Seçmeliyim?

| Durum | Önerilen Yöntem |
|-------|-----------------|
| **Yeni başlıyorum** | 🏆 Cloudflare (en kolay) |
| **Küçük proje (1 domain)** | Registrar DNS (basit) |
| **Wildcard gerekli** | 🏆 Cloudflare (kolay) veya BIND (zor) |
| **Tam kontrol istiyorum** | BIND DNS Server (ileri seviye) |
| **DDoS koruması önemli** | 🏆 Cloudflare (ücretsiz) |
| **Hız önemli (CDN)** | 🏆 Cloudflare (ücretsiz CDN) |
| **Üçüncü parti istemiyorum** | BIND DNS Server + Let's Encrypt |

**%95 durumda: Cloudflare! 🏆**

---

## 📚 İlgili Dökümanlar

- 📄 **CLOUDFLARE_SETUP.md** - Cloudflare detaylı rehber
- 📄 **PRODUCTION_DEPLOYMENT.md** - VPS canlıya çıkış
- 📄 **README.md** - Proje genel döküman

---

**🎉 Artık cPanel olmadan domain yönetimi yapabilirsiniz!**

📅 Oluşturulma: 2025-11-09  
✍️ Geliştirici: SaaS 2026 Team



