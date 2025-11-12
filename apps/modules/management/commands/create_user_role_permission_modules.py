"""
Kullanıcı, Rol ve Yetki Yönetimi modüllerini oluştur
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Kullanıcı, Rol ve Yetki Yönetimi modüllerini Module tablosuna ekler'

    def handle(self, *args, **options):
        modules_data = [
            {
                'name': 'Kullanıcı Yönetimi',
                'code': 'users',
                'description': 'Tenant kullanıcılarını yönetme modülü',
                'icon': 'fas fa-users',
                'category': 'other',
                'app_name': 'apps.tenant_apps.core',
                'url_prefix': '/users/',
                'available_permissions': {
                    'view': 'Kullanıcı görüntüleme',
                    'add': 'Kullanıcı ekleme',
                    'edit': 'Kullanıcı düzenleme',
                    'delete': 'Kullanıcı silme',
                    'assign_role': 'Rol atama',
                },
                'is_core': True,
                'sort_order': 10,
            },
            {
                'name': 'Rol Yönetimi',
                'code': 'roles',
                'description': 'Rol tanımlama ve yönetme modülü',
                'icon': 'fas fa-shield-alt',
                'category': 'other',
                'app_name': 'apps.tenant_apps.core',
                'url_prefix': '/roles/',
                'available_permissions': {
                    'view': 'Rol görüntüleme',
                    'add': 'Rol ekleme',
                    'edit': 'Rol düzenleme',
                    'delete': 'Rol silme',
                    'assign_permission': 'Yetki atama',
                },
                'is_core': True,
                'sort_order': 11,
            },
            {
                'name': 'Yetki Yönetimi',
                'code': 'permissions',
                'description': 'Yetki tanımlama ve yönetme modülü',
                'icon': 'fas fa-key',
                'category': 'other',
                'app_name': 'apps.tenant_apps.core',
                'url_prefix': '/permissions/',
                'available_permissions': {
                    'view': 'Yetki görüntüleme',
                    'add': 'Yetki ekleme',
                    'edit': 'Yetki düzenleme',
                    'delete': 'Yetki silme',
                },
                'is_core': True,
                'sort_order': 12,
            },
        ]

        created_count = 0
        updated_count = 0

        for module_data in modules_data:
            module, created = Module.objects.update_or_create(
                code=module_data['code'],
                defaults={
                    'name': module_data['name'],
                    'description': module_data['description'],
                    'icon': module_data['icon'],
                    'category': module_data['category'],
                    'app_name': module_data['app_name'],
                    'url_prefix': module_data['url_prefix'],
                    'available_permissions': module_data['available_permissions'],
                    'is_core': module_data['is_core'],
                    'sort_order': module_data['sort_order'],
                    'is_active': True,
                }
            )

            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] {module.name} modülü oluşturuldu.')
                )
            else:
                updated_count += 1
                self.stdout.write(
                    self.style.WARNING(f'[GÜNCELLENDİ] {module.name} modülü güncellendi.')
                )

        self.stdout.write(
            self.style.SUCCESS(
                f'\nToplam: {created_count} yeni modül oluşturuldu, {updated_count} modül güncellendi.'
            )
        )

