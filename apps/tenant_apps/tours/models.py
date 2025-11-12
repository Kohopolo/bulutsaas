"""
Tur Yönetim Modelleri
Profesyonel tur operatörü yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== DİNAMİK YÖNETİM MODELLERİ ====================

class TourRegion(TimeStampedModel, SoftDeleteModel):
    """Tur Bölgeleri (Ege, Akdeniz, Bodrum, Yunan Adaları, vb.)"""
    name = models.CharField('Bölge Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, blank=True, help_text='Font Awesome icon class')
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Tur Bölgesi'
        verbose_name_plural = 'Tur Bölgeleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name


class TourLocation(TimeStampedModel, SoftDeleteModel):
    """Tur Lokasyonu (Yurt İçi, Yurtdışı)"""
    name = models.CharField('Lokasyon Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    LOCATION_TYPE_CHOICES = [
        ('domestic', 'Yurt İçi'),
        ('international', 'Yurtdışı'),
    ]
    location_type = models.CharField('Lokasyon Tipi', max_length=20, choices=LOCATION_TYPE_CHOICES)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Tur Lokasyonu'
        verbose_name_plural = 'Tur Lokasyonları'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return f"{self.name} ({self.get_location_type_display()})"


class TourCity(TimeStampedModel, SoftDeleteModel):
    """Tur Şehirleri (İzmir, İstanbul, Bodrum, vb.)"""
    name = models.CharField('Şehir Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    country = models.CharField('Ülke', max_length=100, default='Türkiye')
    latitude = models.DecimalField('Enlem', max_digits=9, decimal_places=6, null=True, blank=True)
    longitude = models.DecimalField('Boylam', max_digits=9, decimal_places=6, null=True, blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Tur Şehri'
        verbose_name_plural = 'Tur Şehirleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return f"{self.name}, {self.country}"


class TourType(TimeStampedModel, SoftDeleteModel):
    """Tur Türleri (Kültür Turu, Doğa Turu, vb.)"""
    name = models.CharField('Tur Türü Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, blank=True)
    color = models.CharField('Renk', max_length=7, default='#3498db', help_text='Hex color code')
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Tur Türü'
        verbose_name_plural = 'Tur Türleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name


# ==================== VOUCHER ŞABLONU ====================

class TourVoucherTemplate(TimeStampedModel, SoftDeleteModel):
    """Tur Voucher Şablonu"""
    name = models.CharField('Şablon Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    template_html = models.TextField('HTML Şablon', help_text='Voucher HTML içeriği')
    template_css = models.TextField('CSS Stilleri', blank=True)
    is_default = models.BooleanField('Varsayılan Şablon mu?', default=False)
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Voucher Şablonu'
        verbose_name_plural = 'Voucher Şablonları'
    
    def __str__(self):
        return self.name


# ==================== ANA TUR MODELİ ====================

class Tour(TimeStampedModel, SoftDeleteModel):
    """Ana Tur Modeli"""
    
    # Temel Bilgiler
    name = models.CharField('Tur Adı', max_length=200)
    code = models.SlugField('Tur Kodu', max_length=100, unique=True)
    slug = models.SlugField('URL Slug', max_length=200, unique=True, blank=True)
    
    # Kategoriler
    region = models.ForeignKey(TourRegion, on_delete=models.SET_NULL, null=True, related_name='tours', verbose_name='Tur Bölgesi')
    location = models.ForeignKey(TourLocation, on_delete=models.SET_NULL, null=True, related_name='tours', verbose_name='Tur Lokasyonu')
    city = models.ForeignKey(TourCity, on_delete=models.SET_NULL, null=True, related_name='tours', verbose_name='Tur Şehri')
    tour_type = models.ForeignKey(TourType, on_delete=models.SET_NULL, null=True, related_name='tours', verbose_name='Tur Türü')
    
    # Ulaşım ve Süre
    TRANSPORT_CHOICES = [
        ('bus', 'Otobüs'),
        ('plane', 'Uçak'),
        ('train', 'Tren'),
        ('ship', 'Gemi'),
        ('car', 'Araba'),
        ('mixed', 'Karma'),
    ]
    transport_type = models.CharField('Ulaşım Türü', max_length=20, choices=TRANSPORT_CHOICES, default='bus')
    duration_days = models.IntegerField('Kaç Gün', validators=[MinValueValidator(1)])
    duration_nights = models.IntegerField('Kaç Gece', default=0, validators=[MinValueValidator(0)])
    
    # Açıklamalar
    description = models.TextField('Tur Açıklaması', blank=True)
    cities_to_visit = models.TextField('Gezilecek Şehirler', blank=True, help_text='Virgülle ayrılmış şehir listesi')
    notes = models.TextField('Tur Notları', blank=True)
    
    # Kontenjan
    max_adults = models.IntegerField('Maksimum Yetişkin', default=50, validators=[MinValueValidator(1)])
    max_children = models.IntegerField('Maksimum Çocuk', default=20, validators=[MinValueValidator(0)])
    child_age_min = models.IntegerField('Çocuk Yaş Minimum', default=0, validators=[MinValueValidator(0), MaxValueValidator(18)])
    child_age_max = models.IntegerField('Çocuk Yaş Maksimum', default=12, validators=[MinValueValidator(0), MaxValueValidator(18)])
    
    # Fiyatlandırma (Genel - tarih bazlı fiyatlar öncelikli)
    adult_price = models.DecimalField('Yetişkin Fiyatı', max_digits=10, decimal_places=2, default=0)
    child_price = models.DecimalField('Çocuk Fiyatı', max_digits=10, decimal_places=2, default=0)
    group_price = models.DecimalField('Grup Fiyatı (10+ Kişi)', max_digits=10, decimal_places=2, null=True, blank=True)
    group_min_people = models.IntegerField('Grup Minimum Kişi', default=10, validators=[MinValueValidator(2)])
    
    # Kampanya
    campaign_price = models.DecimalField('Kampanya Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    campaign_start_date = models.DateField('Kampanya Başlangıç', null=True, blank=True)
    campaign_end_date = models.DateField('Kampanya Bitiş', null=True, blank=True)
    
    # Dinamik Fiyatlandırma Ayarları
    enable_dynamic_pricing = models.BooleanField('Dinamik Fiyatlandırma Aktif', default=False,
                                                  help_text='Sezon, erken rezervasyon, son dakika fırsatları aktif olsun mu?')
    
    # Sezon Bazlı Fiyatlandırma
    SEASON_CHOICES = [
        ('high', 'Yüksek Sezon'),
        ('medium', 'Orta Sezon'),
        ('low', 'Düşük Sezon'),
    ]
    high_season_start_month = models.IntegerField('Yüksek Sezon Başlangıç Ayı', default=6, 
                                                   validators=[MinValueValidator(1), MaxValueValidator(12)], null=True, blank=True)
    high_season_end_month = models.IntegerField('Yüksek Sezon Bitiş Ayı', default=8,
                                                validators=[MinValueValidator(1), MaxValueValidator(12)], null=True, blank=True)
    high_season_price_increase = models.DecimalField('Yüksek Sezon Fiyat Artışı (%)', max_digits=5, decimal_places=2, 
                                                     default=25.00, help_text='Yüzde olarak (örn: 25 = %25 artış)')
    low_season_start_month = models.IntegerField('Düşük Sezon Başlangıç Ayı', default=11,
                                                 validators=[MinValueValidator(1), MaxValueValidator(12)], null=True, blank=True)
    low_season_end_month = models.IntegerField('Düşük Sezon Bitiş Ayı', default=3,
                                               validators=[MinValueValidator(1), MaxValueValidator(12)], null=True, blank=True)
    low_season_price_decrease = models.DecimalField('Düşük Sezon Fiyat İndirimi (%)', max_digits=5, decimal_places=2,
                                                    default=20.00, help_text='Yüzde olarak (örn: 20 = %20 indirim)')
    
    # Erken Rezervasyon İndirimleri
    enable_early_booking = models.BooleanField('Erken Rezervasyon İndirimi Aktif', default=False)
    early_booking_90_days_discount = models.DecimalField('90 Gün Öncesi İndirim (%)', max_digits=5, decimal_places=2,
                                                         default=15.00, null=True, blank=True)
    early_booking_60_days_discount = models.DecimalField('60 Gün Öncesi İndirim (%)', max_digits=5, decimal_places=2,
                                                         default=10.00, null=True, blank=True)
    early_booking_30_days_discount = models.DecimalField('30 Gün Öncesi İndirim (%)', max_digits=5, decimal_places=2,
                                                         default=5.00, null=True, blank=True)
    
    # Son Dakika Fırsatları
    enable_last_minute = models.BooleanField('Son Dakika Fırsatları Aktif', default=False)
    last_minute_7_days_discount = models.DecimalField('7 Gün İçinde İndirim (%)', max_digits=5, decimal_places=2,
                                                      default=20.00, null=True, blank=True)
    last_minute_3_days_discount = models.DecimalField('3 Gün İçinde İndirim (%)', max_digits=5, decimal_places=2,
                                                      default=30.00, null=True, blank=True)
    last_minute_auto_activate = models.BooleanField('Otomatik Aktifleşme', default=False,
                                                    help_text='Kontenjan %80 dolduğunda otomatik aktif olsun')
    last_minute_capacity_threshold = models.IntegerField('Kapasite Eşiği (%)', default=80,
                                                         validators=[MinValueValidator(0), MaxValueValidator(100)],
                                                         help_text='Bu yüzde dolduğunda son dakika fırsatları aktif olsun')
    
    # Hafta İçi/Hafta Sonu Fiyat Farkı
    enable_weekend_pricing = models.BooleanField('Hafta Sonu Fiyat Farkı Aktif', default=False)
    weekend_price_increase = models.DecimalField('Hafta Sonu Fiyat Artışı (%)', max_digits=5, decimal_places=2,
                                                 default=10.00, null=True, blank=True)
    
    # Talebe Göre Otomatik Fiyat Artışı
    enable_demand_pricing = models.BooleanField('Talep Bazlı Fiyat Artışı Aktif', default=False)
    demand_capacity_threshold = models.IntegerField('Talep Kapasite Eşiği (%)', default=80,
                                                    validators=[MinValueValidator(0), MaxValueValidator(100)],
                                                    help_text='Bu yüzde dolduğunda fiyat otomatik artış yapsın')
    demand_price_increase = models.DecimalField('Talep Fiyat Artışı (%)', max_digits=5, decimal_places=2,
                                               default=15.00, null=True, blank=True)
    
    # Bayram Tatilleri
    enable_holiday_pricing = models.BooleanField('Bayram Tatili Fiyatlandırması Aktif', default=False)
    holiday_price_increase = models.DecimalField('Bayram Fiyat Artışı (%)', max_digits=5, decimal_places=2,
                                                default=30.00, null=True, blank=True)
    
    # Saatler
    departure_time = models.TimeField('Tur Çıkış Saati', null=True, blank=True)
    return_time = models.TimeField('Tur Dönüş Saati', null=True, blank=True)
    
    # Fiyata Dahil/Dahil Olmayanlar
    price_includes = models.TextField('Fiyata Dahil', blank=True, help_text='Her satıra bir madde')
    price_excludes = models.TextField('Fiyata Dahil Olmayanlar', blank=True, help_text='Her satıra bir madde')
    
    # Konaklama
    hotels = models.TextField('Konaklanacak Oteller', blank=True, help_text='Her satıra bir otel')
    
    # Medya
    main_image = models.ImageField('Ana Resim', upload_to='tours/main/', null=True, blank=True)
    
    # SEO ve Durum
    STATUS_CHOICES = [
        ('draft', 'Taslak'),
        ('published', 'Yayında'),
        ('completed', 'Tamamlandı'),
        ('cancelled', 'İptal Edildi'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='draft')
    is_featured = models.BooleanField('Öne Çıkan mı?', default=False)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # SEO
    meta_title = models.CharField('Meta Başlık', max_length=200, blank=True)
    meta_description = models.TextField('Meta Açıklama', blank=True)
    meta_keywords = models.CharField('Meta Anahtar Kelimeler', max_length=500, blank=True)
    
    # İstatistikler
    view_count = models.IntegerField('Görüntülenme Sayısı', default=0)
    reservation_count = models.IntegerField('Rezervasyon Sayısı', default=0)
    rating_average = models.DecimalField('Ortalama Puan', max_digits=3, decimal_places=2, default=0, validators=[MinValueValidator(0), MaxValueValidator(5)])
    rating_count = models.IntegerField('Değerlendirme Sayısı', default=0)
    
    # Ayarlar
    settings = models.JSONField('Ek Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Tur'
        verbose_name_plural = 'Turlar'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['status', 'is_active']),
            models.Index(fields=['region', 'location', 'tour_type']),
        ]
    
    def __str__(self):
        return self.name
    
    def save(self, *args, **kwargs):
        if not self.slug:
            from django.utils.text import slugify
            self.slug = slugify(self.name)
        super().save(*args, **kwargs)
    
    def get_duration_display(self):
        """Süre gösterimi"""
        if self.duration_nights > 0:
            return f"{self.duration_days} Gün {self.duration_nights} Gece"
        return f"{self.duration_days} Gün"
    
    def get_current_price(self, date=None, is_adult=True, reservation_date=None):
        """
        Dinamik fiyatlandırma ile güncel fiyatı hesapla
        date: Tur tarihi
        is_adult: Yetişkin mi çocuk mu
        reservation_date: Rezervasyon yapılma tarihi (erken rezervasyon için)
        """
        # Temel fiyatı al
        base_price = self.adult_price if is_adult else self.child_price
        
        # Tarih bazlı fiyat varsa onu kullan
        if date:
            tour_date = self.tour_dates.filter(date=date, is_active=True).first()
            if tour_date:
                base_price = tour_date.get_adult_price() if is_adult else tour_date.get_child_price()
        
        # Dinamik fiyatlandırma aktif değilse temel fiyatı döndür
        if not self.enable_dynamic_pricing:
            # Kampanya kontrolü (dinamik fiyatlandırma olmadan)
            if self.campaign_price and self.campaign_start_date and self.campaign_end_date:
                today = timezone.now().date()
                if self.campaign_start_date <= today <= self.campaign_end_date:
                    return self.campaign_price
            return base_price
        
        # Dinamik fiyat hesaplama
        return self.calculate_dynamic_price(base_price, date, reservation_date, is_adult)
    
    def calculate_dynamic_price(self, base_price, tour_date=None, reservation_date=None, is_adult=True):
        """
        Dinamik fiyatlandırma hesaplama
        Öncelik sırası:
        1. Kampanya fiyatı (en yüksek öncelik)
        2. Sezon bazlı fiyat
        3. Erken rezervasyon indirimi
        4. Son dakika fırsatları
        5. Hafta sonu fiyat farkı
        6. Talep bazlı fiyat artışı
        7. Bayram tatili fiyat artışı
        """
        from decimal import Decimal
        from datetime import datetime, timedelta
        import calendar
        
        price = Decimal(str(base_price))
        applied_discounts = []
        applied_increases = []
        
        # 1. Kampanya kontrolü (en yüksek öncelik)
        if self.campaign_price and self.campaign_start_date and self.campaign_end_date:
            today = timezone.now().date()
            if self.campaign_start_date <= today <= self.campaign_end_date:
                return Decimal(str(self.campaign_price))
        
        # Tur tarihi yoksa bugünü kullan
        if not tour_date:
            tour_date = timezone.now().date()
        else:
            # datetime ise date'e çevir
            if isinstance(tour_date, datetime):
                tour_date = tour_date.date()
            elif hasattr(tour_date, 'date'):
                tour_date = tour_date.date()
        
        # Rezervasyon tarihi yoksa bugünü kullan
        if not reservation_date:
            reservation_date = timezone.now().date()
        else:
            # datetime ise date'e çevir
            if isinstance(reservation_date, datetime):
                reservation_date = reservation_date.date()
            elif hasattr(reservation_date, 'date'):
                reservation_date = reservation_date.date()
        
        # Rezervasyon tarihinden tur tarihine kadar gün sayısı
        days_until_tour = (tour_date - reservation_date).days
        
        # 2. Sezon bazlı fiyatlandırma
        if self.high_season_start_month and self.high_season_end_month:
            tour_month = tour_date.month
            
            # Yüksek sezon kontrolü
            if self.high_season_start_month <= self.high_season_end_month:
                # Normal sezon (örn: Haziran-Ağustos)
                if self.high_season_start_month <= tour_month <= self.high_season_end_month:
                    increase = price * (Decimal(str(self.high_season_price_increase)) / Decimal('100'))
                    price += increase
                    applied_increases.append(f'Yüksek Sezon: +%{self.high_season_price_increase}')
            else:
                # Yıl sonu sezon (örn: Aralık-Ocak)
                if tour_month >= self.high_season_start_month or tour_month <= self.high_season_end_month:
                    increase = price * (Decimal(str(self.high_season_price_increase)) / Decimal('100'))
                    price += increase
                    applied_increases.append(f'Yüksek Sezon: +%{self.high_season_price_increase}')
        
        if self.low_season_start_month and self.low_season_end_month:
            tour_month = tour_date.month
            
            # Düşük sezon kontrolü
            if self.low_season_start_month <= self.low_season_end_month:
                # Normal sezon (örn: Kasım-Mart)
                if self.low_season_start_month <= tour_month <= self.low_season_end_month:
                    decrease = price * (Decimal(str(self.low_season_price_decrease)) / Decimal('100'))
                    price -= decrease
                    applied_discounts.append(f'Düşük Sezon: -%{self.low_season_price_decrease}')
            else:
                # Yıl sonu sezon (örn: Aralık-Ocak)
                if tour_month >= self.low_season_start_month or tour_month <= self.low_season_end_month:
                    decrease = price * (Decimal(str(self.low_season_price_decrease)) / Decimal('100'))
                    price -= decrease
                    applied_discounts.append(f'Düşük Sezon: -%{self.low_season_price_decrease}')
        
        # 3. Erken rezervasyon indirimleri
        if self.enable_early_booking and days_until_tour > 0:
            if days_until_tour >= 90 and self.early_booking_90_days_discount:
                discount = price * (Decimal(str(self.early_booking_90_days_discount)) / Decimal('100'))
                price -= discount
                applied_discounts.append(f'90 Gün Erken Rezervasyon: -%{self.early_booking_90_days_discount}')
            elif days_until_tour >= 60 and self.early_booking_60_days_discount:
                discount = price * (Decimal(str(self.early_booking_60_days_discount)) / Decimal('100'))
                price -= discount
                applied_discounts.append(f'60 Gün Erken Rezervasyon: -%{self.early_booking_60_days_discount}')
            elif days_until_tour >= 30 and self.early_booking_30_days_discount:
                discount = price * (Decimal(str(self.early_booking_30_days_discount)) / Decimal('100'))
                price -= discount
                applied_discounts.append(f'30 Gün Erken Rezervasyon: -%{self.early_booking_30_days_discount}')
        
        # 4. Son dakika fırsatları
        if self.enable_last_minute and days_until_tour > 0:
            # Otomatik aktifleşme kontrolü
            auto_activate = False
            if self.last_minute_auto_activate:
                capacity = self.get_available_capacity(tour_date)
                total_capacity = capacity.get('max_adults', 0) + capacity.get('max_children', 0)
                reserved = capacity.get('reserved_adults', 0) + capacity.get('reserved_children', 0)
                if total_capacity > 0:
                    utilization = (reserved / total_capacity) * 100
                    if utilization >= self.last_minute_capacity_threshold:
                        auto_activate = True
            
            if auto_activate or not self.last_minute_auto_activate:
                if days_until_tour <= 3 and self.last_minute_3_days_discount:
                    discount = price * (Decimal(str(self.last_minute_3_days_discount)) / Decimal('100'))
                    price -= discount
                    applied_discounts.append(f'Son Dakika (3 Gün): -%{self.last_minute_3_days_discount}')
                elif days_until_tour <= 7 and self.last_minute_7_days_discount:
                    discount = price * (Decimal(str(self.last_minute_7_days_discount)) / Decimal('100'))
                    price -= discount
                    applied_discounts.append(f'Son Dakika (7 Gün): -%{self.last_minute_7_days_discount}')
        
        # 5. Hafta sonu fiyat farkı
        if self.enable_weekend_pricing:
            tour_weekday = tour_date.weekday()
            # 5 = Cumartesi, 6 = Pazar
            if tour_weekday >= 5:
                increase = price * (Decimal(str(self.weekend_price_increase)) / Decimal('100'))
                price += increase
                applied_increases.append(f'Hafta Sonu: +%{self.weekend_price_increase}')
        
        # 6. Talep bazlı fiyat artışı
        if self.enable_demand_pricing:
            capacity = self.get_available_capacity(tour_date)
            total_capacity = capacity.get('max_adults', 0) + capacity.get('max_children', 0)
            reserved = capacity.get('reserved_adults', 0) + capacity.get('reserved_children', 0)
            if total_capacity > 0:
                utilization = (reserved / total_capacity) * 100
                if utilization >= self.demand_capacity_threshold:
                    increase = price * (Decimal(str(self.demand_price_increase)) / Decimal('100'))
                    price += increase
                    applied_increases.append(f'Talep Artışı: +%{self.demand_price_increase}')
        
        # 7. Bayram tatili fiyat artışı
        if self.enable_holiday_pricing:
            # Türkiye resmi tatilleri (basit kontrol)
            turkish_holidays = self._get_turkish_holidays(tour_date.year)
            if tour_date in turkish_holidays:
                increase = price * (Decimal(str(self.holiday_price_increase)) / Decimal('100'))
                price += increase
                applied_increases.append(f'Bayram Tatili: +%{self.holiday_price_increase}')
        
        # Negatif fiyat kontrolü
        if price < 0:
            price = Decimal('0')
        
        return price
    
    def _get_turkish_holidays(self, year):
        """Türkiye resmi tatilleri (basit versiyon)"""
        from datetime import date
        holidays = []
        
        # Sabit tatiller
        holidays.append(date(year, 1, 1))  # Yılbaşı
        holidays.append(date(year, 4, 23))  # Ulusal Egemenlik ve Çocuk Bayramı
        holidays.append(date(year, 5, 1))   # Emek ve Dayanışma Günü
        holidays.append(date(year, 5, 19))  # Atatürk'ü Anma, Gençlik ve Spor Bayramı
        holidays.append(date(year, 7, 15))  # Demokrasi ve Milli Birlik Günü
        holidays.append(date(year, 8, 30))  # Zafer Bayramı
        holidays.append(date(year, 10, 29)) # Cumhuriyet Bayramı
        
        # Dini bayramlar (basit hesaplama - gerçekte Hicri takvime göre hesaplanmalı)
        # Kurban Bayramı ve Ramazan Bayramı için daha gelişmiş bir sistem gerekir
        # Şimdilik basit bir yaklaşım kullanıyoruz
        
        return holidays
    
    def get_available_capacity(self, date):
        """Belirli bir tarih için müsait kontenjan"""
        tour_date = self.tour_dates.filter(date=date, is_active=True).first()
        if not tour_date:
            return {'adults': 0, 'children': 0, 'total': 0}
        
        # Tarih bazlı kontenjan varsa onu kullan
        max_adults = tour_date.max_adults if tour_date.max_adults is not None else self.max_adults
        max_children = tour_date.max_children if tour_date.max_children is not None else self.max_children
        
        # Rezerve edilmiş kontenjan
        reserved_adults = tour_date.reservations.filter(
            status__in=['confirmed', 'pending']
        ).aggregate(total=models.Sum('adult_count'))['total'] or 0
        
        reserved_children = tour_date.reservations.filter(
            status__in=['confirmed', 'pending']
        ).aggregate(total=models.Sum('child_count'))['total'] or 0
        
        available_adults = max(0, max_adults - reserved_adults)
        available_children = max(0, max_children - reserved_children)
        
        return {
            'adults': available_adults,
            'children': available_children,
            'total': available_adults + available_children,
            'max_adults': max_adults,
            'max_children': max_children,
            'reserved_adults': reserved_adults,
            'reserved_children': reserved_children,
        }
    
    def generate_pdf_program(self):
        """PDF program oluştur"""
        # PDF oluşturma işlemi views'da yapılacak
        pass


class TourDate(TimeStampedModel):
    """Tur Tarihleri - Her tarih için ayrı fiyat ve kontenjan"""
    tour = models.ForeignKey(Tour, on_delete=models.CASCADE, related_name='tour_dates', verbose_name='Tur')
    date = models.DateField('Tur Tarihi')
    
    # Tarih bazlı fiyatlar (genel fiyattan farklıysa)
    adult_price = models.DecimalField('Yetişkin Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    child_price = models.DecimalField('Çocuk Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    group_price = models.DecimalField('Grup Fiyatı', max_digits=10, decimal_places=2, null=True, blank=True)
    
    # Tarih bazlı kontenjan (genel kontenjandan farklıysa)
    max_adults = models.IntegerField('Maksimum Yetişkin', null=True, blank=True)
    max_children = models.IntegerField('Maksimum Çocuk', null=True, blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_full = models.BooleanField('Dolu mu?', default=False)
    
    class Meta:
        verbose_name = 'Tur Tarihi'
        verbose_name_plural = 'Tur Tarihleri'
        unique_together = ('tour', 'date')
        ordering = ['date']
    
    def __str__(self):
        return f"{self.tour.name} - {self.date.strftime('%d.%m.%Y')}"
    
    def get_adult_price(self):
        """Yetişkin fiyatı - tarih bazlı varsa onu, yoksa genel fiyatı"""
        return self.adult_price if self.adult_price is not None else self.tour.adult_price
    
    def get_child_price(self):
        """Çocuk fiyatı - tarih bazlı varsa onu, yoksa genel fiyatı"""
        return self.child_price if self.child_price is not None else self.tour.child_price


class TourProgram(TimeStampedModel):
    """Gün Gün Tur Programı"""
    tour = models.ForeignKey(Tour, on_delete=models.CASCADE, related_name='programs', verbose_name='Tur')
    day_number = models.IntegerField('Gün Numarası', validators=[MinValueValidator(1)])
    title = models.CharField('Gün Başlığı', max_length=200, blank=True, help_text='Örn: 1. Gün: İstanbul - İzmir')
    description = models.TextField('Gün Programı', help_text='O günün detaylı programı')
    activities = models.TextField('Aktiviteler', blank=True, help_text='Yapılacak aktiviteler')
    meals = models.CharField('Yemekler', max_length=200, blank=True, help_text='Örn: Kahvaltı, Öğle Yemeği')
    accommodation = models.CharField('Konaklama', max_length=200, blank=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Tur Programı'
        verbose_name_plural = 'Tur Programları'
        unique_together = ('tour', 'day_number')
        ordering = ['tour', 'day_number']
    
    def __str__(self):
        return f"{self.tour.name} - {self.day_number}. Gün"


class TourImage(TimeStampedModel):
    """Tur Resimleri (Galeri)"""
    tour = models.ForeignKey(Tour, on_delete=models.CASCADE, related_name='images', verbose_name='Tur')
    image = models.ImageField('Resim', upload_to='tours/gallery/')
    title = models.CharField('Başlık', max_length=200, blank=True)
    alt_text = models.CharField('Alt Metin', max_length=200, blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Tur Resmi'
        verbose_name_plural = 'Tur Resimleri'
        ordering = ['tour', 'sort_order']
    
    def __str__(self):
        return f"{self.tour.name} - {self.title or 'Resim'}"


class TourVideo(TimeStampedModel):
    """Tur Videoları (YouTube, Instagram)"""
    tour = models.ForeignKey(Tour, on_delete=models.CASCADE, related_name='videos', verbose_name='Tur')
    VIDEO_TYPE_CHOICES = [
        ('youtube', 'YouTube'),
        ('instagram', 'Instagram'),
    ]
    video_type = models.CharField('Video Tipi', max_length=20, choices=VIDEO_TYPE_CHOICES, default='youtube')
    video_url = models.URLField('Video URL')
    video_id = models.CharField('Video ID', max_length=100, blank=True, help_text='YouTube video ID veya Instagram post ID')
    title = models.CharField('Başlık', max_length=200, blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Tur Videosu'
        verbose_name_plural = 'Tur Videoları'
        ordering = ['tour', 'sort_order']
    
    def __str__(self):
        return f"{self.tour.name} - {self.title or self.video_type}"


class TourExtraService(TimeStampedModel):
    """Ekstra Hizmetler (Kişi başı fiyatlanabilir)"""
    tour = models.ForeignKey(Tour, on_delete=models.CASCADE, related_name='extra_services', verbose_name='Tur')
    name = models.CharField('Hizmet Adı', max_length=200)
    description = models.TextField('Açıklama', blank=True)
    price_per_person = models.DecimalField('Kişi Başı Fiyat', max_digits=10, decimal_places=2, default=0)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Ekstra Hizmet'
        verbose_name_plural = 'Ekstra Hizmetler'
        ordering = ['tour', 'sort_order']
    
    def __str__(self):
        return f"{self.tour.name} - {self.name}"


class TourRoute(TimeStampedModel):
    """Tur Rota Bilgisi (Harita için şehir şehir)"""
    tour = models.ForeignKey(Tour, on_delete=models.CASCADE, related_name='routes', verbose_name='Tur')
    city = models.ForeignKey(TourCity, on_delete=models.CASCADE, related_name='tour_routes', verbose_name='Şehir')
    order = models.IntegerField('Sıra', validators=[MinValueValidator(1)], help_text='Rota sırası')
    is_departure = models.BooleanField('Kalkış Noktası mı?', default=False)
    is_destination = models.BooleanField('Varış Noktası mı?', default=False)
    stay_duration = models.CharField('Kalış Süresi', max_length=50, blank=True, help_text='Örn: 2 gün, 1 gece')
    
    class Meta:
        verbose_name = 'Tur Rotası'
        verbose_name_plural = 'Tur Rotaları'
        unique_together = ('tour', 'order')
        ordering = ['tour', 'order']
    
    def __str__(self):
        return f"{self.tour.name} - {self.city.name} ({self.order}. Durak)"


# ==================== REZERVASYON MODELLERİ ====================

class TourReservation(TimeStampedModel):
    """Tur Rezervasyonu"""
    
    # Tur ve Tarih
    tour = models.ForeignKey(Tour, on_delete=models.PROTECT, related_name='reservations', verbose_name='Tur')
    tour_date = models.ForeignKey('TourDate', on_delete=models.PROTECT, related_name='reservations', verbose_name='Tur Tarihi')
    
    # Rezervasyon Bilgileri
    reservation_code = models.CharField('Rezervasyon Kodu', max_length=50, unique=True, db_index=True)
    
    # Müşteri (Merkezi CRM entegrasyonu)
    customer = models.ForeignKey('tenant_core.Customer', on_delete=models.SET_NULL, null=True, blank=True,
                               related_name='tour_reservations', verbose_name='Müşteri Profili')
    
    # Müşteri Bilgileri (Customer bulunamazsa manuel giriş için)
    customer_name = models.CharField('Müşteri Adı', max_length=100)
    customer_surname = models.CharField('Müşteri Soyadı', max_length=100)
    customer_email = models.EmailField('E-posta', db_index=True)
    customer_phone = models.CharField('Telefon', max_length=20)
    customer_tc = models.CharField('TC Kimlik No', max_length=11, blank=True)
    customer_address = models.TextField('Adres', blank=True)
    
    # Kişi Sayıları
    adult_count = models.IntegerField('Yetişkin Sayısı', validators=[MinValueValidator(0)], default=0)
    child_count = models.IntegerField('Çocuk Sayısı', validators=[MinValueValidator(0)], default=0)
    total_people = models.IntegerField('Toplam Kişi', validators=[MinValueValidator(1)])
    
    # Fiyat Hesaplama
    adult_price = models.DecimalField('Yetişkin Birim Fiyat', max_digits=10, decimal_places=2)
    child_price = models.DecimalField('Çocuk Birim Fiyat', max_digits=10, decimal_places=2)
    subtotal = models.DecimalField('Ara Toplam', max_digits=10, decimal_places=2)
    extra_services_total = models.DecimalField('Ekstra Hizmetler Toplam', max_digits=10, decimal_places=2, default=0)
    discount_amount = models.DecimalField('İndirim Tutarı', max_digits=10, decimal_places=2, default=0)
    total_amount = models.DecimalField('Toplam Tutar', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Durum
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('confirmed', 'Onaylandı'),
        ('cancelled', 'İptal Edildi'),
        ('refunded', 'İade Edildi'),
        ('completed', 'Tamamlandı'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    
    # Satış Elemanı
    sales_person = models.ForeignKey(
        'tenant_core.TenantUser',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='tour_reservations',
        verbose_name='Satış Elemanı'
    )
    
    # Acente ve Komisyon
    agency = models.ForeignKey('TourAgency', on_delete=models.SET_NULL, null=True, blank=True,
                              related_name='reservations', verbose_name='Acente')
    
    # Promosyon Kodu
    promo_code = models.CharField('Promosyon Kodu', max_length=50, blank=True, db_index=True)
    campaign = models.ForeignKey('TourCampaign', on_delete=models.SET_NULL, null=True, blank=True,
                                related_name='reservations', verbose_name='Kampanya')
    
    # Ödeme
    payment_status = models.CharField('Ödeme Durumu', max_length=20, choices=[
        ('pending', 'Beklemede'),
        ('partial', 'Kısmi Ödendi'),
        ('paid', 'Ödendi'),
        ('refunded', 'İade Edildi'),
    ], default='pending')
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    # Voucher
    voucher_generated = models.BooleanField('Voucher Oluşturuldu mu?', default=False)
    voucher_pdf = models.FileField('Voucher PDF', upload_to='tours/vouchers/', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Tur Rezervasyonu'
        verbose_name_plural = 'Tur Rezervasyonları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['reservation_code']),
            models.Index(fields=['status', 'payment_status']),
        ]
    
    def __str__(self):
        return f"{self.reservation_code} - {self.customer_name} {self.customer_surname}"
    
    def save(self, *args, **kwargs):
        if not self.reservation_code:
            import random
            import string
            self.reservation_code = f"TR{''.join(random.choices(string.ascii_uppercase + string.digits, k=8))}"
        
        # Toplam kişi hesapla
        self.total_people = self.adult_count + self.child_count
        
        # Ara toplam hesapla
        adult_total = Decimal(str(self.adult_count)) * self.adult_price
        child_total = Decimal(str(self.child_count)) * self.child_price
        self.subtotal = adult_total + child_total
        
        # Toplam tutar
        self.total_amount = self.subtotal + self.extra_services_total - self.discount_amount
        
        # Müşteri profilini eşleştir veya oluştur (Merkezi CRM entegrasyonu)
        if not self.customer and (self.customer_email or self.customer_phone or self.customer_tc):
            from apps.tenant_apps.core.models import Customer as CoreCustomer
            customer, created = CoreCustomer.get_or_create_by_identifier(
                email=self.customer_email,
                phone=self.customer_phone,
                tc_no=self.customer_tc,
                defaults={
                    'first_name': self.customer_name,
                    'last_name': self.customer_surname,
                    'address': self.customer_address,
                }
            )
            self.customer = customer
            
            # Customer varsa bilgileri güncelle
            if self.customer:
                self.customer_name = self.customer.first_name
                self.customer_surname = self.customer.last_name
                if self.customer.email:
                    self.customer_email = self.customer.email
                if self.customer.phone:
                    self.customer_phone = self.customer.phone
                if self.customer.tc_no:
                    self.customer_tc = self.customer.tc_no
                if self.customer.address:
                    self.customer_address = self.customer.address
        
        super().save(*args, **kwargs)
        
        # Rezervasyon onaylandığında sadakat puanı ekle ve istatistikleri güncelle
        if self.customer and self.status in ['confirmed', 'completed']:
            from django.db.models import Sum
            # Sadakat puanı ekle (her 100 TL için 1 puan)
            points = int(self.total_amount / 100)
            if points > 0:
                self.customer.add_loyalty_points(points, f'Tur Rezervasyonu: {self.reservation_code}', module='tours')
            
            # Müşteri istatistiklerini güncelle (tüm tur rezervasyonlarından)
            tour_reservations = TourReservation.objects.filter(
                customer=self.customer,
                status__in=['confirmed', 'completed']
            )
            self.customer.total_reservations = tour_reservations.count()
            total_spent_result = tour_reservations.aggregate(total=Sum('total_amount'))
            self.customer.total_spent = total_spent_result['total'] or Decimal('0')
            self.customer.last_reservation_date = self.created_at.date() if self.created_at else timezone.now().date()
            self.customer.save()
        
        # Acente komisyonu oluştur
        if self.agency and self.status in ['confirmed', 'completed']:
            commission, created = TourReservationCommission.objects.get_or_create(
                reservation=self,
                defaults={
                    'agency': self.agency,
                    'base_amount': self.total_amount,
                    'commission_rate': self.agency.commission_rate,
                    'currency': self.currency,
                }
            )
            if not created:
                # Mevcut komisyonu güncelle
                commission.base_amount = self.total_amount
                commission.commission_rate = self.agency.commission_rate
                commission.save()
    
    def calculate_total(self):
        """Toplam tutarı yeniden hesapla (Dinamik fiyatlandırma ile)"""
        # Dinamik fiyatlandırma ile güncel fiyatları al
        reservation_date = self.created_at.date() if self.created_at else timezone.now().date()
        tour_date_obj = self.tour_date.date if hasattr(self.tour_date, 'date') else self.tour_date
        
        # Dinamik fiyatlandırma aktifse kullan
        if self.tour.enable_dynamic_pricing:
            adult_price = self.tour.get_current_price(
                date=tour_date_obj,
                is_adult=True,
                reservation_date=reservation_date
            )
            child_price = self.tour.get_current_price(
                date=tour_date_obj,
                is_adult=False,
                reservation_date=reservation_date
            )
        else:
            # Eski yöntem (dinamik fiyatlandırma yoksa)
            adult_price = self.adult_price
            child_price = self.child_price
        
        # Fiyatları güncelle
        self.adult_price = adult_price
        self.child_price = child_price
        
        # Ara toplam hesapla
        adult_total = Decimal(str(self.adult_count)) * adult_price
        child_total = Decimal(str(self.child_count)) * child_price
        subtotal = adult_total + child_total
        
        # Ekstra hizmetler toplamı
        extra_total = self.reservation_extra_services.aggregate(
            total=models.Sum('price')
        )['total'] or Decimal('0')
        
        # Grup fiyat kontrolü (dinamik fiyatlandırma öncelikli, grup fiyatı sadece dinamik fiyatlandırma yoksa)
        total_people = self.adult_count + self.child_count
        if not self.tour.enable_dynamic_pricing and self.tour.group_price and total_people >= self.tour.group_min_people:
            # Grup fiyatı kullan (dinamik fiyatlandırma yoksa)
            subtotal = Decimal(str(total_people)) * self.tour.group_price
        
        # Kampanya/Promosyon kodu indirimi
        campaign_discount = Decimal('0')
        if self.campaign:
            campaign_discount = self.campaign.calculate_discount(subtotal + extra_total, self.customer)
        elif self.promo_code:
            try:
                promo = TourPromoCode.objects.get(code=self.promo_code, is_active=True)
                is_valid, message = promo.is_valid(self.customer)
                if is_valid:
                    campaign_discount = promo.campaign.calculate_discount(subtotal + extra_total, self.customer)
                    self.campaign = promo.campaign
            except TourPromoCode.DoesNotExist:
                pass
        
        # Toplam tutar (kampanya indirimi dahil)
        total = subtotal + extra_total - self.discount_amount - campaign_discount
        
        # Negatif kontrolü
        if total < 0:
            total = Decimal('0')
        
        self.total_amount = total
        self.subtotal = subtotal
        self.extra_services_total = extra_total
        
        # Kampanya kullanım sayısını artır
        if self.campaign and self.status in ['confirmed', 'completed']:
            self.campaign.usage_count += 1
            self.campaign.save()
        
        return total
    
    def check_capacity(self):
        """Kontenjan kontrolü yap"""
        if not self.tour_date:
            return {'available': False, 'message': 'Tur tarihi seçilmemiş'}
        
        tour_date = self.tour_date
        capacity = self.tour.get_available_capacity(tour_date.date)
        
        if self.adult_count > capacity['adults']:
            return {
                'available': False,
                'message': f'Müsait yetişkin kontenjanı yetersiz. Müsait: {capacity["adults"]}, İstenen: {self.adult_count}',
                'add_to_waiting_list': True,  # Bekleme listesine eklenebilir
            }
        
        if self.child_count > capacity['children']:
            return {
                'available': False,
                'message': f'Müsait çocuk kontenjanı yetersiz. Müsait: {capacity["children"]}, İstenen: {self.child_count}',
                'add_to_waiting_list': True,  # Bekleme listesine eklenebilir
            }
        
        return {'available': True, 'message': 'Kontenjan yeterli'}
    
    def add_to_waiting_list(self, priority=0):
        """Rezervasyonu bekleme listesine ekle"""
        from .models import TourWaitingList
        
        waiting_list, created = TourWaitingList.objects.get_or_create(
            tour=self.tour,
            tour_date=self.tour_date,
            customer_email=self.customer_email,
            status='waiting',
            defaults={
                'customer_name': self.customer_name,
                'customer_surname': self.customer_surname,
                'customer_phone': self.customer_phone,
                'customer_tc': self.customer_tc,
                'adult_count': self.adult_count,
                'child_count': self.child_count,
                'total_people': self.total_people,
                'priority': priority,
            }
        )
        
        if not created:
            # Mevcut kayıt varsa güncelle
            waiting_list.adult_count = self.adult_count
            waiting_list.child_count = self.child_count
            waiting_list.total_people = self.total_people
            waiting_list.priority = priority
            waiting_list.status = 'waiting'
            waiting_list.save()
        
        return waiting_list
    
    def reduce_capacity(self):
        """Rezervasyon sonrası kontenjanı azalt"""
        if self.status in ['confirmed', 'pending']:
            # Kontenjan zaten rezerve edilmiş sayılır
            pass
        # İptal durumunda kontenjan geri verilir (cancel metodunda)
    
    def generate_voucher(self):
        """Voucher oluştur"""
        # Voucher oluşturma işlemi views'da yapılacak
        pass


class TourGuest(TimeStampedModel):
    """Rezervasyondaki Misafirler (Ad Soyad)"""
    reservation = models.ForeignKey(TourReservation, on_delete=models.CASCADE, related_name='guests', verbose_name='Rezervasyon')
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    is_adult = models.BooleanField('Yetişkin mi?', default=True)
    age = models.IntegerField('Yaş', null=True, blank=True)
    tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True)
    passport_no = models.CharField('Pasaport No', max_length=50, blank=True)
    
    class Meta:
        verbose_name = 'Tur Misafiri'
        verbose_name_plural = 'Tur Misafirleri'
        ordering = ['reservation', 'is_adult', 'first_name']
    
    def __str__(self):
        return f"{self.first_name} {self.last_name} ({'Yetişkin' if self.is_adult else 'Çocuk'})"


class TourReservationExtraService(TimeStampedModel):
    """Rezervasyona eklenen ekstra hizmetler"""
    reservation = models.ForeignKey(TourReservation, on_delete=models.CASCADE, related_name='reservation_extra_services', verbose_name='Rezervasyon')
    extra_service = models.ForeignKey(TourExtraService, on_delete=models.PROTECT, related_name='reservations', verbose_name='Ekstra Hizmet')
    quantity = models.IntegerField('Adet', validators=[MinValueValidator(1)], default=1)
    unit_price = models.DecimalField('Birim Fiyat', max_digits=10, decimal_places=2)
    total_price = models.DecimalField('Toplam Fiyat', max_digits=10, decimal_places=2)
    
    class Meta:
        verbose_name = 'Rezervasyon Ekstra Hizmeti'
        verbose_name_plural = 'Rezervasyon Ekstra Hizmetleri'
        unique_together = ('reservation', 'extra_service')
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.extra_service.name}"
    
    def save(self, *args, **kwargs):
        self.total_price = Decimal(str(self.quantity)) * self.unit_price
        super().save(*args, **kwargs)


class TourPayment(TimeStampedModel):
    """Tur Rezervasyon Ödemeleri"""
    reservation = models.ForeignKey(TourReservation, on_delete=models.CASCADE, related_name='payments', verbose_name='Rezervasyon')
    amount = models.DecimalField('Tutar', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    PAYMENT_METHOD_CHOICES = [
        ('cash', 'Nakit'),
        ('bank_transfer', 'Banka Havalesi'),
        ('credit_card', 'Kredi Kartı'),
        ('iyzico', 'İyzico'),
        ('paytr', 'PayTR'),
        ('nestpay', 'NestPay'),
        ('garanti', 'Garanti Sanal Pos'),
        ('akbank', 'Akbank Sanal Pos'),
        ('other', 'Diğer'),
    ]
    payment_method = models.CharField('Ödeme Yöntemi', max_length=50, choices=PAYMENT_METHOD_CHOICES)
    
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('completed', 'Tamamlandı'),
        ('failed', 'Başarısız'),
        ('refunded', 'İade Edildi'),
        ('cancelled', 'İptal Edildi'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    
    payment_date = models.DateField('Ödeme Tarihi', null=True, blank=True)
    transaction_id = models.CharField('İşlem ID', max_length=100, blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Tur Ödemesi'
        verbose_name_plural = 'Tur Ödemeleri'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.amount} {self.currency}"


class TourWaitingList(TimeStampedModel):
    """Tur Bekleme Listesi - Kontenjan dolduğunda müşterileri bekletir"""
    tour = models.ForeignKey(Tour, on_delete=models.CASCADE, related_name='waiting_lists', verbose_name='Tur')
    tour_date = models.ForeignKey(TourDate, on_delete=models.CASCADE, related_name='waiting_lists', verbose_name='Tur Tarihi')
    
    # Müşteri Bilgileri
    customer_name = models.CharField('Müşteri Adı', max_length=100)
    customer_surname = models.CharField('Müşteri Soyadı', max_length=100)
    customer_email = models.EmailField('E-posta', db_index=True)
    customer_phone = models.CharField('Telefon', max_length=20)
    customer_tc = models.CharField('TC Kimlik No', max_length=11, blank=True)
    
    # Kişi Sayıları
    adult_count = models.IntegerField('Yetişkin Sayısı', validators=[MinValueValidator(0)], default=0)
    child_count = models.IntegerField('Çocuk Sayısı', validators=[MinValueValidator(0)], default=0)
    total_people = models.IntegerField('Toplam Kişi', validators=[MinValueValidator(1)])
    
    # Öncelik ve Durum
    priority = models.IntegerField('Öncelik', default=0, help_text='Düşük sayı = Yüksek öncelik (VIP müşteriler için)')
    STATUS_CHOICES = [
        ('waiting', 'Beklemede'),
        ('notified', 'Bildirildi'),
        ('converted', 'Rezervasyona Dönüştürüldü'),
        ('cancelled', 'İptal Edildi'),
        ('expired', 'Süresi Doldu'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='waiting')
    
    # Bildirim Bilgileri
    notified_at = models.DateTimeField('Bildirim Tarihi', null=True, blank=True)
    notification_method = models.CharField('Bildirim Yöntemi', max_length=20, blank=True,
                                          choices=[('email', 'E-posta'), ('sms', 'SMS'), ('whatsapp', 'WhatsApp')])
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Bekleme Listesi'
        verbose_name_plural = 'Bekleme Listeleri'
        ordering = ['priority', 'created_at']
        indexes = [
            models.Index(fields=['tour', 'tour_date', 'status']),
            models.Index(fields=['status', 'priority']),
        ]
    
    def __str__(self):
        return f"{self.tour.name} - {self.customer_name} {self.customer_surname} ({self.get_status_display()})"
    
    def save(self, *args, **kwargs):
        # Toplam kişi hesapla
        self.total_people = self.adult_count + self.child_count
        super().save(*args, **kwargs)
    
    def notify_availability(self, method='email'):
        """Müsaitlik durumunda müşteriye bildirim gönder"""
        from django.utils import timezone
        from django.core.mail import send_mail
        from django.conf import settings
        
        self.status = 'notified'
        self.notified_at = timezone.now()
        self.notification_method = method
        self.save()
        
        # E-posta bildirimi
        if method == 'email':
            subject = f'Tur Müsaitlik Bildirimi - {self.tour.name}'
            message = f"""
            Sayın {self.customer_name} {self.customer_surname},
            
            Bekleme listesinde olduğunuz tur için müsaitlik oluştu!
            
            Tur: {self.tour.name}
            Tarih: {self.tour_date.date.strftime('%d.%m.%Y')}
            Kişi Sayısı: {self.total_people} ({self.adult_count} yetişkin, {self.child_count} çocuk)
            
            Lütfen en kısa sürede rezervasyon yapmak için bizimle iletişime geçin.
            
            İyi günler dileriz.
            """
            try:
                send_mail(
                    subject,
                    message,
                    settings.DEFAULT_FROM_EMAIL,
                    [self.customer_email],
                    fail_silently=False,
                )
            except Exception as e:
                # E-posta gönderilemezse log'a kaydet
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f'Bekleme listesi bildirimi gönderilemedi: {str(e)}')
        
        # SMS bildirimi (gelecekte entegre edilebilir)
        elif method == 'sms':
            # SMS API entegrasyonu burada olacak
            pass
        
        # WhatsApp bildirimi (gelecekte entegre edilebilir)
        elif method == 'whatsapp':
            # WhatsApp API entegrasyonu burada olacak
            pass
    
    def convert_to_reservation(self):
        """Bekleme listesindeki kaydı rezervasyona dönüştür"""
        from .models import TourReservation
        
        # Rezervasyon oluştur
        reservation = TourReservation.objects.create(
            tour=self.tour,
            tour_date=self.tour_date,
            customer_name=self.customer_name,
            customer_surname=self.customer_surname,
            customer_email=self.customer_email,
            customer_phone=self.customer_phone,
            customer_tc=self.customer_tc,
            adult_count=self.adult_count,
            child_count=self.child_count,
            total_people=self.total_people,
            adult_price=self.tour.get_current_price(date=self.tour_date.date, is_adult=True),
            child_price=self.tour.get_current_price(date=self.tour_date.date, is_adult=False),
            status='pending',
        )
        
        # Toplam tutarı hesapla
        reservation.calculate_total()
        reservation.save()
        
        # Bekleme listesi kaydını güncelle
        self.status = 'converted'
        self.save()
        
        return reservation


# ==================== CRM VE SADAKAT SİSTEMİ ====================

class TourCustomer(TimeStampedModel, SoftDeleteModel):
    """
    Tur Müşteri Profili - CRM ve Sadakat Sistemi
    
    DEPRECATED: Bu model artık kullanılmamaktadır.
    Lütfen merkezi Customer modelini (apps.tenant_apps.core.models.Customer) kullanın.
    Bu model sadece geriye dönük uyumluluk için korunmaktadır.
    
    Migration: python manage.py migrate_tour_customers_to_customers
    """
    
    # Temel Bilgiler
    customer_code = models.CharField('Müşteri Kodu', max_length=50, unique=True, db_index=True)
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    email = models.EmailField('E-posta', unique=True, db_index=True)
    phone = models.CharField('Telefon', max_length=20)
    tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True)
    
    # İletişim Bilgileri
    address = models.TextField('Adres', blank=True)
    city = models.CharField('Şehir', max_length=100, blank=True)
    country = models.CharField('Ülke', max_length=100, default='Türkiye')
    postal_code = models.CharField('Posta Kodu', max_length=10, blank=True)
    
    # Doğum Tarihi ve Özel Günler
    birth_date = models.DateField('Doğum Tarihi', null=True, blank=True)
    special_dates = models.JSONField('Özel Günler', default=list, blank=True,
                                     help_text='Örn: [{"date": "2024-12-25", "name": "Evlilik Yıldönümü"}]')
    
    # Sadakat Sistemi
    loyalty_points = models.IntegerField('Sadakat Puanı', default=0, validators=[MinValueValidator(0)])
    total_reservations = models.IntegerField('Toplam Rezervasyon', default=0)
    total_spent = models.DecimalField('Toplam Harcama', max_digits=12, decimal_places=2, default=0)
    
    # VIP Statüsü
    VIP_LEVEL_CHOICES = [
        ('regular', 'Normal'),
        ('silver', 'Gümüş (5+ rezervasyon)'),
        ('gold', 'Altın (10+ rezervasyon)'),
        ('platinum', 'Platin (20+ rezervasyon)'),
        ('diamond', 'Elmas (50+ rezervasyon)'),
    ]
    vip_level = models.CharField('VIP Seviyesi', max_length=20, choices=VIP_LEVEL_CHOICES, default='regular')
    
    # Tercihler
    preferred_regions = models.ManyToManyField(TourRegion, blank=True, related_name='preferred_customers', verbose_name='Tercih Edilen Bölgeler')
    preferred_tour_types = models.ManyToManyField(TourType, blank=True, related_name='preferred_customers', verbose_name='Tercih Edilen Tur Türleri')
    preferred_travel_months = models.JSONField('Tercih Edilen Seyahat Ayları', default=list, blank=True,
                                              help_text='Örn: [6, 7, 8] (Haziran, Temmuz, Ağustos)')
    
    # Notlar ve İstekler
    notes = models.TextField('Notlar', blank=True, help_text='Müşteri hakkında özel notlar')
    special_requests = models.TextField('Özel İstekler', blank=True, help_text='Müşterinin özel istekleri')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_vip = models.BooleanField('VIP Müşteri mi?', default=False)
    last_reservation_date = models.DateField('Son Rezervasyon Tarihi', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Tur Müşterisi'
        verbose_name_plural = 'Tur Müşterileri'
        ordering = ['-total_spent', '-created_at']
        indexes = [
            models.Index(fields=['email']),
            models.Index(fields=['customer_code']),
            models.Index(fields=['vip_level', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.first_name} {self.last_name} ({self.customer_code})"
    
    def save(self, *args, **kwargs):
        if not self.customer_code:
            import random
            import string
            self.customer_code = f"TC{''.join(random.choices(string.ascii_uppercase + string.digits, k=8))}"
        
        # VIP seviyesini güncelle
        if self.total_reservations >= 50:
            self.vip_level = 'diamond'
            self.is_vip = True
        elif self.total_reservations >= 20:
            self.vip_level = 'platinum'
            self.is_vip = True
        elif self.total_reservations >= 10:
            self.vip_level = 'gold'
            self.is_vip = True
        elif self.total_reservations >= 5:
            self.vip_level = 'silver'
            self.is_vip = True
        else:
            self.vip_level = 'regular'
            self.is_vip = False
        
        super().save(*args, **kwargs)
    
    def add_loyalty_points(self, points, reason=''):
        """Sadakat puanı ekle"""
        self.loyalty_points += points
        self.save()
        
        # Puan geçmişi kaydet
        TourLoyaltyHistory.objects.create(
            customer=self,
            points=points,
            reason=reason or 'Rezervasyon',
        )
    
    def use_loyalty_points(self, points):
        """Sadakat puanı kullan"""
        if self.loyalty_points >= points:
            self.loyalty_points -= points
            self.save()
            
            # Puan geçmişi kaydet
            TourLoyaltyHistory.objects.create(
                customer=self,
                points=-points,
                reason='Puan kullanımı',
            )
            return True
        return False
    
    def get_loyalty_discount(self):
        """Sadakat puanına göre indirim hesapla (100 puan = %1 indirim, max %10)"""
        discount_percentage = min(10, self.loyalty_points // 100)
        return discount_percentage
    
    def update_statistics(self):
        """Müşteri istatistiklerini güncelle"""
        reservations = TourReservation.objects.filter(
            customer_email=self.email,
            status__in=['confirmed', 'completed']
        )
        
        self.total_reservations = reservations.count()
        self.total_spent = reservations.aggregate(
            total=models.Sum('total_amount')
        )['total'] or Decimal('0')
        
        last_reservation = reservations.order_by('-created_at').first()
        if last_reservation:
            self.last_reservation_date = last_reservation.created_at.date()
        
        self.save()


class TourLoyaltyHistory(TimeStampedModel):
    """Sadakat Puanı Geçmişi"""
    customer = models.ForeignKey(TourCustomer, on_delete=models.CASCADE, related_name='loyalty_history', verbose_name='Müşteri')
    points = models.IntegerField('Puan', help_text='Pozitif = Ekleme, Negatif = Kullanım')
    reason = models.CharField('Sebep', max_length=200, blank=True)
    reservation = models.ForeignKey(TourReservation, on_delete=models.SET_NULL, null=True, blank=True,
                                    related_name='loyalty_points', verbose_name='Rezervasyon')
    
    class Meta:
        verbose_name = 'Sadakat Puanı Geçmişi'
        verbose_name_plural = 'Sadakat Puanı Geçmişleri'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.customer.customer_code} - {self.points} puan ({self.reason})"


class TourCustomerNote(TimeStampedModel):
    """Müşteri Notları"""
    customer = models.ForeignKey(TourCustomer, on_delete=models.CASCADE, related_name='notes_history', verbose_name='Müşteri')
    note = models.TextField('Not')
    created_by = models.ForeignKey('tenant_core.TenantUser', on_delete=models.SET_NULL, null=True,
                                   related_name='customer_notes', verbose_name='Oluşturan')
    is_important = models.BooleanField('Önemli mi?', default=False)
    
    class Meta:
        verbose_name = 'Müşteri Notu'
        verbose_name_plural = 'Müşteri Notları'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.customer.customer_code} - {self.note[:50]}"


# ==================== KOMİSYON VE ACENTE YÖNETİMİ ====================

class TourAgency(TimeStampedModel, SoftDeleteModel):
    """Tur Acentesi - Komisyon yönetimi için"""
    name = models.CharField('Acente Adı', max_length=200)
    code = models.SlugField('Acente Kodu', max_length=50, unique=True)
    contact_person = models.CharField('İletişim Kişisi', max_length=100)
    email = models.EmailField('E-posta')
    phone = models.CharField('Telefon', max_length=20)
    address = models.TextField('Adres', blank=True)
    tax_number = models.CharField('Vergi No', max_length=20, blank=True)
    tax_office = models.CharField('Vergi Dairesi', max_length=100, blank=True)
    
    # Komisyon Ayarları
    commission_type = models.CharField('Komisyon Tipi', max_length=20,
                                       choices=[('percentage', 'Yüzde'), ('fixed', 'Sabit Tutar')],
                                       default='percentage')
    commission_rate = models.DecimalField('Komisyon Oranı', max_digits=5, decimal_places=2, default=0,
                                         help_text='Yüzde ise %10 = 10.00, Sabit tutar ise TL cinsinden')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Tur Acentesi'
        verbose_name_plural = 'Tur Acenteleri'
        ordering = ['name']
    
    def __str__(self):
        return f"{self.name} ({self.code})"
    
    def calculate_commission(self, amount):
        """Komisyon hesapla"""
        if self.commission_type == 'percentage':
            return amount * (self.commission_rate / Decimal('100'))
        else:
            return self.commission_rate


class TourReservationCommission(TimeStampedModel):
    """Rezervasyon Komisyonu"""
    reservation = models.OneToOneField(TourReservation, on_delete=models.CASCADE,
                                       related_name='commission', verbose_name='Rezervasyon')
    agency = models.ForeignKey(TourAgency, on_delete=models.PROTECT,
                               related_name='commissions', verbose_name='Acente')
    
    # Komisyon Bilgileri
    base_amount = models.DecimalField('Temel Tutar', max_digits=10, decimal_places=2,
                                     help_text='Komisyon hesaplanan tutar')
    commission_rate = models.DecimalField('Komisyon Oranı', max_digits=5, decimal_places=2)
    commission_amount = models.DecimalField('Komisyon Tutarı', max_digits=10, decimal_places=2)
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Ödeme Durumu
    PAYMENT_STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('paid', 'Ödendi'),
        ('cancelled', 'İptal Edildi'),
    ]
    payment_status = models.CharField('Ödeme Durumu', max_length=20, choices=PAYMENT_STATUS_CHOICES, default='pending')
    payment_date = models.DateField('Ödeme Tarihi', null=True, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon Komisyonu'
        verbose_name_plural = 'Rezervasyon Komisyonları'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - {self.agency.name} ({self.commission_amount} {self.currency})"
    
    def save(self, *args, **kwargs):
        # Komisyon tutarını hesapla
        if not self.commission_amount:
            self.commission_amount = self.agency.calculate_commission(self.base_amount)
        super().save(*args, **kwargs)


# ==================== OPERASYONEL YÖNETİM ====================

class TourGuide(TimeStampedModel, SoftDeleteModel):
    """Tur Rehberi"""
    name = models.CharField('Rehber Adı', max_length=100)
    surname = models.CharField('Rehber Soyadı', max_length=100)
    email = models.EmailField('E-posta', blank=True)
    phone = models.CharField('Telefon', max_length=20)
    license_number = models.CharField('Rehber Belge No', max_length=50, blank=True)
    languages = models.JSONField('Bildiği Diller', default=list, blank=True,
                                help_text='Örn: ["Türkçe", "İngilizce", "Almanca"]')
    specialties = models.TextField('Uzmanlık Alanları', blank=True,
                                  help_text='Örn: Kültür turları, Doğa turları')
    hourly_rate = models.DecimalField('Saatlik Ücret', max_digits=10, decimal_places=2, default=0)
    daily_rate = models.DecimalField('Günlük Ücret', max_digits=10, decimal_places=2, default=0)
    is_active = models.BooleanField('Aktif mi?', default=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Tur Rehberi'
        verbose_name_plural = 'Tur Rehberleri'
        ordering = ['name', 'surname']
    
    def __str__(self):
        return f"{self.name} {self.surname}"


class TourVehicle(TimeStampedModel, SoftDeleteModel):
    """Tur Aracı"""
    plate_number = models.CharField('Plaka', max_length=20, unique=True)
    brand = models.CharField('Marka', max_length=50)
    model = models.CharField('Model', max_length=50)
    year = models.IntegerField('Yıl', validators=[MinValueValidator(1900), MaxValueValidator(2100)])
    capacity = models.IntegerField('Kapasite', validators=[MinValueValidator(1)])
    vehicle_type = models.CharField('Araç Tipi', max_length=50,
                                    choices=[('bus', 'Otobüs'), ('minibus', 'Minibüs'), ('van', 'Van'), ('car', 'Araba')])
    driver_name = models.CharField('Şoför Adı', max_length=100, blank=True)
    driver_phone = models.CharField('Şoför Telefonu', max_length=20, blank=True)
    daily_rate = models.DecimalField('Günlük Ücret', max_digits=10, decimal_places=2, default=0)
    is_active = models.BooleanField('Aktif mi?', default=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Tur Aracı'
        verbose_name_plural = 'Tur Araçları'
        ordering = ['plate_number']
    
    def __str__(self):
        return f"{self.brand} {self.model} - {self.plate_number}"


class TourHotel(TimeStampedModel, SoftDeleteModel):
    """Tur Oteli"""
    name = models.CharField('Otel Adı', max_length=200)
    city = models.ForeignKey(TourCity, on_delete=models.SET_NULL, null=True, blank=True,
                            related_name='hotels', verbose_name='Şehir')
    address = models.TextField('Adres', blank=True)
    phone = models.CharField('Telefon', max_length=20, blank=True)
    email = models.EmailField('E-posta', blank=True)
    star_rating = models.IntegerField('Yıldız', validators=[MinValueValidator(1), MaxValueValidator(5)], default=3)
    room_types = models.JSONField('Oda Tipleri', default=list, blank=True,
                                 help_text='Örn: [{"type": "Standart", "capacity": 2}, {"type": "Suit", "capacity": 4}]')
    daily_rate_per_person = models.DecimalField('Kişi Başı Günlük Ücret', max_digits=10, decimal_places=2, default=0)
    is_active = models.BooleanField('Aktif mi?', default=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Tur Oteli'
        verbose_name_plural = 'Tur Otelleri'
        ordering = ['name']
    
    def __str__(self):
        return f"{self.name} ({self.city.name if self.city else 'Şehir Yok'})"


class TourTransfer(TimeStampedModel, SoftDeleteModel):
    """Transfer Hizmeti"""
    name = models.CharField('Transfer Adı', max_length=200)
    transfer_type = models.CharField('Transfer Tipi', max_length=50,
                                    choices=[('airport', 'Havalimanı'), ('hotel', 'Otel'), ('station', 'İstasyon'), ('other', 'Diğer')])
    from_location = models.CharField('Nereden', max_length=200)
    to_location = models.CharField('Nereye', max_length=200)
    distance_km = models.DecimalField('Mesafe (KM)', max_digits=10, decimal_places=2, null=True, blank=True)
    duration_minutes = models.IntegerField('Süre (Dakika)', validators=[MinValueValidator(0)], null=True, blank=True)
    price_per_person = models.DecimalField('Kişi Başı Fiyat', max_digits=10, decimal_places=2, default=0)
    price_per_vehicle = models.DecimalField('Araç Başı Fiyat', max_digits=10, decimal_places=2, default=0)
    is_active = models.BooleanField('Aktif mi?', default=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Transfer Hizmeti'
        verbose_name_plural = 'Transfer Hizmetleri'
        ordering = ['name']
    
    def __str__(self):
        return f"{self.name} ({self.from_location} - {self.to_location})"


class TourReservationOperation(TimeStampedModel):
    """Rezervasyon Operasyonel Detayları"""
    reservation = models.OneToOneField(TourReservation, on_delete=models.CASCADE,
                                      related_name='operation', verbose_name='Rezervasyon')
    
    # Rehber
    guide = models.ForeignKey(TourGuide, on_delete=models.SET_NULL, null=True, blank=True,
                             related_name='reservations', verbose_name='Rehber')
    guide_days = models.IntegerField('Rehber Gün Sayısı', default=0, validators=[MinValueValidator(0)])
    guide_total_cost = models.DecimalField('Rehber Toplam Maliyet', max_digits=10, decimal_places=2, default=0)
    
    # Araç
    vehicle = models.ForeignKey(TourVehicle, on_delete=models.SET_NULL, null=True, blank=True,
                               related_name='reservations', verbose_name='Araç')
    vehicle_days = models.IntegerField('Araç Gün Sayısı', default=0, validators=[MinValueValidator(0)])
    vehicle_total_cost = models.DecimalField('Araç Toplam Maliyet', max_digits=10, decimal_places=2, default=0)
    
    # Otel
    hotels = models.ManyToManyField(TourHotel, blank=True, related_name='reservations', verbose_name='Oteller')
    hotel_nights = models.IntegerField('Otel Gece Sayısı', default=0, validators=[MinValueValidator(0)])
    hotel_total_cost = models.DecimalField('Otel Toplam Maliyet', max_digits=10, decimal_places=2, default=0)
    
    # Transfer
    transfers = models.ManyToManyField(TourTransfer, blank=True, related_name='reservations', verbose_name='Transferler')
    transfer_total_cost = models.DecimalField('Transfer Toplam Maliyet', max_digits=10, decimal_places=2, default=0)
    
    # Toplam Operasyonel Maliyet
    total_operation_cost = models.DecimalField('Toplam Operasyonel Maliyet', max_digits=10, decimal_places=2, default=0)
    
    # Notlar
    notes = models.TextField('Operasyonel Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Rezervasyon Operasyonu'
        verbose_name_plural = 'Rezervasyon Operasyonları'
    
    def __str__(self):
        return f"{self.reservation.reservation_code} - Operasyon"
    
    def save(self, *args, **kwargs):
        # Toplam operasyonel maliyeti hesapla
        self.total_operation_cost = (
            self.guide_total_cost +
            self.vehicle_total_cost +
            self.hotel_total_cost +
            self.transfer_total_cost
        )
        super().save(*args, **kwargs)


# ==================== OTOMATİK BİLDİRİM SİSTEMİ ====================

class TourNotificationTemplate(TimeStampedModel, SoftDeleteModel):
    """Bildirim Şablonları"""
    name = models.CharField('Şablon Adı', max_length=200)
    code = models.SlugField('Şablon Kodu', max_length=50, unique=True)
    notification_type = models.CharField('Bildirim Tipi', max_length=20,
                                        choices=[('email', 'E-posta'), ('sms', 'SMS'), ('whatsapp', 'WhatsApp')])
    trigger_event = models.CharField('Tetikleyici Olay', max_length=50,
                                     choices=[
                                         ('reservation_created', 'Rezervasyon Oluşturuldu'),
                                         ('reservation_confirmed', 'Rezervasyon Onaylandı'),
                                         ('reservation_cancelled', 'Rezervasyon İptal Edildi'),
                                         ('payment_received', 'Ödeme Alındı'),
                                         ('tour_reminder', 'Tur Hatırlatması'),
                                         ('waiting_list_available', 'Bekleme Listesi Müsait'),
                                         ('voucher_ready', 'Voucher Hazır'),
                                     ])
    subject = models.CharField('Konu', max_length=200, blank=True, help_text='E-posta için')
    message = models.TextField('Mesaj İçeriği')
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    # Değişkenler (JSON format)
    # Örn: {"customer_name": "Müşteri Adı", "tour_name": "Tur Adı", "reservation_code": "Rezervasyon Kodu"}
    variables = models.JSONField('Kullanılabilir Değişkenler', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Bildirim Şablonu'
        verbose_name_plural = 'Bildirim Şablonları'
        ordering = ['notification_type', 'trigger_event']
    
    def __str__(self):
        return f"{self.name} ({self.get_notification_type_display()})"


class TourNotification(TimeStampedModel):
    """Gönderilen Bildirimler"""
    template = models.ForeignKey(TourNotificationTemplate, on_delete=models.SET_NULL, null=True,
                                related_name='notifications', verbose_name='Şablon')
    notification_type = models.CharField('Bildirim Tipi', max_length=20,
                                        choices=[('email', 'E-posta'), ('sms', 'SMS'), ('whatsapp', 'WhatsApp')])
    recipient_email = models.EmailField('Alıcı E-posta', blank=True)
    recipient_phone = models.CharField('Alıcı Telefon', max_length=20, blank=True)
    subject = models.CharField('Konu', max_length=200, blank=True)
    message = models.TextField('Mesaj')
    
    # İlişkili Kayıtlar
    reservation = models.ForeignKey(TourReservation, on_delete=models.SET_NULL, null=True, blank=True,
                                   related_name='notifications', verbose_name='Rezervasyon')
    customer = models.ForeignKey(TourCustomer, on_delete=models.SET_NULL, null=True, blank=True,
                                related_name='notifications', verbose_name='Müşteri')
    
    # Durum
    STATUS_CHOICES = [
        ('pending', 'Beklemede'),
        ('sent', 'Gönderildi'),
        ('failed', 'Başarısız'),
        ('delivered', 'Teslim Edildi'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='pending')
    sent_at = models.DateTimeField('Gönderilme Tarihi', null=True, blank=True)
    error_message = models.TextField('Hata Mesajı', blank=True)
    
    class Meta:
        verbose_name = 'Bildirim'
        verbose_name_plural = 'Bildirimler'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['status', 'notification_type']),
            models.Index(fields=['reservation', 'status']),
        ]
    
    def __str__(self):
        return f"{self.get_notification_type_display()} - {self.recipient_email or self.recipient_phone} ({self.get_status_display()})"


# ==================== KAMPANYA VE PROMOSYON YÖNETİMİ ====================

class TourCampaign(TimeStampedModel, SoftDeleteModel):
    """Tur Kampanyaları"""
    name = models.CharField('Kampanya Adı', max_length=200)
    code = models.SlugField('Kampanya Kodu', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    
    # Kampanya Tipi
    CAMPAIGN_TYPE_CHOICES = [
        ('discount_percentage', 'Yüzde İndirim'),
        ('discount_fixed', 'Sabit Tutar İndirim'),
        ('buy_one_get_one', 'Al 1 Bedava 1'),
        ('early_booking', 'Erken Rezervasyon'),
        ('group_discount', 'Grup İndirimi'),
        ('loyalty_bonus', 'Sadakat Bonusu'),
    ]
    campaign_type = models.CharField('Kampanya Tipi', max_length=30, choices=CAMPAIGN_TYPE_CHOICES)
    
    # İndirim Ayarları
    discount_percentage = models.DecimalField('İndirim Yüzdesi', max_digits=5, decimal_places=2, null=True, blank=True)
    discount_amount = models.DecimalField('İndirim Tutarı', max_digits=10, decimal_places=2, null=True, blank=True)
    min_purchase_amount = models.DecimalField('Minimum Alış Tutarı', max_digits=10, decimal_places=2, null=True, blank=True)
    max_discount_amount = models.DecimalField('Maksimum İndirim Tutarı', max_digits=10, decimal_places=2, null=True, blank=True)
    
    # Tarih Aralığı
    start_date = models.DateField('Başlangıç Tarihi')
    end_date = models.DateField('Bitiş Tarihi')
    
    # Uygulanabilir Turlar
    applicable_tours = models.ManyToManyField(Tour, blank=True, related_name='campaigns', verbose_name='Uygulanabilir Turlar')
    applicable_tour_types = models.ManyToManyField(TourType, blank=True, related_name='campaigns', verbose_name='Uygulanabilir Tur Türleri')
    
    # Kullanım Limitleri
    usage_limit = models.IntegerField('Kullanım Limiti', null=True, blank=True,
                                     help_text='Toplam kaç kez kullanılabileceği (boş = sınırsız)')
    usage_count = models.IntegerField('Kullanım Sayısı', default=0)
    per_customer_limit = models.IntegerField('Müşteri Başı Limit', default=1,
                                            help_text='Bir müşteri kaç kez kullanabilir')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_featured = models.BooleanField('Öne Çıkan mı?', default=False)
    
    # Görsel
    image = models.ImageField('Kampanya Görseli', upload_to='campaigns/', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Tur Kampanyası'
        verbose_name_plural = 'Tur Kampanyaları'
        ordering = ['-is_featured', '-start_date']
    
    def __str__(self):
        return f"{self.name} ({self.get_campaign_type_display()})"
    
    def is_valid(self):
        """Kampanya geçerli mi kontrol et"""
        from django.utils import timezone
        today = timezone.now().date()
        
        if not self.is_active:
            return False
        
        if today < self.start_date or today > self.end_date:
            return False
        
        if self.usage_limit and self.usage_count >= self.usage_limit:
            return False
        
        return True
    
    def calculate_discount(self, amount, customer=None):
        """İndirim hesapla"""
        if not self.is_valid():
            return Decimal('0')
        
        discount = Decimal('0')
        
        if self.campaign_type == 'discount_percentage' and self.discount_percentage:
            discount = amount * (self.discount_percentage / Decimal('100'))
            if self.max_discount_amount:
                discount = min(discount, self.max_discount_amount)
        
        elif self.campaign_type == 'discount_fixed' and self.discount_amount:
            discount = self.discount_amount
        
        elif self.campaign_type == 'early_booking' and self.discount_percentage:
            # Erken rezervasyon kontrolü (rezervasyon tarihinden tur tarihine kadar gün sayısı)
            discount = amount * (self.discount_percentage / Decimal('100'))
        
        # Minimum alış tutarı kontrolü
        if self.min_purchase_amount and amount < self.min_purchase_amount:
            return Decimal('0')
        
        return discount


class TourPromoCode(TimeStampedModel, SoftDeleteModel):
    """Promosyon Kodları"""
    code = models.CharField('Promosyon Kodu', max_length=50, unique=True, db_index=True)
    campaign = models.ForeignKey(TourCampaign, on_delete=models.CASCADE, related_name='promo_codes',
                                verbose_name='Kampanya')
    description = models.TextField('Açıklama', blank=True)
    
    # Kullanım Limitleri
    usage_limit = models.IntegerField('Kullanım Limiti', null=True, blank=True)
    usage_count = models.IntegerField('Kullanım Sayısı', default=0)
    per_customer_limit = models.IntegerField('Müşteri Başı Limit', default=1)
    
    # Tarih Aralığı
    start_date = models.DateField('Başlangıç Tarihi')
    end_date = models.DateField('Bitiş Tarihi')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Promosyon Kodu'
        verbose_name_plural = 'Promosyon Kodları'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.code} ({self.campaign.name})"
    
    def is_valid(self, customer=None):
        """Promosyon kodu geçerli mi kontrol et"""
        from django.utils import timezone
        today = timezone.now().date()
        
        if not self.is_active:
            return False, 'Promosyon kodu aktif değil'
        
        if today < self.start_date or today > self.end_date:
            return False, 'Promosyon kodu geçerli tarih aralığında değil'
        
        if self.usage_limit and self.usage_count >= self.usage_limit:
            return False, 'Promosyon kodu kullanım limitine ulaştı'
        
        if customer:
            # Müşteri başı limit kontrolü
            # TourReservation zaten aynı modülde, doğrudan kullanabiliriz
            customer_usage = TourReservation.objects.filter(
                customer=customer,
                promo_code=self.code,
                status__in=['confirmed', 'completed']
            ).count()
            
            if customer_usage >= self.per_customer_limit:
                return False, 'Bu promosyon kodunu daha fazla kullanamazsınız'
        
        return True, 'Geçerli'


class TourReview(TimeStampedModel):
    """Tur Değerlendirmeleri ve Yorumlar"""
    tour = models.ForeignKey(Tour, on_delete=models.CASCADE, related_name='reviews', verbose_name='Tur')
    reservation = models.ForeignKey(TourReservation, on_delete=models.SET_NULL, null=True, blank=True, related_name='review', verbose_name='Rezervasyon')
    
    customer_name = models.CharField('Müşteri Adı', max_length=100)
    rating = models.IntegerField('Puan', validators=[MinValueValidator(1), MaxValueValidator(5)])
    comment = models.TextField('Yorum', blank=True)
    is_approved = models.BooleanField('Onaylandı mı?', default=False)
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Tur Değerlendirmesi'
        verbose_name_plural = 'Tur Değerlendirmeleri'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.tour.name} - {self.customer_name} ({self.rating}/5)"
