from django.apps import AppConfig


class TenantAiConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.ai'
    label = 'ai_tenant'  # Tenant schema için
    verbose_name = 'Tenant AI Yönetimi'

