#!/bin/bash
# Web Container Log Check
# Django'nun neden 404 döndürdüğünü kontrol eder

echo "🔍 Web container logları kontrol ediliyor..."
echo ""

# Son 100 satır log
echo "=== Son 100 Satır Log ==="
docker compose logs web --tail=100

echo ""
echo "=== 404 Hataları ==="
docker compose logs web --tail=200 | grep -i "404\|Not Found\|WARNING"

echo ""
echo "=== Middleware Hataları ==="
docker compose logs web --tail=200 | grep -i "middleware\|tenant\|domain"

echo ""
echo "✅ Log kontrolü tamamlandı!"

