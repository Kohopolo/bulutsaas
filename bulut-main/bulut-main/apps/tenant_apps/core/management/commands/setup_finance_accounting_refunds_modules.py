"""
Finance, Accounting ve Refunds modüllerini dinamik olarak kurar
- Modülleri oluşturur (yoksa)
- Paketlere ekler (yoksa)
- Admin role yetkilerini atar (yoksa)
"""
from django.core.management.base import BaseCommand
from django.core.management import call_command
from django.db import connection
from django_tenants.utils import schema_context, get_public_schema_name
from apps.modules.models import Module
from apps.packages.models import Package, PackageModule
from apps.tenant_apps.core.models import Role, Permission, RolePermission
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Finance, Accounting ve Refunds modüllerini dinamik olarak kurar'

    def add_arguments(self, parser):
        parser.add_argument(
            '--tenant',
            type=str,
            help='Belirli bir tenant için çalıştır (schema_name)',
        )
        parser.add_argument(
            '--all-tenants',
            action='store_true',
            help='Tüm tenant\'lar için çalıştır',
        )

    def handle(self, *args, **options):
        tenant_schema = options.get('tenant')
        all_tenants = options.get('all_tenants', False)

        # Public schema'da modülleri oluştur
        self.setup_modules_in_public()

        if all_tenants:
            # Tüm tenant'lar için çalıştır
            tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
            for tenant in tenants:
                self.setup_modules_for_tenant(tenant.schema_name)
        elif tenant_schema:
            # Belirli tenant için çalıştır
            self.setup_modules_for_tenant(tenant_schema)
        else:
            # Mevcut tenant için çalıştır
            current_schema = connection.schema_name
            if current_schema != get_public_schema_name():
                self.setup_modules_for_tenant(current_schema)
            else:
                self.stdout.write(
                    self.style.ERROR('Public schema\'da çalıştırılamaz. --tenant veya --all-tenants kullanın.')
                )

    def setup_modules_in_public(self):
        """Public schema'da modülleri oluştur"""
        self.stdout.write('Public schema\'da modüller oluşturuluyor...')
        
        # Modülleri oluştur
        call_command('create_finance_accounting_refunds_modules', verbosity=0)
        
        # Paketlere ekle
        call_command('add_finance_accounting_refunds_to_packages', verbosity=0)
        
        self.stdout.write(self.style.SUCCESS('[OK] Public schema modülleri hazır'))

    def setup_modules_for_tenant(self, schema_name):
        """Tenant için modül yetkilerini kur"""
        self.stdout.write(f'\n[{schema_name}] Modül yetkileri kuruluyor...')
        
        try:
            with schema_context(schema_name):
                # Modülleri kontrol et
                modules = ['finance', 'accounting', 'refunds']
                
                for module_code in modules:
                    try:
                        # Public schema'dan modülü al
                        with schema_context(get_public_schema_name()):
                            module = Module.objects.get(code=module_code)
                        
                        # Tenant schema'da permission'ları oluştur
                        self.create_module_permissions(module_code, module)
                        
                        # Admin role yetkilerini ata
                        self.assign_permissions_to_admin(module_code)
                        
                    except Module.DoesNotExist:
                        self.stdout.write(
                            self.style.WARNING(f'  [SKIP] {module_code} modülü bulunamadı')
                        )
                    except Exception as e:
                        self.stdout.write(
                            self.style.ERROR(f'  [ERROR] {module_code}: {str(e)}')
                        )
                
                self.stdout.write(
                    self.style.SUCCESS(f'[{schema_name}] Modül yetkileri kuruldu')
                )
        
        except Exception as e:
            self.stdout.write(
                self.style.ERROR(f'[{schema_name}] HATA: {str(e)}')
            )

    def create_module_permissions(self, module_code, module):
        """Modül için permission'ları oluştur"""
        # Module bilgisini tenant schema'ya aktar (gerekirse)
        # Permission'ları oluştur
        from apps.modules.models import Module as TenantModule
        
        tenant_module, created = TenantModule.objects.get_or_create(
            code=module_code,
            defaults={
                'name': module.name,
                'description': module.description,
                'icon': module.icon,
                'url_prefix': module.url_prefix,
                'is_active': True,
            }
        )
        
        # Modülün available_permissions'ından permission'ları oluştur
        available_permissions = module.available_permissions or {}
        
        created_count = 0
        for perm_code, perm_name in available_permissions.items():
            permission, created = Permission.objects.get_or_create(
                module=tenant_module,
                code=perm_code,
                defaults={
                    'name': perm_name,
                    'description': f'{module.name} - {perm_name}',
                    'permission_type': 'module',
                    'is_active': True,
                }
            )
            if created:
                created_count += 1
        
        if created_count > 0:
            self.stdout.write(
                f'  [OK] {module_code}: {created_count} permission oluşturuldu'
            )

    def assign_permissions_to_admin(self, module_code):
        """Admin role'e modül yetkilerini ata"""
        admin_role = Role.objects.filter(code='admin', is_active=True).first()
        if not admin_role:
            self.stdout.write(
                self.style.WARNING(f'  [SKIP] {module_code}: Admin rolü bulunamadı')
            )
            return
        
        # Modül permission'larını al
        permissions = Permission.objects.filter(
            module__code=module_code,
            is_active=True
        )
        
        assigned_count = 0
        for permission in permissions:
            role_permission, created = RolePermission.objects.get_or_create(
                role=admin_role,
                permission=permission,
                defaults={'is_active': True}
            )
            if created:
                assigned_count += 1
        
        if assigned_count > 0:
            self.stdout.write(
                f'  [OK] {module_code}: Admin role\'e {assigned_count} yetki atandı'
            )

