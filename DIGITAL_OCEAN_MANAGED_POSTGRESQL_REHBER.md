# Digital Ocean Managed PostgreSQL Cluster Yapılandırma Rehberi

## 📋 Genel Bakış

Bu rehber, Bulut Acente Yönetim Sistemi için Digital Ocean Managed PostgreSQL cluster'ının nasıl oluşturulacağını ve yapılandırılacağını açıklar.

---

## 🎯 Create Database Cluster Özellikleri

### 1. **Database Engine**
- **Seçim**: `PostgreSQL`
- **Versiyon**: `PostgreSQL 15` ✅ **ÖNERİLEN**
  - Projenizde Docker Compose'da `postgres:15-alpine` kullanılıyor
  - Django 4.2.11 ve django-tenants 3.6.1 ile tam uyumlu
  - PostgreSQL 14+ gereksinimi karşılanır
  - PostgreSQL 16 da kullanılabilir ama 15 daha stabil ve test edilmiş

### 2. **Datacenter Region**
- **Önerilen**: Size en yakın lokasyon
  - **Avrupa**: Amsterdam, Frankfurt, London
  - **ABD**: New York, San Francisco
  - **Asya**: Singapore, Bangalore
- **Önemli**: Droplet ile aynı region'da olması önerilir (düşük latency için)

### 3. **Database Plan**

#### **Küçük Ölçekli Projeler (Başlangıç)**
- **Plan**: `Basic` (Standalone)
- **Node Size**: 
  - **Development/Test**: `db-s-1vcpu-1gb` ($15/ay) ⚠️ **ÇOK DÜŞÜK - ÖNERİLMEZ**
  - **Production (Küçük)**: `db-s-1vcpu-2gb` ($25/ay) ⚠️ **YETERSİZ - ÖNERİLMEZ**
- **Storage**: `10GB` (başlangıç için yeterli)
- **Not**: Standalone node, yüksek erişilebilirlik gerektirmeyen projeler için uygundur
- **⚠️ UYARI**: 1 GB RAM ve 22 connection limit multi-tenant SaaS için **YETERSİZDİR**

#### **Orta Ölçekli Projeler (Önerilen)**
- **Plan**: `Basic` (Standalone) veya `Production` (High Availability)
- **Node Size**: 
  - **Production**: `db-s-2vcpu-4gb` ($60/ay) - **ÖNERİLEN**
  - **Production (HA)**: `db-s-2vcpu-4gb` (2 nodes) ($120/ay)
- **Storage**: `25GB` - `50GB` (başlangıç için 25GB yeterli, otomatik genişletme açık)
- **Not**: Multi-tenant SaaS için minimum 4GB RAM önerilir

#### **Büyük Ölçekli Projeler**
- **Plan**: `Production` (High Availability)
- **Node Size**: 
  - `db-s-4vcpu-8gb` (2 nodes) ($240/ay)
  - `db-s-8vcpu-16gb` (2 nodes) ($480/ay)
- **Storage**: `100GB+` (otomatik genişletme açık)

### 4. **Database Configuration**

#### **Database Name**
- **Varsayılan**: `defaultdb` (oluşturulduktan sonra değiştirilebilir)
- **Önerilen**: `bulut_acente_db` veya `saas_db`

#### **Database User**
- **Username**: `doadmin` (varsayılan) veya özel kullanıcı adı
- **Password**: Güçlü bir şifre oluşturun (min. 16 karakter, büyük/küçük harf, rakam, özel karakter)

#### **Connection Pooling**
- **Mode**: `Transaction` (önerilen) veya `Session`
- **Pool Size**: `25` (varsayılan) - Django için yeterli
- **Not**: django-tenants için transaction pooling önerilir

### 5. **Advanced Options**

#### **Maintenance Window**
- **Day**: Hafta sonu (Cumartesi veya Pazar)
- **Time**: Gece saatleri (02:00 - 04:00 UTC)
- **Not**: Kullanıcı trafiğinin en düşük olduğu saatleri seçin

#### **Backup Retention**
- **Standart**: `7 days` (varsayılan)
- **Production**: `30 days` (önerilen)
- **Not**: Kritik veriler için 30 gün önerilir

#### **Automated Backups**
- **Enable**: ✅ Açık (zorunlu)
- **Backup Window**: Gece saatleri (02:00 - 04:00 UTC)

#### **Standby Nodes** (Sadece Production Plan)
- **Count**: `1` (High Availability için)
- **Not**: Veri kaybını önlemek için önerilir

---

## 🔧 Django Settings Yapılandırması

### `.env` Dosyası Ayarları

```bash
# Database Configuration
POSTGRES_HOST=your-cluster-host.db.ondigitalocean.com
POSTGRES_PORT=25060
POSTGRES_DB=defaultdb
POSTGRES_USER=doadmin
POSTGRES_PASSWORD=your-strong-password-here

# SSL Connection (Zorunlu)
POSTGRES_SSL_MODE=require
```

### `settings.py` Güncellemesi

```python
DATABASES = {
    'default': {
        'ENGINE': 'django_tenants.postgresql_backend',
        'NAME': env('POSTGRES_DB', default='defaultdb'),
        'USER': env('POSTGRES_USER', default='doadmin'),
        'PASSWORD': env('POSTGRES_PASSWORD'),
        'HOST': env('POSTGRES_HOST'),
        'PORT': env('POSTGRES_PORT', default='25060'),
        'OPTIONS': {
            'sslmode': env('POSTGRES_SSL_MODE', default='require'),
            # Connection pooling için
            'connect_timeout': 10,
        },
        'CONN_MAX_AGE': 600,  # 10 dakika connection pooling
    }
}
```

---

## 🔐 Güvenlik Ayarları

### 1. **Trusted Sources (Firewall Rules)**
- **Droplet IP**: Droplet'inizin IP adresini ekleyin
- **Local Development**: Kendi IP'nizi ekleyin (opsiyonel)
- **Not**: Sadece gerekli IP'leri ekleyin, güvenlik için önemli

### 2. **SSL/TLS**
- **Mode**: `require` (varsayılan)
- **Not**: Tüm bağlantılar SSL ile şifrelenir

### 3. **Database Access Control**
- **Public Access**: ❌ Kapalı (önerilen)
- **VPC**: Droplet ile aynı VPC'de ise VPC erişimi kullanın

---

## 📊 Önerilen Konfigürasyon Özeti

### **Küçük Ölçekli (Başlangıç)**
```
Engine: PostgreSQL 15
Plan: Basic (Standalone)
Node Size: db-s-1vcpu-2gb
Storage: 10GB
Backup Retention: 7 days
Connection Pooling: Transaction (25)
Trusted Sources: Droplet IP only
```

### **Orta Ölçekli (Production - ÖNERİLEN)**
```
Engine: PostgreSQL 15
Plan: Basic (Standalone) veya Production (HA)
Node Size: db-s-2vcpu-4gb
Storage: 25GB (Auto-scaling açık)
Backup Retention: 30 days
Connection Pooling: Transaction (25)
Trusted Sources: Droplet IP only
Maintenance Window: Weekend 02:00-04:00 UTC
```

### **Büyük Ölçekli (Enterprise)**
```
Engine: PostgreSQL 15
Plan: Production (High Availability)
Node Size: db-s-4vcpu-8gb (2 nodes)
Storage: 100GB+ (Auto-scaling açık)
Backup Retention: 30 days
Connection Pooling: Transaction (50)
Standby Nodes: 1
Trusted Sources: Droplet IP only
Maintenance Window: Weekend 02:00-04:00 UTC
```

---

## 🚀 Cluster Oluşturma Adımları

### 1. Digital Ocean Dashboard'a Giriş
1. Digital Ocean hesabınıza giriş yapın
2. **Databases** > **Create Database Cluster** seçin

### 2. Temel Ayarlar
1. **Database Engine**: `PostgreSQL` seçin
2. **Version**: `PostgreSQL 15` seçin
3. **Datacenter Region**: Droplet'inizle aynı region'ı seçin

### 3. Plan Seçimi
1. **Plan Type**: `Basic` veya `Production` seçin
2. **Node Size**: `db-s-2vcpu-4gb` seçin (önerilen)
3. **Storage**: `25GB` seçin (otomatik genişletme açık)

### 4. Database Configuration
1. **Database Name**: `bulut_acente_db` veya `saas_db`
2. **Database User**: `doadmin` (varsayılan) veya özel kullanıcı adı
3. **Password**: Güçlü bir şifre oluşturun ve kaydedin

### 5. Advanced Options
1. **Maintenance Window**: Hafta sonu gece saatleri seçin
2. **Backup Retention**: `30 days` seçin
3. **Automated Backups**: ✅ Açık bırakın

### 6. Trusted Sources
1. **Add Trusted Source**: Droplet'inizin IP adresini ekleyin
2. **Description**: `Production Droplet` yazın

### 7. Create Cluster
1. **Create Database Cluster** butonuna tıklayın
2. Cluster'ın oluşturulmasını bekleyin (5-10 dakika)

---

## 🔗 Bağlantı Bilgilerini Alma

### 1. Connection String
1. Cluster oluşturulduktan sonra **Overview** sayfasına gidin
2. **Connection Details** bölümünden bağlantı bilgilerini alın:
   - **Host**: `your-cluster-host.db.ondigitalocean.com`
   - **Port**: `25060` (varsayılan)
   - **Database**: `defaultdb` veya oluşturduğunuz database adı
   - **User**: `doadmin` veya oluşturduğunuz kullanıcı adı
   - **Password**: Oluşturduğunuz şifre

### 2. Connection String Format
```
postgresql://doadmin:password@host:25060/defaultdb?sslmode=require
```

---

## ✅ Test Bağlantısı

### Python ile Test
```python
import psycopg2

try:
    conn = psycopg2.connect(
        host="your-cluster-host.db.ondigitalocean.com",
        port=25060,
        database="defaultdb",
        user="doadmin",
        password="your-password",
        sslmode="require"
    )
    print("✅ Bağlantı başarılı!")
    conn.close()
except Exception as e:
    print(f"❌ Bağlantı hatası: {e}")
```

### Django ile Test
```bash
python manage.py dbshell
```

---

## 📈 Monitoring ve Optimizasyon

### 1. **Performance Insights**
- Digital Ocean Dashboard'da **Insights** sekmesini kullanın
- CPU, Memory, Disk kullanımını izleyin
- Yavaş sorguları tespit edin

### 2. **Connection Pooling**
- PgBouncer kullanarak connection pooling yapın
- Django `CONN_MAX_AGE` ayarını optimize edin

### 3. **Index Optimization**
- Sık kullanılan sorgular için index'ler oluşturun
- `EXPLAIN ANALYZE` ile sorgu performansını kontrol edin

---

## 🔄 Yedekleme ve Geri Yükleme

### 1. **Otomatik Yedekleme**
- Digital Ocean otomatik yedekleme yapar
- Yedekler 7-30 gün saklanır (ayarınıza göre)

### 2. **Manuel Yedekleme**
```bash
# Digital Ocean Dashboard'dan
Databases > Your Cluster > Backups > Create Backup
```

### 3. **Geri Yükleme**
```bash
# Digital Ocean Dashboard'dan
Databases > Your Cluster > Backups > Restore
```

---

## 💰 Maliyet Tahmini

### Küçük Ölçekli (Başlangıç)
- **Monthly Cost**: ~$25-30/ay
- **Storage**: 10GB dahil
- **Backup**: Dahil

### Orta Ölçekli (Production)
- **Monthly Cost**: ~$60-80/ay
- **Storage**: 25GB dahil (ekstra $0.20/GB)
- **Backup**: Dahil

### Büyük Ölçekli (Enterprise)
- **Monthly Cost**: ~$240-500/ay
- **Storage**: 100GB+ dahil
- **Backup**: Dahil
- **High Availability**: Dahil

---

## ⚠️ Önemli Notlar

1. **SSL Zorunlu**: Tüm bağlantılar SSL ile şifrelenmelidir
2. **IP Whitelist**: Sadece gerekli IP'leri ekleyin
3. **Password Güvenliği**: Güçlü şifreler kullanın ve güvenli saklayın
4. **Backup**: Düzenli yedekleme yapın
5. **Monitoring**: Performans metriklerini düzenli kontrol edin
6. **Connection Pooling**: Django için `CONN_MAX_AGE` kullanın
7. **Schema Management**: django-tenants için schema yönetimini doğru yapılandırın

---

## 🆘 Sorun Giderme

### Bağlantı Sorunları
- **SSL Mode**: `require` olmalı
- **IP Whitelist**: Droplet IP'si eklenmiş olmalı
- **Port**: `25060` (varsayılan) kullanılmalı

### Performans Sorunları
- **Connection Pooling**: PgBouncer kullanın
- **Index**: Sık kullanılan sorgular için index oluşturun
- **Node Size**: Yetersizse yükseltin

### Yedekleme Sorunları
- **Backup Window**: Maintenance window ile çakışmamalı
- **Storage**: Yeterli depolama alanı olmalı

---

## 📚 Ek Kaynaklar

- [Digital Ocean Managed Databases Docs](https://docs.digitalocean.com/products/databases/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [django-tenants Documentation](https://django-tenants.readthedocs.io/)

---

**Son Güncelleme**: 2025-01-16

