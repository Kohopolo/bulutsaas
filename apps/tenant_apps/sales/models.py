"""
Satış Yönetimi Modelleri
Profesyonel otel satış yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== ACENTE YÖNETİMİ ====================

class Agency(TimeStampedModel, SoftDeleteModel):
    """Acente Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='agencies', verbose_name='Otel')
    
    name = models.CharField('Acente Adı', max_length=200)
    code = models.CharField('Acente Kodu', max_length=50, db_index=True)
    
    contact_person = models.CharField('İletişim Kişisi', max_length=200, blank=True)
    email = models.EmailField('E-posta', blank=True)
    phone = models.CharField('Telefon', max_length=20, blank=True)
    address = models.TextField('Adres', blank=True)
    
    # Komisyon Ayarları
    commission_rate = models.DecimalField('Komisyon Oranı (%)', max_digits=5, decimal_places=2, default=Decimal('0.00'), validators=[MinValueValidator(Decimal('0.00'))])
    commission_type = models.CharField('Komisyon Tipi', max_length=20, choices=[
        ('percentage', 'Yüzde'), ('fixed', 'Sabit Tutar')
    ], default='percentage')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    contract_start_date = models.DateField('Sözleşme Başlangıç Tarihi', null=True, blank=True)
    contract_end_date = models.DateField('Sözleşme Bitiş Tarihi', null=True, blank=True)
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Acente'
        verbose_name_plural = 'Acenteler'
        unique_together = ['hotel', 'code']
        ordering = ['name']
        indexes = [models.Index(fields=['hotel', 'is_active'])]
    
    def __str__(self):
        return f"{self.name} ({self.code})"


# ==================== SATIŞ KAYDI ====================

class SalesRecord(TimeStampedModel, SoftDeleteModel):
    """Satış Kaydı Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='sales_records', verbose_name='Otel')
    reservation = models.ForeignKey('reception.Reservation', on_delete=models.SET_NULL, null=True, blank=True, related_name='sales_records', verbose_name='Rezervasyon')
    agency = models.ForeignKey(Agency, on_delete=models.SET_NULL, null=True, blank=True, related_name='sales_records', verbose_name='Acente')
    
    sales_type = models.CharField('Satış Tipi', max_length=50, choices=[
        ('direct', 'Direkt'), ('agency', 'Acente'), ('online', 'Online'), ('walk_in', 'Walk-In'), ('corporate', 'Kurumsal')
    ], default='direct', db_index=True)
    
    sales_date = models.DateField('Satış Tarihi', db_index=True)
    sales_amount = models.DecimalField('Satış Tutarı', max_digits=10, decimal_places=2, validators=[MinValueValidator(Decimal('0.00'))])
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Komisyon
    commission_amount = models.DecimalField('Komisyon Tutarı', max_digits=10, decimal_places=2, default=Decimal('0.00'), validators=[MinValueValidator(Decimal('0.00'))])
    commission_paid = models.BooleanField('Komisyon Ödendi mi?', default=False)
    commission_paid_date = models.DateField('Komisyon Ödeme Tarihi', null=True, blank=True)
    
    # Satış Personeli
    sales_person = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, blank=True, related_name='sales_records', verbose_name='Satış Personeli')
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Satış Kaydı'
        verbose_name_plural = 'Satış Kayıtları'
        ordering = ['-sales_date']
        indexes = [models.Index(fields=['hotel', 'sales_date', 'sales_type'])]
    
    def __str__(self):
        return f"{self.get_sales_type_display()} - {self.sales_amount} {self.currency} ({self.sales_date})"


# ==================== SATIŞ HEDEFİ ====================

class SalesTarget(TimeStampedModel, SoftDeleteModel):
    """Satış Hedefi Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='sales_targets', verbose_name='Otel')
    
    target_name = models.CharField('Hedef Adı', max_length=200)
    target_type = models.CharField('Hedef Tipi', max_length=50, choices=[
        ('revenue', 'Gelir'), ('reservations', 'Rezervasyon Sayısı'), ('occupancy', 'Doluluk Oranı')
    ], default='revenue')
    
    target_amount = models.DecimalField('Hedef Tutar', max_digits=10, decimal_places=2, validators=[MinValueValidator(Decimal('0.00'))])
    target_count = models.IntegerField('Hedef Sayı', null=True, blank=True, validators=[MinValueValidator(0)])
    
    period_type = models.CharField('Dönem Tipi', max_length=20, choices=[
        ('daily', 'Günlük'), ('weekly', 'Haftalık'), ('monthly', 'Aylık'), ('yearly', 'Yıllık')
    ], default='monthly')
    
    start_date = models.DateField('Başlangıç Tarihi')
    end_date = models.DateField('Bitiş Tarihi')
    
    assigned_to = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, blank=True, related_name='sales_targets', verbose_name='Atanan')
    
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Satış Hedefi'
        verbose_name_plural = 'Satış Hedefleri'
        ordering = ['-start_date']
        indexes = [models.Index(fields=['hotel', 'is_active', 'start_date'])]
    
    def __str__(self):
        return f"{self.target_name} - {self.get_period_type_display()} ({self.start_date} - {self.end_date})"


# ==================== SATIŞ RAPORU ====================

class SalesReport(TimeStampedModel):
    """Satış Raporu"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='sales_reports', verbose_name='Otel')
    report_date = models.DateField('Rapor Tarihi', db_index=True)
    report_type = models.CharField('Rapor Tipi', max_length=20, choices=[
        ('daily', 'Günlük'), ('weekly', 'Haftalık'), ('monthly', 'Aylık'), ('yearly', 'Yıllık')
    ], default='daily')
    
    created_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, related_name='created_sales_reports', verbose_name='Oluşturan')
    
    # İstatistikler
    total_sales = models.DecimalField('Toplam Satış', max_digits=12, decimal_places=2, default=Decimal('0.00'))
    total_reservations = models.IntegerField('Toplam Rezervasyon', default=0)
    total_commission = models.DecimalField('Toplam Komisyon', max_digits=10, decimal_places=2, default=Decimal('0.00'))
    
    direct_sales = models.DecimalField('Direkt Satış', max_digits=12, decimal_places=2, default=Decimal('0.00'))
    agency_sales = models.DecimalField('Acente Satışı', max_digits=12, decimal_places=2, default=Decimal('0.00'))
    online_sales = models.DecimalField('Online Satış', max_digits=12, decimal_places=2, default=Decimal('0.00'))
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Satış Raporu'
        verbose_name_plural = 'Satış Raporları'
        unique_together = ['hotel', 'report_date', 'report_type']
        ordering = ['-report_date']
    
    def __str__(self):
        return f"{self.hotel.name} - {self.get_report_type_display()} ({self.report_date})"


# ==================== SATIŞ YÖNETİMİ AYARLARI ====================

class SalesSettings(TimeStampedModel):
    """Satış Yönetimi Ayarları"""
    hotel = models.OneToOneField('hotels.Hotel', on_delete=models.CASCADE, related_name='sales_settings', verbose_name='Otel')
    
    default_commission_rate = models.DecimalField('Varsayılan Komisyon Oranı (%)', max_digits=5, decimal_places=2, default=Decimal('10.00'))
    auto_calculate_commission = models.BooleanField('Otomatik Komisyon Hesapla', default=True)
    
    sales_target_enabled = models.BooleanField('Satış Hedefi Aktif mi?', default=True)
    default_target_period = models.CharField('Varsayılan Hedef Dönemi', max_length=20, choices=[
        ('daily', 'Günlük'), ('weekly', 'Haftalık'), ('monthly', 'Aylık'), ('yearly', 'Yıllık')
    ], default='monthly')
    
    class Meta:
        verbose_name = 'Satış Yönetimi Ayarları'
        verbose_name_plural = 'Satış Yönetimi Ayarları'
    
    def __str__(self):
        return f"{self.hotel.name} - Satış Yönetimi Ayarları"

