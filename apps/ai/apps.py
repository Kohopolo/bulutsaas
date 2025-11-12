from django.apps import AppConfig


class AiConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.ai'
    label = 'ai_shared'  # Shared schema için
    verbose_name = 'AI Yönetimi'

