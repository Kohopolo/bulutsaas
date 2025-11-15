"""
AI Yönetim Modelleri
Super Admin tarafından AI sağlayıcıları ve modelleri yönetilir
"""
from django.db import models
from django.core.exceptions import ValidationError
from apps.core.models import TimeStampedModel, SoftDeleteModel
from django.conf import settings
import base64
import os

try:
    from cryptography.fernet import Fernet
    CRYPTOGRAPHY_AVAILABLE = True
except ImportError:
    CRYPTOGRAPHY_AVAILABLE = False


def get_encryption_key():
    """Şifreleme anahtarı al (settings'den veya environment'tan)"""
    key = getattr(settings, 'AI_ENCRYPTION_KEY', None)
    if not key:
        # Environment'tan al
        key = os.environ.get('AI_ENCRYPTION_KEY')
        if not key:
            # Varsayılan key (production'da mutlaka değiştirilmeli!)
            key = settings.SECRET_KEY[:32].encode()
            key = base64.urlsafe_b64encode(key)
    if isinstance(key, str):
        key = key.encode()
    return key


def encrypt_api_key(api_key):
    """API key'i şifrele"""
    if not api_key or not CRYPTOGRAPHY_AVAILABLE:
        return None
    try:
        f = Fernet(get_encryption_key())
        return f.encrypt(api_key.encode()).decode()
    except Exception:
        return None


def decrypt_api_key(encrypted_key):
    """API key'i çöz"""
    if not encrypted_key or not CRYPTOGRAPHY_AVAILABLE:
        return None
    try:
        f = Fernet(get_encryption_key())
        return f.decrypt(encrypted_key.encode()).decode()
    except Exception:
        return None


class AIProvider(TimeStampedModel, SoftDeleteModel):
    """
    AI Sağlayıcı Modeli
    OpenAI, Anthropic, Google, Cursor vb.
    """
    PROVIDER_CHOICES = [
        ('openai', 'OpenAI (ChatGPT)'),
        ('anthropic', 'Anthropic (Claude)'),
        ('google', 'Google (Gemini)'),
        ('cursor', 'Cursor'),
        ('other', 'Diğer'),
    ]
    
    name = models.CharField('Sağlayıcı Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    provider_type = models.CharField('Sağlayıcı Tipi', max_length=50, choices=PROVIDER_CHOICES)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, default='🤖', help_text='Emoji veya Font Awesome class')
    
    # API Ayarları
    api_base_url = models.URLField('API Base URL', blank=True, help_text='API endpoint URL')
    api_key_encrypted = models.TextField('API Key (Şifreli)', blank=True, help_text='Ana API key şifreli olarak saklanır')
    api_key_label = models.CharField('API Key Etiketi', max_length=100, default='API Key', help_text='Formda gösterilecek etiket')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar (JSON)
    settings = models.JSONField('Ayarlar', default=dict, blank=True, help_text='Ek ayarlar (timeout, retry vb.)')
    
    class Meta:
        verbose_name = 'AI Sağlayıcı'
        verbose_name_plural = 'AI Sağlayıcıları'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return self.name
    
    def set_api_key(self, api_key):
        """API key'i şifreleyerek kaydet"""
        self.api_key_encrypted = encrypt_api_key(api_key)
        self.save()
    
    def get_api_key(self):
        """API key'i çözerek döndür"""
        return decrypt_api_key(self.api_key_encrypted)
    
    @property
    def has_api_key(self):
        """API key var mı?"""
        return bool(self.api_key_encrypted)


class AIModel(TimeStampedModel, SoftDeleteModel):
    """
    AI Model Modeli
    GPT-4, Claude-3, Gemini-Pro vb.
    """
    provider = models.ForeignKey(AIProvider, on_delete=models.CASCADE, related_name='models', verbose_name='Sağlayıcı')
    name = models.CharField('Model Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50)
    model_id = models.CharField('Model ID', max_length=100, help_text='API\'de kullanılan model ID (örn: gpt-4, claude-3-opus)')
    description = models.TextField('Açıklama', blank=True)
    
    # Kredi Ayarları
    credit_cost = models.DecimalField('Kredi Maliyeti', max_digits=10, decimal_places=2, default=1.0, 
                                     help_text='Bu modeli kullanmak için düşülecek kredi miktarı')
    
    # Model Özellikleri
    max_tokens = models.IntegerField('Maksimum Token', null=True, blank=True, help_text='Maksimum token sayısı')
    supports_streaming = models.BooleanField('Streaming Desteği', default=True)
    supports_function_calling = models.BooleanField('Function Calling Desteği', default=False)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_default = models.BooleanField('Varsayılan Model mi?', default=False, help_text='Bu sağlayıcı için varsayılan model')
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar (JSON)
    settings = models.JSONField('Ayarlar', default=dict, blank=True, help_text='Model özel ayarları')
    
    class Meta:
        verbose_name = 'AI Model'
        verbose_name_plural = 'AI Modelleri'
        ordering = ['provider', 'sort_order', 'name']
        unique_together = [['provider', 'code']]
    
    def __str__(self):
        return f"{self.provider.name} - {self.name}"
    
    def clean(self):
        """Model doğrulama"""
        if self.is_default:
            # Aynı sağlayıcıda sadece bir varsayılan model olabilir
            existing_default = AIModel.objects.filter(
                provider=self.provider,
                is_default=True,
                is_deleted=False
            ).exclude(pk=self.pk if self.pk else None)
            if existing_default.exists():
                raise ValidationError('Bu sağlayıcı için zaten bir varsayılan model tanımlı.')


class PackageAI(TimeStampedModel):
    """
    Paket-AI İlişkisi
    Hangi pakette hangi AI'lar kullanılabilir
    """
    package = models.ForeignKey('packages.Package', on_delete=models.CASCADE, related_name='ai_configs', verbose_name='Paket')
    ai_provider = models.ForeignKey(AIProvider, on_delete=models.CASCADE, related_name='package_configs', verbose_name='AI Sağlayıcı')
    ai_model = models.ForeignKey(AIModel, on_delete=models.CASCADE, related_name='package_configs', verbose_name='AI Model')
    
    # Kredi Ayarları
    monthly_credit_limit = models.IntegerField('Aylık Kredi Limiti', default=1000, 
                                               help_text='Paket ile birlikte verilen aylık kredi miktarı (-1 = sınırsız)')
    credit_renewal_type = models.CharField('Kredi Yenileme Tipi', max_length=20, 
                                          choices=[('monthly', 'Aylık'), ('yearly', 'Yıllık')], 
                                          default='monthly')
    
    # Durum
    is_enabled = models.BooleanField('Aktif mi?', default=True)
    
    class Meta:
        verbose_name = 'Paket AI Yapılandırması'
        verbose_name_plural = 'Paket AI Yapılandırmaları'
        unique_together = [['package', 'ai_provider', 'ai_model']]
        ordering = ['package', 'ai_provider', 'ai_model']
    
    def __str__(self):
        return f"{self.package.name} - {self.ai_provider.name} ({self.ai_model.name})"

