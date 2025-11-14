# GitHub'a Yükleme Scripti
# PowerShell Script - Otomatik GitHub Push

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "GitHub'a Yükleme İşlemi Başlatılıyor..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Git kurulumunu kontrol et
Write-Host "[1/8] Git kurulumu kontrol ediliyor..." -ForegroundColor Yellow
try {
    $gitVersion = git --version 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Git bulunamadı"
    }
    Write-Host "✓ Git kurulu: $gitVersion" -ForegroundColor Green
} catch {
    Write-Host "✗ Git kurulu değil!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Lütfen önce Git'i kurun:" -ForegroundColor Yellow
    Write-Host "1. https://git-scm.com/download/win adresinden Git'i indirin" -ForegroundColor Yellow
    Write-Host "2. Kurulumu tamamlayın" -ForegroundColor Yellow
    Write-Host "3. PowerShell'i yeniden başlatın" -ForegroundColor Yellow
    Write-Host "4. Bu scripti tekrar çalıştırın" -ForegroundColor Yellow
    exit 1
}

# Git kullanıcı bilgilerini kontrol et
Write-Host ""
Write-Host "[2/8] Git kullanıcı bilgileri kontrol ediliyor..." -ForegroundColor Yellow
$userName = git config --global user.name 2>&1
$userEmail = git config --global user.email 2>&1

if (-not $userName -or $userName -eq "") {
    Write-Host "⚠ Git kullanıcı adı ayarlanmamış" -ForegroundColor Yellow
    $userName = Read-Host "Git kullanıcı adınızı girin"
    git config --global user.name $userName
    Write-Host "✓ Git kullanıcı adı ayarlandı: $userName" -ForegroundColor Green
} else {
    Write-Host "✓ Git kullanıcı adı: $userName" -ForegroundColor Green
}

if (-not $userEmail -or $userEmail -eq "") {
    Write-Host "⚠ Git email ayarlanmamış" -ForegroundColor Yellow
    $userEmail = Read-Host "Git email adresinizi girin"
    git config --global user.email $userEmail
    Write-Host "✓ Git email ayarlandı: $userEmail" -ForegroundColor Green
} else {
    Write-Host "✓ Git email: $userEmail" -ForegroundColor Green
}

# Git repository durumunu kontrol et
Write-Host ""
Write-Host "[3/8] Git repository durumu kontrol ediliyor..." -ForegroundColor Yellow
if (-not (Test-Path ".git")) {
    Write-Host "⚠ Git repository başlatılmamış, başlatılıyor..." -ForegroundColor Yellow
    git init
    Write-Host "✓ Git repository başlatıldı" -ForegroundColor Green
} else {
    Write-Host "✓ Git repository mevcut" -ForegroundColor Green
}

# Remote repository kontrolü
Write-Host ""
Write-Host "[4/8] Remote repository kontrol ediliyor..." -ForegroundColor Yellow
$remoteUrl = git remote get-url origin 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "⚠ Remote repository ayarlanmamış" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "GitHub repository URL'inizi girin:" -ForegroundColor Yellow
    Write-Host "Örnek: https://github.com/KULLANICI_ADI/REPO_ADI.git" -ForegroundColor Gray
    $repoUrl = Read-Host "Repository URL"
    
    if ($repoUrl) {
        git remote add origin $repoUrl
        Write-Host "✓ Remote repository eklendi: $repoUrl" -ForegroundColor Green
    } else {
        Write-Host "✗ Repository URL girilmedi, işlem iptal edildi" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "✓ Remote repository: $remoteUrl" -ForegroundColor Green
}

# Değişiklikleri kontrol et
Write-Host ""
Write-Host "[5/8] Değişiklikler kontrol ediliyor..." -ForegroundColor Yellow
$status = git status --short
if ($status) {
    Write-Host "Değişiklikler:" -ForegroundColor Cyan
    git status --short
} else {
    Write-Host "⚠ Yeni değişiklik bulunamadı" -ForegroundColor Yellow
    Write-Host "Tüm dosyalar ekleniyor..." -ForegroundColor Yellow
}

# Tüm dosyaları ekle
Write-Host ""
Write-Host "[6/8] Dosyalar staging area'ya ekleniyor..." -ForegroundColor Yellow
git add .
Write-Host "✓ Tüm dosyalar eklendi" -ForegroundColor Green

# Commit oluştur
Write-Host ""
Write-Host "[7/8] Commit oluşturuluyor..." -ForegroundColor Yellow
$commitMessage = @"
PDF güvenlik güncellemesi ve Türkçe karakter düzeltmeleri

- xhtml2pdf güvenlik riski nedeniyle kaldırıldı
- WeasyPrint öncelikli PDF oluşturma sistemi eklendi
- ReportLab Türkçe font desteği eklendi
- Güvenli PDF utility fonksiyonu oluşturuldu (apps/tenant_apps/core/pdf_utils.py)
- Tüm PDF view'ları güncellendi (ferry_tickets, reception, bungalovs)
- Türkçe karakter desteği eklendi (UTF-8 encoding garantisi)
- Voucher HTML formatı iyileştirildi
- requirements.txt güncellendi
- Tüm migrationlar eklendi
- Ferry tickets modülü tamamlandı
- Detay sayfası düzenle butonu düzeltildi
- PDF indirme linkleri düzeltildi (direkt indirme)
"@

git commit -m $commitMessage
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Commit oluşturuldu" -ForegroundColor Green
} else {
    Write-Host "⚠ Commit oluşturulamadı (değişiklik yok olabilir)" -ForegroundColor Yellow
}

# GitHub'a push et
Write-Host ""
Write-Host "[8/8] GitHub'a push ediliyor..." -ForegroundColor Yellow
Write-Host "Bu işlem biraz zaman alabilir..." -ForegroundColor Gray

# Branch kontrolü
$currentBranch = git branch --show-current 2>&1
if (-not $currentBranch) {
    $currentBranch = "main"
    git branch -M main
}

try {
    git push -u origin $currentBranch 2>&1 | ForEach-Object {
        Write-Host $_ -ForegroundColor Gray
    }
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Green
        Write-Host "✓ Başarıyla GitHub'a yüklendi!" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Green
        Write-Host ""
        Write-Host "Repository URL: $remoteUrl" -ForegroundColor Cyan
        Write-Host "Branch: $currentBranch" -ForegroundColor Cyan
    } else {
        Write-Host ""
        Write-Host "⚠ Push işlemi başarısız oldu" -ForegroundColor Yellow
        Write-Host "Hata mesajını kontrol edin" -ForegroundColor Yellow
    }
} catch {
    Write-Host ""
    Write-Host "✗ Push işlemi sırasında hata oluştu: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Manuel olarak deneyin:" -ForegroundColor Yellow
    Write-Host "git push -u origin $currentBranch" -ForegroundColor Gray
}

Write-Host ""
Write-Host "İşlem tamamlandı!" -ForegroundColor Cyan

