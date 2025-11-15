"""
Ödeme Yönetimi Modülü
"""
from django.apps import AppConfig


class PaymentManagementConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.tenant_apps.payment_management'
    verbose_name = 'Ödeme Yönetimi'





