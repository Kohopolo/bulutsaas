"""
Kanal Yönetimi Modülü App Config
"""
from django.apps import AppConfig


class ChannelManagementConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.channel_management'
    verbose_name = 'Kanal Yönetimi'
    
    def ready(self):
        # Signal'ları yükle
        try:
            import apps.tenant_apps.channel_management.signals  # noqa
        except ImportError:
            pass

