# 📋 Yedekleme Modülü Kurulum Talimatları

**Tarih:** 2025-01-27  
**Modül:** Yedekleme Yönetimi (Backup)

---

## 🚀 Kurulum Adımları

### 1. Virtual Environment Aktifleştirme

```bash
# Windows
venv\Scripts\activate

# Linux/Mac
source venv/bin/activate
```

### 2. Migration Çalıştırma

```bash
# Public schema'da migration
python manage.py migrate backup

# Tüm tenant schema'larında migration
python manage.py migrate_schemas backup
```

### 3. SaaS Modül Oluşturma

```bash
# Public schema'da modül oluştur
python manage.py create_backup_module
```

Bu komut:
- `Module` tablosuna "Yedekleme Yönetimi" modülünü ekler
- Modül bilgilerini ve yetkilerini tanımlar
- `available_permissions` JSONField'ını doldurur

### 4. Permission Oluşturma

#### Public Schema'da:
```bash
python manage.py create_backup_permissions
```

#### Tenant Schema'larda:
```bash
# Her tenant için ayrı ayrı
python manage.py migrate_schemas --schema=<tenant_schema> backup
python manage.py create_backup_permissions --schema=<tenant_schema>

# VEYA otomatik tüm tenant'lar için:
python manage.py setup_backup_all_tenants
```

### 5. Paket Yönetiminde Modülü Aktifleştirme

```bash
# Tüm aktif paketlere modülü ekle
python manage.py add_backup_to_packages
```

Bu komut:
- Tüm aktif paketlere "Yedekleme Yönetimi" modülünü ekler
- Varsayılan yetkileri atar (view, add, edit, delete, download)
- Modülü aktif hale getirir

### 6. Super Admin Panelinden Kontrol

1. **Super Admin** > **Paketler** > Paket seç > **Düzenle**
2. **Modüller** sekmesinde **Yedekleme Yönetimi** modülünü kontrol et
3. **Aktif** işaretli olduğundan emin ol
4. **Yetkiler** JSON alanını kontrol et:
   ```json
   {
     "view": true,
     "add": true,
     "edit": true,
     "delete": true,
     "download": true
   }
   ```

---

## ⚙️ Otomatik Yedekleme Kurulumu

### Celery Beat ile Otomatik Yedekleme

Celery Beat zaten yapılandırılmış. Otomatik görevler:

1. **Günlük Yedekleme**: Her gece saat 03:00
   - Task: `backup.daily_backup`
   - Public schema ve tüm tenant schema'larını yedekler

2. **Eski Yedek Temizleme**: Her Pazar saat 04:00
   - Task: `backup.cleanup_old_backups`
   - 30 günden eski yedekleri siler

### Celery Beat Çalıştırma

```bash
# Celery Beat servisini başlat
celery -A config beat -l info --scheduler django_celery_beat.schedulers:DatabaseScheduler
```

### Cron Job (Alternatif)

Eğer Celery Beat kullanmıyorsanız, cron job ile:

#### Linux/Mac:
```bash
# Crontab düzenle
crontab -e

# Her gece saat 03:00'de yedekleme
0 3 * * * cd /path/to/project && /path/to/venv/bin/python manage.py backup_daily

# Her Pazar saat 04:00'de eski yedekleri temizle
0 4 * * 0 cd /path/to/project && /path/to/venv/bin/python manage.py backup_database --cleanup-days=30
```

#### Windows (Task Scheduler):
1. **Task Scheduler** açın
2. **Create Basic Task** seçin
3. **Name**: "Günlük Veritabanı Yedekleme"
4. **Trigger**: Daily, 03:00
5. **Action**: Start a program
6. **Program**: `C:\path\to\venv\Scripts\python.exe`
7. **Arguments**: `manage.py backup_daily`
8. **Start in**: `C:\xampp\htdocs\bulutacente`

---

## 🔒 Güvenlik

### Klasör Güvenliği

`backupdatabase` klasörü otomatik olarak korunur:

1. **Django Middleware**: HTTP erişimini engeller
2. **Apache .htaccess**: Apache sunucularda erişimi engeller
3. **IIS web.config**: IIS sunucularda erişimi engeller
4. **index.html**: Dizin listeleme durumunda 403 Forbidden gösterir

### Dosya İzinleri

Yedek dosyaları hassas veriler içerir. Güvenlik için:

```bash
# Linux/Mac
chmod 600 backupdatabase/*.sql.gz
chmod 700 backupdatabase

# Windows
# Dosya özelliklerinden "Read-only" işaretle
```

---

## 📊 Yetki Sistemi

### Modül Yetkileri

- **view**: Yedeklemeleri görüntüleme
- **add**: Yeni yedekleme oluşturma
- **edit**: Yedekleme düzenleme
- **delete**: Yedekleme silme
- **download**: Yedekleme indirme

### Rol Yetkileri

Admin rolüne otomatik olarak tüm yetkiler atanır. Diğer roller için Super Admin panelinden yetki ataması yapılabilir.

---

## 🎯 Kullanım

### Manuel Yedekleme

#### Web Arayüzünden:
1. **Yedekleme Yönetimi** > **Yeni Yedekleme**
2. Schema seçin
3. **Yedekleme Oluştur** butonuna tıklayın

#### Komut Satırından:
```bash
# Public schema yedekle
python manage.py backup_database

# Belirli schema yedekle
python manage.py backup_database --schema=tenant_schema_name

# Tüm schema'ları yedekle
python manage.py backup_database --all

# Otomatik yedekleme (Celery Beat için)
python manage.py backup_database --type=automatic
```

### Yedekleme İndirme

1. **Yedekleme Yönetimi** > **Yedeklemeler**
2. İndirmek istediğiniz yedeklemeyi bulun
3. **İndir** butonuna tıklayın

### Yedekleme Silme

1. **Yedekleme Yönetimi** > **Yedeklemeler**
2. Silmek istediğiniz yedeklemeyi bulun
3. **Sil** butonuna tıklayın
4. Onaylayın

---

## 📝 Cron Job Detayları

### Günlük Otomatik Yedekleme

**Zamanlama**: Her gece saat 03:00  
**Komut**: `python manage.py backup_daily`  
**Görev**: Public schema ve tüm tenant schema'larını yedekler

### Eski Yedek Temizleme

**Zamanlama**: Her Pazar saat 04:00  
**Komut**: `python manage.py backup_database --cleanup-days=30`  
**Görev**: 30 günden eski yedekleri siler

---

## 🔍 Sorun Giderme

### pg_dump Bulunamadı

**Hata**: `pg_dump bulunamadı`

**Çözüm**: PostgreSQL client tools yüklenmeli
```bash
# Ubuntu/Debian
sudo apt-get install postgresql-client

# Windows
# PostgreSQL installer'dan "Command Line Tools" seçeneğini seçin
```

### Yedekleme Başarısız

**Kontrol Edilmesi Gerekenler:**
1. PostgreSQL bağlantı bilgileri doğru mu?
2. Kullanıcının schema'ya erişim yetkisi var mı?
3. Disk alanı yeterli mi?
4. `backupdatabase` klasörü yazılabilir mi?

### Celery Beat Çalışmıyor

**Kontrol Edilmesi Gerekenler:**
1. Celery Beat servisi çalışıyor mu?
2. Redis/Broker bağlantısı çalışıyor mu?
3. Task'lar doğru tanımlanmış mı?

---

## ✅ Kontrol Listesi

- [ ] Virtual environment aktif
- [ ] Migration'lar çalıştırıldı
- [ ] Modül oluşturuldu (`create_backup_module`)
- [ ] Permission'lar oluşturuldu (`create_backup_permissions`)
- [ ] Modül paketlere eklendi (`add_backup_to_packages`)
- [ ] Sidebar'da görünüyor mu?
- [ ] Celery Beat çalışıyor mu?
- [ ] Güvenlik dosyaları oluşturuldu mu?
- [ ] Test yedekleme başarılı mı?

---

**Son Güncelleme:** 2025-01-27





