"""
Personel Yönetimi App Config
"""
from django.apps import AppConfig


class StaffConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.staff'
    verbose_name = 'Personel Yönetimi'
    
    def ready(self):
        pass

