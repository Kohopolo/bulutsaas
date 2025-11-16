"""
Raporlar Modülü Decorators
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from apps.tenant_apps.core.decorators import require_module_permission


def require_reports_permission(permission='view'):
    """
    Raporlar modülü için yetki kontrolü decorator'ı
    """
    return require_module_permission('reports', permission)

