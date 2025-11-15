# Hotel Değeri Otomatik Atama - Tamamlandı

## 📋 Sorun

Tüm modüllerde işlemler oluşturulurken `hotel` değeri veritabanına kaydedilmiyordu. Bu yüzden filtreleme çalışmıyordu ve bir otelin kayıtları diğer otelde de görünüyordu.

**Örnek:**
- Test Otel için bir iade talebi oluşturuldu (hotel=None)
- Test Otel 2'ye geçildiğinde bu iade talebi görünüyordu (yanlış!)

---

## ✅ Çözüm

Muhasebe modülündeki çözüm yaklaşımı tüm modüllere uygulandı:

### 1. Utility Fonksiyonlarına Hotel Parametresi Eklendi

**`apps/tenant_apps/refunds/utils.py`** - `create_refund_request` fonksiyonu:
- ✅ `hotel` parametresi eklendi
- ✅ Eğer `hotel` verilmemişse, `source_module` ve `source_id`'den otomatik olarak hotel bilgisi çıkarılıyor
- ✅ `reception`, `tours`, `ferry_tickets`, `bungalovs` modüllerinden hotel bilgisi otomatik alınıyor

```python
def create_refund_request(
    ...
    hotel=None,  # Otel bilgisi eklendi
    **kwargs
):
    # Hotel bilgisini source_module'den çıkar (eğer verilmemişse)
    if not hotel and source_module and source_id:
        try:
            if source_module == 'reception':
                from apps.tenant_apps.reception.models import Reservation
                source_obj = Reservation.objects.filter(pk=source_id).first()
                if source_obj and hasattr(source_obj, 'hotel'):
                    hotel = source_obj.hotel
            # ... diğer modüller
        except Exception:
            pass
    
    refund_request = RefundRequest.objects.create(
        hotel=hotel,  # Otel bilgisi eklendi
        ...
    )
```

### 2. Core Utility Fonksiyonlarına Hotel Eklendi

**`apps/tenant_apps/core/utils.py`** - `start_refund_process_for_deletion` fonksiyonu:
- ✅ Obj'den hotel bilgisi alınıyor
- ✅ `create_refund_request`'e `hotel` parametresi geçiliyor

```python
def start_refund_process_for_deletion(obj, source_module, user, reason='...'):
    # Hotel bilgisini al
    hotel = None
    if hasattr(obj, 'hotel'):
        hotel = obj.hotel
    
    refund_request = create_refund_request(
        ...
        hotel=hotel,  # Otel bilgisi eklendi
    )
```

### 3. Tüm create_refund_request Çağrıları Güncellendi

**Düzeltilen Dosyalar:**
- ✅ `apps/tenant_apps/reception/views.py` - `reservation_refund` view'ında
- ✅ `apps/tenant_apps/tours/signals.py` - `create_refund_request_on_cancellation` signal'ında
- ✅ `apps/tenant_apps/hotels/signals.py` - Signal'da

```python
# Örnek: reception/views.py
refund_request = create_refund_request(
    ...
    hotel=reservation.hotel,  # Otel bilgisi eklendi
)

# Örnek: tours/signals.py
create_refund_request(
    ...
    hotel=getattr(instance, 'hotel', None),  # Otel bilgisi eklendi
)
```

### 4. View'larda Otomatik Hotel Atama (Zaten Mevcut)

Tüm modüllerdeki create view'larında zaten mevcut pattern:

```python
def model_create(request):
    if request.method == 'POST':
        form = ModelForm(request.POST)
        if form.is_valid():
            instance = form.save(commit=False)
            # Eğer hotel seçilmemişse ve aktif otel varsa, aktif oteli ata
            if not instance.hotel and hasattr(request, 'active_hotel') and request.active_hotel:
                instance.hotel = request.active_hotel
            instance.save()
    else:
        form = ModelForm()
        # Varsayılan olarak aktif oteli seç
        if hasattr(request, 'active_hotel') and request.active_hotel:
            form.fields['hotel'].initial = request.active_hotel
```

**Kontrol Edilen Modüller:**
- ✅ **Accounting** - `account_create`, `journal_entry_create`, `invoice_create`, `payment_create`
- ✅ **Finance** - `account_create`, `transaction_create`
- ✅ **Refunds** - `policy_create`, `request_create`
- ✅ **Housekeeping** - `task_create`, `missing_item_create`, `maintenance_request_create` (zaten aktif otel kontrolü var)
- ✅ **Technical Service** - `request_create` (zaten aktif otel kontrolü var)
- ✅ **Ferry Tickets** - `ticket_create` (zaten aktif otel kontrolü var)

---

## 🎯 Sonuç

Artık:
- ✅ Tüm işlemler oluşturulurken `hotel` değeri otomatik olarak atanıyor
- ✅ `create_refund_request` fonksiyonu `source_module`'den hotel bilgisini otomatik çıkarıyor
- ✅ View'larda aktif otel otomatik atanıyor
- ✅ Signal'lerde hotel bilgisi geçiliyor
- ✅ Filtreleme doğru çalışıyor

**Tarih:** 2025-11-14

**Not:** Eğer bir kayıt manuel olarak oluşturuluyorsa ve `hotel` seçilmemişse, sistem aktif oteli otomatik olarak atar. Eğer `source_module` ve `source_id` varsa, sistem kaynak objeden hotel bilgisini otomatik olarak çıkarır.

