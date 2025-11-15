"""
Reception Modülü Permission'larını Oluşturma Command
Tenant schema'da çalışır
"""
from django.core.management.base import BaseCommand
from apps.tenant_apps.core.models import Permission, Module, Role, RolePermission


class Command(BaseCommand):
    help = 'Reception modülü permission\'larını oluşturur (tenant schema)'

    def handle(self, *args, **options):
        try:
            module = Module.objects.get(code='reception')
        except Module.DoesNotExist:
            self.stdout.write(
                self.style.ERROR('[HATA] Reception modülü bulunamadı. Önce create_reception_module komutunu çalıştırın.')
            )
            return
        
        # Permission'ları oluştur
        permissions_data = [
            {'code': 'view', 'name': 'Görüntüleme', 'description': 'Rezervasyonları görüntüleme yetkisi'},
            {'code': 'add', 'name': 'Ekleme', 'description': 'Yeni rezervasyon oluşturma yetkisi'},
            {'code': 'edit', 'name': 'Düzenleme', 'description': 'Rezervasyon düzenleme yetkisi'},
            {'code': 'delete', 'name': 'Silme', 'description': 'Rezervasyon silme yetkisi'},
            {'code': 'checkin', 'name': 'Check-in', 'description': 'Check-in yapma yetkisi'},
            {'code': 'checkout', 'name': 'Check-out', 'description': 'Check-out yapma yetkisi'},
        ]
        
        created_count = 0
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
        
        self.stdout.write(
            self.style.SUCCESS(f'\n[OK] {created_count} permission oluşturuldu')
        )





