"""
Custom Tenant Middleware
Domain bulunamadığında public schema'ya yönlendirme için özelleştirilmiş middleware
"""
from django.conf import settings
from django.db import connection
from django_tenants.middleware.main import TenantMainMiddleware
from django_tenants.utils import get_public_schema_name, get_tenant_model, get_public_schema_urlconf
from django.urls import set_urlconf


class CustomTenantMainMiddleware(TenantMainMiddleware):
    """
    Custom tenant middleware that properly handles public schema fallback
    """
    
    def no_tenant_found(self, request, hostname):
        """
        Domain bulunamadığında public schema'ya yönlendir
        """
        if hasattr(settings, 'SHOW_PUBLIC_IF_NO_TENANT_FOUND') and settings.SHOW_PUBLIC_IF_NO_TENANT_FOUND:
            # Public schema'ya geç
            connection.set_schema_to_public()
            
            # Public tenant'ı bul ve ayarla
            try:
                Tenant = get_tenant_model()
                public_tenant = Tenant.objects.get(schema_name=get_public_schema_name())
                request.tenant = public_tenant
                request.tenant.domain_url = hostname
            except Tenant.DoesNotExist:
                # Public tenant yoksa None olarak bırak
                request.tenant = None
            
            # URL routing'i ayarla
            self.setup_url_routing(request=request, force_public=True)
        else:
            raise self.TENANT_NOT_FOUND_EXCEPTION('No tenant for hostname "%s"' % hostname)
    
    @staticmethod
    def setup_url_routing(request, force_public=False):
        """
        Sets the correct url conf based on the tenant
        Override to handle force_public case properly
        """
        from django_tenants.utils import get_public_schema_urlconf, has_multi_type_tenants, get_tenant_types
        
        if force_public:
            # Public schema için public URL conf kullan
            if hasattr(settings, 'PUBLIC_SCHEMA_URLCONF'):
                request.urlconf = settings.PUBLIC_SCHEMA_URLCONF
                set_urlconf(request.urlconf)
            return
        
        # Normal tenant routing (parent class'ın mantığını kullan)
        public_schema_name = get_public_schema_name()
        if has_multi_type_tenants():
            tenant_types = get_tenant_types()
            if (not hasattr(request, 'tenant') or
                    ((force_public or (request.tenant and request.tenant.schema_name == get_public_schema_name())) and
                     'URLCONF' in tenant_types[public_schema_name])):
                request.urlconf = get_public_schema_urlconf()
            else:
                if request.tenant:
                    tenant_type = request.tenant.get_tenant_type()
                    request.urlconf = tenant_types[tenant_type]['URLCONF']
            set_urlconf(request.urlconf)
        else:
            # Do we have a public-specific urlconf?
            if (hasattr(settings, 'PUBLIC_SCHEMA_URLCONF') and
                    (force_public or (hasattr(request, 'tenant') and request.tenant and 
                                     request.tenant.schema_name == get_public_schema_name()))):
                request.urlconf = settings.PUBLIC_SCHEMA_URLCONF
                set_urlconf(request.urlconf)

