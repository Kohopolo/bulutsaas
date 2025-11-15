"""
Kanal Yönetimi Decorator'ları
Yetki kontrolü için decorator'lar
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from apps.tenant_apps.core.decorators import require_module_permission


def require_channel_management_permission(permission_type='view'):
    """
    Kanal Yönetimi modülü için yetki kontrolü decorator'ı
    
    Kullanım:
        @require_channel_management_permission('view')
        def channel_list(request):
            ...
    """
    return require_module_permission('channel_management', permission_type)





