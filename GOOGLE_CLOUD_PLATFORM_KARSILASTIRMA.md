# Google Cloud Platform (GCP) Karşılaştırması

## 📋 Genel Bakış

Google Cloud Platform (GCP), Google'ın bulut altyapı hizmetidir. Bu rehber, GCP'yi Hetzner ve Digital Ocean ile karşılaştırır.

---

## 💰 Fiyatlandırma Karşılaştırması

### Google Cloud Platform (GCP) Fiyatları

| Plan | vCPU | RAM | Storage | Fiyat/Ay (Sürekli Kullanım) | Fiyat/Ay (On-Demand) |
|------|------|-----|---------|----------------------------|----------------------|
| e2-micro | 0.25-1 | 1 GB | 10 GB | ~$6 | ~$7 |
| e2-small | 0.5-2 | 2 GB | 20 GB | ~$12 | ~$14 |
| e2-medium | 1-2 | 4 GB | 40 GB | ~$24 | ~$28 |
| e2-standard-2 | 2 | 8 GB | 80 GB | ~$48 | ~$56 |
| e2-standard-4 | 4 | 16 GB | 160 GB | ~$96 | ~$112 |

**Not**: GCP fiyatlandırması karmaşık (sürekli kullanım indirimi, committed use discount, spot instances vb.)

### Digital Ocean Droplet Fiyatları

| Plan | vCPU | RAM | Storage | Fiyat/Ay |
|------|------|-----|---------|----------|
| Basic | 1 | 2 GB | 50 GB SSD | $12 |
| Basic | 2 | 4 GB | 80 GB SSD | $24 |
| Basic | 2 | 8 GB | 160 GB SSD | $48 |

### Hetzner Cloud Fiyatları

| Plan | vCPU | RAM | Storage | Fiyat/Ay |
|------|------|-----|---------|----------|
| CX21 | 2 | 4 GB | 40 GB SSD | €5.83 (~$6.30) |
| CX31 | 2 | 8 GB | 80 GB SSD | €10.96 (~$11.90) |
| CX41 | 4 | 16 GB | 160 GB SSD | €21.96 (~$23.80) |

---

## 🎯 Özellikler Karşılaştırması

### 1. Performans

| Özellik | GCP | Digital Ocean | Hetzner | Kazanan |
|---------|-----|---------------|---------|---------|
| **CPU Performansı** | Çok İyi (Intel/AMD) | İyi | Çok İyi (AMD EPYC) | ⚖️ GCP/Hetzner |
| **Disk I/O** | Çok İyi (SSD) | İyi (SSD) | Çok İyi (NVMe SSD) | ✅ Hetzner |
| **Network** | Mükemmel (Google network) | İyi | Çok İyi | ✅ GCP |
| **Global CDN** | Var (Cloud CDN) | Var (Cloudflare) | Yok | ✅ GCP |

### 2. Lokasyonlar

| Sağlayıcı | Lokasyon Sayısı | Bölgeler |
|-----------|----------------|----------|
| **GCP** | ~30+ | Dünya çapında (çok fazla) |
| **Digital Ocean** | ~15 | Dünya çapında |
| **Hetzner** | ~4 | Avrupa ağırlıklı |

**Kazanan**: ✅ GCP (en fazla lokasyon)

### 3. Managed Servisler

#### Google Cloud Platform

- ✅ **Cloud SQL** (Managed PostgreSQL, MySQL, SQL Server)
- ✅ **Cloud Storage** (Object Storage - S3 uyumlu)
- ✅ **Cloud Run** (Serverless containers)
- ✅ **Cloud Functions** (Serverless)
- ✅ **Kubernetes Engine** (GKE)
- ✅ **Cloud Load Balancing**
- ✅ **Cloud CDN**
- ✅ **Cloud Monitoring**
- ✅ **Cloud Logging**
- ✅ **Cloud IAM** (Gelişmiş kimlik yönetimi)
- ✅ **Cloud Armor** (DDoS protection)
- ✅ **Cloud DNS**
- ✅ **BigQuery** (Data warehouse)
- ✅ **Cloud Pub/Sub** (Message queue)

#### Digital Ocean

- ✅ Managed Databases (PostgreSQL, MySQL, Redis)
- ✅ Managed Kubernetes (DOKS)
- ✅ Spaces (Object Storage)
- ✅ Load Balancers
- ✅ CDN (Cloudflare entegrasyonu)
- ✅ Monitoring & Alerts
- ✅ Firewalls

#### Hetzner

- ✅ Managed Kubernetes
- ✅ Load Balancers
- ✅ Firewalls
- ❌ Managed Databases (YOK)
- ❌ Object Storage (YOK)

**Kazanan**: ✅ GCP (en kapsamlı managed servisler)

### 4. Fiyatlandırma Modeli

| Özellik | GCP | Digital Ocean | Hetzner |
|---------|-----|---------------|---------|
| **Fiyatlandırma** | Karmaşık (sürekli kullanım, committed use) | Basit (sabit fiyat) | Basit (sabit fiyat) |
| **Sürekli Kullanım İndirimi** | Var (%30'a kadar) | Yok | Yok |
| **Committed Use Discount** | Var (%70'e kadar) | Yok | Yok |
| **Spot/Preemptible Instances** | Var (çok ucuz) | Yok | Yok |
| **Free Tier** | Var ($300 kredi) | Yok | Yok |

**Kazanan**: ✅ GCP (esnek fiyatlandırma, indirimler)

### 5. API ve Entegrasyonlar

| Özellik | GCP | Digital Ocean | Hetzner |
|---------|-----|---------------|---------|
| **API** | Çok Gelişmiş (REST, gRPC) | İyi (REST) | İyi (REST) |
| **CLI Tool** | gcloud (çok güçlü) | doctl (iyi) | hcloud (iyi) |
| **Terraform** | Tam destek | Tam destek | Tam destek |
| **Dokümantasyon** | Çok Kapsamlı | İyi | İyi |
| **SDK'lar** | Çok sayıda (Python, Node.js, Go, Java vb.) | Sınırlı | Sınırlı |

**Kazanan**: ✅ GCP (en gelişmiş API ve SDK'lar)

### 6. Güvenlik

| Özellik | GCP | Digital Ocean | Hetzner |
|---------|-----|---------------|---------|
| **DDoS Protection** | Gelişmiş (Cloud Armor) | Temel | Gelişmiş |
| **Firewall** | Gelişmiş (VPC Firewall) | İyi | İyi |
| **IAM** | Çok Gelişmiş | Temel | Temel |
| **Encryption** | Çok Gelişmiş (at-rest, in-transit) | İyi | İyi |
| **Compliance** | Çok Kapsamlı (SOC 2, ISO 27001, HIPAA, GDPR) | SOC 2, ISO 27001 | ISO 27001 |

**Kazanan**: ✅ GCP (en kapsamlı güvenlik)

### 7. Destek

| Özellik | GCP | Digital Ocean | Hetzner |
|---------|-----|---------------|---------|
| **Destek Kanalları** | Email, Chat, Phone, Enterprise | Email, Ticket, Community | Email, Ticket |
| **Yanıt Süresi** | Değişken (plan'a göre) | 1-4 saat | 1-2 saat |
| **Dokümantasyon** | Çok Kapsamlı | İyi | İyi |
| **Community** | Çok Aktif | Aktif | Aktif |
| **Türkçe Destek** | Yok | Yok | Yok |

**Kazanan**: ✅ GCP (Enterprise plan ile en iyi destek)

---

## 💡 Projeniz İçin Öneriler

### Google Cloud Platform Önerilir Eğer:

1. ✅ **Enterprise özellikler** gerekiyorsa
   - Gelişmiş IAM, monitoring, logging
   - Compliance gereksinimleri (HIPAA, GDPR vb.)

2. ✅ **Küresel erişim** gerekiyorsa
   - Çok sayıda lokasyon
   - Global CDN

3. ✅ **Managed PostgreSQL** kullanmak istiyorsanız
   - Cloud SQL (PostgreSQL) mevcut
   - Otomatik yedekleme, scaling

4. ✅ **Büyük ölçek** planlıyorsanız
   - Auto-scaling
   - Load balancing
   - Kubernetes (GKE)

5. ✅ **Maliyet optimizasyonu** yapmak istiyorsanız
   - Sürekli kullanım indirimi
   - Committed use discount
   - Spot instances

6. ✅ **Free Tier** kullanmak istiyorsanız
   - $300 kredi (ilk 90 gün)
   - Always Free tier (sınırlı)

### Google Cloud Platform Önerilmez Eğer:

1. ❌ **Basit proje** ise
   - GCP karmaşık, küçük projeler için fazla

2. ❌ **Düşük bütçe** varsa
   - Hetzner çok daha ucuz
   - Digital Ocean da daha ucuz

3. ❌ **Avrupa lokasyonları** yeterliyse
   - Hetzner daha ucuz ve yeterli

4. ❌ **Basit yönetim** istiyorsanız
   - GCP karmaşık, öğrenme eğrisi yüksek

---

## 📊 Maliyet Analizi (Projeniz İçin)

### Senaryo 1: Google Cloud Platform

#### Seçenek 1: Compute Engine (VM)

```
VM Instance: e2-medium (2 vCPU / 4 GB RAM) = ~$24/ay (sürekli kullanım)
Cloud SQL: db-f1-micro (1 vCPU / 0.6 GB RAM) = ~$7/ay
Cloud Storage: 100 GB = ~$2/ay
Network Egress: 100 GB = ~$12/ay
-------------------------------------------
TOPLAM: ~$45/ay
```

#### Seçenek 2: Cloud Run (Serverless)

```
Cloud Run: 2 vCPU / 4 GB RAM = Kullanım bazlı (~$20-40/ay)
Cloud SQL: db-f1-micro = ~$7/ay
Cloud Storage: 100 GB = ~$2/ay
-------------------------------------------
TOPLAM: ~$29-49/ay (değişken)
```

#### Seçenek 3: GKE (Kubernetes)

```
GKE Cluster: 1 node (e2-medium) = ~$24/ay + $0.10/saat cluster fee = ~$31/ay
Cloud SQL: db-f1-micro = ~$7/ay
Cloud Storage: 100 GB = ~$2/ay
-------------------------------------------
TOPLAM: ~$40/ay
```

### Senaryo 2: Digital Ocean

```
Droplet: 2 vCPU / 4 GB RAM = $24/ay
Managed PostgreSQL: 2 vCPU / 4 GB RAM = $60/ay
Spaces: 250 GB = $5/ay
-------------------------------------------
TOPLAM: ~$89/ay
```

### Senaryo 3: Hetzner

```
Droplet: 2 vCPU / 4 GB RAM = €5.83 (~$6.30/ay)
PostgreSQL: Droplet içinde (ekstra maliyet yok)
Object Storage: MinIO (ekstra maliyet yok)
-------------------------------------------
TOPLAM: ~$6.30/ay
```

**Maliyet Sıralaması:**
1. ✅ Hetzner: ~$6.30/ay
2. ✅ GCP: ~$29-49/ay
3. ⚠️ Digital Ocean: ~$89/ay

---

## 🎯 GCP Özellikleri ve Avantajlar

### 1. Free Tier ($300 Kredi)

- İlk 90 gün için $300 kredi
- Always Free tier (sınırlı kaynaklar)
- Cloud SQL: 1 instance (db-f1-micro)
- Cloud Storage: 5 GB
- Compute Engine: 1 f1-micro instance (aylık 720 saat)

### 2. Sürekli Kullanım İndirimi

- Aylık kullanım %30'a kadar indirim
- Otomatik uygulanır
- Hiçbir taahhüt gerekmez

### 3. Committed Use Discount

- 1-3 yıllık taahhüt ile %70'e kadar indirim
- Öngörülebilir maliyetler

### 4. Spot Instances (Preemptible)

- %80-90 daha ucuz
- Kısa süreli işler için ideal
- 24 saat içinde sonlandırılabilir

### 5. Auto-Scaling

- Otomatik ölçeklendirme
- Yüksek trafikte otomatik genişleme
- Düşük trafikte otomatik daralma

### 6. Global Infrastructure

- ~30+ lokasyon
- Düşük latency
- Global CDN

---

## ⚠️ GCP Dezavantajları

### 1. Karmaşıklık

- Öğrenme eğrisi yüksek
- Çok fazla servis ve seçenek
- Küçük projeler için fazla

### 2. Fiyatlandırma Karmaşıklığı

- Sürekli kullanım, committed use, spot instances
- Network egress ücretleri
- Fiyatlandırma tahmin etmek zor

### 3. Network Egress Ücretleri

- Veri çıkışı için ücretlendirme
- Digital Ocean ve Hetzner'da yok (bandwidth dahil)

### 4. Minimum Ücretler

- Bazı servisler için minimum ücretler
- Küçük kullanım için pahalı olabilir

---

## 🚀 GCP Kurulum Rehberi (Özet)

### 1. GCP Hesabı Oluşturma

1. [Google Cloud Console](https://console.cloud.google.com/)'a gidin
2. Hesap oluşturun
3. $300 kredi alın (ilk 90 gün)

### 2. Compute Engine Instance Oluşturma

```bash
# gcloud CLI kurulumu
# Windows: https://cloud.google.com/sdk/docs/install

# GCP'ye giriş yap
gcloud auth login

# Proje oluştur
gcloud projects create bulut-acente-prod

# Projeyi seç
gcloud config set project bulut-acente-prod

# VM instance oluştur
gcloud compute instances create bulut-acente-vm \
    --zone=europe-west3-a \
    --machine-type=e2-medium \
    --image-family=ubuntu-2204-lts \
    --image-project=ubuntu-os-cloud \
    --boot-disk-size=40GB \
    --tags=http-server,https-server
```

### 3. Cloud SQL (Managed PostgreSQL)

```bash
# Cloud SQL instance oluştur
gcloud sql instances create bulut-acente-db \
    --database-version=POSTGRES_15 \
    --tier=db-f1-micro \
    --region=europe-west3 \
    --root-password=GÜÇLÜ_ŞİFRE
```

### 4. Firewall Kuralları

```bash
# HTTP ve HTTPS izin ver
gcloud compute firewall-rules create allow-http \
    --allow tcp:80 \
    --source-ranges 0.0.0.0/0 \
    --target-tags http-server

gcloud compute firewall-rules create allow-https \
    --allow tcp:443 \
    --source-ranges 0.0.0.0/0 \
    --target-tags https-server
```

---

## ✅ Sonuç ve Öneri

### Projeniz İçin Öneri

**Google Cloud Platform önerilir eğer:**

1. ✅ **Enterprise özellikler** gerekiyorsa
2. ✅ **Küresel erişim** gerekiyorsa
3. ✅ **Managed PostgreSQL** kullanmak istiyorsanız
4. ✅ **Büyük ölçek** planlıyorsanız
5. ✅ **Free Tier** kullanmak istiyorsanız ($300 kredi)

**Google Cloud Platform önerilmez eğer:**

1. ❌ **Basit proje** ise
2. ❌ **Düşük bütçe** varsa (Hetzner çok daha ucuz)
3. ❌ **Avrupa lokasyonları** yeterliyse (Hetzner daha ucuz)
4. ❌ **Basit yönetim** istiyorsanız

### Maliyet Karşılaştırması

| Sağlayıcı | Aylık Maliyet | Özellikler |
|-----------|---------------|------------|
| **Hetzner** | ~$6.30/ay | En ucuz, yüksek performans |
| **GCP** | ~$29-49/ay | Enterprise özellikler, managed servisler |
| **Digital Ocean** | ~$89/ay | Basit, managed servisler |

### Final Öneri

**Küçük-Orta Ölçek Projeler İçin:**
- ✅ **Hetzner** (en ucuz, yeterli)
- ⚠️ **GCP** (Free Tier ile başlayabilirsiniz)

**Büyük Ölçek/Enterprise Projeler İçin:**
- ✅ **GCP** (en kapsamlı özellikler)
- ⚠️ **Digital Ocean** (daha basit alternatif)

---

## 📚 Ek Kaynaklar

- [Google Cloud Platform Dokümantasyonu](https://cloud.google.com/docs)
- [GCP Free Tier](https://cloud.google.com/free)
- [GCP Fiyatlandırma Hesaplayıcı](https://cloud.google.com/products/calculator)
- [GCP vs AWS vs Azure Karşılaştırması](https://cloud.google.com/docs/compare)

---

**Son Güncelleme**: 2025-01-16

