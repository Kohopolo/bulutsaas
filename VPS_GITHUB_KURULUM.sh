#!/bin/bash
# VPS GitHub Kurulum Scripti
# Docker Compose yeniden kurulumu için

# set -e kaldırıldı - hataları manuel kontrol edeceğiz

echo "=========================================="
echo "🚀 Docker Compose GitHub Kurulumu"
echo "=========================================="
echo ""

# 1. Proje klasörüne git veya oluştur
echo "📁 Proje klasörü kontrol ediliyor..."
if [ ! -d "/docker/bulutsaas" ]; then
    echo "⚠️  /docker/bulutsaas klasörü bulunamadı, oluşturuluyor..."
    mkdir -p /docker/bulutsaas 2>/dev/null || sudo mkdir -p /docker/bulutsaas
    chown $USER:$USER /docker/bulutsaas 2>/dev/null || sudo chown $USER:$USER /docker/bulutsaas
    echo "✅ Klasör oluşturuldu: /docker/bulutsaas"
fi

if [ ! -d "/docker/bulutsaas" ]; then
    echo "❌ Hata: /docker/bulutsaas klasörü oluşturulamadı!"
    echo "💡 Manuel olarak oluşturun: sudo mkdir -p /docker/bulutsaas && sudo chown $USER:$USER /docker/bulutsaas"
    exit 1
fi

cd /docker/bulutsaas || {
    echo "❌ Hata: /docker/bulutsaas klasörüne geçilemedi!"
    exit 1
}
echo "✅ Klasöre geçildi: $(pwd)"

# 2. Git durumunu kontrol et
echo ""
echo "🔍 Git durumu kontrol ediliyor..."
if [ ! -d ".git" ]; then
    echo "⚠️  Git repository bulunamadı. İlk kurulum yapılıyor..."
    echo "📥 GitHub'dan proje klonlanıyor..."
    git clone https://github.com/Kohopolo/bulutsaas.git .
    if [ $? -ne 0 ]; then
        echo "❌ Hata: Git clone başarısız!"
        exit 1
    fi
    echo "✅ Proje klonlandı"
else
    echo "✅ Git repository bulundu"
    git remote set-url origin https://github.com/Kohopolo/bulutsaas.git || true
fi

# 3. GitHub'dan güncellemeleri çek
echo ""
echo "⬇️  GitHub'dan güncellemeler çekiliyor..."
git fetch origin
git pull origin main || {
    echo "⚠️  Pull başarısız, merge yapılıyor..."
    git merge origin/main || true
}

# 4. .env dosyasını kontrol et
echo ""
echo "🔐 .env dosyası kontrol ediliyor..."
if [ ! -f ".env" ]; then
    echo "⚠️  .env dosyası bulunamadı, env.example'dan oluşturuluyor..."
    if [ -f "env.example" ]; then
        cp env.example .env
        echo "✅ .env dosyası oluşturuldu"
        echo "⚠️  ÖNEMLİ: .env dosyasını düzenleyin: nano .env"
    else
        echo "❌ Hata: env.example dosyası bulunamadı!"
        exit 1
    fi
else
    echo "✅ .env dosyası mevcut"
fi

# 5. Gerekli klasörleri oluştur
echo ""
echo "📂 Gerekli klasörler oluşturuluyor..."
mkdir -p logs
mkdir -p certbot/www
mkdir -p nginx/conf.d
echo "✅ Klasörler hazır"

# 6. Eski container'ları durdur
echo ""
echo "🛑 Eski container'lar durduruluyor..."
docker compose down 2>/dev/null || docker-compose down 2>/dev/null || true

# 7. Container'ları oluştur ve başlat
echo ""
echo "🐳 Container'lar oluşturuluyor ve başlatılıyor..."
docker compose up -d --build || docker-compose up -d --build || {
    echo "❌ Hata: Docker Compose başlatılamadı!"
    exit 1
}

# 8. Biraz bekle (container'ların başlaması için)
echo ""
echo "⏳ Container'ların başlaması bekleniyor (30 saniye)..."
sleep 30

# 9. Container durumunu kontrol et
echo ""
echo "📊 Container durumu kontrol ediliyor..."
docker compose ps || docker-compose ps

# 10. Middleware dosyalarını kontrol et
echo ""
echo "🔍 Middleware dosyaları kontrol ediliyor..."
if docker exec saas2026_web ls -la /app/apps/tenants/middleware/ > /dev/null 2>&1; then
    echo "✅ Middleware dosyaları mevcut"
    docker exec saas2026_web ls -la /app/apps/tenants/middleware/
else
    echo "⚠️  Middleware dosyaları kontrol edilemedi (container henüz hazır olmayabilir)"
fi

# 11. Health check
echo ""
echo "🏥 Health check yapılıyor..."
sleep 10
if curl -f http://localhost/health/ > /dev/null 2>&1 || curl -f http://localhost:8000/health/ > /dev/null 2>&1; then
    echo "✅ Health check başarılı"
else
    echo "⚠️  Health check başarısız (container henüz hazır olmayabilir)"
fi

# 12. Logları göster
echo ""
echo "📋 Son loglar (web container):"
docker compose logs web --tail=20 || docker-compose logs web --tail=20

echo ""
echo "=========================================="
echo "✅ Kurulum tamamlandı!"
echo "=========================================="
echo ""
echo "📝 Sonraki adımlar:"
echo "1. Logları kontrol edin: docker compose logs -f"
echo "2. Container durumunu kontrol edin: docker compose ps"
echo "3. Middleware import testi:"
echo "   docker exec saas2026_web python -c \"import sys; sys.path.insert(0, '/app'); from apps.tenants.middleware.tenant_middleware import CustomTenantMainMiddleware; print('✅ Middleware import başarılı!')\""
echo ""
echo "🔗 GitHub Repo: https://github.com/Kohopolo/bulutsaas.git"
echo ""

