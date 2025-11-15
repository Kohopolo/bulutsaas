# ⏰ Yedekleme Modülü Cron Job Rehberi

**Tarih:** 2025-01-27  
**Modül:** Yedekleme Yönetimi (Backup)

---

## 📋 Genel Bakış

Yedekleme modülü için otomatik yedekleme işlemleri iki yöntemle yapılabilir:

1. **Celery Beat** (Önerilen - Django entegrasyonu)
2. **Cron Job** (Alternatif - Sistem seviyesi)

---

## 🔄 Celery Beat ile Otomatik Yedekleme

### Avantajlar

- Django ile tam entegrasyon
- Veritabanı tabanlı zamanlama (Django admin'den yönetilebilir)
- Task durumu takibi
- Hata yönetimi ve loglama
- Web arayüzünden yönetim

### Kurulum

Celery Beat zaten yapılandırılmış. Sadece servisi başlatmanız yeterli:

```bash
# Celery Beat servisini başlat
celery -A config beat -l info --scheduler django_celery_beat.schedulers:DatabaseScheduler
```

### Otomatik Görevler

#### 1. Günlük Yedekleme

**Task**: `backup.daily_backup`  
**Zamanlama**: Her gece saat 03:00  
**Görev**: 
- Public schema'yı yedekler
- Tüm tenant schema'larını yedekler
- Yedekleme kayıtlarını veritabanına kaydeder

**Yapılandırma**: `config/celery.py`
```python
'daily-database-backup': {
    'task': 'backup.daily_backup',
    'schedule': crontab(hour=3, minute=0),
},
```

#### 2. Eski Yedek Temizleme

**Task**: `backup.cleanup_old_backups`  
**Zamanlama**: Her Pazar saat 04:00  
**Görev**: 
- 30 günden eski yedekleri siler
- Disk alanı tasarrufu sağlar

**Yapılandırma**: `config/celery.py`
```python
'cleanup-old-backups': {
    'task': 'backup.cleanup_old_backups',
    'schedule': crontab(hour=4, minute=0, day_of_week=0),  # 0 = Pazar
},
```

### Zamanlama Değiştirme

Django Admin'den:
1. **Django Admin** > **Periodic Tasks**
2. İlgili task'ı bulun
3. **Crontab Schedule** veya **Interval Schedule** düzenleyin
4. Kaydedin

---

## ⏰ Cron Job ile Otomatik Yedekleme (Alternatif)

### Linux/Mac

#### 1. Crontab Düzenleme

```bash
# Crontab düzenle
crontab -e
```

#### 2. Günlük Yedekleme Ekleme

```bash
# Her gece saat 03:00'de yedekleme
0 3 * * * cd /path/to/project && /path/to/venv/bin/python manage.py backup_daily >> /var/log/backup.log 2>&1
```

#### 3. Eski Yedek Temizleme Ekleme

```bash
# Her Pazar saat 04:00'de eski yedekleri temizle
0 4 * * 0 cd /path/to/project && /path/to/venv/bin/python manage.py backup_database --cleanup-days=30 >> /var/log/backup_cleanup.log 2>&1
```

#### 4. Örnek Crontab Girişleri

```bash
# Günlük yedekleme (her gece 03:00)
0 3 * * * cd /home/user/bulutacente && /home/user/bulutacente/venv/bin/python manage.py backup_daily >> /var/log/backup.log 2>&1

# Haftalık eski yedek temizleme (her Pazar 04:00)
0 4 * * 0 cd /home/user/bulutacente && /home/user/bulutacente/venv/bin/python manage.py backup_database --cleanup-days=30 >> /var/log/backup_cleanup.log 2>&1

# Haftalık tam yedekleme (her Pazar 02:00 - tüm schema'lar)
0 2 * * 0 cd /home/user/bulutacente && /home/user/bulutacente/venv/bin/python manage.py backup_database --all --type=automatic >> /var/log/backup_full.log 2>&1
```

### Windows (Task Scheduler)

#### 1. Günlük Yedekleme Görevi Oluşturma

1. **Task Scheduler** açın (Win + R > `taskschd.msc`)
2. **Create Basic Task** seçin
3. **Name**: "Günlük Veritabanı Yedekleme"
4. **Description**: "Her gece saat 03:00'de veritabanı yedekleme"
5. **Trigger**: Daily, 03:00
6. **Action**: Start a program
7. **Program/script**: `C:\xampp\htdocs\bulutacente\venv\Scripts\python.exe`
8. **Add arguments**: `manage.py backup_daily`
9. **Start in**: `C:\xampp\htdocs\bulutacente`
10. **Finish**

#### 2. Eski Yedek Temizleme Görevi Oluşturma

1. **Task Scheduler** açın
2. **Create Basic Task** seçin
3. **Name**: "Haftalık Eski Yedek Temizleme"
4. **Description**: "Her Pazar saat 04:00'de 30 günden eski yedekleri temizle"
5. **Trigger**: Weekly, Pazar, 04:00
6. **Action**: Start a program
7. **Program/script**: `C:\xampp\htdocs\bulutacente\venv\Scripts\python.exe`
8. **Add arguments**: `manage.py backup_database --cleanup-days=30`
9. **Start in**: `C:\xampp\htdocs\bulutacente`
10. **Finish**

#### 3. PowerShell Script ile (Alternatif)

`backup_daily.ps1` dosyası oluşturun:

```powershell
# backup_daily.ps1
cd C:\xampp\htdocs\bulutacente
.\venv\Scripts\python.exe manage.py backup_daily
```

Task Scheduler'da bu script'i çalıştırın.

---

## 📊 Zamanlama Örnekleri

### Günlük Yedekleme

```bash
# Her gece saat 03:00
0 3 * * * python manage.py backup_daily

# Her gece saat 02:30
30 2 * * * python manage.py backup_daily

# Her gün saat 23:00
0 23 * * * python manage.py backup_daily
```

### Haftalık Yedekleme

```bash
# Her Pazar saat 02:00
0 2 * * 0 python manage.py backup_database --all

# Her Pazartesi saat 01:00
0 1 * * 1 python manage.py backup_database --all
```

### Aylık Yedekleme

```bash
# Her ayın 1'i saat 01:00
0 1 1 * * python manage.py backup_database --all
```

### Eski Yedek Temizleme

```bash
# Her Pazar saat 04:00 (30 günden eski)
0 4 * * 0 python manage.py backup_database --cleanup-days=30

# Her ayın 1'i saat 05:00 (60 günden eski)
0 5 1 * * python manage.py backup_database --cleanup-days=60
```

---

## 🔍 Loglama ve İzleme

### Log Dosyası Oluşturma

```bash
# Crontab'ta log dosyasına yaz
0 3 * * * cd /path/to/project && python manage.py backup_daily >> /var/log/backup.log 2>&1
```

### Log Dosyası İzleme

```bash
# Son 50 satırı göster
tail -n 50 /var/log/backup.log

# Canlı izleme
tail -f /var/log/backup.log

# Hata satırlarını filtrele
grep -i error /var/log/backup.log
```

### Windows Event Viewer

Windows Task Scheduler görevleri **Event Viewer**'da görüntülenebilir:
1. **Event Viewer** açın
2. **Windows Logs** > **Application**
3. Görev adını arayın

---

## ⚙️ Gelişmiş Yapılandırma

### Email Bildirimleri

Yedekleme başarısız olduğunda email göndermek için:

```bash
# Email gönderen script
#!/bin/bash
cd /path/to/project
python manage.py backup_daily
if [ $? -ne 0 ]; then
    echo "Yedekleme başarısız!" | mail -s "Yedekleme Hatası" admin@example.com
fi
```

### Disk Alanı Kontrolü

Yedekleme öncesi disk alanı kontrolü:

```bash
#!/bin/bash
DISK_USAGE=$(df -h /path/to/backupdatabase | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "Disk alanı %80'i aştı! Yedekleme yapılamıyor." | mail -s "Disk Uyarısı" admin@example.com
    exit 1
fi
cd /path/to/project
python manage.py backup_daily
```

### Yedekleme Sonrası Bildirim

```bash
#!/bin/bash
cd /path/to/project
python manage.py backup_daily
if [ $? -eq 0 ]; then
    echo "Yedekleme başarıyla tamamlandı." | mail -s "Yedekleme Başarılı" admin@example.com
fi
```

---

## 🎯 Önerilen Zamanlama

### Küçük Sistemler (1-5 Tenant)

- **Günlük Yedekleme**: Her gece saat 03:00
- **Eski Yedek Temizleme**: Her Pazar saat 04:00 (30 gün)

### Orta Sistemler (5-20 Tenant)

- **Günlük Yedekleme**: Her gece saat 02:00
- **Haftalık Tam Yedekleme**: Her Pazar saat 01:00 (tüm schema'lar)
- **Eski Yedek Temizleme**: Her Pazar saat 04:00 (30 gün)

### Büyük Sistemler (20+ Tenant)

- **Günlük Yedekleme**: Her gece saat 01:00
- **Haftalık Tam Yedekleme**: Her Pazar saat 00:00 (tüm schema'lar)
- **Aylık Arşiv Yedekleme**: Her ayın 1'i saat 23:00
- **Eski Yedek Temizleme**: Her Pazar saat 04:00 (60 gün)

---

## 📝 Notlar

1. **Disk Alanı**: Yedekleme için yeterli disk alanı olduğundan emin olun
2. **Performans**: Yedekleme sırasında sistem performansı etkilenebilir
3. **Zamanlama**: Yoğun saatlerde yedekleme yapmayın
4. **Test**: İlk yedeklemeyi manuel olarak test edin
5. **Monitoring**: Yedekleme loglarını düzenli kontrol edin

---

**Son Güncelleme:** 2025-01-27





