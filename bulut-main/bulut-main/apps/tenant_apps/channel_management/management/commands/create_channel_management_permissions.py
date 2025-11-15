"""
Kanal Yönetimi Yetkileri Oluşturma Komutu
Tenant bazlı yetki tanımlamaları
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import get_public_schema_name, schema_context
from apps.modules.models import Module
from apps.tenant_apps.core.models import Permission, Role, RolePermission


class Command(BaseCommand):
    help = 'Kanal Yönetimi modülü için yetkileri oluşturur (mevcut tenant için)'

    def handle(self, *args, **options):
        schema_name = connection.schema_name
        
        if schema_name == get_public_schema_name():
            self.stdout.write(
                self.style.ERROR('Bu komut public schema\'da çalıştırılamaz. Tenant schema\'sında çalıştırın.')
            )
            return
        
        # Channel Management modülünü public schema'dan al
        with schema_context(get_public_schema_name()):
            try:
                channel_module = Module.objects.get(code='channel_management')
            except Module.DoesNotExist:
                self.stdout.write(
                    self.style.ERROR('[HATA] Kanal Yönetimi modülü bulunamadı. Önce create_channel_management_module komutunu çalıştırın.')
                )
                return
        
        # Tenant schema'da modülü oluştur (eğer yoksa)
        tenant_module, _ = Module.objects.get_or_create(
            code=channel_module.code,
            defaults={
                'name': channel_module.name,
                'description': channel_module.description,
                'icon': channel_module.icon,
                'category': channel_module.category,
                'app_name': channel_module.app_name,
                'url_prefix': channel_module.url_prefix,
                'available_permissions': channel_module.available_permissions,
                'is_active': True,
                'is_core': channel_module.is_core,
                'sort_order': channel_module.sort_order,
            }
        )
        
        # Permission'ları oluştur
        permissions_data = [
            {'code': 'view', 'name': 'Kanal Yönetimi Görüntüleme', 'description': 'Kanal yönetimi modülünü görüntüleme yetkisi', 'permission_type': 'view'},
            {'code': 'add', 'name': 'Kanal Konfigürasyonu Ekleme', 'description': 'Yeni kanal konfigürasyonu oluşturma yetkisi', 'permission_type': 'add'},
            {'code': 'edit', 'name': 'Kanal Konfigürasyonu Düzenleme', 'description': 'Kanal konfigürasyonu düzenleme yetkisi', 'permission_type': 'edit'},
            {'code': 'delete', 'name': 'Kanal Konfigürasyonu Silme', 'description': 'Kanal konfigürasyonu silme yetkisi', 'permission_type': 'delete'},
            {'code': 'sync', 'name': 'Senkronizasyon Başlatma', 'description': 'Kanal senkronizasyonu başlatma yetkisi', 'permission_type': 'other'},
            {'code': 'pricing', 'name': 'Fiyat Yönetimi', 'description': 'Kanal fiyat yönetimi yetkisi', 'permission_type': 'other'},
            {'code': 'reservation', 'name': 'Rezervasyon Yönetimi', 'description': 'Kanal rezervasyon yönetimi yetkisi', 'permission_type': 'other'},
            {'code': 'commission', 'name': 'Komisyon Yönetimi', 'description': 'Kanal komisyon yönetimi yetkisi', 'permission_type': 'other'},
        ]
        
        created_count = 0
        for perm_data in permissions_data:
            permission, created = Permission.objects.get_or_create(
                module=tenant_module,
                code=perm_data['code'],
                defaults={
                    'name': perm_data['name'],
                    'description': perm_data['description'],
                    'permission_type': perm_data['permission_type'] if perm_data['permission_type'] in ['view', 'add', 'edit', 'delete', 'export', 'import', 'approve', 'cancel'] else 'other',
                    'is_active': True,
                    'is_system': True,
                }
            )
            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'  [OK] {permission.name} permission oluşturuldu')
                )
        
        # Admin rolüne tüm yetkileri ata
        admin_role = Role.objects.filter(code='admin', is_active=True).first()
        if admin_role:
            permissions = Permission.objects.filter(module=tenant_module, is_active=True)
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
            self.stdout.write(
                self.style.WARNING('[SKIP] Admin rolü bulunamadı. Yetkiler manuel olarak atanmalı.')
            )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n[OK] {created_count} permission oluşturuldu')
        )

