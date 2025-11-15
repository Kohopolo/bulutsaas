"""
Kat Hizmetleri App Config
"""
from django.apps import AppConfig


class HousekeepingConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.housekeeping'
    verbose_name = 'Kat Hizmetleri (Housekeeping)'
    
    def ready(self):
        """Signals'ı yükle (gerekirse)"""
        pass
