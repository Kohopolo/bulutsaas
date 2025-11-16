# Docker Compose Nginx Servisi Silme - Detaylı Rehber

## 📝 docker-compose.yml'den Nginx Servisini Kaldırma

Nano editöründe `docker-compose.yml` dosyası açık. Nginx servisini tam olarak şu şekilde kaldırın:

---

## ❌ SİLİNECEK BÖLÜM

**Şu tüm bloğu silin veya yorum satırı yapın:**

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
      - /etc/letsencrypt:/etc/letsencrypt:ro  # SSL sertifikaları
      - ./certbot/www:/var/www/certbot:ro     # Certbot webroot
    depends_on:
      - web
    networks:
      - saas_network
    restart: unless-stopped
```

---

## ✅ YÖNTEM 1: Yorum Satırı Yapma (ÖNERİLEN)

**Nano editöründe:**

1. **Nginx servisinin başlangıcını bulun:** `# Nginx (Reverse Proxy & Static Files)`

2. **Her satırın başına `#` ekleyin:**

```yaml
  # Nginx (Reverse Proxy & Static Files)
  # nginx:
  #   build:
  #     context: .
  #     dockerfile: Dockerfile.nginx
  #   container_name: saas2026_nginx
  #   ports:
  #     - "80:80"
  #     - "443:443"
  #   volumes:
  #     - ./nginx/conf.d:/etc/nginx/conf.d:ro
  #     - static_volume:/app/staticfiles:ro
  #     - media_volume:/app/media:ro
  #     - /etc/letsencrypt:/etc/letsencrypt:ro  # SSL sertifikaları
  #     - ./certbot/www:/var/www/certbot:ro     # Certbot webroot
  #   depends_on:
  #     - web
  #   networks:
  #     - saas_network
  #   restart: unless-stopped
```

**Nasıl yapılır:**
- Her satırın başına gidin
- `#` ekleyin
- Veya tüm bloğu seçip `Alt+3` (nano'da yorum satırı yapma)

---

## ✅ YÖNTEM 2: Tamamen Silme

**Nano editöründe:**

1. **Nginx servisinin başlangıcını bulun:** `# Nginx (Reverse Proxy & Static Files)`

2. **Tüm bloğu silin:**
   - İlk satırdan başlayın: `# Nginx (Reverse Proxy & Static Files)`
   - Son satıra kadar: `restart: unless-stopped`
   - Tüm bloğu seçin ve silin

**Nasıl yapılır:**
- İlk satıra gidin (`Ctrl+W` ile arama yapabilirsiniz: "Nginx")
- `Ctrl+K` ile satırları kesin (her satır için tekrar edin)
- Veya tüm bloğu seçip `Delete` tuşuna basın

---

## 📋 Nano Editöründe Yapılacaklar

### Adım 1: Nginx Servisini Bulun

```bash
Ctrl+W  # Arama yap
```

**Arama terimi:** `nginx:`

### Adım 2: Yorum Satırı Yapın

**Her satırın başına `#` ekleyin:**

- `nginx:` → `# nginx:`
- `build:` → `#   build:`
- `context: .` → `#     context: .`
- vb.

**Veya tüm bloğu seçip silin**

### Adım 3: Dosyayı Kaydedin

```bash
Ctrl+O  # Kaydet
Enter   # Dosya adını onayla
Ctrl+X  # Çık
```

---

## ✅ Kontrol

Dosyayı kaydettikten sonra:

```bash
# Docker Compose syntax kontrolü
docker compose config

# Eğer hata yoksa, container'ları yeniden başlat
docker compose up -d

# Nginx container'ı görünmemeli
docker compose ps
```

---

## 📝 Örnek: Yorum Satırı Yapılmış Hali

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
  #   volumes:
  #     - ./nginx/conf.d:/etc/nginx/conf.d:ro
  #     - static_volume:/app/staticfiles:ro
  #     - media_volume:/app/media:ro
  #     - /etc/letsencrypt:/etc/letsencrypt:ro
  #     - ./certbot/www:/var/www/certbot:ro
  #   depends_on:
  #     - web
  #   networks:
  #     - saas_network
  #   restart: unless-stopped
```

---

## ✅ Sonuç

**Yapılacaklar:**

1. ✅ Nginx servisini bulun (`Ctrl+W` ile "nginx:" arayın)
2. ✅ Tüm bloğu yorum satırı yapın (her satırın başına `#` ekleyin)
3. ✅ Veya tüm bloğu silin
4. ✅ Dosyayı kaydedin (`Ctrl+O`, `Enter`, `Ctrl+X`)
5. ✅ Container'ları yeniden başlatın (`docker compose up -d`)

**Başarılar! 🚀**

