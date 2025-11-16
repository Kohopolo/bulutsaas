# Nano Editöründe .env Dosyası Yapıştırma Rehberi

## 📝 Nano Editöründe .env Dosyası Oluşturma

Nano editöründe `.env` dosyası açık ve boş. Şimdi içeriği yapıştırmanız gerekiyor.

---

## ✅ Adım Adım Yapılacaklar

### ADIM 1: İçeriği Yapıştırın

**Aşağıdaki içeriği kopyalayın ve nano editörüne yapıştırın:**

```env
# Django Settings
DEBUG=False
SECRET_KEY=GÜÇLÜ_SECRET_KEY_BURAYA_OLUŞTURUN
ALLOWED_HOSTS=bulutacente.com.tr,www.bulutacente.com.tr,72.62.35.155,localhost,127.0.0.1

# Database (Docker container içindeki PostgreSQL)
DATABASE_URL=postgresql://saas_user:saas_password_2026@db:5432/saas_db
POSTGRES_DB=saas_db
POSTGRES_USER=saas_user
POSTGRES_PASSWORD=saas_password_2026

# Redis (Docker container içindeki Redis)
REDIS_URL=redis://redis:6379/0
CELERY_BROKER_URL=redis://redis:6379/0
CELERY_RESULT_BACKEND=redis://redis:6379/0

# Site URL
SITE_URL=https://bulutacente.com.tr

# Static ve Media
STATIC_ROOT=/app/staticfiles
MEDIA_ROOT=/app/media

# Email (Opsiyonel)
EMAIL_HOST=smtp.hostinger.com
EMAIL_PORT=465
EMAIL_USE_SSL=True
EMAIL_HOST_USER=noreply@bulutacente.com.tr
EMAIL_HOST_PASSWORD=EMAIL_ŞİFRE_BURAYA
DEFAULT_FROM_EMAIL=noreply@bulutacente.com.tr

# Digital Ocean DNS (Opsiyonel)
DO_API_TOKEN=your_digital_ocean_api_token
DO_DOMAIN=bulutacente.com.tr
DO_DROPLET_IP=72.62.35.155
```

---

## 📋 Nano Editöründe Yapıştırma

### Windows'tan Yapıştırma:

1. **İçeriği kopyalayın** (yukarıdaki tüm metni seçin ve Ctrl+C)

2. **Nano editöründe:**
   - **Sağ tıklayın** (terminal penceresinde)
   - Veya **Shift+Insert** tuşlarına basın
   - Veya **Ctrl+Shift+V** tuşlarına basın

3. **İçerik yapıştırılacak**

---

## 🔐 ADIM 2: SECRET_KEY Oluşturma

**ÖNEMLİ:** `SECRET_KEY=GÜÇLÜ_SECRET_KEY_BURAYA_OLUŞTURUN` satırını değiştirmeniz gerekiyor!

### Yöntem 1: Nano'dan Çıkıp Secret Key Oluşturma

1. **Önce dosyayı kaydedin:**
   - `Ctrl+O` (Write Out - Kaydet)
   - `Enter` (dosya adını onayla)
   - `Ctrl+X` (Exit - Çık)

2. **Secret key oluşturun:**
   ```bash
   python3 -c "import secrets; print(secrets.token_urlsafe(50))"
   ```

3. **Çıktıyı kopyalayın**

4. **Dosyayı tekrar açın:**
   ```bash
   nano .env
   ```

5. **SECRET_KEY satırını bulun** (`Ctrl+W` ile arama yapabilirsiniz)

6. **SECRET_KEY satırını düzenleyin:**
   - `GÜÇLÜ_SECRET_KEY_BURAYA_OLUŞTURUN` kısmını silin
   - Oluşturduğunuz secret key'i yapıştırın

---

## 💾 ADIM 3: Dosyayı Kaydetme ve Çıkma

### Dosyayı Kaydetme:

1. **Ctrl+O** tuşlarına basın (Write Out)
2. **Enter** tuşuna basın (dosya adını onayla)
3. **"Wrote X lines"** mesajını göreceksiniz

### Editörden Çıkma:

1. **Ctrl+X** tuşlarına basın (Exit)
2. **Eğer değişiklik varsa:** "Save modified buffer?" sorusu sorulabilir
   - **Y** yazın ve Enter'a basın (kaydetmek için)

---

## ✅ Kontrol

Dosyayı kaydettikten sonra kontrol edin:

```bash
cat .env
```

**Beklenen:** Tüm environment variables görünmeli

---

## 🔑 Secret Key Oluşturma (Alternatif)

Eğer Python yoksa, başka bir yöntem:

```bash
openssl rand -base64 50
```

Veya:

```bash
head -c 50 /dev/urandom | base64
```

---

## 📝 Nano Kısayolları

- **Ctrl+O** - Dosyayı kaydet (Write Out)
- **Ctrl+X** - Çık (Exit)
- **Ctrl+W** - Arama yap (Where Is)
- **Ctrl+K** - Satırı kes (Cut)
- **Ctrl+U** - Yapıştır (Paste)
- **Ctrl+G** - Yardım (Help)

---

## ✅ Sonuç

**Yapılacaklar:**

1. ✅ İçeriği yapıştırın (yukarıdaki .env içeriği)
2. ✅ SECRET_KEY oluşturun ve değiştirin
3. ✅ Ctrl+O ile kaydedin
4. ✅ Ctrl+X ile çıkın

**Başarılar! 🚀**

