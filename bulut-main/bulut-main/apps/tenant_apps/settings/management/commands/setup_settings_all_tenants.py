"""
Settings Modülü Tüm Tenant'larda Kurulum Komutu
Tüm tenant schema'larda migration ve varsayılan şablonları oluşturur
"""
from django.core.management.base import BaseCommand
from django.core.management import call_command
from django_tenants.utils import schema_context, get_public_schema_name
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Settings modülünü tüm tenant\'larda kurar (migration + varsayılan şablonlar)'

    def add_arguments(self, parser):
        parser.add_argument(
            '--skip-migration',
            action='store_true',
            help='Migration\'ları atla, sadece şablonları oluştur',
        )

    def handle(self, *args, **options):
        skip_migration = options.get('skip_migration', False)
        
        # Tüm tenant'ları al
        tenants = Tenant.objects.filter(is_active=True).exclude(schema_name=get_public_schema_name())
        
        if not tenants.exists():
            self.stdout.write(
                self.style.WARNING('Aktif tenant bulunamadı.')
            )
            return
        
        total_tenants = tenants.count()
        self.stdout.write(
            self.style.SUCCESS(f'{total_tenants} tenant bulundu. Kurulum başlatılıyor...\n')
        )
        
        success_count = 0
        error_count = 0
        
        for tenant in tenants:
            schema_name = tenant.schema_name
            self.stdout.write(f'\n[{schema_name}] İşleniyor...')
            
            try:
                # Tenant schema context'inde çalış
                with schema_context(schema_name):
                    # Migration çalıştır (eğer atlanmadıysa)
                    if not skip_migration:
                        self.stdout.write(f'  -> Migration calistiriliyor...')
                        call_command('migrate', 'settings', verbosity=0)
                        self.stdout.write(
                            self.style.SUCCESS(f'  [OK] Migration tamamlandi')
                        )
                    
                    # Varsayılan şablonları oluştur
                    self.stdout.write(f'  -> Varsayilan sablonlar olusturuluyor...')
                    call_command('create_sms_templates', verbosity=0)
                    self.stdout.write(
                        self.style.SUCCESS(f'  [OK] Sablonlar olusturuldu')
                    )
                    
                    success_count += 1
                    self.stdout.write(
                        self.style.SUCCESS(f'  [OK] [{schema_name}] Kurulum tamamlandi')
                    )
            
            except Exception as e:
                error_count += 1
                self.stdout.write(
                    self.style.ERROR(f'  [HATA] [{schema_name}] Hata: {str(e)}')
                )
        
        # Özet
        self.stdout.write('\n' + '='*50)
        self.stdout.write(
            self.style.SUCCESS(f'\n[OK] Basarili: {success_count} tenant')
        )
        if error_count > 0:
            self.stdout.write(
                self.style.ERROR(f'[HATA] Hatali: {error_count} tenant')
            )
        self.stdout.write('='*50)

