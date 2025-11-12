# Resepsiyon Modülü - Ek Özellikler ve Gelişmiş İşlevler

**Tarih:** 12 Kasım 2025  
**Amaç:** Kullanıcı talepleri ve profesyonel ön büro özelliklerinin detaylandırılması

---

## 📋 İçindekiler

1. [Erken/Geç Çıkış Yönetimi](#erkengeç-çıkış-yönetimi)
2. [Rezervasyon Arşivleme Sistemi](#rezervasyon-arşivleme-sistemi)
3. [Rezervasyon Takip Sistemi](#rezervasyon-takip-sistemi)
4. [Müşteri Bilgileri Yönetimi](#müşteri-bilgileri-yönetimi)
5. [Çocuk Yaş Kontrolü](#çocuk-yaş-kontrolü)
6. [Tek Ekran Oda Durumu](#tek-ekran-oda-durumu)
7. [Kaynak Bazlı Rezervasyonlar](#kaynak-bazlı-rezervasyonlar)
8. [Comp Rezervasyon](#comp-rezervasyon)
9. [Oda Değişimi](#oda-değişimi)
10. [SaaS Panel Entegrasyonları](#saas-panel-entegrasyonları)
11. [Profesyonel Ön Büro Özellikleri](#profesyonel-ön-büro-özellikleri)

---

## 1. Erken/Geç Çıkış Yönetimi

### Erken Check-Out (Early Check-Out)

**Tanım:** Müşteri planlanan çıkış tarihinden önce çıkış yapmak istediğinde.

**Özellikler:**
- ✅ ReceptionSettings'den erken çıkış izni kontrol edilir
- ✅ Erken çıkış ücreti hesaplanabilir (ayarlanabilir)
- ✅ Uyarı mesajı gösterilir
- ✅ Erken çıkış nedeni kaydedilir
- ✅ İade hesaplaması yapılabilir (Refunds modülü ile entegre)

**İş Akışı:**
1. Check-out işlemi başlatılır
2. Sistem planlanan çıkış tarihi ile karşılaştırır
3. Erken çıkış tespit edilirse:
   - Uyarı mesajı gösterilir
   - Erken çıkış ücreti hesaplanır (varsa)
   - İade tutarı hesaplanır (varsa)
   - Onay istenir
4. İşlem tamamlanır

### Geç Check-Out (Late Check-Out)

**Tanım:** Müşteri planlanan çıkış saatinden sonra çıkış yapmak istediğinde.

**Özellikler:**
- ✅ ReceptionSettings'den geç çıkış izni kontrol edilir
- ✅ Geç çıkış ücreti hesaplanır (ayarlanabilir)
- ✅ Uyarı mesajı gösterilir
- ✅ Geç çıkış nedeni kaydedilir
- ✅ Oda müsaitlik durumu kontrol edilir

**İş Akışı:**
1. Check-out işlemi başlatılır
2. Sistem planlanan çıkış saati ile karşılaştırır
3. Geç çıkış tespit edilirse:
   - Uyarı mesajı gösterilir
   - Geç çıkış ücreti hesaplanır
   - Oda müsaitlik durumu kontrol edilir
   - Onay istenir
4. İşlem tamamlanır

**ReceptionSettings Alanları:**
```python
early_checkout_allowed = BooleanField(default=True)
early_checkout_fee = DecimalField(null=True, blank=True)  # Erken çıkış ücreti
early_checkout_refund_rate = DecimalField(null=True, blank=True)  # İade oranı (%)
late_checkout_allowed = BooleanField(default=True)
late_checkout_fee = DecimalField(null=True, blank=True)  # Geç çıkış ücreti
late_checkout_hour_limit = IntegerField(default=12)  # Saat 12'den sonra geç çıkış sayılır
```

---

## 2. Rezervasyon Arşivleme Sistemi

### Arşivleme Mantığı

**Amaç:** Silinen rezervasyonların kayıt altına alınması, veri kaybının önlenmesi.

**Özellikler:**
- ✅ Soft delete kullanılır (`is_deleted=True`)
- ✅ Arşivlenme tarihi kaydedilir
- ✅ Arşivleyen kullanıcı kaydedilir
- ✅ Arşivleme nedeni kaydedilir
- ✅ Arşivlenmiş rezervasyonlar ayrı listede görüntülenir
- ✅ Arşivlenmiş rezervasyonlar geri getirilebilir (restore)

**Model Alanları:**
```python
class Reservation(TimeStampedModel, SoftDeleteModel):
    # ... diğer alanlar
    archived_at = DateTimeField(null=True, blank=True)
    archived_by = ForeignKey(User, null=True, blank=True, related_name='archived_reservations')
    archive_reason = TextField(blank=True)
```

**İş Akışı:**
1. Rezervasyon silinmek istendiğinde
2. "Arşivle" butonuna tıklanır
3. Arşivleme nedeni sorulur (opsiyonel)
4. Rezervasyon `is_deleted=True` yapılır
5. `archived_at`, `archived_by`, `archive_reason` kaydedilir
6. Rezervasyon arşiv listesine taşınır

**Arşiv Listesi:**
- Filtreleme: Tarih, müşteri, arşivleme nedeni
- Arama: Rezervasyon kodu, müşteri adı
- Geri Getirme: Arşivlenmiş rezervasyon geri getirilebilir
- Kalıcı Silme: Yönetici yetkisi ile kalıcı silme yapılabilir

---

## 3. Rezervasyon Takip Sistemi

### Rezervasyon Güncellemeleri (Audit Log)

**Amaç:** Rezervasyonda yapılan tüm değişikliklerin kayıt altına alınması.

**Model:**
```python
class ReservationUpdate(TimeStampedModel):
    """
    Rezervasyon güncelleme kayıtları (Audit Log)
    """
    reservation = ForeignKey(Reservation, related_name='updates')
    updated_by = ForeignKey(User, related_name='reservation_updates')
    update_type = CharField()  # created, updated, cancelled, checked_in, checked_out, room_changed, etc.
    field_name = CharField(blank=True)  # Hangi alan değişti
    old_value = TextField(blank=True)  # Eski değer
    new_value = TextField(blank=True)  # Yeni değer
    notes = TextField(blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon Güncelleme'
        verbose_name_plural = 'Rezervasyon Güncellemeleri'
        ordering = ['-created_at']
```

**Takip Edilen Değişiklikler:**
- Rezervasyon oluşturuldu
- Rezervasyon güncellendi (tarih, oda tipi, müşteri, vb.)
- Rezervasyon iptal edildi
- Check-in yapıldı
- Check-out yapıldı
- Oda değişikliği yapıldı
- Ödeme yapıldı
- İade yapıldı

**Görüntüleme:**
- Rezervasyon detay modal'ında "Güncellemeler" sekmesi
- Tarih, kullanıcı, değişiklik türü, detaylar

### Ödeme Takibi

**Amaç:** Rezervasyona ait tüm ödemelerin takip edilmesi.

**Entegrasyon:**
- Finance modülü ile entegre
- Tüm ödemeler Finance modülüne kaydedilir
- Rezervasyon detayında ödeme geçmişi gösterilir

**Görüntüleme:**
- Rezervasyon detay modal'ında "Ödemeler" sekmesi
- Ödeme tarihi, tutar, yöntem, durum
- Toplam ödenen, kalan bakiye

### İade Takibi

**Amaç:** Rezervasyona ait tüm iadelerin takip edilmesi.

**Entegrasyon:**
- Refunds modülü ile entegre
- Tüm iadeler Refunds modülüne kaydedilir
- Rezervasyon detayında iade geçmişi gösterilir

**Görüntüleme:**
- Rezervasyon detay modal'ında "İadeler" sekmesi
- İade tarihi, tutar, durum, nedeni
- Toplam iade edilen

---

## 4. Müşteri Bilgileri Yönetimi

### Zorunlu Alanlar

**Rezervasyon için:**
- Ad (first_name)
- Soyad (last_name)
- Kimlik No (TC Kimlik veya Pasaport No)
- Telefon
- Email (opsiyonel, ancak önerilir)

### Çocuk Bilgileri

**Çocuk Sayısı > 0 ise:**
- Her çocuk için yaş bilgisi otomatik sorulur
- Yaş bilgisi zorunludur (0'dan büyük olmalı)
- Yaş bilgisi ücretsiz çocuk kuralları ile karşılaştırılır

**Form Validasyonu:**
```python
def clean(self):
    cleaned_data = super().clean()
    child_count = cleaned_data.get('child_count', 0)
    child_ages = cleaned_data.get('child_ages', [])
    
    if child_count > 0:
        if not child_ages or len(child_ages) != child_count:
            raise ValidationError('Çocuk sayısı kadar yaş bilgisi girilmelidir.')
        
        for age in child_ages:
            if age <= 0:
                raise ValidationError('Çocuk yaşı 0\'dan büyük olmalıdır.')
    
    return cleaned_data
```

---

## 5. Çocuk Yaş Kontrolü

### Otomatik Formül Karşılaştırması

**Amaç:** Çocuk yaşları ile ücretsiz çocuk kurallarını otomatik karşılaştırma.

**İş Akışı:**
1. Çocuk sayısı girilir
2. Her çocuk için yaş bilgisi otomatik sorulur
3. Yaş bilgileri Global Fiyatlama Utility'ye gönderilir
4. Ücretsiz çocuk kuralları kontrol edilir
5. Ücretsiz çocuk sayısı hesaplanır
6. Fiyat hesaplaması yapılır

**Kod Örneği:**
```python
# Rezervasyon formunda
if child_count > 0:
    # Yaş bilgileri otomatik sorulur
    child_ages = [age1, age2, ...]  # Form'dan alınır
    
    # Global utility ile fiyat hesaplama
    price_result = room_price.calculate_price(
        adults=adults_count,
        children=child_count,
        child_ages=child_ages,  # Yaş bilgileri gönderilir
        # ... diğer parametreler
    )
    
    # Sonuç:
    # price_result['free_children_count'] - Ücretsiz çocuk sayısı
    # price_result['paid_children_count'] - Ücretli çocuk sayısı
    # price_result['child_price'] - Çocuk fiyatı
```

**Form Validasyonu:**
- Çocuk sayısı > 0 ise, yaş bilgileri zorunludur
- Yaş bilgileri 0'dan büyük olmalıdır
- Yaş bilgileri sayı olmalıdır

---

## 6. Tek Ekran Oda Durumu

### Oda Detay Modal'ı

**Amaç:** Oda kartına tıklayınca tüm bilgilerin tek ekranda toplanması.

**Layout:**
```
┌─────────────────────────────────────────────────────────┐
│ ODA DETAYI - Oda 101                                    │
├──────────────┬──────────────┬──────────────┬───────────┤
│ ODA BİLGİLERİ│ REZERVASYON  │   İŞLEMLER   │  FOLIO    │
│              │   BİLGİLERİ  │   VE GEÇMİŞ  │           │
│              │              │              │           │
│ - Oda No     │ - Müşteri    │ - Check-in  │ - Harcama │
│ - Oda Tipi   │ - Tarihler   │ - Check-out │ - Ödeme   │
│ - Durum      │ - Kişi      │ - Oda Değiş. │ - Bakiye  │
│ - Kat/Blok   │ - Pansiyon   │ - Rez. Düzen│           │
│ - Özellikler │ - Fiyat      │ - Rez. İptal│           │
│ - Görseller  │ - Ödeme Dur. │ - Geçmiş    │           │
│              │ - Notlar     │ - Notlar    │           │
└──────────────┴──────────────┴──────────────┴───────────┘
```

**Özellikler:**
- ✅ Tüm bilgiler tek ekranda
- ✅ Düzenleme yapılabilir (yetkiye göre)
- ✅ Real-time güncelleme (WebSocket)
- ✅ Yazdırma seçenekleri
- ✅ Hızlı işlemler (check-in, check-out, oda değişikliği)

**Detaylar:**
- **Oda Bilgileri:** Oda numarası, tipi, durumu, özellikleri, görselleri
- **Rezervasyon Bilgileri:** Müşteri, tarihler, kişi sayıları, fiyatlandırma
- **İşlemler:** Check-in, check-out, oda değişikliği, rezervasyon düzenleme
- **Geçmiş:** Rezervasyon geçmişi, ödeme geçmişi, iade geçmişi, oda değişiklik geçmişi
- **Folio:** Harcamalar, ödemeler, bakiye

---

## 7. Kaynak Bazlı Rezervasyonlar

### Rezervasyon Kaynakları

**Kaynak Türleri:**
1. **Resepsiyon (reception):** Resepsiyon personeli tarafından yapıldı
2. **Satış (sales):** Satış ekibi tarafından yapıldı
3. **Call Center (call_center):** Call center tarafından yapıldı
4. **Acente (agency):** Acente tarafından yapıldı (agency_id ile)
5. **Web (web):** Web sitesinden self müşteri satışı
6. **Kanal (channel):** Kanal yönetiminden (channel_id ile)

### Model Yapısı

```python
class Reservation(TimeStampedModel, SoftDeleteModel):
    # ... diğer alanlar
    
    # Kaynak Bilgisi
    SOURCE_CHOICES = [
        ('reception', 'Resepsiyon'),
        ('sales', 'Satış'),
        ('call_center', 'Call Center'),
        ('agency', 'Acente'),
        ('web', 'Web'),
        ('channel', 'Kanal'),
    ]
    source = CharField(max_length=20, choices=SOURCE_CHOICES, default='reception')
    created_by = ForeignKey(User, null=True, blank=True, related_name='created_reservations')
    
    # Acente Bilgisi (varsa)
    agency = ForeignKey('TourAgency', null=True, blank=True, related_name='reservations')
    agency_id = IntegerField(null=True, blank=True, db_index=True)  # Acente ID
    
    # Kanal Bilgisi (varsa)
    channel = ForeignKey('Channel', null=True, blank=True, related_name='reservations')
    channel_id = IntegerField(null=True, blank=True, db_index=True)  # Kanal ID
    
    # Web Rezervasyonu
    is_web_booking = BooleanField(default=False)  # Web'den self müşteri satışı
    web_booking_reference = CharField(max_length=100, blank=True)  # Web rezervasyon referansı
```

### Raporlama

**Acente Rezervasyonları:**
- Acente ID'ye göre filtreleme
- Acente bazlı raporlar
- Komisyon hesaplamaları

**Web Rezervasyonları:**
- Web rezervasyonları listesi
- Self müşteri satış raporları
- Web rezervasyon istatistikleri

**Kanal Rezervasyonları:**
- Kanal ID'ye göre filtreleme
- Kanal bazlı raporlar
- Kanal performans analizi

---

## 8. Comp Rezervasyon

### Ücretsiz Oda Tahsisi

**Amaç:** Ücretsiz oda tahsisi (Complimentary) için özel rezervasyon türü.

**Özellikler:**
- ✅ Rezervasyon türü olarak işaretlenir
- ✅ Fiyat 0 olarak ayarlanır
- ✅ Comp nedeni kaydedilir
- ✅ Onay gerektirir (yönetici yetkisi)
- ✅ Raporlanabilir

**Model:**
```python
class Reservation(TimeStampedModel, SoftDeleteModel):
    # ... diğer alanlar
    
    is_complimentary = BooleanField(default=False)  # Comp rezervasyon mu?
    complimentary_reason = TextField(blank=True)  # Comp nedeni
    complimentary_approved_by = ForeignKey(User, null=True, blank=True, related_name='approved_comps')
    complimentary_approved_at = DateTimeField(null=True, blank=True)
```

**İş Akışı:**
1. Rezervasyon oluşturulurken "Comp Rezervasyon" işaretlenir
2. Fiyat otomatik 0 olur
3. Comp nedeni sorulur
4. Yönetici onayı gerektirir
5. Onaylandıktan sonra rezervasyon kaydedilir

**Raporlama:**
- Comp rezervasyon listesi
- Comp rezervasyon istatistikleri
- Comp nedenleri analizi

---

## 9. Oda Değişimi

### Oda Değişikliği İşlemi

**Amaç:** Müşterinin farklı bir odaya taşınması.

**Özellikler:**
- ✅ Eski oda ve yeni oda kaydedilir
- ✅ Oda değişiklik nedeni kaydedilir
- ✅ Oda değişiklik tarihi kaydedilir
- ✅ Oda değişiklik geçmişi tutulur
- ✅ Fiyat farkı hesaplanır (varsa)

**Model:**
```python
class RoomChange(TimeStampedModel):
    """
    Oda Değişikliği Kayıtları
    """
    reservation = ForeignKey(Reservation, related_name='room_changes')
    old_room = ForeignKey(Room, related_name='room_changes_from')
    new_room = ForeignKey(Room, related_name='room_changes_to')
    changed_by = ForeignKey(User, related_name='room_changes')
    reason = TextField(blank=True)  # Oda değişiklik nedeni
    price_difference = DecimalField(null=True, blank=True)  # Fiyat farkı
    
    class Meta:
        verbose_name = 'Oda Değişikliği'
        verbose_name_plural = 'Oda Değişiklikleri'
        ordering = ['-created_at']
```

**İş Akışı:**
1. Rezervasyon detayında "Oda Değişikliği" butonuna tıklanır
2. Yeni oda seçilir
3. Oda değişiklik nedeni sorulur (opsiyonel)
4. Fiyat farkı hesaplanır (varsa)
5. Onay istenir
6. Oda değişikliği kaydedilir
7. Rezervasyon güncellenir

**Görüntüleme:**
- Rezervasyon detayında "Oda Değişiklikleri" sekmesi
- Tüm oda değişiklik geçmişi
- Tarih, eski oda, yeni oda, neden, fiyat farkı

---

## 10. SaaS Panel Entegrasyonları

### Modül Yetkilendirmeleri

**Amaç:** Resepsiyon modülünün SaaS panel'de yönetilmesi.

**Gereksinimler:**
1. **Module Oluşturma:**
   - `apps/modules/models.py` - Module modeli
   - `code='reception'`, `name='Resepsiyon'`
   - `url_prefix='reception'`

2. **PackageModule Entegrasyonu:**
   - Paketlere resepsiyon modülü eklenir
   - Modül limitleri tanımlanır (max_reservations, vb.)

3. **Permission Oluşturma:**
   - `reception.view` - Görüntüleme
   - `reception.add` - Ekleme
   - `reception.edit` - Düzenleme
   - `reception.delete` - Silme
   - `reception.checkin` - Check-in
   - `reception.checkout` - Check-out
   - `reception.manage` - Yönetim

4. **RolePermission Atama:**
   - Admin role'e tüm yetkiler
   - Resepsiyonist role'e sınırlı yetkiler

### Sidebar Entegrasyonu

**Amaç:** Sidebar'da resepsiyon modülü linkinin görüntülenmesi.

**Gereksinimler:**
1. **Context Processor:**
   - `has_reception_module` kontrolü
   - `user_has_reception_permission` kontrolü

2. **Sidebar Link:**
   - Modül aktif ve kullanıcı yetkisi varsa görünür
   - Otel bazlı kontrol (tek/çoklu otel yetkisi)

3. **Accordion Yapısı:**
   - "Resepsiyon" ana modülü
   - Alt modüller (ileride eklenebilir)

### Kullanıcı Yetkileri

**Amaç:** Kullanıcılara resepsiyon modülü yetkilerinin atanması.

**Gereksinimler:**
1. **UserPermission Modeli:**
   - Kullanıcıya resepsiyon yetkileri atanır
   - Otel bazlı yetki kontrolü

2. **HotelUserPermission:**
   - Otel bazlı kullanıcı yetkileri
   - Resepsiyon yetkisi kontrolü

3. **Decorator:**
   - `@require_reception_permission` decorator'ı
   - Otel bazlı yetki kontrolü

### Paket Limit Kontrolleri

**Amaç:** Rezervasyon limitlerinin paket bazlı kontrol edilmesi.

**Gereksinimler:**
1. **PackageModule Limits:**
   ```json
   {
     "max_reservations": 100,
     "max_reservations_per_month": 50,
     "max_concurrent_reservations": 10
   }
   ```

2. **Limit Kontrolü:**
   - Rezervasyon oluşturulurken limit kontrolü
   - Aylık limit kontrolü
   - Eşzamanlı rezervasyon limiti kontrolü

3. **Decorator:**
   - `@check_reservation_limit` decorator'ı
   - Limit aşılırsa hata mesajı

---

## 11. Profesyonel Ön Büro Özellikleri

### Waitlist Yönetimi

**Amaç:** Müsait oda olmadığında müşterileri bekleme listesine ekleme.

**Özellikler:**
- ✅ Bekleme listesi oluşturma
- ✅ Müsait oda olduğunda otomatik bildirim
- ✅ Bekleme listesi öncelik sırası
- ✅ Bekleme listesi yönetimi

### Overbooking Yönetimi

**Amaç:** Oda sayısından fazla rezervasyon alma durumu.

**Özellikler:**
- ✅ Overbooking izni (ReceptionSettings)
- ✅ Overbooking limiti
- ✅ Overbooking uyarıları
- ✅ Overbooking raporları

### Group Booking Yönetimi

**Amaç:** 11+ kişi için grup rezervasyonu yönetimi.

**Özellikler:**
- ✅ Grup rezervasyonu oluşturma
- ✅ Grup oda listesi (Rooming List)
- ✅ Grup fiyatlandırması
- ✅ Grup check-in/out

### No-Show Yönetimi

**Amaç:** Rezervasyon yaptığı halde gelmeyen müşteriler.

**Özellikler:**
- ✅ No-show işaretleme
- ✅ No-show ücreti hesaplama
- ✅ No-show raporları
- ✅ No-show müşteri listesi

### Guest History Tracking

**Amaç:** Müşteri geçmiş konaklamalarının takibi.

**Özellikler:**
- ✅ Geçmiş konaklamalar listesi
- ✅ Müşteri tercihleri
- ✅ Müşteri notları
- ✅ VIP müşteri işaretleme

### Loyalty Program Entegrasyonu

**Amaç:** Sadakat programı entegrasyonu (ileride).

**Özellikler:**
- ✅ Puan kazanma
- ✅ Puan kullanma
- ✅ Seviye takibi
- ✅ Özel fırsatlar

### Special Requests Yönetimi

**Amaç:** Müşteri özel isteklerinin yönetimi.

**Özellikler:**
- ✅ Özel istek ekleme
- ✅ Özel istek kategorileri
- ✅ Özel istek durumu takibi
- ✅ Özel istek tamamlama

### Wake-up Call Yönetimi

**Amaç:** Müşteri uyandırma çağrılarının yönetimi.

**Özellikler:**
- ✅ Uyandırma çağrısı ekleme
- ✅ Uyandırma listesi (Wake-up Form)
- ✅ Uyandırma çağrısı tamamlama
- ✅ Uyandırma çağrısı geçmişi

### Message Board

**Amaç:** Müşterilere mesaj gönderme.

**Özellikler:**
- ✅ Mesaj ekleme
- ✅ Mesaj kategorileri
- ✅ Mesaj durumu takibi
- ✅ Mesaj geçmişi

### Lost & Found

**Amaç:** Kayıp eşya yönetimi.

**Özellikler:**
- ✅ Kayıp eşya kaydı
- ✅ Buluntu eşya kaydı
- ✅ Eşya durumu takibi
- ✅ Müşteriye teslim

### Guest Folio Yönetimi

**Amaç:** Müşteri hesap özeti yönetimi.

**Özellikler:**
- ✅ Harcama ekleme
- ✅ Ödeme ekleme
- ✅ Bakiye takibi
- ✅ Fatura oluşturma

### Payment Tracking

**Amaç:** Ödeme takibi ve yönetimi.

**Özellikler:**
- ✅ Ödeme ekleme
- ✅ Ödeme geçmişi
- ✅ Ödeme durumu takibi
- ✅ Ödeme raporları

### Refund Yönetimi

**Amaç:** İade yönetimi.

**Özellikler:**
- ✅ İade talebi oluşturma
- ✅ İade onayı
- ✅ İade işlemi
- ✅ İade geçmişi

### Room Blocking

**Amaç:** Oda blokajı yönetimi.

**Özellikler:**
- ✅ Oda blokajı oluşturma
- ✅ Blokaj nedeni
- ✅ Blokaj süresi
- ✅ Blokaj kaldırma

### Housekeeping Coordination

**Amaç:** Kat hizmetleri ile koordinasyon (ileride).

**Özellikler:**
- ✅ Temizlik durumu takibi
- ✅ Temizlik bildirimleri
- ✅ Temizlik tamamlama bildirimleri

### Maintenance Coordination

**Amaç:** Bakım ile koordinasyon (ileride).

**Özellikler:**
- ✅ Arıza bildirimi
- ✅ Bakım durumu takibi
- ✅ Bakım tamamlama bildirimleri

---

## 🎯 Sonuç

Bu dokümantasyon, Resepsiyon modülünün tüm ek özelliklerini ve profesyonel ön büro işlevlerini detaylandırmaktadır. Tüm özellikler modüler yapıda tasarlanmıştır, böylece gelecekte yeni özellikler kolayca eklenebilir.

**Öncelik Sırası:**
1. **Yüksek Öncelik:** Erken/geç çıkış, arşivleme, takip, müşteri bilgileri, çocuk yaş kontrolü, tek ekran oda durumu
2. **Orta Öncelik:** Kaynak bazlı rezervasyonlar, comp rezervasyon, oda değişimi
3. **Düşük Öncelik:** SaaS entegrasyonları, profesyonel ön büro özellikleri

---

**Hazırlayan:** AI Assistant  
**Tarih:** 12 Kasım 2025  
**Durum:** Detaylandırıldı - Modül Oluşturma Aşaması

