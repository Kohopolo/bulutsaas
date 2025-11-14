# Ödeme İade Silme Entegrasyon Planı

**Tarih:** 2025-01-XX  
**Modüller:** Reception, Tours, Ferry Tickets

---

## 📋 İş Akışı Mantığı

### Senaryo 1: Ödeme Yok
```
Silme İsteği → Ödeme Kontrolü (total_paid = 0) → ✅ Direkt Silme
```

### Senaryo 2: Ödeme Var, İade Yok
```
Silme İsteği → Ödeme Kontrolü (total_paid > 0) → İade Kontrolü (yok) 
→ ❌ Silme Engellendi → İade Süreci Başlatıldı → Kullanıcıya Bilgi
```

### Senaryo 3: Ödeme Var, İade Tamamlandı
```
Silme İsteği → Ödeme Kontrolü (total_paid > 0) → İade Kontrolü (completed) 
→ ✅ Silme Yapılabilir
```

### Senaryo 4: Ödeme Var, İade Beklemede
```
Silme İsteği → Ödeme Kontrolü (total_paid > 0) → İade Kontrolü (pending/processing) 
→ ❌ Silme Engellendi → "İade tamamlanana kadar bekleyin" mesajı
```

---

## 🏗️ Mimari Tasarım

### 1. Utility Fonksiyonları (Ortak)

**Dosya:** `apps/tenant_apps/core/utils.py` veya her modülde ayrı

```python
def can_delete_with_payment_check(obj, source_module):
    """
    Ödeme kontrolü ile silme yapılabilir mi kontrol et
    
    Args:
        obj: Reservation, TourReservation veya FerryTicket objesi
        source_module: 'reception', 'tours', 'ferry_tickets'
    
    Returns:
        dict: {
            'can_delete': bool,
            'has_payment': bool,
            'refund_status': str or None,
            'refund_request_id': int or None,
            'message': str
        }
    """
```

### 2. İade Durumu Kontrolü

**RefundRequest modeli kullanılacak:**
- `source_module`: 'reception', 'tours', 'ferry_tickets'
- `source_id`: Rezervasyon/Bilet ID
- `status`: 'pending', 'approved', 'processing', 'completed'

### 3. Silme View Güncellemeleri

Her modülde silme view'ı güncellenecek:
- Ödeme kontrolü eklenecek
- İade kontrolü eklenecek
- İade yoksa iade süreci başlatılacak
- İade tamamlandıysa silme yapılacak

---

## 📝 Uygulama Planı

### Adım 1: Utility Fonksiyonları Oluştur
- [ ] `can_delete_with_payment_check()` fonksiyonu
- [ ] `start_refund_process_for_deletion()` fonksiyonu
- [ ] `check_refund_status()` fonksiyonu

### Adım 2: Reception Modülü Güncelleme
- [ ] `reservation_delete` view'ı güncelle
- [ ] Ödeme kontrolü ekle
- [ ] İade kontrolü ekle
- [ ] İade süreci başlatma ekle

### Adım 3: Tours Modülü Güncelleme
- [ ] `tour_reservation_delete` view'ı bul/güncelle
- [ ] Ödeme kontrolü ekle
- [ ] İade kontrolü ekle
- [ ] İade süreci başlatma ekle

### Adım 4: Ferry Tickets Modülü Güncelleme
- [ ] `ticket_delete` view'ı güncelle
- [ ] Ödeme kontrolü ekle
- [ ] İade kontrolü ekle
- [ ] İade süreci başlatma ekle

### Adım 5: Signal Entegrasyonu
- [ ] İade tamamlandığında silme izni veren signal
- [ ] RefundTransaction.completed signal'i

### Adım 6: Template Güncellemeleri
- [ ] Silme modal'larına ödeme/iade durumu bilgisi
- [ ] İade süreci başlatma butonu
- [ ] İade durumu gösterimi

---

## 🔧 Teknik Detaylar

### Utility Fonksiyon Yapısı

```python
def can_delete_with_payment_check(obj, source_module):
    """
    Ödeme kontrolü ile silme yapılabilir mi?
    
    Returns:
        {
            'can_delete': False,
            'has_payment': True,
            'refund_status': 'pending',
            'refund_request_id': 123,
            'message': 'Ödeme alınmış. İade tamamlanana kadar silme yapılamaz.',
            'refund_request': RefundRequest objesi
        }
    """
    from apps.tenant_apps.refunds.models import RefundRequest
    
    # Ödeme kontrolü
    total_paid = getattr(obj, 'total_paid', 0) or Decimal('0')
    has_payment = total_paid > 0
    
    if not has_payment:
        return {
            'can_delete': True,
            'has_payment': False,
            'refund_status': None,
            'refund_request_id': None,
            'message': 'Ödeme yok, silme yapılabilir.',
            'refund_request': None
        }
    
    # İade kontrolü
    refund_request = RefundRequest.objects.filter(
        source_module=source_module,
        source_id=obj.pk,
        is_deleted=False
    ).order_by('-created_at').first()
    
    if not refund_request:
        return {
            'can_delete': False,
            'has_payment': True,
            'refund_status': None,
            'refund_request_id': None,
            'message': 'Ödeme alınmış. Silme için önce iade yapılmalı.',
            'refund_request': None
        }
    
    # İade durumu kontrolü
    if refund_request.status == 'completed':
        return {
            'can_delete': True,
            'has_payment': True,
            'refund_status': 'completed',
            'refund_request_id': refund_request.pk,
            'message': 'İade tamamlandı, silme yapılabilir.',
            'refund_request': refund_request
        }
    else:
        return {
            'can_delete': False,
            'has_payment': True,
            'refund_status': refund_request.status,
            'refund_request_id': refund_request.pk,
            'message': f'İade durumu: {refund_request.get_status_display()}. İade tamamlanana kadar silme yapılamaz.',
            'refund_request': refund_request
        }
```

---

## 📊 Durum Diyagramı

```
[Silme İsteği]
    ↓
[Ödeme Var mı?]
    ├─ Hayır → ✅ [Direkt Silme]
    └─ Evet → [İade Var mı?]
              ├─ Hayır → ❌ [İade Süreci Başlat] → [Kullanıcıya Bilgi]
              └─ Evet → [İade Durumu?]
                        ├─ completed → ✅ [Silme Yapılabilir]
                        └─ pending/processing → ❌ [İade Tamamlanana Kadar Bekle]
```

---

## 🎯 Sonuç

Bu entegrasyon ile:
- ✅ Ödeme alınmış rezervasyon/biletler güvenli şekilde silinecek
- ✅ İade süreci otomatik başlatılacak
- ✅ İade tamamlandıktan sonra silme yapılabilecek
- ✅ Kullanıcıya net bilgi verilecek

---

**Durum:** Planlama Tamamlandı - Uygulama Bekliyor

