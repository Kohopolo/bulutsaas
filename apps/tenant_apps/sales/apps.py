"""
Satış Yönetimi App Config
"""
from django.apps import AppConfig


class SalesConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.sales'
    verbose_name = 'Satış Yönetimi'
    
    def ready(self):
        pass

