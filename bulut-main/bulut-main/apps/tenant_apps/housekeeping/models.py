"""
Kat Hizmetleri (Housekeeping) Modelleri
Profesyonel otel kat hizmetleri yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from decimal import Decimal
from datetime import date, datetime, timedelta
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== TEMİZLİK DURUMU ====================

class CleaningStatus(models.TextChoices):
    """Temizlik Durumları"""
    CLEAN = 'clean', 'Temiz'
    DIRTY = 'dirty', 'Kirli'
    CLEANING = 'cleaning', 'Temizleniyor'
    INSPECTED = 'inspected', 'Kontrol Edildi'
    NEEDS_ATTENTION = 'needs_attention', 'Dikkat Gerekiyor'
    OUT_OF_ORDER = 'out_of_order', 'Hizmet Dışı'


class CleaningPriority(models.TextChoices):
    """Temizlik Önceliği"""
    LOW = 'low', 'Düşük'
    NORMAL = 'normal', 'Normal'
    HIGH = 'high', 'Yüksek'
    URGENT = 'urgent', 'Acil'


# ==================== TEMİZLİK GÖREVİ ====================

class CleaningTask(TimeStampedModel, SoftDeleteModel):
    """
    Temizlik Görevi Modeli
    Her oda için temizlik görevlerini yönetir
    """
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='cleaning_tasks',
        verbose_name='Otel'
    )
    room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.CASCADE,
        related_name='cleaning_tasks',
        verbose_name='Oda Numarası'
    )
    # reservation = models.ForeignKey(
    #     'reception.Reservation',
    #     on_delete=models.SET_NULL,
    #     null=True,
    #     blank=True,
    #     related_name='cleaning_tasks',
    #     verbose_name='Rezervasyon',  # KALDIRILDI - Reception modülü yeniden inşa edilecek
    #     help_text='İlgili rezervasyon (varsa)'
    # )
    
    # Görev Bilgileri
    task_type = models.CharField(
        'Görev Tipi',
        max_length=50,
        choices=[
            ('checkout', 'Check-Out Temizliği'),
            ('stayover', 'Günlük Temizlik'),
            ('deep', 'Derinlemesine Temizlik'),
            ('inspection', 'Kontrol'),
            ('maintenance', 'Bakım Sonrası Temizlik'),
            ('vip', 'VIP Hazırlık'),
        ],
        default='checkout',
        db_index=True
    )
    
    priority = models.CharField(
        'Öncelik',
        max_length=20,
        choices=CleaningPriority.choices,
        default=CleaningPriority.NORMAL,
        db_index=True
    )
    
    # Atama
    assigned_to = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='assigned_cleaning_tasks',
        verbose_name='Atanan Personel'
    )
    assigned_at = models.DateTimeField('Atanma Tarihi', null=True, blank=True)
    assigned_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='assigned_cleaning_tasks_by',
        verbose_name='Atayan'
    )
    
    # Durum
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=[
            ('pending', 'Bekliyor'),
            ('in_progress', 'Devam Ediyor'),
            ('completed', 'Tamamlandı'),
            ('cancelled', 'İptal Edildi'),
            ('on_hold', 'Beklemede'),
        ],
        default='pending',
        db_index=True
    )
    
    # Tarih/Saat Bilgileri
    scheduled_time = models.DateTimeField('Planlanan Zaman', null=True, blank=True)
    started_at = models.DateTimeField('Başlangıç Zamanı', null=True, blank=True)
    completed_at = models.DateTimeField('Tamamlanma Zamanı', null=True, blank=True)
    estimated_duration = models.IntegerField(
        'Tahmini Süre (Dakika)',
        default=30,
        validators=[MinValueValidator(1)]
    )
    actual_duration = models.IntegerField('Gerçek Süre (Dakika)', null=True, blank=True)
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    special_instructions = models.TextField('Özel Talimatlar', blank=True)
    
    # Kontrol
    inspected_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='inspected_cleaning_tasks',
        verbose_name='Kontrol Eden'
    )
    inspected_at = models.DateTimeField('Kontrol Tarihi', null=True, blank=True)
    inspection_notes = models.TextField('Kontrol Notları', blank=True)
    inspection_passed = models.BooleanField('Kontrol Geçti mi?', default=False)
    
    class Meta:
        verbose_name = 'Temizlik Görevi'
        verbose_name_plural = 'Temizlik Görevleri'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['hotel', 'status', 'priority']),
            models.Index(fields=['room_number', 'status']),
            models.Index(fields=['assigned_to', 'status']),
            models.Index(fields=['scheduled_time']),
        ]
    
    def __str__(self):
        return f"{self.room_number.number} - {self.get_task_type_display()} ({self.get_status_display()})"
    
    def get_duration_display(self):
        """Süre gösterimi"""
        if self.actual_duration:
            return f"{self.actual_duration} dk"
        return f"{self.estimated_duration} dk (tahmini)"
    
    def is_overdue(self):
        """Gecikmiş mi?"""
        if self.scheduled_time and self.status in ['pending', 'in_progress']:
            return timezone.now() > self.scheduled_time
        return False


# ==================== TEMİZLİK KONTROL LİSTESİ ====================

class CleaningChecklistItem(TimeStampedModel, SoftDeleteModel):
    """
    Temizlik Kontrol Listesi Öğesi
    Her görev için kontrol edilecek öğeler
    """
    task = models.ForeignKey(
        CleaningTask,
        on_delete=models.CASCADE,
        related_name='checklist_items',
        verbose_name='Temizlik Görevi'
    )
    
    item_name = models.CharField('Öğe Adı', max_length=200)
    category = models.CharField(
        'Kategori',
        max_length=50,
        choices=[
            ('bathroom', 'Banyo'),
            ('bedroom', 'Yatak Odası'),
            ('living', 'Oturma Alanı'),
            ('kitchen', 'Mutfak'),
            ('balcony', 'Balkon'),
            ('other', 'Diğer'),
        ],
        default='bedroom'
    )
    
    is_checked = models.BooleanField('Kontrol Edildi mi?', default=False)
    checked_at = models.DateTimeField('Kontrol Tarihi', null=True, blank=True)
    checked_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='checked_cleaning_items',
        verbose_name='Kontrol Eden'
    )
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Kontrol Listesi Öğesi'
        verbose_name_plural = 'Kontrol Listesi Öğeleri'
        ordering = ['category', 'item_name']
    
    def __str__(self):
        return f"{self.task.room_number.number} - {self.item_name}"


# ==================== EKSİK MALZEME ====================

class MissingItem(TimeStampedModel, SoftDeleteModel):
    """
    Eksik Malzeme Modeli
    Odalardan eksik olan malzemeleri takip eder
    """
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='missing_items',
        verbose_name='Otel'
    )
    room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.CASCADE,
        related_name='missing_items',
        verbose_name='Oda Numarası'
    )
    task = models.ForeignKey(
        CleaningTask,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='missing_items',
        verbose_name='Temizlik Görevi'
    )
    
    item_name = models.CharField('Malzeme Adı', max_length=200)
    item_category = models.CharField(
        'Kategori',
        max_length=50,
        choices=[
            ('linen', 'Çarşaf/Yatak Örtüsü'),
            ('towel', 'Havlu'),
            ('amenity', 'Aksesuar'),
            ('furniture', 'Mobilya'),
            ('appliance', 'Cihaz'),
            ('other', 'Diğer'),
        ],
        default='amenity'
    )
    
    quantity = models.IntegerField('Miktar', default=1, validators=[MinValueValidator(1)])
    reported_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        related_name='reported_missing_items',
        verbose_name='Bildiren'
    )
    reported_at = models.DateTimeField('Bildirim Tarihi', auto_now_add=True)
    
    # Durum
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=[
            ('reported', 'Bildirildi'),
            ('replaced', 'Yerine Konuldu'),
            ('not_found', 'Bulunamadı'),
            ('damaged', 'Hasarlı'),
        ],
        default='reported',
        db_index=True
    )
    
    replaced_at = models.DateTimeField('Yerine Konulma Tarihi', null=True, blank=True)
    replaced_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='replaced_missing_items',
        verbose_name='Yerine Koyan'
    )
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Eksik Malzeme'
        verbose_name_plural = 'Eksik Malzemeler'
        ordering = ['-reported_at']
        indexes = [
            models.Index(fields=['hotel', 'status']),
            models.Index(fields=['room_number', 'status']),
        ]
    
    def __str__(self):
        return f"{self.room_number.number} - {self.item_name} ({self.get_status_display()})"


# ==================== ÇAMAŞIR YÖNETİMİ ====================

class LaundryStatus(models.TextChoices):
    """Çamaşır Durumları"""
    COLLECTED = 'collected', 'Toplandı'
    IN_WASH = 'in_wash', 'Yıkanıyor'
    DRYING = 'drying', 'Kuruyor'
    IRONING = 'ironing', 'Ütüleniyor'
    READY = 'ready', 'Hazır'
    DELIVERED = 'delivered', 'Teslim Edildi'


class LaundryItem(TimeStampedModel, SoftDeleteModel):
    """
    Çamaşır Öğesi Modeli
    Odalardan toplanan çamaşırları takip eder
    """
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='laundry_items',
        verbose_name='Otel'
    )
    room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.CASCADE,
        related_name='laundry_items',
        verbose_name='Oda Numarası'
    )
    task = models.ForeignKey(
        CleaningTask,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='laundry_items',
        verbose_name='Temizlik Görevi'
    )
    
    item_type = models.CharField(
        'Çamaşır Tipi',
        max_length=50,
        choices=[
            ('sheet', 'Çarşaf'),
            ('pillowcase', 'Yastık Kılıfı'),
            ('duvet_cover', 'Yorgan Kılıfı'),
            ('towel', 'Havlu'),
            ('bathrobe', 'Bornoz'),
            ('curtain', 'Perde'),
            ('other', 'Diğer'),
        ],
        default='sheet'
    )
    
    quantity = models.IntegerField('Miktar', default=1, validators=[MinValueValidator(1)])
    
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=LaundryStatus.choices,
        default=LaundryStatus.COLLECTED,
        db_index=True
    )
    
    collected_at = models.DateTimeField('Toplanma Tarihi', auto_now_add=True)
    collected_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        related_name='collected_laundry_items',
        verbose_name='Toplayan'
    )
    
    delivered_at = models.DateTimeField('Teslim Tarihi', null=True, blank=True)
    delivered_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='delivered_laundry_items',
        verbose_name='Teslim Eden'
    )
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Çamaşır Öğesi'
        verbose_name_plural = 'Çamaşır Öğeleri'
        ordering = ['-collected_at']
        indexes = [
            models.Index(fields=['hotel', 'status']),
            models.Index(fields=['room_number', 'status']),
        ]
    
    def __str__(self):
        return f"{self.room_number.number} - {self.get_item_type_display()} ({self.get_status_display()})"


# ==================== BAKIM TALEBİ ====================

class MaintenanceRequest(TimeStampedModel, SoftDeleteModel):
    """
    Bakım Talebi Modeli
    Kat hizmetleri personeli tarafından oluşturulan bakım talepleri
    """
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='housekeeping_maintenance_requests',
        verbose_name='Otel'
    )
    room_number = models.ForeignKey(
        'hotels.RoomNumber',
        on_delete=models.CASCADE,
        related_name='housekeeping_maintenance_requests',
        verbose_name='Oda Numarası'
    )
    task = models.ForeignKey(
        CleaningTask,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='maintenance_requests',
        verbose_name='Temizlik Görevi'
    )
    
    request_type = models.CharField(
        'Talep Tipi',
        max_length=50,
        choices=[
            ('plumbing', 'Tesisat'),
            ('electrical', 'Elektrik'),
            ('hvac', 'Isıtma/Soğutma'),
            ('furniture', 'Mobilya'),
            ('appliance', 'Cihaz'),
            ('paint', 'Boya'),
            ('other', 'Diğer'),
        ],
        default='other'
    )
    
    priority = models.CharField(
        'Öncelik',
        max_length=20,
        choices=CleaningPriority.choices,
        default=CleaningPriority.NORMAL,
        db_index=True
    )
    
    description = models.TextField('Açıklama')
    reported_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        related_name='reported_maintenance_requests',
        verbose_name='Bildiren'
    )
    reported_at = models.DateTimeField('Bildirim Tarihi', auto_now_add=True)
    
    # Durum
    status = models.CharField(
        'Durum',
        max_length=20,
        choices=[
            ('pending', 'Bekliyor'),
            ('assigned', 'Atandı'),
            ('in_progress', 'Devam Ediyor'),
            ('completed', 'Tamamlandı'),
            ('cancelled', 'İptal Edildi'),
        ],
        default='pending',
        db_index=True
    )
    
    assigned_to = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='assigned_maintenance_requests',
        verbose_name='Atanan Teknisyen'
    )
    assigned_at = models.DateTimeField('Atanma Tarihi', null=True, blank=True)
    
    completed_at = models.DateTimeField('Tamamlanma Tarihi', null=True, blank=True)
    completion_notes = models.TextField('Tamamlanma Notları', blank=True)
    
    class Meta:
        verbose_name = 'Bakım Talebi'
        verbose_name_plural = 'Bakım Talepleri'
        ordering = ['-reported_at']
        indexes = [
            models.Index(fields=['hotel', 'status', 'priority']),
            models.Index(fields=['room_number', 'status']),
        ]
    
    def __str__(self):
        return f"{self.room_number.number} - {self.get_request_type_display()} ({self.get_status_display()})"


# ==================== KAT HİZMETLERİ AYARLARI ====================

class HousekeepingSettings(TimeStampedModel):
    """
    Kat Hizmetleri Ayarları
    Otel bazlı kat hizmetleri ayarları
    """
    hotel = models.OneToOneField(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='housekeeping_settings',
        verbose_name='Otel'
    )
    
    # Varsayılan Ayarlar
    default_cleaning_duration = models.IntegerField(
        'Varsayılan Temizlik Süresi (Dakika)',
        default=30,
        validators=[MinValueValidator(1)]
    )
    default_checkout_cleaning_duration = models.IntegerField(
        'Varsayılan Check-Out Temizlik Süresi (Dakika)',
        default=45,
        validators=[MinValueValidator(1)]
    )
    default_deep_cleaning_duration = models.IntegerField(
        'Varsayılan Derinlemesine Temizlik Süresi (Dakika)',
        default=120,
        validators=[MinValueValidator(1)]
    )
    
    # Kontrol Ayarları
    require_inspection = models.BooleanField('Kontrol Zorunlu mu?', default=True)
    inspection_timeout = models.IntegerField(
        'Kontrol Zaman Aşımı (Dakika)',
        default=60,
        validators=[MinValueValidator(1)]
    )
    
    # Bildirim Ayarları
    notify_on_overdue = models.BooleanField('Gecikme Bildirimi', default=True)
    notify_on_completion = models.BooleanField('Tamamlanma Bildirimi', default=True)
    
    # Otomatik İşlemler
    auto_assign_tasks = models.BooleanField('Otomatik Görev Atama', default=False)
    auto_create_checkout_tasks = models.BooleanField('Otomatik Check-Out Görevi Oluştur', default=True)
    
    class Meta:
        verbose_name = 'Kat Hizmetleri Ayarları'
        verbose_name_plural = 'Kat Hizmetleri Ayarları'
    
    def __str__(self):
        return f"{self.hotel.name} - Kat Hizmetleri Ayarları"


# ==================== GÜNLÜK RAPOR ====================

class HousekeepingDailyReport(TimeStampedModel):
    """
    Günlük Kat Hizmetleri Raporu
    Günlük temizlik işlemlerinin özeti
    """
    hotel = models.ForeignKey(
        'hotels.Hotel',
        on_delete=models.CASCADE,
        related_name='housekeeping_daily_reports',
        verbose_name='Otel'
    )
    report_date = models.DateField('Rapor Tarihi', db_index=True)
    created_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        related_name='created_housekeeping_reports',
        verbose_name='Oluşturan'
    )
    
    # İstatistikler
    total_tasks = models.IntegerField('Toplam Görev', default=0)
    completed_tasks = models.IntegerField('Tamamlanan Görev', default=0)
    pending_tasks = models.IntegerField('Bekleyen Görev', default=0)
    overdue_tasks = models.IntegerField('Gecikmiş Görev', default=0)
    
    total_rooms_cleaned = models.IntegerField('Temizlenen Oda Sayısı', default=0)
    total_rooms_inspected = models.IntegerField('Kontrol Edilen Oda Sayısı', default=0)
    
    average_cleaning_time = models.DecimalField(
        'Ortalama Temizlik Süresi (Dakika)',
        max_digits=8,
        decimal_places=2,
        null=True,
        blank=True
    )
    
    # Notlar
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Günlük Rapor'
        verbose_name_plural = 'Günlük Raporlar'
        unique_together = ['hotel', 'report_date']
        ordering = ['-report_date']
        indexes = [
            models.Index(fields=['hotel', 'report_date']),
        ]
    
    def __str__(self):
        return f"{self.hotel.name} - {self.report_date}"
