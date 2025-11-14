"""
Tenant Context Processors
Template'lerde kullanılacak context değişkenleri
"""
from apps.subscriptions.models import Subscription
from apps.packages.models import PackageModule
from apps.modules.models import Module
from apps.tenant_apps.core.models import TenantUser
from django.db import connection
from django.utils import timezone
from django_tenants.utils import get_public_schema_name


def tenant_modules(request):
    """
    Tenant'ın paketinde aktif olan modülleri ve kullanıcı yetkilerini context'e ekle
    """
    enabled_modules = []
    user_accessible_modules = []  # Kullanıcının yetkisi olan modüller
    
    if hasattr(request, 'tenant') and request.tenant:
        tenant = request.tenant
        
        # Public schema'da değilse
        if connection.schema_name != get_public_schema_name():
            try:
                # Aktif aboneliği al
                active_subscription = Subscription.objects.filter(
                    tenant=tenant,
                    status='active',
                    end_date__gte=timezone.now().date()
                ).first()
                
                if active_subscription:
                    package = active_subscription.package
                    
                    # Pakette aktif olan modülleri al
                    package_modules = PackageModule.objects.filter(
                        package=package,
                        is_enabled=True
                    ).select_related('module').order_by('module__sort_order', 'module__name')
                    
                    for pm in package_modules:
                        enabled_modules.append({
                            'code': pm.module.code,
                            'name': pm.module.name,
                            'icon': pm.module.icon,
                            'url_prefix': pm.module.url_prefix,
                        })
                
                # Kullanıcı giriş yapmışsa, yetkisi olan modülleri kontrol et
                if request.user.is_authenticated:
                    try:
                        tenant_user = TenantUser.objects.get(user=request.user, is_active=True)
                        
                        # Paketteki her modül için kullanıcının 'view' yetkisi var mı kontrol et
                        for module_info in enabled_modules:
                            if tenant_user.has_module_permission(module_info['code'], 'view'):
                                user_accessible_modules.append(module_info['code'])
                    except TenantUser.DoesNotExist:
                        pass
                        
            except Exception as e:
                import logging
                logger = logging.getLogger(__name__)
                logger.error(f"Tenant modülleri yüklenirken hata oluştu ({connection.schema_name}): {e}")
    
    # Modül kodları listesi (geriye dönük uyumluluk için)
    enabled_module_codes = [m['code'] if isinstance(m, dict) else m for m in enabled_modules]
    
    # Core modüller her zaman aktif (paket kontrolü olmadan)
    # Kullanıcı, Rol ve Yetki yönetimi core modüller olduğu için her zaman görünür
    core_modules = ['users', 'roles', 'permissions', 'customers']
    for core_code in core_modules:
        if core_code not in enabled_module_codes:
            enabled_module_codes.append(core_code)
            user_accessible_modules.append(core_code)  # Core modüller her zaman erişilebilir
            # Modül bilgisini de ekle
            try:
                core_module = Module.objects.filter(code=core_code).first()
                if core_module:
                    enabled_modules.append({
                        'code': core_module.code,
                        'name': core_module.name,
                        'icon': core_module.icon,
                        'url_prefix': core_module.url_prefix,
                    })
            except:
                pass
    
    # Kullanıcı giriş yapmamışsa, tüm paket modüllerini erişilebilir olarak işaretle
    if not request.user.is_authenticated:
        user_accessible_modules = enabled_module_codes.copy()
    
    return {
        'enabled_modules': enabled_modules,
        'enabled_module_codes': enabled_module_codes,
        'user_accessible_modules': user_accessible_modules,  # Kullanıcının yetkisi olan modüller
        'has_tour_module': 'tours' in enabled_module_codes and 'tours' in user_accessible_modules,
        'has_hotel_module': 'hotels' in enabled_module_codes and 'hotels' in user_accessible_modules,
        'has_reception_module': 'reception' in enabled_module_codes and 'reception' in user_accessible_modules,
        'has_housekeeping_module': 'housekeeping' in enabled_module_codes and 'housekeeping' in user_accessible_modules,
        'has_technical_service_module': 'technical_service' in enabled_module_codes and 'technical_service' in user_accessible_modules,
        'has_quality_control_module': 'quality_control' in enabled_module_codes and 'quality_control' in user_accessible_modules,
        'has_sales_module': 'sales' in enabled_module_codes and 'sales' in user_accessible_modules,
        'has_staff_module': 'staff' in enabled_module_codes and 'staff' in user_accessible_modules,
        'has_finance_module': 'finance' in enabled_module_codes and 'finance' in user_accessible_modules,
        'has_accounting_module': 'accounting' in enabled_module_codes and 'accounting' in user_accessible_modules,
        'has_refunds_module': 'refunds' in enabled_module_codes and 'refunds' in user_accessible_modules,
        'has_payment_management_module': 'payment_management' in enabled_module_codes and 'payment_management' in user_accessible_modules,
        'has_channel_management_module': 'channel_management' in enabled_module_codes and 'channel_management' in user_accessible_modules,
        'has_ferry_tickets_module': 'ferry_tickets' in enabled_module_codes and 'ferry_tickets' in user_accessible_modules,
        'has_bungalovs_module': 'bungalovs' in enabled_module_codes and 'bungalovs' in user_accessible_modules,
        'has_backup_module': 'backup' in enabled_module_codes and 'backup' in user_accessible_modules,
        'has_users_module': True,  # Core modül, her zaman aktif
        'has_roles_module': True,  # Core modül, her zaman aktif
        'has_permissions_module': True,  # Core modül, her zaman aktif
        'has_customers_module': True,  # Core modül, her zaman aktif
    }
