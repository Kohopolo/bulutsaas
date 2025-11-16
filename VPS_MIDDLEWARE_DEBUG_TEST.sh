#!/bin/bash
# Middleware Debug Test
# IP adresi ile erişimde middleware'in davranışını test eder

echo "🔍 Middleware debug testi yapılıyor..."
echo ""

docker exec saas2026_web python manage.py shell -c "
from django.test import RequestFactory
from django.db import connection
from django_tenants.utils import get_public_schema_name, get_tenant_domain_model
from apps.tenants.middleware.tenant_middleware import CustomTenantMainMiddleware

# Public schema'ya geç
connection.set_schema_to_public()

# Request oluştur (IP adresi ile)
factory = RequestFactory()
request = factory.get('/admin/')
request.META['HTTP_HOST'] = '72.62.35.155'
request.META['SERVER_NAME'] = '72.62.35.155'

print('=== Middleware Test ===')
print(f'Request Host: {request.META[\"HTTP_HOST\"]}')
print(f'Request Path: {request.path}')
print('')

# Middleware oluştur
middleware = CustomTenantMainMiddleware(lambda r: None)

# Hostname çıkar
try:
    hostname = middleware.hostname_from_request(request)
    print(f'✅ Hostname çıkarıldı: {hostname}')
except Exception as e:
    print(f'❌ Hostname çıkarılamadı: {e}')
    import traceback
    traceback.print_exc()
    exit(1)

print('')

# Domain sorgula
domain_model = get_tenant_domain_model()
try:
    domain = domain_model.objects.select_related('tenant').get(domain=hostname)
    print(f'✅ Domain bulundu: {domain.domain}')
    print(f'   Tenant: {domain.tenant.name}')
    print(f'   Schema: {domain.tenant.schema_name}')
    print('')
    
    # Tenant'ı ayarla
    request.tenant = domain.tenant
    connection.set_tenant(request.tenant)
    print(f'✅ Schema değiştirildi: {connection.schema_name}')
    print('')
    
    # URL routing'i ayarla
    middleware.setup_url_routing(request)
    print(f'✅ URL routing ayarlandı: {request.urlconf}')
    print('')
    
except domain_model.DoesNotExist:
    print(f'❌ Domain bulunamadı: {hostname}')
    print('   no_tenant_found çağrılacak...')
    print('')
    
    # no_tenant_found'u çağır
    try:
        middleware.no_tenant_found(request, hostname)
        print(f'✅ no_tenant_found çağrıldı')
        print(f'   Schema: {connection.schema_name}')
        print(f'   Tenant: {getattr(request, \"tenant\", None)}')
        print(f'   URL routing: {getattr(request, \"urlconf\", None)}')
    except Exception as e:
        print(f'❌ no_tenant_found hatası: {e}')
        import traceback
        traceback.print_exc()

print('')
print('=== ALLOWED_HOSTS Kontrolü ===')
from django.conf import settings
print(f'ALLOWED_HOSTS: {settings.ALLOWED_HOSTS}')
print(f'72.62.35.155 in ALLOWED_HOSTS: {\"72.62.35.155\" in settings.ALLOWED_HOSTS}')
"

echo ""
echo "✅ Test tamamlandı!"

