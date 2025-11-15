"""
Tüm Tenant'larda Customer Modülü İzinlerini Oluşturma Komutu
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from django.db import connection
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Tüm aktif tenant\'larda Customer modülü izinlerini oluşturur'

    def add_arguments(self, parser):
        parser.add_argument(
            '--skip-public',
            action='store_true',
            help='Public schema\'yı atla',
        )

    def handle(self, *args, **options):
        skip_public = options.get('skip_public', False)
        
        # Tüm aktif tenant'ları al
        tenants = Tenant.objects.filter(is_active=True)
        
        if skip_public:
            from django_tenants.utils import get_public_schema_name
            tenants = tenants.exclude(schema_name=get_public_schema_name())

        if not tenants.exists():
            self.stdout.write(self.style.WARNING('[WARN] Aktif tenant bulunamadi.'))
            return

        total_tenants = tenants.count()
        success_count = 0
        error_count = 0

        for tenant in tenants:
            try:
                with schema_context(tenant):
                    # create_customer_permissions komutunu çalıştır
                    from django.core.management import call_command
                    call_command('create_customer_permissions', verbosity=0)
                    success_count += 1
                    self.stdout.write(self.style.SUCCESS(f'[OK] {tenant.schema_name}: Customer izinleri olusturuldu'))
            except Exception as e:
                error_count += 1
                self.stdout.write(self.style.ERROR(f'[ERROR] {tenant.schema_name}: Hata - {str(e)}'))

        self.stdout.write(self.style.SUCCESS(f'\n[OK] Toplam: {success_count}/{total_tenants} tenant basarili, {error_count} hata'))

