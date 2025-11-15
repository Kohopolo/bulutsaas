"""
Ödeme Yönetimi Modülü İzinlerini Oluşturma Komutu
Tenant schema içinde çalışır
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from django.db import connection
from apps.modules.models import Module
from apps.tenant_apps.core.models import Permission, Role, RolePermission


class Command(BaseCommand):
    help = 'Ödeme Yönetimi modülü için izinleri oluşturur (tenant schema içinde)'

    def handle(self, *args, **options):
        # Public schema'da çalıştırılmamalı
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(self.style.WARNING('[WARN] Bu komut tenant schema icinde calistirilmalidir.'))
            return

        # Payment Management modülünü public schema'dan al
        with schema_context(get_public_schema_name()):
            try:
                payment_module = Module.objects.get(code='payment_management')
            except Module.DoesNotExist:
                self.stdout.write(self.style.ERROR('[ERROR] Payment Management modulu bulunamadi. Once create_payment_management_module komutunu calistirin.'))
                return

        # Tenant schema'da izinleri oluştur
        permissions_data = [
            {'code': 'view', 'name': 'Gateway Görüntüleme', 'permission_type': 'view'},
            {'code': 'add', 'name': 'Gateway Ekleme', 'permission_type': 'add'},
            {'code': 'edit', 'name': 'Gateway Düzenleme', 'permission_type': 'edit'},
            {'code': 'delete', 'name': 'Gateway Silme', 'permission_type': 'delete'},
            {'code': 'transaction_view', 'name': 'İşlem Görüntüleme', 'permission_type': 'view'},
        ]

        created_count = 0
        updated_count = 0

        for perm_data in permissions_data:
            # Module'ü tenant schema'da oluştur (eğer yoksa)
            tenant_module, _ = Module.objects.get_or_create(
                code=payment_module.code,
                defaults={
                    'name': payment_module.name,
                    'description': payment_module.description,
                    'icon': payment_module.icon,
                    'category': payment_module.category,
                    'app_name': payment_module.app_name,
                    'url_prefix': payment_module.url_prefix,
                    'available_permissions': payment_module.available_permissions,
                    'is_active': True,
                    'is_core': payment_module.is_core,
                    'sort_order': payment_module.sort_order,
                }
            )

            permission, created = Permission.objects.get_or_create(
                module=tenant_module,
                code=perm_data['code'],
                defaults={
                    'name': perm_data['name'],
                    'description': f'Ödeme Yönetimi - {perm_data["name"]}',
                    'permission_type': perm_data['permission_type'] if perm_data['permission_type'] in ['view', 'add', 'edit', 'delete', 'export', 'import', 'approve', 'cancel'] else 'other',
                    'is_active': True,
                    'is_system': True,
                }
            )

            if created:
                created_count += 1
            else:
                # Mevcut izni güncelle
                permission.name = perm_data['name']
                permission.permission_type = perm_data['permission_type']
                permission.is_active = True
                permission.save()
                updated_count += 1

        self.stdout.write(self.style.SUCCESS(f'[OK] Ödeme Yönetimi modulu izinleri olusturuldu: {created_count} yeni, {updated_count} guncellendi'))
        
        # Admin rolüne otomatik yetki atama
        try:
            from django.core.management import call_command
            call_command('assign_module_permissions_to_admin', '--module-code', 'payment_management', verbosity=0)
            self.stdout.write(self.style.SUCCESS('[OK] Ödeme Yönetimi modulu yetkileri admin rolune otomatik atandi'))
        except Exception as e:
            self.stdout.write(self.style.WARNING(f'[WARN] Admin rolune yetki atama basarisiz: {str(e)}'))

