"""
Ayarlar Modülü Decorators
Yetki kontrolü için decorator'lar
"""
from apps.tenant_apps.core.decorators import require_module_permission


def require_settings_permission(permission_type='view'):
    """
    Ayarlar modülü için yetki kontrolü decorator'ı
    
    Kullanım:
        @require_settings_permission('view')
        def settings_list(request):
            ...
    
    Args:
        permission_type: 'view', 'add', 'change', 'delete'
    """
    return require_module_permission('settings', permission_type)

