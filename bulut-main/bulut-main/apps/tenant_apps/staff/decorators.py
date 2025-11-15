"""
Personel Yönetimi Permission Decorators
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from django.contrib.auth.decorators import login_required
from apps.tenant_apps.core.decorators import require_module_permission


def require_staff_permission(permission_level='view'):
    """
    Personel Yönetimi modülü için yetki kontrolü decorator'ı
    
    Kullanım:
        @require_staff_permission('view')
        def my_view(request):
            ...
    
    Permission levels:
        - 'view': Görüntüleme yetkisi
        - 'add' veya 'manage': Ekleme/Düzenleme yetkisi
        - 'admin': Yönetim yetkisi
    """
    # Permission level mapping
    permission_map = {
        'view': 'view',
        'manage': 'add',
        'admin': 'admin'
    }
    
    permission_code = permission_map.get(permission_level, 'view')
    
    def decorator(view_func):
        @wraps(view_func)
        @login_required
        @require_module_permission('staff', permission_code)
        def _wrapped_view(request, *args, **kwargs):
            # Aktif otel kontrolü
            if not hasattr(request, 'active_hotel') or not request.active_hotel:
                messages.error(request, 'Aktif otel seçilmedi.')
                return redirect('hotels:select_hotel')
            
            return view_func(request, *args, **kwargs)
        
        return _wrapped_view
    return decorator
