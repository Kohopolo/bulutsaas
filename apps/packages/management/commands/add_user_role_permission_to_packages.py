"""
Kullanıcı, Rol ve Yetki Yönetimi modüllerini tüm paketlere ekle
Bu modüller core modüller olduğu için tüm paketlerde aktif olmalı
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module
from apps.packages.models import Package, PackageModule


class Command(BaseCommand):
    help = 'Kullanıcı, Rol ve Yetki Yönetimi modüllerini tüm paketlere ekler'

    def handle(self, *args, **options):
        # Modülleri al
        users_module = Module.objects.filter(code='users').first()
        roles_module = Module.objects.filter(code='roles').first()
        permissions_module = Module.objects.filter(code='permissions').first()

        if not users_module or not roles_module or not permissions_module:
            self.stdout.write(
                self.style.ERROR('Modüller bulunamadı. Önce create_user_role_permission_modules komutunu çalıştırın.')
            )
            return

        # Tüm paketleri al
        packages = Package.objects.filter(is_active=True, is_deleted=False)

        added_count = 0
        updated_count = 0

        for package in packages:
            # Kullanıcı Yönetimi
            pm_users, created = PackageModule.objects.get_or_create(
                package=package,
                module=users_module,
                defaults={
                    'is_enabled': True,
                    'permissions': {
                        'view': True,
                        'add': True,
                        'edit': True,
                        'delete': True,
                        'assign_role': True,
                    },
                    'limits': {
                        'max_users': package.max_users if hasattr(package, 'max_users') else None,
                    },
                }
            )
            if not created:
                pm_users.is_enabled = True
                pm_users.save()
                updated_count += 1
            else:
                added_count += 1

            # Rol Yönetimi
            pm_roles, created = PackageModule.objects.get_or_create(
                package=package,
                module=roles_module,
                defaults={
                    'is_enabled': True,
                    'permissions': {
                        'view': True,
                        'add': True,
                        'edit': True,
                        'delete': True,
                        'assign_permission': True,
                    },
                    'limits': {},
                }
            )
            if not created:
                pm_roles.is_enabled = True
                pm_roles.save()
                updated_count += 1
            else:
                added_count += 1

            # Yetki Yönetimi
            pm_permissions, created = PackageModule.objects.get_or_create(
                package=package,
                module=permissions_module,
                defaults={
                    'is_enabled': True,
                    'permissions': {
                        'view': True,
                        'add': True,
                        'edit': True,
                        'delete': True,
                    },
                    'limits': {},
                }
            )
            if not created:
                pm_permissions.is_enabled = True
                pm_permissions.save()
                updated_count += 1
            else:
                added_count += 1

            self.stdout.write(
                self.style.SUCCESS(f'[OK] {package.name} paketine modüller eklendi/güncellendi.')
            )

        self.stdout.write(
            self.style.SUCCESS(
                f'\nToplam: {added_count} yeni modül-paket ilişkisi oluşturuldu, {updated_count} ilişki güncellendi.'
            )
        )

