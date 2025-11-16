# CloudPanel Docker Aktifleştirme Rehberi

## ⚠️ Sorun: Docker CloudPanel'de Görünmüyor

CloudPanel'de Docker desteğinin aktif olması gerekiyor. Bu rehber Docker'ı CloudPanel'de nasıl aktifleştireceğinizi gösterir.

---

## 🔍 Docker Durumunu Kontrol Etme

### 1. SSH ile VPS'e Bağlanın

```bash
ssh root@88.255.216.16
```

### 2. Docker Durumunu Kontrol Edin

```bash
# Docker servisinin çalışıp çalışmadığını kontrol et
systemctl status docker

# Docker daemon'ın çalışıp çalışmadığını kontrol et
docker ps

# Docker Compose versiyonunu kontrol et
docker compose version
```

**Beklenen Çıktı:**
```
● docker.service - Docker Application Container Engine
     Loaded: loaded (/lib/systemd/system/docker.service; enabled; vendor preset: enabled)
     Active: active (running) since ...

CONTAINER ID   IMAGE     COMMAND   CREATED   STATUS    PORTS     NAMES
Docker Compose version v2.40.3
```

---

## ✅ CloudPanel'de Docker Aktifleştirme

### Yöntem 1: CloudPanel Settings'den Aktifleştirme

1. **CloudPanel'e giriş yapın:**
   ```
   https://88.255.216.16:8443
   ```

2. **Settings → System → Docker** bölümüne gidin

3. **Docker Status** kontrol edin:
   - ✅ **Enabled** olmalı
   - ❌ **Disabled** ise **Enable** butonuna tıklayın

4. **Save** butonuna tıklayın

---

### Yöntem 2: CloudPanel CLI ile Aktifleştirme

SSH üzerinden CloudPanel CLI kullanarak Docker'ı aktifleştirin:

```bash
# CloudPanel CLI'ye erişim
/usr/local/bin/clpctl

# Docker durumunu kontrol et
clpctl system:docker:status

# Docker'ı aktifleştir
clpctl system:docker:enable

# Docker durumunu tekrar kontrol et
clpctl system:docker:status
```

---

### Yöntem 3: Manuel Docker Servis Kontrolü

Eğer CloudPanel Docker'ı görmüyorsa, Docker servisini kontrol edin:

```bash
# Docker servisini başlat
systemctl start docker

# Docker servisini otomatik başlatmayı etkinleştir
systemctl enable docker

# Docker servis durumunu kontrol et
systemctl status docker

# Docker daemon'ı yeniden başlat
systemctl restart docker
```

---

## 🔧 CloudPanel Docker Entegrasyonu

### CloudPanel'in Docker'ı Görmesi İçin:

1. **Docker servisi çalışıyor olmalı:**
   ```bash
   systemctl status docker
   ```

2. **Docker socket erişilebilir olmalı:**
   ```bash
   ls -la /var/run/docker.sock
   ```

3. **CloudPanel kullanıcısı docker grubunda olmalı:**
   ```bash
   # CloudPanel'in hangi kullanıcıyı kullandığını kontrol et
   ps aux | grep cloudpanel | head -5
   
   # Root kullanıcısını docker grubuna ekle (zaten yapıldı)
   usermod -aG docker root
   
   # CloudPanel kullanıcısını docker grubuna ekle (eğer varsa)
   # CloudPanel genellikle root kullanıcısı ile çalışır
   ```

---

## 🐛 Sorun Giderme

### Sorun 1: Docker Servisi Çalışmıyor

```bash
# Docker servisini başlat
systemctl start docker

# Docker servisini etkinleştir
systemctl enable docker

# Docker servis durumunu kontrol et
systemctl status docker

# Docker loglarını kontrol et
journalctl -u docker -n 50
```

### Sorun 2: Docker Socket Erişilemiyor

```bash
# Docker socket dosyasını kontrol et
ls -la /var/run/docker.sock

# Docker socket izinlerini kontrol et
stat /var/run/docker.sock

# Docker socket izinlerini düzelt (gerekirse)
chmod 666 /var/run/docker.sock

# Docker servisini yeniden başlat
systemctl restart docker
```

### Sorun 3: CloudPanel Docker'ı Görmüyor

```bash
# CloudPanel servisini yeniden başlat
systemctl restart cloudpanel

# CloudPanel loglarını kontrol et
tail -f /var/log/cloudpanel/cloudpanel.log

# CloudPanel Docker entegrasyonunu kontrol et
/usr/local/bin/clpctl system:docker:status
```

### Sorun 4: Docker Compose Komutu Bulunamıyor

```bash
# Docker Compose plugin'inin kurulu olduğunu kontrol et
docker compose version

# Eğer kurulu değilse, Docker Compose plugin'ini kur
apt update
apt install -y docker-compose-plugin

# Docker Compose versiyonunu kontrol et
docker compose version
```

---

## 📋 Kontrol Listesi

### Docker Kurulumu:
- [ ] Docker servisi çalışıyor (`systemctl status docker`)
- [ ] Docker daemon çalışıyor (`docker ps`)
- [ ] Docker Compose kurulu (`docker compose version`)
- [ ] Root kullanıcısı docker grubunda (`groups root`)

### CloudPanel Entegrasyonu:
- [ ] CloudPanel'e giriş yapıldı
- [ ] Settings → System → Docker bölümüne gidildi
- [ ] Docker Status **Enabled** olarak görünüyor
- [ ] Sites → Create Site → Docker Compose seçeneği görünüyor

---

## 🔄 CloudPanel'i Yeniden Başlatma

Eğer Docker aktifleştirildikten sonra CloudPanel'de görünmüyorsa:

```bash
# CloudPanel servisini yeniden başlat
systemctl restart cloudpanel

# CloudPanel durumunu kontrol et
systemctl status cloudpanel

# CloudPanel loglarını kontrol et
tail -f /var/log/cloudpanel/cloudpanel.log
```

---

## ✅ Alternatif: CloudPanel Versiyonunu Kontrol Etme

Bazı CloudPanel versiyonlarında Docker desteği farklı yerlerde olabilir:

### CloudPanel v2.x:
- **Settings → System → Docker**

### CloudPanel v1.x:
- **Settings → Docker** (doğrudan)

### CloudPanel Lite:
- Docker desteği sınırlı olabilir
- **Sites → Create Site** → **Docker Compose** seçeneği olmayabilir

---

## 🚀 CloudPanel Docker Versiyonunu Güncelleme

Eğer CloudPanel eski bir versiyondaysa, Docker desteği olmayabilir:

```bash
# CloudPanel versiyonunu kontrol et
/usr/local/bin/clpctl --version

# CloudPanel'i güncelle (dikkatli olun!)
# Bu işlem CloudPanel'i en son versiyona günceller
/usr/local/bin/clpctl system:update
```

**⚠️ Uyarı:** CloudPanel güncellemesi yapmadan önce yedek alın!

---

## 📝 CloudPanel Docker Kontrol Komutları

### SSH Üzerinden Kontrol:

```bash
# Docker servis durumu
systemctl status docker

# Docker daemon test
docker ps

# Docker Compose test
docker compose version

# CloudPanel Docker durumu (eğer CLI varsa)
/usr/local/bin/clpctl system:docker:status

# CloudPanel versiyonu
/usr/local/bin/clpctl --version
```

---

## ✅ Sonuç ve Öneri

### Adım 1: Docker Durumunu Kontrol Edin

```bash
systemctl status docker
docker ps
docker compose version
```

### Adım 2: CloudPanel'de Docker'ı Aktifleştirin

1. CloudPanel → Settings → System → Docker
2. Docker Status → **Enabled**
3. **Save**

### Adım 3: CloudPanel'i Yeniden Başlatın

```bash
systemctl restart cloudpanel
```

### Adım 4: Docker Compose Site Oluşturmayı Deneyin

1. CloudPanel → Sites → Create Site
2. **Docker Compose** seçeneğini kontrol edin

---

## 🆘 Hala Görünmüyorsa

Eğer Docker hala CloudPanel'de görünmüyorsa:

1. **CloudPanel versiyonunu kontrol edin** (Docker desteği olmayabilir)
2. **CloudPanel loglarını kontrol edin** (`/var/log/cloudpanel/cloudpanel.log`)
3. **CloudPanel'i güncelleyin** (dikkatli!)
4. **Alternatif:** Manuel Docker Compose kullanın (SSH üzerinden)

---

**Başarılar! 🚀**

