# 502 Bad Gateway Sorunu Çözüm Rehberi
## Nginx Web Container'a Bağlanamıyor

---

## 🔍 Sorun Tespiti

Nginx loglarında görülen hata:
```
connect() failed (111: Connection refused) while connecting to upstream
upstream: "http://172.18.0.4:8000/admin/"
```

Bu, web container'ın port 8000'i dinlemediği veya henüz hazır olmadığı anlamına gelir.

---

## ✅ Çözüm Adımları

### 1. Web Container Durumunu Kontrol Edin

```bash
# Container durumunu kontrol et
docker compose ps

# Web container loglarını kontrol et
docker compose logs web --tail=100

# Web container'ın port 8000'i dinleyip dinlemediğini kontrol et
docker exec saas2026_web netstat -tlnp | grep 8000
# veya
docker exec saas2026_web ss -tlnp | grep 8000
```

### 2. Web Container'ı Yeniden Başlatın

```bash
# Web container'ı yeniden başlat
docker compose restart web

# Biraz bekleyin (30 saniye)
sleep 30

# Durumu kontrol edin
docker compose ps web
```

### 3. Container'ları Tamamen Yeniden Başlatın

```bash
# Tüm container'ları durdur
docker compose down

# Yeniden başlat
docker compose up -d

# Logları izle
docker compose logs -f web
```

### 4. Git Çakışmasını Çözün

```bash
cd /docker/bulutsaas

# Çakışan dosyayı yedekle veya sil
mv VPS_MIDDLEWARE_TEST.sh VPS_MIDDLEWARE_TEST.sh.bak

# GitHub'dan çek
git pull origin main
```

### 5. Test Edin

```bash
# Container portundan direkt test
curl http://localhost:8000/admin/

# Nginx üzerinden test
curl http://localhost/admin/
curl http://72.62.35.155/admin/
```

---

## 🔧 Olası Sorunlar ve Çözümleri

### Sorun 1: Web Container Başlamıyor

**Kontrol:**
```bash
docker compose logs web --tail=100
```

**Çözüm:**
- Database bağlantısını kontrol edin
- Environment variable'ları kontrol edin
- Migration'ları kontrol edin

### Sorun 2: Port 8000 Dinlenmiyor

**Kontrol:**
```bash
docker exec saas2026_web netstat -tlnp | grep 8000
```

**Çözüm:**
- Gunicorn'un çalışıp çalışmadığını kontrol edin
- Container'ı yeniden başlatın

### Sorun 3: Network Sorunu

**Kontrol:**
```bash
docker network inspect bulutsaas_saas_network
```

**Çözüm:**
- Network'ü yeniden oluşturun
- Container'ları yeniden başlatın

---

## 📝 Hızlı Komutlar

```bash
# Tüm container'ları durdur ve yeniden başlat
docker compose down && docker compose up -d

# Web container loglarını izle
docker compose logs -f web

# Container durumunu kontrol et
docker compose ps

# Health check
curl http://localhost/health/
curl http://localhost:8000/health/
```

