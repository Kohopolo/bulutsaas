# 🔄 GitHub Güncelleme Rehberi

**Tarih:** 2025-01-27  
**Durum:** Hazır

---

## 📋 Yapılacaklar

### 1. Git Kurulumu (Eğer yoksa)

Git kurulu değilse:

**Windows için:**
1. https://git-scm.com/download/win adresinden Git'i indirin
2. Kurulumu tamamlayın
3. PowerShell veya CMD'yi yeniden başlatın

**Kurulumu kontrol etmek için:**
```powershell
git --version
```

### 2. GitHub Repository Hazırlığı

1. GitHub'da yeni bir repository oluşturun: https://github.com/new
2. Repository adı: `bulutacente` (veya istediğiniz isim)
3. **Public** veya **Private** seçin
4. **README.md**, **.gitignore**, **license** eklemeyin (zaten var)

---

## 🚀 GitHub'a Yükleme Adımları

### Adım 1: Git Repository Başlat (Eğer başlatılmadıysa)

```powershell
cd C:\xampp\htdocs\bulutacente
git init
```

### Adım 2: Remote Repository Ekle

```powershell
# GitHub repository URL'inizi buraya ekleyin
git remote add origin https://github.com/KULLANICI_ADI/REPO_ADI.git

# Veya SSH kullanıyorsanız:
# git remote add origin git@github.com:KULLANICI_ADI/REPO_ADI.git
```

### Adım 3: Tüm Değişiklikleri Kontrol Et

```powershell
git status
```

### Adım 4: Değişiklikleri Staging Area'ya Ekle

```powershell
# Tüm değişiklikleri ekle
git add .

# Veya belirli dosyaları ekle
# git add requirements.txt
# git add apps/tenant_apps/core/pdf_utils.py
# git add apps/tenant_apps/ferry_tickets/views.py
```

### Adım 5: Commit Oluştur

```powershell
git commit -m "PDF güvenlik güncellemesi: xhtml2pdf kaldırıldı, ReportLab öncelikli yapıldı

- xhtml2pdf güvenlik riski nedeniyle kaldırıldı
- ReportLab öncelikli PDF oluşturma sistemi eklendi
- Güvenli PDF utility fonksiyonu oluşturuldu (apps/tenant_apps/core/pdf_utils.py)
- Tüm PDF view'ları güncellendi (ferry_tickets, reception, bungalovs)
- requirements.txt güncellendi
- Migrationlar ve veritabanı değişiklikleri eklendi"
```

### Adım 6: GitHub'a Push Et

```powershell
# İlk push için
git push -u origin main

# Veya master branch kullanıyorsanız:
# git push -u origin master

# Sonraki push'lar için sadece:
# git push
```

---

## 📦 Yüklenmesi Gereken Dosyalar

### ✅ Mutlaka Yüklenmeli

- ✅ `requirements.txt` - Güncellenmiş bağımlılıklar
- ✅ `apps/tenant_apps/core/pdf_utils.py` - Yeni PDF utility
- ✅ `apps/tenant_apps/ferry_tickets/views.py` - Güncellenmiş PDF view
- ✅ `apps/tenant_apps/reception/views.py` - Güncellenmiş PDF view
- ✅ `apps/tenant_apps/bungalovs/views.py` - Güncellenmiş PDF view
- ✅ Tüm migration dosyaları (`apps/*/migrations/*.py`)
- ✅ `.gitignore` - Güncel ignore kuralları

### ❌ Yüklenmemeli (.gitignore'da)

- ❌ `venv/` - Virtual environment
- ❌ `*.pyc`, `__pycache__/` - Python cache
- ❌ `.env` - Environment variables
- ❌ `db.sqlite3` - Local database
- ❌ `media/` - Uploaded files
- ❌ `staticfiles/` - Collected static files
- ❌ `logs/` - Log files
- ❌ `database_backups/*.sql` - Database backups

---

## 🔍 Kontrol Listesi

### Git Kurulumu
- [ ] Git kurulu mu? (`git --version`)
- [ ] Git kullanıcı adı ve email ayarlandı mı?
  ```powershell
  git config --global user.name "Adınız"
  git config --global user.email "email@example.com"
  ```

### Repository Hazırlığı
- [ ] GitHub'da repository oluşturuldu mu?
- [ ] Remote repository eklendi mi? (`git remote -v`)
- [ ] `.gitignore` dosyası kontrol edildi mi?

### Değişiklikler
- [ ] Tüm değişiklikler kontrol edildi mi? (`git status`)
- [ ] Değişiklikler staging area'ya eklendi mi? (`git add .`)
- [ ] Commit mesajı anlamlı mı?
- [ ] Push yapıldı mı?

---

## 🛠️ Sorun Giderme

### "git: command not found" Hatası

Git kurulu değil. Yukarıdaki Git kurulum adımlarını takip edin.

### "fatal: not a git repository" Hatası

```powershell
git init
```

### "fatal: remote origin already exists" Hatası

```powershell
# Mevcut remote'u kaldır
git remote remove origin

# Yeni remote ekle
git remote add origin https://github.com/KULLANICI_ADI/REPO_ADI.git
```

### "error: failed to push some refs" Hatası

```powershell
# Önce pull yapın
git pull origin main --allow-unrelated-histories

# Sonra push yapın
git push -u origin main
```

---

## 📝 Önemli Notlar

1. **Veritabanı Dosyaları:** `.gitignore` dosyasında `db.sqlite3` ve database backup dosyaları ignore edilmiş. Production veritabanı GitHub'a yüklenmemeli.

2. **Environment Variables:** `.env` dosyası ignore edilmiş. Production environment variables GitHub'a yüklenmemeli.

3. **Migration Dosyaları:** Tüm migration dosyaları (`*.py`) GitHub'a yüklenmeli. Bu dosyalar veritabanı şeması değişikliklerini içerir.

4. **Virtual Environment:** `venv/` klasörü ignore edilmiş. Her geliştirici kendi virtual environment'ını oluşturmalı.

5. **Güvenlik:** Hassas bilgiler (API keys, passwords, vb.) GitHub'a yüklenmemeli. `.env` dosyası kullanın.

---

## ✅ Tamamlandı

Tüm adımlar tamamlandıktan sonra:

1. GitHub repository'nizi kontrol edin
2. Tüm dosyaların yüklendiğini doğrulayın
3. README.md dosyasını güncelleyin (opsiyonel)
4. Diğer geliştiricilere bilgi verin

---

**Son Güncelleme:** 2025-01-27





