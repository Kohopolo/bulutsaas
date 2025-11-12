"""
Reception Decorators
"""
from functools import wraps
from django.shortcuts import redirect
from django.contrib import messages


def require_reception_permission(permission_level='view'):
    """
    Resepsiyon modülü yetki kontrolü decorator'ı
    
    Kullanım:
    @require_reception_permission('add')
    def my_view(request):
        ...
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            if not request.user.is_authenticated:
                return redirect('tenant:login')
            
            # Yetki kontrolü burada yapılabilir
            # Şimdilik sadece login kontrolü yapıyoruz
            return view_func(request, *args, **kwargs)
        return _wrapped_view
    return decorator

