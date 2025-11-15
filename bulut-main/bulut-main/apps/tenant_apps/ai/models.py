"""
Tenant AI Yönetim Modelleri
Her tenant için AI kredi takibi ve kullanım logları
"""
from django.db import models
from django.utils import timezone
from apps.core.models import TimeStampedModel


class TenantAICredit(TimeStampedModel):
    """
    Tenant AI Kredi Takibi
    Her tenant için AI kredi bakiyesi
    Not: Bu model tenant schema'da olduğu için tenant bilgisi connection.tenant'dan alınır
    """
    # Tenant bilgisi connection.tenant'dan alınır, burada saklamaya gerek yok
    # Ancak loglama için tenant_id saklanabilir
    tenant_id = models.IntegerField('Tenant ID', null=True, blank=True, help_text='Tenant ID (loglama için)')
    tenant_name = models.CharField('Tenant Adı', max_length=200, blank=True, help_text='Tenant adı (loglama için)')
    
    # Kredi Bilgileri
    total_credits = models.IntegerField('Toplam Kredi', default=0, help_text='Toplam kullanılabilir kredi')
    used_credits = models.IntegerField('Kullanılan Kredi', default=0, help_text='Kullanılan kredi miktarı')
    
    # Yenileme Bilgileri
    last_renewal_date = models.DateField('Son Yenileme Tarihi', null=True, blank=True)
    next_renewal_date = models.DateField('Sonraki Yenileme Tarihi', null=True, blank=True)
    renewal_type = models.CharField('Yenileme Tipi', max_length=20, 
                                    choices=[('monthly', 'Aylık'), ('yearly', 'Yıllık'), ('manual', 'Manuel')], 
                                    default='monthly')
    
    # Manuel Kredi Bilgileri
    manual_credits = models.IntegerField('Manuel Eklenen Kredi', default=0, 
                                        help_text='Paket dışında manuel olarak eklenen kredi')
    
    class Meta:
        verbose_name = 'Tenant AI Kredi'
        verbose_name_plural = 'Tenant AI Kredileri'
    
    def __str__(self):
        tenant_name = self.tenant_name or f"Tenant-{self.tenant_id}" if self.tenant_id else "Unknown"
        return f"{tenant_name} - {self.remaining_credits} kredi"
    
    @property
    def remaining_credits(self):
        """Kalan kredi"""
        return max(0, self.total_credits - self.used_credits)
    
    @property
    def is_credit_available(self):
        """Kredi var mı?"""
        return self.remaining_credits > 0
    
    def use_credit(self, amount):
        """Kredi kullan"""
        if self.remaining_credits < amount:
            raise ValueError(f'Yetersiz kredi. Kalan: {self.remaining_credits}, İstenen: {amount}')
        self.used_credits += amount
        self.save(update_fields=['used_credits', 'updated_at'])
    
    def add_credit(self, amount, is_manual=False):
        """Kredi ekle"""
        self.total_credits += amount
        if is_manual:
            self.manual_credits += amount
        self.save(update_fields=['total_credits', 'manual_credits', 'updated_at'])
    
    def renew_credits(self, amount):
        """Kredileri yenile (aylık/yıllık)"""
        self.total_credits = amount
        self.used_credits = 0
        self.last_renewal_date = timezone.now().date()
        
        if self.renewal_type == 'monthly':
            from dateutil.relativedelta import relativedelta
            self.next_renewal_date = timezone.now().date() + relativedelta(months=1)
        elif self.renewal_type == 'yearly':
            from dateutil.relativedelta import relativedelta
            self.next_renewal_date = timezone.now().date() + relativedelta(years=1)
        
        self.save()


class TenantAIUsage(TimeStampedModel):
    """
    Tenant AI Kullanım Logları
    Her AI kullanımı loglanır
    Not: Bu model tenant schema'da olduğu için tenant bilgisi connection.tenant'dan alınır
    """
    # Tenant bilgisi (loglama için)
    tenant_id = models.IntegerField('Tenant ID', null=True, blank=True)
    tenant_name = models.CharField('Tenant Adı', max_length=200, blank=True)
    
    # AI Bilgileri (shared schema'da olduğu için string reference)
    ai_provider_name = models.CharField('AI Sağlayıcı Adı', max_length=100, blank=True)
    ai_model_name = models.CharField('AI Model Adı', max_length=100, blank=True)
    ai_provider_code = models.CharField('AI Sağlayıcı Kodu', max_length=50, blank=True)
    ai_model_code = models.CharField('AI Model Kodu', max_length=50, blank=True)
    
    # Kullanım Bilgileri
    usage_type = models.CharField('Kullanım Tipi', max_length=50, 
                                  choices=[
                                      ('tour_program', 'Tur Programı Oluşturma'),
                                      ('tour_description', 'Tur Açıklaması Oluşturma'),
                                      ('form_fill', 'Form Doldurma'),
                                      ('website_generate', 'Web Sitesi Oluşturma'),
                                      ('other', 'Diğer'),
                                  ],
                                  default='other')
    prompt = models.TextField('Prompt', blank=True, help_text='Kullanıcının gönderdiği prompt')
    response = models.TextField('Yanıt', blank=True, help_text='AI\'dan gelen yanıt')
    
    # Kredi Bilgileri
    credit_used = models.DecimalField('Kullanılan Kredi', max_digits=10, decimal_places=2, default=1.0)
    
    # Durum
    STATUS_CHOICES = [
        ('success', 'Başarılı'),
        ('failed', 'Başarısız'),
        ('error', 'Hata'),
    ]
    status = models.CharField('Durum', max_length=20, choices=STATUS_CHOICES, default='success')
    error_message = models.TextField('Hata Mesajı', blank=True)
    
    # Kullanıcı Bilgisi
    user = models.ForeignKey('auth.User', on_delete=models.SET_NULL, null=True, blank=True, 
                            related_name='ai_usage_logs', verbose_name='Kullanıcı')
    
    # Ek Bilgiler (JSON)
    metadata = models.JSONField('Ek Bilgiler', default=dict, blank=True, 
                               help_text='Token sayısı, süre vb. ek bilgiler')
    
    class Meta:
        verbose_name = 'AI Kullanım Logu'
        verbose_name_plural = 'AI Kullanım Logları'
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['tenant_id', '-created_at']),
            models.Index(fields=['tenant_id', 'usage_type']),
        ]
    
    def __str__(self):
        tenant_name = self.tenant_name or f"Tenant-{self.tenant_id}" if self.tenant_id else "Unknown"
        return f"{tenant_name} - {self.get_usage_type_display()} - {self.created_at.strftime('%d.%m.%Y %H:%M')}"

