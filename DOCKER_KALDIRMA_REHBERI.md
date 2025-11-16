# Docker ve Docker Compose Plugin Kaldırma Rehberi

## 🗑️ Docker'ı Tamamen Kaldırma

Docker'ı ve Docker Compose plugin'ini birlikte kaldırmak için aşağıdaki adımları izleyin.

---

## ⚠️ ÖNEMLİ UYARILAR

### Kaldırmadan Önce:

1. **Tüm container'ları durdurun ve silin**
2. **Önemli verileri yedekleyin** (volumes, images)
3. **CloudPanel'de Docker kullanıyorsanız dikkatli olun**

---

## ✅ Adım 1: Tüm Container'ları Durdurma ve Silme

```bash
# Çalışan tüm container'ları durdur
docker stop $(docker ps -aq)

# Tüm container'ları sil
docker rm $(docker ps -aq)

# Tüm image'ları sil (opsiyonel)
docker rmi $(docker images -q)

# Tüm volume'ları sil (opsiyonel - DİKKATLİ!)
docker volume rm $(docker volume ls -q)
```

---

## ✅ Adım 2: Docker Compose Plugin'ini Kaldırma

```bash
# Docker Compose plugin'ini kaldır
apt remove -y docker-compose-plugin

# Veya purge ile tamamen kaldır (yapılandırma dosyaları dahil)
apt purge -y docker-compose-plugin
```

---

## ✅ Adım 3: Docker'ı Kaldırma

### Ubuntu/Debian için:

```bash
# Docker paketlerini kaldır
apt remove -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin

# Veya purge ile tamamen kaldır (yapılandırma dosyaları dahil)
apt purge -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin

# Docker repository'yi kaldır (opsiyonel)
rm -f /etc/apt/sources.list.d/docker.list
rm -f /etc/apt/keyrings/docker.gpg
```

---

## ✅ Adım 4: Docker Verilerini Temizleme

```bash
# Docker veri dizinini sil
rm -rf /var/lib/docker
rm -rf /var/lib/containerd

# Docker socket dosyasını sil
rm -f /var/run/docker.sock

# Docker yapılandırma dosyalarını sil
rm -rf /etc/docker
```

---

## ✅ Adım 5: Docker Grubunu Temizleme

```bash
# Docker grubundaki kullanıcıları kontrol et
getent group docker

# Docker grubunu sil (opsiyonel)
groupdel docker
```

---

## ✅ Adım 6: Sistem Temizliği

```bash
# Kullanılmayan paketleri temizle
apt autoremove -y

# Paket önbelleğini temizle
apt autoclean

# Sistem paket listesini güncelle
apt update
```

---

## 🔄 Tek Komutla Kaldırma (Tüm Adımlar)

```bash
# Tüm container'ları durdur ve sil
docker stop $(docker ps -aq) 2>/dev/null || true
docker rm $(docker ps -aq) 2>/dev/null || true

# Docker Compose plugin'ini kaldır
apt remove -y docker-compose-plugin

# Docker paketlerini kaldır
apt remove -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin

# Docker verilerini temizle
rm -rf /var/lib/docker
rm -rf /var/lib/containerd
rm -f /var/run/docker.sock
rm -rf /etc/docker

# Sistem temizliği
apt autoremove -y
apt autoclean
```

---

## 🔍 Kaldırma Sonrası Kontrol

### Docker'ın Kaldırıldığını Kontrol:

```bash
# Docker komutunu kontrol et (hata vermeli)
docker --version
# Beklenen: command not found

# Docker Compose komutunu kontrol et (hata vermeli)
docker compose version
# Beklenen: command not found

# Docker servisini kontrol et (bulunmamalı)
systemctl status docker
# Beklenen: Unit docker.service could not be found

# Docker dosyalarının kaldırıldığını kontrol et
ls -la /var/lib/docker
# Beklenen: No such file or directory
```

---

## 🔄 Yeniden Kurulum (İsteğe Bağlı)

Eğer Docker'ı tekrar kurmak isterseniz:

```bash
# Docker kurulum scriptini çalıştır
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# Docker Compose plugin'ini kur
apt install -y docker-compose-plugin

# Docker servisini başlat
systemctl start docker
systemctl enable docker

# Docker durumunu kontrol et
docker --version
docker compose version
docker ps
```

---

## 📋 Kaldırma Kontrol Listesi

### Ön Hazırlık:
- [ ] Tüm container'lar durduruldu (`docker stop $(docker ps -aq)`)
- [ ] Tüm container'lar silindi (`docker rm $(docker ps -aq)`)
- [ ] Önemli veriler yedeklendi (volumes, images)

### Kaldırma:
- [ ] Docker Compose plugin kaldırıldı (`apt remove docker-compose-plugin`)
- [ ] Docker paketleri kaldırıldı (`apt remove docker-ce docker-ce-cli containerd.io`)
- [ ] Docker verileri temizlendi (`rm -rf /var/lib/docker`)
- [ ] Docker yapılandırması temizlendi (`rm -rf /etc/docker`)

### Kontrol:
- [ ] Docker komutu çalışmıyor (`docker --version` → command not found)
- [ ] Docker Compose komutu çalışmıyor (`docker compose version` → command not found)
- [ ] Docker servisi bulunamıyor (`systemctl status docker` → Unit not found)

---

## ⚠️ Önemli Notlar

### CloudPanel Kullanıyorsanız:

- ⚠️ CloudPanel Docker kullanıyorsa, Docker'ı kaldırmadan önce CloudPanel'deki Docker sitelerini kaldırın
- ⚠️ CloudPanel → Settings → System → Docker bölümünden Docker'ı devre dışı bırakın
- ⚠️ Docker kaldırıldıktan sonra CloudPanel'i yeniden başlatın

### Veri Kaybı:

- ⚠️ `/var/lib/docker` dizini silindiğinde tüm Docker verileri (volumes, images, containers) kalıcı olarak silinir
- ⚠️ Önemli verileri yedeklediğinizden emin olun

---

## ✅ Özet Komutlar

### Hızlı Kaldırma (Tüm Adımlar):

```bash
# Container'ları durdur ve sil
docker stop $(docker ps -aq) 2>/dev/null || true
docker rm $(docker ps -aq) 2>/dev/null || true

# Docker Compose plugin'ini kaldır
apt remove -y docker-compose-plugin

# Docker paketlerini kaldır
apt remove -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin

# Docker verilerini temizle
rm -rf /var/lib/docker /var/lib/containerd /var/run/docker.sock /etc/docker

# Sistem temizliği
apt autoremove -y && apt autoclean
```

### Kontrol:

```bash
docker --version
docker compose version
systemctl status docker
```

**Beklenen:** Tüm komutlar "command not found" veya "Unit not found" hatası vermeli.

---

## 🚀 Sonuç

Docker ve Docker Compose plugin'i başarıyla kaldırıldı!

**Tekrar kurmak için:**
```bash
curl -fsSL https://get.docker.com | sh
apt install -y docker-compose-plugin
```

**Başarılar! 🎉**

