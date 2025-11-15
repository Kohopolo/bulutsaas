"""
Kalite Kontrol Modelleri
Profesyonel otel kalite kontrol yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== ODA KALİTE KONTROLÜ ====================

class RoomQualityInspection(TimeStampedModel, SoftDeleteModel):
    """Oda Kalite Kontrolü Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='quality_inspections', verbose_name='Otel')
    room_number = models.ForeignKey('hotels.RoomNumber', on_delete=models.CASCADE, related_name='quality_inspections', verbose_name='Oda Numarası')
    # reservation = models.ForeignKey('reception.Reservation', on_delete=models.SET_NULL, null=True, blank=True, related_name='quality_inspections', verbose_name='Rezervasyon')  # KALDIRILDI - Reception modülü yeniden inşa edilecek
    
    inspection_type = models.CharField('Kontrol Tipi', max_length=50, choices=[
        ('pre_checkin', 'Check-In Öncesi'), ('post_checkout', 'Check-Out Sonrası'),
        ('routine', 'Rutin Kontrol'), ('complaint', 'Şikayet Sonrası'), ('random', 'Rastgele')
    ], default='routine', db_index=True)
    
    inspected_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, related_name='room_quality_inspections', verbose_name='Kontrol Eden')
    inspected_at = models.DateTimeField('Kontrol Tarihi', default=timezone.now)
    
    # Genel Değerlendirme
    overall_score = models.IntegerField('Genel Puan', validators=[MinValueValidator(0), MaxValueValidator(100)], null=True, blank=True)
    cleanliness_score = models.IntegerField('Temizlik Puanı', validators=[MinValueValidator(0), MaxValueValidator(100)], null=True, blank=True)
    maintenance_score = models.IntegerField('Bakım Puanı', validators=[MinValueValidator(0), MaxValueValidator(100)], null=True, blank=True)
    amenities_score = models.IntegerField('Olanaklar Puanı', validators=[MinValueValidator(0), MaxValueValidator(100)], null=True, blank=True)
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=[
        ('passed', 'Geçti'), ('failed', 'Başarısız'), ('needs_improvement', 'İyileştirme Gerekli')
    ], default='passed', db_index=True)
    
    notes = models.TextField('Notlar', blank=True)
    action_required = models.BooleanField('Aksiyon Gerekli mi?', default=False)
    action_taken = models.TextField('Alınan Aksiyon', blank=True)
    
    class Meta:
        verbose_name = 'Oda Kalite Kontrolü'
        verbose_name_plural = 'Oda Kalite Kontrolleri'
        ordering = ['-inspected_at']
        indexes = [models.Index(fields=['hotel', 'status', 'inspected_at'])]
    
    def __str__(self):
        return f"{self.room_number.number} - {self.get_inspection_type_display()} ({self.inspected_at.date()})"


# ==================== KALİTE KONTROL LİSTESİ ====================

class QualityChecklistItem(TimeStampedModel, SoftDeleteModel):
    """Kalite Kontrol Listesi Öğesi"""
    inspection = models.ForeignKey(RoomQualityInspection, on_delete=models.CASCADE, related_name='checklist_items', verbose_name='Kontrol')
    
    item_name = models.CharField('Öğe Adı', max_length=200)
    category = models.CharField('Kategori', max_length=50, choices=[
        ('cleanliness', 'Temizlik'), ('maintenance', 'Bakım'), ('amenities', 'Olanaklar'),
        ('safety', 'Güvenlik'), ('comfort', 'Konfor'), ('other', 'Diğer')
    ], default='cleanliness')
    
    is_checked = models.BooleanField('Kontrol Edildi mi?', default=False)
    score = models.IntegerField('Puan', validators=[MinValueValidator(0), MaxValueValidator(10)], null=True, blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Kontrol Listesi Öğesi'
        verbose_name_plural = 'Kontrol Listesi Öğeleri'
        ordering = ['category', 'item_name']
    
    def __str__(self):
        return f"{self.inspection.room_number.number} - {self.item_name}"


# ==================== MÜŞTERİ ŞİKAYETİ ====================

class CustomerComplaint(TimeStampedModel, SoftDeleteModel):
    """Müşteri Şikayeti Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='customer_complaints', verbose_name='Otel')
    # reservation = models.ForeignKey('reception.Reservation', on_delete=models.SET_NULL, null=True, blank=True, related_name='complaints', verbose_name='Rezervasyon')  # KALDIRILDI - Reception modülü yeniden inşa edilecek
    customer = models.ForeignKey('tenant_core.Customer', on_delete=models.SET_NULL, null=True, blank=True, related_name='complaints', verbose_name='Müşteri')
    
    complaint_type = models.CharField('Şikayet Tipi', max_length=50, choices=[
        ('room_quality', 'Oda Kalitesi'), ('service', 'Hizmet'), ('cleanliness', 'Temizlik'),
        ('noise', 'Gürültü'), ('staff', 'Personel'), ('facilities', 'Tesisler'), ('other', 'Diğer')
    ], default='other', db_index=True)
    
    priority = models.CharField('Öncelik', max_length=20, choices=[
        ('low', 'Düşük'), ('normal', 'Normal'), ('high', 'Yüksek'), ('urgent', 'Acil')
    ], default='normal', db_index=True)
    
    description = models.TextField('Açıklama')
    reported_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, related_name='reported_complaints', verbose_name='Bildiren')
    reported_at = models.DateTimeField('Bildirim Tarihi', auto_now_add=True)
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=[
        ('pending', 'Bekliyor'), ('investigating', 'İnceleniyor'), ('resolved', 'Çözüldü'),
        ('closed', 'Kapatıldı'), ('escalated', 'Üst Seviyeye Taşındı')
    ], default='pending', db_index=True)
    
    # Çözüm
    resolved_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, blank=True, related_name='resolved_complaints', verbose_name='Çözen')
    resolved_at = models.DateTimeField('Çözüm Tarihi', null=True, blank=True)
    resolution_notes = models.TextField('Çözüm Notları', blank=True)
    customer_satisfaction = models.IntegerField('Müşteri Memnuniyeti', validators=[MinValueValidator(1), MaxValueValidator(5)], null=True, blank=True)
    
    class Meta:
        verbose_name = 'Müşteri Şikayeti'
        verbose_name_plural = 'Müşteri Şikayetleri'
        ordering = ['-reported_at']
        indexes = [models.Index(fields=['hotel', 'status', 'priority'])]
    
    def __str__(self):
        return f"{self.get_complaint_type_display()} - {self.get_status_display()} ({self.reported_at.date()})"


# ==================== KALİTE STANDARTLARI ====================

class QualityStandard(TimeStampedModel, SoftDeleteModel):
    """Kalite Standartları Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='quality_standards', verbose_name='Otel')
    
    name = models.CharField('Standart Adı', max_length=200)
    category = models.CharField('Kategori', max_length=50, choices=[
        ('cleanliness', 'Temizlik'), ('maintenance', 'Bakım'), ('service', 'Hizmet'),
        ('safety', 'Güvenlik'), ('comfort', 'Konfor'), ('other', 'Diğer')
    ], default='cleanliness')
    
    description = models.TextField('Açıklama')
    minimum_score = models.IntegerField('Minimum Puan', validators=[MinValueValidator(0), MaxValueValidator(100)], default=80)
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Kalite Standardı'
        verbose_name_plural = 'Kalite Standartları'
        ordering = ['category', 'name']
    
    def __str__(self):
        return f"{self.name} ({self.get_category_display()})"


# ==================== DENETİM RAPORU ====================

class QualityAuditReport(TimeStampedModel):
    """Kalite Denetim Raporu"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='quality_audit_reports', verbose_name='Otel')
    report_date = models.DateField('Rapor Tarihi', db_index=True)
    created_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, related_name='created_quality_reports', verbose_name='Oluşturan')
    
    # İstatistikler
    total_inspections = models.IntegerField('Toplam Kontrol', default=0)
    passed_inspections = models.IntegerField('Geçen Kontroller', default=0)
    failed_inspections = models.IntegerField('Başarısız Kontroller', default=0)
    
    average_score = models.DecimalField('Ortalama Puan', max_digits=5, decimal_places=2, null=True, blank=True)
    average_cleanliness_score = models.DecimalField('Ortalama Temizlik Puanı', max_digits=5, decimal_places=2, null=True, blank=True)
    average_maintenance_score = models.DecimalField('Ortalama Bakım Puanı', max_digits=5, decimal_places=2, null=True, blank=True)
    
    total_complaints = models.IntegerField('Toplam Şikayet', default=0)
    resolved_complaints = models.IntegerField('Çözülen Şikayet', default=0)
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Denetim Raporu'
        verbose_name_plural = 'Denetim Raporları'
        unique_together = ['hotel', 'report_date']
        ordering = ['-report_date']
    
    def __str__(self):
        return f"{self.hotel.name} - {self.report_date}"


# ==================== KALİTE KONTROL AYARLARI ====================

class QualityControlSettings(TimeStampedModel):
    """Kalite Kontrol Ayarları"""
    hotel = models.OneToOneField('hotels.Hotel', on_delete=models.CASCADE, related_name='quality_control_settings', verbose_name='Otel')
    
    require_pre_checkin_inspection = models.BooleanField('Check-In Öncesi Kontrol Zorunlu mu?', default=True)
    require_post_checkout_inspection = models.BooleanField('Check-Out Sonrası Kontrol Zorunlu mu?', default=True)
    routine_inspection_frequency = models.IntegerField('Rutin Kontrol Sıklığı (Gün)', default=7, validators=[MinValueValidator(1)])
    
    minimum_overall_score = models.IntegerField('Minimum Genel Puan', default=80, validators=[MinValueValidator(0), MaxValueValidator(100)])
    auto_escalate_low_scores = models.BooleanField('Düşük Puanları Otomatik Üst Seviyeye Taşı', default=True)
    
    class Meta:
        verbose_name = 'Kalite Kontrol Ayarları'
        verbose_name_plural = 'Kalite Kontrol Ayarları'
    
    def __str__(self):
        return f"{self.hotel.name} - Kalite Kontrol Ayarları"

