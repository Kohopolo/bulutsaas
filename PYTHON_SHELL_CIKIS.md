# Python Shell'den Çıkış

## ⚠️ Sorun: Python Shell'desiniz

Şu anda Python shell'inde (`>>>` işareti görünüyor). `nginx -t` komutu Python komutu değil, bash komutudur.

---

## ✅ ÇÖZÜM: Python Shell'den Çıkın

### ADIM 1: Python Shell'den Çıkın

**Şunu yazın:**
```
exit()
```

**Veya:**
```
quit()
```

**Ve Enter'a basın.**

**Beklenen:** Komut satırına döneceksiniz:
```
root@srv1132080:~#
```

---

## ✅ ADIM 2: Nginx Komutunu Çalıştırın

Python shell'den çıktıktan sonra:

```bash
nginx -t
```

**Beklenen Çıktı:**
```
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

---

## 🔍 Python Shell vs Bash Shell

### Python Shell (`>>>`):
- Python komutları çalıştırır
- Django shell: `python manage.py shell`
- Çıkmak için: `exit()` veya `quit()`

### Bash Shell (`$` veya `#`):
- Linux komutları çalıştırır
- `nginx`, `docker`, `ls` gibi komutlar
- Çıkmak için: `exit` veya `Ctrl+D`

---

## 📋 Python Shell'den Çıkış Yöntemleri

### Yöntem 1: exit() Fonksiyonu
```
exit()
```

### Yöntem 2: quit() Fonksiyonu
```
quit()
```

### Yöntem 3: Ctrl+D
- **Ctrl+D** tuşlarına basın (Linux/Mac)
- Windows'ta çalışmayabilir

---

## ✅ Sonuç

**Yapılacaklar:**

1. ✅ Python shell'den çıkın: `exit()` yazın ve Enter'a basın
2. ✅ Komut satırına döneceksiniz: `root@srv1132080:~#`
3. ✅ Nginx komutunu çalıştırın: `nginx -t`

**Başarılar! 🚀**

