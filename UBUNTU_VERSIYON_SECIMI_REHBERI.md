# Ubuntu Versiyonu Seçim Rehberi

## 📋 Genel Bakış

Bu rehber, Bulut Acente Yönetim Sistemi için hangi Ubuntu versiyonunun seçilmesi gerektiğini açıklar.

---

## 🎯 Önerilen Ubuntu Versiyonu

### ✅ Ubuntu 22.04 LTS (Jammy Jellyfish) - ÖNERİLEN

**Neden Ubuntu 22.04 LTS?**

1. ✅ **LTS (Long Term Support)**
   - 5 yıl destek (2022-2027)
   - Güvenlik güncellemeleri
   - Stabil ve güvenilir

2. ✅ **Python 3.10** (varsayılan)
   - Projeniz Python 3.11+ gerektiriyor ama 3.10 ile de uyumlu
   - Python 3.11 kolayca kurulabilir

3. ✅ **PostgreSQL 14+** desteği
   - PostgreSQL 15 kolayca kurulabilir
   - django-tenants 3.6.1 ile uyumlu

4. ✅ **Güncel paketler**
   - Nginx, Redis, Gunicorn güncel versiyonlar
   - Sistem bağımlılıkları güncel

5. ✅ **Tüm hosting sağlayıcılarında mevcut**
   - Digital Ocean ✅
   - Hetzner ✅
   - Google Cloud Platform ✅
   - Hostinger ✅

---

## 📊 Ubuntu Versiyonları Karşılaştırması

### Ubuntu 20.04 LTS (Focal Fossa)

| Özellik | Durum | Notlar |
|---------|-------|--------|
| **Destek Süresi** | 2020-2025 (sona eriyor) | ⚠️ 2025'te destek sona eriyor |
| **Python** | 3.8 (varsayılan) | ⚠️ Eski versiyon |
| **PostgreSQL** | 12 (varsayılan) | ⚠️ Eski versiyon |
| **Paketler** | Eski | ⚠️ Güncellemeler sınırlı |

**Öneri**: ❌ **ÖNERİLMEZ** (destek sona eriyor)

### Ubuntu 22.04 LTS (Jammy Jellyfish) ✅ ÖNERİLEN

| Özellik | Durum | Notlar |
|---------|-------|--------|
| **Destek Süresi** | 2022-2027 | ✅ 5 yıl destek |
| **Python** | 3.10 (varsayılan) | ✅ İyi, 3.11 kurulabilir |
| **PostgreSQL** | 14 (varsayılan) | ✅ İyi, 15 kurulabilir |
| **Paketler** | Güncel | ✅ Tüm paketler güncel |

**Öneri**: ✅ **ÖNERİLEN**

### Ubuntu 24.04 LTS (Noble Numbat)

| Özellik | Durum | Notlar |
|---------|-------|--------|
| **Destek Süresi** | 2024-2029 | ✅ 5 yıl destek |
| **Python** | 3.12 (varsayılan) | ✅ En güncel |
| **PostgreSQL** | 16 (varsayılan) | ✅ En güncel |
| **Paketler** | Çok Güncel | ✅ Tüm paketler çok güncel |

**Öneri**: ⚠️ **KABUL EDİLEBİLİR** (ama 22.04 daha stabil)

---

## 🐍 Python Versiyonu Gereksinimleri

### Projenizin Python Gereksinimi

```python
# requirements.txt kontrolü
# Python 3.11+ önerilir
# Python 3.10+ minimum
```

### Ubuntu 22.04'te Python Kurulumu

```bash
# Ubuntu 22.04 varsayılan Python 3.10
python3 --version
# Python 3.10.12

# Python 3.11 kurulumu
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:deadsnakes/ppa
sudo apt update
sudo apt install -y python3.11 python3.11-venv python3.11-dev

# Python 3.11'i varsayılan yap
sudo update-alternatives --install /usr/bin/python3 python3 /usr/bin/python3.11 1
```

### Ubuntu 24.04'te Python Kurulumu

```bash
# Ubuntu 24.04 varsayılan Python 3.12
python3 --version
# Python 3.12.x

# Python 3.11 kurulumu (gerekirse)
sudo apt install -y python3.11 python3.11-venv python3.11-dev
```

---

## 🗄️ PostgreSQL Versiyonu Gereksinimleri

### Projenizin PostgreSQL Gereksinimi

- **Minimum**: PostgreSQL 14+
- **Önerilen**: PostgreSQL 15
- **django-tenants**: PostgreSQL 14+ gerektirir

### Ubuntu 22.04'te PostgreSQL Kurulumu

```bash
# PostgreSQL 14 varsayılan (yeterli)
sudo apt install -y postgresql-14 postgresql-contrib-14

# PostgreSQL 15 kurulumu (önerilen)
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update
sudo apt install -y postgresql-15 postgresql-contrib-15
```

### Ubuntu 24.04'te PostgreSQL Kurulumu

```bash
# PostgreSQL 16 varsayılan (çok güncel)
sudo apt install -y postgresql-16 postgresql-contrib-16

# PostgreSQL 15 kurulumu (önerilen)
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update
sudo apt install -y postgresql-15 postgresql-contrib-15
```

---

## 🎯 Hosting Sağlayıcılarına Göre Ubuntu Seçimi

### Digital Ocean

- ✅ **Ubuntu 22.04 LTS** - Önerilen
- ✅ **Ubuntu 24.04 LTS** - Kabul edilebilir
- ⚠️ **Ubuntu 20.04 LTS** - Önerilmez (destek sona eriyor)

### Hetzner

- ✅ **Ubuntu 22.04 LTS** - Önerilen
- ✅ **Ubuntu 24.04 LTS** - Kabul edilebilir
- ⚠️ **Ubuntu 20.04 LTS** - Önerilmez

### Google Cloud Platform

- ✅ **Ubuntu 22.04 LTS** - Önerilen
- ✅ **Ubuntu 24.04 LTS** - Kabul edilebilir
- ⚠️ **Ubuntu 20.04 LTS** - Önerilmez

### Hostinger

- ✅ **Ubuntu 22.04 LTS** - Önerilen
- ✅ **Ubuntu 24.04 LTS** - Kabul edilebilir
- ⚠️ **Ubuntu 20.04 LTS** - Önerilmez

---

## ✅ Final Öneri

### Küçük-Orta Ölçek Projeler İçin

**Ubuntu 22.04 LTS** ✅ **ÖNERİLEN**

**Nedenler:**
1. ✅ **Stabil ve güvenilir** (LTS)
2. ✅ **5 yıl destek** (2022-2027)
3. ✅ **Tüm paketler mevcut** (Python 3.11, PostgreSQL 15 kurulabilir)
4. ✅ **Tüm hosting sağlayıcılarında mevcut**
5. ✅ **Dokümantasyon bol** (en çok kullanılan)
6. ✅ **Test edilmiş** (production'da yaygın)

### Büyük Ölçek/Enterprise Projeler İçin

**Ubuntu 22.04 LTS** ✅ **ÖNERİLEN**

**Alternatif**: **Ubuntu 24.04 LTS** (daha yeni ama daha az test edilmiş)

---

## 🔧 Ubuntu 22.04 Kurulum Sonrası Yapılacaklar

### 1. Sistem Güncellemesi

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Python 3.11 Kurulumu

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:deadsnakes/ppa
sudo apt update
sudo apt install -y python3.11 python3.11-venv python3.11-dev python3-pip
sudo update-alternatives --install /usr/bin/python3 python3 /usr/bin/python3.11 1
```

### 3. PostgreSQL 15 Kurulumu

```bash
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update
sudo apt install -y postgresql-15 postgresql-contrib-15
```

### 4. Temel Araçlar

```bash
sudo apt install -y curl wget git build-essential
sudo apt install -y libjpeg-dev zlib1g-dev libpng-dev libfreetype6-dev
sudo apt install -y libpq-dev
```

---

## ⚠️ Ubuntu 24.04 Kullanırsanız

### Dikkat Edilmesi Gerekenler

1. **Python 3.12** varsayılan
   - Projeniz Python 3.11 gerektiriyorsa kurulum gerekir
   - Python 3.12 ile uyumluluk test edilmeli

2. **PostgreSQL 16** varsayılan
   - Projeniz PostgreSQL 15 gerektiriyorsa kurulum gerekir
   - PostgreSQL 16 ile uyumluluk test edilmeli

3. **Paket versiyonları**
   - Bazı paketler daha yeni versiyonlarda olabilir
   - Uyumluluk test edilmeli

4. **Dokümantasyon**
   - Ubuntu 22.04 için daha fazla dokümantasyon mevcut
   - Ubuntu 24.04 için dokümantasyon daha az

---

## 📊 Özet Tablo

| Ubuntu Versiyonu | Destek Süresi | Python | PostgreSQL | Öneri |
|------------------|---------------|--------|------------|--------|
| **20.04 LTS** | 2020-2025 (sona eriyor) | 3.8 | 12 | ❌ Önerilmez |
| **22.04 LTS** | 2022-2027 | 3.10 (3.11 kurulabilir) | 14 (15 kurulabilir) | ✅ **ÖNERİLEN** |
| **24.04 LTS** | 2024-2029 | 3.12 | 16 | ⚠️ Kabul edilebilir |

---

## ✅ Sonuç

### Kesin Öneri: **Ubuntu 22.04 LTS** ✅

**Nedenler:**
1. ✅ **Stabil ve güvenilir** (LTS)
2. ✅ **5 yıl destek** (2022-2027)
3. ✅ **Tüm paketler mevcut** ve kolay kurulabilir
4. ✅ **Tüm hosting sağlayıcılarında mevcut**
5. ✅ **En çok kullanılan** (production'da yaygın)
6. ✅ **Dokümantasyon bol**

**Alternatif**: Ubuntu 24.04 LTS (daha yeni ama daha az test edilmiş)

---

**Son Güncelleme**: 2025-01-16

