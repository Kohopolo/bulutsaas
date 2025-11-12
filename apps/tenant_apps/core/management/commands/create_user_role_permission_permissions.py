"""
Kullanıcı, Rol ve Yetki Yönetimi modülleri için Permission kayıtlarını oluştur
Her tenant schema'da çalıştırılmalı
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import get_public_schema_name
from apps.modules.models import Module
from apps.tenant_apps.core.models import Permission


class Command(BaseCommand):
    help = 'Kullanıcı, Rol ve Yetki Yönetimi modülleri için Permission kayıtlarını oluşturur'

    def handle(self, *args, **options):
        # Public schema'da değilse devam et
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(
                self.style.WARNING('Bu komut public schema\'da çalıştırılamaz. Tenant schema\'da çalıştırın.')
            )
            return
        
        created_count = 0
        updated_count = 0

        # Public schema'ya geçip modülleri al
        from django_tenants.utils import schema_context
        with schema_context(get_public_schema_name()):
            users_module = Module.objects.filter(code='users').first()
            roles_module = Module.objects.filter(code='roles').first()
            permissions_module = Module.objects.filter(code='permissions').first()
        
        if not users_module or not roles_module or not permissions_module:
            self.stdout.write(
                self.style.ERROR('Modüller bulunamadı. Önce create_user_role_permission_modules komutunu çalıştırın.')
            )
            return

        # Kullanıcı Yönetimi Modülü
        if users_module:
            permissions_data = [
                {'code': 'view', 'name': 'Kullanıcı Görüntüleme', 'permission_type': 'view'},
                {'code': 'add', 'name': 'Kullanıcı Ekleme', 'permission_type': 'add'},
                {'code': 'edit', 'name': 'Kullanıcı Düzenleme', 'permission_type': 'edit'},
                {'code': 'delete', 'name': 'Kullanıcı Silme', 'permission_type': 'delete'},
                {'code': 'assign_role', 'name': 'Rol Atama', 'permission_type': 'other'},
            ]

            for perm_data in permissions_data:
                perm, created = Permission.objects.get_or_create(
                    module=users_module,
                    code=perm_data['code'],
                    defaults={
                        'name': perm_data['name'],
                        'permission_type': perm_data['permission_type'],
                        'is_active': True,
                        'is_system': True,
                        'sort_order': len([p for p in permissions_data if permissions_data.index(p) < permissions_data.index(perm_data)]) + 1,
                    }
                )
                if created:
                    created_count += 1
                    self.stdout.write(self.style.SUCCESS(f'[OK] {perm.name} yetkisi oluşturuldu.'))
                else:
                    updated_count += 1

        # Rol Yönetimi Modülü
        if roles_module:
            permissions_data = [
                {'code': 'view', 'name': 'Rol Görüntüleme', 'permission_type': 'view'},
                {'code': 'add', 'name': 'Rol Ekleme', 'permission_type': 'add'},
                {'code': 'edit', 'name': 'Rol Düzenleme', 'permission_type': 'edit'},
                {'code': 'delete', 'name': 'Rol Silme', 'permission_type': 'delete'},
                {'code': 'assign_permission', 'name': 'Yetki Atama', 'permission_type': 'other'},
            ]

            for perm_data in permissions_data:
                perm, created = Permission.objects.get_or_create(
                    module=roles_module,
                    code=perm_data['code'],
                    defaults={
                        'name': perm_data['name'],
                        'permission_type': perm_data['permission_type'],
                        'is_active': True,
                        'is_system': True,
                        'sort_order': len([p for p in permissions_data if permissions_data.index(p) < permissions_data.index(perm_data)]) + 1,
                    }
                )
                if created:
                    created_count += 1
                    self.stdout.write(self.style.SUCCESS(f'[OK] {perm.name} yetkisi oluşturuldu.'))
                else:
                    updated_count += 1

        # Yetki Yönetimi Modülü
        if permissions_module:
            permissions_data = [
                {'code': 'view', 'name': 'Yetki Görüntüleme', 'permission_type': 'view'},
                {'code': 'add', 'name': 'Yetki Ekleme', 'permission_type': 'add'},
                {'code': 'edit', 'name': 'Yetki Düzenleme', 'permission_type': 'edit'},
                {'code': 'delete', 'name': 'Yetki Silme', 'permission_type': 'delete'},
            ]

            for perm_data in permissions_data:
                perm, created = Permission.objects.get_or_create(
                    module=permissions_module,
                    code=perm_data['code'],
                    defaults={
                        'name': perm_data['name'],
                        'permission_type': perm_data['permission_type'],
                        'is_active': True,
                        'is_system': True,
                        'sort_order': len([p for p in permissions_data if permissions_data.index(p) < permissions_data.index(perm_data)]) + 1,
                    }
                )
                if created:
                    created_count += 1
                    self.stdout.write(self.style.SUCCESS(f'[OK] {perm.name} yetkisi oluşturuldu.'))
                else:
                    updated_count += 1

        self.stdout.write(
            self.style.SUCCESS(
                f'\nToplam: {created_count} yeni yetki oluşturuldu, {updated_count} yetki güncellendi.'
            )
        )
        
        # Admin rolüne otomatik yetki atama
        try:
            from django.core.management import call_command
            call_command('assign_module_permissions_to_admin', '--module-code', 'users', verbosity=0)
            call_command('assign_module_permissions_to_admin', '--module-code', 'roles', verbosity=0)
            call_command('assign_module_permissions_to_admin', '--module-code', 'permissions', verbosity=0)
            self.stdout.write(self.style.SUCCESS('[OK] Users, Roles ve Permissions modulu yetkileri admin rolune otomatik atandi'))
        except Exception as e:
            self.stdout.write(self.style.WARNING(f'[WARN] Admin rolune yetki atama basarisiz: {str(e)}'))

