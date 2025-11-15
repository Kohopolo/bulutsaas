# 🚀 GitHub Otomatik Yükleme Rehberi

**Tarih:** 2025-01-27  
**Durum:** Hazır

---

## ⚠️ ÖNEMLİ: Git Kurulumu Gerekli

Git kurulu değil. Önce Git'i kurmanız gerekiyor.

### Git Kurulumu

1. **Git'i İndirin:**
   - https://git-scm.com/download/win
   - İndirme işlemi başlatılacak

2. **Git'i Kurun:**
   - İndirilen `.exe` dosyasını çalıştırın
   - Kurulum sırasında varsayılan ayarları kullanabilirsiniz
   - "Add Git to PATH" seçeneğinin işaretli olduğundan emin olun

3. **PowerShell'i Yeniden Başlatın:**
   - Mevcut PowerShell penceresini kapatın
   - Yeni bir PowerShell penceresi açın

4. **Kurulumu Kontrol Edin:**
   ```powershell
   git --version
   ```

---

## 🤖 Otomatik Yükleme Scripti

Hazırladığım `github_push.ps1` scriptini kullanarak otomatik olarak GitHub'a yükleyebilirsiniz.

### Script Özellikleri

- ✅ Git kurulumunu kontrol eder
- ✅ Git kullanıcı bilgilerini kontrol eder ve ayarlar
- ✅ Git repository'yi başlatır (gerekirse)
- ✅ Remote repository ekler (gerekirse)
- ✅ Tüm değişiklikleri ekler
- ✅ Commit oluşturur
- ✅ GitHub'a push eder

### Scripti Çalıştırma

```powershell
cd C:\xampp\htdocs\bulutacente
.\github_push.ps1
```

### Script İçeriği

Script şu adımları otomatik olarak yapar:

1. Git kurulumunu kontrol eder
2. Git kullanıcı bilgilerini kontrol eder (yoksa sorar)
3. Git repository'yi başlatır (gerekirse)
4. Remote repository ekler (yoksa sorar)
5. Değişiklikleri kontrol eder
6. Tüm dosyaları staging area'ya ekler
7. Commit oluşturur
8. GitHub'a push eder

---

## 📋 Manuel Yükleme Adımları

Eğer scripti kullanmak istemiyorsanız, manuel olarak şu adımları takip edin:

### 1. Git Kurulumu (Yukarıdaki adımları takip edin)

### 2. Git Kullanıcı Bilgilerini Ayarlayın

```powershell
git config --global user.name "Adınız"
git config --global user.email "email@example.com"
```

### 3. Git Repository Başlatın

```powershell
cd C:\xampp\htdocs\bulutacente
git init
```

### 4. GitHub Repository Oluşturun

1. https://github.com/new adresine gidin
2. Repository adı: `bulutacente` (veya istediğiniz isim)
3. **Public** veya **Private** seçin
4. **README.md**, **.gitignore**, **license** eklemeyin (zaten var)
5. "Create repository" butonuna tıklayın

### 5. Remote Repository Ekleyin

```powershell
git remote add origin https://github.com/KULLANICI_ADI/REPO_ADI.git
```

**Örnek:**
```powershell
git remote add origin https://github.com/username/bulutacente.git
```

### 6. Tüm Dosyaları Ekleyin

```powershell
git add .
```

### 7. Commit Oluşturun

```powershell
git commit -m "PDF güvenlik güncellemesi ve Türkçe karakter düzeltmeleri

- xhtml2pdf güvenlik riski nedeniyle kaldırıldı
- WeasyPrint öncelikli PDF oluşturma sistemi eklendi
- ReportLab Türkçe font desteği eklendi
- Güvenli PDF utility fonksiyonu oluşturuldu
- Tüm PDF view'ları güncellendi
- Türkçe karakter desteği eklendi
- Voucher HTML formatı iyileştirildi
- requirements.txt güncellendi
- Tüm migrationlar eklendi"
```

### 8. GitHub'a Push Edin

```powershell
git push -u origin main
```

Veya `master` branch kullanıyorsanız:

```powershell
git push -u origin master
```

---

## ✅ Kontrol Listesi

### Git Kurulumu
- [ ] Git kurulu mu? (`git --version`)
- [ ] Git kullanıcı adı ayarlandı mı? (`git config --global user.name`)
- [ ] Git email ayarlandı mı? (`git config --global user.email`)

### Repository Hazırlığı
- [ ] GitHub'da repository oluşturuldu mu?
- [ ] Git repository başlatıldı mı? (`git init`)
- [ ] Remote repository eklendi mi? (`git remote -v`)
- [ ] `.gitignore` dosyası kontrol edildi mi?

### Yükleme
- [ ] Tüm dosyalar eklendi mi? (`git add .`)
- [ ] Commit oluşturuldu mu? (`git commit`)
- [ ] GitHub'a push edildi mi? (`git push`)

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
git remote remove origin
git remote add origin https://github.com/KULLANICI_ADI/REPO_ADI.git
```

### "error: failed to push some refs" Hatası

```powershell
git pull origin main --allow-unrelated-histories
git push -u origin main
```

### PowerShell Execution Policy Hatası

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

---

## 📝 Önemli Notlar

1. **Veritabanı Dosyaları:** `.gitignore` dosyasında `db.sqlite3` ve database backup dosyaları ignore edilmiş. Production veritabanı GitHub'a yüklenmemeli.

2. **Environment Variables:** `.env` dosyası ignore edilmiş. Production environment variables GitHub'a yüklenmemeli.

3. **Migration Dosyaları:** Tüm migration dosyaları (`*.py`) GitHub'a yüklenmeli. Bu dosyalar veritabanı şeması değişikliklerini içerir.

4. **Virtual Environment:** `venv/` klasörü ignore edilmiş. Her geliştirici kendi virtual environment'ını oluşturmalı.

5. **Güvenlik:** Hassas bilgiler (API keys, passwords, vb.) GitHub'a yüklenmemeli. `.env` dosyası kullanın.

---

## 🚀 Hızlı Başlangıç

1. Git'i kurun (yukarıdaki adımları takip edin)
2. PowerShell'i yeniden başlatın
3. Scripti çalıştırın:

```powershell
cd C:\xampp\htdocs\bulutacente
.\github_push.ps1
```

Script sizi adım adım yönlendirecek!

---

**Son Güncelleme:** 2025-01-27





