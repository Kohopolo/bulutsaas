#!/bin/bash

################################################################################
# 🌐 Hostinger VPS - Nginx Yapılandırma Script'i
# 
# Bu script:
# 1. Nginx kurulumunu yapar
# 2. bulutacente.com.tr için Nginx yapılandırmasını oluşturur
# 3. Nginx'i başlatır
#
# Kullanım: ./HOSTINGER_NGINX_KURULUM.sh
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

# Hostinger VPS IP
VPS_IP="72.62.35.155"
DOMAIN="bulutacente.com.tr"
PROJECT_DIR="/var/www/bulutsaas-hostinger"

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "🌐 Hostinger VPS - Nginx Yapılandırma"
echo "📡 Domain: $DOMAIN"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# 1. Nginx kurulumu
if ! command -v nginx &> /dev/null; then
    info_msg "Nginx kuruluyor..."
    apt update
    apt install -y nginx
    success_msg "Nginx kuruldu"
else
    success_msg "Nginx zaten kurulu: $(nginx -v 2>&1 | cut -d' ' -f3)"
fi

# 2. Nginx yapılandırması oluştur
info_msg "Nginx yapılandırması oluşturuluyor..."
cat > /etc/nginx/sites-available/$DOMAIN << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN $VPS_IP;

    client_max_body_size 100M;

    # Static dosyalar
    location /static/ {
        alias $PROJECT_DIR/staticfiles/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Media dosyalar
    location /media/ {
        alias $PROJECT_DIR/media/;
        expires 7d;
        add_header Cache-Control "public";
    }

    # Django uygulaması
    location / {
        proxy_pass http://127.0.0.1:8001;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_connect_timeout 300s;
        proxy_send_timeout 300s;
        proxy_read_timeout 300s;
        proxy_buffering off;
    }

    # Health check endpoint
    location /health/ {
        proxy_pass http://127.0.0.1:8001/health/;
        access_log off;
    }
}
EOF

success_msg "Nginx yapılandırması oluşturuldu"

# 3. Symbolic link oluştur
info_msg "Symbolic link oluşturuluyor..."
ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/
success_msg "Symbolic link oluşturuldu"

# 4. Default yapılandırmayı kaldır
if [ -f /etc/nginx/sites-enabled/default ]; then
    info_msg "Default yapılandırma kaldırılıyor..."
    rm /etc/nginx/sites-enabled/default
    success_msg "Default yapılandırma kaldırıldı"
fi

# 5. Nginx yapılandırmasını test et
info_msg "Nginx yapılandırması test ediliyor..."
if nginx -t; then
    success_msg "Nginx yapılandırması doğru"
else
    error_msg "Nginx yapılandırması hatalı!"
    exit 1
fi

# 6. Nginx'i başlat
info_msg "Nginx başlatılıyor..."
systemctl restart nginx
systemctl enable nginx
success_msg "Nginx başlatıldı"

# 7. Nginx durumunu kontrol et
echo ""
info_msg "Nginx durumu:"
systemctl status nginx --no-pager -l

# 8. Test
echo ""
info_msg "Test ediliyor..."
sleep 2

# IP ile test
if curl -I http://$VPS_IP 2>/dev/null | grep -q "200 OK"; then
    success_msg "IP ile erişim başarılı: http://$VPS_IP"
else
    warning_msg "IP ile erişim test edilemedi"
fi

# Domain ile test (DNS ayarlıysa)
if curl -I http://$DOMAIN 2>/dev/null | grep -q "200 OK"; then
    success_msg "Domain ile erişim başarılı: http://$DOMAIN"
else
    warning_msg "Domain ile erişim test edilemedi (DNS ayarlarını kontrol edin)"
fi

echo ""
success_msg "Nginx yapılandırması tamamlandı!"
info_msg "Sonraki adım: SSL sertifikası kurulumu (Let's Encrypt)"
info_msg "Komut: certbot --nginx -d $DOMAIN -d www.$DOMAIN"

