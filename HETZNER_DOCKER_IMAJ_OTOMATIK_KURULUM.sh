#!/bin/bash

################################################################################
# 🐳 SaaS 2026 - Hetzner VPS Docker İmaj Otomatik Kurulum Script'i
# 
# VPS IP: 78.46.142.212
# 
# Bu script:
# 1. Docker ve Docker Compose kurulumunu yapar (eğer yoksa)
# 2. Projeyi GitHub'dan çeker
# 3. Docker imajını build eder
# 4. Tüm servisleri otomatik başlatır (Supervisord ile)
#
# Kullanım: ./HETZNER_DOCKER_IMAJ_OTOMATIK_KURULUM.sh
################################################################################

set -e

# Renkli çıktı için
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Mesaj fonksiyonları
success_msg() {
    echo -e "${GREEN}✅ $1${NC}"
}

error_msg() {
    echo -e "${RED}❌ $1${NC}"
}

info_msg() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

warning_msg() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Hetzner VPS IP
VPS_IP="78.46.142.212"
VPS_NAME="hetzner"

# Proje dizini (Hetzner için özel)
PROJECT_DIR="/var/www/bulutsaas-hetzner"
GITHUB_REPO="https://github.com/Kohopolo/bulutsaas.git"
COMPOSE_FILE="docker-compose.simple.hetzner.yml"

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "🚀 SaaS 2026 - Hetzner VPS Docker İmaj Otomatik Kurulum"
echo "📡 VPS IP: $VPS_IP"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# 1. Sistem güncellemesi
info_msg "Sistem güncellemesi yapılıyor..."
apt update && apt upgrade -y
success_msg "Sistem güncellendi"

# 2. Temel araçlar
info_msg "Temel araçlar kuruluyor..."
apt install -y curl wget git
success_msg "Temel araçlar kuruldu"

# 3. Docker kurulumu (eğer yoksa)
if ! command -v docker &> /dev/null; then
    info_msg "Docker kuruluyor..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
    success_msg "Docker kuruldu"
else
    success_msg "Docker zaten kurulu"
fi

# 4. Docker Compose kurulumu (eğer yoksa)
if ! command -v docker compose &> /dev/null; then
    info_msg "Docker Compose kuruluyor..."
    apt install -y docker-compose-plugin
    success_msg "Docker Compose kuruldu"
else
    success_msg "Docker Compose zaten kurulu"
fi

# 5. Docker servisini başlat
info_msg "Docker servisi başlatılıyor..."
systemctl start docker
systemctl enable docker
success_msg "Docker servisi başlatıldı"

# 6. Kullanıcıyı docker grubuna ekle
if ! groups $USER | grep -q docker; then
    info_msg "Kullanıcı docker grubuna ekleniyor..."
    usermod -aG docker $USER
    warning_msg "Yeni grup ayarları için logout/login gerekebilir"
fi

# 7. Proje dizini oluştur
info_msg "Proje dizini oluşturuluyor: $PROJECT_DIR"
mkdir -p $PROJECT_DIR
cd $PROJECT_DIR

# 8. Projeyi GitHub'dan çek
if [ -d ".git" ]; then
    info_msg "Proje zaten mevcut, güncelleniyor..."
    git pull
    success_msg "Proje güncellendi"
else
    info_msg "Proje GitHub'dan çekiliyor..."
    
    # Branch adını sor
    read -p "Branch adını girin (varsayılan: main, alternatif: master): " BRANCH_NAME
    BRANCH_NAME=${BRANCH_NAME:-main}
    
    if git clone -b $BRANCH_NAME $GITHUB_REPO . 2>/dev/null || \
       git clone -b master $GITHUB_REPO . 2>/dev/null || \
       git clone $GITHUB_REPO .; then
        success_msg "Proje GitHub'dan çekildi"
    else
        error_msg "GitHub'dan proje çekilemedi. Manuel olarak yükleyin veya SCP kullanın."
        exit 1
    fi
fi

# 9. .env dosyası oluştur
if [ ! -f ".env" ]; then
    if [ -f "env.example" ]; then
        info_msg ".env dosyası oluşturuluyor..."
        cp env.example .env
        success_msg ".env dosyası oluşturuldu (env.example'dan)"
        
        # Hetzner VPS IP'sini otomatik ekle
        sed -i "s/ALLOWED_HOSTS=.*/ALLOWED_HOSTS=localhost,127.0.0.1,0.0.0.0,$VPS_IP/" .env
        sed -i "s|SITE_URL=.*|SITE_URL=http://$VPS_IP|" .env
        sed -i "s/VPS_IP=.*/VPS_IP=$VPS_IP/" .env
        sed -i "s/HETZNER_VPS_IP=.*/HETZNER_VPS_IP=$VPS_IP/" .env
        
        warning_msg "⚠️  .env dosyasını düzenleyip SECRET_KEY ve diğer ayarları yapın!"
    else
        error_msg "env.example dosyası bulunamadı!"
        exit 1
    fi
else
    success_msg ".env dosyası zaten mevcut"
fi

# 10. Docker imajını build et
info_msg "Docker imajı build ediliyor (bu biraz zaman alabilir)..."
docker compose -f $COMPOSE_FILE build --no-cache
success_msg "Docker imajı build edildi"

# 11. Tüm servisleri başlat
info_msg "Tüm servisler başlatılıyor..."
docker compose -f $COMPOSE_FILE up -d
success_msg "Tüm servisler başlatıldı"

# 12. Servis durumunu kontrol et
echo ""
info_msg "Servis durumu kontrol ediliyor..."
sleep 5
docker compose -f $COMPOSE_FILE ps

# 13. Logları göster
echo ""
info_msg "Son loglar (Ctrl+C ile çıkabilirsiniz):"
echo "═══════════════════════════════════════════════════════════════"
docker compose -f $COMPOSE_FILE logs -f --tail=50

