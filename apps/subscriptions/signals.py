"""
Subscription signals
Abonelik aktif olduğunda otomatik ilk admin kullanıcı oluşturma
"""
from django.db.models.signals import post_save
from django.dispatch import receiver
from django.contrib.auth.models import User
from django.db import connection
from django_tenants.utils import schema_context, get_public_schema_name
from .models import Subscription
from apps.tenant_apps.core.models import TenantUser, UserType, Role, UserRole, RolePermission, Permission
from apps.modules.models import Module
from apps.tenants.models import Tenant


@receiver(post_save, sender=Subscription)
def sync_tenant_package(sender, instance, created, **kwargs):
    """
    Subscription değiştiğinde tenant.package'ı senkronize et
    """
    if instance.status == 'active' and instance.package:
        tenant = instance.tenant
        if tenant.package != instance.package:
            tenant.package = instance.package
            tenant.save(update_fields=['package'])
            import logging
            logger = logging.getLogger(__name__)
            logger.info(f'Tenant {tenant.name} paketi güncellendi: {instance.package.name}')


@receiver(post_save, sender=Tenant)
def sync_subscription_package(sender, instance, created, **kwargs):
    """
    Tenant.package değiştiğinde subscription.package'ı senkronize et
    """
    if instance.package:
        # Aktif subscription'ı bul
        subscription = Subscription.objects.filter(
            tenant=instance,
            status='active'
        ).first()
        
        if subscription and subscription.package != instance.package:
            subscription.package = instance.package
            subscription.amount = instance.package.price_monthly
            subscription.currency = instance.package.currency
            subscription.save(update_fields=['package', 'amount', 'currency'])
            import logging
            logger = logging.getLogger(__name__)
            logger.info(f'Subscription {subscription.id} paketi güncellendi: {instance.package.name}')


@receiver(post_save, sender=Subscription)
def create_initial_admin_user(sender, instance, created, **kwargs):
    """
    Subscription aktif olduğunda ilk admin kullanıcı oluştur
    """
    # Sadece aktif subscription için
    if instance.status != 'active':
        return
    
    # Tenant schema'da çalış
    tenant = instance.tenant
    
    with schema_context(tenant.schema_name):
        # İlk kullanıcı var mı kontrol et
        if TenantUser.objects.exists():
            return  # Zaten kullanıcı var, işlem yapma
        
        # Owner bilgilerini al
        owner_email = tenant.owner_email
        owner_name = tenant.owner_name
        
        # İsim ve soyisim ayır
        name_parts = owner_name.split(' ', 1)
        first_name = name_parts[0] if name_parts else owner_name
        last_name = name_parts[1] if len(name_parts) > 1 else ''
        
        # Username oluştur (email'den)
        username = owner_email.split('@')[0]
        
        # Django User oluştur
        user, user_created = User.objects.get_or_create(
            username=username,
            defaults={
                'email': owner_email,
                'first_name': first_name,
                'last_name': last_name,
                'is_active': True,
                'is_staff': False,
                'is_superuser': False,
            }
        )
        
        if not user_created:
            # Mevcut kullanıcıyı güncelle
            user.email = owner_email
            user.first_name = first_name
            user.last_name = last_name
            user.is_active = True
            user.save()
        
        # Şifre ayarla (varsayılan: email'in ilk kısmı + "123")
        if user_created:
            default_password = f"{username}123"
            user.set_password(default_password)
            user.save()
        
        # UserType bul veya oluştur (admin)
        user_type, _ = UserType.objects.get_or_create(
            code='admin',
            defaults={
                'name': 'Yönetici',
                'description': 'Tam yetkili yönetici',
                'icon': '👔',
                'dashboard_url': '/',
                'is_active': True,
                'sort_order': 1,
            }
        )
        
        # TenantUser oluştur
        tenant_user, tenant_user_created = TenantUser.objects.get_or_create(
            user=user,
            defaults={
                'user_type': user_type,
                'is_active': True,
            }
        )
        
        if not tenant_user_created:
            tenant_user.user_type = user_type
            tenant_user.is_active = True
            tenant_user.save()
        
        # Admin rolünü bul
        admin_role = Role.objects.filter(code='admin').first()
        if not admin_role:
            # Admin rolü yoksa oluştur
            admin_role = Role.objects.create(
                name='Admin',
                code='admin',
                description='Yönetici rolü',
                icon='🛡️',
                is_active=True,
                is_system=True,
                sort_order=2,
            )
        
        # Admin rolüne tüm yetkileri ata (eğer yoksa)
        assign_all_permissions_to_admin_role(admin_role)
        
        # Kullanıcıya admin rolünü ata
        user_role, role_created = UserRole.objects.get_or_create(
            tenant_user=tenant_user,
            role=admin_role,
            defaults={
                'is_active': True,
                'assigned_by': user,
            }
        )
        
        if not role_created:
            user_role.is_active = True
            user_role.assigned_by = user
            user_role.save()


def assign_all_permissions_to_admin_role(admin_role):
    """
    Admin rolüne tüm yetkileri otomatik ata
    """
    # Tenant schema'da çalış
    # Tüm Permission kayıtlarını al
    permissions = Permission.objects.filter(is_active=True).select_related('module')
    
    assigned_count = 0
    for permission in permissions:
        role_permission, created = RolePermission.objects.get_or_create(
            role=admin_role,
            permission=permission,
            defaults={
                'is_active': True,
            }
        )
        if created:
            assigned_count += 1
    
    return assigned_count

