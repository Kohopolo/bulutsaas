"""
Tenant domain kontrolü
"""
from django.core.management.base import BaseCommand
from apps.tenants.models import Tenant, Domain


class Command(BaseCommand):
    help = 'Tenant domain kontrolü yapar'

    def add_arguments(self, parser):
        parser.add_argument('--domain', type=str, help='Kontrol edilecek domain adı')

    def handle(self, *args, **options):
        domain_name = options.get('domain', 'test-otel.localhost')
        
        self.stdout.write(f'\n🔍 Domain kontrolü: {domain_name}\n')
        
        # Domain'i kontrol et
        try:
            domain = Domain.objects.get(domain=domain_name)
            tenant = domain.tenant
            
            self.stdout.write(self.style.SUCCESS(f'✅ Domain bulundu!'))
            self.stdout.write(f'   Domain: {domain.domain}')
            self.stdout.write(f'   Tenant: {tenant.name} (slug: {tenant.slug})')
            self.stdout.write(f'   Schema: {tenant.schema_name}')
            self.stdout.write(f'   Primary: {domain.is_primary}')
            self.stdout.write(f'   Active: {tenant.is_active}')
            self.stdout.write(f'\n🌐 URL: http://{domain_name}:8000/')
            
        except Domain.DoesNotExist:
            self.stdout.write(self.style.ERROR(f'❌ Domain bulunamadı: {domain_name}'))
            self.stdout.write(f'\n💡 Domain eklemek için:')
            self.stdout.write(f'   python manage.py add_tenant_domain --tenant-slug=test-otel --domain={domain_name}')
            
            # Tenant'ı kontrol et
            try:
                tenant = Tenant.objects.get(slug='test-otel')
                self.stdout.write(f'\n✅ Tenant bulundu: {tenant.name}')
                self.stdout.write(f'   Mevcut domain\'ler:')
                for d in tenant.domains.all():
                    self.stdout.write(f'   - {d.domain} (primary: {d.is_primary})')
            except Tenant.DoesNotExist:
                self.stdout.write(self.style.ERROR(f'\n❌ Tenant bulunamadı: test-otel'))
        
        # Tüm domain'leri listele
        self.stdout.write(f'\n📋 Tüm domain\'ler:')
        for domain in Domain.objects.all().select_related('tenant'):
            self.stdout.write(f'   {domain.domain} → {domain.tenant.name} (primary: {domain.is_primary})')



