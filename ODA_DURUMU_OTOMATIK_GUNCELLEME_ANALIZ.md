# 🏨 Oda Durumu Otomatik Güncelleme Sistemi - Analiz ve Açıklama

> **Tarih:** 2025-11-13  
> **Durum:** Mevcut Sistem Analizi ve İyileştirme Önerileri

---

## 📋 Mevcut Sistem Analizi

### 1. Oda Durumları (RoomNumberStatus)

**Dosya:** `apps/tenant_apps/hotels/models.py`

```python
class RoomNumberStatus(models.TextChoices):
    AVAILABLE = 'available', 'Boş'                    # Müsait, rezervasyon yapılabilir
    OCCUPIED = 'occupied', 'Dolu'                    # Müşteri var, dolu
    CLEAN = 'clean', 'Temiz'                         # Temiz, ama henüz müsait değil
    DIRTY = 'dirty', 'Kirli'                         # Kirli
    CLEANING_PENDING = 'cleaning_pending', 'Temizlik Bekliyor'  # Temizlik bekliyor
    MAINTENANCE = 'maintenance', 'Bakımda'           # Bakımda
    OUT_OF_ORDER = 'out_of_order', 'Hizmet Dışı'     # Hizmet dışı
```

### 2. Rezervasyon Durumları (ReservationStatus)

**Dosya:** `apps/tenant_apps/reception/models.py`

```python
class ReservationStatus(models.TextChoices):
    PENDING = 'pending', 'Beklemede'
    CONFIRMED = 'confirmed', 'Onaylandı'
    CHECKED_IN = 'checked_in', 'Check-In Yapıldı'
    CHECKED_OUT = 'checked_out', 'Check-Out Yapıldı'
    CANCELLED = 'cancelled', 'İptal Edildi'
    NO_SHOW = 'no_show', 'Gelmedi'
```

---

## 🔍 Mevcut Durum - Sorunlar

### ❌ Sorun 1: Check-In Yapıldığında Oda Durumu Güncellenmiyor

**Dosya:** `apps/tenant_apps/reception/views.py` - `reservation_checkin` (Satır 1277-1303)

**Mevcut Kod:**
```python
if request.method == 'POST':
    reservation.is_checked_in = True
    reservation.status = ReservationStatus.CHECKED_IN
    reservation.checked_in_at = timezone.now()
    reservation.save()  # ❌ Oda durumu güncellenmiyor!
```

**Beklenen Davranış:**
- Rezervasyonda `room_number` varsa
- Oda durumu `OCCUPIED` (Dolu) olmalı

**Eksik:** Oda durumu güncellemesi yok!

---

### ❌ Sorun 2: Check-Out Yapıldığında Oda Durumu Güncellenmiyor

**Dosya:** `apps/tenant_apps/reception/views.py` - `reservation_checkout` (Satır 1308-1334)

**Mevcut Kod:**
```python
if request.method == 'POST':
    reservation.is_checked_out = True
    reservation.status = ReservationStatus.CHECKED_OUT
    reservation.checked_out_at = timezone.now()
    reservation.save()  # ❌ Oda durumu güncellenmiyor!
```

**Beklenen Davranış:**
- Rezervasyonda `room_number` varsa
- Oda durumu `CLEANING_PENDING` (Temizlik Bekliyor) olmalı

**Eksik:** Oda durumu güncellemesi yok!

---

### ⚠️ Sorun 3: Housekeeping Temizlik Tamamlandığında Yetersiz Güncelleme

**Dosya:** `apps/tenant_apps/housekeeping/views.py` - `task_complete` (Satır 306-336)

**Mevcut Kod:**
```python
# Oda durumunu güncelle
task.room_number.status = RoomNumberStatus.CLEAN
task.room_number.save()
```

**Sorun:**
- Oda durumu `CLEAN` oluyor ✅
- Ama `AVAILABLE` (Müsait) olmuyor ❌
- Rezervasyon kontrolü yapılmıyor ❌

**Beklenen Davranış:**
- Temizlik tamamlandığında
- Oda durumu `CLEAN` olmalı
- Eğer o tarihte rezervasyon yoksa → `AVAILABLE` olmalı
- Eğer o tarihte rezervasyon varsa → `OCCUPIED` olmalı (check-in bekliyor)

---

### ❌ Sorun 4: Signal'lar Oda Durumunu Güncellemiyor

**Dosya:** `apps/tenant_apps/reception/signals.py`

**Mevcut Signal'lar:**
- ✅ Finance entegrasyonu var
- ✅ Accounting entegrasyonu var
- ✅ Refunds entegrasyonu var
- ✅ Bildirim entegrasyonu var
- ❌ **Oda durumu güncellemesi YOK!**

---

## 🎯 Beklenen İş Akışı

### Senaryo 1: Check-In İşlemi

```
1. Rezervasyon oluşturuldu → status: CONFIRMED
   - Oda durumu: AVAILABLE (henüz check-in yapılmadı)

2. Check-in yapıldı → status: CHECKED_IN
   - Oda durumu: OCCUPIED (Dolu) ✅
   - Oda numarası atandıysa güncellenmeli
```

### Senaryo 2: Check-Out İşlemi

```
1. Müşteri odada → status: CHECKED_IN
   - Oda durumu: OCCUPIED (Dolu)

2. Check-out yapıldı → status: CHECKED_OUT
   - Oda durumu: CLEANING_PENDING (Temizlik Bekliyor) ✅
   - Housekeeping'e temizlik görevi oluşturulmalı (opsiyonel)
```

### Senaryo 3: Housekeeping Temizlik

```
1. Oda temizlik bekliyor → status: CLEANING_PENDING
   - Temizlik görevi oluşturuldu

2. Temizlik başladı → status: CLEANING (opsiyonel)
   - Görev durumu: in_progress

3. Temizlik tamamlandı → status: CLEAN
   - Görev durumu: completed
   - Oda durumu kontrolü:
     - Bugün rezervasyon var mı? → Varsa: OCCUPIED, Yoksa: AVAILABLE
     - Yarın rezervasyon var mı? → Varsa: CLEAN (hazır), Yoksa: AVAILABLE
```

---

## 🔧 Çözüm Önerileri

### Çözüm 1: Check-In View'ında Oda Durumu Güncelleme

**Dosya:** `apps/tenant_apps/reception/views.py` - `reservation_checkin`

```python
if request.method == 'POST':
    reservation.is_checked_in = True
    reservation.status = ReservationStatus.CHECKED_IN
    reservation.checked_in_at = timezone.now()
    reservation.save()
    
    # ✅ Oda durumunu güncelle
    if reservation.room_number:
        from apps.tenant_apps.hotels.models import RoomNumberStatus
        reservation.room_number.status = RoomNumberStatus.OCCUPIED
        reservation.room_number.save()
```

### Çözüm 2: Check-Out View'ında Oda Durumu Güncelleme

**Dosya:** `apps/tenant_apps/reception/views.py` - `reservation_checkout`

```python
if request.method == 'POST':
    reservation.is_checked_out = True
    reservation.status = ReservationStatus.CHECKED_OUT
    reservation.checked_out_at = timezone.now()
    reservation.save()
    
    # ✅ Oda durumunu güncelle
    if reservation.room_number:
        from apps.tenant_apps.hotels.models import RoomNumberStatus
        reservation.room_number.status = RoomNumberStatus.CLEANING_PENDING
        reservation.room_number.save()
        
        # ✅ Housekeeping'e temizlik görevi oluştur (opsiyonel)
        # create_cleaning_task_for_checkout(reservation.room_number)
```

### Çözüm 3: Housekeeping Temizlik Tamamlandığında Akıllı Güncelleme

**Dosya:** `apps/tenant_apps/housekeeping/views.py` - `task_complete`

```python
# Oda durumunu akıllı güncelle
from apps.tenant_apps.hotels.models import RoomNumberStatus
from apps.tenant_apps.reception.models import Reservation
from datetime import date

today = date.today()

# Bugün veya yarın rezervasyon var mı?
has_reservation = Reservation.objects.filter(
    room_number=task.room_number,
    check_in_date__lte=today + timedelta(days=1),
    check_out_date__gte=today,
    status__in=['confirmed', 'checked_in'],
    is_deleted=False
).exists()

if has_reservation:
    # Rezervasyon var → Dolu veya Hazır
    task.room_number.status = RoomNumberStatus.OCCUPIED
else:
    # Rezervasyon yok → Müsait
    task.room_number.status = RoomNumberStatus.AVAILABLE

task.room_number.save()
```

### Çözüm 4: Signal ile Otomatik Güncelleme (Önerilen)

**Dosya:** `apps/tenant_apps/reception/signals.py`

```python
@receiver(post_save, sender=Reservation)
def update_room_status_on_reservation_change(sender, instance, created, **kwargs):
    """
    Rezervasyon durumu değiştiğinde oda durumunu otomatik güncelle
    """
    if not instance.room_number:
        return  # Oda numarası yoksa işlem yapma
    
    from apps.tenant_apps.hotels.models import RoomNumberStatus
    from datetime import date
    
    # Check-in yapıldıysa
    if instance.is_checked_in and instance.status == ReservationStatus.CHECKED_IN:
        instance.room_number.status = RoomNumberStatus.OCCUPIED
        instance.room_number.save()
    
    # Check-out yapıldıysa
    elif instance.is_checked_out and instance.status == ReservationStatus.CHECKED_OUT:
        instance.room_number.status = RoomNumberStatus.CLEANING_PENDING
        instance.room_number.save()
    
    # Rezervasyon iptal edildiyse
    elif instance.status == ReservationStatus.CANCELLED:
        # Bugün rezervasyon var mı kontrol et
        today = date.today()
        has_other_reservation = Reservation.objects.filter(
            room_number=instance.room_number,
            check_in_date__lte=today,
            check_out_date__gte=today,
            status__in=['confirmed', 'checked_in'],
            is_deleted=False
        ).exclude(pk=instance.pk).exists()
        
        if not has_other_reservation:
            instance.room_number.status = RoomNumberStatus.AVAILABLE
            instance.room_number.save()
```

---

## 📊 Oda Durumu Geçiş Diyagramı

```
AVAILABLE (Boş)
    ↓
    [Check-In Yapıldı]
    ↓
OCCUPIED (Dolu)
    ↓
    [Check-Out Yapıldı]
    ↓
CLEANING_PENDING (Temizlik Bekliyor)
    ↓
    [Housekeeping Temizlik Başladı]
    ↓
CLEANING (Temizleniyor) [Opsiyonel]
    ↓
    [Housekeeping Temizlik Tamamlandı]
    ↓
CLEAN (Temiz) veya AVAILABLE (Müsait)
    ↓
    [Rezervasyon Kontrolü]
    ├─ Bugün/Yarın Rezervasyon Var → OCCUPIED
    └─ Rezervasyon Yok → AVAILABLE
```

---

## 🎯 Önerilen İyileştirmeler

### 1. Check-In/Check-Out View'larına Oda Durumu Güncelleme Ekle
- ✅ Check-in → OCCUPIED
- ✅ Check-out → CLEANING_PENDING

### 2. Housekeeping Temizlik Tamamlandığında Akıllı Güncelleme
- ✅ Rezervasyon kontrolü yap
- ✅ Varsa: OCCUPIED, Yoksa: AVAILABLE

### 3. Signal ile Otomatik Güncelleme
- ✅ Rezervasyon durumu değiştiğinde otomatik güncelle
- ✅ İptal durumunda da kontrol et

### 4. Rezervasyon Oluşturulduğunda Oda Durumu Kontrolü
- ✅ Oda seçildiğinde durum kontrolü
- ✅ Dolu oda seçilemez (validation)

---

## 📝 Sonuç

**Mevcut Durum:**
- ❌ Check-in/check-out'ta oda durumu güncellenmiyor
- ⚠️ Housekeeping'de yetersiz güncelleme
- ❌ Signal'lar oda durumunu güncellemiyor

**Gerekli Değişiklikler:**
1. `reservation_checkin` view'ına oda durumu güncelleme ekle
2. `reservation_checkout` view'ına oda durumu güncelleme ekle
3. `task_complete` view'ında akıllı güncelleme yap
4. Signal ekle (opsiyonel ama önerilen)

---

**📅 Analiz Tarihi:** 2025-11-13  
**✍️ Analiz Eden:** AI Assistant





