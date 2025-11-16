# Nginx Konfigürasyon Dosyası Yapıştırma

## 📝 Nano Editöründe Nginx Config Dosyası

Nano editöründe `/etc/nginx/sites-available/bulutsaas` dosyası açık ve boş. Şimdi Nginx konfigürasyonunu yapıştırmanız gerekiyor.

---

## ✅ Adım Adım Yapılacaklar

### ADIM 1: Nginx Konfigürasyonunu Yapıştırın

**Aşağıdaki içeriği kopyalayın ve nano editörüne yapıştırın:**

```nginx
upstream django {
    server 127.0.0.1:8000;
    keepalive 64;
}

server {
    listen 80;
    server_name bulutacente.com.tr www.bulutacente.com.tr 72.62.35.155;
    client_max_body_size 50M;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Static files (Docker volume'dan)
    location /static/ {
        alias /var/www/bulutsaas/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Media files (Docker volume'dan)
    location /media/ {
        alias /var/www/bulutsaas/media/;
        expires 7d;
    }

    # Django application
    location / {
        proxy_pass http://django;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_redirect off;
        
        # Timeout settings
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

---

## 📋 Nano Editöründe Yapıştırma

### Windows'tan Yapıştırma:

1. **İçeriği kopyalayın** (yukarıdaki tüm Nginx config'i seçin ve Ctrl+C)

2. **Nano editöründe:**
   - **Sağ tıklayın** (terminal penceresinde)
   - Veya **Shift+Insert** tuşlarına basın
   - Veya **Ctrl+Shift+V** tuşlarına basın

3. **İçerik yapıştırılacak**

---

## 💾 ADIM 2: Dosyayı Kaydetme

### Dosyayı Kaydetme:

1. **Ctrl+O** tuşlarına basın (Write Out)
2. **Enter** tuşuna basın (dosya adını onayla)
3. **"Wrote X lines"** mesajını göreceksiniz

### Editörden Çıkma:

1. **Ctrl+X** tuşlarına basın (Exit)
2. **Eğer değişiklik varsa:** "Save modified buffer?" sorusu sorulabilir
   - **Y** yazın ve Enter'a basın (kaydetmek için)

---

## ✅ ADIM 3: Nginx Konfigürasyonunu Test Etme

Dosyayı kaydettikten sonra:

```bash
nginx -t
```

**Beklenen Çıktı:**
```
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

---

## ✅ ADIM 4: Site'ı Aktif Etme

```bash
ln -s /etc/nginx/sites-available/bulutsaas /etc/nginx/sites-enabled/
```

```bash
rm /etc/nginx/sites-enabled/default
```

```bash
systemctl reload nginx
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

1. ✅ Nginx konfigürasyonunu yapıştırın (yukarıdaki içerik)
2. ✅ Ctrl+O ile kaydedin
3. ✅ Ctrl+X ile çıkın
4. ✅ `nginx -t` ile test edin
5. ✅ Site'ı aktif edin ve Nginx'i reload edin

**Başarılar! 🚀**

