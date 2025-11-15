# ☁️ Cloudflare ile Domain Yönetimi - Detaylı Rehber

> **cPanel olmadan domain yönetimi - Çok daha kolay ve ücretsiz!**

## 🎯 Cloudflare Nedir?

- ✅ **Ücretsiz DNS yönetimi**
- ✅ **Otomatik SSL sertifikası**
- ✅ **DDoS koruması**
- ✅ **CDN (İçerik Dağıtım Ağı)**
- ✅ **Web Application Firewall**
- ✅ **Wildcard domain desteği** (*.yourdomain.com)

**Maliyet:** ÜCRETSİZ! 🎉

---

## 📋 Adım 1: Cloudflare Hesabı Oluştur

### 1.1 Siteye Git

```
🌐 https://cloudflare.com
```

### 1.2 Kayıt Ol

```
1. "Sign Up" butonuna tıkla
2. E-posta ve şifre gir
3. E-posta doğrulama linkine tıkla
```

**Ekran Görüntüsü Tarifi:**
```
┌─────────────────────────────────┐
│  Cloudflare                  🔍 │
│                                 │
│  ┌─────────────────────────┐   │
│  │ Email: info@saas2026.com│   │
│  └─────────────────────────┘   │
│  ┌─────────────────────────┐   │
│  │ Password: ************  │   │
│  └─────────────────────────┘   │
│                                 │
│      [Sign Up]                  │
└─────────────────────────────────┘
```

---

## 📋 Adım 2: Domain Ekle

### 2.1 Dashboard'a Git

```
Login yap → "Add a Site" butonuna tıkla
```

### 2.2 Domain Adını Gir

```
Enter your site: saas2026.com
[Add Site]
```

**NOT:** 
- ✅ `saas2026.com` (doğru)
- ❌ `www.saas2026.com` (yanlış)
- ❌ `https://saas2026.com` (yanlış)

### 2.3 Plan Seç

```
┌─────────────────────────────────┐
│ Select a Plan:                  │
│                                 │
│ ○ Free          $0/mo    ✅     │
│ ○ Pro           $20/mo          │
│ ○ Business      $200/mo         │
│ ○ Enterprise    Custom          │
│                                 │
│         [Continue]              │
└─────────────────────────────────┘
```

**FREE planı seç!** Bizim için yeterli.

---

## 📋 Adım 3: DNS Kayıtlarını İncele

Cloudflare otomatik olarak mevcut DNS kayıtlarını tarar.

```
┌─────────────────────────────────────────────────┐
│ Review your DNS records                         │
├──────┬──────────┬────────────────┬──────────────┤
│ Type │   Name   │     Content    │    Status    │
├──────┼──────────┼────────────────┼──────────────┤
│  A   │    @     │  123.45.67.89  │  ✅ Found    │
│  A   │   www    │  123.45.67.89  │  ✅ Found    │
│ CNAME│   mail   │  mail.host.com │  ✅ Found    │
└──────┴──────────┴────────────────┴──────────────┘

[Continue]
```

**Şimdilik "Continue"** de, sonra düzenleyeceğiz.

---

## 📋 Adım 4: Nameserver'ları Değiştir (ÇOK ÖNEMLİ!)

### 4.1 Cloudflare Nameserver'ları

Cloudflare size 2 nameserver verecek:

```
┌─────────────────────────────────────────────────┐
│ Change your nameservers                         │
│                                                 │
│ Replace your nameservers with:                 │
│                                                 │
│  🔹 april.ns.cloudflare.com                    │
│  🔹 tony.ns.cloudflare.com                     │
│                                                 │
│ Where to change: Your domain registrar         │
│ (GoDaddy, Namecheap, etc.)                     │
│                                                 │
│          [Done, check nameservers]             │
└─────────────────────────────────────────────────┘
```

**ÖNEMLİ:** Bu nameserver'ları not alın!

### 4.2 Domain Registrar'da Değiştir

Domain'i nereden aldıysanız oraya gidin:

---

#### **A) GoDaddy'de Nameserver Değiştirme:**

```
1. GoDaddy.com → Login
2. "My Products" → Domain'inizi bulun
3. Domain yanında "..." → "Manage DNS"
4. "Nameservers" bölümü → "Change"
5. "Custom Nameservers" seç
6. Cloudflare'den aldığınız 2 nameserver'ı girin:
   
   Nameserver 1: april.ns.cloudflare.com
   Nameserver 2: tony.ns.cloudflare.com

7. "Save" → Onay ver
```

**Ekran Görüntüsü Tarifi:**
```
┌─────────────────────────────────────────┐
│ GoDaddy - Manage DNS                    │
│                                         │
│ Nameservers: ⚙️ Change                 │
│                                         │
│ ○ Default                               │
│ ● Custom                                │
│                                         │
│ Nameserver 1: [april.ns.cloudflare.com]│
│ Nameserver 2: [tony.ns.cloudflare.com] │
│                                         │
│              [Save]                     │
└─────────────────────────────────────────┘
```

---

#### **B) Namecheap'te Nameserver Değiştirme:**

```
1. Namecheap.com → Login
2. "Domain List" → Domain'inizi seç
3. "Manage" butonuna tıkla
4. "Nameservers" dropdown → "Custom DNS" seç
5. Cloudflare nameserver'larını gir:
   
   Nameserver 1: april.ns.cloudflare.com
   Nameserver 2: tony.ns.cloudflare.com

6. "✓" (Save) butonuna tıkla
```

---

#### **C) Türkiye Registrar'ları (Natro, Turhost, vb.):**

```
1. Registrar paneline giriş yap
2. Domain listesi → Domain'inizi seç
3. "DNS Yönetimi" veya "Nameserver Ayarları"
4. Cloudflare nameserver'larını gir
5. Kaydet
```

---

### 4.3 Cloudflare'de Doğrula

```
Nameserver'ları değiştirdikten sonra:

1. Cloudflare'e dön
2. "Done, check nameservers" butonuna tıkla
3. Bekleme süresi: 1-48 saat (genelde 2-6 saat)
```

**Cloudflare size e-posta gönderecek:**
```
✅ "Your site is now active on Cloudflare"
```

---

## 📋 Adım 5: DNS Kayıtlarını Ekle/Düzenle

Nameserver'lar aktif olduktan sonra:

### 5.1 Dashboard → DNS Settings

```
Cloudflare Dashboard → saas2026.com → DNS
```

### 5.2 Mevcut Kayıtları Temizle

Eski kayıtlar varsa sil (DELETE).

### 5.3 Yeni DNS Kayıtları Ekle

#### **A Record (Ana Domain):**

```
[Add Record]

Type:     A
Name:     @
IPv4:     [VPS_IP_ADRESINIZ]  (örn: 123.45.67.89)
TTL:      Auto
Proxy:    ✅ Proxied (turuncu bulut)

[Save]
```

**Ekran Görüntüsü:**
```
┌──────────────────────────────────────────────────┐
│ Add DNS Record                                   │
├──────────┬───────────────────────────────────────┤
│ Type:    │ A                                  ▼  │
│ Name:    │ @                                     │
│ IPv4:    │ 123.45.67.89                          │
│ TTL:     │ Auto                               ▼  │
│ Proxy:   │ ☁️ Proxied (orange cloud)        ON  │
│          │                                       │
│                    [Save]                        │
└──────────┴───────────────────────────────────────┘
```

---

#### **A Record (WWW):**

```
[Add Record]

Type:     A
Name:     www
IPv4:     [VPS_IP_ADRESINIZ]
TTL:      Auto
Proxy:    ✅ Proxied

[Save]
```

---

#### **A Record (Wildcard - Tenant Domain'leri için):**

**⚠️ ÇOK ÖNEMLİ!** Multi-tenant için gerekli!

```
[Add Record]

Type:     A
Name:     *
IPv4:     [VPS_IP_ADRESINIZ]
TTL:      Auto
Proxy:    ⚠️ DNS Only (gri bulut)  ← Dikkat!

[Save]
```

**Neden DNS Only?**
- Wildcard (*.saas2026.com) Cloudflare proxy ile çalışmaz
- Her tenant farklı subdomain kullanacak (otel1.saas2026.com, otel2.saas2026.com)

---

#### **Son Durum:**

```
┌──────┬────────┬────────────────┬─────────────────┐
│ Type │  Name  │    Content     │     Proxy       │
├──────┼────────┼────────────────┼─────────────────┤
│  A   │   @    │ 123.45.67.89   │ ☁️ Proxied     │
│  A   │  www   │ 123.45.67.89   │ ☁️ Proxied     │
│  A   │   *    │ 123.45.67.89   │ 🔘 DNS Only    │
└──────┴────────┴────────────────┴─────────────────┘
```

**Bu kadar!** DNS ayarları tamam. ✅

---

## 📋 Adım 6: SSL/TLS Ayarları

### 6.1 SSL/TLS Encryption Mode

```
Cloudflare Dashboard → SSL/TLS

Encryption Mode: Full (strict)  ← Seç
```

**Seçenekler:**
- ❌ Off - SSL yok
- ❌ Flexible - Cloudflare ↔ Ziyaretçi arası SSL (VPS'te yok)
- ✅ **Full (strict)** - Cloudflare ↔ VPS arası da SSL (önerilen)
- ⚠️ Full - Cloudflare ↔ VPS arası SSL (self-signed kabul eder)

```
┌─────────────────────────────────────────────────┐
│ SSL/TLS Encryption Mode                         │
│                                                 │
│ ○ Off                                           │
│ ○ Flexible                                      │
│ ● Full (strict)                          ✅     │
│ ○ Full                                          │
│                                                 │
│ Your SSL/TLS encryption mode controls how      │
│ Cloudflare connects to your origin server.     │
└─────────────────────────────────────────────────┘
```

### 6.2 Always Use HTTPS

```
SSL/TLS → Edge Certificates → Always Use HTTPS: ON
```

HTTP istekleri otomatik HTTPS'e yönlendirilir.

### 6.3 Automatic HTTPS Rewrites

```
SSL/TLS → Edge Certificates → Automatic HTTPS Rewrites: ON
```

---

## 📋 Adım 7: Güvenlik Ayarları (Opsiyonel)

### 7.1 Firewall Rules

```
Security → WAF → Create Firewall Rule

Örnek: Sadece Türkiye'den erişim:
- Field: Country
- Operator: does not equal
- Value: Turkey
- Action: Block
```

### 7.2 Rate Limiting

```
Security → WAF → Rate Limiting Rules

Örnek: API endpoint koruması:
- If incoming requests match: api/*
- When rate exceeds: 100 requests per 10 minutes
- Then: Block
```

---

## 📋 Adım 8: Test & Doğrulama

### 8.1 DNS Propagasyonu Kontrol

```bash
# Windows (PowerShell/CMD)
nslookup saas2026.com

# Sonuç:
Name:    saas2026.com
Address: 104.21.xxx.xxx  ← Cloudflare IP (proxy açıksa)
```

### 8.2 Wildcard Test

```bash
nslookup otel1.saas2026.com
nslookup otel2.saas2026.com

# Sonuç:
Address: 123.45.67.89  ← VPS IP (proxy kapalı)
```

### 8.3 Online Kontrol

```
🌐 https://dnschecker.org/

Domain: saas2026.com
Type: A Record
Check →

✅ Tüm lokasyonlarda yeşil ✓ olmalı
```

### 8.4 SSL Kontrolü

```
🌐 https://www.ssllabs.com/ssltest/

Domain: saas2026.com
Test →

✅ Grade A+ olmalı
```

---

## 📋 Adım 9: VPS'te Nginx Yapılandırma

Cloudflare DNS hazır, şimdi VPS'te ayarlar:

### 9.1 Nginx Config (Production)

```nginx
# nginx/conf.d/default.conf

# Main Domain
server {
    listen 443 ssl http2;
    server_name saas2026.com www.saas2026.com;
    
    # SSL (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/saas2026.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/saas2026.com/privkey.pem;
    
    # Django
    location / {
        proxy_pass http://web:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Wildcard - Tenant Domains
server {
    listen 443 ssl http2;
    server_name *.saas2026.com;
    
    # SSL (Wildcard certificate)
    ssl_certificate /etc/letsencrypt/live/saas2026.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/saas2026.com/privkey.pem;
    
    # Django (tenant routing)
    location / {
        proxy_pass http://web:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# HTTP → HTTPS Redirect
server {
    listen 80;
    server_name saas2026.com www.saas2026.com *.saas2026.com;
    return 301 https://$host$request_uri;
}
```

### 9.2 Let's Encrypt Wildcard SSL

```bash
# VPS'te SSH ile bağlan

# Certbot kur
sudo apt install certbot python3-certbot-dns-cloudflare -y

# Cloudflare API token oluştur:
# Cloudflare Dashboard → My Profile → API Tokens
# Create Token → Edit zone DNS → saas2026.com

# API token dosyası oluştur
sudo mkdir -p /root/.secrets
sudo nano /root/.secrets/cloudflare.ini

# İçeriği:
dns_cloudflare_api_token = YOUR_CLOUDFLARE_API_TOKEN

# Dosya izinleri
sudo chmod 600 /root/.secrets/cloudflare.ini

# Wildcard SSL sertifikası al
sudo certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d saas2026.com \
  -d *.saas2026.com \
  --email info@saas2026.com \
  --agree-tos \
  --non-interactive

# Sonuç:
# Successfully received certificate.
# Certificate is saved at: /etc/letsencrypt/live/saas2026.com/fullchain.pem
# Key is saved at:         /etc/letsencrypt/live/saas2026.com/privkey.pem

# Nginx'i yeniden başlat
docker-compose restart nginx
```

### 9.3 Otomatik SSL Yenileme

```bash
# Cron job ekle
sudo crontab -e

# Ekle: Her gün saat 03:00'te kontrol et
0 3 * * * certbot renew --quiet && docker-compose -f /var/www/saas2026/docker-compose.yml restart nginx
```

---

## 📋 Adım 10: Django Tenant Domain Ayarları

### 10.1 Admin Panelde Domain Ekle

```
Admin Panel → Domains → Add Domain

Domain: otel1.saas2026.com
Tenant: Test Oteli
Domain Type: subdomain
Is Primary: Yes

[Save]
```

### 10.2 Test

```bash
# Tarayıcıda
https://otel1.saas2026.com

# Django otomatik olarak tenant'ı tanıyacak
# otel1.saas2026.com → tenant_otel1 schema'sını kullanacak
```

---

## 📊 Cloudflare Dashboard - Özet

### Ana Sayfa:

```
┌─────────────────────────────────────────────────┐
│ saas2026.com                            Active  │
├─────────────────────────────────────────────────┤
│                                                 │
│ Quick Actions:                                  │
│  • DNS Settings                                 │
│  • SSL/TLS                                      │
│  • Firewall                                     │
│  • Speed                                        │
│  • Analytics                                    │
│                                                 │
│ Traffic (Last 24h):                            │
│  Requests:     12,345                          │
│  Bandwidth:    1.2 GB                          │
│  Threats:      23 blocked                      │
│                                                 │
└─────────────────────────────────────────────────┘
```

### DNS Kayıtları:

```
┌──────┬────────┬────────────────┬──────────────┐
│ Type │  Name  │    Content     │    Proxy     │
├──────┼────────┼────────────────┼──────────────┤
│  A   │   @    │ 123.45.67.89   │ ☁️ Proxied  │
│  A   │  www   │ 123.45.67.89   │ ☁️ Proxied  │
│  A   │   *    │ 123.45.67.89   │ 🔘 DNS Only │
└──────┴────────┴────────────────┴──────────────┘
```

---

## 🎯 Özet Checklist

### Cloudflare Kurulumu:

- [ ] Cloudflare hesabı oluştur
- [ ] Domain ekle
- [ ] FREE plan seç
- [ ] Nameserver'ları not al
- [ ] Domain registrar'da nameserver'ları değiştir
- [ ] DNS propagasyonunu bekle (2-6 saat)
- [ ] A record ekle: @ → VPS IP (proxied)
- [ ] A record ekle: www → VPS IP (proxied)
- [ ] A record ekle: * → VPS IP (DNS only) ⭐
- [ ] SSL/TLS mode: Full (strict)
- [ ] Always Use HTTPS: ON
- [ ] Test: nslookup, dnschecker.org

### VPS Ayarları:

- [ ] Let's Encrypt wildcard SSL al
- [ ] Nginx config güncelle (*.saas2026.com)
- [ ] Docker Compose yeniden başlat
- [ ] Test: https://saas2026.com
- [ ] Test: https://www.saas2026.com
- [ ] Test: https://test.saas2026.com (tenant)

---

## 🆘 Sorun Giderme

### Nameserver değişikliği çalışmıyor:

```bash
# Kontrol:
nslookup saas2026.com

# Eğer eski IP görünüyorsa:
# - 24-48 saat bekle (DNS propagasyonu)
# - Domain registrar'da nameserver'ları kontrol et
# - Cloudflare'de "Check nameservers" butonuna bas
```

### SSL hatası:

```bash
# VPS'te SSL sertifikası var mı?
sudo ls -la /etc/letsencrypt/live/saas2026.com/

# Yoksa tekrar al:
sudo certbot certonly --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d saas2026.com -d *.saas2026.com
```

### Wildcard domain çalışmıyor:

```
1. Cloudflare'de * A record var mı?
2. DNS Only (gri bulut) seçili mi?
3. VPS'te wildcard SSL var mı?
4. Nginx config *.saas2026.com server_name var mı?
```

---

## 💰 Cloudflare Ücretsiz Plan Limitleri

```
✅ DNS Yönetimi: Sınırsız
✅ DDoS Koruması: Sınırsız
✅ SSL Sertifikası: Sınırsız
✅ CDN: Sınırsız
✅ Bandwidth: Sınırsız
✅ Firewall Rules: 5 kural
✅ Page Rules: 3 kural
✅ Analytics: Temel

❌ Image Optimization: Yok (Pro'da)
❌ Mobile Optimization: Yok (Pro'da)
❌ Advanced DDoS: Yok (Business'ta)
```

**Bizim için yeterli!** 🎉

---

## 🎉 Tebrikler!

Artık **cPanel olmadan** domain yönetimi yapabiliyorsunuz!

**Cloudflare ile:**
- ✅ DNS yönetimi
- ✅ SSL otomasyonu
- ✅ DDoS koruması
- ✅ Wildcard domain desteği
- ✅ Web interface
- ✅ Tamamen ücretsiz!

**cPanel'den daha iyi!** 🚀

---

📅 Oluşturulma: 2025-11-09  
✍️ Geliştirici: SaaS 2026 Team



