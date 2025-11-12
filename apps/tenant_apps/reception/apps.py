"""
Resepsiyon (Ön Büro) App Config
"""
from django.apps import AppConfig


class ReceptionConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.reception'
    verbose_name = 'Resepsiyon (Ön Büro)'
    
    def ready(self):
        """Signals'ı yükle"""
        import apps.tenant_apps.reception.signals  # noqa

