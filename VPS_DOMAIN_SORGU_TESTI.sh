#!/bin/bash
# Domain Sorgu Testi
# Middleware'in domain'i bulup bulamadığını test eder

echo "🔍 Domain sorgu testi yapılıyor..."
echo ""

docker exec saas2026_web python manage.py shell -c "
from django.db import connection
from django_tenants.utils import get_public_schema_name
connection.set_schema_to_public()

from apps.tenants.models import Domain
from django_tenants.utils import get_tenant_domain_model

# Test edilecek hostname'ler
test_hostnames = ['localhost', '72.62.35.155', 'bulutacente.com.tr']

print('=== Domain Sorgu Testi ===')
print('')

for hostname in test_hostnames:
    print(f'Hostname: {hostname}')
    
    # Domain modelini al
    domain_model = get_tenant_domain_model()
    
    # Domain'i sorgula
    try:
        domain = domain_model.objects.select_related('tenant').get(domain=hostname)
        print(f'  ✅ Domain bulundu: {domain.domain}')
        print(f'  Tenant: {domain.tenant.name}')
        print(f'  Schema: {domain.tenant.schema_name}')
    except domain_model.DoesNotExist:
        print(f'  ❌ Domain bulunamadı: {hostname}')
        
        # Benzer domain'leri göster
        similar = domain_model.objects.filter(domain__icontains=hostname.split('.')[-1] if '.' in hostname else hostname)
        if similar.exists():
            print(f'  Benzer domainler: {list(similar.values_list(\"domain\", flat=True))}')
    
    print('')

print('=== Tüm Domainler ===')
all_domains = Domain.objects.all()
for domain in all_domains:
    print(f'  - {domain.domain} -> {domain.tenant.name} ({domain.tenant.schema_name})')
"

echo ""
echo "✅ Test tamamlandı!"

