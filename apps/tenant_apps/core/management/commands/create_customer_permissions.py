"""
Customer Modülü İzinlerini Oluşturma Komutu
Tenant schema içinde çalışır
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from django.db import connection
from apps.modules.models import Module
from apps.tenant_apps.core.models import Permission


class Command(BaseCommand):
    help = 'Customer modülü için izinleri oluşturur (tenant schema içinde)'

    def handle(self, *args, **options):
        # Public schema'da çalıştırılmamalı
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(self.style.WARNING('[WARN] Bu komut tenant schema icinde calistirilmalidir.'))
            return

        # Customer modülünü public schema'dan al
        with schema_context(get_public_schema_name()):
            try:
                customer_module = Module.objects.get(code='customers')
            except Module.DoesNotExist:
                self.stdout.write(self.style.ERROR('[ERROR] Customer modulu bulunamadi. Once create_customer_module komutunu calistirin.'))
                return

        # Tenant schema'da izinleri oluştur
        permissions_data = [
            {'code': 'view', 'name': 'Müşteri Görüntüleme', 'permission_type': 'view'},
            {'code': 'add', 'name': 'Müşteri Ekleme', 'permission_type': 'add'},
            {'code': 'edit', 'name': 'Müşteri Düzenleme', 'permission_type': 'edit'},
            {'code': 'delete', 'name': 'Müşteri Silme', 'permission_type': 'delete'},
            {'code': 'export', 'name': 'Müşteri Dışa Aktarma', 'permission_type': 'export'},
            {'code': 'view_notes', 'name': 'Müşteri Notlarını Görüntüleme', 'permission_type': 'view'},
            {'code': 'add_notes', 'name': 'Müşteri Notu Ekleme', 'permission_type': 'add'},
            {'code': 'view_loyalty', 'name': 'Sadakat Puanı Görüntüleme', 'permission_type': 'view'},
            {'code': 'manage_loyalty', 'name': 'Sadakat Puanı Yönetimi', 'permission_type': 'other'},
        ]

        created_count = 0
        updated_count = 0

        for perm_data in permissions_data:
            # Module'ü tenant schema'da oluştur (eğer yoksa)
            tenant_module, _ = Module.objects.get_or_create(
                code=customer_module.code,
                defaults={
                    'name': customer_module.name,
                    'description': customer_module.description,
                    'icon': customer_module.icon,
                    'category': customer_module.category,
                    'app_name': customer_module.app_name,
                    'url_prefix': customer_module.url_prefix,
                    'available_permissions': customer_module.available_permissions,
                    'is_active': True,
                    'is_core': True,
                    'sort_order': customer_module.sort_order,
                }
            )

            permission, created = Permission.objects.get_or_create(
                module=tenant_module,
                code=perm_data['code'],
                defaults={
                    'name': perm_data['name'],
                    'permission_type': perm_data['permission_type'],
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

        self.stdout.write(self.style.SUCCESS(f'[OK] Customer modulu izinleri olusturuldu: {created_count} yeni, {updated_count} guncellendi'))
        
        # Admin rolüne otomatik yetki atama
        try:
            from django.core.management import call_command
            call_command('assign_module_permissions_to_admin', '--module-code', 'customers', verbosity=0)
            self.stdout.write(self.style.SUCCESS('[OK] Customer modulu yetkileri admin rolune otomatik atandi'))
        except Exception as e:
            self.stdout.write(self.style.WARNING(f'[WARN] Admin rolune yetki atama basarisiz: {str(e)}'))

