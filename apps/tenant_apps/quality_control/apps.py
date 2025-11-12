"""
Kalite Kontrol App Config
"""
from django.apps import AppConfig


class QualityControlConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.quality_control'
    verbose_name = 'Kalite Kontrol'
    
    def ready(self):
        pass

