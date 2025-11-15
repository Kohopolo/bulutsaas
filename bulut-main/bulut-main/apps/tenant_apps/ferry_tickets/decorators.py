"""
Feribot Bileti Decorators
Feribot bileti modülü yetki kontrolü
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from apps.tenant_apps.core.decorators import require_module_permission


def require_ferry_ticket_permission(permission_level='view'):
    """
    Feribot bileti modülü yetki kontrolü decorator'ı
    Modül yetkisi kontrolü
    
    Kullanım:
    @require_ferry_ticket_permission('add')
    def my_view(request):
        ...
    
    Yetki Kontrolü:
    1. Kullanıcı giriş yapmış mı?
    2. Ferry Tickets modülü yetkisi var mı? (Module Permission)
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            # Modül yetkisi kontrolü
            return require_module_permission('ferry_tickets', permission_level)(view_func)(request, *args, **kwargs)
        return _wrapped_view
    return decorator

