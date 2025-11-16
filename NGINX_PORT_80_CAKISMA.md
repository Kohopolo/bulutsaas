# Nginx Port 80 Çakışması Çözümü

## ⚠️ Sorun: Port 80 Zaten Kullanılıyor

```
nginx: [emerg] bind() to 0.0.0.0:80 failed (98: Address already in use)
```

Port 80 zaten başka bir servis tarafından kullanılıyor. Bu servisi bulup durdurmamız gerekiyor.

---

## ✅ ADIM 1: Port 80'i Kullanan Servisi Bulun

### Port 80'i Kullanan Servisi Kontrol Edin:

```bash
netstat -tlnp | grep :80
```

**Veya:**

```bash
lsof -i :80
```

**Veya:**

```bash
ss -tlnp | grep :80
```

**Beklenen Çıktı:**
```
tcp        0      0 0.0.0.0:80              0.0.0.0:*               LISTEN      1234/apache2
```

Veya:
```
tcp        0      0 0.0.0.0:80              0.0.0.0:*               LISTEN      5678/litespeed
```

---

## ✅ ADIM 2: Servisi Durdurun

### Apache Kullanıyorsa:

```bash
systemctl stop apache2
```

```bash
systemctl disable apache2
```

### OpenLiteSpeed Kullanıyorsa:

```bash
systemctl stop lsws
```

```bash
systemctl disable lsws
```

### Başka Bir Servis Kullanıyorsa:

**Servis adını bulun:**
```bash
systemctl list-units --type=service --state=running | grep -E "apache|httpd|litespeed|nginx"
```

**Servisi durdurun:**
```bash
systemctl stop SERVIS_ADI
```

---

## ✅ ADIM 3: Nginx'i Tekrar Başlatın

```bash
systemctl start nginx
```

```bash
systemctl status nginx
```

**Beklenen:** Nginx başarıyla başlamalı

---

## 🔍 Olası Servisler

### Apache2:

```bash
# Apache durumunu kontrol et
systemctl status apache2

# Apache'i durdur
systemctl stop apache2
systemctl disable apache2
```

### OpenLiteSpeed:

```bash
# OpenLiteSpeed durumunu kontrol et
systemctl status lsws

# OpenLiteSpeed'i durdur
systemctl stop lsws
systemctl disable lsws
```

### Eski Nginx Process:

```bash
# Eski Nginx process'lerini kontrol et
ps aux | grep nginx

# Eski process'leri öldür
pkill nginx
```

---

## 📋 Kontrol Listesi

### Port Kontrolü:
- [ ] Port 80'i kullanan servis bulundu (`netstat -tlnp | grep :80`)
- [ ] Servis durduruldu (`systemctl stop SERVIS_ADI`)
- [ ] Servis otomatik başlatma devre dışı bırakıldı (`systemctl disable SERVIS_ADI`)
- [ ] Port 80 boş (`netstat -tlnp | grep :80` → boş olmalı)

### Nginx Başlatma:
- [ ] Nginx başlatıldı (`systemctl start nginx`)
- [ ] Nginx çalışıyor (`systemctl status nginx` → active)

---

## ✅ Hızlı Çözüm Komutları

```bash
# Port 80'i kullanan servisi bul
netstat -tlnp | grep :80

# Apache varsa durdur
systemctl stop apache2
systemctl disable apache2

# OpenLiteSpeed varsa durdur
systemctl stop lsws
systemctl disable lsws

# Nginx'i başlat
systemctl start nginx
systemctl status nginx
```

---

## ✅ Sonuç

**Yapılacaklar:**

1. ✅ Port 80'i kullanan servisi bulun: `netstat -tlnp | grep :80`
2. ✅ Servisi durdurun: `systemctl stop SERVIS_ADI`
3. ✅ Otomatik başlatmayı devre dışı bırakın: `systemctl disable SERVIS_ADI`
4. ✅ Nginx'i başlatın: `systemctl start nginx`
5. ✅ Durumu kontrol edin: `systemctl status nginx`

**Önce `netstat -tlnp | grep :80` komutunu çalıştırın ve çıktıyı paylaşın!**

**Başarılar! 🚀**

