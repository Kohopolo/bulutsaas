"""
Fiyat Hesaplama Modülü Decorators
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages
from django.http import JsonResponse
from apps.tenant_apps.core.utils import is_module_enabled, has_module_permission


def require_price_calculator_permission(permission='view'):
    """
    Fiyat Hesaplama modülü için yetki kontrolü decorator'ı
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            # Modül aktif mi kontrol et
            if not is_module_enabled(request.tenant, 'price_calculator'):
                if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                    return JsonResponse({
                        'error': 'Fiyat Hesaplama modülü paketinizde aktif değil.'
                    }, status=403)
                messages.error(request, 'Fiyat Hesaplama modülü paketinizde aktif değil.')
                return redirect('tenant:dashboard')
            
            # Kullanıcının yetkisi var mı kontrol et
            if not has_module_permission(request.user, 'price_calculator', permission):
                if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
                    return JsonResponse({
                        'error': f'Fiyat Hesaplama modülü için {permission} yetkiniz bulunmamaktadır.'
                    }, status=403)
                messages.error(request, f'Fiyat Hesaplama modülü için {permission} yetkiniz bulunmamaktadır.')
                return redirect('tenant:dashboard')
            
            return view_func(request, *args, **kwargs)
        return _wrapped_view
    return decorator

