"""
Management Command: Tenant Domain Ekleme
DNS kaydı ile birlikte domain ekler
"""
from django.core.management.base import BaseCommand, CommandError
from django_tenants.utils import schema_context
from apps.tenants.models import Tenant, Domain
import logging
import os

logger = logging.getLogger(__name__)


class Command(BaseCommand):
    help = 'Tenant domain ekle ve DNS kaydı oluştur'
    
    def add_arguments(self, parser):
        parser.add_argument(
            '--tenant-schema',
            type=str,
            required=True,
            help='Tenant schema adı'
        )
        parser.add_argument(
            '--domain',
            type=str,
            required=True,
            help='Domain adı (örn: test-otel.yourdomain.com)'
        )
        parser.add_argument(
            '--domain-type',
            type=str,
            choices=['primary', 'custom', 'subdomain'],
            default='subdomain',
            help='Domain tipi'
        )
        parser.add_argument(
            '--is-primary',
            action='store_true',
            help='Primary domain olarak işaretle'
        )
        parser.add_argument(
            '--skip-dns',
            action='store_true',
            help='DNS kaydı oluşturma (sadece Django kaydı)'
        )
    
    def handle(self, *args, **options):
        schema_name = options['tenant_schema']
        domain_name = options['domain']
        domain_type = options['domain_type']
        is_primary = options['is_primary']
        skip_dns = options['skip_dns']
        
        with schema_context('public'):
            try:
                tenant = Tenant.objects.get(schema_name=schema_name)
            except Tenant.DoesNotExist:
                raise CommandError(f'Tenant bulunamadı: {schema_name}')
            
            # Domain zaten var mı kontrol et
            if Domain.objects.filter(domain=domain_name).exists():
                self.stdout.write(
                    self.style.WARNING(f'Domain zaten mevcut: {domain_name}')
                )
                return
            
            # Domain oluştur
            domain = Domain.objects.create(
                tenant=tenant,
                domain=domain_name,
                domain_type=domain_type,
                is_primary=is_primary
            )
            
            self.stdout.write(
                self.style.SUCCESS(f'✓ Domain oluşturuldu: {domain_name}')
            )
            
            # DNS kaydı oluştur (opsiyonel)
            if not skip_dns:
                if os.getenv('DO_API_TOKEN'):
                    try:
                        from apps.tenants.utils.dns_manager import DigitalOceanDNSManager
                        
                        dns_manager = DigitalOceanDNSManager()
                        
                        if domain_type == 'subdomain':
                            # Subdomain'i ana domain'den ayır
                            base_domain = dns_manager.domain
                            if domain_name.endswith(base_domain):
                                subdomain = domain_name.replace(f'.{base_domain}', '')
                            else:
                                subdomain = domain_name.split('.')[0]
                            
                            # Mevcut record kontrolü
                            existing = dns_manager.find_record(subdomain, 'A')
                            if existing:
                                self.stdout.write(
                                    self.style.WARNING(
                                        f'DNS A record zaten mevcut: {subdomain}'
                                    )
                                )
                            else:
                                result = dns_manager.create_a_record(subdomain)
                                self.stdout.write(
                                    self.style.SUCCESS(
                                        f'✓ DNS A record oluşturuldu: {subdomain}'
                                    )
                                )
                                
                                # Record ID'yi kaydet
                                if 'domain_record' in result:
                                    domain.ssl_certificate = str(result['domain_record']['id'])
                                    domain.save(update_fields=['ssl_certificate'])
                        
                        elif domain_type == 'custom':
                            self.stdout.write(
                                self.style.WARNING(
                                    f'Custom domain: {domain_name}. '
                                    'DNS ayarlarını domain sahibi yapmalıdır.'
                                )
                            )
                    
                    except ImportError:
                        self.stdout.write(
                            self.style.ERROR(
                                'python-digitalocean paketi yüklü değil. '
                                'DNS kaydı oluşturulamadı.'
                            )
                        )
                    except Exception as e:
                        self.stdout.write(
                            self.style.ERROR(f'DNS kaydı oluşturulamadı: {str(e)}')
                        )
                else:
                    self.stdout.write(
                        self.style.WARNING(
                            'DO_API_TOKEN environment variable ayarlanmamış. '
                            'DNS kaydı oluşturulamadı.'
                        )
                    )
            
            self.stdout.write(
                self.style.SUCCESS(
                    f'\n✓ Domain başarıyla eklendi!\n'
                    f'  Domain: {domain_name}\n'
                    f'  Tenant: {tenant.name}\n'
                    f'  Type: {domain_type}\n'
                    f'  Primary: {is_primary}'
                )
            )

