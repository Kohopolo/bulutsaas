from django.apps import AppConfig


class SubscriptionsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.subscriptions'
    label = 'tenant_subscriptions'
    verbose_name = 'Tenant Subscriptions'
