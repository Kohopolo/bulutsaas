"""
Otel Yönetimi Modelleri
Çoklu otel desteği ile otel, oda ve fiyatlama yönetimi
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from decimal import Decimal
from typing import Optional, Dict, List
from datetime import date
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== OTEL AYARLARI (DİNAMİK) ====================

class HotelRegion(TimeStampedModel, SoftDeleteModel):
    """
    Otel Bölgeleri (Dinamik)
    Örnek: Ege, Akdeniz, Marmara, Karadeniz, İç Anadolu, Doğu Anadolu, Güneydoğu Anadolu
    """
    name = models.CharField('Bölge Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Otel Bölgesi'
        verbose_name_plural = 'Otel Bölgeleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name


class HotelCity(TimeStampedModel, SoftDeleteModel):
    """
    Otel Şehirleri (Dinamik)
    Örnek: İstanbul, Ankara, İzmir, Antalya, Bodrum, vb.
    """
    name = models.CharField('Şehir Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    region = models.ForeignKey(
        HotelRegion,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='cities',
        verbose_name='Bölge'
    )
    description = models.TextField('Açıklama', blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Otel Şehri'
        verbose_name_plural = 'Otel Şehirleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name


class HotelType(TimeStampedModel, SoftDeleteModel):
    """
    Otel Türleri (Dinamik)
    Örnek: Otel, Pansiyon, Butik Otel, Resort, Villa, Apart, vb.
    """
    name = models.CharField('Otel Türü', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, blank=True, help_text='Font Awesome class veya emoji')
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Otel Türü'
        verbose_name_plural = 'Otel Türleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name


class RoomType(TimeStampedModel, SoftDeleteModel):
    """
    Oda Tipleri (Dinamik) - Otel Bazlı
    Örnek: Tek Kişilik, Çift Kişilik, Süit, Aile Odası, Deluxe, vb.
    Her otel kendi oda tiplerini tanımlayabilir.
    """
    hotel = models.ForeignKey(
        'Hotel',
        on_delete=models.CASCADE,
        related_name='room_types',
        verbose_name='Otel'
    )
    name = models.CharField('Oda Tipi', max_length=100)
    code = models.SlugField('Kod', max_length=50)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Oda Tipi'
        verbose_name_plural = 'Oda Tipleri'
        ordering = ['hotel', 'sort_order', 'name']
        unique_together = ('hotel', 'code')
        indexes = [
            models.Index(fields=['hotel', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.hotel.name} - {self.name}"


class BoardType(TimeStampedModel, SoftDeleteModel):
    """
    Pansiyon Tipleri (Dinamik) - Otel Bazlı
    Örnek: Sadece Oda, Kahvaltı Dahil, Yarım Pansiyon, Tam Pansiyon, Ultra Her Şey Dahil, vb.
    Her otel kendi pansiyon tiplerini tanımlayabilir.
    """
    hotel = models.ForeignKey(
        'Hotel',
        on_delete=models.CASCADE,
        related_name='board_types',
        verbose_name='Otel'
    )
    name = models.CharField('Pansiyon Tipi', max_length=100)
    code = models.SlugField('Kod', max_length=50)
    description = models.TextField('Açıklama', blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Pansiyon Tipi'
        verbose_name_plural = 'Pansiyon Tipleri'
        ordering = ['hotel', 'sort_order', 'name']
        unique_together = ('hotel', 'code')
        indexes = [
            models.Index(fields=['hotel', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.hotel.name} - {self.name}"


class BedType(TimeStampedModel, SoftDeleteModel):
    """
    Yatak Tipleri (Dinamik) - Otel Bazlı
    Örnek: Tek Kişilik Yatak, Çift Kişilik Yatak, İki Tek Kişilik Yatak, Yatak Sofası, vb.
    Her otel kendi yatak tiplerini tanımlayabilir.
    """
    hotel = models.ForeignKey(
        'Hotel',
        on_delete=models.CASCADE,
        related_name='bed_types',
        verbose_name='Otel'
    )
    name = models.CharField('Yatak Tipi', max_length=100)
    code = models.SlugField('Kod', max_length=50)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Yatak Tipi'
        verbose_name_plural = 'Yatak Tipleri'
        ordering = ['hotel', 'sort_order', 'name']
        unique_together = ('hotel', 'code')
        indexes = [
            models.Index(fields=['hotel', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.hotel.name} - {self.name}"


class RoomFeature(TimeStampedModel, SoftDeleteModel):
    """
    Oda Özellikleri (Dinamik) - Otel Bazlı
    Örnek: Balkon, Deniz Manzarası, Klima, Minibar, Kasa, WiFi, TV, vb.
    Her otel kendi oda özelliklerini tanımlayabilir.
    """
    hotel = models.ForeignKey(
        'Hotel',
        on_delete=models.CASCADE,
        related_name='room_features',
        verbose_name='Otel'
    )
    name = models.CharField('Özellik Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Oda Özelliği'
        verbose_name_plural = 'Oda Özellikleri'
        ordering = ['hotel', 'sort_order', 'name']
        unique_together = ('hotel', 'code')
        indexes = [
            models.Index(fields=['hotel', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.hotel.name} - {self.name}"


class HotelFeature(TimeStampedModel, SoftDeleteModel):
    """
    Otel Özellikleri (Dinamik)
    Örnek: Havuz, Spa, Fitness, Restoran, Bar, Otopark, WiFi, Asansör, vb.
    """
    name = models.CharField('Özellik Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, blank=True)
    category = models.CharField('Kategori', max_length=50, blank=True, help_text='Örn: Genel, Eğlence, Spor, vb.')
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Otel Özelliği'
        verbose_name_plural = 'Otel Özellikleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name


# ==================== OTEL MODELİ ====================

class Hotel(TimeStampedModel, SoftDeleteModel):
    """
    Otel Modeli
    Her tenant paket limitine göre birden fazla otel ekleyebilir
    """
    # Temel Bilgiler
    name = models.CharField('Otel Adı', max_length=200)
    code = models.SlugField('Otel Kodu', max_length=50, db_index=True)
    description = models.TextField('Açıklama', blank=True)
    
    # Kategoriler
    region = models.ForeignKey(
        HotelRegion,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='hotels',
        verbose_name='Bölge'
    )
    city = models.ForeignKey(
        HotelCity,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='hotels',
        verbose_name='Şehir'
    )
    hotel_type = models.ForeignKey(
        HotelType,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='hotels',
        verbose_name='Otel Türü'
    )
    star_rating = models.IntegerField(
        'Yıldız',
        choices=[(i, f'{i} Yıldız') for i in range(1, 6)],
        null=True,
        blank=True,
        validators=[MinValueValidator(1), MaxValueValidator(5)]
    )
    
    # İletişim Bilgileri
    email = models.EmailField('E-posta', blank=True)
    phone = models.CharField('Telefon', max_length=20, blank=True)
    whatsapp = models.CharField('WhatsApp', max_length=20, blank=True)
    telegram = models.CharField('Telegram', max_length=50, blank=True)
    website = models.URLField('Web Sitesi', blank=True)
    
    # Adres Bilgileri
    address = models.TextField('Adres')
    district = models.CharField('İlçe', max_length=100, blank=True)
    postal_code = models.CharField('Posta Kodu', max_length=10, blank=True)
    country = models.CharField('Ülke', max_length=100, default='Türkiye')
    
    # Konum Bilgileri
    latitude = models.DecimalField('Enlem', max_digits=9, decimal_places=6, null=True, blank=True)
    longitude = models.DecimalField('Boylam', max_digits=9, decimal_places=6, null=True, blank=True)
    
    # Otel Özellikleri
    total_rooms = models.IntegerField('Toplam Oda Sayısı', default=0, validators=[MinValueValidator(0)])
    total_beds = models.IntegerField('Toplam Yatak Sayısı', default=0, validators=[MinValueValidator(0)])
    check_in_time = models.TimeField('Check-in Saati', default='14:00')
    check_out_time = models.TimeField('Check-out Saati', default='12:00')
    
    # Görsel İçerik
    main_image = models.ImageField('Ana Sayfa Resmi', upload_to='hotels/main/', blank=True, null=True)
    logo = models.ImageField('Logo', upload_to='hotels/logo/', blank=True, null=True)
    favicon = models.ImageField('Favicon', upload_to='hotels/favicon/', blank=True, null=True)
    
    # Detay Bilgileri
    detail_description = models.TextField('Detay Açıklama', blank=True, help_text='Otel hakkında detaylı bilgi')
    policies = models.TextField('Otel Politikaları', blank=True, help_text='İptal, check-in/out, vb. politikalar')
    concept_description = models.TextField('Konsept Açıklama', blank=True, help_text='Otel konsepti ve özellikleri')
    
    # Video
    youtube_video_url = models.URLField('YouTube Video Linki', blank=True)
    video_file = models.FileField('Video Dosyası', upload_to='hotels/videos/', blank=True, null=True)
    
    # Sosyal Medya
    social_media = models.JSONField('Sosyal Medya Hesapları', default=dict, blank=True,
                                   help_text='{"facebook": "url", "instagram": "url", "twitter": "url"}')
    
    # Özellikler (ManyToMany)
    hotel_features = models.ManyToManyField(
        HotelFeature,
        blank=True,
        related_name='hotels',
        verbose_name='Otel Özellikleri'
    )
    services = models.JSONField('Hizmetler', default=list, blank=True,
                               help_text='Otel hizmetleri listesi')
    amenities = models.JSONField('Olanaklar', default=list, blank=True,
                                help_text='Otel olanakları listesi')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan Otel mi?', default=False,
                                    help_text='İlk otel varsayılan olarak işaretlenir')
    is_featured = models.BooleanField('Öne Çıkan mı?', default=False)
    is_homepage_visible = models.BooleanField('Anasayfada Görünür mü?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar (JSON)
    settings = models.JSONField('Otel Ayarları', default=dict, blank=True,
                               help_text='Otel özel ayarları (dil, para birimi, vb.)')
    
    class Meta:
        verbose_name = 'Otel'
        verbose_name_plural = 'Oteller'
        ordering = ['sort_order', 'name']
        indexes = [
            models.Index(fields=['code', 'is_active']),
            models.Index(fields=['city', 'is_active']),
            models.Index(fields=['region', 'is_active']),
            models.Index(fields=['is_default', 'is_active']),
        ]
    
    def __str__(self):
        return self.name
    
    def save(self, *args, **kwargs):
        # İlk otel varsayılan olarak işaretlenir
        if not Hotel.objects.filter(is_default=True).exists():
            self.is_default = True
        super().save(*args, **kwargs)
    
    def get_image_gallery(self):
        """Otel resim galerisini getir"""
        return self.images.filter(is_active=True).order_by('sort_order')
    
    def get_total_room_count(self):
        """Toplam oda sayısını getir"""
        return self.rooms.filter(is_active=True).count()


class HotelImage(TimeStampedModel, SoftDeleteModel):
    """
    Otel Resim Galerisi
    """
    hotel = models.ForeignKey(
        Hotel,
        on_delete=models.CASCADE,
        related_name='images',
        verbose_name='Otel'
    )
    image = models.ImageField('Resim', upload_to='hotels/gallery/')
    title = models.CharField('Başlık', max_length=200, blank=True)
    description = models.TextField('Açıklama', blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Otel Resmi'
        verbose_name_plural = 'Otel Resimleri'
        ordering = ['sort_order', '-created_at']
    
    def __str__(self):
        return f"{self.hotel.name} - {self.title or 'Resim'}"


# ==================== EKSTRA HİZMETLER ====================

class ExtraServicePriceType(models.TextChoices):
    """Ekstra Hizmet Fiyatlandırma Tipleri"""
    PER_PERSON = 'per_person', 'Kişi Başı'
    FIXED = 'fixed', 'Tek Seferlik / Sabit'
    PER_NIGHT = 'per_night', 'Gece Başı'
    PER_ROOM = 'per_room', 'Oda Başı'


class HotelExtraService(TimeStampedModel, SoftDeleteModel):
    """
    Otel Ekstra Hizmetleri
    Örnek: Havaalanı Transfer, Spa, Oda Servisi, vb.
    """
    hotel = models.ForeignKey(
        Hotel,
        on_delete=models.CASCADE,
        related_name='extra_services',
        verbose_name='Otel'
    )
    
    # Temel Bilgiler
    name = models.CharField('Hizmet Adı', max_length=200)
    code = models.SlugField('Kod', max_length=50, db_index=True, blank=True)
    description = models.TextField('Açıklama', blank=True, help_text='Hizmet hakkında detaylı bilgi')
    
    # Fiyatlandırma
    price = models.DecimalField(
        'Fiyat',
        max_digits=10,
        decimal_places=2,
        validators=[MinValueValidator(Decimal('0.01'))],
        help_text='Hizmet fiyatı'
    )
    price_type = models.CharField(
        'Fiyatlandırma Tipi',
        max_length=20,
        choices=ExtraServicePriceType.choices,
        default=ExtraServicePriceType.FIXED,
        help_text='Fiyatlandırma şekli'
    )
    currency = models.CharField('Para Birimi', max_length=3, default='TRY', help_text='TRY, USD, EUR, vb.')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Ekstra Hizmet'
        verbose_name_plural = 'Ekstra Hizmetler'
        ordering = ['sort_order', 'name']
        indexes = [
            models.Index(fields=['hotel', 'is_active']),
            models.Index(fields=['code', 'is_active']),
        ]
        unique_together = [['hotel', 'code']]
    
    def __str__(self):
        price_display = f"{self.price} {self.currency}"
        price_type_display = self.get_price_type_display()
        return f"{self.hotel.name} - {self.name} ({price_display} - {price_type_display})"
    
    def get_price_display(self):
        """Fiyat gösterimi"""
        return f"{self.price} {self.currency}"
    
    def get_full_price_display(self):
        """Tam fiyat gösterimi (tip ile birlikte)"""
        price_type_display = self.get_price_type_display()
        return f"{self.price} {self.currency} ({price_type_display})"


# ==================== ODA MODELİ ====================

class Room(TimeStampedModel, SoftDeleteModel):
    """
    Oda Modeli
    Her oda bir otel'e bağlıdır
    """
    hotel = models.ForeignKey(
        Hotel,
        on_delete=models.CASCADE,
        related_name='rooms',
        verbose_name='Otel'
    )
    
    # Temel Bilgiler
    name = models.CharField('Oda Adı', max_length=200)
    code = models.SlugField('Oda Kodu', max_length=50, db_index=True)
    room_type = models.ForeignKey(
        RoomType,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='rooms',
        verbose_name='Oda Tipi'
    )
    board_type = models.ForeignKey(
        BoardType,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='rooms',
        verbose_name='Pansiyon Tipi'
    )
    
    # Kapasite
    max_adults = models.IntegerField('Maksimum Yetişkin', default=2, validators=[MinValueValidator(1)])
    max_children = models.IntegerField('Maksimum Çocuk', default=2, validators=[MinValueValidator(0)])
    max_total_capacity = models.IntegerField('Maksimum Toplam Kapasite', default=4, validators=[MinValueValidator(1)])
    
    # Oda Özellikleri
    room_features = models.ManyToManyField(
        RoomFeature,
        blank=True,
        related_name='rooms',
        verbose_name='Oda Özellikleri'
    )
    area_sqm = models.DecimalField('Metrekare', max_digits=8, decimal_places=2, null=True, blank=True,
                                  validators=[MinValueValidator(Decimal('0.01'))])
    
    # Yatak Bilgileri
    bed_type = models.ForeignKey(
        BedType,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='rooms',
        verbose_name='Yatak Tipi'
    )
    bed_count = models.IntegerField('Yatak Sayısı', default=1, validators=[MinValueValidator(1)])
    
    # Görsel İçerik
    main_image = models.ImageField('Ana Sayfa Resmi', upload_to='rooms/main/', blank=True, null=True)
    video_url = models.URLField('Video URL (YouTube)', blank=True)
    video_file = models.FileField('Video Dosyası', upload_to='rooms/videos/', blank=True, null=True)
    virtual_3d_url = models.URLField('3D Virtual Tour URL', blank=True)
    
    # Detay
    description = models.TextField('Detay Açıklama', blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_featured = models.BooleanField('Öne Çıkan mı?', default=False)
    is_homepage_visible = models.BooleanField('Anasayfada Görünür mü?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Oda Sayısı (Bu tip odadan kaç tane var)
    room_count = models.IntegerField('Oda Sayısı', default=1, validators=[MinValueValidator(1)])
    
    class Meta:
        verbose_name = 'Oda'
        verbose_name_plural = 'Odalar'
        ordering = ['sort_order', 'name']
        indexes = [
            models.Index(fields=['hotel', 'is_active']),
            models.Index(fields=['room_type', 'is_active']),
            models.Index(fields=['code']),
        ]
    
    def __str__(self):
        return f"{self.hotel.name} - {self.name}"
    
    def get_image_gallery(self):
        """Oda resim galerisini getir"""
        return self.images.filter(is_active=True).order_by('sort_order')
    
    def get_current_price(self, date=None):
        """Belirli bir tarih için güncel fiyatı getir"""
        from django.utils import timezone
        if not date:
            date = timezone.now().date()
        
        # Önce kampanya fiyatlarını kontrol et
        # Sonra sezonluk fiyatları
        # Sonra özel fiyatları
        # En son basic fiyatı
        # Bu mantık RoomPrice modelinde olacak
        pass


class RoomImage(TimeStampedModel, SoftDeleteModel):
    """
    Oda Resim Galerisi
    """
    room = models.ForeignKey(
        Room,
        on_delete=models.CASCADE,
        related_name='images',
        verbose_name='Oda'
    )
    image = models.ImageField('Resim', upload_to='rooms/gallery/')
    title = models.CharField('Başlık', max_length=200, blank=True)
    description = models.TextField('Açıklama', blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Oda Resmi'
        verbose_name_plural = 'Oda Resimleri'
        ordering = ['sort_order', '-created_at']
    
    def __str__(self):
        return f"{self.room.name} - {self.title or 'Resim'}"


# ==================== ODA FİYATLAMA SİSTEMİ ====================

class RoomPricingType(models.TextChoices):
    """
    Oda Fiyatlama Tipleri
    """
    FIXED_ROOM = 'fixed_room', 'Sabit Oda Fiyatı'
    PER_PERSON = 'per_person', 'Kişi Çarpanı'


class RoomPrice(TimeStampedModel, SoftDeleteModel):
    """
    Oda Fiyatlandırma Modeli
    """
    room = models.ForeignKey(
        Room,
        on_delete=models.CASCADE,
        related_name='prices',
        verbose_name='Oda'
    )
    
    # Fiyatlama Tipi
    pricing_type = models.CharField(
        'Fiyatlama Tipi',
        max_length=20,
        choices=RoomPricingType.choices,
        default=RoomPricingType.FIXED_ROOM
    )
    
    # Basic Gecelik Fiyat
    basic_nightly_price = models.DecimalField(
        'Basic Gecelik Fiyat',
        max_digits=10,
        decimal_places=2,
        default=0,
        validators=[MinValueValidator(Decimal('0'))]
    )
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Kişi Çarpanı (Eğer pricing_type = PER_PERSON ise)
    adult_multipliers = models.JSONField(
        'Yetişkin Çarpanları',
        default=dict,
        blank=True,
        help_text='{"1": 1.0, "2": 1.8, "3": 2.5} - Kişi sayısına göre çarpan'
    )
    child_fixed_multiplier = models.DecimalField(
        'Çocuk Sabit Çarpan',
        max_digits=5,
        decimal_places=2,
        default=Decimal('0.5'),
        validators=[MinValueValidator(Decimal('0'))],
        help_text='Çocuk için sabit çarpan (örn: 0.5 = yarı fiyat)'
    )
    child_age_range = models.CharField(
        'Çocuk Yaş Aralığı',
        max_length=20,
        default='0-12',
        help_text='Örn: 0-12'
    )
    
    # Ücretsiz Çocuk
    free_children_count = models.IntegerField(
        'Ücretsiz Çocuk Sayısı',
        default=0,
        validators=[MinValueValidator(0)]
    )
    free_children_rules = models.JSONField(
        'Ücretsiz Çocuk Kuralları',
        default=list,
        blank=True,
        help_text='[{"age_range": "0-6", "count": 2, "adult_required": 2}]'
    )
    
    # Toplam İndirim Oranı
    total_discount_rate = models.DecimalField(
        'Toplam İndirim Oranı (%)',
        max_digits=5,
        decimal_places=2,
        default=0,
        validators=[MinValueValidator(Decimal('0')), MaxValueValidator(Decimal('100'))]
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Oda Fiyatı'
        verbose_name_plural = 'Oda Fiyatları'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.room.name} - {self.get_pricing_type_display()}"
    
    def calculate_price(
        self,
        check_date: Optional[date] = None,
        adults: int = 1,
        children: int = 0,
        child_ages: Optional[List[int]] = None,
        agency_id: Optional[int] = None,
        channel_name: Optional[str] = None,
        nights: int = 1,
    ) -> Dict:
        """
        Oda fiyatını hesapla (Global utility fonksiyonunu kullanır)
        
        Args:
            check_date: Kontrol edilecek tarih (None ise bugün)
            adults: Yetişkin sayısı
            children: Çocuk sayısı
            child_ages: Çocuk yaşları listesi
            agency_id: Acente ID (varsa)
            channel_name: Kanal adı (varsa)
            nights: Gece sayısı
        
        Returns:
            Dict: calculate_dynamic_price fonksiyonunun döndürdüğü dict
        """
        from apps.tenant_apps.core.utils import calculate_dynamic_price
        from decimal import Decimal
        
        if check_date is None:
            check_date = timezone.now().date()
        
        # Sezonluk fiyatları hazırla
        seasonal_prices = []
        for sp in self.seasonal_prices.filter(is_active=True):
            if sp.start_date <= check_date <= sp.end_date:
                price = sp.price_per_person if self.pricing_type == RoomPricingType.PER_PERSON else sp.fixed_room_price
                if price:
                    seasonal_prices.append({
                        'start_date': sp.start_date,
                        'end_date': sp.end_date,
                        'price': price,
                    })
        
        # Özel fiyatları hazırla
        special_prices = []
        for sp in self.special_prices.filter(is_active=True):
            if sp.start_date <= check_date <= sp.end_date:
                # Gün bazlı kontrol
                weekday_names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
                day_name = weekday_names[check_date.weekday()]
                
                if day_name in sp.weekday_prices:
                    day_price = sp.weekday_prices[day_name]
                    price = day_price.get('per_person' if self.pricing_type == RoomPricingType.PER_PERSON else 'fixed')
                    if price:
                        try:
                            # Eğer price 'per_person' string'i ise atla
                            if isinstance(price, str) and price.lower().strip() == 'per_person':
                                continue
                            special_prices.append({
                                'date': check_date,
                                'day_of_week': check_date.weekday(),
                                'price': Decimal(str(price)),
                            })
                        except (ValueError, TypeError):
                            continue
                else:
                    # Hafta içi/hafta sonu kontrolü
                    is_weekend = check_date.weekday() >= 5
                    if is_weekend and sp.weekend_price_per_person:
                        price = sp.weekend_price_per_person if self.pricing_type == RoomPricingType.PER_PERSON else sp.weekend_fixed_price
                    elif not is_weekend and sp.weekday_price_per_person:
                        price = sp.weekday_price_per_person if self.pricing_type == RoomPricingType.PER_PERSON else sp.weekday_fixed_price
                    else:
                        price = None
                    
                    if price:
                        special_prices.append({
                            'date': check_date,
                            'day_of_week': check_date.weekday(),
                            'price': price,
                        })
        
        # Kampanya fiyatları hazırla
        campaign_prices = []
        for cp in self.campaign_prices.filter(is_active=True):
            if cp.start_date <= check_date <= cp.end_date:
                price = cp.price_per_person if self.pricing_type == RoomPricingType.PER_PERSON else cp.fixed_room_price
                if price:
                    campaign_prices.append({
                        'start_date': cp.start_date,
                        'end_date': cp.end_date,
                        'price': price,
                    })
        
        # Acente fiyatları hazırla
        agency_prices = {}
        if agency_id:
            agency_price = self.agency_prices.filter(agency_id=agency_id, is_active=True).first()
            if agency_price:
                price = agency_price.price_per_person if self.pricing_type == RoomPricingType.PER_PERSON else agency_price.fixed_room_price
                if price:
                    agency_prices[agency_id] = price
        
        # Kanal fiyatları hazırla
        channel_prices = {}
        if channel_name:
            channel_price = self.channel_prices.filter(channel_name=channel_name, is_active=True).first()
            if channel_price:
                price = channel_price.price_per_person if self.pricing_type == RoomPricingType.PER_PERSON else channel_price.fixed_room_price
                if price:
                    channel_prices[channel_name] = price
        
        # Ücretsiz çocuk kurallarını hazırla
        free_children_rules = []
        if self.free_children_rules:
            for rule in self.free_children_rules:
                age_range_str = rule.get('age_range', '0-12')
                age_start, age_end = map(int, age_range_str.split('-'))
                free_children_rules.append({
                    'age_range': (age_start, age_end),
                    'count': rule.get('count', 0),
                    'with_adults': rule.get('adult_required', 1),
                })
        
        # Yetişkin çarpanlarını hazırla
        multipliers = {}
        if self.adult_multipliers:
            multipliers = {int(k): Decimal(str(v)) for k, v in self.adult_multipliers.items()}
        
        # check_out_date hesapla (check_date + nights)
        from datetime import timedelta
        check_out_date = check_date + timedelta(days=nights) if check_date else None
        
        # Global utility fonksiyonunu çağır
        # basic_nightly_price kontrolü
        if not self.basic_nightly_price or self.basic_nightly_price == 0:
            return {
                'total_price': Decimal('0'),
                'adult_price': Decimal('0'),
                'child_price': Decimal('0'),
                'breakdown': {
                    'base_price': Decimal('0'),
                    'pricing_type': 'fixed' if self.pricing_type == RoomPricingType.FIXED_ROOM else 'per_person',
                    'adults': adults,
                    'children': children,
                }
            }
        
        result = calculate_dynamic_price(
            base_price=self.basic_nightly_price,
            check_in_date=check_date,
            check_out_date=check_out_date,
            pricing_type='fixed' if self.pricing_type == RoomPricingType.FIXED_ROOM else 'per_person',
            adults=adults,
            children=children,
            child_ages=child_ages or [],
            multipliers=multipliers,
            child_multiplier=self.child_fixed_multiplier,
            free_children_rules=free_children_rules,
            seasonal_prices=seasonal_prices,
            special_prices=special_prices,
            campaign_prices=campaign_prices,
            agency_prices=agency_prices,
            channel_prices=channel_prices,
            agency_id=agency_id,
            channel_name=channel_name,
            discount_rate=self.total_discount_rate / Decimal('100') if self.total_discount_rate else None,
        )
        
        # Gece sayısı ile çarp
        if nights > 1:
            result['total_price'] = result['total_price'] * Decimal(str(nights))
            result['adult_price'] = result['adult_price'] * Decimal(str(nights))
            result['child_price'] = result['child_price'] * Decimal(str(nights))
            result['breakdown']['nights'] = nights
        
        return result


class RoomSeasonalPrice(TimeStampedModel, SoftDeleteModel):
    """
    Oda Sezonluk Fiyatları
    Tarih aralığı bazlı fiyatlandırma
    """
    room_price = models.ForeignKey(
        RoomPrice,
        on_delete=models.CASCADE,
        related_name='seasonal_prices',
        verbose_name='Oda Fiyatı'
    )
    
    # Tarih Aralığı
    start_date = models.DateField('Başlangıç Tarihi', db_index=True)
    end_date = models.DateField('Bitiş Tarihi', db_index=True)
    
    # Fiyat (Kişi başı veya sabit oda fiyatı)
    price_per_person = models.DecimalField(
        'Kişi Başı Fiyat',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True,
        validators=[MinValueValidator(Decimal('0'))]
    )
    fixed_room_price = models.DecimalField(
        'Sabit Oda Fiyatı',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True,
        validators=[MinValueValidator(Decimal('0'))]
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Sezonluk Fiyat'
        verbose_name_plural = 'Sezonluk Fiyatlar'
        ordering = ['start_date']
        indexes = [
            models.Index(fields=['start_date', 'end_date', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.room_price.room.name} - {self.start_date} / {self.end_date}"


class RoomSpecialPrice(TimeStampedModel, SoftDeleteModel):
    """
    Oda Özel Fiyatları
    Hafta içi, hafta sonu, gün bazlı fiyatlandırma
    """
    room_price = models.ForeignKey(
        RoomPrice,
        on_delete=models.CASCADE,
        related_name='special_prices',
        verbose_name='Oda Fiyatı'
    )
    
    # Tarih Aralığı
    start_date = models.DateField('Başlangıç Tarihi', db_index=True)
    end_date = models.DateField('Bitiş Tarihi', db_index=True)
    
    # Gün Bazlı Fiyatlar
    WEEKDAY_CHOICES = [
        ('monday', 'Pazartesi'),
        ('tuesday', 'Salı'),
        ('wednesday', 'Çarşamba'),
        ('thursday', 'Perşembe'),
        ('friday', 'Cuma'),
        ('saturday', 'Cumartesi'),
        ('sunday', 'Pazar'),
    ]
    
    weekday_prices = models.JSONField(
        'Gün Bazlı Fiyatlar',
        default=dict,
        blank=True,
        help_text='{"monday": {"per_person": 100, "fixed": 200}, "saturday": {"per_person": 150, "fixed": 300}}'
    )
    
    # Hafta İçi / Hafta Sonu
    weekend_price_per_person = models.DecimalField(
        'Hafta Sonu Kişi Başı Fiyat',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    weekend_fixed_price = models.DecimalField(
        'Hafta Sonu Sabit Fiyat',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    weekday_price_per_person = models.DecimalField(
        'Hafta İçi Kişi Başı Fiyat',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    weekday_fixed_price = models.DecimalField(
        'Hafta İçi Sabit Fiyat',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Özel Fiyat'
        verbose_name_plural = 'Özel Fiyatlar'
        ordering = ['start_date']
        indexes = [
            models.Index(fields=['start_date', 'end_date', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.room_price.room.name} - Özel Fiyat ({self.start_date} / {self.end_date})"


class RoomCampaignPrice(TimeStampedModel, SoftDeleteModel):
    """
    Oda Kampanya Fiyatları
    Farklı senaryolar için kampanya fiyatlandırma
    """
    room_price = models.ForeignKey(
        RoomPrice,
        on_delete=models.CASCADE,
        related_name='campaign_prices',
        verbose_name='Oda Fiyatı'
    )
    
    # Kampanya Bilgileri
    name = models.CharField('Kampanya Adı', max_length=200)
    description = models.TextField('Açıklama', blank=True)
    
    # Tarih Aralığı
    start_date = models.DateField('Başlangıç Tarihi', db_index=True)
    end_date = models.DateField('Bitiş Tarihi', db_index=True)
    
    # Kampanya Tipi
    CAMPAIGN_TYPE_CHOICES = [
        ('stay_nights', 'X Gece Kal'),
        ('early_booking', 'Erken Rezervasyon'),
        ('last_minute', 'Son Dakika'),
        ('group', 'Grup İndirimi'),
        ('custom', 'Özel Kampanya'),
    ]
    campaign_type = models.CharField(
        'Kampanya Tipi',
        max_length=20,
        choices=CAMPAIGN_TYPE_CHOICES,
        default='custom'
    )
    
    # Kampanya Kuralları (JSON)
    campaign_rules = models.JSONField(
        'Kampanya Kuralları',
        default=dict,
        blank=True,
        help_text='{"stay_nights": 7, "discount_percent": 10, "discount_amount": 100}'
    )
    
    # Fiyat
    price_per_person = models.DecimalField(
        'Kişi Başı Fiyat',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    fixed_room_price = models.DecimalField(
        'Sabit Oda Fiyatı',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    discount_percent = models.DecimalField(
        'İndirim Oranı (%)',
        max_digits=5,
        decimal_places=2,
        default=0,
        validators=[MinValueValidator(Decimal('0')), MaxValueValidator(Decimal('100'))]
    )
    discount_amount = models.DecimalField(
        'İndirim Tutarı',
        max_digits=10,
        decimal_places=2,
        default=0,
        validators=[MinValueValidator(Decimal('0'))]
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Kampanya Fiyatı'
        verbose_name_plural = 'Kampanya Fiyatları'
        ordering = ['-start_date']
        indexes = [
            models.Index(fields=['start_date', 'end_date', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.room_price.room.name} - {self.name}"


class RoomAgencyPrice(TimeStampedModel, SoftDeleteModel):
    """
    Oda Acente Fiyatlandırması
    Acente ID için özel fiyatlandırma
    """
    room_price = models.ForeignKey(
        RoomPrice,
        on_delete=models.CASCADE,
        related_name='agency_prices',
        verbose_name='Oda Fiyatı'
    )
    
    # Acente Bilgileri (İleride Agency modülü ile entegre edilebilir)
    agency_id = models.IntegerField('Acente ID', db_index=True)
    agency_name = models.CharField('Acente Adı', max_length=200, blank=True)
    
    # Fiyat
    price_per_person = models.DecimalField(
        'Kişi Başı Fiyat',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    fixed_room_price = models.DecimalField(
        'Sabit Oda Fiyatı',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    commission_rate = models.DecimalField(
        'Komisyon Oranı (%)',
        max_digits=5,
        decimal_places=2,
        default=0
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Acente Fiyatı'
        verbose_name_plural = 'Acente Fiyatları'
        unique_together = ['room_price', 'agency_id']
        indexes = [
            models.Index(fields=['agency_id', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.room_price.room.name} - {self.agency_name or f'Acente {self.agency_id}'}"


class RoomChannelPrice(TimeStampedModel, SoftDeleteModel):
    """
    Oda Kanal Fiyatlandırması
    Booking.com, Expedia vb. kanallar için özel fiyatlandırma
    """
    room_price = models.ForeignKey(
        RoomPrice,
        on_delete=models.CASCADE,
        related_name='channel_prices',
        verbose_name='Oda Fiyatı'
    )
    
    # Kanal Bilgileri (İleride Channel modülü ile entegre edilebilir)
    channel_code = models.CharField('Kanal Kodu', max_length=50, db_index=True)
    channel_name = models.CharField('Kanal Adı', max_length=200)
    
    # Fiyat
    price_per_person = models.DecimalField(
        'Kişi Başı Fiyat',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    fixed_room_price = models.DecimalField(
        'Sabit Oda Fiyatı',
        max_digits=10,
        decimal_places=2,
        null=True,
        blank=True
    )
    markup_rate = models.DecimalField(
        'Markup Oranı (%)',
        max_digits=5,
        decimal_places=2,
        default=0,
        help_text='Kanal için ek fiyat artışı'
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Kanal Fiyatı'
        verbose_name_plural = 'Kanal Fiyatları'
        unique_together = ['room_price', 'channel_code']
        indexes = [
            models.Index(fields=['channel_code', 'is_active']),
        ]
    
    def __str__(self):
        return f"{self.room_price.room.name} - {self.channel_name}"


# ==================== ODA NUMARALARI ====================

class RoomNumberStatus(models.TextChoices):
    """
    Oda Numarası Durumları
    """
    AVAILABLE = 'available', 'Boş'
    OCCUPIED = 'occupied', 'Dolu'
    CLEAN = 'clean', 'Temiz'
    DIRTY = 'dirty', 'Kirli'
    CLEANING_PENDING = 'cleaning_pending', 'Temizlik Bekliyor'
    MAINTENANCE = 'maintenance', 'Bakımda'
    OUT_OF_ORDER = 'out_of_order', 'Hizmet Dışı'


class Floor(TimeStampedModel, SoftDeleteModel):
    """
    Kat Modeli
    """
    hotel = models.ForeignKey(
        Hotel,
        on_delete=models.CASCADE,
        related_name='floors',
        verbose_name='Otel'
    )
    floor_number = models.IntegerField('Kat Numarası', validators=[MinValueValidator(0)])
    name = models.CharField('Kat Adı', max_length=100, blank=True, help_text='Örn: Zemin Kat, 1. Kat, vb.')
    description = models.TextField('Açıklama', blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Kat'
        verbose_name_plural = 'Katlar'
        unique_together = ['hotel', 'floor_number']
        ordering = ['floor_number']
    
    def __str__(self):
        return f"{self.hotel.name} - {self.name or f'Kat {self.floor_number}'}"


class Block(TimeStampedModel, SoftDeleteModel):
    """
    Blok Modeli (Opsiyonel)
    """
    hotel = models.ForeignKey(
        Hotel,
        on_delete=models.CASCADE,
        related_name='blocks',
        verbose_name='Otel'
    )
    name = models.CharField('Blok Adı', max_length=100)
    code = models.SlugField('Blok Kodu', max_length=50)
    description = models.TextField('Açıklama', blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Blok'
        verbose_name_plural = 'Bloklar'
        unique_together = ['hotel', 'code']
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return f"{self.hotel.name} - {self.name}"


class RoomNumber(TimeStampedModel, SoftDeleteModel):
    """
    Oda Numarası Modeli
    """
    hotel = models.ForeignKey(
        Hotel,
        on_delete=models.CASCADE,
        related_name='room_numbers',
        verbose_name='Otel'
    )
    room = models.ForeignKey(
        Room,
        on_delete=models.CASCADE,
        related_name='room_numbers',
        verbose_name='Oda Tipi',
        help_text='Bu numara hangi oda tipine ait'
    )
    floor = models.ForeignKey(
        Floor,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='room_numbers',
        verbose_name='Kat'
    )
    block = models.ForeignKey(
        Block,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='room_numbers',
        verbose_name='Blok'
    )
    
    # Oda Numarası
    number = models.CharField('Oda Numarası', max_length=50, db_index=True)
    
    # Durum
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=RoomNumberStatus.choices,
        default=RoomNumberStatus.AVAILABLE,
        db_index=True
    )
    
    # Notlar
    notes = models.TextField('Notlar', blank=True, help_text='Oda hakkında özel notlar')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Oda Numarası'
        verbose_name_plural = 'Oda Numaraları'
        unique_together = ['hotel', 'number']
        ordering = ['floor__floor_number', 'number']
        indexes = [
            models.Index(fields=['hotel', 'status', 'is_active']),
            models.Index(fields=['room', 'status']),
            models.Index(fields=['number']),
        ]
    
    def __str__(self):
        block_str = f"{self.block.code}-" if self.block else ""
        floor_str = f"{self.floor.floor_number}." if self.floor else ""
        return f"{self.hotel.name} - {block_str}{floor_str}{self.number}"


# ==================== KULLANICI-OTEL YETKİ İLİŞKİSİ ====================

class HotelUserPermission(TimeStampedModel):
    """
    Kullanıcı-Otel Yetki İlişkisi
    Hangi kullanıcı hangi otellere erişebilir ve hangi yetkilere sahip
    """
    tenant_user = models.ForeignKey(
        'tenant_core.TenantUser',
        on_delete=models.CASCADE,
        related_name='hotel_permissions',
        verbose_name='Kullanıcı'
    )
    hotel = models.ForeignKey(
        Hotel,
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
        'auth.User',
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
