# CloudPanel Docker Kurulum Rehberi

## 🐳 CloudPanel'de Docker Kurulumu

CloudPanel'de Docker kurulumu için iki yöntem var:
1. **CloudPanel otomatik kurulum** (Önerilen)
2. **Manuel kurulum** (Alternatif)

---

## ✅ Yöntem 1: CloudPanel Otomatik Kurulum (ÖNERİLEN)

### Adım 1: Docker Kurulumunu Kontrol Et

CloudPanel genellikle Docker'ı otomatik kurar. Kontrol edin:

1. **CloudPanel → Settings → System**
2. **Docker** bölümünü kontrol edin
3. **Docker Status** görünmeli

### Adım 2: Docker Kurulumu (Eğer Yoksa)

CloudPanel'de Docker genellikle otomatik kurulur. Eğer yoksa:

1. **CloudPanel → Settings → System**
2. **Docker** sekmesine gidin
3. **Install Docker** butonuna tıklayın
4. Kurulum otomatik tamamlanır

---

## ✅ Yöntem 2: Manuel Kurulum (Alternatif)

### Adım 1: SSH ile VPS'e Bağlanın

```bash
ssh root@VPS_IP
```

### Adım 2: Docker Kurulumu

**Ubuntu/Debian için:**

```bash
# Eski Docker versiyonlarını kaldır
sudo apt-get remove docker docker-engine docker.io containerd runc

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

# Docker Compose kurulumu (eğer yoksa)
sudo apt-get install -y docker-compose-plugin
```

### Adım 3: Docker Kurulumunu Kontrol Et

```bash
# Docker versiyonunu kontrol et
docker --version

# Docker Compose versiyonunu kontrol et
docker compose version

# Docker servisini kontrol et
sudo systemctl status docker
```

---

## 🔧 CloudPanel'de Docker Compose Kullanımı

### Adım 1: Docker Compose Site Oluşturma

1. **CloudPanel → Sites → Create Site**
2. **Site Type**: Docker Compose seçin
3. **docker-compose.yml** dosyanızı yükleyin
4. **.env** dosyanızı yükleyin
5. **Create**

### Adım 2: Docker Container Yönetimi

**CloudPanel → Sites → Site Seç → Containers**

Buradan:
- ✅ Container'ları görüntüleyebilirsiniz
- ✅ Container loglarını görebilirsiniz
- ✅ Container'ları restart/stop/start edebilirsiniz
- ✅ Container ayarlarını düzenleyebilirsiniz

---

## 📋 Docker Kurulum Kontrolü

### VPS'te Kontrol:

```bash
# Docker versiyonunu kontrol et
docker --version

# Docker Compose versiyonunu kontrol et
docker compose version

# Docker servisini kontrol et
sudo systemctl status docker

# Docker container'larını listele
docker ps -a

# Docker image'larını listele
docker images
```

---

## 🔍 CloudPanel'de Docker Durumu

### CloudPanel → Settings → System → Docker

Buradan:
- ✅ Docker versiyonunu görebilirsiniz
- ✅ Docker servis durumunu kontrol edebilirsiniz
- ✅ Docker kurulumunu yapabilirsiniz

---

## ⚠️ Önemli Notlar

### Docker Compose Plugin:

CloudPanel'de **Docker Compose Plugin** kullanılır (V2):
```bash
docker compose up -d
```

Eski `docker-compose` komutu yerine:
```bash
docker compose up -d
```

### Docker Permissions:

CloudPanel kullanıcısının Docker'a erişimi olmalı:
```bash
# CloudPanel kullanıcısını docker grubuna ekle
sudo usermod -aG docker cloudpanel
```

---

## 🆘 Sorun Giderme

### Docker Kurulu Değil:

1. **CloudPanel → Settings → System → Docker**
2. **Install Docker** butonuna tıklayın
3. Veya manuel kurulum yapın (yukarıdaki komutlar)

### Docker Compose Çalışmıyor:

```bash
# Docker Compose plugin'i kontrol et
docker compose version

# Eğer yoksa kur
sudo apt-get install -y docker-compose-plugin
```

### Permission Denied:

```bash
# Kullanıcıyı docker grubuna ekle
sudo usermod -aG docker $USER

# Yeni oturum açın veya
newgrp docker
```

---

## ✅ CloudPanel'de Docker Compose Site Oluşturma

### Adım 1: Site Oluşturma

1. **CloudPanel → Sites → Create Site**
2. **Site Type**: Docker Compose
3. **Domain**: `bulutacente.com.tr`
4. **Docker Compose File**: `docker-compose.yml` yükleyin
5. **Environment File**: `.env` yükleyin
6. **Create**

### Adım 2: Container Yönetimi

**CloudPanel → Sites → Site Seç → Containers**

- ✅ Container'ları görüntüleyin
- ✅ Logları kontrol edin
- ✅ Container'ları yönetin

---

## 📝 Docker Kurulum Özeti

### CloudPanel Otomatik Kurulum:

1. **CloudPanel → Settings → System → Docker**
2. **Install Docker** (eğer yoksa)
3. Docker otomatik kurulur

### Manuel Kurulum:

```bash
# Ubuntu/Debian için
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl start docker
sudo systemctl enable docker
```

### Docker Compose Site:

1. **CloudPanel → Sites → Create Site**
2. **Docker Compose** seçin
3. **docker-compose.yml** ve **.env** yükleyin
4. **Create**

---

## ✅ Sonuç

**CloudPanel'de Docker:**
- ✅ Genellikle otomatik kurulur
- ✅ CloudPanel → Settings → System → Docker'dan kontrol edin
- ✅ Docker Compose Plugin kurulu olmalı

**Docker Compose Site:**
- ✅ CloudPanel → Sites → Create Site
- ✅ Docker Compose seçin
- ✅ docker-compose.yml ve .env yükleyin

**Sonuç:** CloudPanel'de Docker genellikle otomatik kurulur. Eğer yoksa Settings → System → Docker'dan kurun!

