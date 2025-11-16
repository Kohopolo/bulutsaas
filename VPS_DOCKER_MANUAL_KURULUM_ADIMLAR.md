# VPS Docker Manuel Kurulum Adımları

## 🐳 Docker Manuel Kurulum (CloudPanel Otomatik Çalışmadıysa)

CloudPanel'de otomatik Docker kurulumu çalışmadıysa, VPS'te SSH ile bağlanıp manuel kurulum yapın.

---

## 📋 Adım 1: VPS'e SSH ile Bağlanın

### Yöntem 1: Hostinger Web Terminal (ÖNERİLEN)

1. **Hostinger Panel → VPS → Terminal**
2. **"Open Terminal"** butonuna tıklayın
3. **Web terminal açılır**

### Yöntem 2: Windows PowerShell/CMD

```bash
ssh root@72.62.35.155
```

---

## 📋 Adım 2: Docker Kurulum Scriptini İndirin ve Çalıştırın

### Script ile Kurulum (ÖNERİLEN):

```bash
# Script'i indir
wget -O VPS_DOCKER_MANUAL_KURULUM.sh https://raw.githubusercontent.com/Kohopolo/bulutsaas/main/VPS_DOCKER_MANUAL_KURULUM.sh

# Çalıştırılabilir yap
chmod +x VPS_DOCKER_MANUAL_KURULUM.sh

# Çalıştır
sudo ./VPS_DOCKER_MANUAL_KURULUM.sh
```

---

## 📋 Adım 3: Manuel Kurulum (Alternatif)

Eğer script çalışmazsa, komutları tek tek çalıştırın:

```bash
# Eski Docker versiyonlarını kaldır
sudo apt-get remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true

# Gerekli paketleri kur
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release

# Docker'ın resmi GPG key'ini ekle
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Docker repository'yi ekle
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Docker'ı kur
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Docker servisini başlat
sudo systemctl start docker
sudo systemctl enable docker
```

---

## 📋 Adım 4: Docker Kurulumunu Kontrol Edin

```bash
# Docker versiyonunu kontrol et
docker --version

# Docker Compose versiyonunu kontrol et
docker compose version

# Docker servisini kontrol et
sudo systemctl status docker

# Docker container'larını listele (boş olmalı)
docker ps -a
```

**Beklenen Çıktı:**
```
Docker version 24.x.x
Docker Compose version v2.x.x
docker.service: active (running)
```

---

## 📋 Adım 5: CloudPanel Kullanıcısını Docker Grubuna Ekleyin

```bash
# CloudPanel kullanıcısını docker grubuna ekle
sudo usermod -aG docker cloudpanel

# Veya root kullanıcısı için
sudo usermod -aG docker root

# Değişiklikleri uygula
newgrp docker
```

---

## 📋 Adım 6: Docker Test Edin

```bash
# Test container çalıştır
docker run hello-world

# Beklenen çıktı:
# Hello from Docker!
# This message shows that your installation appears to be working correctly.
```

---

## 🔧 Sorun Giderme

### Docker Kurulumu Başarısız:

```bash
# Hata mesajlarını kontrol et
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Eğer hata varsa, hata mesajını paylaşın
```

### Docker Servisi Başlamıyor:

```bash
# Docker servisini kontrol et
sudo systemctl status docker

# Docker servisini başlat
sudo systemctl start docker

# Docker servisini otomatik başlat
sudo systemctl enable docker
```

### Permission Denied:

```bash
# Kullanıcıyı docker grubuna ekle
sudo usermod -aG docker $USER

# Yeni oturum açın veya
newgrp docker
```

---

## ✅ Kurulum Sonrası

### CloudPanel'de Docker Durumunu Kontrol Edin:

1. **CloudPanel → Settings → System → Docker**
2. **Docker Status** görünmeli
3. **Docker Version** görünmeli

### Docker Compose Site Oluşturma:

1. **CloudPanel → Sites → Create Site**
2. **Site Type**: Docker Compose
3. **docker-compose.yml** yükleyin
4. **.env** yükleyin
5. **Create**

---

## 📝 Hızlı Kurulum Komutları

### Tek Komutla Kurulum:

```bash
# Script ile kurulum
wget -O VPS_DOCKER_MANUAL_KURULUM.sh https://raw.githubusercontent.com/Kohopolo/bulutsaas/main/VPS_DOCKER_MANUAL_KURULUM.sh && chmod +x VPS_DOCKER_MANUAL_KURULUM.sh && sudo ./VPS_DOCKER_MANUAL_KURULUM.sh
```

### Manuel Kurulum (Tek Tek):

```bash
# 1. Eski Docker'ı kaldır
sudo apt-get remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true

# 2. Gerekli paketleri kur
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release

# 3. GPG key ekle
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# 4. Repository ekle
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# 5. Docker kur
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# 6. Docker başlat
sudo systemctl start docker
sudo systemctl enable docker

# 7. Kontrol et
docker --version
docker compose version
```

---

## ✅ Özet

**Docker Kurulumu:**

1. ✅ VPS'e SSH ile bağlanın
2. ✅ Script'i indirip çalıştırın (veya manuel komutları çalıştırın)
3. ✅ Docker kurulumunu kontrol edin
4. ✅ CloudPanel kullanıcısını docker grubuna ekleyin
5. ✅ Docker test edin

**Kurulum Sonrası:**
- ✅ CloudPanel → Settings → System → Docker'dan kontrol edin
- ✅ Docker Compose site oluşturabilirsiniz

**Sonuç:** Docker kurulumu tamamlandıktan sonra CloudPanel'de Docker Compose site oluşturabilirsiniz!

