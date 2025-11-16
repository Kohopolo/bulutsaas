# Hostinger VPS SSH Bağlantı Rehberi

## 🔐 Hostinger VPS'e SSH ile Bağlanma

Hostinger VPS'inize SSH ile bağlanmak için gerekli bilgiler ve adımlar.

---

## 📋 Gerekli Bilgiler

### Bağlantı Bilgileri:

- **VPS IP Adresi**: `72.62.35.155` (veya `88.255.216.16`)
- **Port**: `22` (varsayılan SSH portu)
- **Kullanıcı Adı**: `root` (veya Hostinger'in verdiği kullanıcı)
- **Şifre**: Hostinger panelinden aldığınız şifre
- **Veya SSH Key**: Eğer SSH key kullanıyorsanız

---

## ✅ Yöntem 1: Windows'tan SSH Bağlantısı

### Adım 1: PowerShell veya Command Prompt Açın

**Windows 10/11:**
- `Win + R` → `powershell` veya `cmd` yazın
- Enter'a basın

### Adım 2: SSH Bağlantısı

```bash
ssh root@72.62.35.155
```

**Veya:**
```bash
ssh root@88.255.216.16
```

### Adım 3: Şifre Girin

İlk bağlantıda şu uyarıyı göreceksiniz:
```
The authenticity of host '72.62.35.155' can't be established.
Are you sure you want to continue connecting (yes/no)?
```

**`yes`** yazın ve Enter'a basın.

### Adım 4: Şifre Girin

Hostinger panelinden aldığınız şifreyi girin (görünmez, normal).

---

## ✅ Yöntem 2: PuTTY ile Bağlantı (Windows)

### Adım 1: PuTTY İndirin

1. **PuTTY'yi indirin**: https://www.putty.org/
2. **PuTTY'yi açın**

### Adım 2: Bağlantı Ayarları

1. **Host Name**: `72.62.35.155` (veya `88.255.216.16`)
2. **Port**: `22`
3. **Connection Type**: SSH
4. **Open** butonuna tıklayın

### Adım 3: Giriş

1. **Username**: `root`
2. **Password**: Hostinger panelinden aldığınız şifre

---

## ✅ Yöntem 3: Hostinger Panel'den SSH Terminal

### Adım 1: Hostinger Panel'e Giriş

1. **Hostinger hesabınıza giriş yapın**
2. **VPS yönetim paneline gidin**
3. **"SSH Access"** veya **"Terminal"** sekmesine gidin

### Adım 2: Web Terminal

1. **"Open Terminal"** veya **"SSH Terminal"** butonuna tıklayın
2. **Web terminal açılır**
3. **Direkt komut çalıştırabilirsiniz**

**Avantajlar:**
- ✅ Tarayıcıdan direkt erişim
- ✅ SSH client kurulumu gerekmez
- ✅ Kolay kullanım

---

## 🔑 SSH Key ile Bağlantı (Önerilen)

### Adım 1: SSH Key Oluşturma (Windows)

**PowerShell'de:**

```powershell
# SSH key oluştur
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"

# Key dosyası konumu:
# C:\Users\YourUsername\.ssh\id_rsa.pub
```

### Adım 2: SSH Key'i VPS'e Kopyalama

```bash
# SSH key'i VPS'e kopyala
ssh-copy-id root@72.62.35.155

# Veya manuel olarak:
cat ~/.ssh/id_rsa.pub | ssh root@72.62.35.155 "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
```

### Adım 3: Hostinger Panel'den SSH Key Ekleme

1. **Hostinger Panel → VPS → SSH Keys**
2. **"Add SSH Key"** butonuna tıklayın
3. **Public Key**'i yapıştırın (`id_rsa.pub` içeriği)
4. **Save**

---

## 📋 SSH Bağlantı Komutları

### Temel Bağlantı:

```bash
# Şifre ile bağlan
ssh root@72.62.35.155

# Port belirtme (varsayılan 22)
ssh -p 22 root@72.62.35.155

# SSH key ile bağlan
ssh -i ~/.ssh/id_rsa root@72.62.35.155
```

### Bağlantı Testi:

```bash
# Bağlantıyı test et
ping 72.62.35.155

# SSH portunu kontrol et
telnet 72.62.35.155 22
```

---

## 🔧 SSH Yapılandırması

### SSH Config Dosyası (Windows)

**Dosya**: `C:\Users\YourUsername\.ssh\config`

```ssh
Host hostinger-vps
    HostName 72.62.35.155
    User root
    Port 22
    IdentityFile ~/.ssh/id_rsa
```

**Kullanım:**
```bash
ssh hostinger-vps
```

---

## ⚠️ Güvenlik Notları

### Şifre Güvenliği:

- ✅ Güçlü şifre kullanın
- ✅ Şifreyi düzenli değiştirin
- ✅ SSH key kullanın (daha güvenli)

### SSH Key Kullanımı:

- ✅ Şifre yerine SSH key kullanın
- ✅ Private key'i asla paylaşmayın
- ✅ Public key'i VPS'e ekleyin

### Firewall:

- ✅ Port 22'nin açık olduğundan emin olun
- ✅ Gereksiz portları kapatın
- ✅ Fail2ban kullanın (brute force koruması)

---

## 🆘 Sorun Giderme

### Bağlantı Reddedildi:

1. **VPS IP adresini kontrol edin**
2. **Port 22'nin açık olduğunu kontrol edin**
3. **Hostinger panelinden SSH erişimini kontrol edin**

### Şifre Hatası:

1. **Hostinger panelinden şifreyi kontrol edin**
2. **Şifreyi reset edin** (gerekirse)
3. **SSH key kullanmayı deneyin**

### Timeout Hatası:

1. **Firewall kurallarını kontrol edin**
2. **VPS'in çalıştığını kontrol edin**
3. **Network bağlantısını kontrol edin**

---

## 📝 Hostinger Panel'den SSH Bilgileri

### SSH Bilgilerini Bulma:

1. **Hostinger Panel → VPS → Server Details**
2. **SSH Access** sekmesine gidin
3. **IP Address**: `72.62.35.155`
4. **Port**: `22`
5. **Username**: `root`
6. **Password**: Hostinger panelinden alın

---

## ✅ Hızlı Bağlantı

### Windows PowerShell/CMD:

```bash
ssh root@72.62.35.155
```

### Hostinger Web Terminal:

1. **Hostinger Panel → VPS → Terminal**
2. **"Open Terminal"** butonuna tıklayın

---

## 📋 Özet

**SSH Bağlantı Bilgileri:**
- **IP**: `72.62.35.155` (veya `88.255.216.16`)
- **Port**: `22`
- **User**: `root`
- **Password**: Hostinger panelinden

**Bağlantı Komutu:**
```bash
ssh root@72.62.35.155
```

**Alternatif:**
- ✅ Hostinger Panel → Terminal (Web terminal)
- ✅ PuTTY (Windows)
- ✅ SSH Key (Daha güvenli)

**Sonuç:** `ssh root@72.62.35.155` komutu ile bağlanabilirsiniz!

