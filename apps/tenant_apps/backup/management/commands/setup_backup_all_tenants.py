"""
Yedekleme Modülünü Tüm Tenant'larda Kurma Command
Migration ve permission oluşturma işlemlerini otomatik yapar
"""
from django.core.management.base import BaseCommand
from django.core.management import call_command
from django_tenants.utils import get_tenant_model
from django.db import connection
from django_tenants.utils import get_public_schema_name


class Command(BaseCommand):
    help = 'Yedekleme modülünü tüm tenant\'larda kurar (migration + permissions)'

    def handle(self, *args, **options):
        Tenant = get_tenant_model()
        
        # Public schema'da migration çalıştır
        self.stdout.write('[PUBLIC] Migration çalıştırılıyor...')
        try:
            call_command('migrate', 'backup', verbosity=0)
            self.stdout.write(self.style.SUCCESS('[PUBLIC] Migration tamamlandı'))
        except Exception as e:
            self.stdout.write(self.style.ERROR(f'[PUBLIC] Migration hatası: {str(e)}'))
        
        # Public schema'da permission oluştur
        self.stdout.write('[PUBLIC] Permission\'lar oluşturuluyor...')
        try:
            call_command('create_backup_permissions', verbosity=0)
            self.stdout.write(self.style.SUCCESS('[PUBLIC] Permission\'lar oluşturuldu'))
        except Exception as e:
            self.stdout.write(self.style.ERROR(f'[PUBLIC] Permission hatası: {str(e)}'))
        
        # Tüm tenant schema'larında migration ve permission oluştur
        tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
        
        self.stdout.write(f'\n{tenants.count()} tenant schema işleniyor...')
        
        success_count = 0
        error_count = 0
        
        for tenant in tenants:
            schema_name = tenant.schema_name
            self.stdout.write(f'\n[{schema_name}] İşleniyor...')
            
            try:
                # Migration çalıştır
                call_command('migrate_schemas', '--schema', schema_name, 'backup', verbosity=0)
                self.stdout.write(f'  [{schema_name}] Migration tamamlandı')
                
                # Permission oluştur - tenant schema'ya geçiş yap
                from django_tenants.utils import schema_context
                with schema_context(schema_name):
                    call_command('create_backup_permissions', verbosity=0)
                    self.stdout.write(f'  [{schema_name}] Permission\'lar oluşturuldu')
                
                success_count += 1
                self.stdout.write(self.style.SUCCESS(f'  [{schema_name}] Tamamlandı'))
                
            except Exception as e:
                error_count += 1
                self.stdout.write(
                    self.style.ERROR(f'  [{schema_name}] HATA: {str(e)}')
                )
        
        self.stdout.write(
            self.style.SUCCESS(
                f'\n[ÖZET] Başarılı: {success_count}, Hatalı: {error_count}'
            )
        )

