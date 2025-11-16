# Docker Port 80 Çakışması Çözümü

## ⚠️ Sorun: Docker Container Port 80'i Kullanıyor

```
tcp        0      0 0.0.0.0:80              0.0.0.0:*               LISTEN      5227/docker-proxy
```

Port 80'i Docker proxy kullanıyor. Bu, Docker container'larından birinin (muhtemelen Nginx container'ı) port 80'i kullanıyor demektir.

---

## ✅ ÇÖZÜM: Docker Container'larını Kontrol Edin

### ADIM 1: Çalışan Container'ları Kontrol Edin

```bash
docker ps
```

**Veya:**

```bash
docker compose ps
```

**Beklenen:** Nginx container'ı çalışıyor olabilir

---

## ✅ ADIM 2: Nginx Container'ını Durdurun

### Eğer docker-compose.yml'de Nginx servisi varsa:

```bash
docker compose stop nginx
```

**Veya:**

```bash
docker stop saas2026_nginx
```

**Veya tüm container'ları durdurun:**

```bash
docker compose down
```

---

## ✅ ADIM 3: docker-compose.yml'den Nginx Servisini Kaldırın

**ÖNEMLİ:** Host üzerinde Nginx kullanacağız, Docker container'ındaki Nginx'e gerek yok!

```bash
nano docker-compose.yml
```

**Nginx servisini bulun ve kaldırın veya yorum satırı yapın:**

```yaml
# Nginx servisini kaldırın veya yorum satırı yapın
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

## ✅ ADIM 4: Container'ları Yeniden Başlatın

```bash
docker compose up -d
```

**Nginx container'ı olmadan başlamalı**

---

## ✅ ADIM 5: Port 80'i Tekrar Kontrol Edin

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

## 🔍 docker-compose.yml Kontrolü

### Nginx Servisini Kaldırma:

**docker-compose.yml dosyasında şu kısmı bulun:**

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
  ...
```

**Bu kısmı kaldırın veya yorum satırı yapın:**

```yaml
# Nginx servisi kaldırıldı - Host üzerinde Nginx kullanıyoruz
# nginx:
#   build:
#     context: .
#     dockerfile: Dockerfile.nginx
#   ...
```

---

## 📋 Kontrol Listesi

### Docker Container'ları:
- [ ] Çalışan container'lar kontrol edildi (`docker compose ps`)
- [ ] Nginx container'ı durduruldu (`docker compose stop nginx`)
- [ ] docker-compose.yml'den Nginx servisi kaldırıldı
- [ ] Container'lar yeniden başlatıldı (`docker compose up -d`)

### Port Kontrolü:
- [ ] Port 80 boş (`netstat -tlnp | grep :80` → boş olmalı)
- [ ] Host üzerinde Nginx başlatıldı (`systemctl start nginx`)
- [ ] Nginx çalışıyor (`systemctl status nginx` → active)

---

## ✅ Hızlı Çözüm Komutları

```bash
# Container'ları kontrol et
docker compose ps

# Nginx container'ını durdur
docker compose stop nginx

# Veya tüm container'ları durdur
docker compose down

# docker-compose.yml'den Nginx servisini kaldır
nano docker-compose.yml

# Container'ları yeniden başlat (Nginx olmadan)
docker compose up -d

# Port 80'i kontrol et
netstat -tlnp | grep :80

# Host üzerinde Nginx'i başlat
systemctl start nginx
systemctl status nginx
```

---

## ✅ Sonuç

**Yapılacaklar:**

1. ✅ Container'ları kontrol edin: `docker compose ps`
2. ✅ Nginx container'ını durdurun: `docker compose stop nginx`
3. ✅ docker-compose.yml'den Nginx servisini kaldırın
4. ✅ Container'ları yeniden başlatın: `docker compose up -d`
5. ✅ Port 80'i kontrol edin: `netstat -tlnp | grep :80`
6. ✅ Host üzerinde Nginx'i başlatın: `systemctl start nginx`

**Önce `docker compose ps` komutunu çalıştırın ve çıktıyı paylaşın!**

**Başarılar! 🚀**

