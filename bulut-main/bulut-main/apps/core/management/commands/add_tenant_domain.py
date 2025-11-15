"""
Tenant'a domain ekle
"""
from django.core.management.base import BaseCommand
from apps.tenants.models import Tenant, Domain


class Command(BaseCommand):
    help = 'Tenant\'a domain ekle'

    def add_arguments(self, parser):
        parser.add_argument('--tenant-slug', type=str, required=True, help='Tenant slug')
        parser.add_argument('--domain', type=str, required=True, help='Domain adı')

    def handle(self, *args, **options):
        tenant_slug = options['tenant_slug']
        domain_name = options['domain']
        
        try:
            tenant = Tenant.objects.get(slug=tenant_slug)
        except Tenant.DoesNotExist:
            self.stdout.write(self.style.ERROR(f'Tenant bulunamadi: {tenant_slug}'))
            return
        
        domain, created = Domain.objects.get_or_create(
            domain=domain_name,
            defaults={
                'tenant': tenant,
                'is_primary': False,
            }
        )
        
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Domain eklendi: {domain_name}'))
        else:
            if domain.tenant != tenant:
                self.stdout.write(self.style.WARNING(f'[SKIP] Domain zaten baska bir tenant\'a ait: {domain_name}'))
            else:
                self.stdout.write(self.style.WARNING(f'[SKIP] Domain zaten mevcut: {domain_name}'))
        
        self.stdout.write(f'\nTenant: {tenant.name}')
        self.stdout.write(f'Domain: {domain_name}')
        self.stdout.write(f'URL: http://{domain_name}:8000/')
