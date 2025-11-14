"""
Tüm tenant schema'larında feribot bileti modülünü kur
Migration ve permission işlemlerini yapar
"""
from django.core.management.base import BaseCommand
from django.core.management import call_command
from django_tenants.utils import get_tenant_model, schema_context, get_public_schema_name
from django.db import connection


class Command(BaseCommand):
    help = 'Tüm tenant schema\'larında feribot bileti modülünü kur (migration + permission)'

    def add_arguments(self, parser):
        parser.add_argument(
            '--skip-public',
            action='store_true',
            help='Public schema\'yı atla',
        )
        parser.add_argument(
            '--skip-migration',
            action='store_true',
            help='Migration\'ları atla, sadece permission oluştur',
        )
        parser.add_argument(
            '--skip-permission',
            action='store_true',
            help='Permission\'ları atla, sadece migration çalıştır',
        )

    def handle(self, *args, **options):
        skip_public = options.get('skip_public', False)
        skip_migration = options.get('skip_migration', False)
        skip_permission = options.get('skip_permission', False)
        
        # Public schema'da modülü oluştur
        if not skip_public:
            self.stdout.write('\n[PUBLIC SCHEMA] Modül oluşturuluyor...')
            try:
                connection.set_schema_to_public()
                call_command('create_ferry_tickets_module', verbosity=1)
                self.stdout.write(self.style.SUCCESS('[OK] Public schema\'da modül oluşturuldu'))
            except Exception as e:
                self.stdout.write(self.style.ERROR(f'[HATA] Public schema modül oluşturma: {str(e)}'))
        
        # Public schema'da migration
        if not skip_migration and not skip_public:
            self.stdout.write('\n[PUBLIC SCHEMA] Migration çalıştırılıyor...')
            try:
                connection.set_schema_to_public()
                call_command('migrate', 'ferry_tickets', verbosity=1)
                self.stdout.write(self.style.SUCCESS('[OK] Public schema migration tamamlandı'))
            except Exception as e:
                self.stdout.write(self.style.ERROR(f'[HATA] Public schema migration: {str(e)}'))
        
        # Tüm tenant'ları al
        connection.set_schema_to_public()
        TenantModel = get_tenant_model()
        tenants = TenantModel.objects.filter(
            is_active=True
        ).exclude(schema_name=get_public_schema_name())
        
        total_tenants = tenants.count()
        self.stdout.write(f'\n{total_tenants} tenant schema\'da işlem yapılacak...\n')
        
        success_count = 0
        error_count = 0
        
        for tenant in tenants:
            try:
                self.stdout.write(f'\n[{tenant.schema_name}] {tenant.name}')
                self.stdout.write('-' * 50)
                
                # Migration
                if not skip_migration:
                    self.stdout.write('  Migration çalıştırılıyor...', ending=' ')
                    try:
                        with schema_context(tenant.schema_name):
                            call_command('migrate', 'ferry_tickets', verbosity=0)
                        self.stdout.write(self.style.SUCCESS('OK'))
                    except Exception as e:
                        self.stdout.write(self.style.ERROR(f'HATA: {str(e)}'))
                        raise
                
                # Permission
                if not skip_permission:
                    self.stdout.write('  Permission\'lar oluşturuluyor...', ending=' ')
                    try:
                        with schema_context(tenant.schema_name):
                            call_command('create_ferry_tickets_permissions', verbosity=0)
                        self.stdout.write(self.style.SUCCESS('OK'))
                    except Exception as e:
                        self.stdout.write(self.style.ERROR(f'HATA: {str(e)}'))
                        raise
                
                success_count += 1
                
            except Exception as e:
                self.stdout.write(self.style.ERROR(f'  [HATA] {str(e)}'))
                error_count += 1
        
        # Özet
        self.stdout.write('\n' + '='*50)
        self.stdout.write(self.style.SUCCESS(f'Toplam Tenant: {total_tenants}'))
        self.stdout.write(self.style.SUCCESS(f'Başarılı: {success_count}'))
        if error_count > 0:
            self.stdout.write(self.style.ERROR(f'Hatalı: {error_count}'))
        self.stdout.write('='*50 + '\n')

