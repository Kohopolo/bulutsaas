# Bungalov Yönetimi Modülü - Kurulum Talimatları

**Tarih:** 2025-01-XX  
**Durum:** ✅ Modül Hazır

---

## 📋 Kurulum Adımları

### 1. Virtual Environment Aktifleştirme

**Windows:**
```bash
venv\Scripts\activate
```

**Linux/Mac:**
```bash
source venv/bin/activate
```

### 2. Migration İşlemleri

#### Public Schema Migration
```bash
python manage.py migrate_schemas --schema=public bungalovs
```

#### Tenant Schema Migration
```bash
python manage.py migrate_schemas --schema=<tenant_schema_name> bungalovs
```

**Veya tüm tenant'lar için:**
```bash
python manage.py migrate_schemas --tenant bungalovs
```

### 3. Modül Oluşturma

Public schema'da modülü oluştur:
```bash
python manage.py create_bungalovs_module
```

### 4. Permission Oluşturma

Her tenant schema'da permission'ları oluştur:
```bash
python manage.py create_bungalovs_permissions --schema=<tenant_schema_name>
```

**Veya tüm tenant'lar için otomatik:**
```bash
python manage.py setup_bungalovs_all_tenants
```

### 5. Paketlere Ekleme

Modülü tüm aktif paketlere ekle:
```bash
python manage.py add_bungalovs_to_packages
```

### 6. Super Admin Panel

Super Admin panelinden paket yönetiminde modülü aktifleştirin:
1. Super Admin'e giriş yapın
2. Paketler > [Paket Adı] > Düzenle
3. Modüller sekmesine gidin
4. "Bungalov Yönetimi" modülünü aktifleştirin

---

## 🎯 Hızlı Kurulum (Tüm Adımlar)

```bash
# 1. Virtual environment aktifleştir
venv\Scripts\activate  # Windows
# veya source venv/bin/activate  # Linux/Mac

# 2. Public schema'da modül oluştur
python manage.py create_bungalovs_module

# 3. Public schema migration
python manage.py migrate_schemas --schema=public bungalovs

# 4. Paketlere ekle
python manage.py add_bungalovs_to_packages

# 5. Tenant schema migration ve permission (her tenant için)
python manage.py migrate_schemas --schema=<tenant_schema> bungalovs
python manage.py create_bungalovs_permissions --schema=<tenant_schema>
```

---

## 📊 Modül Özellikleri

### Bungalov Yönetimi
- ✅ Bungalov tipleri (Standart, Deluxe, Suite vb.)
- ✅ Bungalov özellikleri (Deniz manzarası, jakuzi, şömine vb.)
- ✅ Fiziksel bungalov birimleri
- ✅ Konum ve pozisyon yönetimi
- ✅ Durum yönetimi (Müsait, Dolu, Temizlikte, Bakımda)

### Rezervasyon Sistemi
- ✅ Rezervasyon oluşturma/düzenleme/silme
- ✅ Check-In/Check-Out işlemleri
- ✅ Misafir bilgileri yönetimi
- ✅ Ödeme yönetimi
- ✅ Depozito yönetimi
- ✅ İptal ve iade işlemleri
- ✅ Ödeme/iade kontrolü ile silme

### Fiyatlandırma
- ✅ Gecelik fiyatlandırma
- ✅ Haftalık fiyatlandırma (7 gece için indirimli)
- ✅ Aylık fiyatlandırma (Uzun dönem kiralama)
- ✅ Sezonluk fiyatlandırma (Düşük/Orta/Yüksek/Pik sezon)
- ✅ Hafta içi/Hafta sonu fiyatlandırma
- ✅ Bayram fiyatlandırma
- ✅ Minimum konaklama süresi kontrolü

### Temizlik Yönetimi
- ✅ Check-out temizliği
- ✅ Haftalık temizlik
- ✅ Derinlemesine temizlik
- ✅ İsteğe bağlı temizlik
- ✅ Temizlik durumu takibi
- ✅ Personel atama

### Bakım Yönetimi
- ✅ Rutin bakım
- ✅ Acil onarım
- ✅ Yenileme
- ✅ Boyama/Badana
- ✅ Mobilya/Ekipman değişimi
- ✅ Bakım durumu takibi
- ✅ Maliyet takibi

### Ekipman Yönetimi
- ✅ Mutfak ekipmanları
- ✅ Elektronik cihazlar
- ✅ Mobilya
- ✅ Dış mekan ekipmanları
- ✅ Ekipman durumu takibi

### Voucher Sistemi
- ✅ Voucher oluşturma
- ✅ Voucher şablonları
- ✅ Public voucher görüntüleme (Token ile)
- ✅ Voucher ödeme linki
- ✅ Voucher PDF oluşturma
- ✅ Voucher gönderme (E-posta/SMS/WhatsApp)

---

## 🔗 Entegrasyonlar

### ✅ Tamamlanan Entegrasyonlar
1. **Core Modülü** - Customer ve User entegrasyonu
2. **Refunds Modülü** - İade yönetimi entegrasyonu
3. **Ödeme Kontrolü** - Silme işleminde ödeme/iade kontrolü

### ⏳ Bekleyen Entegrasyonlar
1. **Finance Modülü** - Kasa işlemleri
2. **Accounting Modülü** - Muhasebe kayıtları
3. **Payments Modülü** - Online ödeme entegrasyonu
4. **Notifications Modülü** - E-posta/SMS bildirimleri
5. **Sales Modülü** - Acente entegrasyonu
6. **Channels Modülü** - Kanal entegrasyonu

---

## 📝 Modül URL'leri

- **Dashboard:** `/bungalovs/`
- **Bungalovlar:** `/bungalovs/bungalovs/`
- **Rezervasyonlar:** `/bungalovs/reservations/`
- **Temizlik:** `/bungalovs/cleanings/`
- **Bakım:** `/bungalovs/maintenances/`
- **Ekipman:** `/bungalovs/equipments/`
- **Fiyatlandırma:** `/bungalovs/prices/`
- **Voucher Şablonları:** `/bungalovs/voucher-templates/`

---

## ✅ Kontrol Listesi

- [x] Modül yapısı oluşturuldu
- [x] Tüm modeller tanımlandı (12 model)
- [x] Forms oluşturuldu (9 form)
- [x] Views oluşturuldu (40+ view)
- [x] URLs yapılandırıldı
- [x] Admin kayıtları yapıldı
- [x] Utility fonksiyonları oluşturuldu
- [x] Signals oluşturuldu
- [x] Management commands oluşturuldu
- [x] Settings.py güncellendi
- [x] URLs.py güncellendi
- [x] Ödeme/iade kontrolü entegrasyonu
- [x] Migration dosyası oluşturuldu
- [ ] Migration çalıştırıldı
- [ ] Modül oluşturuldu
- [ ] Permission'lar oluşturuldu
- [ ] Paketlere eklendi
- [ ] Template dosyaları oluşturuldu
- [ ] Modül test edildi

---

## 🎯 Sonraki Adımlar

1. **Migration Çalıştırma**
   ```bash
   python manage.py migrate_schemas --schema=public bungalovs
   python manage.py migrate_schemas --schema=<tenant_schema> bungalovs
   ```

2. **Modül Oluşturma**
   ```bash
   python manage.py create_bungalovs_module
   ```

3. **Permission Oluşturma**
   ```bash
   python manage.py create_bungalovs_permissions --schema=<tenant_schema>
   ```

4. **Paketlere Ekleme**
   ```bash
   python manage.py add_bungalovs_to_packages
   ```

5. **Template Dosyaları**
   - Dashboard template
   - Bungalov listesi template
   - Rezervasyon form template
   - Voucher template

---

**Durum:** ✅ Modül Hazır - Kurulum Bekliyor  
**Son Güncelleme:** 2025-01-XX

