# Docker Nginx Container Kaldırma

## ⚠️ Sorun: Nginx Container Port 80'i Kullanıyor

```
saas2026_nginx         bulutsaas-nginx         ...   nginx         14 minutes ago   Up 14 minutes               0.0.0.0:80->80/tcp
```

Nginx container'ı çalışıyor ve port 80'i kullanıyor. Host üzerinde Nginx kullanacağız, bu yüzden Docker container'ını kaldırmamız gerekiyor.

---

## ✅ ADIM 1: Nginx Container'ını Durdurun

```bash
docker compose stop nginx
```

**Veya:**

```bash
docker stop saas2026_nginx
```

---

## ✅ ADIM 2: docker-compose.yml'den Nginx Servisini Kaldırın

```bash
nano docker-compose.yml
```

**Nginx servisini bulun ve kaldırın veya yorum satırı yapın:**

**Bulun:**
```yaml
  # Nginx (Reverse Proxy & Static Files)
  nginx:
    build:
      context: .
      dockerfile: Dockerfile.nginx
    container_name: saas2026_nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d:ro
      - static_volume:/app/staticfiles:ro
      - media_volume:/app/media:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
      - ./certbot/www:/var/www/certbot:ro
    depends_on:
      - web
    networks:
      - saas_network
    restart: unless-stopped
```

**Yorum satırı yapın veya kaldırın:**
```yaml
  # Nginx servisi kaldırıldı - Host üzerinde Nginx kullanıyoruz
  # nginx:
  #   build:
  #     context: .
  #     dockerfile: Dockerfile.nginx
  #   container_name: saas2026_nginx
  #   ports:
  #     - "80:80"
  #     - "443:443"
  #   ...
```

**Kaydedin:** `Ctrl+O`, `Enter`, `Ctrl+X`

---

## ✅ ADIM 3: Container'ları Yeniden Başlatın

```bash
docker compose up -d
```

**Nginx container'ı olmadan başlamalı**

---

## ✅ ADIM 4: Container'ları Kontrol Edin

```bash
docker compose ps
```

**Beklenen:** Nginx container'ı görünmemeli

---

## ✅ ADIM 5: Port 80'i Kontrol Edin

```bash
netstat -tlnp | grep :80
```

**Beklenen:** Port 80 boş olmalı (Docker proxy yok)

---

## ✅ ADIM 6: Host Üzerinde Nginx'i Başlatın

```bash
systemctl start nginx
```

```bash
systemctl status nginx
```

**Beklenen:** Nginx başarıyla başlamalı

---

## 📋 Kontrol Listesi

### Docker Container'ları:
- [ ] Nginx container'ı durduruldu (`docker compose stop nginx`)
- [ ] docker-compose.yml'den Nginx servisi kaldırıldı
- [ ] Container'lar yeniden başlatıldı (`docker compose up -d`)
- [ ] Nginx container'ı görünmüyor (`docker compose ps`)

### Port Kontrolü:
- [ ] Port 80 boş (`netstat -tlnp | grep :80` → boş olmalı)
- [ ] Host üzerinde Nginx başlatıldı (`systemctl start nginx`)
- [ ] Nginx çalışıyor (`systemctl status nginx` → active)

---

## ✅ Hızlı Çözüm Komutları

```bash
# Nginx container'ını durdur
docker compose stop nginx

# docker-compose.yml'den Nginx servisini kaldır
nano docker-compose.yml
# (Nginx servisini yorum satırı yap veya kaldır)

# Container'ları yeniden başlat (Nginx olmadan)
docker compose up -d

# Container'ları kontrol et
docker compose ps

# Port 80'i kontrol et
netstat -tlnp | grep :80

# Host üzerinde Nginx'i başlat
systemctl start nginx
systemctl status nginx
```

---

## ✅ Sonuç

**Yapılacaklar:**

1. ✅ Nginx container'ını durdurun: `docker compose stop nginx`
2. ✅ docker-compose.yml'den Nginx servisini kaldırın (yorum satırı yapın)
3. ✅ Container'ları yeniden başlatın: `docker compose up -d`
4. ✅ Port 80'i kontrol edin: `netstat -tlnp | grep :80`
5. ✅ Host üzerinde Nginx'i başlatın: `systemctl start nginx`

**Başarılar! 🚀**

