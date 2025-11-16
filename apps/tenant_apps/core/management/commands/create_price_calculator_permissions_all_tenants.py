"""
Tüm tenant'lar için Fiyat Hesaplama modülü permission'larını oluşturur
Public schema'da çalışır
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import get_public_schema_name, schema_context, get_tenant_model
from django.core.management import call_command


class Command(BaseCommand):
    help = 'Tüm tenant\'lar için Fiyat Hesaplama modülü permission\'larını oluşturur'
    
    def handle(self, *args, **options):
        # Public schema'da çalışmalı
        if connection.schema_name != get_public_schema_name():
            self.stdout.write(self.style.ERROR('Bu komut public schema\'da çalışmalıdır!'))
            return
        
        self.stdout.write('Tüm tenant\'lar için Fiyat Hesaplama modülü permission\'ları oluşturuluyor...')
        
        with schema_context(get_public_schema_name()):
            Tenant = get_tenant_model()
            tenants = Tenant.objects.filter(is_active=True)
            
            if not tenants.exists():
                self.stdout.write(self.style.WARNING('Aktif tenant bulunamadı.'))
                return
            
            self.stdout.write(f'{tenants.count()} tenant işleniyor...\n')
            
            success_count = 0
            error_count = 0
            
            for tenant in tenants:
                try:
                    self.stdout.write(f'Tenant: {tenant.name} ({tenant.schema_name})...')
                    
                    with schema_context(tenant.schema_name):
                        call_command('create_price_calculator_permissions', verbosity=0)
                    
                    success_count += 1
                    self.stdout.write(self.style.SUCCESS(f'  [OK] {tenant.name}'))
                except Exception as e:
                    error_count += 1
                    self.stdout.write(self.style.ERROR(f'  [HATA] {tenant.name}: {str(e)}'))
            
            self.stdout.write('')
            self.stdout.write(self.style.SUCCESS('=' * 60))
            self.stdout.write(self.style.SUCCESS(f'Toplam {tenants.count()} tenant işlendi:'))
            self.stdout.write(f'  - Başarılı: {success_count}')
            self.stdout.write(f'  - Hatalı: {error_count}')
            self.stdout.write(self.style.SUCCESS('=' * 60))

