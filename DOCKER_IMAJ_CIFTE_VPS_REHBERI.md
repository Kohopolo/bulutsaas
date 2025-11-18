# 🐳 Docker İmaj Çift VPS Kurulum Rehberi

> **Hetzner ve Hostinger VPS'lerinde aynı anda çalışan Docker kurulumları**

---

## 📋 Genel Bakış

Bu rehber, **Hetzner** ve **Hostinger** VPS'lerinde aynı projeyi **birbirini bozmadan** çalıştırmak için hazırlanmıştır.

### ✅ Özellikler

- ✅ **Ayrı dizinler** - Her VPS kendi dizininde çalışır
- ✅ **Ayrı container isimleri** - Çakışma yok
- ✅ **Ayrı portlar** - Hostinger farklı portlar kullanır
- ✅ **Ayrı volume'lar** - Veriler birbirinden izole
- ✅ **Ayrı network'ler** - Network çakışması yok

---

## 🏗️ Yapı Karşılaştırması

### Hetzner VPS (78.46.142.212)

```
Dizin: /var/www/bulutsaas-hetzner
Compose: docker-compose.simple.hetzner.yml
Container'lar:
  - saas2026_app_hetzner
  - saas2026_db_hetzner
  - saas2026_redis_hetzner
Portlar:
  - 8000 (Django)
  - 5432 (PostgreSQL)
  - 6379 (Redis)
```

### Hostinger VPS (72.62.35.155)

```
Dizin: /var/www/bulutsaas-hostinger
Compose: docker-compose.simple.hostinger.yml
Container'lar:
  - saas2026_app_hostinger
  - saas2026_db_hostinger
  - saas2026_redis_hostinger
Portlar:
  - 8001 (Django) ← Farklı!
  - 5433 (PostgreSQL) ← Farklı!
  - 6380 (Redis) ← Farklı!
```

---

## 🚀 Hetzner VPS Kurulumu

### Adım 1: SSH Bağlantısı

```bash
ssh root@78.46.142.212
```

### Adım 2: Script'i İndir ve Çalıştır

```bash
# Script'i indir
wget https://raw.githubusercontent.com/Kohopolo/bulutsaas/main/HETZNER_DOCKER_IMAJ_OTOMATIK_KURULUM.sh

# Çalıştırılabilir yap
chmod +x HETZNER_DOCKER_IMAJ_OTOMATIK_KURULUM.sh

# Çalıştır
./HETZNER_DOCKER_IMAJ_OTOMATIK_KURULUM.sh
```

**Script otomatik olarak:**
1. ✅ Docker kurulumu (eğer yoksa)
2. ✅ Projeyi GitHub'dan çeker
3. ✅ `.env` dosyasını oluşturur (IP: 78.46.142.212)
4. ✅ Docker imajını build eder
5. ✅ Tüm servisleri başlatır

---

## 🚀 Hostinger VPS Kurulumu

### Adım 1: SSH Bağlantısı

```bash
ssh root@72.62.35.155
```

### Adım 2: Script'i İndir ve Çalıştır

```bash
# Script'i indir
wget https://raw.githubusercontent.com/Kohopolo/bulutsaas/main/HOSTINGER_DOCKER_IMAJ_OTOMATIK_KURULUM.sh

# Çalıştırılabilir yap
chmod +x HOSTINGER_DOCKER_IMAJ_OTOMATIK_KURULUM.sh

# Çalıştır
./HOSTINGER_DOCKER_IMAJ_OTOMATIK_KURULUM.sh
```

**Script otomatik olarak:**
1. ✅ Docker kontrolü (Hostinger'da genelde kurulu)
2. ✅ Projeyi GitHub'dan çeker
3. ✅ `.env` dosyasını oluşturur (IP: 72.62.35.155)
4. ✅ Docker imajını build eder
5. ✅ Tüm servisleri başlatır

---

## 🔧 Servis Yönetimi

### Hetzner VPS

```bash
cd /var/www/bulutsaas-hetzner

# Servisleri başlat
docker compose -f docker-compose.simple.hetzner.yml up -d

# Servisleri durdur
docker compose -f docker-compose.simple.hetzner.yml down

# Logları izle
docker compose -f docker-compose.simple.hetzner.yml logs -f

# Durum kontrolü
docker compose -f docker-compose.simple.hetzner.yml ps
```

### Hostinger VPS

```bash
cd /var/www/bulutsaas-hostinger

# Servisleri başlat
docker compose -f docker-compose.simple.hostinger.yml up -d

# Servisleri durdur
docker compose -f docker-compose.simple.hostinger.yml down

# Logları izle
docker compose -f docker-compose.simple.hostinger.yml logs -f

# Durum kontrolü
docker compose -f docker-compose.simple.hostinger.yml ps
```

---

## 🌐 Nginx Yapılandırması

### Hetzner VPS Nginx

```nginx
server {
    listen 80;
    server_name 78.46.142.212 _;

    location /static/ {
        alias /var/www/bulutsaas-hetzner/staticfiles/;
    }

    location /media/ {
        alias /var/www/bulutsaas-hetzner/media/;
    }

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Hostinger VPS Nginx

```nginx
server {
    listen 80;
    server_name 72.62.35.155 _;

    location /static/ {
        alias /var/www/bulutsaas-hostinger/staticfiles/;
    }

    location /media/ {
        alias /var/www/bulutsaas-hostinger/media/;
    }

    location / {
        proxy_pass http://127.0.0.1:8001;  # ← Farklı port!
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 🔍 Çakışma Kontrolü

### Container İsimleri

```bash
# Hetzner container'ları
docker ps | grep hetzner
# saas2026_app_hetzner
# saas2026_db_hetzner
# saas2026_redis_hetzner

# Hostinger container'ları
docker ps | grep hostinger
# saas2026_app_hostinger
# saas2026_db_hostinger
# saas2026_redis_hostinger
```

### Port Kullanımı

```bash
# Hetzner portları
netstat -tulpn | grep -E '8000|5432|6379'

# Hostinger portları
netstat -tulpn | grep -E '8001|5433|6380'
```

### Volume'lar

```bash
# Hetzner volume'ları
docker volume ls | grep hetzner
# postgres_data_hetzner
# redis_data_hetzner
# static_volume_hetzner
# media_volume_hetzner

# Hostinger volume'ları
docker volume ls | grep hostinger
# postgres_data_hostinger
# redis_data_hostinger
# static_volume_hostinger
# media_volume_hostinger
```

---

## 🔄 Güncelleme

### Hetzner VPS Güncelleme

```bash
cd /var/www/bulutsaas-hetzner
git pull
docker compose -f docker-compose.simple.hetzner.yml build
docker compose -f docker-compose.simple.hetzner.yml up -d
```

### Hostinger VPS Güncelleme

```bash
cd /var/www/bulutsaas-hostinger
git pull
docker compose -f docker-compose.simple.hostinger.yml build
docker compose -f docker-compose.simple.hostinger.yml up -d
```

---

## ⚠️ Önemli Notlar

1. **Ayrı Dizinler**: Her VPS kendi dizininde çalışır, birbirini etkilemez
2. **Ayrı Portlar**: Hostinger farklı portlar kullanır (çakışma yok)
3. **Ayrı Container'lar**: Container isimleri farklıdır
4. **Ayrı Volume'lar**: Veriler birbirinden izole
5. **Ayrı Network'ler**: Network çakışması yok

---

## 📊 Karşılaştırma Tablosu

| Özellik | Hetzner | Hostinger |
|---------|---------|-----------|
| **IP** | 78.46.142.212 | 72.62.35.155 |
| **Dizin** | `/var/www/bulutsaas-hetzner` | `/var/www/bulutsaas-hostinger` |
| **Compose** | `docker-compose.simple.hetzner.yml` | `docker-compose.simple.hostinger.yml` |
| **Django Port** | 8000 | 8001 |
| **PostgreSQL Port** | 5432 | 5433 |
| **Redis Port** | 6379 | 6380 |
| **Container Prefix** | `saas2026_*_hetzner` | `saas2026_*_hostinger` |
| **Volume Prefix** | `*_hetzner` | `*_hostinger` |
| **Network** | `saas_network_hetzner` | `saas_network_hostinger` |

---

## ✅ Kontrol Listesi

### Hetzner VPS
- [ ] Script çalıştırıldı
- [ ] Container'lar çalışıyor
- [ ] Nginx yapılandırıldı
- [ ] Site erişilebilir: `http://78.46.142.212`

### Hostinger VPS
- [ ] Script çalıştırıldı
- [ ] Container'lar çalışıyor
- [ ] Nginx yapılandırıldı
- [ ] Site erişilebilir: `http://72.62.35.155`

---

## 🎯 Sonuç

Artık **her iki VPS'te de** proje **birbirini bozmadan** çalışıyor! 

- ✅ Ayrı dizinler
- ✅ Ayrı container'lar
- ✅ Ayrı portlar
- ✅ Ayrı volume'lar
- ✅ Ayrı network'ler

**Sorularınız için:** GitHub Issues veya dokümantasyonu kontrol edin.

