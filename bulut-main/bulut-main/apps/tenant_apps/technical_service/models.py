"""
Teknik Servis Modelleri
Profesyonel otel teknik servis yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== BAKIM TALEBİ ====================

class MaintenanceRequest(TimeStampedModel, SoftDeleteModel):
    """Bakım Talebi Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='maintenance_requests', verbose_name='Otel')
    room_number = models.ForeignKey('hotels.RoomNumber', on_delete=models.CASCADE, related_name='maintenance_requests', verbose_name='Oda Numarası', null=True, blank=True)
    
    request_type = models.CharField('Talep Tipi', max_length=50, choices=[
        ('plumbing', 'Tesisat'), ('electrical', 'Elektrik'), ('hvac', 'Isıtma/Soğutma'),
        ('furniture', 'Mobilya'), ('appliance', 'Cihaz'), ('paint', 'Boya'), ('other', 'Diğer')
    ], default='other')
    
    priority = models.CharField('Öncelik', max_length=20, choices=[
        ('low', 'Düşük'), ('normal', 'Normal'), ('high', 'Yüksek'), ('urgent', 'Acil')
    ], default='normal', db_index=True)
    
    description = models.TextField('Açıklama')
    reported_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, related_name='reported_maintenance', verbose_name='Bildiren')
    reported_at = models.DateTimeField('Bildirim Tarihi', auto_now_add=True)
    
    status = models.CharField('Durum', max_length=20, choices=[
        ('pending', 'Bekliyor'), ('assigned', 'Atandı'), ('in_progress', 'Devam Ediyor'),
        ('completed', 'Tamamlandı'), ('cancelled', 'İptal Edildi')
    ], default='pending', db_index=True)
    
    assigned_to = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, blank=True, related_name='assigned_maintenance', verbose_name='Atanan Teknisyen')
    assigned_at = models.DateTimeField('Atanma Tarihi', null=True, blank=True)
    
    started_at = models.DateTimeField('Başlangıç Tarihi', null=True, blank=True)
    completed_at = models.DateTimeField('Tamamlanma Tarihi', null=True, blank=True)
    completion_notes = models.TextField('Tamamlanma Notları', blank=True)
    
    estimated_cost = models.DecimalField('Tahmini Maliyet', max_digits=10, decimal_places=2, null=True, blank=True)
    actual_cost = models.DecimalField('Gerçek Maliyet', max_digits=10, decimal_places=2, null=True, blank=True)
    
    class Meta:
        verbose_name = 'Bakım Talebi'
        verbose_name_plural = 'Bakım Talepleri'
        ordering = ['-reported_at']
        indexes = [models.Index(fields=['hotel', 'status', 'priority'])]
    
    def __str__(self):
        room = self.room_number.number if self.room_number else 'Genel'
        return f"{room} - {self.get_request_type_display()} ({self.get_status_display()})"


# ==================== BAKIM KAYITLARI ====================

class MaintenanceRecord(TimeStampedModel, SoftDeleteModel):
    """Bakım Kaydı Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='maintenance_records', verbose_name='Otel')
    request = models.ForeignKey(MaintenanceRequest, on_delete=models.CASCADE, related_name='records', verbose_name='Bakım Talebi', null=True, blank=True)
    
    equipment_name = models.CharField('Ekipman Adı', max_length=200)
    equipment_type = models.CharField('Ekipman Tipi', max_length=50, choices=[
        ('hvac', 'Isıtma/Soğutma'), ('plumbing', 'Tesisat'), ('electrical', 'Elektrik'),
        ('appliance', 'Cihaz'), ('furniture', 'Mobilya'), ('other', 'Diğer')
    ], default='other')
    
    maintenance_type = models.CharField('Bakım Tipi', max_length=50, choices=[
        ('preventive', 'Önleyici'), ('corrective', 'Düzeltici'), ('emergency', 'Acil')
    ], default='corrective')
    
    performed_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, related_name='performed_maintenance', verbose_name='Yapan Teknisyen')
    performed_at = models.DateTimeField('Bakım Tarihi', default=timezone.now)
    
    description = models.TextField('Açıklama')
    parts_used = models.TextField('Kullanılan Parçalar', blank=True)
    cost = models.DecimalField('Maliyet', max_digits=10, decimal_places=2, null=True, blank=True)
    
    next_maintenance_date = models.DateField('Sonraki Bakım Tarihi', null=True, blank=True)
    
    class Meta:
        verbose_name = 'Bakım Kaydı'
        verbose_name_plural = 'Bakım Kayıtları'
        ordering = ['-performed_at']
    
    def __str__(self):
        return f"{self.equipment_name} - {self.get_maintenance_type_display()} ({self.performed_at.date()})"


# ==================== EKİPMAN ENVANTERİ ====================

class Equipment(TimeStampedModel, SoftDeleteModel):
    """Ekipman Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='equipment', verbose_name='Otel')
    room_number = models.ForeignKey('hotels.RoomNumber', on_delete=models.SET_NULL, null=True, blank=True, related_name='equipment', verbose_name='Oda Numarası')
    
    name = models.CharField('Ekipman Adı', max_length=200)
    equipment_type = models.CharField('Ekipman Tipi', max_length=50, choices=[
        ('hvac', 'Isıtma/Soğutma'), ('plumbing', 'Tesisat'), ('electrical', 'Elektrik'),
        ('appliance', 'Cihaz'), ('furniture', 'Mobilya'), ('other', 'Diğer')
    ], default='other')
    
    brand = models.CharField('Marka', max_length=100, blank=True)
    model = models.CharField('Model', max_length=100, blank=True)
    serial_number = models.CharField('Seri Numarası', max_length=100, blank=True)
    
    purchase_date = models.DateField('Satın Alma Tarihi', null=True, blank=True)
    warranty_expiry = models.DateField('Garanti Bitiş Tarihi', null=True, blank=True)
    
    status = models.CharField('Durum', max_length=20, choices=[
        ('operational', 'Çalışır Durumda'), ('maintenance', 'Bakımda'), ('broken', 'Arızalı'),
        ('retired', 'Emekli'), ('spare', 'Yedek')
    ], default='operational', db_index=True)
    
    last_maintenance_date = models.DateField('Son Bakım Tarihi', null=True, blank=True)
    next_maintenance_date = models.DateField('Sonraki Bakım Tarihi', null=True, blank=True)
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Ekipman'
        verbose_name_plural = 'Ekipmanlar'
        ordering = ['name']
        indexes = [models.Index(fields=['hotel', 'status'])]
    
    def __str__(self):
        return f"{self.name} ({self.get_equipment_type_display()})"


# ==================== TEKNİK SERVİS AYARLARI ====================

class TechnicalServiceSettings(TimeStampedModel):
    """Teknik Servis Ayarları"""
    hotel = models.OneToOneField('hotels.Hotel', on_delete=models.CASCADE, related_name='technical_service_settings', verbose_name='Otel')
    
    auto_assign_requests = models.BooleanField('Otomatik Talep Atama', default=False)
    default_priority = models.CharField('Varsayılan Öncelik', max_length=20, choices=[
        ('low', 'Düşük'), ('normal', 'Normal'), ('high', 'Yüksek'), ('urgent', 'Acil')
    ], default='normal')
    
    maintenance_reminder_days = models.IntegerField('Bakım Hatırlatma Günü', default=7, validators=[MinValueValidator(1)])
    
    class Meta:
        verbose_name = 'Teknik Servis Ayarları'
        verbose_name_plural = 'Teknik Servis Ayarları'
    
    def __str__(self):
        return f"{self.hotel.name} - Teknik Servis Ayarları"

