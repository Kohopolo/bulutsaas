"""
Management Command: Bungalovs Modülü Permission'ları Oluştur
Tenant schema'da bungalovs modülü permission'larını oluşturur
"""
from django.core.management.base import BaseCommand
from django.db import connection
from apps.tenant_apps.core.models import Permission, Role, RolePermission
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Bungalovs modülü permission\'larını tenant schema\'da oluşturur'

    def add_arguments(self, parser):
        parser.add_argument(
            '--schema',
            type=str,
            help='Tenant schema adı (belirtilmezse connection.schema_name kullanılır)',
        )

    def handle(self, *args, **options):
        schema_name = options.get('schema') or connection.schema_name

        if schema_name == 'public':
            self.stdout.write(self.style.WARNING('Bu komut tenant schema\'da çalıştırılmalıdır.'))
            return

        self.stdout.write(f'[{schema_name.upper()}] Bungalovs modülü permission\'ları oluşturuluyor...')

        # Modülü bul
        try:
            module = Module.objects.get(code='bungalovs', is_active=True)
        except Module.DoesNotExist:
            self.stdout.write(self.style.ERROR('Bungalovs modülü bulunamadı. Önce create_bungalovs_module komutunu çalıştırın.'))
            return

        # Permission'ları oluştur
        permissions_data = [
            {'code': 'view', 'name': 'Görüntüleme', 'description': 'Bungalov ve rezervasyonları görüntüleme yetkisi'},
            {'code': 'add', 'name': 'Ekleme', 'description': 'Yeni bungalov ve rezervasyon oluşturma yetkisi'},
            {'code': 'edit', 'name': 'Düzenleme', 'description': 'Bungalov ve rezervasyon düzenleme yetkisi'},
            {'code': 'delete', 'name': 'Silme', 'description': 'Bungalov ve rezervasyon silme yetkisi'},
            {'code': 'voucher', 'name': 'Voucher Oluşturma', 'description': 'Rezervasyon voucher\'ları oluşturma yetkisi'},
            {'code': 'payment', 'name': 'Ödeme İşlemleri', 'description': 'Ödeme alma ve yönetimi yetkisi'},
        ]
        
        created_count = 0
        skipped_count = 0
        for perm_data in permissions_data:
            permission, created = Permission.objects.get_or_create(
                module=module,
                code=perm_data['code'],
                defaults={
                    'name': perm_data['name'],
                    'description': perm_data['description'],
                    'is_active': True,
                }
            )
            if created:
                created_count += 1
                self.stdout.write(self.style.SUCCESS(f'  [OK] {permission.name} permission oluşturuldu'))
            else:
                skipped_count += 1
                self.stdout.write(self.style.WARNING(f'  [SKIP] {permission.name} permission zaten mevcut'))
        
        # Admin rolüne tüm yetkileri ata
        admin_role = Role.objects.filter(code='admin').first()
        if admin_role:
            permissions = Permission.objects.filter(module=module, is_active=True)
            assigned_count = 0
            for permission in permissions:
                role_permission, created = RolePermission.objects.get_or_create(
                    role=admin_role,
                    permission=permission,
                    defaults={'is_active': True}
                )
                if created:
                    assigned_count += 1
            
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Admin rolüne {assigned_count} yetki atandı')
            )
        else:
            self.stdout.write(self.style.WARNING('Admin rolü bulunamadı. Yetkiler manuel olarak atanmalı.'))
        
        self.stdout.write(
            self.style.SUCCESS(f'\n[OK] {created_count} permission oluşturuldu, {skipped_count} permission zaten mevcuttu.')
        )

