"""
Personel Yönetimi Modelleri
Profesyonel otel personel yönetim sistemi
"""
from django.db import models
from django.core.validators import MinValueValidator, MaxValueValidator
from django.utils import timezone
from django.contrib.auth.models import User
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel


# ==================== PERSONEL KAYDI ====================

class Staff(TimeStampedModel, SoftDeleteModel):
    """Personel Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='staff', verbose_name='Otel')
    user = models.OneToOneField(User, on_delete=models.CASCADE, related_name='staff_profile', verbose_name='Kullanıcı', null=True, blank=True)
    
    employee_id = models.CharField('Personel No', max_length=50, db_index=True)
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    
    department = models.CharField('Departman', max_length=100, choices=[
        ('reception', 'Resepsiyon'), ('housekeeping', 'Kat Hizmetleri'), ('maintenance', 'Teknik Servis'),
        ('kitchen', 'Mutfak'), ('restaurant', 'Restoran'), ('security', 'Güvenlik'),
        ('management', 'Yönetim'), ('sales', 'Satış'), ('other', 'Diğer')
    ], default='other', db_index=True)
    
    position = models.CharField('Pozisyon', max_length=100)
    
    # İletişim
    email = models.EmailField('E-posta', blank=True)
    phone = models.CharField('Telefon', max_length=20, blank=True)
    address = models.TextField('Adres', blank=True)
    
    # İş Bilgileri
    hire_date = models.DateField('İşe Başlama Tarihi', null=True, blank=True)
    termination_date = models.DateField('İşten Ayrılma Tarihi', null=True, blank=True)
    
    employment_type = models.CharField('İstihdam Tipi', max_length=20, choices=[
        ('full_time', 'Tam Zamanlı'), ('part_time', 'Yarı Zamanlı'), ('contract', 'Sözleşmeli'), ('intern', 'Stajyer')
    ], default='full_time')
    
    # Maaş
    salary = models.DecimalField('Maaş', max_digits=10, decimal_places=2, null=True, blank=True, validators=[MinValueValidator(Decimal('0.00'))])
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Personel'
        verbose_name_plural = 'Personel'
        unique_together = ['hotel', 'employee_id']
        ordering = ['last_name', 'first_name']
        indexes = [models.Index(fields=['hotel', 'department', 'is_active'])]
    
    def __str__(self):
        return f"{self.first_name} {self.last_name} ({self.employee_id})"
    
    @property
    def full_name(self):
        return f"{self.first_name} {self.last_name}"


# ==================== VARDİYA YÖNETİMİ ====================

class Shift(TimeStampedModel, SoftDeleteModel):
    """Vardiya Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='shifts', verbose_name='Otel')
    staff = models.ForeignKey(Staff, on_delete=models.CASCADE, related_name='shifts', verbose_name='Personel')
    
    shift_date = models.DateField('Vardiya Tarihi', db_index=True)
    shift_type = models.CharField('Vardiya Tipi', max_length=20, choices=[
        ('morning', 'Sabah'), ('afternoon', 'Öğleden Sonra'), ('evening', 'Akşam'), ('night', 'Gece'), ('custom', 'Özel')
    ], default='morning')
    
    start_time = models.TimeField('Başlangıç Saati')
    end_time = models.TimeField('Bitiş Saati')
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=[
        ('scheduled', 'Planlandı'), ('confirmed', 'Onaylandı'), ('in_progress', 'Devam Ediyor'),
        ('completed', 'Tamamlandı'), ('cancelled', 'İptal Edildi'), ('no_show', 'Gelmedi')
    ], default='scheduled', db_index=True)
    
    actual_start_time = models.TimeField('Gerçek Başlangıç Saati', null=True, blank=True)
    actual_end_time = models.TimeField('Gerçek Bitiş Saati', null=True, blank=True)
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Vardiya'
        verbose_name_plural = 'Vardiyalar'
        ordering = ['-shift_date', 'start_time']
        indexes = [models.Index(fields=['hotel', 'shift_date', 'status'])]
    
    def __str__(self):
        return f"{self.staff.full_name} - {self.shift_date} ({self.get_shift_type_display()})"


# ==================== İZİN TAKİBİ ====================

class LeaveRequest(TimeStampedModel, SoftDeleteModel):
    """İzin Talebi Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='leave_requests', verbose_name='Otel')
    staff = models.ForeignKey(Staff, on_delete=models.CASCADE, related_name='leave_requests', verbose_name='Personel')
    
    leave_type = models.CharField('İzin Tipi', max_length=20, choices=[
        ('annual', 'Yıllık İzin'), ('sick', 'Hastalık İzni'), ('personal', 'Özel İzin'),
        ('unpaid', 'Ücretsiz İzin'), ('maternity', 'Doğum İzni'), ('paternity', 'Babalık İzni'), ('other', 'Diğer')
    ], default='annual')
    
    start_date = models.DateField('Başlangıç Tarihi', db_index=True)
    end_date = models.DateField('Bitiş Tarihi', db_index=True)
    total_days = models.IntegerField('Toplam Gün', validators=[MinValueValidator(1)])
    
    reason = models.TextField('Sebep')
    
    # Durum
    status = models.CharField('Durum', max_length=20, choices=[
        ('pending', 'Bekliyor'), ('approved', 'Onaylandı'), ('rejected', 'Reddedildi'), ('cancelled', 'İptal Edildi')
    ], default='pending', db_index=True)
    
    requested_at = models.DateTimeField('Talep Tarihi', auto_now_add=True)
    reviewed_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, blank=True, related_name='reviewed_leave_requests', verbose_name='İnceleyen')
    reviewed_at = models.DateTimeField('İnceleme Tarihi', null=True, blank=True)
    review_notes = models.TextField('İnceleme Notları', blank=True)
    
    class Meta:
        verbose_name = 'İzin Talebi'
        verbose_name_plural = 'İzin Talepleri'
        ordering = ['-requested_at']
        indexes = [models.Index(fields=['hotel', 'status', 'start_date'])]
    
    def __str__(self):
        return f"{self.staff.full_name} - {self.get_leave_type_display()} ({self.start_date} - {self.end_date})"


# ==================== PERFORMANS DEĞERLENDİRME ====================

class PerformanceReview(TimeStampedModel, SoftDeleteModel):
    """Performans Değerlendirmesi Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='performance_reviews', verbose_name='Otel')
    staff = models.ForeignKey(Staff, on_delete=models.CASCADE, related_name='performance_reviews', verbose_name='Personel')
    
    review_period_start = models.DateField('Değerlendirme Dönemi Başlangıç', db_index=True)
    review_period_end = models.DateField('Değerlendirme Dönemi Bitiş', db_index=True)
    
    reviewed_by = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, related_name='conducted_performance_reviews', verbose_name='Değerlendiren')
    reviewed_at = models.DateTimeField('Değerlendirme Tarihi', default=timezone.now)
    
    # Puanlar (1-10)
    attendance_score = models.IntegerField('Devam Puanı', validators=[MinValueValidator(1), MaxValueValidator(10)], null=True, blank=True)
    performance_score = models.IntegerField('Performans Puanı', validators=[MinValueValidator(1), MaxValueValidator(10)], null=True, blank=True)
    teamwork_score = models.IntegerField('Takım Çalışması Puanı', validators=[MinValueValidator(1), MaxValueValidator(10)], null=True, blank=True)
    communication_score = models.IntegerField('İletişim Puanı', validators=[MinValueValidator(1), MaxValueValidator(10)], null=True, blank=True)
    
    overall_score = models.DecimalField('Genel Puan', max_digits=4, decimal_places=2, null=True, blank=True)
    
    strengths = models.TextField('Güçlü Yönler', blank=True)
    areas_for_improvement = models.TextField('Geliştirilmesi Gereken Alanlar', blank=True)
    goals = models.TextField('Hedefler', blank=True)
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Performans Değerlendirmesi'
        verbose_name_plural = 'Performans Değerlendirmeleri'
        ordering = ['-reviewed_at']
        indexes = [models.Index(fields=['hotel', 'staff', 'review_period_start'])]
    
    def __str__(self):
        return f"{self.staff.full_name} - {self.review_period_start} - {self.review_period_end}"


# ==================== MAAŞ YÖNETİMİ ====================

class SalaryRecord(TimeStampedModel, SoftDeleteModel):
    """Maaş Kaydı Modeli"""
    hotel = models.ForeignKey('hotels.Hotel', on_delete=models.CASCADE, related_name='salary_records', verbose_name='Otel')
    staff = models.ForeignKey(Staff, on_delete=models.CASCADE, related_name='salary_records', verbose_name='Personel')
    
    salary_month = models.DateField('Maaş Ayı', db_index=True)
    
    base_salary = models.DecimalField('Temel Maaş', max_digits=10, decimal_places=2, validators=[MinValueValidator(Decimal('0.00'))])
    overtime_hours = models.DecimalField('Mesai Saati', max_digits=6, decimal_places=2, default=Decimal('0.00'), validators=[MinValueValidator(Decimal('0.00'))])
    overtime_rate = models.DecimalField('Mesai Oranı', max_digits=5, decimal_places=2, default=Decimal('1.5'), validators=[MinValueValidator(Decimal('0.00'))])
    
    bonuses = models.DecimalField('Primler', max_digits=10, decimal_places=2, default=Decimal('0.00'), validators=[MinValueValidator(Decimal('0.00'))])
    deductions = models.DecimalField('Kesintiler', max_digits=10, decimal_places=2, default=Decimal('0.00'), validators=[MinValueValidator(Decimal('0.00'))])
    
    gross_salary = models.DecimalField('Brüt Maaş', max_digits=10, decimal_places=2, validators=[MinValueValidator(Decimal('0.00'))])
    net_salary = models.DecimalField('Net Maaş', max_digits=10, decimal_places=2, validators=[MinValueValidator(Decimal('0.00'))])
    
    currency = models.CharField('Para Birimi', max_length=3, default='TRY')
    
    paid = models.BooleanField('Ödendi mi?', default=False)
    paid_date = models.DateField('Ödeme Tarihi', null=True, blank=True)
    
    notes = models.TextField('Notlar', blank=True)
    
    class Meta:
        verbose_name = 'Maaş Kaydı'
        verbose_name_plural = 'Maaş Kayıtları'
        unique_together = ['hotel', 'staff', 'salary_month']
        ordering = ['-salary_month']
        indexes = [models.Index(fields=['hotel', 'salary_month', 'paid'])]
    
    def __str__(self):
        return f"{self.staff.full_name} - {self.salary_month.strftime('%Y-%m')} - {self.net_salary} {self.currency}"


# ==================== PERSONEL YÖNETİMİ AYARLARI ====================

class StaffSettings(TimeStampedModel):
    """Personel Yönetimi Ayarları"""
    hotel = models.OneToOneField('hotels.Hotel', on_delete=models.CASCADE, related_name='staff_settings', verbose_name='Otel')
    
    default_shift_duration = models.IntegerField('Varsayılan Vardiya Süresi (Saat)', default=8, validators=[MinValueValidator(1)])
    overtime_threshold = models.DecimalField('Mesai Eşiği (Saat)', max_digits=5, decimal_places=2, default=Decimal('40.00'))
    default_overtime_rate = models.DecimalField('Varsayılan Mesai Oranı', max_digits=5, decimal_places=2, default=Decimal('1.5'))
    
    annual_leave_days = models.IntegerField('Yıllık İzin Günü', default=14, validators=[MinValueValidator(0)])
    sick_leave_days = models.IntegerField('Hastalık İzni Günü', default=5, validators=[MinValueValidator(0)])
    
    class Meta:
        verbose_name = 'Personel Yönetimi Ayarları'
        verbose_name_plural = 'Personel Yönetimi Ayarları'
    
    def __str__(self):
        return f"{self.hotel.name} - Personel Yönetimi Ayarları"

