# Modül Entegrasyon Rehberi

## Genel Bakış

Bu dokümantasyon, tüm modüllerin birbiriyle nasıl entegre olduğunu ve ödeme işlemlerinin nasıl yönetildiğini açıklar.

## Entegrasyon Mimarisi

### 1. Merkezi Ödeme Sistemi

Tüm modüllerden yapılan ödemeler aşağıdaki modüllere otomatik olarak yansır:

- **Finance (Kasa) Modülü**: `CashTransaction` modeli ile kasa işlemleri kaydedilir
- **Accounting (Muhasebe) Modülü**: `Payment` ve `Invoice` modelleri ile muhasebe kayıtları tutulur
- **Raporlar**: Günlük raporlar, gelir raporları ve diğer tüm raporlar Finance modülünden veri çeker

### 2. Signal-Based Entegrasyon

Her modül, Django Signals kullanarak diğer modüllerle entegre olur:

```python
# apps/tenant_apps/[modul]/signals.py
from django.db.models.signals import pre_save, post_save
from django.dispatch import receiver

@receiver(pre_save, sender=[Modul]Reservation)
def sync_payment_to_finance_and_accounting(sender, instance, **kwargs):
    # Ödeme değişikliklerini Finance ve Accounting'e senkronize et
    pass

@receiver(post_save, sender=[Modul]Reservation)
def create_accounting_invoice_on_reservation(sender, instance, created, **kwargs):
    # Rezervasyon oluşturulduğunda fatura oluştur
    pass
```

## Modül Bazında Entegrasyon

### Reception (Resepsiyon) Modülü ✅

**Dosya:** `apps/tenant_apps/reception/signals.py`

**Entegrasyonlar:**
- ✅ **Finance**: `Reservation.total_paid` değiştiğinde `CashTransaction` oluşturulur
- ✅ **Accounting**: Rezervasyon oluşturulduğunda `Invoice`, ödeme yapıldığında `Payment` oluşturulur
- ✅ **Raporlar**: Günlük raporlar Finance modülünden veri çeker

**Kullanım:**
```python
# Rezervasyon ödemesi yapıldığında
reservation.total_paid = 1000.00
reservation.save()  # Otomatik olarak Finance ve Accounting'e yansır
```

### Tours (Tur) Modülü ✅

**Dosya:** `apps/tenant_apps/tours/signals.py`

**Entegrasyonlar:**
- ✅ **Finance**: `TourPayment` oluşturulduğunda `CashTransaction` oluşturulur
- ✅ **Accounting**: `TourReservation` oluşturulduğunda `Invoice` oluşturulur
- ✅ **Refunds**: Rezervasyon iptal edildiğinde `RefundRequest` oluşturulur

### Hotels (Otel) Modülü ⚠️

**Dosya:** `apps/tenant_apps/hotels/signals.py`

**Durum:** Signals dosyası hazır, rezervasyon modelleri oluşturulduğunda aktif hale getirilecek

## Utility Fonksiyonları

### Finance Modülü

**Dosya:** `apps/tenant_apps/finance/utils.py`

```python
from apps.tenant_apps.finance.utils import create_cash_transaction, get_default_cash_account

# Kasa işlemi oluştur
create_cash_transaction(
    account_id=1,
    transaction_type='income',  # 'income', 'expense', 'transfer', 'adjustment'
    amount=1000.00,
    source_module='reception',  # Modül kodu
    source_id=123,  # Rezervasyon ID
    source_reference='Rezervasyon: RES-2025-0001',
    description='Otel rezervasyon ödemesi',
    payment_method='cash',  # 'cash', 'bank_transfer', 'credit_card', vb.
    currency='TRY',
    created_by=request.user,
    status='completed',
)

# Varsayılan kasa hesabını al
account = get_default_cash_account(currency='TRY')
```

### Accounting Modülü

**Dosya:** `apps/tenant_apps/accounting/utils.py`

```python
from apps.tenant_apps.accounting.utils import create_invoice, create_payment

# Fatura oluştur
invoice = create_invoice(
    invoice_type='sales',
    customer_name='Ahmet Yılmaz',
    total_amount=1000.00,
    source_module='reception',
    source_id=123,
    source_reference='Rezervasyon: RES-2025-0001',
    invoice_date=timezone.now().date(),
    currency='TRY',
    created_by=request.user,
    lines_data=[
        {
            'item_name': 'Otel Rezervasyonu',
            'item_code': 'RES-2025-0001',
            'quantity': 3,
            'unit_price': 333.33,
            'line_total': 1000.00,
            'description': '3 gece konaklama',
        }
    ],
)

# Ödeme kaydı oluştur
payment = create_payment(
    amount=1000.00,
    invoice_id=invoice.pk,
    source_module='reception',
    source_id=123,
    source_reference='Rezervasyon: RES-2025-0001',
    currency='TRY',
    payment_method='cash',
    cash_account_id=1,
    created_by=request.user,
    auto_complete=True,  # Otomatik olarak tamamlandı olarak işaretle
)
```

## Raporlama Entegrasyonu

### Günlük Raporlar

Tüm günlük raporlar Finance modülünden veri çeker:

```python
from apps.tenant_apps.finance.models import CashTransaction

# Günlük ödemeler
daily_payments = CashTransaction.objects.filter(
    source_module='reception',  # veya 'tours', 'hotels', vb.
    payment_date__date=report_date,
    transaction_type='income',
    status='completed',
    is_deleted=False
)

total_daily_income = daily_payments.aggregate(
    total=Sum('amount')
)['total'] or Decimal('0')
```

### Gelir Raporları

Gelir raporları tüm modüllerden gelen ödemeleri toplar:

```python
from apps.tenant_apps.finance.models import CashTransaction

# Tüm modüllerden gelen gelirler
all_income = CashTransaction.objects.filter(
    payment_date__date__gte=date_from,
    payment_date__date__lte=date_to,
    transaction_type='income',
    status='completed',
    is_deleted=False
)

# Modül bazında grupla
by_module = all_income.values('source_module').annotate(
    total=Sum('amount'),
    count=Count('id')
)
```

## Yeni Modül Entegrasyonu

Yeni bir modül eklerken aşağıdaki adımları izleyin:

### 1. Signals Dosyası Oluştur

```python
# apps/tenant_apps/[yeni_modul]/signals.py
from django.db.models.signals import pre_save, post_save
from django.dispatch import receiver
from decimal import Decimal
from .models import [Modul]Reservation

@receiver(pre_save, sender=[Modul]Reservation)
def sync_payment_to_finance_and_accounting(sender, instance, **kwargs):
    """Ödeme değişikliklerini Finance ve Accounting'e senkronize et"""
    if not instance.pk:
        return
    
    try:
        old_instance = [Modul]Reservation.objects.get(pk=instance.pk)
        old_total_paid = old_instance.total_paid or Decimal('0')
        new_total_paid = instance.total_paid or Decimal('0')
        
        payment_difference = new_total_paid - old_total_paid
        
        if payment_difference > 0:
            # Finance entegrasyonu
            from apps.tenant_apps.finance.utils import create_cash_transaction, get_default_cash_account
            
            account = get_default_cash_account(currency=instance.currency)
            if account:
                create_cash_transaction(
                    account_id=account.pk,
                    transaction_type='income',
                    amount=payment_difference,
                    source_module='[yeni_modul]',  # Modül kodu
                    source_id=instance.pk,
                    source_reference=f"[Modul] Rezervasyon: {instance.reservation_code}",
                    description=f"[Modul] rezervasyon ödemesi",
                    payment_method='cash',
                    currency=instance.currency,
                    created_by=instance.created_by,
                    status='completed',
                )
            
            # Accounting entegrasyonu
            from apps.tenant_apps.accounting.utils import create_payment
            from apps.tenant_apps.accounting.models import Invoice
            
            invoice = Invoice.objects.filter(
                source_module='[yeni_modul]',
                source_id=instance.pk,
                is_deleted=False
            ).first()
            
            if invoice:
                create_payment(
                    amount=payment_difference,
                    invoice_id=invoice.pk,
                    source_module='[yeni_modul]',
                    source_id=instance.pk,
                    source_reference=f"[Modul] Rezervasyon: {instance.reservation_code}",
                    currency=instance.currency,
                    payment_method='cash',
                    cash_account_id=account.pk if account else None,
                    created_by=instance.created_by,
                    auto_complete=True,
                )
    except [Modul]Reservation.DoesNotExist:
        pass

@receiver(post_save, sender=[Modul]Reservation)
def create_accounting_invoice_on_reservation(sender, instance, created, **kwargs):
    """Rezervasyon oluşturulduğunda fatura oluştur"""
    if created and instance.total_amount > 0:
        try:
            from apps.tenant_apps.accounting.models import Invoice
            from apps.tenant_apps.accounting.utils import create_invoice
            
            existing_invoice = Invoice.objects.filter(
                source_module='[yeni_modul]',
                source_id=instance.pk,
                is_deleted=False
            ).first()
            
            if not existing_invoice:
                create_invoice(
                    invoice_type='sales',
                    customer_name=f"{instance.customer_name}",
                    total_amount=instance.total_amount,
                    source_module='[yeni_modul]',
                    source_id=instance.pk,
                    source_reference=f"[Modul] Rezervasyon: {instance.reservation_code}",
                    invoice_date=timezone.now().date(),
                    currency=instance.currency,
                    created_by=instance.created_by,
                    lines_data=[...],
                )
        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.warning(f'Accounting modülü entegrasyonu başarısız: {str(e)}')
```

### 2. Apps.py'da Signals'ı Yükle

```python
# apps/tenant_apps/[yeni_modul]/apps.py
class [Modul]Config(AppConfig):
    def ready(self):
        import apps.tenant_apps.[yeni_modul].signals  # noqa
```

### 3. Raporları Güncelle

Raporlarınızda Finance modülünden veri çekin:

```python
from apps.tenant_apps.finance.models import CashTransaction

# Modül bazında ödemeler
module_payments = CashTransaction.objects.filter(
    source_module='[yeni_modul]',
    payment_date__date=report_date,
    transaction_type='income',
    status='completed',
    is_deleted=False
)
```

## Modül Kodları

Her modül için benzersiz bir kod kullanılmalıdır:

- `reception` - Resepsiyon (Ön Büro)
- `tours` - Tur Modülü
- `hotels` - Otel Modülü
- `villas` - Villa Modülü
- `bungalows` - Bungalov Modülü
- `timeshare` - Devre Mülk Modülü
- `ferry` - Feribot Bileti Modülü
- `agency` - Acente Modülü
- `call_center` - Call Center Modülü
- `sales` - Satış Yönetimi Modülü

## Önemli Notlar

1. **Signal Sırası**: `pre_save` signal'ı `post_save`'den önce çalışır. Ödeme senkronizasyonu için `pre_save` kullanın.

2. **Hata Yönetimi**: Tüm entegrasyonlar try-except blokları içinde olmalıdır. Modül yoksa veya hata oluşursa sessizce geçmelidir.

3. **Veri Tutarlılığı**: Finance ve Accounting modüllerindeki veriler her zaman senkronize olmalıdır.

4. **Raporlar**: Tüm raporlar Finance modülünden veri çekmelidir. Modül bazlı raporlar `source_module` filtresi kullanmalıdır.

5. **Ödeme Yöntemi**: Ödeme yöntemi bilgisi formdan alınmalı ve Finance/Accounting modüllerine aktarılmalıdır.

## Test Edilmesi Gerekenler

- [ ] Reception modülü → Finance entegrasyonu
- [ ] Reception modülü → Accounting entegrasyonu
- [ ] Reception modülü → Günlük raporlar
- [ ] Reception modülü → Gelir raporları
- [ ] Tours modülü → Finance entegrasyonu (mevcut)
- [ ] Tours modülü → Accounting entegrasyonu (mevcut)
- [ ] Yeni modül entegrasyonu (gelecek modüller için)

## Sorun Giderme

### Ödeme Finance Modülüne Yansımıyor

1. Signals dosyasının `apps.py`'da yüklendiğinden emin olun
2. `total_paid` alanının değiştiğinden emin olun
3. Finance modülünün aktif olduğundan emin olun
4. Varsayılan kasa hesabının tanımlı olduğundan emin olun

### Ödeme Accounting Modülüne Yansımıyor

1. Fatura oluşturulduğundan emin olun
2. `create_payment` fonksiyonunun çağrıldığından emin olun
3. Accounting modülünün aktif olduğundan emin olun

### Raporlarda Veri Görünmüyor

1. Finance modülünden veri çekildiğinden emin olun
2. `source_module` filtresinin doğru olduğundan emin olun
3. `status='completed'` filtresinin eklendiğinden emin olun
