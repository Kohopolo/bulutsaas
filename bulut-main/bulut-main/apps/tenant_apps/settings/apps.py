"""
Settings App Configuration
"""
from django.apps import AppConfig


class SettingsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.settings'
    verbose_name = 'Ayarlar'

