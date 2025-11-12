"""
Tüm tenant schema'larında Kullanıcı, Rol ve Yetki Yönetimi modülleri için Permission kayıtlarını oluştur
"""
from django.core.management.base import BaseCommand
from django.core.management import call_command
from django_tenants.utils import get_tenant_model, schema_context, get_public_schema_name
from django.db import connection


class Command(BaseCommand):
    help = 'Tüm tenant schema\'larında Kullanıcı, Rol ve Yetki Yönetimi modülleri için Permission kayıtlarını oluştur'

    def add_arguments(self, parser):
        parser.add_argument(
            '--skip-public',
            action='store_true',
            help='Public schema\'yı atla',
        )

    def handle(self, *args, **options):
        # Public schema'ya geç
        connection.set_schema_to_public()
        
        # Tüm tenant'ları al (public schema hariç)
        TenantModel = get_tenant_model()
        tenants = TenantModel.objects.filter(
            is_active=True
        ).exclude(schema_name=get_public_schema_name())
        
        total_tenants = tenants.count()
        self.stdout.write(f'\n{total_tenants} tenant schema\'da yetkiler oluşturulacak...\n')
        
        success_count = 0
        error_count = 0
        
        for tenant in tenants:
            try:
                self.stdout.write(f'[{tenant.schema_name}] {tenant.name} - Yetkiler oluşturuluyor...', ending=' ')
                
                with schema_context(tenant.schema_name):
                    call_command('create_user_role_permission_permissions', verbosity=0)
                
                self.stdout.write(self.style.SUCCESS('OK'))
                success_count += 1
                
            except Exception as e:
                self.stdout.write(self.style.ERROR(f'HATA: {str(e)}'))
                error_count += 1
        
        # Özet
        self.stdout.write('\n' + '='*50)
        self.stdout.write(self.style.SUCCESS(f'Başarılı: {success_count} tenant'))
        if error_count > 0:
            self.stdout.write(self.style.ERROR(f'Hatalı: {error_count} tenant'))
        self.stdout.write('='*50 + '\n')

