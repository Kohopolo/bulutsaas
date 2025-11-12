# Tenant Domain Kurulumu (Windows)

Windows'ta `test-otel.localhost` subdomain'i çözümlenmediği için, hosts dosyasına ekleme yapmanız gerekiyor.

## Adım 1: Hosts Dosyasını Düzenle

1. **Notepad'i Yönetici Olarak Çalıştır:**
   - Windows tuşuna basın
   - "Notepad" yazın
   - Sağ tıklayın → "Yönetici olarak çalıştır"

2. **Hosts Dosyasını Aç:**
   - Dosya → Aç
   - `C:\Windows\System32\drivers\etc\hosts` yolunu yazın
   - Dosya türünü "Tüm Dosyalar" olarak seçin

3. **Aşağıdaki Satırı Ekleyin:**
   ```
   127.0.0.1    test-otel.localhost
   ```

4. **Kaydedin ve Kapatın**

## Adım 2: Tarayıcıyı Test Et

Tarayıcıda şu URL'yi açın:
```
http://test-otel.localhost:8000/login/
```

## Alternatif: Port ile Test

Eğer hosts dosyasını düzenlemek istemiyorsanız, şu komutu çalıştırarak alternatif domain ekleyebilirsiniz:

```powershell
python manage.py add_tenant_domain --tenant-slug=test-otel --domain=test-otel.127.0.0.1
```

Sonra şu URL'yi kullanın:
```
http://test-otel.127.0.0.1:8000/login/
```

## Mevcut Domain'ler

Test tenant için şu domain'ler mevcut:
- `test-otel.localhost` (hosts dosyası gerekli)
- `test-otel.127.0.0.1` (alternatif)

## Test Kullanıcı Bilgileri

- **Kullanıcı Adı:** `testadmin`
- **Şifre:** `test123`
- **Tenant:** Test Otel
- **Paket:** Başlangıç Paketi
