#!/bin/bash
# Docker Manuel Kurulum Scripti
# CloudPanel'de Docker yoksa bu script ile kurun

echo "🐳 Docker kurulumu başlatılıyor..."
echo ""

# Eski Docker versiyonlarını kaldır
echo "📦 Eski Docker versiyonları kaldırılıyor..."
sudo apt-get remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true

# Gerekli paketleri kur
echo "📦 Gerekli paketler kuruluyor..."
sudo apt-get update
sudo apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release

# Docker'ın resmi GPG key'ini ekle
echo "🔑 Docker GPG key ekleniyor..."
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Docker repository'yi ekle
echo "📚 Docker repository ekleniyor..."
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Docker'ı kur
echo "🐳 Docker kuruluyor..."
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Docker servisini başlat
echo "🚀 Docker servisi başlatılıyor..."
sudo systemctl start docker
sudo systemctl enable docker

# Docker kurulumunu kontrol et
echo ""
echo "✅ Docker kurulumu tamamlandı!"
echo ""
echo "📋 Docker Versiyonları:"
docker --version
docker compose version

echo ""
echo "📋 Docker Servis Durumu:"
sudo systemctl status docker --no-pager | head -5

echo ""
echo "✅ Docker başarıyla kuruldu!"
echo ""
echo "🔧 CloudPanel kullanıcısını docker grubuna eklemek için:"
echo "sudo usermod -aG docker cloudpanel"
echo ""

