# 🚀 GitHub'a Yükleme Rehberi

## 📋 Ön Gereksinimler

### 1. Git Kurulumu

Git kurulu değilse, şu adımları izleyin:

**Windows için:**
1. https://git-scm.com/download/win adresinden Git'i indirin
2. Kurulumu tamamlayın
3. PowerShell veya CMD'yi yeniden başlatın

**Kurulumu kontrol etmek için:**
```powershell
git --version
```

### 2. GitHub Hesabı

- GitHub hesabınız yoksa: https://github.com/signup
- Yeni bir repository oluşturun: https://github.com/new

---

## 🔧 Projeyi GitHub'a Yükleme Adımları

### Adım 1: Git Repository Başlat

PowerShell veya CMD'de proje klasörüne gidin:

```powershell
cd C:\xampp\htdocs\bulutacente
```

Git repository'yi başlatın:

```powershell
git init
```

### Adım 2: Dosyaları Ekle

Tüm dosyaları staging area'ya ekleyin:

```powershell
git add .
```

### Adım 3: İlk Commit

İlk commit'i oluşturun:

```powershell
git commit -m "Initial commit: SaaS 2026 Multi-Tenant Otel/Tur Yönetim Sistemi"
```

### Adım 4: GitHub Repository Oluştur

1. https://github.com/new adresine gidin
2. Repository adı: `bulutacente` veya `saas2026` (istediğiniz isim)
3. **Public** veya **Private** seçin
4. **Initialize this repository with a README** seçeneğini **İŞARETLEMEYİN**
5. **Create repository** butonuna tıklayın

### Adım 5: Remote Repository Ekle

GitHub'da oluşturduğunuz repository'nin URL'sini kopyalayın (örn: `https://github.com/kullaniciadi/bulutacente.git`)

PowerShell'de:

```powershell
git remote add origin https://github.com/KULLANICI_ADI/REPO_ADI.git
```

**Örnek:**
```powershell
git remote add origin https://github.com/username/bulutacente.git
```

### Adım 6: Branch Adını Değiştir (Opsiyonel)

GitHub varsayılan olarak `main` branch kullanır:

```powershell
git branch -M main
```

### Adım 7: GitHub'a Push Et

```powershell
git push -u origin main
```

GitHub kullanıcı adı ve şifreniz istenecek. (Personal Access Token kullanmanız gerekebilir)

---

## 🔐 Personal Access Token (PAT) Oluşturma

GitHub artık şifre yerine Personal Access Token kullanıyor:

1. https://github.com/settings/tokens adresine gidin
2. **Generate new token** → **Generate new token (classic)** seçin
3. Token adı: `bulutacente-upload`
4. Süre: `90 days` veya `No expiration`
5. İzinler: `repo` seçeneğini işaretleyin
6. **Generate token** butonuna tıklayın
7. Token'ı kopyalayın (bir daha gösterilmeyecek!)

**Push yaparken şifre yerine bu token'ı kullanın.**

---

## 📝 Sonraki Commit'ler İçin

Değişiklik yaptıktan sonra:

```powershell
# Değişiklikleri kontrol et
git status

# Değişiklikleri ekle
git add .

# Commit oluştur
git commit -m "Açıklayıcı commit mesajı"

# GitHub'a gönder
git push
```

---

## 🎯 Örnek Commit Mesajları

```powershell
git commit -m "Tablo stilleri ve filtreleme sistemi eklendi"
git commit -m "Toolbar tasarımı güncellendi"
git commit -m "Otomatik tablo filtreleme özelliği eklendi"
git commit -m "CSS güncellemeleri: referans görüntüye uygun stil"
```

---

## ⚠️ Önemli Notlar

1. **`.env` dosyası `.gitignore`'da** - Hassas bilgiler GitHub'a yüklenmeyecek
2. **`venv/` klasörü yüklenmeyecek** - Virtual environment GitHub'a yüklenmez
3. **`staticfiles/` yüklenmeyecek** - Django collectstatic ile oluşturulan dosyalar
4. **Database backup dosyaları yüklenmeyecek**

---

## 🔍 Repository Durumunu Kontrol Etme

```powershell
# Değişiklikleri göster
git status

# Commit geçmişini göster
git log --oneline

# Remote repository'yi kontrol et
git remote -v
```

---

## 🆘 Sorun Giderme

### "git: command not found"
- Git kurulu değil, yukarıdaki kurulum adımlarını izleyin

### "remote origin already exists"
```powershell
git remote remove origin
git remote add origin https://github.com/KULLANICI_ADI/REPO_ADI.git
```

### "Authentication failed"
- Personal Access Token kullanın, şifre değil
- Token'ın `repo` izni olduğundan emin olun

### "Permission denied"
- GitHub hesabınızın repository'ye erişim izni olduğundan emin olun

---

## 📚 Ek Kaynaklar

- Git Dokümantasyon: https://git-scm.com/doc
- GitHub Guides: https://guides.github.com
- Git Cheat Sheet: https://education.github.com/git-cheat-sheet-education.pdf

---

**🎉 Başarılar! Projeniz GitHub'da!**

