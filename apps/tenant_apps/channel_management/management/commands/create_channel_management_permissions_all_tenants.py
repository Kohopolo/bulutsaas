"""
Kanal Yönetimi Yetkileri Oluşturma Komutu (Tüm Tenant'lar için)
Tüm tenant'larda yetki tanımlamaları
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import schema_context, get_public_schema_name
from apps.tenants.models import Tenant
from apps.tenant_apps.channel_management.management.commands.create_channel_management_permissions import Command as CreatePermissionsCommand


class Command(BaseCommand):
    help = 'Kanal Yönetimi modülü için yetkileri tüm tenant\'larda oluşturur'

    def handle(self, *args, **options):
        # Public schema'da çalıştırılmalı
        if connection.schema_name != get_public_schema_name():
            self.stdout.write(
                self.style.ERROR('Bu komut public schema\'da çalıştırılmalıdır.')
            )
            return
        
        tenants = Tenant.objects.filter(is_active=True)
        total_tenants = tenants.count()
        
        self.stdout.write(f'Tüm tenant\'larda yetkiler oluşturuluyor... ({total_tenants} tenant)')
        
        success_count = 0
        error_count = 0
        
        for tenant in tenants:
            try:
                with schema_context(tenant.schema_name):
                    self.stdout.write(f'\n[{tenant.schema_name}] Yetkiler oluşturuluyor...')
                    cmd = CreatePermissionsCommand()
                    cmd.handle()
                    success_count += 1
            except Exception as e:
                self.stdout.write(
                    self.style.ERROR(f'[{tenant.schema_name}] Hata: {str(e)}')
                )
                error_count += 1
        
        self.stdout.write(
            self.style.SUCCESS(
                f'\nTamamlandı: {success_count} başarılı, {error_count} hata'
            )
        )





