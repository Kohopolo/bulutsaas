"""
Fiyat Hesaplama modülü için permission'ları oluşturur
Tenant schema'da çalışır
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import get_public_schema_name, schema_context
from apps.modules.models import Module
from apps.tenant_apps.core.models import Permission, Role, RolePermission


class Command(BaseCommand):
    help = 'Fiyat Hesaplama modülü için permission\'ları oluşturur'
    
    def handle(self, *args, **options):
        # Public schema'da çalışmamalı
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(self.style.ERROR('Bu komut tenant schema\'da çalışmalıdır!'))
            return
        
        self.stdout.write('Fiyat Hesaplama modülü permission\'ları oluşturuluyor...')
        
        # Modülü bul (public schema'dan)
        with schema_context(get_public_schema_name()):
            try:
                module = Module.objects.get(code='price_calculator', is_active=True)
            except Module.DoesNotExist:
                self.stdout.write(self.style.ERROR('Fiyat Hesaplama modülü bulunamadı! Önce modülü oluşturun: python manage.py create_price_calculator_module'))
                return
        
        # Tenant schema'da modülü oluştur (eğer yoksa)
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
        
        # Permission'ları tanımla
        permissions = [
            {
                'name': 'Fiyat Hesaplama Görüntüleme',
                'code': 'view',
                'description': 'Fiyat hesaplama modülünü görüntüleme yetkisi',
                'permission_type': 'view',
                'is_active': True,
                'is_system': True,
                'sort_order': 1,
            },
            {
                'name': 'Fiyat Hesaplama Kullanma',
                'code': 'use',
                'description': 'Fiyat hesaplama modülünü kullanma yetkisi',
                'permission_type': 'use',
                'is_active': True,
                'is_system': True,
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
                    self.style.SUCCESS(f'[OK] {permission.name} permission\'i oluşturuldu')
                )
            else:
                # Mevcut permission'ı güncelle
                for key, value in perm_data.items():
                    if key != 'code':
                        setattr(permission, key, value)
                permission.save()
                self.stdout.write(
                    self.style.WARNING(f'[UPDATE] {permission.name} permission\'i güncellendi')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n{created_count} permission oluşturuldu')
        )
        
        # Admin rolüne tüm yetkileri ata
        admin_role = Role.objects.filter(code='admin', is_active=True).first()
        if admin_role:
            price_calculator_permissions = Permission.objects.filter(module=tenant_module, is_active=True)
            assigned_count = 0
            for permission in price_calculator_permissions:
                role_permission, created = RolePermission.objects.get_or_create(
                    role=admin_role,
                    permission=permission,
                    defaults={'is_active': True}
                )
                if created:
                    assigned_count += 1
            if assigned_count > 0:
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] Admin rolüne {assigned_count} fiyat hesaplama yetkisi atandı')
                )
        
        self.stdout.write(self.style.SUCCESS('\n[OK] Fiyat Hesaplama modülü permission\'ları hazır!'))

