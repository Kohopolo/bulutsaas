"""
Reception App Configuration
"""
from django.apps import AppConfig


class ReceptionConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.reception'
    verbose_name = 'Resepsiyon (Ön Büro)'
    
    def ready(self):
        """Signal'ları import et"""
        try:
            import apps.tenant_apps.reception.signals  # noqa
        except ImportError:
            pass

