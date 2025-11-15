"""
Paket yönetim modelleri
SaaS Super Admin tarafından paketler oluşturulur
"""
from django.db import models
from apps.core.models import TimeStampedModel, SoftDeleteModel


class Package(TimeStampedModel, SoftDeleteModel):
    """
    Paket modeli
    Tenant'lar bu paketlere abone olur
    """
    # Temel Bilgiler
    name = models.CharField('Paket Adı', max_length=100)
    code = models.SlugField('Paket Kodu', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    
    # Fiyatlandırma
    CURRENCY_CHOICES = [
        ('TRY', 'Türk Lirası'),
        ('USD', 'US Dollar'),
        ('EUR', 'Euro'),
    ]
    price_monthly = models.DecimalField('Aylık Fiyat', max_digits=10, decimal_places=2)
    price_yearly = models.DecimalField('Yıllık Fiyat', max_digits=10, decimal_places=2, null=True, blank=True)
    currency = models.CharField('Para Birimi', max_length=3, choices=CURRENCY_CHOICES, default='TRY')
    
    # NOT: Limitler artık PackageModule.limits JSON field'ında modül bazlı olarak tanımlanıyor
    # Eski genel limitler kaldırıldı: max_hotels, max_rooms, max_users, max_reservations_per_month, 
    # max_storage_gb, max_api_calls_per_day
    
    # Özellikler
    trial_days = models.IntegerField('Deneme Süresi (Gün)', default=14)
    is_featured = models.BooleanField('Öne Çıkan mı?', default=False)
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Modüller (ManyToMany)
    modules = models.ManyToManyField(
        'modules.Module',
        through='PackageModule',
        related_name='packages',
        verbose_name='Modüller'
    )
    
    # Ayarlar
    settings = models.JSONField('Ek Ayarlar', default=dict, blank=True)

    class Meta:
        verbose_name = 'Paket'
        verbose_name_plural = 'Paketler'
        ordering = ['sort_order', 'name']

    def __str__(self):
        return f"{self.name} ({self.code})"

    def get_modules_count(self):
        """Bu paketteki modül sayısı"""
        return self.modules.count()
    get_modules_count.short_description = 'Modül Sayısı'

    def get_tenants_count(self):
        """Bu paketi kullanan tenant sayısı"""
        return self.tenants.filter(is_active=True).count()
    get_tenants_count.short_description = 'Aktif Üye Sayısı'

    def get_yearly_discount_percentage(self):
        """Yıllık abonelikte indirim yüzdesi"""
        if self.price_yearly and self.price_monthly:
            monthly_total = self.price_monthly * 12
            discount = ((monthly_total - self.price_yearly) / monthly_total) * 100
            return round(discount, 2)
        return 0


class PackageModule(TimeStampedModel):
    """
    Paket-Modül ilişkisi
    Her modül için paket bazında yetki ayarları
    """
    package = models.ForeignKey(
        Package,
        on_delete=models.CASCADE,
        related_name='package_modules',
        verbose_name='Paket'
    )
    module = models.ForeignKey(
        'modules.Module',
        on_delete=models.CASCADE,
        related_name='module_packages',
        verbose_name='Modül'
    )
    
    # Modül Yetkileri (JSON format)
    # Örnek: {"view": true, "add": true, "edit": false, "delete": false}
    permissions = models.JSONField('Yetkiler', default=dict)
    
    # Modül Limitleri
    limits = models.JSONField('Limitler', default=dict, blank=True)
    
    # Durum
    is_enabled = models.BooleanField('Aktif mi?', default=True)
    is_required = models.BooleanField('Zorunlu mu?', default=False)

    class Meta:
        verbose_name = 'Paket Modülü'
        verbose_name_plural = 'Paket Modülleri'
        unique_together = ('package', 'module')
        ordering = ['package', 'module']

    def __str__(self):
        return f"{self.package.name} - {self.module.name}"



