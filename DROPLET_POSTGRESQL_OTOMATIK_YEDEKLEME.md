# Droplet Üzerinde PostgreSQL Otomatik Yedekleme Rehberi

## 📋 Genel Bakış

Bu rehber, Digital Ocean Droplet üzerinde kurulu PostgreSQL veritabanının otomatik yedekleme sistemini açıklar. Managed PostgreSQL yerine kendi PostgreSQL kurulumunuzu kullanarak maliyet tasarrufu sağlayabilirsiniz.

---

## ✅ Avantajlar ve Dezavantajlar

### ✅ Avantajlar
- **Maliyet Tasarrufu**: Managed PostgreSQL yerine droplet üzerinde çalıştırma
- **Tam Kontrol**: PostgreSQL yapılandırması üzerinde tam kontrol
- **Özelleştirme**: İhtiyacınıza göre özelleştirme yapabilme
- **Yedekleme Kontrolü**: Yedekleme stratejinizi kendiniz belirleme

### ⚠️ Dezavantajlar
- **Yönetim Yükü**: PostgreSQL'i kendiniz yönetmeniz gerekir
- **Yedekleme Sorumluluğu**: Yedeklemeleri kendiniz yönetmeniz gerekir
- **Yüksek Erişilebilirlik Yok**: Managed PostgreSQL'deki HA özellikleri yok
- **Otomatik Güncelleme Yok**: PostgreSQL güncellemelerini manuel yapmanız gerekir

---

## 🗄️ PostgreSQL Kurulumu (Droplet Üzerinde)

### 1. PostgreSQL 15 Kurulumu

```bash
# PostgreSQL repository ekle
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update

# PostgreSQL 15 kurulumu
sudo apt install -y postgresql-15 postgresql-contrib-15

# PostgreSQL servisini başlat
sudo systemctl start postgresql
sudo systemctl enable postgresql

# PostgreSQL versiyonunu kontrol et
sudo -u postgres psql -c "SELECT version();"
```

### 2. Database ve Kullanıcı Oluşturma

```bash
# PostgreSQL'e bağlan
sudo -u postgres psql

# PostgreSQL içinde:
CREATE DATABASE bulutacente_db;
CREATE USER bulutacente_user WITH PASSWORD 'GÜÇLÜ_ŞİFRE_BURAYA';
ALTER ROLE bulutacente_user SET client_encoding TO 'utf8';
ALTER ROLE bulutacente_user SET default_transaction_isolation TO 'read committed';
ALTER ROLE bulutacente_user SET timezone TO 'Europe/Istanbul';
GRANT ALL PRIVILEGES ON DATABASE bulutacente_db TO bulutacente_user;

# PostgreSQL extension'ları aktifleştir (django-tenants için gerekli)
\c bulutacente_db
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
\q
```

### 3. PostgreSQL Yapılandırması

```bash
# PostgreSQL config dosyasını düzenle
sudo nano /etc/postgresql/15/main/postgresql.conf

# Aşağıdaki satırları bulun ve değiştirin:
# listen_addresses = 'localhost'  # Sadece localhost'tan erişim (güvenlik)
# max_connections = 100           # Bağlantı sayısı
# shared_buffers = 256MB          # RAM'in %25'i (1GB RAM için)
# effective_cache_size = 1GB      # RAM'in %50-75'i
# maintenance_work_mem = 64MB     # Bakım işlemleri için
# checkpoint_completion_target = 0.9
# wal_buffers = 16MB
# default_statistics_target = 100
# random_page_cost = 1.1          # SSD için
# effective_io_concurrency = 200  # SSD için
# work_mem = 4MB
# min_wal_size = 1GB
# max_wal_size = 4GB

# PostgreSQL'i yeniden başlat
sudo systemctl restart postgresql

# PostgreSQL durumunu kontrol et
sudo systemctl status postgresql
```

### 4. Güvenlik Ayarları

```bash
# pg_hba.conf dosyasını düzenle (sadece localhost'tan erişim)
sudo nano /etc/postgresql/15/main/pg_hba.conf

# Aşağıdaki satırları ekleyin:
# local   all             all                                     peer
# host    all             all             127.0.0.1/32            md5
# host    all             all             ::1/128                 md5

# PostgreSQL'i yeniden başlat
sudo systemctl restart postgresql
```

---

## 🔄 Otomatik Yedekleme Sistemi

### 1. Yedekleme Script'i Oluşturma

Projenizde zaten `backup_database` management command'ı var. Bunu kullanarak otomatik yedekleme script'i oluşturalım:

```bash
# Yedekleme script'i oluştur
sudo nano /var/www/bulutacente/backup_script.sh
```

```bash
#!/bin/bash
# PostgreSQL Otomatik Yedekleme Script'i

# Değişkenler
PROJECT_DIR="/var/www/bulutacente"
VENV_DIR="$PROJECT_DIR/.venv"
BACKUP_DIR="$PROJECT_DIR/backupdatabase"
LOG_FILE="$PROJECT_DIR/logs/backup.log"
RETENTION_DAYS=7  # 7 günden eski yedekleri sil

# Log dizini oluştur
mkdir -p "$(dirname "$LOG_FILE")"

# Log fonksiyonu
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Hata kontrolü
set -e

log "=== Yedekleme Başlatıldı ==="

# Virtual environment'i aktifleştir
source "$VENV_DIR/bin/activate"

# Proje dizinine git
cd "$PROJECT_DIR"

# Public schema'yı yedekle
log "Public schema yedekleniyor..."
python manage.py backup_database --schema=public --type=automatic || {
    log "HATA: Public schema yedeklenemedi!"
    exit 1
}

# Tüm tenant schema'larını yedekle
log "Tüm tenant schema'ları yedekleniyor..."
python manage.py backup_database --all --type=automatic || {
    log "HATA: Tenant schema'ları yedeklenemedi!"
    exit 1
}

# Eski yedekleri sil (7 günden eski)
log "Eski yedekler temizleniyor..."
find "$BACKUP_DIR" -name "backup_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete || {
    log "UYARI: Eski yedekler silinemedi!"
}

log "=== Yedekleme Tamamlandı ==="
log ""

# Virtual environment'i deaktifleştir
deactivate

exit 0
```

```bash
# Script'i çalıştırılabilir yap
chmod +x /var/www/bulutacente/backup_script.sh

# Test çalıştırma
/var/www/bulutacente/backup_script.sh
```

### 2. Cron Job ile Otomatik Yedekleme

```bash
# Crontab'ı düzenle
crontab -e

# Aşağıdaki satırları ekleyin:
# Her gün gece 02:00'da yedekleme yap
0 2 * * * /var/www/bulutacente/backup_script.sh >> /var/www/bulutacente/logs/backup_cron.log 2>&1

# Her 6 saatte bir yedekleme yap (opsiyonel)
# 0 */6 * * * /var/www/bulutacente/backup_script.sh >> /var/www/bulutacente/logs/backup_cron.log 2>&1

# Crontab'ı kontrol et
crontab -l
```

### 3. Celery Beat ile Otomatik Yedekleme (Önerilen)

Celery Beat kullanarak daha gelişmiş bir otomatik yedekleme sistemi kurabilirsiniz:

#### 3.1. Celery Task Oluşturma

```python
# apps/tenant_apps/backup/tasks.py dosyası oluştur
```

```python
"""
Otomatik Yedekleme Celery Tasks
"""
from celery import shared_task
from django.core.management import call_command
from django_tenants.utils import get_tenant_model, get_public_schema_name
from django.db import connection
import logging

logger = logging.getLogger(__name__)


@shared_task(name='backup.automatic_backup_all_tenants')
def automatic_backup_all_tenants():
    """
    Tüm tenant schema'larını otomatik olarak yedekler
    """
    try:
        logger.info('Otomatik yedekleme başlatıldı...')
        
        # Public schema'yı yedekle
        logger.info('Public schema yedekleniyor...')
        call_command('backup_database', schema='public', type='automatic')
        
        # Tüm tenant schema'larını yedekle
        Tenant = get_tenant_model()
        tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
        
        logger.info(f'{tenants.count()} tenant schema yedekleniyor...')
        
        for tenant in tenants:
            try:
                from django_tenants.utils import tenant_context
                with tenant_context(tenant):
                    call_command('backup_database', schema=tenant.schema_name, type='automatic')
                logger.info(f'Tenant {tenant.schema_name} yedeklendi.')
            except Exception as e:
                logger.error(f'Tenant {tenant.schema_name} yedeklenirken hata: {str(e)}')
        
        logger.info('Otomatik yedekleme tamamlandı.')
        return {'status': 'success', 'message': 'Tüm yedeklemeler tamamlandı'}
        
    except Exception as e:
        logger.error(f'Otomatik yedekleme hatası: {str(e)}')
        return {'status': 'error', 'message': str(e)}
```

#### 3.2. Celery Beat Schedule Ayarları

```python
# config/settings.py dosyasına ekle
from celery.schedules import crontab

CELERY_BEAT_SCHEDULE = {
    # Her gün gece 02:00'da otomatik yedekleme
    'automatic-backup-daily': {
        'task': 'backup.automatic_backup_all_tenants',
        'schedule': crontab(hour=2, minute=0),  # Her gün 02:00
    },
    # Her 6 saatte bir yedekleme (opsiyonel)
    # 'automatic-backup-every-6-hours': {
    #     'task': 'backup.automatic_backup_all_tenants',
    #     'schedule': crontab(minute=0, hour='*/6'),  # Her 6 saatte bir
    # },
}
```

#### 3.3. Celery Beat Servisini Başlatma

```bash
# Celery Beat systemd servisi oluştur
sudo nano /etc/systemd/system/celery-beat.service
```

```ini
[Unit]
Description=Celery Beat Service
After=network.target

[Service]
Type=simple
User=bulutacente
Group=bulutacente
WorkingDirectory=/var/www/bulutacente
Environment="PATH=/var/www/bulutacente/.venv/bin"
ExecStart=/var/www/bulutacente/.venv/bin/celery -A config beat --loglevel=info --logfile=/var/www/bulutacente/logs/celery-beat.log
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
# Servisi başlat ve aktifleştir
sudo systemctl daemon-reload
sudo systemctl start celery-beat
sudo systemctl enable celery-beat

# Durumunu kontrol et
sudo systemctl status celery-beat
```

---

## 📤 Yedeklemeleri Uzak Sunucuya Gönderme

### 1. Digital Ocean Spaces'e Yedekleme Gönderme

```bash
# s3cmd kurulumu (Digital Ocean Spaces için)
sudo apt install -y s3cmd

# s3cmd yapılandırması
s3cmd --configure

# Yedekleme script'ini güncelle
sudo nano /var/www/bulutacente/backup_script.sh
```

```bash
#!/bin/bash
# PostgreSQL Otomatik Yedekleme Script'i (Spaces'e yükleme ile)

# ... (önceki kod) ...

# Yedekleme tamamlandıktan sonra Spaces'e yükle
log "Yedeklemeler Digital Ocean Spaces'e yükleniyor..."

# Spaces bucket adı ve endpoint
SPACES_BUCKET="your-backup-bucket"
SPACES_ENDPOINT="fra1.digitaloceanspaces.com"  # Region'a göre değişir

# Yeni yedekleri Spaces'e yükle
find "$BACKUP_DIR" -name "backup_*.sql.gz" -type f -mtime -1 | while read backup_file; do
    filename=$(basename "$backup_file")
    log "Yükleniyor: $filename"
    s3cmd put "$backup_file" "s3://$SPACES_BUCKET/postgresql-backups/$filename" || {
        log "HATA: $filename yüklenemedi!"
    }
done

log "Yedeklemeler Spaces'e yüklendi."
```

### 2. SFTP ile Uzak Sunucuya Yedekleme Gönderme

```bash
# Yedekleme script'ini güncelle
sudo nano /var/www/bulutacente/backup_script.sh
```

```bash
#!/bin/bash
# PostgreSQL Otomatik Yedekleme Script'i (SFTP ile)

# ... (önceki kod) ...

# SFTP ile uzak sunucuya yükle
log "Yedeklemeler uzak sunucuya yükleniyor..."

REMOTE_HOST="backup.example.com"
REMOTE_USER="backup_user"
REMOTE_DIR="/backups/postgresql"
SSH_KEY="/home/bulutacente/.ssh/backup_key"

# Yeni yedekleri SFTP ile yükle
find "$BACKUP_DIR" -name "backup_*.sql.gz" -type f -mtime -1 | while read backup_file; do
    filename=$(basename "$backup_file")
    log "Yükleniyor: $filename"
    sftp -i "$SSH_KEY" "$REMOTE_USER@$REMOTE_HOST" <<EOF
        cd $REMOTE_DIR
        put "$backup_file" "$filename"
        quit
EOF
    if [ $? -eq 0 ]; then
        log "Başarılı: $filename"
    else
        log "HATA: $filename yüklenemedi!"
    fi
done

log "Yedeklemeler uzak sunucuya yüklendi."
```

### 3. rsync ile Yedekleme Senkronizasyonu

```bash
# rsync ile yedekleme senkronizasyonu
rsync -avz --delete \
    /var/www/bulutacente/backupdatabase/ \
    backup_user@backup.example.com:/backups/postgresql/
```

---

## 🔍 Yedekleme İzleme ve Bildirim

### 1. Email Bildirimi

```bash
# Yedekleme script'ini güncelle
sudo nano /var/www/bulutacente/backup_script.sh
```

```bash
#!/bin/bash
# PostgreSQL Otomatik Yedekleme Script'i (Email bildirimi ile)

# ... (önceki kod) ...

# Email gönder (mailutils kurulu olmalı)
send_email() {
    local subject="$1"
    local body="$2"
    echo "$body" | mail -s "$subject" admin@example.com
}

# Yedekleme başarılı
if [ $? -eq 0 ]; then
    log "Yedekleme başarılı!"
    send_email "PostgreSQL Yedekleme Başarılı" "Yedekleme başarıyla tamamlandı. Log: $LOG_FILE"
else
    log "Yedekleme başarısız!"
    send_email "PostgreSQL Yedekleme HATASI" "Yedekleme başarısız oldu! Log: $LOG_FILE"
    exit 1
fi
```

### 2. Slack/Discord Bildirimi

```bash
# Webhook URL'i ile bildirim gönder
send_slack_notification() {
    local message="$1"
    local webhook_url="https://hooks.slack.com/services/YOUR/WEBHOOK/URL"
    
    curl -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"$message\"}" \
        "$webhook_url"
}

# Yedekleme sonrası bildirim
if [ $? -eq 0 ]; then
    send_slack_notification "✅ PostgreSQL yedekleme başarılı!"
else
    send_slack_notification "❌ PostgreSQL yedekleme başarısız!"
fi
```

---

## 📊 Yedekleme Performansı ve Optimizasyon

### 1. Yedekleme Performansı İyileştirme

```bash
# PostgreSQL yedekleme ayarları
sudo nano /etc/postgresql/15/main/postgresql.conf

# Aşağıdaki ayarları ekleyin:
# max_wal_senders = 3          # WAL streaming için
# wal_level = replica          # WAL seviyesi
# archive_mode = on            # Archive modu
# archive_command = 'test ! -f /var/lib/postgresql/15/archive/%f && cp %p /var/lib/postgresql/15/archive/%f'
```

### 2. Yedekleme Sıkıştırma

Projenizde zaten `gzip` ile sıkıştırma yapılıyor. Daha iyi sıkıştırma için:

```bash
# pg_dump komutunu güncelle (backup_database.py içinde)
# --compress=9 parametresi ekle (maksimum sıkıştırma)
```

### 3. Paralel Yedekleme

```bash
# pg_dump ile paralel yedekleme
pg_dump -h localhost -U bulutacente_user -d bulutacente_db \
    --schema=public \
    --jobs=4 \
    --format=directory \
    --file=/var/www/bulutacente/backupdatabase/backup_public_$(date +%Y%m%d_%H%M%S)
```

---

## 🔄 Yedekten Geri Yükleme

### 1. Yedekten Geri Yükleme Script'i

```bash
# Geri yükleme script'i oluştur
sudo nano /var/www/bulutacente/restore_script.sh
```

```bash
#!/bin/bash
# PostgreSQL Geri Yükleme Script'i

BACKUP_FILE="$1"
SCHEMA_NAME="$2"

if [ -z "$BACKUP_FILE" ] || [ -z "$SCHEMA_NAME" ]; then
    echo "Kullanım: $0 <backup_file.sql.gz> <schema_name>"
    exit 1
fi

# Değişkenler
DB_NAME="bulutacente_db"
DB_USER="bulutacente_user"
BACKUP_DIR="/var/www/bulutacente/backupdatabase"

# Yedek dosyasını aç
gunzip -c "$BACKUP_DIR/$BACKUP_FILE" | \
    psql -h localhost -U "$DB_USER" -d "$DB_NAME" -c "SET search_path TO $SCHEMA_NAME, public;"

echo "Geri yükleme tamamlandı: $SCHEMA_NAME"
```

```bash
# Script'i çalıştırılabilir yap
chmod +x /var/www/bulutacente/restore_script.sh

# Kullanım
./restore_script.sh backup_tenant_test-otel_20250116_020000.sql.gz tenant_test-otel
```

---

## 📈 Yedekleme İzleme ve Raporlama

### 1. Yedekleme Durumu Kontrolü

```bash
# Son yedeklemeleri kontrol et
ls -lh /var/www/bulutacente/backupdatabase/

# Yedekleme loglarını kontrol et
tail -f /var/www/bulutacente/logs/backup.log

# Cron job loglarını kontrol et
tail -f /var/www/bulutacente/logs/backup_cron.log
```

### 2. Yedekleme Raporu Oluşturma

```bash
# Yedekleme raporu script'i
sudo nano /var/www/bulutacente/backup_report.sh
```

```bash
#!/bin/bash
# Yedekleme Raporu Script'i

BACKUP_DIR="/var/www/bulutacente/backupdatabase"
REPORT_FILE="/var/www/bulutacente/logs/backup_report.txt"

echo "=== PostgreSQL Yedekleme Raporu ===" > "$REPORT_FILE"
echo "Tarih: $(date)" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo "Toplam Yedek Dosyası: $(find "$BACKUP_DIR" -name "backup_*.sql.gz" | wc -l)" >> "$REPORT_FILE"
echo "Toplam Yedek Boyutu: $(du -sh "$BACKUP_DIR" | cut -f1)" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

echo "Son 10 Yedek:" >> "$REPORT_FILE"
find "$BACKUP_DIR" -name "backup_*.sql.gz" -type f -printf "%T@ %p\n" | \
    sort -rn | head -10 | \
    awk '{print strftime("%Y-%m-%d %H:%M:%S", $1), $2}' >> "$REPORT_FILE"

cat "$REPORT_FILE"
```

---

## ✅ Özet ve Öneriler

### Önerilen Yedekleme Stratejisi

1. **Günlük Yedekleme**: Her gün gece 02:00'da (Celery Beat ile)
2. **Yedekleme Saklama**: 7 gün (local), 30 gün (remote)
3. **Uzak Yedekleme**: Digital Ocean Spaces veya SFTP ile
4. **Bildirim**: Email veya Slack/Discord webhook ile
5. **İzleme**: Log dosyaları ve raporlama script'leri ile

### Güvenlik Önerileri

1. **Yedekleme Klasörü**: Web erişimine kapalı olmalı (`.htaccess` ile)
2. **Şifreleme**: Hassas veriler için yedeklemeleri şifreleyin
3. **Erişim Kontrolü**: Yedekleme dosyalarına sadece admin erişebilmeli
4. **Uzak Yedekleme**: Yedeklemeleri mutlaka uzak sunucuya gönderin

### Performans Önerileri

1. **Yedekleme Zamanı**: Düşük trafik saatlerinde yedekleme yapın
2. **Sıkıştırma**: `gzip` ile sıkıştırma kullanın
3. **Paralel Yedekleme**: Büyük veritabanları için `pg_dump --jobs` kullanın
4. **İnkremental Yedekleme**: WAL archiving ile incremental yedekleme yapın

---

**Son Güncelleme**: 2025-01-16

