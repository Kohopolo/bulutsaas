"""
Ferry Tickets App Configuration
"""
from django.apps import AppConfig


class FerryTicketsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.ferry_tickets'
    verbose_name = 'Feribot Bileti'
    
    def ready(self):
        """Signals'ları yükle"""
        try:
            import apps.tenant_apps.ferry_tickets.signals  # noqa
        except ImportError:
            pass





