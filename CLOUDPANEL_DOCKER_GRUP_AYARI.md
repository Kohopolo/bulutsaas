# CloudPanel Docker Grup Ayarı

## 🔧 CloudPanel Kullanıcısı Bulunamadı

`cloudpanel` kullanıcısı yoksa, CloudPanel'in hangi kullanıcıyı kullandığını kontrol edin.

---

## ✅ Çözüm 1: Root Kullanıcısını Docker Grubuna Ekleyin

```bash
# Root kullanıcısını docker grubuna ekle
sudo usermod -aG docker root

# Değişiklikleri uygula
newgrp docker

# Kontrol et
groups
```

**Beklenen Çıktı:**
```
root docker
```

---

## ✅ Çözüm 2: CloudPanel Kullanıcısını Bulun

### CloudPanel'in Hangi Kullanıcıyı Kullandığını Kontrol Edin:

```bash
# Tüm kullanıcıları listele
cat /etc/passwd | grep -E "cloud|panel|admin"

# CloudPanel process'lerini kontrol et
ps aux | grep cloudpanel

# CloudPanel'in çalıştığı kullanıcıyı bul
ps aux | grep -i cloudpanel | head -5
```

### Olası Kullanıcılar:

- `root` (en yaygın)
- `admin`
- `cloudpanel`
- `www-data`
- `nginx`

---

## ✅ Çözüm 3: Tüm Olası Kullanıcıları Docker Grubuna Ekleyin

```bash
# Root kullanıcısını ekle
sudo usermod -aG docker root

# www-data kullanıcısını ekle (Nginx için)
sudo usermod -aG docker www-data

# Nginx kullanıcısını ekle (eğer varsa)
sudo usermod -aG docker nginx 2>/dev/null || true

# Değişiklikleri uygula
newgrp docker
```

---

## 🔍 CloudPanel Kullanıcısını Bulma

### CloudPanel Process'lerini Kontrol Edin:

```bash
# CloudPanel process'lerini bul
ps aux | grep -i cloudpanel

# CloudPanel'in çalıştığı kullanıcıyı göster
ps aux | grep -i cloudpanel | awk '{print $1}' | sort -u
```

### CloudPanel Dosyalarını Kontrol Edin:

```bash
# CloudPanel kurulum dizinini bul
find / -name "*cloudpanel*" -type d 2>/dev/null | head -5

# CloudPanel config dosyasını bul
find / -name "*cloudpanel*.conf" 2>/dev/null | head -5
```

---

## ✅ Önerilen Çözüm

### Root Kullanıcısını Docker Grubuna Ekleyin:

```bash
# Root kullanıcısını docker grubuna ekle
sudo usermod -aG docker root

# Değişiklikleri uygula
newgrp docker

# Kontrol et
groups

# Docker test et
docker ps
```

**Beklenen Çıktı:**
```
root docker
CONTAINER ID   IMAGE     COMMAND   CREATED   STATUS    PORTS     NAMES
```

---

## 🔧 CloudPanel'de Docker Kullanımı

### CloudPanel'in Docker'ı Kullanabilmesi İçin:

1. **Root kullanıcısını docker grubuna ekleyin** ✅
2. **CloudPanel → Settings → System → Docker** kontrol edin
3. **Docker Compose site oluşturun**

---

## 📋 Kontrol Komutları

### Docker Grubunu Kontrol Edin:

```bash
# Docker grubundaki kullanıcıları listele
getent group docker

# Mevcut kullanıcının gruplarını kontrol et
groups

# Root'un gruplarını kontrol et
groups root
```

### Docker Test:

```bash
# Docker komutunu test et
docker ps

# Docker Compose komutunu test et
docker compose version

# Test container çalıştır
docker run hello-world
```

---

## ⚠️ Önemli Notlar

### Root Kullanıcısı:

- ✅ Root kullanıcısı docker grubuna eklenirse, CloudPanel Docker'ı kullanabilir
- ✅ CloudPanel genellikle root kullanıcısı ile çalışır
- ✅ Root kullanıcısı zaten tüm yetkilere sahiptir

### Güvenlik:

- ⚠️ Root kullanıcısını docker grubuna eklemek güvenlik riski oluşturabilir
- ⚠️ Production'da özel kullanıcı oluşturmanız önerilir
- ✅ CloudPanel için root yeterli

---

## ✅ Sonuç ve Öneri

### Yapılacaklar:

```bash
# Root kullanıcısını docker grubuna ekle
sudo usermod -aG docker root

# Değişiklikleri uygula
newgrp docker

# Kontrol et
docker ps
```

### CloudPanel'de Kontrol:

1. **CloudPanel → Settings → System → Docker**
2. **Docker Status** görünmeli
3. **Docker Compose site oluşturabilirsiniz**

---

## 📝 Özet

**Sorun:** `cloudpanel` kullanıcısı yok

**Çözüm:**
```bash
sudo usermod -aG docker root
newgrp docker
docker ps
```

**Sonuç:** Root kullanıcısı docker grubuna eklendi, CloudPanel Docker'ı kullanabilir!

