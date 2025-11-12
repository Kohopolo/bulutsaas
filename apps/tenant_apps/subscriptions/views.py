"""
Tenant Subscription Views
Kiracı üye paket yönetimi görünümleri
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.utils import timezone
from django.db import connection
from apps.subscriptions.models import Subscription, Payment
from apps.packages.models import Package, PackageModule
from apps.tenant_apps.core.models import TenantUser
from apps.tenant_apps.hotels.models import Hotel, Room


@login_required
def package_dashboard(request):
    """Paket yönetim ana sayfası"""
    # Mevcut tenant'ı al
    tenant = request.tenant
    
    # Aktif aboneliği al
    active_subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if not active_subscription:
        # Eğer aktif abonelik yoksa, en son aboneliği al
        active_subscription = Subscription.objects.filter(
            tenant=tenant
        ).order_by('-created_at').first()
    
    # Paket bilgileri
    package = active_subscription.package if active_subscription else tenant.package
    
    # Kullanım istatistikleri
    usage_stats = get_usage_statistics(tenant)
    
    # Paket limitleri - Modül bazlı limitlerden al
    limits = {}
    module_limits = {}
    
    if package:
        # Hotels modülü limitleri
        try:
            hotels_module = PackageModule.objects.filter(
                package=package,
                module__code='hotels',
                is_enabled=True
            ).first()
            if hotels_module and hotels_module.limits:
                module_limits['hotels'] = hotels_module.limits
                limits['max_hotels'] = hotels_module.limits.get('max_hotels', 0)
                limits['max_rooms'] = hotels_module.limits.get('max_room_numbers', 0)
                limits['max_reservations'] = hotels_module.limits.get('max_reservations', 0)
        except:
            pass
        
        # Tours modülü limitleri
        try:
            tours_module = PackageModule.objects.filter(
                package=package,
                module__code='tours',
                is_enabled=True
            ).first()
            if tours_module and tours_module.limits:
                module_limits['tours'] = tours_module.limits
                limits['max_tours'] = tours_module.limits.get('max_tours', 0)
        except:
            pass
    
    # Genel limitler (modül bazlı değilse varsayılan değerler)
    if 'max_hotels' not in limits:
        limits['max_hotels'] = 0
    if 'max_rooms' not in limits:
        limits['max_rooms'] = 0
    if 'max_users' not in limits:
        limits['max_users'] = 0
    if 'max_reservations_per_month' not in limits:
        limits['max_reservations_per_month'] = limits.get('max_reservations', 0)
    
    # Limit kullanım yüzdeleri
    usage_percentages = {}
    limit_mapping = {
        'max_hotels': 'current_hotels',
        'max_rooms': 'current_rooms',
        'max_users': 'current_users',
        'max_reservations_per_month': 'current_reservations_this_month',
        'max_tours': 'current_tours',
    }
    
    for key, limit in limits.items():
        usage_key = limit_mapping.get(key)
        if usage_key:
            current = usage_stats.get(usage_key, 0)
            percentage = (current / limit * 100) if limit > 0 else 0
            usage_percentages[key] = min(100, percentage)
    
    # Paket modülleri
    package_modules = []
    if package:
        package_modules = PackageModule.objects.filter(
            package=package,
            is_enabled=True
        ).select_related('module')
    
    context = {
        'tenant': tenant,
        'subscription': active_subscription,
        'package': package,
        'usage_stats': usage_stats,
        'limits': limits,
        'module_limits': module_limits,
        'usage_percentages': usage_percentages,
        'package_modules': package_modules,
    }
    
    return render(request, 'tenant/subscriptions/dashboard.html', context)


@login_required
def package_details(request):
    """Paket detay sayfası"""
    tenant = request.tenant
    
    # Aktif abonelik
    subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if not subscription:
        messages.error(request, 'Aktif abonelik bulunamadı.')
        return redirect('tenant_subscriptions:dashboard')
    
    package = subscription.package
    
    # Paket modülleri ve yetkileri
    package_modules = PackageModule.objects.filter(
        package=package,
        is_enabled=True
    ).select_related('module')
    
    # Modül bazlı limitler
    module_limits = {}
    for pm in package_modules:
        if pm.limits:
            module_limits[pm.module.code] = pm.limits
    
    # Ödeme geçmişi
    payments = Payment.objects.filter(
        subscription=subscription
    ).order_by('-created_at')[:10]
    
    context = {
        'subscription': subscription,
        'package': package,
        'package_modules': package_modules,
        'module_limits': module_limits,
        'payments': payments,
    }
    
    return render(request, 'tenant/subscriptions/details.html', context)


@login_required
def package_upgrade(request):
    """Paket yükseltme sayfası"""
    tenant = request.tenant
    
    # Mevcut paket
    current_subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    current_package = current_subscription.package if current_subscription else tenant.package
    
    # Tüm aktif paketler (mevcut paket hariç)
    available_packages = Package.objects.filter(
        is_active=True
    ).exclude(id=current_package.id if current_package else None).order_by('sort_order', 'price_monthly')
    
    # Her paket için modül limitlerini al (template'de kullanım için düzenli yapı)
    package_module_limits_list = []
    for package in available_packages:
        package_modules = PackageModule.objects.filter(
            package=package,
            is_enabled=True
        ).select_related('module')
        limits_dict = {}
        for pm in package_modules:
            if pm.limits:
                limits_dict[pm.module.code] = pm.limits
        package_module_limits_list.append({
            'package_id': package.id,
            'limits': limits_dict
        })
    
    # Mevcut paket için de modül limitlerini al
    current_package_limits = {}
    if current_package:
        current_package_modules = PackageModule.objects.filter(
            package=current_package,
            is_enabled=True
        ).select_related('module')
        for pm in current_package_modules:
            if pm.limits:
                current_package_limits[pm.module.code] = pm.limits
    
    # Paket ve limitleri eşleştir
    package_limits_map = {item['package_id']: item['limits'] for item in package_module_limits_list}
    
    context = {
        'current_package': current_package,
        'current_subscription': current_subscription,
        'available_packages': available_packages,
        'package_limits_map': package_limits_map,
        'current_package_limits': current_package_limits,
    }
    
    return render(request, 'tenant/subscriptions/upgrade.html', context)


@login_required
def package_renew(request):
    """Paket yenileme sayfası"""
    tenant = request.tenant
    
    # Aktif abonelik
    subscription = Subscription.objects.filter(
        tenant=tenant,
        status='active'
    ).first()
    
    if not subscription:
        messages.error(request, 'Aktif abonelik bulunamadı.')
        return redirect('tenant_subscriptions:dashboard')
    
    if request.method == 'POST':
        period = request.POST.get('period', 'monthly')
        
        # Aboneliği yenile
        subscription.renew(period=period)
        subscription.save()
        
        messages.success(request, f'Aboneliğiniz başarıyla yenilendi. ({subscription.get_period_display()})')
        return redirect('tenant_subscriptions:dashboard')
    
    context = {
        'subscription': subscription,
        'package': subscription.package,
    }
    
    return render(request, 'tenant/subscriptions/renew.html', context)


@login_required
def payment_history(request):
    """Ödeme geçmişi"""
    tenant = request.tenant
    
    # Tüm abonelikler
    subscriptions = Subscription.objects.filter(tenant=tenant).order_by('-created_at')
    
    # Tüm ödemeler
    payments = Payment.objects.filter(
        subscription__tenant=tenant
    ).select_related('subscription').order_by('-created_at')
    
    context = {
        'subscriptions': subscriptions,
        'payments': payments,
    }
    
    return render(request, 'tenant/subscriptions/payment_history.html', context)


def get_usage_statistics(tenant):
    """Tenant kullanım istatistiklerini hesapla"""
    stats = {
        'current_hotels': 0,
        'current_rooms': 0,
        'current_users': 0,
        'current_reservations_this_month': 0,
        'current_storage_gb': 0,
        'current_api_calls_today': 0,
        # Tur modülü istatistikleri
        'current_tours': 0,
        'current_tour_users': 0,
        'current_tour_reservations': 0,
        'current_tour_reservations_this_month': 0,
    }
    
    # Tenant schema'sında çalış
    with connection.cursor() as cursor:
        # Kullanıcı sayısı
        try:
            cursor.execute("SELECT COUNT(*) FROM tenant_core_tenantuser WHERE is_active = true")
            stats['current_users'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Otel sayısı (hotels app'ten) - silinmemiş oteller
        try:
            stats['current_hotels'] = Hotel.objects.filter(is_deleted=False).count()
        except Exception as e:
            # Fallback: SQL sorgusu
            try:
                cursor.execute("SELECT COUNT(*) FROM tenant_apps_hotels_hotel WHERE is_deleted = false")
                stats['current_hotels'] = cursor.fetchone()[0] or 0
            except:
                pass
        
        # Oda sayısı (hotels app'ten) - silinmemiş odalar
        try:
            stats['current_rooms'] = Room.objects.filter(is_deleted=False).count()
        except Exception as e:
            # Fallback: SQL sorgusu
            try:
                cursor.execute("SELECT COUNT(*) FROM tenant_apps_hotels_room WHERE is_deleted = false")
                stats['current_rooms'] = cursor.fetchone()[0] or 0
            except:
                pass
        
        # Bu ayki rezervasyon sayısı
        try:
            from datetime import datetime
            current_month = datetime.now().month
            cursor.execute(
                "SELECT COUNT(*) FROM tenant_apps_reservations_reservation WHERE EXTRACT(MONTH FROM created_at) = %s",
                [current_month]
            )
            stats['current_reservations_this_month'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Tur sayısı
        try:
            cursor.execute("SELECT COUNT(*) FROM tenant_apps_tours_tour WHERE is_active = true AND is_deleted = false")
            stats['current_tours'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Tur rezervasyon sayısı (toplam)
        try:
            cursor.execute("SELECT COUNT(*) FROM tenant_apps_tours_tourreservation WHERE is_deleted = false")
            stats['current_tour_reservations'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Bu ayki tur rezervasyon sayısı
        try:
            from datetime import datetime
            current_month = datetime.now().month
            current_year = datetime.now().year
            cursor.execute(
                "SELECT COUNT(*) FROM tenant_apps_tours_tourreservation WHERE EXTRACT(MONTH FROM created_at) = %s AND EXTRACT(YEAR FROM created_at) = %s AND is_deleted = false",
                [current_month, current_year]
            )
            stats['current_tour_reservations_this_month'] = cursor.fetchone()[0] or 0
        except:
            pass
        
        # Tur modülüne erişimi olan kullanıcı sayısı
        # (Bu karmaşık bir sorgu, Python tarafında hesaplamak daha iyi)
        try:
            # Tur modülünde yetkisi olan kullanıcılar
            from apps.modules.models import Module
            from apps.tenant_apps.core.models import Permission, RolePermission, UserRole
            
            tour_module = Module.objects.filter(code='tours', is_active=True).first()
            if tour_module:
                tour_permissions = Permission.objects.filter(
                    module=tour_module,
                    is_active=True
                )
                
                user_ids_with_permission = UserRole.objects.filter(
                    role__role_permissions__permission__in=tour_permissions,
                    is_active=True
                ).values_list('user__user__id', flat=True).distinct()
                
                stats['current_tour_users'] = len(set(user_ids_with_permission))
        except:
            pass
    
    return stats

