# Hostinger Landing Page Kaldırma Rehberi

## 🔍 Sorun

Hostinger VPS'te varsayılan bir landing page var ve bu bizim Django uygulamamızı engelliyor:
- `srv1132080.hstgr.cloud` → `http://88.255.216.16/landpage?op=2&ms=...`

## ✅ Çözüm

### 1. Hostinger'in Varsayılan Web Server'ını Kontrol Et

Hostinger VPS'te varsayılan olarak Apache veya OpenLiteSpeed çalışıyor olabilir. Bunu kontrol edin:

```bash
# Apache kontrolü
systemctl status apache2
# veya
systemctl status httpd

# OpenLiteSpeed kontrolü
systemctl status litespeed
# veya
systemctl status openlitespeed

# Nginx kontrolü (bizim container)
docker compose ps nginx
```

### 2. Varsayılan Web Server'ı Durdur

Eğer Apache veya OpenLiteSpeed çalışıyorsa, port 80'i bizim Nginx container'ımıza bırakmak için durdurun:

```bash
# Apache durdur
sudo systemctl stop apache2
sudo systemctl disable apache2

# veya OpenLiteSpeed durdur
sudo systemctl stop litespeed
sudo systemctl disable litespeed
```

### 3. Port 80 Kontrolü

Port 80'in kim tarafından kullanıldığını kontrol edin:

```bash
# Port 80'i kullanan process'leri göster
sudo netstat -tlnp | grep :80
# veya
sudo ss -tlnp | grep :80
# veya
sudo lsof -i :80
```

### 4. Docker Nginx Container'ının Port 80'i Dinlediğinden Emin Olun

```bash
# Docker compose dosyasını kontrol et
cat docker-compose.yml | grep -A 5 "nginx:"

# Nginx container'ının port mapping'i:
# ports:
#   - "80:80"
#   - "443:443"
```

### 5. Nginx Yapılandırmasını Güncelle

`nginx/conf.d/default.conf` dosyasına Hostinger domain'ini ekledik:

```nginx
server {
    listen 80;
    server_name bulutacente.com.tr www.bulutacente.com.tr 72.62.35.155 88.255.216.16 srv1132080.hstgr.cloud localhost;
    # ...
}
```

### 6. Django ALLOWED_HOSTS'i Güncelle

`config/settings.py` dosyasına Hostinger domain ve IP'lerini ekledik:

```python
ALLOWED_HOSTS.extend([
    'srv1132080.hstgr.cloud',
    '88.255.216.16',
])
```

### 7. Container'ı Yeniden Başlat

```bash
cd /docker/bulutsaas

# Container'ı yeniden başlat
docker compose restart nginx

# Veya tamamen yeniden başlat
docker compose down
docker compose up -d
```

### 8. Test Et

```bash
# Hostinger domain ile test
curl -v http://srv1132080.hstgr.cloud/admin/ 2>&1 | head -30

# IP adresi ile test
curl -v http://88.255.216.16/admin/ 2>&1 | head -30

# Health check
curl http://srv1132080.hstgr.cloud/health/
```

## 🔧 Alternatif Çözüm: Hostinger Panel'den Ayarlama

Eğer Hostinger'in kendi panelinden landing page'i kapatabilirseniz:

1. Hostinger VPS Panel'e giriş yapın
2. "Web Server" veya "Apache/OpenLiteSpeed" ayarlarına gidin
3. Varsayılan landing page'i kapatın
4. Port 80'i Docker Nginx'e bırakın

## ⚠️ Önemli Notlar

1. **Port Çakışması**: Eğer Apache veya OpenLiteSpeed port 80'i kullanıyorsa, Docker Nginx container'ı çalışmayacaktır. Mutlaka durdurun.

2. **Firewall**: Port 80 ve 443'in açık olduğundan emin olun:
   ```bash
   sudo ufw status
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   ```

3. **DNS**: `srv1132080.hstgr.cloud` domain'i Hostinger tarafından otomatik olarak yönetiliyor. Bu domain'i kullanmak istiyorsanız, Hostinger panel'den DNS ayarlarını kontrol edin.

## 📋 Kontrol Listesi

- [ ] Apache/OpenLiteSpeed durduruldu mu?
- [ ] Port 80 Docker Nginx tarafından kullanılıyor mu?
- [ ] Nginx config'e Hostinger domain'i eklendi mi?
- [ ] Django ALLOWED_HOSTS'e Hostinger domain'i eklendi mi?
- [ ] Container yeniden başlatıldı mı?
- [ ] Test edildi mi?

## 🆘 Sorun Giderme

### Port 80 Zaten Kullanımda Hatası

```bash
# Hangi process port 80'i kullanıyor?
sudo lsof -i :80

# Process'i durdur
sudo kill -9 <PID>

# Veya Apache/OpenLiteSpeed'i durdur
sudo systemctl stop apache2
sudo systemctl stop litespeed
```

### Docker Nginx Container Çalışmıyor

```bash
# Container loglarını kontrol et
docker compose logs nginx --tail=100

# Container'ı yeniden başlat
docker compose restart nginx

# Container'ı yeniden oluştur
docker compose up -d --force-recreate nginx
```

### Domain Hala Landing Page Gösteriyor

1. Browser cache'ini temizleyin
2. Incognito/Private mode'da test edin
3. DNS cache'ini temizleyin:
   ```bash
   # Windows
   ipconfig /flushdns
   
   # Linux/Mac
   sudo systemd-resolve --flush-caches
   # veya
   sudo dscacheutil -flushcache
   ```

