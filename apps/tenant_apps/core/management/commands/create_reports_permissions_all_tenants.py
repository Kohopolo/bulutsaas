"""
Tum tenant'lar icin raporlar modulu permission'larini olusturur
Public schema'da calisir ve tum tenant schema'larinda calistirir
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import tenant_context, get_public_schema_name
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Tum tenant\'lar icin raporlar modulu permission\'larini olusturur'
    
    def handle(self, *args, **options):
        # Public schema'da calismali
        if connection.schema_name != get_public_schema_name():
            self.stdout.write(self.style.ERROR('Bu komut public schema\'da calismalidir!'))
            return
        
        self.stdout.write('Tum tenant\'lar icin raporlar modulu permission\'lari olusturuluyor...\n')
        
        tenants = Tenant.objects.filter(is_active=True)
        total_tenants = tenants.count()
        success_count = 0
        error_count = 0
        
        for tenant in tenants:
            try:
                with tenant_context(tenant):
                    from django.core.management import call_command
                    call_command('create_reports_permissions', verbosity=0)
                    success_count += 1
                    self.stdout.write(
                        self.style.SUCCESS(f'[OK] {tenant.name} - Permission\'lar olusturuldu')
                    )
            except Exception as e:
                error_count += 1
                self.stdout.write(
                    self.style.ERROR(f'[ERROR] {tenant.name} - {str(e)}')
                )
        
        self.stdout.write(
            self.style.SUCCESS(
                f'\n[OK] Tamamlandi! Basarili: {success_count}/{total_tenants}, Hata: {error_count}'
            )
        )

