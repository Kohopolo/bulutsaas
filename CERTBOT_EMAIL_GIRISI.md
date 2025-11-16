# Certbot Email Girişi

## 📧 SSL Sertifikası İçin Email Adresi

Certbot SSL sertifikası oluştururken email adresi istiyor:

```
Enter email address (used for urgent renewal and security notices)
 (Enter 'c' to cancel):
```

---

## ✅ Yapılacaklar

### ADIM 1: Email Adresinizi Girin

**Email adresinizi yazın ve Enter'a basın:**

Örnek:
```
your-email@example.com
```

**Enter'a basın**

---

## 📋 Certbot Soruları ve Cevapları

### Soru 1: Email Adresi
```
Enter email address (used for urgent renewal and security notices)
```
**Cevap:** Email adresinizi yazın ve Enter'a basın

### Soru 2: Terms of Service
```
Please read the Terms of Service at
https://letsencrypt.org/documents/LE-SA-v1.3-September-21-2022.pdf. You must
agree in order to register with the ACME server at
https://acme-v02.api.letsencrypt.org/directory
(A)gree/(C)ancel:
```
**Cevap:** `A` yazın ve Enter'a basın (Agree)

### Soru 3: Email Paylaşımı (Opsiyonel)
```
Would you be willing, once your first certificate is successfully issued, to
share your email address with the Electronic Frontier Foundation, a founding
partner of the Let's Encrypt project and the non-profit organization that
develops Certbot? We'd like to send you email about our work encrypting the web,
EFF news, campaigns, and ways to support digital freedom.
(Y)es/(N)o:
```
**Cevap:** `Y` veya `N` yazın ve Enter'a basın (opsiyonel)

### Soru 4: HTTP'den HTTPS'e Yönlendirme
```
Please choose whether or not to redirect HTTP traffic to HTTPS, removing HTTP access.
-------------------------------------------------------------------------------
1: No redirect - Make no further changes to the webserver configuration.
2: Redirect - Make all requests redirect to secure HTTPS access. Select this for
new sites, or if you're confident your site works on HTTPS. You can undo this
change by editing your web server's configuration.
-------------------------------------------------------------------------------
Select the appropriate number [1-2] then [enter] (press 'c' to cancel):
```
**Cevap:** `2` yazın ve Enter'a basın (HTTPS'e yönlendirme)

---

## ✅ Beklenen Sonuç

SSL sertifikası başarıyla oluşturulduktan sonra:

```
Successfully received certificate.
Certificate is saved at: /etc/letsencrypt/live/bulutacente.com.tr/fullchain.pem
Key is saved at:         /etc/letsencrypt/live/bulutacente.com.tr/privkey.pem
This certificate expires on 2024-XX-XX.
```

---

## ✅ Sonuç

**Yapılacaklar:**

1. ✅ Email adresinizi girin ve Enter'a basın
2. ✅ Terms of Service'i kabul edin (`A` yazın)
3. ✅ Email paylaşımı için `Y` veya `N` yazın (opsiyonel)
4. ✅ HTTP'den HTTPS'e yönlendirme için `2` yazın

**Başarılar! 🚀**

