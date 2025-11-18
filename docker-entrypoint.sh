#!/bin/bash
set -e

echo "🚀 SaaS 2026 - Container başlatılıyor..."

# Database bağlantısını bekle
echo "⏳ Database bağlantısı bekleniyor..."
python manage.py wait_for_db --max-retries=30 --retry-delay=2

# Migration işlemleri
echo "📦 Database migration işlemleri yapılıyor..."
python manage.py migrate_schemas --shared --noinput || true
python manage.py migrate_schemas --noinput || true

# Static dosyaları topla
echo "📁 Static dosyalar toplanıyor..."
python manage.py collectstatic --noinput || true

# Supervisord'u başlat (tüm servisler: gunicorn, celery, celery-beat)
echo "✅ Tüm servisler başlatılıyor (Gunicorn, Celery, Celery-Beat)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

