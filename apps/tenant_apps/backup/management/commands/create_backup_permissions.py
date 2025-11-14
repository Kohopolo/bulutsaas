"""
Yedekleme Modülü Permission'larını Oluşturma Command
Tenant schema'da çalışır
"""
from django.core.management.base import BaseCommand
from apps.tenant_apps.core.models import Permission, Role, RolePermission
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Yedekleme modülü permission\'larını oluşturur (tenant schema)'

    def add_arguments(self, parser):
        parser.add_argument(
            '--schema',
            type=str,
            help='Schema adı (tenant schema için)',
            default=None
        )

    def handle(self, *args, **options):
        schema_name = options.get('schema')
        
        # Public schema'da çalıştırılıyorsa uyarı ver
        from django.db import connection
        from django_tenants.utils import get_public_schema_name
        
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(
                self.style.WARNING('[UYARI] Bu komut tenant schema\'da çalıştırılmalıdır.')
            )
            self.stdout.write(
                self.style.WARNING('Public schema\'da Permission modeli bulunmamaktadır.')
            )
            return
        
        try:
            module = Module.objects.get(code='backup')
        except Module.DoesNotExist:
            self.stdout.write(
                self.style.ERROR('[HATA] Yedekleme modülü bulunamadı. Önce create_backup_module komutunu çalıştırın.')
            )
            return
        
        # Permission'ları oluştur
        permissions_data = [
            {'code': 'view', 'name': 'Görüntüleme', 'description': 'Yedeklemeleri görüntüleme yetkisi'},
            {'code': 'add', 'name': 'Yedekleme Oluşturma', 'description': 'Yeni yedekleme oluşturma yetkisi'},
            {'code': 'edit', 'name': 'Düzenleme', 'description': 'Yedekleme düzenleme yetkisi'},
            {'code': 'delete', 'name': 'Silme', 'description': 'Yedekleme silme yetkisi'},
            {'code': 'download', 'name': 'İndirme', 'description': 'Yedekleme indirme yetkisi'},
        ]
        
        created_count = 0
        updated_count = 0
        
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
                self.stdout.write(
                    self.style.SUCCESS(f'  [OK] {permission.name} permission oluşturuldu')
                )
            else:
                # Mevcut permission'ı güncelle
                permission.name = perm_data['name']
                permission.description = perm_data['description']
                permission.is_active = True
                permission.save()
                updated_count += 1
        
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
            
            if assigned_count > 0:
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] Admin rolüne {assigned_count} yetki atandı')
                )
        else:
            self.stdout.write(
                self.style.WARNING('[WARN] Admin rolü bulunamadı, yetkiler atanmadı')
            )
        
        # Admin rolüne otomatik yetki atama (alternatif yöntem)
        try:
            from django.core.management import call_command
            call_command('assign_module_permissions_to_admin', '--module-code', 'backup', verbosity=0)
            self.stdout.write(self.style.SUCCESS('[OK] Yedekleme modülü yetkileri admin rolüne otomatik atandı'))
        except Exception as e:
            self.stdout.write(self.style.WARNING(f'[WARN] Admin rolüne yetki atama başarısız: {str(e)}'))
        
        self.stdout.write(
            self.style.SUCCESS(f'\n[OK] {created_count} permission oluşturuldu, {updated_count} permission güncellendi')
        )

