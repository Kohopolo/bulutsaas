"""
Günlük Otomatik Yedekleme Management Command
Cron job veya Celery Beat için kullanılır
"""
from django.core.management import call_command
from django.core.management.base import BaseCommand
from django_tenants.utils import get_tenant_model, get_public_schema_name


class Command(BaseCommand):
    help = 'Günlük otomatik veritabanı yedekleme (cron job veya Celery Beat için)'
    
    def handle(self, *args, **options):
        self.stdout.write('Günlük otomatik yedekleme başlatılıyor...')
        
        # Public schema'yı yedekle
        try:
            self.stdout.write('Public schema yedekleniyor...')
            call_command(
                'backup_database',
                schema=get_public_schema_name(),
                type='automatic'
            )
            self.stdout.write(self.style.SUCCESS('Public schema yedekleme tamamlandı.'))
        except Exception as e:
            self.stdout.write(
                self.style.ERROR(f'Public schema yedeklenirken hata: {str(e)}')
            )
        
        # Tüm tenant schema'larını yedekle
        Tenant = get_tenant_model()
        tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
        
        self.stdout.write(f'{tenants.count()} tenant schema yedekleniyor...')
        
        success_count = 0
        error_count = 0
        
        for tenant in tenants:
            try:
                self.stdout.write(f'Tenant schema yedekleniyor: {tenant.schema_name}')
                call_command(
                    'backup_database',
                    schema=tenant.schema_name,
                    type='automatic'
                )
                success_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'Tenant {tenant.schema_name} yedekleme tamamlandı.')
                )
            except Exception as e:
                error_count += 1
                self.stdout.write(
                    self.style.ERROR(f'Tenant {tenant.schema_name} yedeklenirken hata: {str(e)}')
                )
        
        self.stdout.write(
            self.style.SUCCESS(
                f'Günlük otomatik yedekleme tamamlandı. '
                f'Başarılı: {success_count}, Hatalı: {error_count}'
            )
        )

