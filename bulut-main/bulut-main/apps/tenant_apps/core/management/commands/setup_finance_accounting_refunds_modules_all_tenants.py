"""
Finance, Accounting ve Refunds modüllerini tüm tenant'lar için kurar
"""
from django.core.management.base import BaseCommand
from django.core.management import call_command
from django_tenants.utils import schema_context, get_public_schema_name
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Finance, Accounting ve Refunds modüllerini tüm tenant\'lar için kurar'

    def handle(self, *args, **options):
        # Önce public schema'da modülleri oluştur
        self.stdout.write('Public schema\'da modüller oluşturuluyor...')
        call_command('create_finance_accounting_refunds_modules', verbosity=1)
        call_command('add_finance_accounting_refunds_to_packages', verbosity=1)
        
        # Tüm tenant'lar için çalıştır
        tenants = Tenant.objects.exclude(schema_name=get_public_schema_name())
        total = tenants.count()
        
        self.stdout.write(f'\n{total} tenant için modül yetkileri kuruluyor...')
        
        for idx, tenant in enumerate(tenants, 1):
            self.stdout.write(f'\n[{idx}/{total}] {tenant.schema_name} - {tenant.name}')
            call_command('setup_finance_accounting_refunds_modules', '--tenant', tenant.schema_name, verbosity=0)
        
        self.stdout.write(self.style.SUCCESS(f'\n[OK] Tüm tenant\'lar için modül yetkileri kuruldu!'))

