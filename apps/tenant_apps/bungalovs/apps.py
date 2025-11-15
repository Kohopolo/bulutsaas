"""
Bungalov Yönetimi App Config
"""
from django.apps import AppConfig


class BungalovsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.bungalovs'
    verbose_name = 'Bungalov Yönetimi'

    def ready(self):
        """Modül hazır olduğunda signals'ı yükle"""
        try:
            import apps.tenant_apps.bungalovs.signals
        except ImportError:
            pass





