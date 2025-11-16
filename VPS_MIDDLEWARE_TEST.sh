#!/bin/bash
# Middleware Import Test Script
# Django settings yüklenmiş şekilde test yapar

echo "🔍 Middleware import testi yapılıyor..."
echo ""

docker exec saas2026_web python manage.py shell << 'EOF'
import sys
sys.path.insert(0, '/app')

try:
    from apps.tenants.middleware.tenant_middleware import CustomTenantMainMiddleware
    print('✅ Middleware import başarılı!')
    print(f'✅ CustomTenantMainMiddleware sınıfı: {CustomTenantMainMiddleware}')
except Exception as e:
    print(f'❌ Import hatası: {e}')
    import traceback
    traceback.print_exc()
EOF

echo ""
echo "✅ Test tamamlandı!"

