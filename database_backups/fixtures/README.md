# 📦 Database Fixtures

Bu klasör GitHub'a yüklenebilir güvenli veritabanı yapısı ve örnek verileri içerir.

## 🚀 Kullanım

### Fixture'ları Yükle

```powershell
# Tüm fixture'ları yükle
python manage.py loaddata database_backups/fixtures/initial_data.json

# Veya tek tek
python manage.py loaddata database_backups/fixtures/tenants.json
python manage.py loaddata database_backups/fixtures/packages.json
python manage.py loaddata database_backups/fixtures/modules.json
```

## ⚠️ Güvenlik

Bu dosyalar hassas veriler içermez:
- ✅ Şifreler temizlenmiş
- ✅ Kişisel bilgiler temizlenmiş
- ✅ Sadece yapı ve örnek veriler

## 📋 Dosya Açıklamaları

- `initial_data.json` - Tüm başlangıç verileri
- `tenants.json` - Örnek tenant'lar
- `packages.json` - Paket tanımları
- `modules.json` - Modül tanımları





