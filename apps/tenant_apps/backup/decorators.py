"""
Yedekleme Modülü Decorators
"""
from functools import wraps
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from apps.tenant_apps.core.decorators import require_module_permission


def require_backup_permission(permission_type='view'):
    """
    Yedekleme modülü için yetki kontrolü decorator'ı
    
    Args:
        permission_type: 'view', 'add', 'change', 'delete'
    """
    def decorator(view_func):
        @wraps(view_func)
        @login_required
        @require_module_permission('backup', permission_type)
        def wrapped_view(request, *args, **kwargs):
            return view_func(request, *args, **kwargs)
        return wrapped_view
    return decorator

