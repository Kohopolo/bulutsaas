# 🗄️ Veritabanını GitHub'a Yükleme Rehberi

## ⚠️ ÖNEMLİ GÜVENLİK UYARILARI

**GitHub'a yüklemeden önce mutlaka okuyun:**

1. ❌ **Hassas verileri yüklemeyin:**
   - Kullanıcı şifreleri
   - Kredi kartı bilgileri
   - TC kimlik numaraları
   - Telefon numaraları (opsiyonel)
   - E-posta adresleri (opsiyonel)

2. ✅ **Yüklenebilir veriler:**
   - Veritabanı yapısı (schema)
   - Örnek veriler (test verileri)
   - Migration dosyaları
   - Seed data (başlangıç verileri)

---

## 📋 Yöntem 1: Django dumpdata (Önerilen)

### Adım 1: Export Scriptini Çalıştır

```powershell
# Güvenli export (hassas veriler temizlenmiş)
python scripts/export_database_safe.py

# Veya tam export (dikkatli kullanın!)
python scripts/export_database.py
```

### Adım 2: Export Edilen Dosyaları Kontrol Et

```powershell
# Export klasörüne bak
dir database_backups\
```

### Adım 3: GitHub'a Ekle

```powershell
# Export dosyalarını git'e ekle
git add database_backups/public_schema_safe_*.json
git add database_backups/sample_structure_*.json

# Commit
git commit -m "Database structure export (safe for GitHub)"

# Push
git push
```

---

## 📋 Yöntem 2: PostgreSQL pg_dump (SQL Format)

### Adım 1: SQL Dump Oluştur

**Docker kullanıyorsanız:**
```powershell
docker-compose exec db pg_dump -U saas_user saas_db > database_backups/db_structure.sql
```

**Yerel PostgreSQL kullanıyorsanız:**
```powershell
pg_dump -U saas_user saas_db > database_backups/db_structure.sql
```

### Adım 2: Hassas Verileri Temizle

SQL dosyasını açıp şunları temizleyin:
- `INSERT INTO auth_user` satırları (şifreler)
- `INSERT INTO subscriptions` içindeki kredi kartı bilgileri
- Kişisel bilgiler (TC, telefon, e-posta)

### Adım 3: GitHub'a Ekle

```powershell
git add database_backups/db_structure.sql
git commit -m "Database structure SQL dump"
git push
```

---

## 📋 Yöntem 3: Sadece Migration Dosyaları (En Güvenli)

**Bu yöntem veri içermez, sadece yapıyı içerir:**

```powershell
# Migration dosyaları zaten git'te
git add apps/*/migrations/
git commit -m "Database migrations"
git push
```

**Avantajları:**
- ✅ Hassas veri yok
- ✅ Veritabanı yapısı korunur
- ✅ Diğer geliştiriciler migration çalıştırabilir

**Dezavantajları:**
- ❌ Örnek veri yok
- ❌ Test için manuel veri girmek gerekir

---

## 📋 Yöntem 4: Fixtures (Seed Data)

### Adım 1: Fixture Oluştur

```powershell
# Örnek verileri fixture olarak export et
python manage.py dumpdata tenants packages modules --indent 2 --output database_backups/fixtures/initial_data.json
```

### Adım 2: Hassas Verileri Temizle

`initial_data.json` dosyasını açıp şifreleri ve kişisel bilgileri temizleyin.

### Adım 3: GitHub'a Ekle

```powershell
git add database_backups/fixtures/initial_data.json
git commit -m "Initial fixtures (safe seed data)"
git push
```

### Adım 4: Fixture'ı Yükle

```powershell
# Yeni kurulumda fixture'ı yükle
python manage.py loaddata database_backups/fixtures/initial_data.json
```

---

## 🔒 Güvenlik Checklist

GitHub'a yüklemeden önce kontrol edin:

- [ ] Kullanıcı şifreleri temizlendi mi?
- [ ] Kredi kartı bilgileri var mı?
- [ ] TC kimlik numaraları var mı?
- [ ] Gerçek müşteri verileri var mı?
- [ ] API anahtarları var mı?
- [ ] Secret key'ler var mı?

**Eğer bunlardan herhangi biri varsa, temizleyin veya yüklemeyin!**

---

## 📁 Önerilen Dosya Yapısı

```
database_backups/
├── fixtures/              # Seed data (GitHub'a yüklenebilir)
│   ├── initial_data.json
│   └── sample_tenants.json
├── structure/             # Sadece yapı (GitHub'a yüklenebilir)
│   ├── schema.sql
│   └── migrations/
└── production/            # Production backup (GitHub'a YÜKLENMEZ!)
    └── backup_*.sql
```

**`.gitignore` dosyasına ekleyin:**
```
database_backups/production/
database_backups/*_production_*
```

---

## 🚀 Hızlı Başlangıç (Önerilen Yöntem)

### 1. Güvenli Export Scriptini Çalıştır

```powershell
python scripts/export_database_safe.py
```

### 2. Dosyaları Git'e Ekle

```powershell
git add database_backups/public_schema_safe_*.json
git add database_backups/sample_structure_*.json
git commit -m "Database structure export (safe for GitHub)"
git push
```

### 3. README'ye Not Ekleyin

`README.md` dosyasına ekleyin:

```markdown
## 📦 Veritabanı Yapısı

Veritabanı yapısı `database_backups/` klasöründe bulunmaktadır.

**Kurulum:**
1. Migration'ları çalıştırın: `python manage.py migrate_schemas`
2. Örnek verileri yükleyin: `python manage.py loaddata database_backups/fixtures/initial_data.json`
```

---

## 🆘 Sorun Giderme

### "ModuleNotFoundError: No module named 'django'"

```powershell
# Virtual environment'ı aktifleştir
.\venv\Scripts\Activate.ps1
```

### "Permission denied" (PostgreSQL)

```powershell
# Kullanıcı adını kontrol et
# settings.py'daki DATABASES ayarlarına bak
```

### "File too large" (GitHub)

GitHub 100MB'dan büyük dosyaları kabul etmez. Çözüm:

1. Dosyayı bölün
2. Git LFS kullanın
3. Sadece yapıyı yükleyin (veri olmadan)

---

## 📚 Ek Kaynaklar

- Django dumpdata: https://docs.djangoproject.com/en/stable/ref/django-admin/#dumpdata
- PostgreSQL pg_dump: https://www.postgresql.org/docs/current/app-pgdump.html
- Git LFS: https://git-lfs.github.com/

---

**🎉 Başarılar! Veritabanı yapınız GitHub'da!**





