from django.apps import AppConfig


class FinanceConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.finance'
    label = 'finance'
    verbose_name = 'Kasa Yönetimi'

