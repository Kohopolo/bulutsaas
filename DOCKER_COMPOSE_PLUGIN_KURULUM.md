# Docker Compose Plugin Kurulum Rehberi

## ⚠️ Docker Zaten Kurulu!

Docker zaten kurulu olduğu için kurulum scriptini iptal edip sadece Docker Compose plugin'ini kurmanız yeterli.

---

## ✅ Yöntem 1: Docker Compose Plugin Kurulumu (ÖNERİLEN)

### Adım 1: Kurulum Scriptini İptal Edin

Eğer Docker kurulum scripti hala çalışıyorsa:
- **Ctrl+C** tuşlarına basarak scripti iptal edin

### Adım 2: Sadece Docker Compose Plugin'ini Kurun

```bash
# Docker Compose plugin'ini kur
apt update
apt install -y docker-compose-plugin

# Docker Compose versiyonunu kontrol et
docker compose version
```

**Beklenen Çıktı:**
```
Docker Compose version v2.x.x
```

---

## ✅ Yöntem 2: Docker Kurulum Scriptini Devam Ettirme (Alternatif)

Eğer Docker'ı yeniden kurmak istiyorsanız:

1. **20 saniye bekleyin** (script otomatik devam eder)
2. Veya **Enter** tuşuna basarak devam edin

**⚠️ Uyarı:** Bu işlem mevcut Docker kurulumunu sıfırlayabilir!

---

## 🔍 Docker Durumunu Kontrol Etme

### Docker Kurulumunu Kontrol:

```bash
# Docker versiyonunu kontrol et
docker --version

# Docker Compose versiyonunu kontrol et
docker compose version

# Docker servis durumunu kontrol et
systemctl status docker

# Docker daemon test
docker ps
```

---

## 🔧 Docker Compose Plugin Kurulumu (Detaylı)

### Ubuntu/Debian için:

```bash
# Paket listesini güncelle
apt update

# Docker Compose plugin'ini kur
apt install -y docker-compose-plugin

# Docker Compose versiyonunu kontrol et
docker compose version

# Test: Docker Compose komutunu çalıştır
docker compose --help
```

---

## 🐛 Sorun Giderme

### Sorun 1: Docker Compose Komutu Bulunamıyor

```bash
# Docker Compose plugin'inin kurulu olduğunu kontrol et
apt list --installed | grep docker-compose

# Eğer yoksa kur
apt install -y docker-compose-plugin

# Docker Compose versiyonunu kontrol et
docker compose version
```

### Sorun 2: Permission Denied

```bash
# Root kullanıcısını docker grubuna ekle (zaten yapıldı)
usermod -aG docker root

# Yeni grup ayarlarını uygula
newgrp docker

# Docker komutunu test et
docker ps
```

### Sorun 3: Docker Servisi Çalışmıyor

```bash
# Docker servisini başlat
systemctl start docker

# Docker servisini etkinleştir
systemctl enable docker

# Docker servis durumunu kontrol et
systemctl status docker
```

---

## 📋 Kontrol Listesi

### Docker Kurulumu:
- [ ] Docker kurulu (`docker --version`)
- [ ] Docker servisi çalışıyor (`systemctl status docker`)
- [ ] Docker daemon çalışıyor (`docker ps`)

### Docker Compose Plugin:
- [ ] Docker Compose plugin kurulu (`docker compose version`)
- [ ] Docker Compose komutu çalışıyor (`docker compose --help`)

---

## ✅ Önerilen Adımlar

### 1. Kurulum Scriptini İptal Edin

Eğer Docker kurulum scripti hala çalışıyorsa:
- **Ctrl+C** tuşlarına basın

### 2. Docker Compose Plugin'ini Kurun

```bash
apt update
apt install -y docker-compose-plugin
```

### 3. Docker Durumunu Kontrol Edin

```bash
docker --version
docker compose version
docker ps
```

### 4. CloudPanel'de Docker'ı Kontrol Edin

1. CloudPanel → Settings → System → Docker
2. Docker Status kontrol edin
3. Docker Compose site oluşturmayı deneyin

---

## 🚀 Sonuç

**Docker zaten kurulu!** Sadece Docker Compose plugin'ini kurmanız yeterli:

```bash
apt update
apt install -y docker-compose-plugin
docker compose version
```

**Başarılar! 🎉**

