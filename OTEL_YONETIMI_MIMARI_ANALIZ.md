# Otel Yönetimi Modülü - Sistem Mimarisi Analizi

**Tarih:** 2025-01-XX  
**Durum:** Analiz ve Planlama  
**Kullanım:** Otel Yönetimi modülü geliştirilirken referans olarak kullanılacak

---

## 🎯 Genel Gereksinimler

### Temel İhtiyaçlar
1. ✅ **Çoklu Otel Desteği**: Paket limitine göre birden fazla otel eklenebilmeli
2. ✅ **Kullanıcı Yetkilendirme**: Farklı kullanıcılara farklı otel yetkileri verilebilmeli
3. ✅ **Oteller Arası Geçiş**: A, B, C otelleri arasında kolay geçiş
4. ✅ **Veri İzolasyonu**: Her otelin verileri tenant + otel ID ile yönetilmeli
5. ✅ **Filtreleme**: Otel bazlı otomatik filtreleme
6. ✅ **Gelecek Modüller**: Rezervasyon, Oda, Housekeeping vb. modüllerle uyumlu

---

## 🏗️ Önerilen Sistem Mimarisi

### 1. Veri Modeli Katmanı

#### 1.1. Hotel Modeli (Temel Otel Bilgileri)

```python
class Hotel(TimeStampedModel, SoftDeleteModel):
    """
    Otel Modeli
    Her tenant paket limitine göre birden fazla otel ekleyebilir
    """
    # Temel Bilgiler
    name = models.CharField('Otel Adı', max_length=200)
    code = models.SlugField('Otel Kodu', max_length=50, db_index=True)
    description = models.TextField('Açıklama', blank=True)
    
    # İletişim Bilgileri
    email = models.EmailField('E-posta', blank=True)
    phone = models.CharField('Telefon', max_length=20, blank=True)
    website = models.URLField('Web Sitesi', blank=True)
    
    # Adres Bilgileri
    address = models.TextField('Adres')
    city = models.CharField('Şehir', max_length=100)
    district = models.CharField('İlçe', max_length=100, blank=True)
    postal_code = models.CharField('Posta Kodu', max_length=10, blank=True)
    country = models.CharField('Ülke', max_length=100, default='Türkiye')
    
    # Konum Bilgileri
    latitude = models.DecimalField('Enlem', max_digits=9, decimal_places=6, null=True, blank=True)
    longitude = models.DecimalField('Boylam', max_digits=9, decimal_places=6, null=True, blank=True)
    
    # Otel Özellikleri
    star_rating = models.IntegerField('Yıldız', choices=[(i, i) for i in range(1, 6)], null=True, blank=True)
    total_rooms = models.IntegerField('Toplam Oda Sayısı', default=0)
    check_in_time = models.TimeField('Check-in Saati', default='14:00')
    check_out_time = models.TimeField('Check-out Saati', default='12:00')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan Otel mi?', default=False,
                                    help_text='İlk otel varsayılan olarak işaretlenir')
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar (JSON)
    settings = models.JSONField('Otel Ayarları', default=dict, blank=True,
                               help_text='Otel özel ayarları (dil, para birimi, vb.)')
    
    class Meta:
        verbose_name = 'Otel'
        verbose_name_plural = 'Oteller'
        unique_together = ['code']  # Tenant bazlı unique olmalı (django-tenants ile)
        ordering = ['sort_order', 'name']
        indexes = [
            models.Index(fields=['code', 'is_active']),
            models.Index(fields=['city', 'is_active']),
        ]
    
    def __str__(self):
        return self.name
    
    def save(self, *args, **kwargs):
        # İlk otel varsayılan olarak işaretlenir
        if not Hotel.objects.filter(is_default=True).exists():
            self.is_default = True
        super().save(*args, **kwargs)
```

#### 1.2. HotelUserPermission Modeli (Kullanıcı-Otel Yetki İlişkisi)

```python
class HotelUserPermission(TimeStampedModel):
    """
    Kullanıcı-Otel Yetki İlişkisi
    Hangi kullanıcı hangi otellere erişebilir ve hangi yetkilere sahip
    """
    tenant_user = models.ForeignKey(
        'TenantUser',
        on_delete=models.CASCADE,
        related_name='hotel_permissions',
        verbose_name='Kullanıcı'
    )
    hotel = models.ForeignKey(
        'Hotel',
        on_delete=models.CASCADE,
        related_name='user_permissions',
        verbose_name='Otel'
    )
    
    # Yetki Seviyeleri
    PERMISSION_LEVEL_CHOICES = [
        ('view', 'Görüntüleme'),
        ('manage', 'Yönetim'),
        ('admin', 'Yönetici'),
    ]
    permission_level = models.CharField(
        'Yetki Seviyesi',
        max_length=20,
        choices=PERMISSION_LEVEL_CHOICES,
        default='view'
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    assigned_at = models.DateTimeField('Atanma Tarihi', auto_now_add=True)
    assigned_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='assigned_hotel_permissions',
        verbose_name='Atayan Kullanıcı'
    )
    
    class Meta:
        verbose_name = 'Otel Kullanıcı Yetkisi'
        verbose_name_plural = 'Otel Kullanıcı Yetkileri'
        unique_together = ('tenant_user', 'hotel')
        indexes = [
            models.Index(fields=['tenant_user', 'is_active']),
            models.Index(fields=['hotel', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.tenant_user} - {self.hotel.name} ({self.get_permission_level_display()})"
```

---

### 2. Middleware Katmanı

#### 2.1. HotelMiddleware (Aktif Otel Yönetimi)

```python
# apps/tenant_apps/hotels/middleware.py

from django.utils.deprecation import MiddlewareMixin
from .models import Hotel, HotelUserPermission
from apps.tenant_apps.core.models import TenantUser

class HotelMiddleware(MiddlewareMixin):
    """
    Aktif otel bilgisini request'e ekler
    Session'dan aktif otel ID'sini alır veya varsayılan oteli kullanır
    """
    
    def process_request(self, request):
        # Public schema'da çalışmaz
        if not hasattr(request, 'tenant') or not request.tenant:
            return None
        
        # Kullanıcı giriş yapmamışsa
        if not request.user.is_authenticated:
            request.active_hotel = None
            return None
        
        try:
            tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
            
            # Session'dan aktif otel ID'sini al
            active_hotel_id = request.session.get('active_hotel_id')
            
            if active_hotel_id:
                try:
                    # Kullanıcının bu otelde yetkisi var mı kontrol et
                    hotel = Hotel.objects.get(id=active_hotel_id, is_active=True)
                    has_permission = HotelUserPermission.objects.filter(
                        tenant_user=tenant_user,
                        hotel=hotel,
                        is_active=True
                    ).exists()
                    
                    # Admin kullanıcılar tüm otellere erişebilir (opsiyonel)
                    is_admin = tenant_user.has_module_permission('hotels', 'admin')
                    
                    if has_permission or is_admin:
                        request.active_hotel = hotel
                    else:
                        # Yetki yoksa varsayılan oteli kullan
                        request.active_hotel = self._get_default_hotel(tenant_user)
                except Hotel.DoesNotExist:
                    request.active_hotel = self._get_default_hotel(tenant_user)
            else:
                # Session'da yoksa varsayılan oteli kullan
                request.active_hotel = self._get_default_hotel(tenant_user)
            
            # Kullanıcının erişebileceği otelleri al
            request.accessible_hotels = self._get_accessible_hotels(tenant_user)
            
        except TenantUser.DoesNotExist:
            request.active_hotel = None
            request.accessible_hotels = []
        
        return None
    
    def _get_default_hotel(self, tenant_user):
        """Kullanıcının varsayılan otelini getir"""
        # Önce kullanıcının yetkili olduğu varsayılan oteli bul
        hotel_permission = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            hotel__is_default=True,
            hotel__is_active=True,
            is_active=True
        ).select_related('hotel').first()
        
        if hotel_permission:
            return hotel_permission.hotel
        
        # Varsayılan otel yoksa, kullanıcının yetkili olduğu ilk oteli al
        hotel_permission = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            hotel__is_active=True,
            is_active=True
        ).select_related('hotel').first()
        
        if hotel_permission:
            return hotel_permission.hotel
        
        # Hiç yetki yoksa, varsayılan oteli döndür (admin kullanıcılar için)
        return Hotel.objects.filter(is_default=True, is_active=True).first()
    
    def _get_accessible_hotels(self, tenant_user):
        """Kullanıcının erişebileceği otelleri getir"""
        # Admin kullanıcılar tüm otellere erişebilir
        is_admin = tenant_user.has_module_permission('hotels', 'admin')
        
        if is_admin:
            return Hotel.objects.filter(is_active=True).order_by('sort_order', 'name')
        
        # Normal kullanıcılar sadece yetkili oldukları otellere erişebilir
        hotel_ids = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            is_active=True
        ).values_list('hotel_id', flat=True)
        
        return Hotel.objects.filter(id__in=hotel_ids, is_active=True).order_by('sort_order', 'name')
```

---

### 3. Query Manager Katmanı

#### 3.1. HotelQueryManager (Otel Bazlı Otomatik Filtreleme)

```python
# apps/tenant_apps/hotels/managers.py

from django.db import models
from django.db.models import Q

class HotelQuerySet(models.QuerySet):
    """
    Otel bazlı query set
    Otomatik olarak aktif otel filtresi uygular
    """
    
    def for_active_hotel(self, hotel):
        """Aktif otel için filtrele"""
        if hotel:
            return self.filter(hotel=hotel)
        return self.none()
    
    def for_accessible_hotels(self, hotels):
        """Erişilebilir oteller için filtrele"""
        if hotels:
            return self.filter(hotel__in=hotels)
        return self.none()


class HotelManager(models.Manager):
    """
    Otel bazlı manager
    """
    
    def get_queryset(self):
        return HotelQuerySet(self.model, using=self._db)
    
    def for_active_hotel(self, hotel):
        """Aktif otel için"""
        return self.get_queryset().for_active_hotel(hotel)
    
    def for_accessible_hotels(self, hotels):
        """Erişilebilir oteller için"""
        return self.get_queryset().for_accessible_hotels(hotels)


# Kullanım Örneği:
# class Reservation(models.Model):
#     hotel = models.ForeignKey(Hotel, on_delete=models.CASCADE)
#     objects = HotelManager()
# 
# # View'da:
# reservations = Reservation.objects.for_active_hotel(request.active_hotel)
```

---

### 4. Decorator Katmanı

#### 4.1. Hotel Permission Decorator

```python
# apps/tenant_apps/hotels/decorators.py

from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from .models import HotelUserPermission

def require_hotel_permission(permission_level='view'):
    """
    Otel bazlı yetki kontrolü decorator'ı
    
    Kullanım:
    @require_hotel_permission('manage')
    def hotel_edit(request, hotel_id):
        ...
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            # Aktif otel yoksa
            if not hasattr(request, 'active_hotel') or not request.active_hotel:
                messages.error(request, 'Aktif otel seçilmedi.')
                return redirect('hotels:select_hotel')
            
            hotel = request.active_hotel
            
            try:
                from apps.tenant_apps.core.models import TenantUser
                tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                
                # Admin kullanıcılar tüm yetkilere sahip
                is_admin = tenant_user.has_module_permission('hotels', 'admin')
                if is_admin:
                    return view_func(request, *args, **kwargs)
                
                # Otel yetkisini kontrol et
                hotel_permission = HotelUserPermission.objects.filter(
                    tenant_user=tenant_user,
                    hotel=hotel,
                    is_active=True
                ).first()
                
                if not hotel_permission:
                    messages.error(request, f'{hotel.name} oteline erişim yetkiniz bulunmamaktadır.')
                    return redirect('hotels:select_hotel')
                
                # Yetki seviyesi kontrolü
                permission_levels = ['view', 'manage', 'admin']
                user_level = permission_levels.index(hotel_permission.permission_level)
                required_level = permission_levels.index(permission_level)
                
                if user_level >= required_level:
                    return view_func(request, *args, **kwargs)
                else:
                    messages.error(request, f'Bu işlem için yeterli yetkiniz bulunmamaktadır.')
                    return redirect('hotels:select_hotel')
                    
            except Exception as e:
                messages.error(request, 'Yetki kontrolü sırasında hata oluştu.')
                return redirect('tenant:dashboard')
        
        return _wrapped_view
    return decorator
```

---

### 5. Context Processor

#### 5.1. Hotel Context Processor

```python
# apps/tenant_apps/hotels/context_processors.py

def hotel_context(request):
    """
    Template'lerde kullanılacak otel bilgileri
    """
    context = {
        'active_hotel': None,
        'accessible_hotels': [],
        'can_switch_hotel': False,
    }
    
    if hasattr(request, 'active_hotel'):
        context['active_hotel'] = request.active_hotel
    
    if hasattr(request, 'accessible_hotels'):
        context['accessible_hotels'] = request.accessible_hotels
        context['can_switch_hotel'] = len(request.accessible_hotels) > 1
    
    return context
```

---

### 6. View'lar ve URL Yapısı

#### 6.1. Otel Seçim View'u

```python
# apps/tenant_apps/hotels/views.py

@login_required
def select_hotel(request):
    """
    Otel seçim sayfası
    Kullanıcı erişebileceği oteller arasından seçim yapar
    """
    tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
    
    # Erişilebilir otelleri al
    accessible_hotels = HotelUserPermission.objects.filter(
        tenant_user=tenant_user,
        is_active=True
    ).select_related('hotel').order_by('hotel__sort_order', 'hotel__name')
    
    if request.method == 'POST':
        hotel_id = request.POST.get('hotel_id')
        try:
            hotel = Hotel.objects.get(id=hotel_id, is_active=True)
            # Yetki kontrolü
            has_permission = HotelUserPermission.objects.filter(
                tenant_user=tenant_user,
                hotel=hotel,
                is_active=True
            ).exists()
            
            if has_permission or tenant_user.has_module_permission('hotels', 'admin'):
                # Session'a kaydet
                request.session['active_hotel_id'] = hotel.id
                messages.success(request, f'{hotel.name} oteline geçildi.')
                return redirect('tenant:dashboard')
            else:
                messages.error(request, 'Bu otele erişim yetkiniz bulunmamaktadır.')
        except Hotel.DoesNotExist:
            messages.error(request, 'Otel bulunamadı.')
    
    context = {
        'accessible_hotels': [hp.hotel for hp in accessible_hotels],
    }
    return render(request, 'hotels/select_hotel.html', context)
```

#### 6.2. Otel Yönetim View'ları

```python
@login_required
@require_module_permission('hotels', 'view')
def hotel_list(request):
    """
    Otel listesi
    """
    hotels = Hotel.objects.filter(is_active=True).order_by('sort_order', 'name')
    
    # Paket limiti kontrolü
    from apps.tenant_apps.subscriptions.views import get_usage_statistics
    stats = get_usage_statistics(request.tenant)
    max_hotels = stats.get('max_hotels', 0)
    current_hotels = stats.get('current_hotels', 0)
    
    context = {
        'hotels': hotels,
        'max_hotels': max_hotels,
        'current_hotels': current_hotels,
        'can_add_hotel': current_hotels < max_hotels,
    }
    return render(request, 'hotels/list.html', context)

@login_required
@require_module_permission('hotels', 'add')
def hotel_create(request):
    """
    Yeni otel ekle
    """
    # Paket limiti kontrolü
    from apps.tenant_apps.subscriptions.views import get_usage_statistics
    stats = get_usage_statistics(request.tenant)
    max_hotels = stats.get('max_hotels', 0)
    current_hotels = stats.get('current_hotels', 0)
    
    if current_hotels >= max_hotels:
        messages.error(request, f'Paket limitiniz doldu. Maksimum {max_hotels} otel ekleyebilirsiniz.')
        return redirect('hotels:list')
    
    if request.method == 'POST':
        form = HotelForm(request.POST)
        if form.is_valid():
            hotel = form.save()
            messages.success(request, f'Otel "{hotel.name}" başarıyla eklendi.')
            return redirect('hotels:list')
    else:
        form = HotelForm()
    
    context = {
        'form': form,
    }
    return render(request, 'hotels/form.html', context)
```

---

### 7. Template Entegrasyonu

#### 7.1. Sidebar'da Otel Seçici

```html
<!-- templates/includes/sidebar.html veya base.html -->

{% if can_switch_hotel and accessible_hotels %}
<div class="hotel-selector mb-4">
    <label class="block text-sm font-semibold text-gray-700 mb-2">
        <i class="fas fa-hotel mr-2"></i>Aktif Otel
    </label>
    <div class="flex flex-wrap gap-2">
        {% for hotel in accessible_hotels %}
        <a href="{% url 'hotels:switch_hotel' hotel.id %}" 
           class="px-3 py-2 rounded-lg text-sm font-medium transition
                  {% if active_hotel.id == hotel.id %}
                  bg-blue-600 text-white
                  {% else %}
                  bg-gray-200 text-gray-700 hover:bg-gray-300
                  {% endif %}">
            {{ hotel.name }}
        </a>
        {% endfor %}
    </div>
    {% if accessible_hotels|length > 1 %}
    <a href="{% url 'hotels:select_hotel' %}" 
       class="mt-2 text-xs text-blue-600 hover:underline">
        <i class="fas fa-exchange-alt mr-1"></i>Tüm Oteller
    </a>
    {% endif %}
</div>
{% endif %}
```

---

### 8. Gelecek Modüllerle Entegrasyon

#### 8.1. Rezervasyon Modülü Örneği

```python
# apps/tenant_apps/reservations/models.py

class Reservation(TimeStampedModel):
    """
    Rezervasyon Modeli
    Otel bazlı çalışır
    """
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='reservations',
        verbose_name='Otel'
    )
    
    # Diğer alanlar...
    
    class Meta:
        indexes = [
            models.Index(fields=['hotel', 'check_in_date']),
        ]

# View'da:
@login_required
@require_module_permission('reservations', 'view')
@require_hotel_permission('view')
def reservation_list(request):
    """
    Rezervasyon listesi
    Otomatik olarak aktif otel filtrelenir
    """
    reservations = Reservation.objects.filter(
        hotel=request.active_hotel
    ).order_by('-created_at')
    
    return render(request, 'reservations/list.html', {
        'reservations': reservations
    })
```

#### 8.2. Oda Yönetimi Modülü Örneği

```python
# apps/tenant_apps/rooms/models.py

class Room(TimeStampedModel):
    """
    Oda Modeli
    Otel bazlı çalışır
    """
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='rooms',
        verbose_name='Otel'
    )
    
    # Diğer alanlar...
```

---

### 9. Paket Limit Kontrolü

#### 9.1. Usage Statistics Güncelleme

```python
# apps/tenant_apps/subscriptions/views.py içinde get_usage_statistics fonksiyonuna ekle:

def get_usage_statistics(tenant):
    """
    Kullanım istatistikleri
    """
    stats = {
        'max_hotels': 0,
        'current_hotels': 0,
        # ... diğer istatistikler
    }
    
    # Aktif aboneliği al
    subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if subscription:
        package = subscription.package
        stats['max_hotels'] = package.max_hotels
        
        # Mevcut otel sayısını al
        from apps.tenant_apps.hotels.models import Hotel
        stats['current_hotels'] = Hotel.objects.filter(is_active=True).count()
    
    return stats
```

---

## 📋 Yapılacaklar Listesi

### Faz 1: Temel Altyapı
- [ ] Hotel modeli oluştur
- [ ] HotelUserPermission modeli oluştur
- [ ] Migration'ları çalıştır
- [ ] HotelMiddleware oluştur ve settings'e ekle
- [ ] HotelContextProcessor oluştur ve settings'e ekle

### Faz 2: Otel Yönetimi
- [ ] Otel listesi view'u
- [ ] Otel ekleme view'u
- [ ] Otel düzenleme view'u
- [ ] Otel silme view'u
- [ ] Otel detay view'u
- [ ] Otel seçim view'u
- [ ] Otel geçiş view'u

### Faz 3: Yetkilendirme
- [ ] HotelPermission decorator oluştur
- [ ] Kullanıcı-Otel yetki atama view'u
- [ ] Toplu yetki atama
- [ ] Yetki kontrolü testleri

### Faz 4: UI/UX
- [ ] Sidebar'da otel seçici
- [ ] Otel geçiş butonları
- [ ] Otel listesi template'i
- [ ] Otel form template'i
- [ ] Otel seçim template'i

### Faz 5: Entegrasyon
- [ ] Paket limit kontrolü
- [ ] Usage statistics güncelleme
- [ ] Modül kaydı (Module tablosuna)
- [ ] Paket entegrasyonu
- [ ] Yetki sistemi entegrasyonu

### Faz 6: Test ve Dokümantasyon
- [ ] Unit testler
- [ ] Integration testler
- [ ] Kullanıcı kılavuzu
- [ ] Teknik dokümantasyon

---

## 🔑 Önemli Tasarım Kararları

### 1. Veri İzolasyonu Stratejisi

**Seçenek 1: ForeignKey Yaklaşımı (Önerilen)**
- Her modelde `hotel = ForeignKey(Hotel)`
- Query'lerde otomatik filtreleme
- Avantaj: Basit, anlaşılır, esnek
- Dezavantaj: Her sorguda filtreleme gerekli

**Seçenek 2: Abstract Base Model**
```python
class HotelBasedModel(models.Model):
    hotel = models.ForeignKey(Hotel, on_delete=models.CASCADE)
    
    class Meta:
        abstract = True

class Reservation(HotelBasedModel):
    # Diğer alanlar
```

### 2. Yetkilendirme Stratejisi

**Seçenek 1: HotelUserPermission (Önerilen)**
- Kullanıcı-Otel yetki ilişkisi
- Yetki seviyeleri: view, manage, admin
- Avantaj: Esnek, detaylı kontrol
- Dezavantaj: Daha karmaşık

**Seçenek 2: Role-Based Hotel Access**
- Rollere otel ataması
- Avantaj: Daha basit
- Dezavantaj: Daha az esnek

### 3. Session Yönetimi

**Aktif Otel Seçimi:**
- Session'da `active_hotel_id` saklanır
- Middleware her request'te kontrol eder
- Kullanıcı otel değiştirdiğinde session güncellenir

### 4. Varsayılan Otel

**Strateji:**
- İlk eklenen otel varsayılan olarak işaretlenir
- Kullanıcının yetkili olduğu varsayılan otel seçilir
- Varsayılan otel yoksa, yetkili olduğu ilk otel seçilir

---

## 🎨 UI/UX Önerileri

### 1. Otel Seçici Tasarımı

**Sidebar'da:**
- Dropdown menü (çok otel varsa)
- Buton grubu (az otel varsa)
- Aktif otel vurgulanır
- Hızlı geçiş butonları

### 2. Dashboard'da Otel Bilgisi

**Header'da:**
- Aktif otel adı gösterilir
- Otel değiştirme butonu
- Otel durumu (aktif/pasif)

### 3. Breadcrumb'da Otel

**Sayfa başlığında:**
- "Otel Adı > Modül > Sayfa" formatı
- Otel adına tıklanınca otel detayına gider

---

## ⚠️ Dikkat Edilmesi Gerekenler

1. **Paket Limit Kontrolü**
   - Otel eklemeden önce limit kontrolü yapılmalı
   - Limit dolduğunda uyarı gösterilmeli

2. **Varsayılan Otel**
   - En az bir otel varsayılan olmalı
   - Varsayılan otel silinememeli (önce başka otel varsayılan yapılmalı)

3. **Yetki Kontrolü**
   - Her otel işleminde yetki kontrolü yapılmalı
   - Admin kullanıcılar tüm otellere erişebilmeli

4. **Veri Bütünlüğü**
   - Otel silinirken ilişkili veriler kontrol edilmeli
   - Soft delete kullanılmalı

5. **Performans**
   - Query'lerde select_related kullanılmalı
   - Index'ler doğru yerleştirilmeli
   - Cache kullanılabilir (opsiyonel)

---

## 📊 Sistem Akış Diyagramı

```
Kullanıcı Girişi
    ↓
HotelMiddleware
    ↓
Session'dan active_hotel_id al
    ↓
Yetki kontrolü (HotelUserPermission)
    ↓
request.active_hotel set et
    ↓
View'a geç
    ↓
@require_hotel_permission decorator
    ↓
Yetki kontrolü (tekrar)
    ↓
Query'de otomatik filtreleme
    ↓
Template'e otel bilgisi gönder
```

---

## 🔄 Gelecek Modüllerle Uyumluluk

### Rezervasyon Modülü
```python
class Reservation(models.Model):
    hotel = ForeignKey(Hotel)  # Otel bazlı
    # ...
```

### Oda Yönetimi
```python
class Room(models.Model):
    hotel = ForeignKey(Hotel)  # Otel bazlı
    # ...
```

### Housekeeping
```python
class HousekeepingTask(models.Model):
    hotel = ForeignKey(Hotel)  # Otel bazlı
    # ...
```

### Kanal Yönetimi
```python
class ChannelConnection(models.Model):
    hotel = ForeignKey(Hotel)  # Otel bazlı
    # ...
```

---

## 📝 Sonuç ve Öneriler

### Önerilen Mimari

1. **Hotel Model**: Temel otel bilgileri
2. **HotelUserPermission**: Kullanıcı-Otel yetki ilişkisi
3. **HotelMiddleware**: Aktif otel yönetimi
4. **HotelQueryManager**: Otomatik filtreleme
5. **HotelDecorator**: Yetki kontrolü
6. **HotelContextProcessor**: Template entegrasyonu

### Avantajlar

- ✅ Esnek yetkilendirme sistemi
- ✅ Otomatik veri izolasyonu
- ✅ Kolay otel geçişi
- ✅ Gelecek modüllerle uyumlu
- ✅ Paket limit kontrolü
- ✅ Ölçeklenebilir yapı

### Uygulama Sırası

1. **İlk Aşama**: Hotel modeli ve temel CRUD
2. **İkinci Aşama**: Yetkilendirme sistemi
3. **Üçüncü Aşama**: Middleware ve context processor
4. **Dördüncü Aşama**: UI/UX iyileştirmeleri
5. **Beşinci Aşama**: Gelecek modüllerle entegrasyon

---

**Son Güncelleme:** 2025-01-XX  
**Hazırlayan:** AI Assistant  
**Durum:** Analiz Tamamlandı - Geliştirme Bekliyor

