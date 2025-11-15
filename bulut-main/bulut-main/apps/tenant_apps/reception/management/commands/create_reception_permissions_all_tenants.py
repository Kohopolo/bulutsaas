"""
Reception Modülü Permission'larını Tüm Tenant'larda Oluşturma Command
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context
from apps.tenants.models import Tenant


class Command(BaseCommand):
    help = 'Reception modülü permission\'larını tüm tenant schema\'larda oluşturur'

    def handle(self, *args, **options):
        from apps.tenant_apps.reception.management.commands.create_reception_permissions import Command as ReceptionPermissionsCommand
        
        tenants = Tenant.objects.exclude(schema_name='public')
        
        if not tenants.exists():
            self.stdout.write(
                self.style.WARNING('[UYARI] Tenant bulunamadı.')
            )
            return
        
        total_created = 0
        
        for tenant in tenants:
            try:
                with schema_context(tenant.schema_name):
                    self.stdout.write(f'\n[{tenant.schema_name}] Permission\'lar oluşturuluyor...')
                    
                    cmd = ReceptionPermissionsCommand()
                    cmd.stdout = self.stdout
                    cmd.style = self.style
                    cmd.handle()
                    
                    total_created += 1
                    
                    self.stdout.write(
                        self.style.SUCCESS(f'  [OK] {tenant.schema_name} - Permission\'lar oluşturuldu')
                    )
            except Exception as e:
                self.stdout.write(
                    self.style.ERROR(f'  [HATA] {tenant.schema_name} - {str(e)}')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n[OK] Toplam {total_created} tenant\'da permission\'lar oluşturuldu')
        )

