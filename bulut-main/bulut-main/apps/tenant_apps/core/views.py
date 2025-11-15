"""
Tenant Admin Panel Views
Kiracı üye paneli için login, logout ve dashboard
"""
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.contrib import messages
from django.views.decorators.http import require_http_methods
from django.utils import timezone
from django.db.models import Count, Sum, Q
from django.db import connection
from django.core.paginator import Paginator
from django.http import JsonResponse
import json
from decimal import Decimal
from .models import TenantUser, UserType, Role, Permission, UserRole, RolePermission, UserPermission, Customer, CustomerLoyaltyHistory, CustomerNote
from .forms import (
    TenantUserForm, UserTypeForm, RoleForm, PermissionForm,
    UserRoleForm, RolePermissionForm, UserPermissionForm
)
from apps.subscriptions.models import Subscription
from apps.packages.models import PackageModule
from apps.modules.models import Module
from .decorators import require_role, require_module_permission


@require_http_methods(["GET", "POST"])
def tenant_login(request):
    """Tenant Admin Panel Login"""
    if request.user.is_authenticated:
        # Kullanıcı zaten giriş yapmış, dashboard'a yönlendir
        return redirect('tenant:dashboard')
    
    if request.method == 'POST':
        username = request.POST.get('username')
        password = request.POST.get('password')
        next_url = request.POST.get('next', request.GET.get('next', ''))
        
        user = authenticate(request, username=username, password=password)
        
        if user is not None:
            # TenantUser profilini kontrol et
            try:
                tenant_user = TenantUser.objects.get(user=user, is_active=True)
                
                # Giriş yap
                login(request, user)
                
                # Son giriş zamanını güncelle
                tenant_user.last_login_at = timezone.now()
                tenant_user.save()
                
                # Kullanıcı tipine göre yönlendir
                if tenant_user.user_type and tenant_user.user_type.dashboard_url:
                    return redirect(tenant_user.user_type.dashboard_url)
                
                # Varsayılan dashboard
                return redirect('tenant:dashboard')
                
            except TenantUser.DoesNotExist:
                messages.error(request, 'Bu kullanıcı için tenant profili bulunamadı.')
        else:
            messages.error(request, 'Kullanıcı adı veya şifre hatalı.')
    
    return render(request, 'tenant/login.html', {
        'next': request.GET.get('next', '')
    })


@login_required
def tenant_logout(request):
    """Tenant Admin Panel Logout"""
    logout(request)
    messages.success(request, 'Başarıyla çıkış yaptınız.')
    return redirect('tenant:login')


def get_module_statistics(tenant, enabled_modules):
    """
    Kiracının aktif modüllerine göre istatistikleri hesapla
    """
    stats = []
    
    # Tur Modülü İstatistikleri
    if 'tours' in enabled_modules:
        try:
            from apps.tenant_apps.tours.models import Tour, TourReservation
            
            # Toplam tur sayısı
            total_tours = Tour.objects.filter(is_active=True).count()
            
            # Toplam rezervasyon sayısı (onaylanmış ve tamamlanmış)
            total_reservations = TourReservation.objects.filter(
                status__in=['confirmed', 'completed']
            ).count()
            
            # Toplam müşteri sayısı (Merkezi Customer modeli)
            total_customers = Customer.objects.count()
            
            # Bu ayki rezervasyonlar
            from datetime import datetime
            current_month = datetime.now().month
            current_year = datetime.now().year
            
            monthly_reservations = TourReservation.objects.filter(
                created_at__month=current_month,
                created_at__year=current_year,
                status__in=['confirmed', 'completed']
            ).count()
            
            # Toplam gelir (bu ay)
            monthly_revenue = TourReservation.objects.filter(
                created_at__month=current_month,
                created_at__year=current_year,
                status__in=['confirmed', 'completed']
            ).aggregate(total=Sum('total_amount'))['total'] or 0
            
            stats.append({
                'module_code': 'tours',
                'module_name': 'Tur Modülü',
                'icon': 'fa-route',
                'color': 'blue',
                'items': [
                    {
                        'label': 'Toplam Tur',
                        'value': total_tours,
                        'icon': 'fa-map-marked-alt',
                    },
                    {
                        'label': 'Toplam Rezervasyon',
                        'value': total_reservations,
                        'icon': 'fa-calendar-check',
                    },
                    {
                        'label': 'Bu Ay Rezervasyon',
                        'value': monthly_reservations,
                        'icon': 'fa-calendar-alt',
                    },
                    {
                        'label': 'Bu Ay Gelir',
                        'value': f"{monthly_revenue:,.2f} TL",
                        'icon': 'fa-lira-sign',
                    },
                ]
            })
        except Exception as e:
            # Modül yüklenemezse sessizce geç
            pass
    
    # Hotels Modülü İstatistikleri
    if 'hotels' in enabled_modules:
        try:
            from apps.tenant_apps.hotels.models import Hotel, Room, RoomNumber
            
            # Toplam otel sayısı
            total_hotels = Hotel.objects.filter(is_deleted=False).count()
            
            # Toplam oda sayısı
            total_rooms = Room.objects.filter(is_deleted=False).count()
            
            # Toplam oda numarası sayısı
            total_room_numbers = RoomNumber.objects.filter(is_deleted=False).count()
            
            # Aktif otel sayısı
            active_hotels = Hotel.objects.filter(is_active=True, is_deleted=False).count()
            
            stats.append({
                'module_code': 'hotels',
                'module_name': 'Otel Modülü',
                'icon': 'fa-hotel',
                'color': 'blue',
                'items': [
                    {
                        'label': 'Toplam Otel',
                        'value': total_hotels,
                        'icon': 'fa-hotel',
                    },
                    {
                        'label': 'Aktif Otel',
                        'value': active_hotels,
                        'icon': 'fa-check-circle',
                    },
                    {
                        'label': 'Toplam Oda',
                        'value': total_rooms,
                        'icon': 'fa-bed',
                    },
                    {
                        'label': 'Oda Numarası',
                        'value': total_room_numbers,
                        'icon': 'fa-door-open',
                    },
                ]
            })
        except Exception as e:
            # Modül yüklenemezse sessizce geç
            pass
    
    # Rezervasyon Modülü İstatistikleri (Otel için)
    if 'reservations' in enabled_modules:
        try:
            # Rezervasyon modülü varsa istatistikleri hesapla
            # Not: Rezervasyon modülü model yapısına göre güncellenebilir
            stats.append({
                'module_code': 'reservations',
                'module_name': 'Rezervasyon Modülü',
                'icon': 'fa-calendar-check',
                'color': 'green',
                'items': [
                    {
                        'label': 'Toplam Rezervasyon',
                        'value': 0,  # Model yapısına göre güncellenecek
                        'icon': 'fa-calendar-check',
                    },
                    {
                        'label': 'Bugün Gelen',
                        'value': 0,
                        'icon': 'fa-user-check',
                    },
                    {
                        'label': 'Bugün Giden',
                        'value': 0,
                        'icon': 'fa-user-times',
                    },
                    {
                        'label': 'Aktif Oda',
                        'value': 0,
                        'icon': 'fa-bed',
                    },
                ]
            })
        except Exception as e:
            pass
    
    # Housekeeping Modülü İstatistikleri
    if 'housekeeping' in enabled_modules:
        try:
            # Housekeeping modülü varsa istatistikleri hesapla
            stats.append({
                'module_code': 'housekeeping',
                'module_name': 'Housekeeping Modülü',
                'icon': 'fa-broom',
                'color': 'purple',
                'items': [
                    {
                        'label': 'Temizlenecek Oda',
                        'value': 0,  # Model yapısına göre güncellenecek
                        'icon': 'fa-broom',
                    },
                    {
                        'label': 'Bekleyen İş',
                        'value': 0,
                        'icon': 'fa-clock',
                    },
                ]
            })
        except Exception as e:
            pass
    
    return stats


@login_required
def tenant_dashboard(request):
    """Tenant Admin Panel Dashboard"""
    try:
        tenant_user = TenantUser.objects.get(user=request.user)
    except TenantUser.DoesNotExist:
        messages.error(request, 'Tenant kullanıcı profili bulunamadı.')
        return redirect('tenant:login')
    
    # Kullanıcı bilgileri
    user_roles = tenant_user.get_roles()
    
    # Aktif modülleri al (context processor'dan veya direkt hesapla)
    enabled_modules = []
    if hasattr(request, 'tenant') and request.tenant:
        tenant = request.tenant
        active_subscription = Subscription.objects.filter(
            tenant=tenant,
            status='active'
        ).first()
        
        if active_subscription:
            package_modules = PackageModule.objects.filter(
                package=active_subscription.package,
                is_enabled=True
            ).select_related('module')
            enabled_modules = [pm.module.code for pm in package_modules]
    
    # Modül bazlı istatistikleri hesapla
    module_statistics = get_module_statistics(request.tenant, enabled_modules)
    
    context = {
        'tenant_user': tenant_user,
        'user_roles': user_roles,
        'module_statistics': module_statistics,
        'enabled_modules': enabled_modules,
    }
    
    # Kullanıcı tipine göre özel template kullan
    if tenant_user.user_type and tenant_user.user_type.panel_template:
        template = tenant_user.user_type.panel_template
    else:
        template = 'tenant/dashboard.html'
    
    return render(request, template, context)


# ==================== KULLANICI YÖNETİMİ ====================

@login_required
@require_module_permission('users', 'view')
def user_list(request):
    """Kullanıcı listeleme"""
    users = TenantUser.objects.select_related('user', 'user_type').all()
    
    # Filtreleme
    search = request.GET.get('search', '')
    user_type = request.GET.get('user_type', '')
    is_active = request.GET.get('is_active', '')
    
    if search:
        users = users.filter(
            Q(user__username__icontains=search) |
            Q(user__first_name__icontains=search) |
            Q(user__last_name__icontains=search) |
            Q(user__email__icontains=search) |
            Q(phone__icontains=search) |
            Q(department__icontains=search)
        )
    
    if user_type:
        users = users.filter(user_type_id=user_type)
    
    if is_active != '':
        users = users.filter(is_active=is_active == '1')
    
    user_types = UserType.objects.filter(is_active=True)
    
    # Sayfalama
    paginator = Paginator(users, 20)
    page = request.GET.get('page', 1)
    users_page = paginator.get_page(page)
    
    # Kullanıcıların otel yetkilerini al (eğer hotels modülü aktifse)
    # Sayfalama sonrası sayfadaki kullanıcılar için yetkileri al
    user_hotel_permissions = {}
    try:
        from apps.tenant_apps.hotels.models import HotelUserPermission
        # Sayfadaki kullanıcılar için
        user_ids = [u.id for u in users_page]
        if user_ids:
            permissions = HotelUserPermission.objects.filter(
                tenant_user_id__in=user_ids,
                is_active=True
            ).select_related('hotel', 'tenant_user')
            
            for perm in permissions:
                if perm.tenant_user_id not in user_hotel_permissions:
                    user_hotel_permissions[perm.tenant_user_id] = []
                user_hotel_permissions[perm.tenant_user_id].append(perm.hotel.name)
    except:
        pass
    
    context = {
        'users': users_page,
        'user_types': user_types,
        'search': search,
        'selected_user_type': user_type,
        'selected_is_active': is_active,
        'user_hotel_permissions': user_hotel_permissions,
    }
    
    return render(request, 'tenant/users/list.html', context)


@login_required
@require_module_permission('users', 'view')
def user_detail(request, pk):
    """Kullanıcı detay"""
    tenant_user = get_object_or_404(TenantUser.objects.select_related('user', 'user_type'), pk=pk)
    user_roles = tenant_user.user_roles.filter(is_active=True).select_related('role')
    user_permissions = tenant_user.user_permissions.filter(is_active=True).select_related('permission', 'permission__module')
    
    # Otel yetkileri (eğer hotels modülü aktifse)
    hotel_permissions = []
    try:
        from apps.tenant_apps.hotels.models import HotelUserPermission
        hotel_permissions = HotelUserPermission.objects.filter(
            tenant_user=tenant_user,
            is_active=True
        ).select_related('hotel').order_by('hotel__name')
    except:
        pass
    
    context = {
        'tenant_user': tenant_user,
        'user_roles': user_roles,
        'user_permissions': user_permissions,
        'hotel_permissions': hotel_permissions,
    }
    
    return render(request, 'tenant/users/detail.html', context)


@login_required
@require_module_permission('users', 'add')
def user_create(request):
    """Yeni kullanıcı oluştur"""
    if request.method == 'POST':
        form = TenantUserForm(request.POST)
        if form.is_valid():
            tenant_user = form.save()
            messages.success(request, f'{tenant_user.user.get_full_name()} kullanıcısı başarıyla oluşturuldu.')
            return redirect('tenant:user_detail', pk=tenant_user.pk)
    else:
        form = TenantUserForm()
    
    context = {
        'form': form,
        'title': 'Yeni Kullanıcı Ekle',
    }
    
    return render(request, 'tenant/users/form.html', context)


@login_required
@require_module_permission('users', 'edit')
def user_update(request, pk):
    """Kullanıcı güncelle"""
    tenant_user = get_object_or_404(TenantUser.objects.select_related('user'), pk=pk)
    
    if request.method == 'POST':
        form = TenantUserForm(request.POST, instance=tenant_user)
        if form.is_valid():
            tenant_user = form.save()
            messages.success(request, f'{tenant_user.user.get_full_name()} kullanıcısı başarıyla güncellendi.')
            return redirect('tenant:user_detail', pk=tenant_user.pk)
    else:
        form = TenantUserForm(instance=tenant_user)
    
    context = {
        'form': form,
        'tenant_user': tenant_user,
        'title': 'Kullanıcı Düzenle',
    }
    
    return render(request, 'tenant/users/form.html', context)


@login_required
@require_module_permission('users', 'delete')
def user_delete(request, pk):
    """Kullanıcı sil (soft delete)"""
    tenant_user = get_object_or_404(TenantUser, pk=pk)
    
    if request.method == 'POST':
        tenant_user.is_active = False
        tenant_user.user.is_active = False
        tenant_user.user.save()
        tenant_user.save()
        messages.success(request, f'{tenant_user.user.get_full_name()} kullanıcısı pasif edildi.')
        return redirect('tenant:user_list')
    
    context = {
        'tenant_user': tenant_user,
    }
    
    return render(request, 'tenant/users/delete.html', context)


# ==================== KULLANICI TİPİ YÖNETİMİ ====================

@login_required
@require_module_permission('users', 'view')
def user_type_list(request):
    """Kullanıcı tipi listeleme"""
    user_types = UserType.objects.all()
    
    # Filtreleme
    search = request.GET.get('search', '')
    is_active = request.GET.get('is_active', '')
    
    if search:
        user_types = user_types.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    if is_active != '':
        user_types = user_types.filter(is_active=is_active == '1')
    
    context = {
        'user_types': user_types,
        'search': search,
        'selected_is_active': is_active,
    }
    
    return render(request, 'tenant/user_types/list.html', context)


@login_required
@require_module_permission('users', 'add')
def user_type_create(request):
    """Yeni kullanıcı tipi oluştur"""
    if request.method == 'POST':
        form = UserTypeForm(request.POST)
        if form.is_valid():
            user_type = form.save()
            messages.success(request, f'{user_type.name} kullanıcı tipi başarıyla oluşturuldu.')
            return redirect('tenant:user_type_list')
    else:
        form = UserTypeForm()
    
    context = {
        'form': form,
        'title': 'Yeni Kullanıcı Tipi Ekle',
    }
    
    return render(request, 'tenant/user_types/form.html', context)


@login_required
@require_module_permission('users', 'edit')
def user_type_update(request, pk):
    """Kullanıcı tipi güncelle"""
    user_type = get_object_or_404(UserType, pk=pk)
    
    if request.method == 'POST':
        form = UserTypeForm(request.POST, instance=user_type)
        if form.is_valid():
            user_type = form.save()
            messages.success(request, f'{user_type.name} kullanıcı tipi başarıyla güncellendi.')
            return redirect('tenant:user_type_list')
    else:
        form = UserTypeForm(instance=user_type)
    
    context = {
        'form': form,
        'user_type': user_type,
        'title': 'Kullanıcı Tipi Düzenle',
    }
    
    return render(request, 'tenant/user_types/form.html', context)


@login_required
@require_module_permission('users', 'delete')
def user_type_delete(request, pk):
    """Kullanıcı tipi sil"""
    user_type = get_object_or_404(UserType, pk=pk)
    
    if request.method == 'POST':
        user_type.delete()
        messages.success(request, f'{user_type.name} kullanıcı tipi silindi.')
        return redirect('tenant:user_type_list')
    
    context = {
        'user_type': user_type,
    }
    
    return render(request, 'tenant/user_types/delete.html', context)


# ==================== ROL YÖNETİMİ ====================

@login_required
@require_module_permission('roles', 'view')
def role_list(request):
    """Rol listeleme"""
    # Tenant panelinde super_admin rolü görünmemeli
    roles = Role.objects.exclude(code='super_admin')
    
    # Filtreleme
    search = request.GET.get('search', '')
    is_active = request.GET.get('is_active', '')
    
    if search:
        roles = roles.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search)
        )
    
    if is_active != '':
        roles = roles.filter(is_active=is_active == '1')
    
    context = {
        'roles': roles,
        'search': search,
        'selected_is_active': is_active,
    }
    
    return render(request, 'tenant/roles/list.html', context)


@login_required
@require_module_permission('roles', 'view')
def role_detail(request, pk):
    """Rol detay"""
    # Tenant panelinde super_admin rolü görünmemeli
    role = get_object_or_404(Role.objects.exclude(code='super_admin'), pk=pk)
    role_permissions = role.role_permissions.filter(is_active=True).select_related('permission', 'permission__module')
    users_with_role = UserRole.objects.filter(role=role, is_active=True).select_related('tenant_user', 'tenant_user__user')
    
    context = {
        'role': role,
        'role_permissions': role_permissions,
        'users_with_role': users_with_role,
    }
    
    return render(request, 'tenant/roles/detail.html', context)


@login_required
@require_module_permission('roles', 'add')
def role_create(request):
    """Yeni rol oluştur"""
    if request.method == 'POST':
        form = RoleForm(request.POST)
        if form.is_valid():
            role = form.save()
            messages.success(request, f'{role.name} rolü başarıyla oluşturuldu.')
            return redirect('tenant:role_detail', pk=role.pk)
    else:
        form = RoleForm()
    
    context = {
        'form': form,
        'title': 'Yeni Rol Ekle',
    }
    
    return render(request, 'tenant/roles/form.html', context)


@login_required
@require_module_permission('roles', 'edit')
def role_update(request, pk):
    """Rol güncelle"""
    # Tenant panelinde super_admin rolü görünmemeli
    role = get_object_or_404(Role.objects.exclude(code='super_admin'), pk=pk)
    
    if role.is_system or role.code == 'super_admin':
        messages.error(request, 'Sistem rolleri düzenlenemez.')
        return redirect('tenant:role_list')
    
    if request.method == 'POST':
        form = RoleForm(request.POST, instance=role)
        if form.is_valid():
            role = form.save()
            messages.success(request, f'{role.name} rolü başarıyla güncellendi.')
            return redirect('tenant:role_detail', pk=role.pk)
    else:
        form = RoleForm(instance=role)
    
    context = {
        'form': form,
        'role': role,
        'title': 'Rol Düzenle',
    }
    
    return render(request, 'tenant/roles/form.html', context)


@login_required
@require_module_permission('roles', 'delete')
def role_delete(request, pk):
    """Rol sil"""
    # Tenant panelinde super_admin rolü görünmemeli
    role = get_object_or_404(Role.objects.exclude(code='super_admin'), pk=pk)
    
    if role.is_system or role.code == 'super_admin':
        messages.error(request, 'Sistem rolleri silinemez.')
        return redirect('tenant:role_list')
    
    if request.method == 'POST':
        role.delete()
        messages.success(request, f'{role.name} rolü silindi.')
        return redirect('tenant:role_list')
    
    context = {
        'role': role,
    }
    
    return render(request, 'tenant/roles/delete.html', context)


# ==================== YETKİ YÖNETİMİ ====================

@login_required
@require_module_permission('permissions', 'view')
def permission_list(request):
    """Yetki listeleme"""
    permissions = Permission.objects.select_related('module').all()
    
    # Filtreleme
    search = request.GET.get('search', '')
    module = request.GET.get('module', '')
    permission_type = request.GET.get('permission_type', '')
    is_active = request.GET.get('is_active', '')
    
    if search:
        permissions = permissions.filter(
            Q(name__icontains=search) |
            Q(code__icontains=search) |
            Q(description__icontains=search) |
            Q(module__name__icontains=search)
        )
    
    if module:
        permissions = permissions.filter(module_id=module)
    
    if permission_type:
        permissions = permissions.filter(permission_type=permission_type)
    
    if is_active != '':
        permissions = permissions.filter(is_active=is_active == '1')
    
    # Sayfalama
    paginator = Paginator(permissions, 20)
    page = request.GET.get('page', 1)
    permissions = paginator.get_page(page)
    
    modules = Module.objects.filter(is_active=True)
    
    context = {
        'permissions': permissions,
        'modules': modules,
        'search': search,
        'selected_module': module,
        'selected_permission_type': permission_type,
        'selected_is_active': is_active,
        'permission_types': Permission.PERMISSION_TYPE_CHOICES,
    }
    
    return render(request, 'tenant/permissions/list.html', context)


@login_required
@require_module_permission('permissions', 'view')
def permission_detail(request, pk):
    """Yetki detay"""
    permission = get_object_or_404(Permission.objects.select_related('module'), pk=pk)
    roles_with_permission = RolePermission.objects.filter(
        permission=permission, is_active=True
    ).select_related('role')
    
    context = {
        'permission': permission,
        'roles_with_permission': roles_with_permission,
    }
    
    return render(request, 'tenant/permissions/detail.html', context)


@login_required
@require_module_permission('permissions', 'add')
def permission_create(request):
    """Yeni yetki oluştur"""
    if request.method == 'POST':
        form = PermissionForm(request.POST)
        if form.is_valid():
            permission = form.save()
            messages.success(request, f'{permission.name} yetkisi başarıyla oluşturuldu.')
            return redirect('tenant:permission_detail', pk=permission.pk)
    else:
        form = PermissionForm()
    
    context = {
        'form': form,
        'title': 'Yeni Yetki Ekle',
    }
    
    return render(request, 'tenant/permissions/form.html', context)


@login_required
@require_module_permission('permissions', 'edit')
def permission_update(request, pk):
    """Yetki güncelle"""
    permission = get_object_or_404(Permission, pk=pk)
    
    if permission.is_system:
        messages.error(request, 'Sistem yetkileri düzenlenemez.')
        return redirect('tenant:permission_detail', pk=permission.pk)
    
    if request.method == 'POST':
        form = PermissionForm(request.POST, instance=permission)
        if form.is_valid():
            permission = form.save()
            messages.success(request, f'{permission.name} yetkisi başarıyla güncellendi.')
            return redirect('tenant:permission_detail', pk=permission.pk)
    else:
        form = PermissionForm(instance=permission)
    
    context = {
        'form': form,
        'permission': permission,
        'title': 'Yetki Düzenle',
    }
    
    return render(request, 'tenant/permissions/form.html', context)


@login_required
@require_module_permission('permissions', 'delete')
def permission_delete(request, pk):
    """Yetki sil"""
    permission = get_object_or_404(Permission, pk=pk)
    
    if permission.is_system:
        messages.error(request, 'Sistem yetkileri silinemez.')
        return redirect('tenant:permission_list')
    
    if request.method == 'POST':
        permission.delete()
        messages.success(request, f'{permission.name} yetkisi silindi.')
        return redirect('tenant:permission_list')
    
    context = {
        'permission': permission,
    }
    
    return render(request, 'tenant/permissions/delete.html', context)


# ==================== KULLANICI-ROL İLİŞKİSİ ====================

@login_required
@require_module_permission('users', 'assign_role')
def user_role_assign(request, user_pk):
    """Kullanıcıya rol ata"""
    tenant_user = get_object_or_404(TenantUser, pk=user_pk)
    
    if request.method == 'POST':
        role_id = request.POST.get('role')
        role = get_object_or_404(Role, pk=role_id)
        
        user_role, created = UserRole.objects.get_or_create(
            tenant_user=tenant_user,
            role=role,
            defaults={'is_active': True, 'assigned_by': request.user}
        )
        
        if not created:
            user_role.is_active = True
            user_role.assigned_by = request.user
            user_role.save()
        
        messages.success(request, f'{role.name} rolü {tenant_user.user.get_full_name()} kullanıcısına atandı.')
        return redirect('tenant:user_detail', pk=tenant_user.pk)
    
    # Tenant panelinde super_admin rolü görünmemeli
    roles = Role.objects.filter(is_active=True).exclude(
        code='super_admin'
    ).exclude(
        id__in=UserRole.objects.filter(
            tenant_user=tenant_user,
            is_active=True
        ).values_list('role_id', flat=True)
    )
    
    context = {
        'tenant_user': tenant_user,
        'roles': roles,
    }
    
    return render(request, 'tenant/users/assign_role.html', context)


@login_required
@require_module_permission('users', 'assign_role')
def user_role_remove(request, user_pk, role_pk):
    """Kullanıcıdan rol kaldır"""
    tenant_user = get_object_or_404(TenantUser, pk=user_pk)
    user_role = get_object_or_404(UserRole, tenant_user=tenant_user, role_id=role_pk)
    
    if request.method == 'POST':
        user_role.is_active = False
        user_role.save()
        messages.success(request, f'Rol kullanıcıdan kaldırıldı.')
        return redirect('tenant:user_detail', pk=tenant_user.pk)
    
    context = {
        'tenant_user': tenant_user,
        'user_role': user_role,
    }
    
    return render(request, 'tenant/users/remove_role.html', context)


# ==================== ROL-YETKİ İLİŞKİSİ ====================

@login_required
@require_module_permission('roles', 'assign_permission')
def role_permission_assign(request, role_pk):
    """Role yetki ata"""
    # Tenant panelinde super_admin rolü görünmemeli
    role = get_object_or_404(Role.objects.exclude(code='super_admin'), pk=role_pk)
    
    if request.method == 'POST':
        permission_id = request.POST.get('permission')
        permission = get_object_or_404(Permission, pk=permission_id)
        
        role_permission, created = RolePermission.objects.get_or_create(
            role=role,
            permission=permission,
            defaults={'is_active': True}
        )
        
        if not created:
            role_permission.is_active = True
            role_permission.save()
        
        messages.success(request, f'{permission.name} yetkisi {role.name} rolüne atandı.')
        return redirect('tenant:role_detail', pk=role.pk)
    
    permissions = Permission.objects.filter(is_active=True).exclude(
        id__in=RolePermission.objects.filter(
            role=role,
            is_active=True
        ).values_list('permission_id', flat=True)
    ).select_related('module')
    
    context = {
        'role': role,
        'permissions': permissions,
    }
    
    return render(request, 'tenant/roles/assign_permission.html', context)


@login_required
@require_module_permission('roles', 'assign_permission')
def role_permission_remove(request, role_pk, permission_pk):
    """Rolden yetki kaldır"""
    # Tenant panelinde super_admin rolü görünmemeli
    role = get_object_or_404(Role.objects.exclude(code='super_admin'), pk=role_pk)
    role_permission = get_object_or_404(RolePermission, role=role, permission_id=permission_pk)
    
    if request.method == 'POST':
        role_permission.is_active = False
        role_permission.save()
        messages.success(request, f'Yetki rolden kaldırıldı.')
        return redirect('tenant:role_detail', pk=role.pk)
    
    context = {
        'role': role,
        'role_permission': role_permission,
    }
    
    return render(request, 'tenant/roles/remove_permission.html', context)


# ==================== KULLANICI-YETKİ İLİŞKİSİ ====================

@login_required
@require_module_permission('users', 'assign_permission')
def user_permission_assign(request, user_pk):
    """Kullanıcıya direkt yetki ata (tek tek veya modül bazlı toplu)"""
    tenant_user = get_object_or_404(TenantUser, pk=user_pk)
    
    if request.method == 'POST':
        # Modül bazlı toplu atama
        if 'assign_module' in request.POST:
            module_id = request.POST.get('assign_module')
            if module_id:
                from apps.modules.models import Module
                try:
                    module = Module.objects.get(pk=module_id)
                    # Bu modüldeki tüm yetkileri al
                    module_permissions = Permission.objects.filter(
                        module=module,
                        is_active=True
                    )
                    
                    assigned_count = 0
                    for permission in module_permissions:
                        # Zaten atanmış mı kontrol et
                        user_permission, created = UserPermission.objects.get_or_create(
                            tenant_user=tenant_user,
                            permission=permission,
                            defaults={
                                'is_active': True,
                                'assigned_by': request.user
                            }
                        )
                        
                        if not created and not user_permission.is_active:
                            user_permission.is_active = True
                            user_permission.assigned_by = request.user
                            user_permission.save()
                            assigned_count += 1
                        elif created:
                            assigned_count += 1
                    
                    messages.success(request, f'{module.name} modülünden {assigned_count} yetki kullanıcıya atandı.')
                    return redirect('tenant:user_detail', pk=tenant_user.pk)
                except Module.DoesNotExist:
                    messages.error(request, 'Modül bulunamadı.')
        
        # Tek yetki atama
        elif 'permission' in request.POST:
            permission_id = request.POST.get('permission')
            permission = get_object_or_404(Permission, pk=permission_id)
            
            user_permission, created = UserPermission.objects.get_or_create(
                tenant_user=tenant_user,
                permission=permission,
                defaults={
                    'is_active': True,
                    'assigned_by': request.user
                }
            )
            
            if not created:
                user_permission.is_active = True
                user_permission.assigned_by = request.user
                user_permission.save()
            
            messages.success(request, f'Yetki kullanıcıya atandı.')
            return redirect('tenant:user_detail', pk=tenant_user.pk)
    
    # Mevcut yetkileri al (rol bazlı ve direkt)
    assigned_permissions = UserPermission.objects.filter(
        tenant_user=tenant_user,
        is_active=True
    ).values_list('permission_id', flat=True)
    
    # Kullanıcının rollerinden gelen yetkileri al
    user_roles = UserRole.objects.filter(
        tenant_user=tenant_user,
        is_active=True
    ).select_related('role')
    
    role_permission_ids = []
    for user_role in user_roles:
        role_perms = RolePermission.objects.filter(
            role=user_role.role,
            is_active=True
        ).values_list('permission_id', flat=True)
        role_permission_ids.extend(role_perms)
    
    # Tüm yetkileri al
    all_permissions = Permission.objects.filter(is_active=True).select_related('module').order_by('module__name', 'name')
    
    # Modül bazında grupla
    permissions_by_module = {}
    modules_info = {}  # Modül bilgileri (ID, name, icon vb.)
    for perm in all_permissions:
        module = perm.module
        module_name = module.name
        if module_name not in permissions_by_module:
            permissions_by_module[module_name] = []
            modules_info[module_name] = {
                'id': module.id,
                'name': module.name,
                'code': module.code,
                'icon': module.icon,
            }
        permissions_by_module[module_name].append(perm)
    
    # Her modül için atanmış yetki sayısını hesapla
    module_stats = {}
    for module_name, permissions in permissions_by_module.items():
        module_permission_ids = [p.pk for p in permissions]
        assigned_count = len([pid for pid in assigned_permissions if pid in module_permission_ids])
        role_count = len([pid for pid in role_permission_ids if pid in module_permission_ids])
        total_count = len(permissions)
        module_stats[module_name] = {
            'total': total_count,
            'assigned': assigned_count,
            'from_role': role_count,
            'available': total_count - assigned_count - role_count,
        }
    
    # Template için modül listesi (sıralı)
    module_list = []
    for module_name in sorted(permissions_by_module.keys()):
        module_list.append({
            'name': module_name,
            'data': modules_info[module_name],
            'stats': module_stats[module_name],
        })
    
    context = {
        'tenant_user': tenant_user,
        'permissions_by_module': permissions_by_module,
        'modules_info': modules_info,
        'module_stats': module_stats,
        'module_list': module_list,  # Sıralı modül listesi
        'assigned_permissions': list(assigned_permissions),
        'role_permission_ids': list(set(role_permission_ids)),
    }
    
    return render(request, 'tenant/users/assign_permission.html', context)


@login_required
@require_module_permission('users', 'assign_permission')
def user_permission_remove(request, user_pk, permission_pk):
    """Kullanıcıdan yetki kaldır"""
    tenant_user = get_object_or_404(TenantUser, pk=user_pk)
    user_permission = get_object_or_404(UserPermission, tenant_user=tenant_user, permission_id=permission_pk)
    
    if request.method == 'POST':
        user_permission.is_active = False
        user_permission.save()
        messages.success(request, f'Yetki kullanıcıdan kaldırıldı.')
        return redirect('tenant:user_detail', pk=tenant_user.pk)
    
    context = {
        'tenant_user': tenant_user,
        'user_permission': user_permission,
    }
    
    return render(request, 'tenant/users/remove_permission.html', context)


# ==================== AJAX MÜŞTERİ ARAMA ====================

@login_required
@require_http_methods(["GET", "POST"])
def ajax_find_customer(request):
    """
    AJAX ile müşteri bulma
    TC No, Email veya Telefon ile müşteri bilgilerini döndürür
    """
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
        except:
            data = request.POST
    else:
        data = request.GET
    
    tc_no = data.get('tc_no', '').strip()
    email = data.get('email', '').strip()
    phone = data.get('phone', '').strip()
    
    if not (tc_no or email or phone):
        return JsonResponse({
            'success': False,
            'error': 'TC No, Email veya Telefon numarası gerekli'
        })
    
    # Müşteriyi bul
    customer = Customer.find_by_identifier(email=email, phone=phone, tc_no=tc_no)
    
    if customer:
        return JsonResponse({
            'success': True,
            'customer': {
                'id': customer.pk,
                'customer_code': customer.customer_code,
                'first_name': customer.first_name,
                'last_name': customer.last_name,
                'email': customer.email,
                'phone': customer.phone,
                'tc_no': customer.tc_no,
                'address': customer.address,
                'city': customer.city,
                'country': customer.country,
                'postal_code': customer.postal_code,
                'birth_date': str(customer.birth_date) if customer.birth_date else None,
                'vip_level': customer.vip_level,
                'loyalty_points': customer.loyalty_points,
                'total_reservations': customer.total_reservations,
                'total_spent': float(customer.total_spent),
            }
        })
    else:
        return JsonResponse({
            'success': False,
            'message': 'Müşteri bulunamadı'
        })


# ==================== MÜŞTERİ YÖNETİMİ (CRM) ====================

@login_required
@require_module_permission('customers', 'view')
def customer_list(request):
    """Müşteri listesi"""
    customers = Customer.objects.all()
    
    # Filtreleme
    vip_level = request.GET.get('vip_level')
    if vip_level:
        customers = customers.filter(vip_level=vip_level)
    
    is_active = request.GET.get('is_active')
    if is_active is not None:
        customers = customers.filter(is_active=is_active == '1')
    
    # Arama
    search = request.GET.get('search')
    if search:
        customers = customers.filter(
            Q(customer_code__icontains=search) |
            Q(first_name__icontains=search) |
            Q(last_name__icontains=search) |
            Q(email__icontains=search) |
            Q(phone__icontains=search) |
            Q(tc_no__icontains=search)
        )
    
    # Sıralama
    sort_by = request.GET.get('sort', '-created_at')
    customers = customers.order_by(sort_by)
    
    # Sayfalama
    paginator = Paginator(customers, 20)
    page = request.GET.get('page')
    customers = paginator.get_page(page)
    
    context = {
        'customers': customers,
        'vip_level_choices': Customer.VIP_LEVEL_CHOICES,
    }
    
    return render(request, 'tenant/customers/list.html', context)


@login_required
@require_module_permission('customers', 'view')
def customer_detail(request, pk):
    """Müşteri detay (Folyo)"""
    customer = get_object_or_404(Customer, pk=pk)
    
    # Tüm modüllerden rezervasyonları al
    tour_reservations = customer.tour_reservations.all().order_by('-created_at')[:10]
    refund_requests = customer.refund_requests.all().order_by('-created_at')[:10]
    invoices = customer.invoices.all().order_by('-created_at')[:10]
    
    # Reception rezervasyonları
    reception_reservations = None
    reception_payments = None
    total_reception_amount = Decimal('0')
    total_reception_paid = Decimal('0')
    total_reception_remaining = Decimal('0')
    
    try:
        from apps.tenant_apps.reception.models import Reservation, ReservationPayment
        reception_reservations = Reservation.objects.filter(
            customer=customer,
            is_deleted=False
        ).order_by('-check_in_date')[:50]
        
        reception_payments = ReservationPayment.objects.filter(
            reservation__customer=customer,
            is_deleted=False
        ).order_by('-payment_date')[:50]
        
        if reception_reservations:
            total_reception_amount = sum(r.total_amount for r in reception_reservations)
            total_reception_paid = sum(r.total_paid for r in reception_reservations)
            total_reception_remaining = total_reception_amount - total_reception_paid
    except:
        pass
    
    # Accounting faturaları (reception için)
    accounting_invoices = None
    try:
        from apps.tenant_apps.accounting.models import Invoice
        if reception_reservations:
            reservation_ids = [r.pk for r in reception_reservations]
            accounting_invoices = Invoice.objects.filter(
                source_module='reception',
                source_id__in=reservation_ids,
                is_deleted=False
            ).order_by('-invoice_date')[:50]
    except:
        pass
    
    # Finance kasa işlemleri (reception için)
    finance_transactions = None
    try:
        from apps.tenant_apps.finance.models import CashTransaction
        if reception_reservations:
            reservation_ids = [r.pk for r in reception_reservations]
            finance_transactions = CashTransaction.objects.filter(
                source_module='reception',
                source_id__in=reservation_ids,
                is_deleted=False
            ).order_by('-payment_date')[:50]
    except:
        pass
    
    # Sadakat geçmişi
    loyalty_history = CustomerLoyaltyHistory.objects.filter(customer=customer).order_by('-created_at')[:10]
    
    # Notlar
    notes = CustomerNote.objects.filter(customer=customer).order_by('-created_at')
    
    context = {
        'customer': customer,
        'tour_reservations': tour_reservations,
        'reception_reservations': reception_reservations,
        'reception_payments': reception_payments,
        'accounting_invoices': accounting_invoices,
        'finance_transactions': finance_transactions,
        'total_reception_amount': total_reception_amount,
        'total_reception_paid': total_reception_paid,
        'total_reception_remaining': total_reception_remaining,
        'refund_requests': refund_requests,
        'invoices': invoices,
        'loyalty_history': loyalty_history,
        'notes': notes,
    }
    
    return render(request, 'tenant/customers/detail.html', context)


@login_required
@require_module_permission('customers', 'add')
def customer_create(request):
    """Müşteri ekle"""
    from .forms import CustomerForm
    
    if request.method == 'POST':
        form = CustomerForm(request.POST)
        if form.is_valid():
            customer = form.save()
            messages.success(request, f'Müşteri "{customer.first_name} {customer.last_name}" başarıyla eklendi.')
            return redirect('tenant:customer_list')
    else:
        form = CustomerForm()
    
    context = {
        'form': form,
        'title': 'Yeni Müşteri Ekle',
    }
    
    return render(request, 'tenant/customers/form.html', context)


@login_required
@require_module_permission('customers', 'edit')
def customer_update(request, pk):
    """Müşteri güncelle"""
    from .forms import CustomerForm
    
    customer = get_object_or_404(Customer, pk=pk)
    
    if request.method == 'POST':
        form = CustomerForm(request.POST, instance=customer)
        if form.is_valid():
            customer = form.save()
            messages.success(request, f'Müşteri "{customer.first_name} {customer.last_name}" başarıyla güncellendi.')
            return redirect('tenant:customer_list')
    else:
        form = CustomerForm(instance=customer)
    
    context = {
        'form': form,
        'customer': customer,
        'title': 'Müşteri Düzenle',
    }
    
    return render(request, 'tenant/customers/form.html', context)


@login_required
@require_module_permission('customers', 'delete')
@require_http_methods(["POST"])
def customer_delete(request, pk):
    """Müşteri sil"""
    customer = get_object_or_404(Customer, pk=pk)
    customer_name = f"{customer.first_name} {customer.last_name}"
    customer.delete()  # Soft delete
    messages.success(request, f'Müşteri "{customer_name}" başarıyla silindi.')
    return redirect('tenant:customer_list')
