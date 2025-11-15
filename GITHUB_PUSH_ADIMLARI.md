# 🚀 GitHub'a Push Adımları

## ✅ Tamamlanan İşlemler

- ✅ Git repository başlatıldı
- ✅ Tüm dosyalar eklendi (747 dosya)
- ✅ İlk commit oluşturuldu

## 📋 Sonraki Adımlar

### 1. GitHub'da Repository Oluşturun

1. https://github.com/new adresine gidin
2. Repository adı: `bulutacente` veya istediğiniz isim
3. **Public** veya **Private** seçin
4. **Initialize this repository with a README** seçeneğini **İŞARETLEMEYİN**
5. **Create repository** butonuna tıklayın

### 2. Remote Repository Ekleyin

PowerShell'de şu komutu çalıştırın (KULLANICI_ADI ve REPO_ADI değiştirin):

```powershell
$env:PATH += ";C:\Program Files\Git\bin"
git remote add origin https://github.com/KULLANICI_ADI/REPO_ADI.git
```

**Örnek:**
```powershell
git remote add origin https://github.com/username/bulutacente.git
```

### 3. GitHub'a Push Edin

```powershell
$env:PATH += ";C:\Program Files\Git\bin"
git push -u origin main
```

**Not:** GitHub kullanıcı adı ve Personal Access Token isteyecek.

---

## 🔐 Personal Access Token Oluşturma

1. https://github.com/settings/tokens adresine gidin
2. **Generate new token** → **Generate new token (classic)**
3. Token adı: `bulutacente-upload`
4. Süre: `90 days` veya `No expiration`
5. İzinler: `repo` seçeneğini işaretleyin
6. **Generate token** butonuna tıklayın
7. Token'ı kopyalayın (bir daha gösterilmeyecek!)

**Push yaparken şifre yerine bu token'ı kullanın.**

---

## ✅ Hızlı Komutlar

```powershell
# PATH'e Git ekle
$env:PATH += ";C:\Program Files\Git\bin"

# Remote ekle (KULLANICI_ADI ve REPO_ADI değiştirin)
git remote add origin https://github.com/KULLANICI_ADI/REPO_ADI.git

# Push et
git push -u origin main
```

---

## 🆘 Sorun Giderme

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

**🎉 Başarılar! Projeniz GitHub'da olacak!**





