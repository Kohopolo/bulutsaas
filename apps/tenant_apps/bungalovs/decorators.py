"""
Bungalov Modülü Decorators
Yetki kontrolü için decorator'lar
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from apps.tenant_apps.core.decorators import require_module_permission


def require_bungalov_permission(permission_name):
    """
    Bungalov modülü için yetki kontrolü decorator'ı
    
    Kullanım:
        @require_bungalov_permission('view')
        def my_view(request):
            ...
    """
    return require_module_permission('bungalovs', permission_name)

