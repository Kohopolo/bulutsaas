"""
Tüm Tenant'larda TourCustomer verilerini Customer modeline taşıma komutu
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from django.db import connection
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Tum aktif tenant\'larda TourCustomer verilerini Customer modeline tasir'

    def add_arguments(self, parser):
        parser.add_argument(
            '--dry-run',
            action='store_true',
            help='Sadece simulasyon yap, veri tasima',
        )
        parser.add_argument(
            '--skip-public',
            action='store_true',
            help='Public schema\'yi atla',
        )
        parser.add_argument(
            '--skip-existing',
            action='store_true',
            help='Mevcut Customer kayitlarini atla',
        )

    def handle(self, *args, **options):
        skip_public = options.get('skip_public', False)
        dry_run = options.get('dry_run', False)
        skip_existing = options.get('skip_existing', False)

        # Tüm aktif tenant'ları al
        tenants = Tenant.objects.filter(is_active=True)

        if skip_public:
            tenants = tenants.exclude(schema_name=get_public_schema_name())

        if not tenants.exists():
            self.stdout.write(self.style.WARNING('[WARN] Aktif tenant bulunamadi.'))
            return

        total_tenants = tenants.count()
        success_count = 0
        error_count = 0

        for tenant in tenants:
            try:
                with schema_context(tenant.schema_name):
                    # migrate_tour_customers_to_customers komutunu çalıştır
                    from django.core.management import call_command
                    
                    cmd_args = []
                    if dry_run:
                        cmd_args.append('--dry-run')
                    if skip_existing:
                        cmd_args.append('--skip-existing')
                    
                    call_command('migrate_tour_customers_to_customers', *cmd_args, verbosity=0)
                    success_count += 1
                    self.stdout.write(self.style.SUCCESS(f'[OK] {tenant.schema_name}: TourCustomer verileri tasindi'))
            except Exception as e:
                error_count += 1
                self.stdout.write(self.style.ERROR(f'[ERROR] {tenant.schema_name}: Hata - {str(e)}'))

        self.stdout.write(self.style.SUCCESS(f'\n[OK] Toplam: {success_count}/{total_tenants} tenant basarili, {error_count} hata'))

