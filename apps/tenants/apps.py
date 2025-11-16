from django.apps import AppConfig


class TenantsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenants'
    verbose_name = 'Üye Yönetimi (Tenants)'
    
    def ready(self):
        """Signal'ları yükle"""
        import apps.tenants.signals  # noqa



