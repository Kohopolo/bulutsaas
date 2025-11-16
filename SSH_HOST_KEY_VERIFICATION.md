# SSH Host Key Verification Sorunu Çözümü

## ⚠️ Sorun: Host Key Verification Failed

SSH bağlantısı sırasında host key verification hatası alıyorsunuz.

---

## ✅ Çözüm 1: Host Key'i Onaylama (ÖNERİLEN)

### Adım 1: Host Key'i Onaylayın

SSH bağlantısı sırasında soruya **yes** yazın:

```bash
ssh root@72.62.35.155
```

**Çıktı:**
```
The authenticity of host '72.62.35.155 (72.62.35.155)' can't be established.
ED25519 key fingerprint is SHA256:sXLt5R1xRTLyYbnV9B4gmNw4lVzzm+9GhYsHPqA0eJA.
Are you sure you want to continue connecting (yes/no/[fingerprint])?
```

**Cevap:** `yes` yazın ve Enter'a basın

---

## ✅ Çözüm 2: Host Key'i Manuel Ekleme

### Adım 1: Host Key'i Manuel Olarak Ekleyin

```bash
# Host key'i manuel olarak ekle
ssh-keyscan -H 72.62.35.155 >> ~/.ssh/known_hosts

# Bağlantıyı test et
ssh root@72.62.35.155
```

---

## ✅ Çözüm 3: Host Key Verification'ı Geçici Olarak Devre Dışı Bırakma (GÜVENSİZ)

**⚠️ Uyarı:** Bu yöntem güvenlik riski oluşturabilir! Sadece test için kullanın.

```bash
# Host key verification'ı geçici olarak devre dışı bırak
ssh -o StrictHostKeyChecking=no root@72.62.35.155

# Veya known_hosts dosyasını kontrol etmeden bağlan
ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no root@72.62.35.155
```

---

## 🔍 Host Key'i Kontrol Etme

### Host Key'i Görüntüleme:

```bash
# Host key'i görüntüle
ssh-keyscan -H 72.62.35.155

# Host key fingerprint'ini görüntüle
ssh-keyscan -H 72.62.35.155 | ssh-keygen -lf -
```

---

## 📋 Kontrol Listesi

### SSH Bağlantısı:
- [ ] Host key'i onaylandı (`yes` yazıldı)
- [ ] SSH bağlantısı başarılı (`ssh root@72.62.35.155`)
- [ ] Host key `~/.ssh/known_hosts` dosyasına eklendi

---

## ✅ Önerilen Adımlar

### 1. Host Key'i Onaylayın

SSH bağlantısı sırasında:
```bash
ssh root@72.62.35.155
```

Sorulduğunda `yes` yazın ve Enter'a basın.

### 2. Bağlantıyı Test Edin

```bash
# Bağlantıyı test et
ssh root@72.62.35.155

# Bağlantı başarılı olmalı
```

---

## 🚀 Sonuç

**Host Key Verification Sorunu:**

1. SSH bağlantısı sırasında `yes` yazın
2. Host key otomatik olarak `~/.ssh/known_hosts` dosyasına eklenir
3. Bir sonraki bağlantıda sorun olmaz

**Başarılar! 🎉**

