"""
Mevcut admin kullanıcılarına yetkileri ata
Test-otel gibi mevcut tenant'lar için
"""
from django.core.management.base import BaseCommand
from django.core.management import call_command
from django_tenants.utils import get_tenant_model, schema_context, get_public_schema_name
from django.db import connection
from apps.tenant_apps.core.models import TenantUser, Role, UserRole, RolePermission, Permission


class Command(BaseCommand):
    help = 'Mevcut admin kullanıcılarına tüm yetkileri ata'

    def add_arguments(self, parser):
        parser.add_argument(
            '--tenant-slug',
            type=str,
            help='Belirli bir tenant için (opsiyonel)',
        )

    def handle(self, *args, **options):
        # Public schema'ya geç
        connection.set_schema_to_public()
        
        tenant_slug = options.get('tenant_slug')
        
        if tenant_slug:
            # Belirli tenant için
            TenantModel = get_tenant_model()
            try:
                tenant = TenantModel.objects.get(slug=tenant_slug)
                self.fix_tenant_admin_permissions(tenant)
            except TenantModel.DoesNotExist:
                self.stdout.write(self.style.ERROR(f'Tenant bulunamadı: {tenant_slug}'))
        else:
            # Tüm tenant'lar için
            TenantModel = get_tenant_model()
            tenants = TenantModel.objects.filter(
                is_active=True
            ).exclude(schema_name=get_public_schema_name())
            
            total = tenants.count()
            self.stdout.write(f'\n{total} tenant için admin yetkileri atanacak...\n')
            
            for tenant in tenants:
                self.fix_tenant_admin_permissions(tenant)
    
    def fix_tenant_admin_permissions(self, tenant):
        """Tenant için admin yetkilerini düzelt"""
        self.stdout.write(f'\n[{tenant.schema_name}] {tenant.name} - Yetkiler atanıyor...', ending=' ')
        
        try:
            with schema_context(tenant.schema_name):
                # Önce rolleri oluştur
                call_command('create_default_roles', verbosity=0)
                
                # Yetkileri oluştur
                call_command('create_user_role_permission_permissions', verbosity=0)
                
                # Admin rolünü bul
                admin_role = Role.objects.filter(code='admin').first()
                if not admin_role:
                    self.stdout.write(self.style.WARNING('Admin rolü bulunamadı'))
                    return
                
                # Admin rolüne tüm yetkileri ata
                permissions = Permission.objects.filter(is_active=True)
                assigned_count = 0
                for permission in permissions:
                    role_permission, created = RolePermission.objects.get_or_create(
                        role=admin_role,
                        permission=permission,
                        defaults={'is_active': True}
                    )
                    if created:
                        assigned_count += 1
                
                # Admin rolüne sahip kullanıcıları bul
                admin_users = TenantUser.objects.filter(
                    user_roles__role=admin_role,
                    user_roles__is_active=True
                ).distinct()
                
                user_count = admin_users.count()
                
                self.stdout.write(
                    self.style.SUCCESS(
                        f'OK - {assigned_count} yetki atandı, {user_count} admin kullanıcı'
                    )
                )
        
        except Exception as e:
            self.stdout.write(self.style.ERROR(f'HATA: {str(e)}'))

