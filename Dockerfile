# Python 3.11 Alpine imajı (hafif ve hızlı)
FROM python:3.11-alpine

# Maintainer bilgisi
LABEL maintainer="SaaS 2026 Team"
LABEL description="SaaS 2026 - Multi-Tenant Otel/Tur Yönetim Sistemi"

# Environment değişkenleri
ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

# Çalışma dizini
WORKDIR /app

# Sistem bağımlılıkları (PostgreSQL, Pillow için)
RUN apk add --no-cache \
    postgresql-dev \
    gcc \
    python3-dev \
    musl-dev \
    jpeg-dev \
    zlib-dev \
    libjpeg \
    libpq \
    bash \
    curl \
    && rm -rf /var/cache/apk/*

# Önce tüm dosyaları kopyala (requirements.txt dahil)
COPY . .

# Python bağımlılıklarını kur
RUN pip install --upgrade pip && \
    pip install -r requirements.txt

# Static ve media klasörleri oluştur
RUN mkdir -p /app/staticfiles /app/media /app/logs

# Script'leri çalıştırılabilir yap
RUN chmod +x /app/docker-entrypoint.sh 2>/dev/null || true

# Port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:8000/health/ || exit 1

# Default komut
CMD ["gunicorn", "config.wsgi:application", "--bind", "0.0.0.0:8000", "--workers", "4"]



