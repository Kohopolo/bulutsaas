# 📱 SMS Modülü Kullanım Rehberi

## 🎯 Genel Bakış

Settings modülü içinde SMS entegrasyonu sistemi kurulmuştur. Bu sistem Twilio, NetGSM ve Verimor SMS gateway'lerini destekler ve dinamik şablon yönetimi sunar.

## 🚀 Hızlı Başlangıç

### 1. SMS Gateway Oluşturma

#### Web Arayüzü Üzerinden

1. **Gateway Ekleme Sayfasına Gidin**:
   ```
   http://test-otel.localhost:8000/settings/sms-gateways/create/
   ```

2. **Gateway Bilgilerini Doldurun**:
   - **Gateway Adı**: Örn: "NetGSM Ana Hesap"
   - **Gateway Tipi**: Twilio, NetGSM veya Verimor seçin
   - **API Bilgileri**: Dinamik form alanları otomatik oluşur
   - **Gönderen ID**: SMS gönderen numarası veya başlık
   - **Aktif mi?**: Gateway'i aktif edin
   - **Varsayılan Gateway mi?**: İlk gateway'i varsayılan yapın

3. **Kaydedin**: Form gönderildiğinde gateway kaydedilir

#### Python Kodu ile

```python
from apps.tenant_apps.settings.models import SMSGateway

# NetGSM Gateway Örneği
gateway = SMSGateway.objects.create(
    name='NetGSM Production',
    gateway_type='netgsm',
    api_credentials={
        'username': 'kullanici_adi',
        'password': 'sifre',
        'api_key': 'api_anahtari'  # Opsiyonel
    },
    sender_id='NETGSM',
    default_country_code='+90',
    is_active=True,
    is_default=True
)

# Twilio Gateway Örneği
gateway = SMSGateway.objects.create(
    name='Twilio Production',
    gateway_type='twilio',
    api_credentials={
        'account_sid': 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'auth_token': 'your_auth_token'
    },
    sender_id='+1234567890',
    default_country_code='+90',
    is_active=True,
    is_default=False
)

# Verimor Gateway Örneği
gateway = SMSGateway.objects.create(
    name='Verimor Production',
    gateway_type='verimor',
    api_credentials={
        'username': 'kullanici_adi',
        'password': 'sifre'
    },
    sender_id='VERIMOR',
    default_country_code='+90',
    is_active=True,
    is_default=False
)
```

### 2. SMS Gönderme

#### Direkt SMS Gönderme

```python
from apps.tenant_apps.settings.utils import send_sms

# Basit SMS gönderimi
result = send_sms(
    phone='05551234567',
    message='Merhaba, bu bir test mesajıdır.'
)

if result['success']:
    print(f"SMS gönderildi! Log ID: {result['log_id']}")
    print(f"Gateway: {result['gateway_name']}")
    print(f"Mesaj ID: {result['message_id']}")
else:
    print(f"Hata: {result['error']}")
    print(f"Hata Kodu: {result.get('error_code', 'N/A')}")
```

#### Şablon ile SMS Gönderme

```python
from apps.tenant_apps.settings.utils import send_sms_by_template

# Rezervasyon onayı SMS'i
result = send_sms_by_template(
    template_code='reservation_confirmation',
    phone='05551234567',
    context={
        'guest_name': 'Ahmet Yılmaz',
        'check_in_date': '20.11.2025',
        'reservation_number': 'RES-2025-001'
    },
    related_module='reception',
    related_object_id=123,
    related_object_type='Reservation'
)

if result['success']:
    print(f"SMS gönderildi! Log ID: {result['log_id']}")
else:
    print(f"Hata: {result['error']}")
```

### 3. SMS Şablonları

#### Mevcut Varsayılan Şablonlar

1. **`reservation_confirmation`** - Rezervasyon Onayı
   - Değişkenler: `guest_name`, `check_in_date`, `reservation_number`
   - Modül: `reception`

2. **`checkin_reminder`** - Check-in Hatırlatma
   - Değişkenler: `guest_name`, `check_in_date`, `reservation_number`
   - Modül: `reception`

3. **`checkout_reminder`** - Check-out Hatırlatma
   - Değişkenler: `guest_name`, `check_out_date`
   - Modül: `reception`

4. **`payment_confirmation`** - Ödeme Onayı
   - Değişkenler: `guest_name`, `amount`, `currency`, `payment_number`
   - Modül: `payment_management`

5. **`ferry_ticket_confirmation`** - Feribot Bileti Onayı
   - Değişkenler: `passenger_name`, `route_name`, `departure_date`, `departure_time`, `ticket_number`
   - Modül: `ferry_tickets`

#### Yeni Şablon Oluşturma

**Web Arayüzü Üzerinden**:
```
http://test-otel.localhost:8000/settings/sms-templates/create/
```

**Python Kodu ile**:
```python
from apps.tenant_apps.settings.models import SMSTemplate

template = SMSTemplate.objects.create(
    name='Özel Bildirim',
    code='custom_notification',
    category='notification',
    template_text='Sayın {{customer_name}}, {{message}}. Teşekkürler!',
    available_variables={
        'customer_name': 'Müşteri Adı',
        'message': 'Mesaj İçeriği'
    },
    module_usage='custom_module',
    description='Özel bildirim şablonu',
    is_active=True
)
```

## 📋 API Referansı

### `send_sms()` Fonksiyonu

```python
send_sms(
    phone: str,              # Telefon numarası (örn: '05551234567' veya '+905551234567')
    message: str,            # SMS mesajı
    gateway_id: int = None,  # Belirli bir gateway kullanmak için (opsiyonel)
    related_module: str = None,  # İlişkili modül (opsiyonel)
    related_object_id: int = None,  # İlişkili obje ID (opsiyonel)
    related_object_type: str = None  # İlişkili obje tipi (opsiyonel)
) -> dict
```

**Dönüş Değeri**:
```python
{
    'success': True/False,
    'log_id': int,  # SMSSentLog ID
    'gateway_name': str,
    'message_id': str,  # Gateway'den dönen mesaj ID
    'error': str,  # Hata mesajı (başarısızsa)
    'error_code': str  # Hata kodu (başarısızsa)
}
```

### `send_sms_by_template()` Fonksiyonu

```python
send_sms_by_template(
    template_code: str,      # Şablon kodu (örn: 'reservation_confirmation')
    phone: str,              # Telefon numarası
    context: dict,           # Şablon değişkenleri
    gateway_id: int = None,  # Belirli bir gateway kullanmak için (opsiyonel)
    related_module: str = None,
    related_object_id: int = None,
    related_object_type: str = None
) -> dict
```

**Dönüş Değeri**: `send_sms()` ile aynı format

## 🔧 Gateway Yönetimi

### Gateway Listesi

```python
from apps.tenant_apps.settings.models import SMSGateway

# Tüm aktif gateway'ler
gateways = SMSGateway.objects.filter(is_active=True, is_deleted=False)

# Varsayılan gateway
default_gateway = SMSGateway.objects.get(is_default=True, is_active=True, is_deleted=False)

# Belirli tipte gateway'ler
twilio_gateways = SMSGateway.objects.filter(gateway_type='twilio', is_active=True)
```

### Gateway Test Etme

**Web Arayüzü Üzerinden**:
```
http://test-otel.localhost:8000/settings/sms-gateways/{gateway_id}/test/
```

**Python Kodu ile**:
```python
from apps.tenant_apps.settings.models import SMSGateway
from apps.tenant_apps.settings.utils import send_sms

gateway = SMSGateway.objects.get(id=1)

# Test SMS'i gönder
result = send_sms(
    phone='05551234567',
    message='Bu bir test mesajıdır.',
    gateway_id=gateway.id
)

if result['success']:
    print("Gateway çalışıyor!")
else:
    print(f"Gateway hatası: {result['error']}")
```

### Gateway Bakiye Kontrolü

```python
from apps.tenant_apps.settings.models import SMSGateway
from apps.tenant_apps.settings.integrations.base import get_gateway_instance

gateway = SMSGateway.objects.get(id=1)
gateway_instance = get_gateway_instance(gateway)

balance_result = gateway_instance.get_balance()

if balance_result['success']:
    print(f"Bakiye: {balance_result['balance']} {balance_result.get('currency', 'TL')}")
else:
    print(f"Hata: {balance_result['error']}")
```

## 📊 SMS Logları

### Log Görüntüleme

```python
from apps.tenant_apps.settings.models import SMSSentLog

# Tüm loglar
logs = SMSSentLog.objects.all().order_by('-created_at')

# Başarılı gönderimler
success_logs = SMSSentLog.objects.filter(status='sent')

# Başarısız gönderimler
failed_logs = SMSSentLog.objects.filter(status='failed')

# Belirli bir modüle ait loglar
reception_logs = SMSSentLog.objects.filter(related_module='reception')

# Belirli bir rezervasyona ait loglar
reservation_logs = SMSSentLog.objects.filter(
    related_module='reception',
    related_object_type='Reservation',
    related_object_id=123
)
```

### Log Detayları

```python
log = SMSSentLog.objects.get(id=1)

print(f"Gönderilen: {log.recipient_phone}")
print(f"Mesaj: {log.message}")
print(f"Durum: {log.status}")
print(f"Gateway: {log.gateway.name}")
print(f"Gönderim Zamanı: {log.created_at}")
print(f"Mesaj ID: {log.message_id}")
print(f"Hata: {log.error_message}")
```

## 🎨 Modüllerde Kullanım Örnekleri

### Reception Modülü - Rezervasyon Onayı

```python
from apps.tenant_apps.settings.utils import send_sms_by_template

def confirm_reservation(reservation):
    # Rezervasyon onaylandığında SMS gönder
    result = send_sms_by_template(
        template_code='reservation_confirmation',
        phone=reservation.guest.phone,
        context={
            'guest_name': reservation.guest.full_name,
            'check_in_date': reservation.check_in_date.strftime('%d.%m.%Y'),
            'reservation_number': reservation.reservation_number
        },
        related_module='reception',
        related_object_id=reservation.id,
        related_object_type='Reservation'
    )
    
    if result['success']:
        # SMS gönderildi, log kaydedildi
        pass
    else:
        # Hata durumunda log'a kaydedildi
        pass
```

### Ferry Tickets Modülü - Bilet Onayı

```python
from apps.tenant_apps.settings.utils import send_sms_by_template

def confirm_ferry_ticket(ticket):
    result = send_sms_by_template(
        template_code='ferry_ticket_confirmation',
        phone=ticket.passenger.phone,
        context={
            'passenger_name': ticket.passenger.full_name,
            'route_name': ticket.route.name,
            'departure_date': ticket.departure_date.strftime('%d.%m.%Y'),
            'departure_time': ticket.departure_time.strftime('%H:%M'),
            'ticket_number': ticket.ticket_number
        },
        related_module='ferry_tickets',
        related_object_id=ticket.id,
        related_object_type='FerryTicket'
    )
```

## ⚠️ Önemli Notlar

1. **Telefon Numarası Formatı**: 
   - Sistem otomatik olarak telefon numarasını normalize eder
   - `05551234567` → `+905551234567` (varsayılan ülke kodu +90)
   - `+905551234567` → Olduğu gibi kullanılır

2. **Gateway Seçimi**:
   - Eğer `gateway_id` belirtilmezse, varsayılan aktif gateway kullanılır
   - Varsayılan gateway yoksa, ilk aktif gateway kullanılır

3. **Hata Yönetimi**:
   - Tüm SMS gönderimleri loglanır (başarılı veya başarısız)
   - Hata durumunda `SMSSentLog` kaydı oluşturulur
   - Gateway'den dönen hata mesajları log'a kaydedilir

4. **Test Modu**:
   - Gateway'de `is_test_mode=True` ise, gerçek SMS gönderilmez
   - Test modunda log kaydı oluşturulur ama gerçek SMS gönderilmez

5. **Şablon Değişkenleri**:
   - Şablon metninde `{{variable_name}}` formatı kullanılır
   - `context` dict'inde tüm değişkenler sağlanmalıdır
   - Eksik değişkenler `{{variable_name}}` olarak kalır

## 📚 İlgili Dosyalar

- `apps/tenant_apps/settings/models.py` - Modeller
- `apps/tenant_apps/settings/utils.py` - Yardımcı fonksiyonlar
- `apps/tenant_apps/settings/integrations/` - Gateway implementasyonları
- `apps/tenant_apps/settings/views.py` - View'lar
- `apps/tenant_apps/settings/forms.py` - Formlar

---

**Son Güncelleme**: 14 Kasım 2025




## 🎯 Genel Bakış

Settings modülü içinde SMS entegrasyonu sistemi kurulmuştur. Bu sistem Twilio, NetGSM ve Verimor SMS gateway'lerini destekler ve dinamik şablon yönetimi sunar.

## 🚀 Hızlı Başlangıç

### 1. SMS Gateway Oluşturma

#### Web Arayüzü Üzerinden

1. **Gateway Ekleme Sayfasına Gidin**:
   ```
   http://test-otel.localhost:8000/settings/sms-gateways/create/
   ```

2. **Gateway Bilgilerini Doldurun**:
   - **Gateway Adı**: Örn: "NetGSM Ana Hesap"
   - **Gateway Tipi**: Twilio, NetGSM veya Verimor seçin
   - **API Bilgileri**: Dinamik form alanları otomatik oluşur
   - **Gönderen ID**: SMS gönderen numarası veya başlık
   - **Aktif mi?**: Gateway'i aktif edin
   - **Varsayılan Gateway mi?**: İlk gateway'i varsayılan yapın

3. **Kaydedin**: Form gönderildiğinde gateway kaydedilir

#### Python Kodu ile

```python
from apps.tenant_apps.settings.models import SMSGateway

# NetGSM Gateway Örneği
gateway = SMSGateway.objects.create(
    name='NetGSM Production',
    gateway_type='netgsm',
    api_credentials={
        'username': 'kullanici_adi',
        'password': 'sifre',
        'api_key': 'api_anahtari'  # Opsiyonel
    },
    sender_id='NETGSM',
    default_country_code='+90',
    is_active=True,
    is_default=True
)

# Twilio Gateway Örneği
gateway = SMSGateway.objects.create(
    name='Twilio Production',
    gateway_type='twilio',
    api_credentials={
        'account_sid': 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'auth_token': 'your_auth_token'
    },
    sender_id='+1234567890',
    default_country_code='+90',
    is_active=True,
    is_default=False
)

# Verimor Gateway Örneği
gateway = SMSGateway.objects.create(
    name='Verimor Production',
    gateway_type='verimor',
    api_credentials={
        'username': 'kullanici_adi',
        'password': 'sifre'
    },
    sender_id='VERIMOR',
    default_country_code='+90',
    is_active=True,
    is_default=False
)
```

### 2. SMS Gönderme

#### Direkt SMS Gönderme

```python
from apps.tenant_apps.settings.utils import send_sms

# Basit SMS gönderimi
result = send_sms(
    phone='05551234567',
    message='Merhaba, bu bir test mesajıdır.'
)

if result['success']:
    print(f"SMS gönderildi! Log ID: {result['log_id']}")
    print(f"Gateway: {result['gateway_name']}")
    print(f"Mesaj ID: {result['message_id']}")
else:
    print(f"Hata: {result['error']}")
    print(f"Hata Kodu: {result.get('error_code', 'N/A')}")
```

#### Şablon ile SMS Gönderme

```python
from apps.tenant_apps.settings.utils import send_sms_by_template

# Rezervasyon onayı SMS'i
result = send_sms_by_template(
    template_code='reservation_confirmation',
    phone='05551234567',
    context={
        'guest_name': 'Ahmet Yılmaz',
        'check_in_date': '20.11.2025',
        'reservation_number': 'RES-2025-001'
    },
    related_module='reception',
    related_object_id=123,
    related_object_type='Reservation'
)

if result['success']:
    print(f"SMS gönderildi! Log ID: {result['log_id']}")
else:
    print(f"Hata: {result['error']}")
```

### 3. SMS Şablonları

#### Mevcut Varsayılan Şablonlar

1. **`reservation_confirmation`** - Rezervasyon Onayı
   - Değişkenler: `guest_name`, `check_in_date`, `reservation_number`
   - Modül: `reception`

2. **`checkin_reminder`** - Check-in Hatırlatma
   - Değişkenler: `guest_name`, `check_in_date`, `reservation_number`
   - Modül: `reception`

3. **`checkout_reminder`** - Check-out Hatırlatma
   - Değişkenler: `guest_name`, `check_out_date`
   - Modül: `reception`

4. **`payment_confirmation`** - Ödeme Onayı
   - Değişkenler: `guest_name`, `amount`, `currency`, `payment_number`
   - Modül: `payment_management`

5. **`ferry_ticket_confirmation`** - Feribot Bileti Onayı
   - Değişkenler: `passenger_name`, `route_name`, `departure_date`, `departure_time`, `ticket_number`
   - Modül: `ferry_tickets`

#### Yeni Şablon Oluşturma

**Web Arayüzü Üzerinden**:
```
http://test-otel.localhost:8000/settings/sms-templates/create/
```

**Python Kodu ile**:
```python
from apps.tenant_apps.settings.models import SMSTemplate

template = SMSTemplate.objects.create(
    name='Özel Bildirim',
    code='custom_notification',
    category='notification',
    template_text='Sayın {{customer_name}}, {{message}}. Teşekkürler!',
    available_variables={
        'customer_name': 'Müşteri Adı',
        'message': 'Mesaj İçeriği'
    },
    module_usage='custom_module',
    description='Özel bildirim şablonu',
    is_active=True
)
```

## 📋 API Referansı

### `send_sms()` Fonksiyonu

```python
send_sms(
    phone: str,              # Telefon numarası (örn: '05551234567' veya '+905551234567')
    message: str,            # SMS mesajı
    gateway_id: int = None,  # Belirli bir gateway kullanmak için (opsiyonel)
    related_module: str = None,  # İlişkili modül (opsiyonel)
    related_object_id: int = None,  # İlişkili obje ID (opsiyonel)
    related_object_type: str = None  # İlişkili obje tipi (opsiyonel)
) -> dict
```

**Dönüş Değeri**:
```python
{
    'success': True/False,
    'log_id': int,  # SMSSentLog ID
    'gateway_name': str,
    'message_id': str,  # Gateway'den dönen mesaj ID
    'error': str,  # Hata mesajı (başarısızsa)
    'error_code': str  # Hata kodu (başarısızsa)
}
```

### `send_sms_by_template()` Fonksiyonu

```python
send_sms_by_template(
    template_code: str,      # Şablon kodu (örn: 'reservation_confirmation')
    phone: str,              # Telefon numarası
    context: dict,           # Şablon değişkenleri
    gateway_id: int = None,  # Belirli bir gateway kullanmak için (opsiyonel)
    related_module: str = None,
    related_object_id: int = None,
    related_object_type: str = None
) -> dict
```

**Dönüş Değeri**: `send_sms()` ile aynı format

## 🔧 Gateway Yönetimi

### Gateway Listesi

```python
from apps.tenant_apps.settings.models import SMSGateway

# Tüm aktif gateway'ler
gateways = SMSGateway.objects.filter(is_active=True, is_deleted=False)

# Varsayılan gateway
default_gateway = SMSGateway.objects.get(is_default=True, is_active=True, is_deleted=False)

# Belirli tipte gateway'ler
twilio_gateways = SMSGateway.objects.filter(gateway_type='twilio', is_active=True)
```

### Gateway Test Etme

**Web Arayüzü Üzerinden**:
```
http://test-otel.localhost:8000/settings/sms-gateways/{gateway_id}/test/
```

**Python Kodu ile**:
```python
from apps.tenant_apps.settings.models import SMSGateway
from apps.tenant_apps.settings.utils import send_sms

gateway = SMSGateway.objects.get(id=1)

# Test SMS'i gönder
result = send_sms(
    phone='05551234567',
    message='Bu bir test mesajıdır.',
    gateway_id=gateway.id
)

if result['success']:
    print("Gateway çalışıyor!")
else:
    print(f"Gateway hatası: {result['error']}")
```

### Gateway Bakiye Kontrolü

```python
from apps.tenant_apps.settings.models import SMSGateway
from apps.tenant_apps.settings.integrations.base import get_gateway_instance

gateway = SMSGateway.objects.get(id=1)
gateway_instance = get_gateway_instance(gateway)

balance_result = gateway_instance.get_balance()

if balance_result['success']:
    print(f"Bakiye: {balance_result['balance']} {balance_result.get('currency', 'TL')}")
else:
    print(f"Hata: {balance_result['error']}")
```

## 📊 SMS Logları

### Log Görüntüleme

```python
from apps.tenant_apps.settings.models import SMSSentLog

# Tüm loglar
logs = SMSSentLog.objects.all().order_by('-created_at')

# Başarılı gönderimler
success_logs = SMSSentLog.objects.filter(status='sent')

# Başarısız gönderimler
failed_logs = SMSSentLog.objects.filter(status='failed')

# Belirli bir modüle ait loglar
reception_logs = SMSSentLog.objects.filter(related_module='reception')

# Belirli bir rezervasyona ait loglar
reservation_logs = SMSSentLog.objects.filter(
    related_module='reception',
    related_object_type='Reservation',
    related_object_id=123
)
```

### Log Detayları

```python
log = SMSSentLog.objects.get(id=1)

print(f"Gönderilen: {log.recipient_phone}")
print(f"Mesaj: {log.message}")
print(f"Durum: {log.status}")
print(f"Gateway: {log.gateway.name}")
print(f"Gönderim Zamanı: {log.created_at}")
print(f"Mesaj ID: {log.message_id}")
print(f"Hata: {log.error_message}")
```

## 🎨 Modüllerde Kullanım Örnekleri

### Reception Modülü - Rezervasyon Onayı

```python
from apps.tenant_apps.settings.utils import send_sms_by_template

def confirm_reservation(reservation):
    # Rezervasyon onaylandığında SMS gönder
    result = send_sms_by_template(
        template_code='reservation_confirmation',
        phone=reservation.guest.phone,
        context={
            'guest_name': reservation.guest.full_name,
            'check_in_date': reservation.check_in_date.strftime('%d.%m.%Y'),
            'reservation_number': reservation.reservation_number
        },
        related_module='reception',
        related_object_id=reservation.id,
        related_object_type='Reservation'
    )
    
    if result['success']:
        # SMS gönderildi, log kaydedildi
        pass
    else:
        # Hata durumunda log'a kaydedildi
        pass
```

### Ferry Tickets Modülü - Bilet Onayı

```python
from apps.tenant_apps.settings.utils import send_sms_by_template

def confirm_ferry_ticket(ticket):
    result = send_sms_by_template(
        template_code='ferry_ticket_confirmation',
        phone=ticket.passenger.phone,
        context={
            'passenger_name': ticket.passenger.full_name,
            'route_name': ticket.route.name,
            'departure_date': ticket.departure_date.strftime('%d.%m.%Y'),
            'departure_time': ticket.departure_time.strftime('%H:%M'),
            'ticket_number': ticket.ticket_number
        },
        related_module='ferry_tickets',
        related_object_id=ticket.id,
        related_object_type='FerryTicket'
    )
```

## ⚠️ Önemli Notlar

1. **Telefon Numarası Formatı**: 
   - Sistem otomatik olarak telefon numarasını normalize eder
   - `05551234567` → `+905551234567` (varsayılan ülke kodu +90)
   - `+905551234567` → Olduğu gibi kullanılır

2. **Gateway Seçimi**:
   - Eğer `gateway_id` belirtilmezse, varsayılan aktif gateway kullanılır
   - Varsayılan gateway yoksa, ilk aktif gateway kullanılır

3. **Hata Yönetimi**:
   - Tüm SMS gönderimleri loglanır (başarılı veya başarısız)
   - Hata durumunda `SMSSentLog` kaydı oluşturulur
   - Gateway'den dönen hata mesajları log'a kaydedilir

4. **Test Modu**:
   - Gateway'de `is_test_mode=True` ise, gerçek SMS gönderilmez
   - Test modunda log kaydı oluşturulur ama gerçek SMS gönderilmez

5. **Şablon Değişkenleri**:
   - Şablon metninde `{{variable_name}}` formatı kullanılır
   - `context` dict'inde tüm değişkenler sağlanmalıdır
   - Eksik değişkenler `{{variable_name}}` olarak kalır

## 📚 İlgili Dosyalar

- `apps/tenant_apps/settings/models.py` - Modeller
- `apps/tenant_apps/settings/utils.py` - Yardımcı fonksiyonlar
- `apps/tenant_apps/settings/integrations/` - Gateway implementasyonları
- `apps/tenant_apps/settings/views.py` - View'lar
- `apps/tenant_apps/settings/forms.py` - Formlar

---

**Son Güncelleme**: 14 Kasım 2025




