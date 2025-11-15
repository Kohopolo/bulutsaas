"""
Kanal Yönetimi Modelleri
Booking.com, Expedia vb. kanallar için yönetim
"""
from django.db import models
from apps.core.models import TimeStampedModel, SoftDeleteModel


class Channel(TimeStampedModel, SoftDeleteModel):
    """
    Rezervasyon Kanalı Modeli
    Booking.com, Expedia, Airbnb vb. kanallar
    """
    name = models.CharField('Kanal Adı', max_length=200)
    code = models.SlugField('Kanal Kodu', max_length=50, unique=True, db_index=True)
    description = models.TextField('Açıklama', blank=True)
    
    # API Ayarları (gelecekte kullanılabilir)
    api_key = models.CharField('API Key', max_length=200, blank=True)
    api_secret = models.CharField('API Secret', max_length=200, blank=True)
    api_url = models.URLField('API URL', blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    class Meta:
        verbose_name = 'Kanal'
        verbose_name_plural = 'Kanallar'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name
