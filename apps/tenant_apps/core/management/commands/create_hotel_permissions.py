"""
Hotels modülü için permission'ları oluştur
Tenant schema içinde çalışır
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import get_public_schema_name
from django.db import connection
from apps.tenant_apps.core.models import Permission, Role, RolePermission
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Hotels modülü için permission\'ları oluşturur (tenant schema içinde)'

    def handle(self, *args, **options):
        # Public schema'da çalıştırılmamalı
        if connection.schema_name == get_public_schema_name():
            self.stdout.write(self.style.WARNING('[UYARI] Bu komut tenant schema içinde çalıştırılmalıdır.'))
            return
        
        try:
            module = Module.objects.get(code='hotels')
        except Module.DoesNotExist:
            self.stdout.write(self.style.ERROR('[HATA] Hotels modülü bulunamadı. Önce create_hotel_module komutunu çalıştırın.'))
            return
        
        permissions = [
            ('view', 'Görüntüleme', 'Otel modülü görüntüleme yetkisi', 'view'),
            ('add', 'Ekleme', 'Otel modülü ekleme yetkisi', 'add'),
            ('edit', 'Düzenleme', 'Otel modülü düzenleme yetkisi', 'edit'),
            ('delete', 'Silme', 'Otel modülü silme yetkisi', 'delete'),
            ('admin', 'Yönetici', 'Otel modülü yönetici yetkisi', 'other'),
        ]
        
        created_count = 0
        for code, name, description, perm_type in permissions:
            permission, created = Permission.objects.get_or_create(
                module=module,
                code=code,
                defaults={
                    'name': name,
                    'description': description,
                    'permission_type': perm_type,
                    'is_active': True,
                }
            )
            if created:
                created_count += 1
        
        self.stdout.write(self.style.SUCCESS(f'[OK] {created_count} permission oluşturuldu.'))
        
        # Admin rolüne otomatik ata
        try:
            admin_role = Role.objects.filter(code='admin', is_active=True).first()
            if admin_role:
                assigned_count = 0
                for code, _, _, _ in permissions:
                    permission = Permission.objects.filter(module=module, code=code, is_active=True).first()
                    if permission:
                        role_permission, created = RolePermission.objects.get_or_create(
                            role=admin_role,
                            permission=permission,
                            defaults={'is_active': True}
                        )
                        if created:
                            assigned_count += 1
                self.stdout.write(self.style.SUCCESS(f'[OK] Admin rolüne {assigned_count} permission atandı.'))
            else:
                self.stdout.write(self.style.WARNING('[UYARI] Admin rolü bulunamadı.'))
        except Exception as e:
            self.stdout.write(self.style.WARNING(f'[UYARI] Admin rolüne permission atanırken hata: {e}'))

