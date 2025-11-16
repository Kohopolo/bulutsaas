# Nginx Servis Başlatma

## ⚠️ Sorun: Nginx Servisi Aktif Değil

```
nginx.service is not active, cannot reload.
```

Nginx konfigürasyonu doğru ama servis çalışmıyor. Servisi başlatmanız gerekiyor.

---

## ✅ ÇÖZÜM: Nginx Servisini Başlatma

### ADIM 1: Nginx Servisini Başlatın

```bash
systemctl start nginx
```

### ADIM 2: Nginx Servisini Otomatik Başlatmayı Etkinleştirin

```bash
systemctl enable nginx
```

### ADIM 3: Nginx Servis Durumunu Kontrol Edin

```bash
systemctl status nginx
```

**Beklenen Çıktı:**
```
● nginx.service - A high performance web server and a reverse proxy server
     Loaded: loaded (/lib/systemd/system/nginx.service; enabled; vendor preset: enabled)
     Active: active (running) since ...
```

---

## ✅ ADIM 4: Nginx'i Reload Edin (Artık Çalışıyor)

```bash
systemctl reload nginx
```

**Veya restart edin:**

```bash
systemctl restart nginx
```

---

## 🔍 Nginx Servis Komutları

### Servisi Başlatma:

```bash
systemctl start nginx
```

### Servisi Durdurma:

```bash
systemctl stop nginx
```

### Servisi Yeniden Başlatma:

```bash
systemctl restart nginx
```

### Servisi Reload Etme (Yeniden yükleme):

```bash
systemctl reload nginx
```

### Servisi Otomatik Başlatmayı Etkinleştirme:

```bash
systemctl enable nginx
```

### Servis Durumunu Kontrol Etme:

```bash
systemctl status nginx
```

---

## 📋 Kontrol Listesi

### Nginx Kurulumu:
- [ ] Nginx kurulu (`apt install -y nginx`)
- [ ] Nginx konfigürasyonu doğru (`nginx -t` → OK)
- [ ] Symbolic link oluşturuldu (`ln -s`)
- [ ] Nginx servisi başlatıldı (`systemctl start nginx`)
- [ ] Nginx servisi etkinleştirildi (`systemctl enable nginx`)
- [ ] Nginx çalışıyor (`systemctl status nginx` → active)

---

## ✅ Sonuç

**Yapılacaklar:**

1. ✅ Nginx servisini başlatın: `systemctl start nginx`
2. ✅ Otomatik başlatmayı etkinleştirin: `systemctl enable nginx`
3. ✅ Servis durumunu kontrol edin: `systemctl status nginx`
4. ✅ Nginx'i reload edin: `systemctl reload nginx`

**Başarılar! 🚀**

