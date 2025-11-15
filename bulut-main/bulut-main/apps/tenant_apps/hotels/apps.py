"""
Otel Yönetimi App Config
"""
from django.apps import AppConfig


class HotelsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.hotels'
    verbose_name = 'Otel Yönetimi'
    
    def ready(self):
        """Signals'ı yükle"""
        # Rezervasyon modelleri oluşturulduğunda aktif hale getirilecek
        # import apps.tenant_apps.hotels.signals
        pass
