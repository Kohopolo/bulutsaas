"""
Resepsiyon Modülü Permission'ları Oluşturma Command
Tenant schema'da permission'ları oluşturur
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from apps.modules.models import Module
from apps.tenant_apps.core.models import Permission, Role, RolePermission
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Resepsiyon modülü permission\'larını oluşturur'

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
        all_tenants = options.get('all_tenants')

        if all_tenants:
            tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
            total = tenants.count()
            self.stdout.write(f'\n{total} tenant için permission\'lar oluşturuluyor...')
            for idx, tenant in enumerate(tenants, 1):
                self.stdout.write(f'\n[{idx}/{total}] {tenant.schema_name} - {tenant.name}')
                self.create_permissions_for_tenant(tenant.schema_name)
        elif tenant_schema:
            self.create_permissions_for_tenant(tenant_schema)
        else:
            self.stdout.write(
                self.style.WARNING('Lütfen --tenant <schema_name> veya --all-tenants argümanını kullanın.')
            )

    def create_permissions_for_tenant(self, schema_name):
        """Belirli bir tenant için permission'ları oluştur"""
        self.stdout.write(f'[{schema_name}] Permission\'lar oluşturuluyor...')
        try:
            with schema_context(schema_name):
                # Public schema'dan modül bilgisini al
                with schema_context(get_public_schema_name()):
                    try:
                        module = Module.objects.get(code='reception')
                    except Module.DoesNotExist:
                        self.stdout.write(
                            self.style.WARNING(f'  [SKIP] Reception modülü bulunamadı')
                        )
                        return
                
                # Tenant schema'da modülü oluştur (gerekirse)
                from apps.modules.models import Module as TenantModule
                tenant_module, created = TenantModule.objects.get_or_create(
                    code='reception',
                    defaults={
                        'name': module.name,
                        'description': module.description,
                        'icon': module.icon,
                        'url_prefix': module.url_prefix,
                        'is_active': True,
                    }
                )
                
                # Permission'ları oluştur
                permissions_data = {
                    'view': 'Görüntüleme',
                    'add': 'Ekleme',
                    'edit': 'Düzenleme',
                    'delete': 'Silme',
                    'checkin': 'Check-in',
                    'checkout': 'Check-out',
                    'manage': 'Yönetim',
                    'admin': 'Yönetici',
                }
                
                created_count = 0
                for perm_code, perm_name in permissions_data.items():
                    permission, created = Permission.objects.get_or_create(
                        module=tenant_module,
                        code=perm_code,
                        defaults={
                            'name': perm_name,
                            'description': f'Resepsiyon - {perm_name}',
                            'permission_type': 'module',
                            'is_active': True,
                        }
                    )
                    if created:
                        created_count += 1
                
                if created_count > 0:
                    self.stdout.write(
                        f'  [OK] {created_count} permission oluşturuldu'
                    )
                
                # Admin role'e tüm yetkileri ata
                admin_role = Role.objects.filter(code='admin', is_active=True).first()
                if admin_role:
                    assigned_count = 0
                    for perm_code in permissions_data.keys():
                        permission = Permission.objects.filter(
                            module=tenant_module,
                            code=perm_code,
                            is_active=True
                        ).first()
                        
                        if permission:
                            role_permission, created = RolePermission.objects.get_or_create(
                                role=admin_role,
                                permission=permission,
                                defaults={'is_active': True}
                            )
                            if created:
                                assigned_count += 1
                    
                    if assigned_count > 0:
                        self.stdout.write(
                            f'  [OK] Admin role\'e {assigned_count} yetki atandı'
                        )
                
                self.stdout.write(
                    self.style.SUCCESS(f'[{schema_name}] Permission\'lar oluşturuldu')
                )
        
        except Exception as e:
            self.stdout.write(
                self.style.ERROR(f'[{schema_name}] HATA: {str(e)}')
            )

