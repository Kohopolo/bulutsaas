"""
Tüm tenant'lar için hotels modülü permission'larını oluştur
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from apps.tenants.models import Tenant
from django.db import connection


class Command(BaseCommand):
    help = 'Tüm tenant\'lar için hotels modülü permission\'larını oluşturur'

    def handle(self, *args, **options):
        # Public schema'dan çalıştırılmalı
        if connection.schema_name != get_public_schema_name():
            self.stdout.write(self.style.WARNING('[UYARI] Bu komut public schema\'dan çalıştırılmalıdır.'))
            return
        
        tenants = Tenant.objects.filter(is_active=True)
        total_tenants = tenants.count()
        processed = 0
        
        for tenant in tenants:
            try:
                with schema_context(tenant.schema_name):
                    from django.core.management import call_command
                    call_command('create_hotel_permissions', verbosity=0)
                    processed += 1
                    self.stdout.write(self.style.SUCCESS(f'[OK] {tenant.name} - Permission\'lar oluşturuldu.'))
            except Exception as e:
                self.stdout.write(self.style.ERROR(f'[HATA] {tenant.name} - {str(e)}'))
        
        self.stdout.write(self.style.SUCCESS(f'\n[OK] Toplam {processed}/{total_tenants} tenant için permission\'lar oluşturuldu.'))

