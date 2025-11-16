"""
Raporlar modulu icin permission'lari olusturur
Tenant schema'da calisir
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import get_public_schema_name
from apps.modules.models import Module
from apps.tenant_apps.core.models import Permission, Role, RolePermission


class Command(BaseCommand):
    help = 'Raporlar modulu icin permission\'lari olusturur'
    
    def handle(self, *args, **options):
        # Public schema'da çalışmamalı
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(self.style.ERROR('Bu komut tenant schema\'da çalışmalıdır!'))
            return
        
        self.stdout.write('Raporlar modulu permission\'lari olusturuluyor...')
        
        # Modulu bul (public schema'dan)
        from django_tenants.utils import schema_context
        with schema_context(get_public_schema_name()):
            try:
                module = Module.objects.get(code='reports', is_active=True)
            except Module.DoesNotExist:
                self.stdout.write(self.style.ERROR('Raporlar modulu bulunamadi! Once modulu olusturun: python manage.py create_reports_module'))
                return
        
        # Tenant schema'da modulu olustur (eger yoksa)
        tenant_module, _ = Module.objects.get_or_create(
            code=module.code,
            defaults={
                'name': module.name,
                'description': module.description,
                'icon': module.icon,
                'category': module.category,
                'app_name': module.app_name,
                'url_prefix': module.url_prefix,
                'available_permissions': module.available_permissions,
                'is_active': True,
                'is_core': module.is_core,
                'sort_order': module.sort_order,
            }
        )
        
        # Permission'lari tanimla
        permissions = [
            {
                'name': 'Rapor Goruntuleme',
                'code': 'view',
                'description': 'Raporlari goruntuleme yetkisi',
                'permission_type': 'view',
                'is_active': True,
                'is_system': True,
                'sort_order': 1,
            },
            {
                'name': 'Rapor Export',
                'code': 'export',
                'description': 'Raporlari export etme yetkisi',
                'permission_type': 'export',
                'is_active': True,
                'is_system': False,
                'sort_order': 2,
            },
        ]
        
        created_count = 0
        for perm_data in permissions:
            permission, created = Permission.objects.get_or_create(
                module=tenant_module,
                code=perm_data['code'],
                defaults=perm_data
            )
            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] {permission.name} permission\'i olusturuldu')
                )
            else:
                # Mevcut permission'i guncelle
                for key, value in perm_data.items():
                    if key != 'code':
                        setattr(permission, key, value)
                permission.save()
                self.stdout.write(
                    self.style.WARNING(f'[UPDATE] {permission.name} permission\'i guncellendi')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n{created_count} permission olusturuldu')
        )
        
        # Admin rolune tum yetkileri ata
        admin_role = Role.objects.filter(code='admin', is_active=True).first()
        if admin_role:
            reports_permissions = Permission.objects.filter(module=tenant_module, is_active=True)
            assigned_count = 0
            for permission in reports_permissions:
                role_permission, created = RolePermission.objects.get_or_create(
                    role=admin_role,
                    permission=permission,
                    defaults={'is_active': True}
                )
                if created:
                    assigned_count += 1
            if assigned_count > 0:
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] Admin rolune {assigned_count} raporlar yetkisi atandi')
                )
        
        self.stdout.write(self.style.SUCCESS('\n[OK] Raporlar modulu permission\'lari hazir!'))

