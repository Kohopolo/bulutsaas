# Web Container Durum Kontrolü
## 502 Bad Gateway ve 404 Hataları İçin

---

## 🔍 Kontrol Komutları

### 1. Web Container Durumunu Kontrol Edin

```bash
# Container durumunu kontrol et
docker compose ps web

# Web container loglarını kontrol et (son 100 satır)
docker compose logs web --tail=100

# Web container'ın çalışıp çalışmadığını kontrol et
docker exec saas2026_web ps aux | grep gunicorn

# Port 8000'i dinleyip dinlemediğini kontrol et
docker exec saas2026_web netstat -tlnp | grep 8000
# veya
docker exec saas2026_web ss -tlnp | grep 8000
```

### 2. Container'a Bağlanıp Test Edin

```bash
# Container'a bağlan
docker exec -it saas2026_web bash

# Container içinde:
# Gunicorn process'lerini kontrol et
ps aux | grep gunicorn

# Port 8000'i kontrol et
netstat -tlnp | grep 8000

# Django'yu test et
python manage.py check

# Health check endpoint'ini test et
curl http://localhost:8000/health/

# Admin endpoint'ini test et
curl http://localhost:8000/admin/
```

### 3. Container'ları Yeniden Başlatın

```bash
# Tüm container'ları durdur
docker compose down

# Yeniden başlat
docker compose up -d

# Logları izle (30 saniye bekleyin)
sleep 30
docker compose logs -f web
```

### 4. Test Edin

```bash
# Container portundan direkt test
curl -v http://localhost:8000/health/
curl -v http://localhost:8000/admin/

# Nginx üzerinden test
curl -v http://localhost/health/
curl -v http://localhost/admin/
curl -v http://72.62.35.155/health/
curl -v http://72.62.35.155/admin/
```

---

## 🔧 Olası Sorunlar

### Sorun 1: Web Container Başlamıyor

**Belirtiler:**
- `docker compose ps` web container'ı "Restarting" gösteriyor
- Loglarda hata mesajları var

**Çözüm:**
```bash
# Logları kontrol et
docker compose logs web --tail=200

# Database bağlantısını kontrol et
docker exec saas2026_web python manage.py check --database default

# Migration'ları kontrol et
docker exec saas2026_web python manage.py showmigrations
```

### Sorun 2: Gunicorn Çalışmıyor

**Belirtiler:**
- Port 8000 dinlenmiyor
- Gunicorn process'i yok

**Çözüm:**
```bash
# Container'ı yeniden başlat
docker compose restart web

# Veya tamamen yeniden oluştur
docker compose up -d --force-recreate web
```

### Sorun 3: Database Bağlantı Sorunu

**Belirtiler:**
- Migration hataları
- Database connection errors

**Çözüm:**
```bash
# Database container'ın çalıştığını kontrol et
docker compose ps db

# Database bağlantısını test et
docker exec saas2026_web python manage.py dbshell
```

---

## 📝 Hızlı Teşhis Komutları

```bash
# Tüm container durumları
docker compose ps

# Web container logları
docker compose logs web --tail=50

# Database container logları
docker compose logs db --tail=50

# Nginx container logları
docker compose logs nginx --tail=50

# Network durumu
docker network inspect bulutsaas_saas_network | grep -A 10 "Containers"
```

