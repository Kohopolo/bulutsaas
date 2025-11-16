# Web Container Ayarları Kontrol

## ⚠️ Eksik Ayarlar Var!

Görüntüdeki ayarlar `docker-compose.yml` dosyasıyla karşılaştırıldığında bazı eksiklikler var.

---

## 📋 Ayar Karşılaştırması

### ✅ Container Adı
- **Görüntü**: `web`
- **docker-compose.yml**: `saas2026_web` (container_name)
- **Durum**: ✅ Doğru (Hostinger panelinde kısa isim gösteriliyor)

### ✅ Port Mapping
- **Görüntü**: `127.0.0.1:8000:8000`
- **docker-compose.yml**: `127.0.0.1:8000:8000`
- **Durum**: ✅ Tamamen doğru

### ⚠️ Volume Mount'lar (Eksik!)

**Görüntüdeki volume'lar:**
1. `.:/app` ✅
2. `static_volume:/app/staticfiles` ✅
3. `media_volume:/app/media` ✅

**docker-compose.yml'deki volume'lar:**
1. `.:/app` ✅
2. `static_volume:/app/staticfiles` ✅
3. `media_volume:/app/media` ✅
4. `./logs:/app/logs` ❌ **EKSİK!**

**Durum**: ⚠️ `./logs:/app/logs` volume'u eksik!

### ⚠️ Container Dependency (Eksik!)

**Görüntü**: Boş ❌

**docker-compose.yml**: 
- `db` (condition: service_healthy)
- `redis` (condition: service_healthy)

**Durum**: ⚠️ Container dependency'ler eksik!

### ✅ Restart Policy
- **Görüntü**: `unless-stopped`
- **docker-compose.yml**: `unless-stopped`
- **Durum**: ✅ Doğru

### ℹ️ Image Alanı
- **Görüntü**: Boş
- **docker-compose.yml**: `build` kullanılıyor (Dockerfile)
- **Durum**: ✅ Normal (build kullanıldığında image belirtmeye gerek yok)

---

## 🔧 Düzeltilmesi Gerekenler

### 1. Volume Ekle: `./logs:/app/logs`

**Yapılacaklar:**
1. "+ Volume ekle" butonuna tıklayın
2. Şu değeri girin: `./logs:/app/logs`
3. Kaydedin

### 2. Container Dependency Ekle: `db` ve `redis`

**Yapılacaklar:**
1. "+ Bağımlılık ekle" butonuna tıklayın
2. `db` yazın ve ekleyin
3. Tekrar "+ Bağımlılık ekle" butonuna tıklayın
4. `redis` yazın ve ekleyin
5. Kaydedin

---

## ✅ Doğru Ayarlar

### Volume'lar (4 adet):
1. `.:/app`
2. `static_volume:/app/staticfiles`
3. `media_volume:/app/media`
4. `./logs:/app/logs` ← **EKLE!**

### Container Dependency'ler (2 adet):
1. `db` ← **EKLE!**
2. `redis` ← **EKLE!**

### Port:
- `127.0.0.1:8000:8000`

### Restart Policy:
- `unless-stopped`

---

## 📝 Adım Adım Düzeltme

### Adım 1: Logs Volume'u Ekle

1. "+ Volume ekle" butonuna tıklayın
2. Yeni volume alanına şunu yazın: `./logs:/app/logs`
3. Kaydedin

### Adım 2: Container Dependency'leri Ekle

1. "+ Bağımlılık ekle" butonuna tıklayın
2. `db` yazın
3. Tekrar "+ Bağımlılık ekle" butonuna tıklayın
4. `redis` yazın
5. Kaydedin

### Adım 3: Container'ı Yeniden Başlat

```bash
docker compose restart web
```

---

## ⚠️ Önemli Notlar

1. **Logs volume'u eksik**: Log dosyaları kaydedilemeyebilir
2. **Container dependency'ler eksik**: Web container'ı db ve redis'ten önce başlayabilir, bu hatalara neden olabilir
3. **Düzeltme gerekli**: Yukarıdaki adımları takip ederek eksik ayarları ekleyin

---

## ✅ Özet

**Eksikler:**
- ❌ `./logs:/app/logs` volume'u
- ❌ `db` container dependency
- ❌ `redis` container dependency

**Doğru Olanlar:**
- ✅ Port mapping (`127.0.0.1:8000:8000`)
- ✅ Diğer volume'lar (3 adet)
- ✅ Restart policy (`unless-stopped`)

**Sonuç**: Eksik ayarları ekleyip kaydedin!

