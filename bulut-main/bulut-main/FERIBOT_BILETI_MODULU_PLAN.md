# Feribot Bileti Modülü - FerryOS Entegrasyon Planı

**Tarih:** 2025-01-XX  
**Durum:** Planlama Aşaması  
**Kullanım:** Feribot bileti modülü oluşturulduğunda referans olarak kullanılacak

---

## 📋 Genel Bakış

Bu dokümantasyon, FerryOS API entegrasyonu ile feribot bileti satış modülü oluşturulması için detaylı plan içerir.

**FerryOS Özellikleri:**
- Türkiye-Yunanistan arası feribot firmaları
- Tek API üzerinden tüm operatörler
- Rezervasyon, biletleme, check-in işlemleri
- Otel, tur, ekstra hizmetler, paket programlar
- Ücretsiz API desteği

**İletişim Bilgileri:**
- Adres: Perpa Ticaret Merkezi B Blok Kat 11 No 1557 Şişli – İstanbul
- Telefon: +90 212 909 33 98
- E-posta: [email protected]
- Web: https://ferryos.com

---

## 🏗️ Sistem Mimarisi

### Django App Yapısı

```
apps/
├── ferry_integration/          # Yeni app
│   ├── __init__.py
│   ├── models.py               # Ferry rezervasyon modelleri
│   ├── services.py              # FerryOS API servisleri
│   ├── views.py                # Ferry rezervasyon view'ları
│   ├── forms.py                # Ferry rezervasyon formları
│   ├── urls.py                 # Ferry URL'leri
│   ├── admin.py                # Admin paneli
│   ├── serializers.py          # API serializers (opsiyonel)
│   ├── tasks.py                # Celery tasks (async işlemler)
│   ├── exceptions.py           # Özel exception'lar
│   ├── utils.py                # Yardımcı fonksiyonlar
│   └── management/
│       └── commands/
│           ├── test_ferryos.py          # FerryOS API test komutu
│           ├── sync_ferry_routes.py      # Rota senkronizasyonu
│           └── create_ferry_module.py   # Modül oluşturma
```

---

## 📊 Veritabanı Modelleri

### 1. FerryProvider Modeli

```python
class FerryProvider(TimeStampedModel, SoftDeleteModel):
    """
    FerryOS yapılandırması
    """
    name = models.CharField('Sağlayıcı Adı', max_length=100)
    code = models.SlugField('Kod', unique=True, default='ferryos')
    description = models.TextField('Açıklama', blank=True)
    
    # API Ayarları
    api_key = models.CharField('API Key', max_length=255)
    api_secret = models.CharField('API Secret', max_length=255)
    base_url = models.URLField('API Base URL', default='https://api.ferryos.com')
    test_base_url = models.URLField('Test API URL', blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_test_mode = models.BooleanField('Test Modu', default=True)
    
    # Komisyon
    commission_rate = models.DecimalField('Komisyon Oranı (%)', max_digits=5, decimal_places=2, default=0)
    commission_fixed = models.DecimalField('Sabit Komisyon', max_digits=10, decimal_places=2, default=0)
    
    # İstatistikler
    total_reservations = models.IntegerField('Toplam Rezervasyon', default=0)
    total_revenue = models.DecimalField('Toplam Gelir', max_digits=12, decimal_places=2, default=0)
    last_sync_at = models.DateTimeField('Son Senkronizasyon', null=True, blank=True)
    
    # Ayarlar (JSON)
    settings = models.JSONField('Ek Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Feribot Sağlayıcı'
        verbose_name_plural = 'Feribot Sağlayıcıları'
```

### 2. FerryRoute Modeli

```python
class FerryRoute(TimeStampedModel, SoftDeleteModel):
    """
    Feribot rotaları
    """
    provider = models.ForeignKey(FerryProvider, on_delete=models.CASCADE, related_name='routes')
    route_code = models.CharField('Rota Kodu', max_length=50, db_index=True)
    route_name = models.CharField('Rota Adı', max_length=200)
    
    # Liman Bilgileri
    origin = models.CharField('Kalkış Limanı', max_length=100)
    origin_code = models.CharField('Kalkış Liman Kodu', max_length=20)
    destination = models.CharField('Varış Limanı', max_length=100)
    destination_code = models.CharField('Varış Liman Kodu', max_length=20)
    
    # Sefer Bilgileri
    duration = models.IntegerField('Süre (dakika)', help_text='Ortalama sefer süresi')
    distance = models.DecimalField('Mesafe (km)', max_digits=8, decimal_places=2, null=True, blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_popular = models.BooleanField('Popüler mi?', default=False)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # FerryOS Bilgileri
    ferryos_route_id = models.CharField('FerryOS Rota ID', max_length=100, blank=True)
    
    class Meta:
        verbose_name = 'Feribot Rotası'
        verbose_name_plural = 'Feribot Rotaları'
        unique_together = ['provider', 'route_code']
        indexes = [
            models.Index(fields=['origin_code', 'destination_code']),
            models.Index(fields=['is_active', 'is_popular']),
        ]
```

### 3. FerrySchedule Modeli

```python
class FerrySchedule(TimeStampedModel):
    """
    Feribot sefer saatleri
    """
    route = models.ForeignKey(FerryRoute, on_delete=models.CASCADE, related_name='schedules')
    schedule_code = models.CharField('Sefer Kodu', max_length=50, db_index=True)
    
    # Tarih ve Saat
    departure_date = models.DateField('Kalkış Tarihi', db_index=True)
    departure_time = models.TimeField('Kalkış Saati')
    arrival_date = models.DateField('Varış Tarihi')
    arrival_time = models.TimeField('Varış Saati')
    
    # Gemi Bilgileri
    vessel_name = models.CharField('Gemi Adı', max_length=100, blank=True)
    vessel_type = models.CharField('Gemi Tipi', max_length=50, blank=True)
    
    # Kapasite
    total_capacity = models.IntegerField('Toplam Kapasite', default=0)
    available_capacity = models.IntegerField('Müsait Kapasite', default=0)
    
    # Fiyat Bilgileri (Base)
    adult_price = models.DecimalField('Yetişkin Fiyatı', max_digits=10, decimal_places=2)
    child_price = models.DecimalField('Çocuk Fiyatı', max_digits=10, decimal_places=2, default=0)
    infant_price = models.DecimalField('Bebek Fiyatı', max_digits=10, decimal_places=2, default=0)
    vehicle_price = models.DecimalField('Araç Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Durum
    is_available = models.BooleanField('Müsait mi?', default=True)
    is_cancelled = models.BooleanField('İptal mi?', default=False)
    
    # FerryOS Bilgileri
    ferryos_schedule_id = models.CharField('FerryOS Sefer ID', max_length=100, blank=True)
    last_synced_at = models.DateTimeField('Son Senkronizasyon', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Feribot Seferi'
        verbose_name_plural = 'Feribot Seferleri'
        unique_together = ['route', 'schedule_code', 'departure_date']
        indexes = [
            models.Index(fields=['departure_date', 'is_available']),
            models.Index(fields=['route', 'departure_date']),
        ]
```

### 4. FerryReservation Modeli

```python
class FerryReservation(TimeStampedModel, SoftDeleteModel):
    """
    Feribot rezervasyonları
    """
    # Rezervasyon Bilgileri
    reservation_code = models.CharField('Rezervasyon Kodu', max_length=50, unique=True, db_index=True)
    provider = models.ForeignKey(FerryProvider, on_delete=models.PROTECT, related_name='reservations')
    route = models.ForeignKey(FerryRoute, on_delete=models.PROTECT, related_name='reservations')
    schedule = models.ForeignKey(FerrySchedule, on_delete=models.PROTECT, related_name='reservations')
    
    # Sefer Bilgileri
    departure_date = models.DateTimeField('Kalkış Tarihi')
    arrival_date = models.DateTimeField('Varış Tarihi')
    
    # Müşteri Bilgileri
    customer = models.ForeignKey('tenant_core.Customer', on_delete=models.PROTECT, related_name='ferry_reservations')
    contact_email = models.EmailField('İletişim E-posta', db_index=True)
    contact_phone = models.CharField('İletişim Telefon', max_length=20)
    contact_name = models.CharField('İletişim Adı', max_length=200)
    
    # Yolcu Bilgileri
    adult_count = models.IntegerField('Yetişkin Sayısı', default=0)
    child_count = models.IntegerField('Çocuk Sayısı', default=0)
    infant_count = models.IntegerField('Bebek Sayısı', default=0)
    vehicle_count = models.IntegerField('Araç Sayısı', default=0)
    
    # Fiyat Bilgileri
    base_price = models.DecimalField('Base Fiyat', max_digits=10, decimal_places=2)
    commission_rate = models.DecimalField('Komisyon Oranı (%)', max_digits=5, decimal_places=2, default=0)
    commission_amount = models.DecimalField('Komisyon Tutarı', max_digits=10, decimal_places=2, default=0)
    total_price = models.DecimalField('Toplam Fiyat', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Durum
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('confirmed', 'Onaylandı'),
        ('cancelled', 'İptal Edildi'),
        ('refunded', 'İade Edildi'),
        ('completed', 'Tamamlandı'),
        ('no_show', 'Gelmedi'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending', db_index=True)
    
    # FerryOS Bilgileri
    ferryos_reservation_id = models.CharField('FerryOS Rezervasyon ID', max_length=100, blank=True, db_index=True)
    ferryos_booking_reference = models.CharField('FerryOS Booking Reference', max_length=100, blank=True)
    ferryos_response = models.JSONField('FerryOS Yanıtı', default=dict, blank=True)
    
    # Ödeme
    payment = models.OneToOneField('payments.PaymentTransaction', on_delete=models.SET_NULL, null=True, blank=True, related_name='ferry_reservation')
    
    # Check-in
    is_checked_in = models.BooleanField('Check-in Yapıldı mı?', default=False)
    checked_in_at = models.DateTimeField('Check-in Tarihi', null=True, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    cancellation_reason = models.TextField('İptal Nedeni', blank=True)
    cancelled_at = models.DateTimeField('İptal Tarihi', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Feribot Rezervasyonu'
        verbose_name_plural = 'Feribot Rezervasyonları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['status', 'departure_date']),
            models.Index(fields=['customer', 'status']),
            models.Index(fields=['reservation_code']),
            models.Index(fields=['ferryos_reservation_id']),
        ]
```

### 5. FerryPassenger Modeli

```python
class FerryPassenger(TimeStampedModel):
    """
    Feribot yolcu bilgileri
    """
    reservation = models.ForeignKey(FerryReservation, on_delete=models.CASCADE, related_name='passengers')
    
    # Kişisel Bilgiler
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    birth_date = models.DateField('Doğum Tarihi', null=True, blank=True)
    gender = models.CharField('Cinsiyet', max_length=10, choices=[('male', 'Erkek'), ('female', 'Kadın')], blank=True)
    
    # Kimlik Bilgileri
    passport_number = models.CharField('Pasaport No', max_length=50, blank=True)
    passport_expiry = models.DateField('Pasaport Geçerlilik', null=True, blank=True)
    tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True)
    nationality = models.CharField('Uyruk', max_length=50, default='TR')
    
    # Yolcu Tipi
    PASSENGER_TYPE_CHOICES = [
        ('adult', 'Yetişkin'),
        ('child', 'Çocuk'),
        ('infant', 'Bebek'),
    ]
    passenger_type = models.CharField('Yolcu Tipi', max_length=20, choices=PASSENGER_TYPE_CHOICES, default='adult')
    
    # Fiyat
    price = models.DecimalField('Fiyat', max_digits=10, decimal_places=2)
    
    # FerryOS Bilgileri
    ferryos_passenger_id = models.CharField('FerryOS Yolcu ID', max_length=100, blank=True)
    
    class Meta:
        verbose_name = 'Feribot Yolcusu'
        verbose_name_plural = 'Feribot Yolcuları'
        ordering = ['passenger_type', 'first_name']
```

### 6. FerryVehicle Modeli (Opsiyonel)

```python
class FerryVehicle(TimeStampedModel):
    """
    Feribot araç bilgileri
    """
    reservation = models.ForeignKey(FerryReservation, on_delete=models.CASCADE, related_name='vehicles')
    
    # Araç Bilgileri
    vehicle_type = models.CharField('Araç Tipi', max_length=50, choices=[
        ('car', 'Otomobil'),
        ('motorcycle', 'Motosiklet'),
        ('van', 'Minibüs'),
        ('truck', 'Kamyon'),
    ])
    license_plate = models.CharField('Plaka', max_length=20)
    make = models.CharField('Marka', max_length=50, blank=True)
    model = models.CharField('Model', max_length=50, blank=True)
    year = models.IntegerField('Yıl', null=True, blank=True)
    
    # Fiyat
    price = models.DecimalField('Fiyat', max_digits=10, decimal_places=2)
    
    class Meta:
        verbose_name = 'Feribot Aracı'
        verbose_name_plural = 'Feribot Araçları'
```

---

## 🔌 API Servis Katmanı

### FerryOSService Sınıfı

```python
# apps/ferry_integration/services.py

import requests
import logging
from typing import Dict, Any, List, Optional
from django.utils import timezone
from .models import FerryProvider, FerryRoute, FerrySchedule, FerryReservation
from .exceptions import FerryOSError, FerryOSConnectionError, FerryOSAuthenticationError

logger = logging.getLogger(__name__)


class FerryOSService:
    """
    FerryOS API entegrasyon servisi
    """
    
    def __init__(self, provider: FerryProvider):
        self.provider = provider
        self.api_key = provider.api_key
        self.api_secret = provider.api_secret
        self.base_url = provider.test_base_url if provider.is_test_mode else provider.base_url
        self.timeout = 30
    
    def _get_headers(self) -> Dict[str, str]:
        """
        API istekleri için header'ları oluştur
        """
        return {
            'Authorization': f'Bearer {self.api_key}',
            'X-API-Secret': self.api_secret,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }
    
    def _make_request(self, method: str, endpoint: str, data: Optional[Dict] = None) -> Dict[str, Any]:
        """
        API isteği yap
        """
        url = f"{self.base_url}{endpoint}"
        headers = self._get_headers()
        
        try:
            if method.upper() == 'GET':
                response = requests.get(url, headers=headers, params=data, timeout=self.timeout)
            elif method.upper() == 'POST':
                response = requests.post(url, headers=headers, json=data, timeout=self.timeout)
            elif method.upper() == 'PUT':
                response = requests.put(url, headers=headers, json=data, timeout=self.timeout)
            elif method.upper() == 'DELETE':
                response = requests.delete(url, headers=headers, timeout=self.timeout)
            else:
                raise FerryOSError(f"Desteklenmeyen HTTP metodu: {method}")
            
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.Timeout:
            raise FerryOSConnectionError("API isteği zaman aşımına uğradı")
        except requests.exceptions.ConnectionError:
            raise FerryOSConnectionError("API'ye bağlanılamadı")
        except requests.exceptions.HTTPError as e:
            if e.response.status_code == 401:
                raise FerryOSAuthenticationError("API kimlik doğrulama hatası")
            else:
                raise FerryOSError(f"API hatası: {e.response.status_code} - {e.response.text}")
        except Exception as e:
            logger.error(f"FerryOS API hatası: {str(e)}")
            raise FerryOSError(f"Beklenmeyen hata: {str(e)}")
    
    # 1. Sefer Sorgulama
    def search_routes(self, origin: str, destination: str, date: str, passengers: Dict[str, int]) -> List[Dict]:
        """
        Rota ve sefer sorgulama
        GET /api/v1/routes/search
        
        Args:
            origin: Kalkış liman kodu
            destination: Varış liman kodu
            date: Tarih (YYYY-MM-DD)
            passengers: {'adult': 2, 'child': 1, 'infant': 0}
        
        Returns:
            Sefer listesi
        """
        params = {
            'origin': origin,
            'destination': destination,
            'date': date,
            'adults': passengers.get('adult', 0),
            'children': passengers.get('child', 0),
            'infants': passengers.get('infant', 0),
        }
        
        response = self._make_request('GET', '/api/v1/routes/search', params)
        return response.get('schedules', [])
    
    # 2. Fiyat Sorgulama
    def get_pricing(self, schedule_id: str, passengers: Dict[str, int], vehicles: int = 0) -> Dict:
        """
        Sefer fiyatlarını sorgula
        GET /api/v1/pricing
        
        Args:
            schedule_id: Sefer ID
            passengers: Yolcu sayıları
            vehicles: Araç sayısı
        
        Returns:
            Fiyat detayları
        """
        params = {
            'schedule_id': schedule_id,
            'adults': passengers.get('adult', 0),
            'children': passengers.get('child', 0),
            'infants': passengers.get('infant', 0),
            'vehicles': vehicles,
        }
        
        response = self._make_request('GET', '/api/v1/pricing', params)
        return response
    
    # 3. Rezervasyon Oluşturma
    def create_reservation(self, schedule_id: str, passengers: List[Dict], contact_info: Dict, vehicles: List[Dict] = None) -> Dict:
        """
        Rezervasyon oluştur
        POST /api/v1/reservations
        
        Args:
            schedule_id: Sefer ID
            passengers: Yolcu bilgileri listesi
            contact_info: İletişim bilgileri
            vehicles: Araç bilgileri (opsiyonel)
        
        Returns:
            Rezervasyon bilgileri
        """
        data = {
            'schedule_id': schedule_id,
            'passengers': passengers,
            'contact': contact_info,
        }
        
        if vehicles:
            data['vehicles'] = vehicles
        
        response = self._make_request('POST', '/api/v1/reservations', data)
        return response
    
    # 4. Rezervasyon Onaylama
    def confirm_reservation(self, reservation_id: str, payment_info: Dict) -> Dict:
        """
        Rezervasyonu onayla ve ödemeyi işle
        POST /api/v1/reservations/{id}/confirm
        
        Args:
            reservation_id: FerryOS rezervasyon ID
            payment_info: Ödeme bilgileri
        
        Returns:
            Onaylanmış rezervasyon bilgileri
        """
        data = {
            'payment': payment_info,
        }
        
        response = self._make_request('POST', f'/api/v1/reservations/{reservation_id}/confirm', data)
        return response
    
    # 5. Rezervasyon İptali
    def cancel_reservation(self, reservation_id: str, reason: str = '') -> Dict:
        """
        Rezervasyonu iptal et
        POST /api/v1/reservations/{id}/cancel
        
        Args:
            reservation_id: FerryOS rezervasyon ID
            reason: İptal nedeni
        
        Returns:
            İptal bilgileri
        """
        data = {
            'reason': reason,
        }
        
        response = self._make_request('POST', f'/api/v1/reservations/{reservation_id}/cancel', data)
        return response
    
    # 6. Rezervasyon Sorgulama
    def get_reservation(self, reservation_id: str) -> Dict:
        """
        Rezervasyon detaylarını getir
        GET /api/v1/reservations/{id}
        
        Args:
            reservation_id: FerryOS rezervasyon ID
        
        Returns:
            Rezervasyon detayları
        """
        response = self._make_request('GET', f'/api/v1/reservations/{reservation_id}')
        return response
    
    # 7. Check-in İşlemleri
    def checkin_passenger(self, reservation_id: str, passenger_ids: List[str]) -> Dict:
        """
        Yolcu check-in işlemi
        POST /api/v1/reservations/{id}/checkin
        
        Args:
            reservation_id: FerryOS rezervasyon ID
            passenger_ids: Check-in yapılacak yolcu ID'leri
        
        Returns:
            Check-in bilgileri
        """
        data = {
            'passenger_ids': passenger_ids,
        }
        
        response = self._make_request('POST', f'/api/v1/reservations/{reservation_id}/checkin', data)
        return response
    
    # 8. Rota Listesi
    def get_routes(self) -> List[Dict]:
        """
        Tüm rotaları getir
        GET /api/v1/routes
        
        Returns:
            Rota listesi
        """
        response = self._make_request('GET', '/api/v1/routes')
        return response.get('routes', [])
    
    # 9. Liman Listesi
    def get_ports(self) -> List[Dict]:
        """
        Tüm limanları getir
        GET /api/v1/ports
        
        Returns:
            Liman listesi
        """
        response = self._make_request('GET', '/api/v1/ports')
        return response.get('ports', [])
```

---

## 🎨 View'lar ve Formlar

### View'lar

```python
# apps/ferry_integration/views.py

from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.utils import timezone
from .models import FerryProvider, FerryRoute, FerrySchedule, FerryReservation
from .services import FerryOSService
from .forms import FerrySearchForm, FerryReservationForm, FerryPassengerFormSet
from apps.tenant_apps.core.decorators import require_module_permission

@login_required
@require_module_permission('ferry', 'view')
def ferry_search(request):
    """
    Feribot seferi arama
    """
    form = FerrySearchForm(request.GET or None)
    schedules = []
    
    if form.is_valid():
        origin = form.cleaned_data['origin']
        destination = form.cleaned_data['destination']
        date = form.cleaned_data['date']
        passengers = {
            'adult': form.cleaned_data['adult_count'],
            'child': form.cleaned_data['child_count'],
            'infant': form.cleaned_data['infant_count'],
        }
        
        # FerryOS API'den seferleri sorgula
        provider = FerryProvider.objects.filter(is_active=True).first()
        if provider:
            try:
                service = FerryOSService(provider)
                schedules = service.search_routes(origin, destination, date.strftime('%Y-%m-%d'), passengers)
            except Exception as e:
                messages.error(request, f"Sefer sorgulama hatası: {str(e)}")
    
    context = {
        'form': form,
        'schedules': schedules,
    }
    return render(request, 'ferry/search.html', context)

@login_required
@require_module_permission('ferry', 'create')
def ferry_reservation_create(request, schedule_id):
    """
    Feribot rezervasyonu oluştur
    """
    # Sefer bilgilerini al
    schedule = get_object_or_404(FerrySchedule, id=schedule_id, is_available=True)
    
    if request.method == 'POST':
        form = FerryReservationForm(request.POST)
        passenger_formset = FerryPassengerFormSet(request.POST)
        
        if form.is_valid() and passenger_formset.is_valid():
            # Rezervasyon oluştur
            # FerryOS API çağrısı
            # Veritabanına kaydet
            pass
    else:
        form = FerryReservationForm()
        passenger_formset = FerryPassengerFormSet()
    
    context = {
        'schedule': schedule,
        'form': form,
        'passenger_formset': passenger_formset,
    }
    return render(request, 'ferry/reservation_create.html', context)

@login_required
@require_module_permission('ferry', 'view')
def ferry_reservation_list(request):
    """
    Rezervasyon listesi
    """
    reservations = FerryReservation.objects.filter(
        customer__tenant=request.tenant
    ).select_related('route', 'schedule', 'customer').order_by('-created_at')
    
    context = {
        'reservations': reservations,
    }
    return render(request, 'ferry/reservation_list.html', context)

@login_required
@require_module_permission('ferry', 'view')
def ferry_reservation_detail(request, reservation_id):
    """
    Rezervasyon detayı
    """
    reservation = get_object_or_404(
        FerryReservation,
        id=reservation_id,
        customer__tenant=request.tenant
    )
    
    # FerryOS'tan güncel bilgileri çek
    if reservation.ferryos_reservation_id:
        provider = reservation.provider
        service = FerryOSService(provider)
        try:
            ferryos_data = service.get_reservation(reservation.ferryos_reservation_id)
            # Rezervasyonu güncelle
        except Exception as e:
            messages.warning(request, f"FerryOS'tan bilgi alınamadı: {str(e)}")
    
    context = {
        'reservation': reservation,
    }
    return render(request, 'ferry/reservation_detail.html', context)

@login_required
@require_module_permission('ferry', 'cancel')
def ferry_reservation_cancel(request, reservation_id):
    """
    Rezervasyon iptali
    """
    reservation = get_object_or_404(
        FerryReservation,
        id=reservation_id,
        customer__tenant=request.tenant,
        status__in=['pending', 'confirmed']
    )
    
    if request.method == 'POST':
        reason = request.POST.get('reason', '')
        
        # FerryOS API'ye iptal isteği gönder
        provider = reservation.provider
        service = FerryOSService(provider)
        
        try:
            service.cancel_reservation(reservation.ferryos_reservation_id, reason)
            
            # Rezervasyonu iptal et
            reservation.status = 'cancelled'
            reservation.cancellation_reason = reason
            reservation.cancelled_at = timezone.now()
            reservation.save()
            
            # İade işlemi başlat (gerekirse)
            # Refund modülüne yönlendir
            
            messages.success(request, 'Rezervasyon iptal edildi')
            return redirect('ferry:reservation_detail', reservation_id=reservation.id)
            
        except Exception as e:
            messages.error(request, f"İptal hatası: {str(e)}")
    
    context = {
        'reservation': reservation,
    }
    return render(request, 'ferry/reservation_cancel.html', context)
```

---

## 🔐 Güvenlik ve Hata Yönetimi

### Exception Sınıfları

```python
# apps/ferry_integration/exceptions.py

class FerryOSError(Exception):
    """FerryOS genel hata"""
    pass

class FerryOSConnectionError(FerryOSError):
    """Bağlantı hatası"""
    pass

class FerryOSAuthenticationError(FerryOSError):
    """Kimlik doğrulama hatası"""
    pass

class FerryOSValidationError(FerryOSError):
    """Veri doğrulama hatası"""
    pass

class FerryOSReservationError(FerryOSError):
    """Rezervasyon hatası"""
    pass
```

### Güvenlik Önlemleri

1. **API Anahtarları Şifreleme**
   - `django-cryptography` kullanarak şifreleme
   - Veritabanında şifreli saklama

2. **Rate Limiting**
   - API isteklerini sınırlama
   - Redis ile rate limiting

3. **Request Signing**
   - HMAC signature ile istek doğrulama

4. **SSL/TLS**
   - Tüm API istekleri HTTPS

5. **IP Whitelist** (Opsiyonel)
   - Belirli IP'lerden erişim

---

## 📧 Bildirim Entegrasyonu

### Rezervasyon Bildirimleri

```python
# Rezervasyon onaylandığında:
from apps.notifications.services import send_notification

# Email bildirimi
send_notification(
    provider_code='email',
    recipient=reservation.contact_email,
    template_code='ferry_reservation_confirmed',
    variables={
        'reservation_code': reservation.reservation_code,
        'route': f"{reservation.route.origin} - {reservation.route.destination}",
        'departure_date': reservation.departure_date,
        'total_price': reservation.total_price,
    }
)

# SMS bildirimi
send_notification(
    provider_code='sms_netgsm',
    recipient=reservation.contact_phone,
    template_code='ferry_reservation_confirmed_sms',
    variables={
        'reservation_code': reservation.reservation_code,
        'departure_date': reservation.departure_date,
    }
)
```

---

## 📊 Raporlama

### Ferry Raporları

1. **Günlük/Aylık Rezervasyon Sayıları**
2. **Gelir Raporları** (Komisyon dahil)
3. **Rota Bazlı İstatistikler**
4. **İptal Oranları**
5. **Müşteri Analizi**
6. **Sefer Doluluk Oranları**

---

## 🧪 Test Stratejisi

### Test Komutları

```python
# apps/ferry_integration/management/commands/test_ferryos.py

class Command(BaseCommand):
    def handle(self, *args, **options):
        # 1. API bağlantı testi
        # 2. Sefer sorgulama testi
        # 3. Rezervasyon oluşturma testi
        # 4. Rezervasyon iptal testi
        # 5. Check-in testi
        pass
```

---

## 📝 Yapılacaklar Listesi

### Ön Hazırlık
- [ ] FerryOS ile iletişime geç
- [ ] API dokümantasyonu al
- [ ] Test ortamı erişimi sağla
- [ ] API anahtarları al
- [ ] Entegrasyon sözleşmesi imzala
- [ ] Komisyon oranlarını öğren

### Geliştirme
- [ ] Django app oluştur (`ferry_integration`)
- [ ] Modelleri oluştur
- [ ] Migration'ları çalıştır
- [ ] FerryOSService sınıfını oluştur
- [ ] View'ları oluştur
- [ ] Form'ları oluştur
- [ ] Template'leri oluştur
- [ ] URL'leri tanımla
- [ ] Admin paneli ekle

### Entegrasyon
- [ ] Ödeme sistemi entegrasyonu
- [ ] Bildirim sistemi entegrasyonu
- [ ] Müşteri yönetimi entegrasyonu
- [ ] Raporlama sistemi

### Test
- [ ] Unit testler
- [ ] Integration testler
- [ ] API test komutları
- [ ] Manuel testler

### Dokümantasyon
- [ ] Kullanıcı kılavuzu
- [ ] API dokümantasyonu
- [ ] Teknik dokümantasyon

---

## ⚠️ Önemli Notlar

1. **FerryOS API Dokümantasyonu Gerekli**
   - Resmi API dokümantasyonu olmadan tam entegrasyon yapılamaz
   - Endpoint'ler, request/response formatları netleştirilmeli

2. **Test Ortamı**
   - Mutlaka test ortamında test edilmeli
   - Canlı ortama geçmeden önce kapsamlı testler yapılmalı

3. **Komisyon Yapısı**
   - Komisyon oranları netleştirilmeli
   - Ödeme koşulları anlaşılmalı

4. **Entegrasyon Süresi**
   - Tahmini: 2-4 hafta (API dokümantasyonuna bağlı)
   - Test süresi dahil

5. **Yedek Plan**
   - FerryOS entegrasyonu başarısız olursa alternatif sağlayıcılar değerlendirilmeli
   - Mevki Yazılım veya FX Port alternatif olabilir

---

## 📞 İletişim

**FerryOS:**
- Adres: Perpa Ticaret Merkezi B Blok Kat 11 No 1557 Şişli – İstanbul
- Telefon: +90 212 909 33 98
- E-posta: [email protected]
- Web: https://ferryos.com

---

**Son Güncelleme:** 2025-01-XX  
**Hazırlayan:** AI Assistant  
**Durum:** Planlama Tamamlandı - Geliştirme Bekliyor

