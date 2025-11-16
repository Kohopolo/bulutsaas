# Digital Ocean SSH Key vs Password Rehberi

## 🔐 SSH Key vs Password Karşılaştırması

### SSH Key (ÖNERİLEN) ✅

**Avantajlar:**
- ✅ **Çok daha güvenli** (brute force saldırılarına karşı korumalı)
- ✅ **Şifre hatırlamaya gerek yok**
- ✅ **Otomatik giriş** (key-based authentication)
- ✅ **Daha hızlı** (şifre girmeye gerek yok)
- ✅ **Best practice** (tüm profesyonel sistemlerde kullanılır)
- ✅ **Çoklu sunucu yönetimi** (aynı key'i birden fazla sunucuda kullanabilirsiniz)
- ✅ **Otomatik deployment** (CI/CD için gerekli)

**Dezavantajlar:**
- ⚠️ Key dosyasını kaybederseniz erişim kaybı (ama yedekleme yapabilirsiniz)
- ⚠️ İlk kurulum biraz daha karmaşık

### Password (ÖNERİLMEZ) ❌

**Avantajlar:**
- ✅ Basit kurulum
- ✅ Şifreyi hatırlayabilirsiniz

**Dezavantajlar:**
- ❌ **Çok güvensiz** (brute force saldırılarına açık)
- ❌ **Şifre unutma riski**
- ❌ **Her girişte şifre girmeniz gerekir**
- ❌ **Otomatik deployment zor**
- ❌ **Best practice değil**

---

## ✅ ÖNERİLEN: SSH Key Kullanın

**Neden SSH Key?**

1. **Güvenlik**: Brute force saldırılarına karşı korumalı
2. **Kolaylık**: Şifre girmeye gerek yok
3. **Profesyonel**: Tüm profesyonel sistemlerde kullanılır
4. **Otomatik**: CI/CD ve otomatik deployment için gerekli

---

## 🔑 SSH Key Oluşturma ve Kurulum

### 1. SSH Key Oluşturma (Windows)

#### Seçenek 1: Git Bash ile (Önerilen)

```bash
# Git Bash'i açın (Git for Windows ile gelir)

# SSH key oluştur
ssh-keygen -t ed25519 -C "your_email@example.com"

# Veya RSA kullanmak isterseniz:
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"

# Sorular:
# - Enter file in which to save the key: ENTER (varsayılan: C:\Users\YourName\.ssh\id_ed25519)
# - Enter passphrase: ENTER (boş bırakabilirsiniz veya güçlü bir şifre girin)
# - Enter same passphrase again: ENTER
```

#### Seçenek 2: PowerShell ile

```powershell
# PowerShell'i açın (Administrator olarak)

# OpenSSH client kurulu değilse kurun
Add-WindowsCapability -Online -Name OpenSSH.Client~~~~0.0.1.0

# SSH key oluştur
ssh-keygen -t ed25519 -C "your_email@example.com"

# Sorular:
# - Enter file in which to save the key: ENTER
# - Enter passphrase: ENTER (boş bırakabilirsiniz)
```

#### Seçenek 3: PuTTY Key Generator (PuTTYgen)

1. **PuTTYgen**'i indirin ve açın: https://www.putty.org/
2. **Generate** butonuna tıklayın
3. Mouse'u hareket ettirin (key oluşturmak için)
4. **Key comment**: Email adresinizi girin
5. **Key passphrase**: Boş bırakabilirsiniz veya şifre girin
6. **Save private key**: `id_rsa.ppk` olarak kaydedin
7. **Save public key**: `id_rsa.pub` olarak kaydedin
8. **Public key'i kopyalayın** (OpenSSH formatında)

### 2. SSH Key'i Digital Ocean'a Ekleme

#### Yöntem 1: Digital Ocean Web Console'dan

1. **Digital Ocean Dashboard**'a giriş yapın
2. **Settings** > **Security** > **SSH Keys** sekmesine gidin
3. **Add SSH Key** butonuna tıklayın
4. **SSH Key Content** alanına public key'inizi yapıştırın:
   - Windows: `C:\Users\YourName\.ssh\id_ed25519.pub` dosyasını açın ve içeriğini kopyalayın
   - PuTTY: Public key'i OpenSSH formatında kopyalayın
5. **Key Name**: Key'inize bir isim verin (örn: "My Laptop")
6. **Add SSH Key** butonuna tıklayın

#### Yöntem 2: Digital Ocean CLI (doctl) ile

```bash
# doctl kurulumu (eğer kurulu değilse)
# Windows: https://docs.digitalocean.com/reference/doctl/how-to/install/

# Digital Ocean'a giriş yap
doctl auth init

# SSH key'i ekle
doctl compute ssh-key create "My Laptop" --public-key-file ~/.ssh/id_ed25519.pub

# Windows'ta:
doctl compute ssh-key create "My Laptop" --public-key-file C:\Users\YourName\.ssh\id_ed25519.pub
```

### 3. Droplet Oluştururken SSH Key Seçme

1. **Digital Ocean Dashboard** > **Create** > **Droplets**
2. **Authentication** bölümünde:
   - ✅ **SSH keys** seçeneğini işaretleyin
   - ✅ Eklediğiniz SSH key'i seçin
   - ❌ **Password** seçeneğini işaretlemeyin
3. Droplet'i oluşturun

---

## 🔍 SSH Key Dosyalarının Konumu

### Windows

```
C:\Users\YourName\.ssh\
├── id_ed25519          # Private key (GİZLİ - kimseyle paylaşmayın!)
├── id_ed25519.pub      # Public key (Digital Ocean'a ekleyin)
├── known_hosts         # Bilinen sunucular
└── config              # SSH config dosyası (opsiyonel)
```

### Linux/Mac

```
~/.ssh/
├── id_ed25519          # Private key (GİZLİ)
├── id_ed25519.pub      # Public key (Digital Ocean'a ekleyin)
├── known_hosts         # Bilinen sunucular
└── config              # SSH config dosyası (opsiyonel)
```

---

## 🔐 SSH Key ile Bağlantı

### Windows'ta SSH Bağlantısı

#### Git Bash ile

```bash
# SSH bağlantısı
ssh root@YOUR_DROPLET_IP

# Veya kullanıcı adı ile
ssh bulutacente@YOUR_DROPLET_IP

# İlk bağlantıda "Are you sure you want to continue connecting?" sorusu gelecek
# "yes" yazın ve ENTER'a basın
```

#### PowerShell ile

```powershell
# SSH bağlantısı
ssh root@YOUR_DROPLET_IP

# Veya kullanıcı adı ile
ssh bulutacente@YOUR_DROPLET_IP
```

#### PuTTY ile

1. **PuTTY**'yi açın
2. **Host Name**: `root@YOUR_DROPLET_IP` veya `bulutacente@YOUR_DROPLET_IP`
3. **Port**: `22`
4. **Connection type**: `SSH`
5. **Connection** > **SSH** > **Auth** > **Credentials**
6. **Private key file for authentication**: `id_rsa.ppk` dosyasını seçin
7. **Open** butonuna tıklayın

---

## 🛡️ Güvenlik Önerileri

### 1. Private Key'i Koruyun

```bash
# Private key dosyasını sadece siz okuyabilirsiniz
chmod 600 ~/.ssh/id_ed25519

# Windows'ta: Dosya özelliklerinden "Read-only" yapın
```

### 2. Passphrase Kullanın (Önerilen)

SSH key oluştururken passphrase ekleyin:

```bash
ssh-keygen -t ed25519 -C "your_email@example.com"
# Enter passphrase: GÜÇLÜ_ŞİFRE_BURAYA
```

**Avantajlar:**
- Key dosyası çalınsa bile kullanılamaz
- Ekstra güvenlik katmanı

**Dezavantajlar:**
- Her SSH bağlantısında passphrase girmeniz gerekir
- Otomatik deployment için sorun olabilir

### 3. Yedekleme

```bash
# Private key'i güvenli bir yere yedekleyin (şifrelenmiş)
# Örnek: USB flash drive, şifrelenmiş cloud storage

# Windows'ta:
# C:\Users\YourName\.ssh\id_ed25519 dosyasını yedekleyin
# C:\Users\YourName\.ssh\id_ed25519.pub dosyasını yedekleyin
```

### 4. Çoklu Key Kullanımı

Her sunucu için farklı key kullanabilirsiniz:

```bash
# Yeni key oluştur (farklı isimle)
ssh-keygen -t ed25519 -f ~/.ssh/digitalocean_key -C "digitalocean@example.com"

# SSH config dosyası oluştur
nano ~/.ssh/config
```

```bash
# ~/.ssh/config içeriği
Host digitalocean
    HostName YOUR_DROPLET_IP
    User root
    IdentityFile ~/.ssh/digitalocean_key
```

```bash
# Artık sadece şunu yazmanız yeterli:
ssh digitalocean
```

---

## 🔄 Password'dan SSH Key'e Geçiş

Eğer droplet'i password ile oluşturduysanız, SSH key'e geçebilirsiniz:

### 1. Password ile Bağlanın

```bash
ssh root@YOUR_DROPLET_IP
# Şifreyi girin
```

### 2. SSH Key'i Sunucuya Ekleyin

```bash
# Sunucuda .ssh dizinini oluştur
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Public key'i ekle
nano ~/.ssh/authorized_keys
# Public key'inizi yapıştırın ve kaydedin

# İzinleri ayarla
chmod 600 ~/.ssh/authorized_keys
```

### 3. Password Authentication'ı Kapatın (Önerilen)

```bash
# SSH config dosyasını düzenle
sudo nano /etc/ssh/sshd_config

# Aşağıdaki satırları bulun ve değiştirin:
# PasswordAuthentication no
# PubkeyAuthentication yes

# SSH servisini yeniden başlat
sudo systemctl restart sshd
```

---

## 🚨 Sorun Giderme

### 1. "Permission denied (publickey)" Hatası

```bash
# SSH key dosyasının izinlerini kontrol et
chmod 600 ~/.ssh/id_ed25519
chmod 644 ~/.ssh/id_ed25519.pub

# SSH agent'ı başlat
eval $(ssh-agent)
ssh-add ~/.ssh/id_ed25519

# Tekrar bağlanmayı deneyin
ssh root@YOUR_DROPLET_IP
```

### 2. "Host key verification failed" Hatası

```bash
# known_hosts dosyasından eski kaydı sil
ssh-keygen -R YOUR_DROPLET_IP

# Veya known_hosts dosyasını düzenle
nano ~/.ssh/known_hosts
# İlgili satırı silin
```

### 3. PuTTY "Server refused our key" Hatası

```bash
# PuTTY key'i OpenSSH formatına dönüştür
# PuTTYgen'de: Conversions > Export OpenSSH key

# Veya PuTTY key'i kullanarak bağlanın
# PuTTY > Connection > SSH > Auth > Credentials
# Private key file: id_rsa.ppk
```

### 4. Windows'ta SSH Key Bulunamıyor

```bash
# SSH config dosyası oluştur
nano ~/.ssh/config
```

```bash
# ~/.ssh/config içeriği
Host *
    IdentityFile ~/.ssh/id_ed25519
    User root
```

---

## ✅ Özet ve Öneriler

### Önerilen Yaklaşım

1. ✅ **SSH Key kullanın** (password değil)
2. ✅ **Passphrase ekleyin** (ekstra güvenlik için)
3. ✅ **Private key'i yedekleyin** (güvenli yerde)
4. ✅ **Password authentication'ı kapatın** (sunucuda)
5. ✅ **Her sunucu için farklı key** (opsiyonel ama önerilen)

### Güvenlik Checklist

- [ ] SSH key oluşturuldu
- [ ] Public key Digital Ocean'a eklendi
- [ ] Private key güvenli yerde saklanıyor
- [ ] Passphrase eklendi (opsiyonel ama önerilen)
- [ ] Yedekleme yapıldı
- [ ] Password authentication kapatıldı (sunucuda)
- [ ] SSH key ile bağlantı test edildi

---

## 📚 Ek Kaynaklar

- [Digital Ocean SSH Keys Dokümantasyonu](https://docs.digitalocean.com/products/droplets/how-to/add-ssh-keys/)
- [SSH Key Best Practices](https://www.ssh.com/academy/ssh/key)
- [Windows SSH Key Kurulumu](https://docs.microsoft.com/en-us/windows-server/administration/openssh/openssh_keymanagement)

---

**Son Güncelleme**: 2025-01-16

