"""
Dynamic ALLOWED_HOSTS Middleware
Domain veritabanında kontrol ederek ALLOWED_HOSTS kontrolünü dinamik yapar
"""
from django.core.exceptions import DisallowedHost
from django_tenants.utils import schema_context
from apps.tenants.models import Domain
import logging

logger = logging.getLogger(__name__)


class DynamicAllowedHostsMiddleware:
    """
    ALLOWED_HOSTS kontrolünü dinamik yapar
    Domain veritabanında kontrol eder
    """
    
    def __init__(self, get_response):
        self.get_response = get_response
    
    def __call__(self, request):
        host = request.get_host().split(':')[0]
        
        # Localhost ve 127.0.0.1 için izin ver (development)
        if host in ['localhost', '127.0.0.1', '0.0.0.0']:
            return self.get_response(request)
        
        # Public schema'da domain kontrolü yap
        try:
            with schema_context('public'):
                domain_exists = Domain.objects.filter(domain=host).exists()
                
                if not domain_exists:
                    # Ana domain kontrolü (wildcard için)
                    # Örn: test-otel.yourdomain.com -> yourdomain.com
                    parts = host.split('.')
                    if len(parts) >= 2:
                        base_domain = '.'.join(parts[-2:])  # yourdomain.com
                        # Base domain veya base domain ile biten domain'ler için kontrol
                        if Domain.objects.filter(
                            domain__in=[base_domain, f'*.{base_domain}']
                        ).exists() or Domain.objects.filter(
                            domain__endswith=base_domain
                        ).exists():
                            domain_exists = True
                
                if not domain_exists:
                    logger.warning(f"Disallowed host: {host}")
                    raise DisallowedHost(f"Invalid host header: {host}")
        
        except Exception as e:
            # Hata durumunda logla ama request'i durdurma (güvenlik için)
            logger.error(f"Domain check failed: {str(e)}")
            # Development'ta izin ver, production'da reddet
            from django.conf import settings
            if not settings.DEBUG:
                raise DisallowedHost(f"Domain check failed: {host}")
        
        return self.get_response(request)

