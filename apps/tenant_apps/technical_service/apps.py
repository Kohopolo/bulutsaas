"""
Teknik Servis App Config
"""
from django.apps import AppConfig


class TechnicalServiceConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.technical_service'
    verbose_name = 'Teknik Servis'
    
    def ready(self):
        pass

