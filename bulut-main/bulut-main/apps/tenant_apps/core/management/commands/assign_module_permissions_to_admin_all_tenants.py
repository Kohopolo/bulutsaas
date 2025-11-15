"""
Tüm tenant'larda yeni modül yetkilerini admin rolüne otomatik atama komutu
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from django.db import connection
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Tum aktif tenant\'larda yeni modul yetkilerini admin rolune otomatik atar'

    def add_arguments(self, parser):
        parser.add_argument(
            '--module-code',
            type=str,
            help='Belirli bir modul icin yetki atama (opsiyonel)',
        )
        parser.add_argument(
            '--skip-public',
            action='store_true',
            help='Public schema\'yi atla',
        )

    def handle(self, *args, **options):
        skip_public = options.get('skip_public', False)
        module_code = options.get('module_code')

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
                    from django.core.management import call_command
                    
                    cmd_args = []
                    if module_code:
                        cmd_args.extend(['--module-code', module_code])
                    
                    call_command('assign_module_permissions_to_admin', *cmd_args, verbosity=0)
                    success_count += 1
                    self.stdout.write(self.style.SUCCESS(f'[OK] {tenant.schema_name}: Yetkiler atandi'))
            except Exception as e:
                error_count += 1
                self.stdout.write(self.style.ERROR(f'[ERROR] {tenant.schema_name}: Hata - {str(e)}'))

        self.stdout.write(self.style.SUCCESS(
            f'\n[OK] Toplam: {success_count}/{total_tenants} tenant basarili, {error_count} hata'
        ))


