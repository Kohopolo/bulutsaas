"""
Tenant modelleri - Multi-tenancy için
Her tenant ayrı PostgreSQL schema'sında çalışır
"""
from django.db import models
from django_tenants.models import TenantMixin, DomainMixin
from apps.core.models import TimeStampedModel


class Tenant(TenantMixin, TimeStampedModel):
    """
    Tenant (Üye) modeli
    Her üye için ayrı schema oluşturulur
    """
    name = models.CharField('İşletme Adı', max_length=200)
    slug = models.SlugField('Slug', max_length=100, unique=True)
    
    # İletişim
    owner_name = models.CharField('Sahip Adı', max_length=100)
    owner_email = models.EmailField('Sahip E-posta')
    phone = models.CharField('Telefon', max_length=20, blank=True)
    
    # Adres
    address = models.TextField('Adres', blank=True)
    city = models.CharField('Şehir', max_length=100, blank=True)
    country = models.CharField('Ülke', max_length=100, default='Türkiye')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_trial = models.BooleanField('Deneme mi?', default=True)
    trial_end_date = models.DateField('Deneme Bitiş Tarihi', null=True, blank=True)
    
    # Paket
    package = models.ForeignKey(
        'packages.Package',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='tenants',
        verbose_name='Paket'
    )
    
    # Ayarlar
    settings = models.JSONField('Ayarlar', default=dict, blank=True)
    
    # Auto-drop schema on delete
    auto_drop_schema = True
    auto_create_schema = True

    class Meta:
        verbose_name = 'Üye (Tenant)'
        verbose_name_plural = 'Üyeler (Tenants)'
        ordering = ['-created_at']

    def __str__(self):
        return self.name

    def get_total_users(self):
        """Bu tenant'ın toplam kullanıcı sayısı"""
        # Tenant schema'sında kullanıcıları say
        return 0  # TODO: Implement

    def is_subscription_active(self):
        """Abonelik aktif mi?"""
        if not self.is_active:
            return False
        
        # Abonelik kontrolü
        from apps.subscriptions.models import Subscription
        return Subscription.objects.filter(
            tenant=self,
            status='active'
        ).exists()


class Domain(DomainMixin):
    """
    Domain modeli
    Her tenant için birden fazla domain olabilir
    """
    tenant = models.ForeignKey(
        Tenant,
        on_delete=models.CASCADE,
        related_name='domains',
        verbose_name='Tenant'
    )
    
    # Domain tipi
    DOMAIN_TYPE_CHOICES = [
        ('primary', 'Ana Domain'),
        ('custom', 'Özel Domain'),
        ('subdomain', 'Alt Domain'),
    ]
    domain_type = models.CharField(
        'Domain Tipi',
        max_length=20,
        choices=DOMAIN_TYPE_CHOICES,
        default='subdomain'
    )
    
    # SSL
    ssl_enabled = models.BooleanField('SSL Aktif mi?', default=False)
    ssl_certificate = models.TextField('SSL Sertifikası', blank=True)

    class Meta:
        verbose_name = 'Domain'
        verbose_name_plural = 'Domainler'
        ordering = ['domain']

    def __str__(self):
        return f"{self.domain} ({self.get_domain_type_display()})"



