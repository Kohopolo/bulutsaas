# Tenant Admin Kullanıcı Kurulumu

## 📋 Sorun

Tenant kayıt olduğunda veya paket satın aldığında:
- İlk admin kullanıcı otomatik oluşturulmuyordu
- Admin rolüne yetkiler atanmıyordu
- Bu yüzden "yetkiniz yoktur" hatası alınıyordu

## ✅ Çözüm

### 1. Subscription Signal Eklendi

`apps/subscriptions/signals.py` dosyası oluşturuldu:
- Subscription aktif olduğunda otomatik ilk admin kullanıcı oluşturuluyor
- Owner bilgilerinden (email, name) kullanıcı oluşturuluyor
- Admin rolüne tüm yetkiler otomatik atanıyor
- İlk kullanıcıya admin rolü otomatik atanıyor

### 2. Admin Rolüne Otomatik Yetki Atama

`create_default_roles` komutu güncellendi:
- Admin rolü oluşturulduğunda tüm yetkiler otomatik atanıyor

### 3. Mevcut Tenant'lar İçin Düzeltme Komutu

`fix_admin_permissions` komutu oluşturuldu:
- Mevcut tenant'larda rolleri ve yetkileri oluşturuyor
- Admin rolüne tüm yetkileri atıyor
- Kullanım: `python manage.py fix_admin_permissions --tenant-slug=test-otel`

## 🔧 Kullanım

### Yeni Tenant İçin

1. Tenant oluşturulduğunda
2. Subscription aktif olduğunda
3. Otomatik olarak:
   - İlk admin kullanıcı oluşturulur (owner_email'den)
   - Admin rolü oluşturulur
   - Tüm yetkiler admin rolüne atanır
   - İlk kullanıcıya admin rolü atanır

### Mevcut Tenant İçin

```bash
# Belirli tenant için
python manage.py fix_admin_permissions --tenant-slug=test-otel

# Tüm tenant'lar için
python manage.py fix_admin_permissions
```

## 📝 Notlar

- İlk kullanıcı şifresi: `{username}123` (örnek: `test123`)
- Kullanıcı adı: email'in @ öncesi kısmı
- Admin rolü sistem rolü olarak işaretlenir (silinemez)
- Tüm yetkiler admin rolüne otomatik atanır

## ✅ Test-otel İçin Durum

- ✅ Roller oluşturuldu (6 rol)
- ✅ Yetkiler oluşturuldu (14 yetki)
- ✅ Admin rolüne 29 yetki atandı
- ⚠️ Admin kullanıcı bulunamadı (manuel oluşturulması gerekiyor)

Test-otel için kullanıcı oluşturmak için:
```bash
python manage.py create_tenant_user --tenant-slug=test-otel --username=testadmin --email=test@example.com --password=test123 --first-name=Test --last-name=Admin --user-type=admin
```

