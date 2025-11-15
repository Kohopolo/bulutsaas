"""
Ödeme Yönetimi Modülü Decorators
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from apps.tenant_apps.core.decorators import require_module_permission


def require_payment_management_permission(permission_code):
    """
    Ödeme yönetimi modülü yetki kontrolü
    """
    return require_module_permission('payment_management', permission_code)





