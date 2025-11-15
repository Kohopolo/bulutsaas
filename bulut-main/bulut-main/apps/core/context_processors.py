"""
Context processors - Tüm template'lerde kullanılabilir değişkenler
"""
import django


def site_settings(request):
    """
    Site ayarlarını tüm template'lere gönder
    """
    from django.conf import settings
    
    return {
        'SITE_NAME': getattr(settings, 'SITE_NAME', 'SaaS 2026'),
        'SITE_URL': getattr(settings, 'SITE_URL', ''),
        'DEBUG': settings.DEBUG,
        'django_version': django.get_version(),
    }



