from django.apps import AppConfig


class CoreConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.core'
    label = 'tenant_core'  # Farklı label kullan
    verbose_name = 'Tenant Core'

