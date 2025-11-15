from django.apps import AppConfig


class ToursConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.tours'
    label = 'tours'
    verbose_name = 'Tur Yönetimi'
    
    def ready(self):
        """Signals'ı yükle"""
        import apps.tenant_apps.tours.signals


