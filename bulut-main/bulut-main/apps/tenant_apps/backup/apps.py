from django.apps import AppConfig


class BackupConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.backup'
    verbose_name = 'Yedekleme Yönetimi'

