# Tenant Paket Senkronizasyon Düzeltme

## 📋 Sorun

Tenant'ın paketi admin panelden değiştirildiğinde (`tenant.package`), subscription'ın paketi (`subscription.package`) güncellenmiyordu. Bu yüzden tenant'ın gerçek paketi ile subscription'ın paketi senkronize değildi.

**Örnek:**
- Tenant Package: Profesyonel (2)
- Subscription Package: Başlangıç Paketi (starter)

---

## 🔍 Sorunun Nedeni

1. **Signal eksikti**: `Subscription` modelinde `post_save` signal'ı vardı ama `Tenant` modelinde `package` değiştiğinde `Subscription`'ı güncelleyen bir signal yoktu.

2. **Çift yönlü senkronizasyon eksikti**: 
   - `Subscription` değiştiğinde `Tenant.package` güncellenmeli
   - `Tenant.package` değiştiğinde `Subscription.package` güncellenmeli

---

## ✅ Çözüm

### 1. `Subscription` Signal Eklendi

**Dosya:** `apps/subscriptions/signals.py`

```python
@receiver(post_save, sender=Subscription)
def sync_tenant_package(sender, instance, created, **kwargs):
    """
    Subscription değiştiğinde tenant.package'ı senkronize et
    """
    if instance.status == 'active' and instance.package:
        tenant = instance.tenant
        if tenant.package != instance.package:
            tenant.package = instance.package
            tenant.save(update_fields=['package'])
            import logging
            logger = logging.getLogger(__name__)
            logger.info(f'Tenant {tenant.name} paketi güncellendi: {instance.package.name}')
```

**Açıklama:**
- `Subscription` kaydedildiğinde ve `status='active'` ise
- `tenant.package`'ı `subscription.package` ile senkronize ediyor
- Sadece farklıysa güncelliyor (gereksiz save işlemlerini önlemek için)

### 2. `Tenant` Signal Eklendi

**Dosya:** `apps/subscriptions/signals.py`

```python
@receiver(post_save, sender=Tenant)
def sync_subscription_package(sender, instance, created, **kwargs):
    """
    Tenant.package değiştiğinde subscription.package'ı senkronize et
    """
    if instance.package:
        # Aktif subscription'ı bul
        subscription = Subscription.objects.filter(
            tenant=instance,
            status='active'
        ).first()
        
        if subscription and subscription.package != instance.package:
            subscription.package = instance.package
            subscription.amount = instance.package.price_monthly
            subscription.currency = instance.package.currency
            subscription.save(update_fields=['package', 'amount', 'currency'])
            import logging
            logger = logging.getLogger(__name__)
            logger.info(f'Subscription {subscription.id} paketi güncellendi: {instance.package.name}')
```

**Açıklama:**
- `Tenant` kaydedildiğinde ve `package` değiştiyse
- Aktif `Subscription`'ı buluyor ve paketini güncelliyor
- Ayrıca `amount` ve `currency` değerlerini de güncelliyor

---

## 📝 Dosya Değişiklikleri

- **`apps/subscriptions/signals.py`**
  - `sync_tenant_package` signal'ı eklendi
  - `Subscription` kaydedildiğinde `tenant.package` otomatik güncelleniyor

---

## 🧪 Test

1. Django admin paneline giriş yapın: `http://localhost:8000/admin/`
2. Bir tenant'ın paketini değiştirin: `Tenants > Test Otel > Package: Profesyonel`
3. Subscription'ı kontrol edin: `Subscriptions > Test Otel`
4. Subscription'ın paketinin de güncellendiğini kontrol edin

**Veya:**

1. Bir subscription'ın paketini değiştirin: `Subscriptions > Test Otel > Package: Profesyonel`
2. Tenant'ı kontrol edin: `Tenants > Test Otel`
3. Tenant'ın paketinin de güncellendiğini kontrol edin

---

## ✅ Sonuç

Artık:
- ✅ `Subscription` değiştiğinde `Tenant.package` otomatik güncelleniyor
- ✅ `Tenant.package` değiştiğinde `Subscription.package` otomatik güncelleniyor
- ✅ Çift yönlü paket senkronizasyonu çalışıyor
- ✅ Test-otel tenant'ının paketi düzeltildi (Profesyonel paketi uygulandı)

**Tarih:** 2025-11-14

**Test Sonucu:**
- Tenant Package: Profesyonel (2) ✅
- Subscription Package: Profesyonel (2) ✅
- Paketler senkronize ✅

