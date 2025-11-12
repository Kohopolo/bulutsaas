"""
Modül sistemi modelleri
Sistem genelinde kullanılabilir modüller
"""
from django.db import models
from apps.core.models import TimeStampedModel


class Module(TimeStampedModel):
    """
    Modül modeli
    Örnek: Rezervasyon, Housekeeping, Kanal Entegrasyonu
    """
    name = models.CharField('Modül Adı', max_length=100)
    code = models.SlugField('Modül Kodu', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, default='📦', help_text='Emoji veya Font Awesome class')
    
    # Kategorileme
    CATEGORY_CHOICES = [
        ('reservation', 'Rezervasyon'),
        ('housekeeping', 'Housekeeping'),
        ('channel', 'Kanal Entegrasyonu'),
        ('payment', 'Ödeme'),
        ('reporting', 'Raporlama'),
        ('other', 'Diğer'),
    ]
    category = models.CharField('Kategori', max_length=50, choices=CATEGORY_CHOICES, default='other')
    
    # Teknik Bilgiler
    app_name = models.CharField('Django App Adı', max_length=100, blank=True, help_text='apps.tenant_apps.reservations')
    url_prefix = models.CharField('URL Prefix', max_length=50, blank=True, help_text='/reservations/')
    
    # İzin Tanımları (JSON format)
    # Örnek: {"view": "Görüntüleme", "add": "Ekleme", "edit": "Düzenleme", "delete": "Silme"}
    available_permissions = models.JSONField('Mevcut İzinler', default=dict)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_core = models.BooleanField('Temel Modül mü?', default=False, help_text='Temel modüller tüm paketlerde zorunludur')
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar
    settings = models.JSONField('Ayarlar', default=dict, blank=True)

    class Meta:
        verbose_name = 'Modül'
        verbose_name_plural = 'Modüller'
        ordering = ['sort_order', 'name']

    def __str__(self):
        return f"{self.icon} {self.name}"

    def get_packages_count(self):
        """Bu modülü kullanan paket sayısı"""
        return self.packages.filter(is_active=True).count()
    get_packages_count.short_description = 'Paket Sayısı'



