"""
Django settings for SaaS 2026 project.
Multi-tenant otel/tur yönetim sistemi.
"""

import os
from pathlib import Path
import environ

# Build paths
BASE_DIR = Path(__file__).resolve().parent.parent

# Environment variables
env = environ.Env(
    DEBUG=(bool, False),
    ALLOWED_HOSTS=(list, ['localhost', '127.0.0.1']),
)

# .env dosyasını oku (varsa)
env_file = BASE_DIR / '.env'
if env_file.exists():
    environ.Env.read_env(str(env_file))

# SECURITY WARNING: keep the secret key used in production secret!
SECRET_KEY = env('SECRET_KEY', default='django-insecure-change-this-key')

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = env('DEBUG')

# ALLOWED_HOSTS - Tenant domain'leri için genişletilmiş liste
ALLOWED_HOSTS = env.list('ALLOWED_HOSTS', default=['localhost', '127.0.0.1'])
# Tenant domain'lerini de ekle (wildcard çalışmaz, her domain'i ayrı ekle)
ALLOWED_HOSTS.extend([
    'test-otel.localhost',
    'test-otel.127.0.0.1',
])

# Application definition
SHARED_APPS = [
    # Django Tenants (ilk sırada olmalı!)
    'django_tenants',
    
    # Django Apps
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    
    # Bildirim Sistemi (Shared)
    'apps.notifications',
    
    # Third Party Apps
    'rest_framework',
    'rest_framework.authtoken',
    'django_filters',
    'drf_spectacular',
    'guardian',
    'django_celery_beat',
    'django_celery_results',
    'crispy_forms',
    'crispy_bootstrap4',
    
    # Shared Apps (Tüm tenant'lar için ortak)
    'apps.core',
    'apps.packages',
    'apps.modules',
    'apps.permissions',
    'apps.subscriptions',
    'apps.payments',
    'apps.tenants',
    'apps.ai',  # AI Yönetimi
]

TENANT_APPS = [
    # Tenant Core (İlk sırada olmalı - kullanıcı, rol, yetki yönetimi)
    'apps.tenant_apps.core',
    
    # Tenant Subscription Management (Paket yönetimi)
    'apps.tenant_apps.subscriptions',
    
    # Tenant'a özel uygulamalar
    # Her tenant için ayrı schema'da çalışır
    'apps.tenant_apps.hotels',
    'apps.tenant_apps.reservations',
    'apps.tenant_apps.reception',  # Resepsiyon (Ön Büro) - Rezervasyon odaklı
    'apps.tenant_apps.housekeeping',  # Kat Hizmetleri
    'apps.tenant_apps.technical_service',  # Teknik Servis
    'apps.tenant_apps.quality_control',  # Kalite Kontrol
    'apps.tenant_apps.sales',  # Satış Yönetimi
    'apps.tenant_apps.staff',  # Personel Yönetimi
    'apps.tenant_apps.channels',
    'apps.tenant_apps.tours',
    'apps.tenant_apps.ai',  # Tenant AI Yönetimi
    'apps.tenant_apps.finance',  # Kasa Yönetimi
    'apps.tenant_apps.accounting',  # Muhasebe Yönetimi
    'apps.tenant_apps.refunds',  # İade Yönetimi
    'apps.tenant_apps.payment_management',  # Ödeme Yönetimi
]

INSTALLED_APPS = list(SHARED_APPS) + [app for app in TENANT_APPS if app not in SHARED_APPS]

# Tenant Configuration
TENANT_MODEL = "tenants.Tenant"
TENANT_DOMAIN_MODEL = "tenants.Domain"
PUBLIC_SCHEMA_NAME = 'public'
PUBLIC_SCHEMA_URLCONF = 'config.urls_public'

MIDDLEWARE = [
    # Django Tenants Middleware (ilk sırada!)
    'django_tenants.middleware.main.TenantMainMiddleware',
    
    'django.middleware.security.SecurityMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
    
    # Otel Middleware (Tenant middleware'den sonra)
    'apps.tenant_apps.hotels.middleware.HotelMiddleware',
]

ROOT_URLCONF = 'config.urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [BASE_DIR / 'templates'],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.debug',
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
                # Custom context processors
                'apps.core.context_processors.site_settings',
                'apps.tenant_apps.core.context_processors.tenant_modules',
                'apps.tenant_apps.hotels.context_processors.hotel_context',
            ],
        },
    },
]

# Custom Form Renderer - Widget template'lerini bulabilmek için
# Ana template engine'i kullan (TEMPLATES ayarlarından)
FORM_RENDERER = 'apps.core.form_renderer.CustomDjangoTemplates'

WSGI_APPLICATION = 'config.wsgi.application'

# Database
DATABASES = {
    'default': {
        'ENGINE': 'django_tenants.postgresql_backend',
        'NAME': env('POSTGRES_DB', default='saas_db'),
        'USER': env('POSTGRES_USER', default='saas_user'),
        'PASSWORD': env('POSTGRES_PASSWORD', default='saas_password_2026'),
        'HOST': env('POSTGRES_HOST', default='db'),
        'PORT': env('POSTGRES_PORT', default='5432'),
    }
}

DATABASE_ROUTERS = (
    'django_tenants.routers.TenantSyncRouter',
)

# Password validation
AUTH_PASSWORD_VALIDATORS = [
    {
        'NAME': 'django.contrib.auth.password_validation.UserAttributeSimilarityValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.MinimumLengthValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.CommonPasswordValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.NumericPasswordValidator',
    },
]

# Internationalization
LANGUAGE_CODE = 'tr'
TIME_ZONE = 'Europe/Istanbul'
USE_I18N = True
USE_TZ = True

# Static files (CSS, JavaScript, Images)
STATIC_URL = '/static/'
STATIC_ROOT = BASE_DIR / 'staticfiles'
STATICFILES_DIRS = [
    BASE_DIR / 'static',
]

# Media files
MEDIA_URL = '/media/'
MEDIA_ROOT = BASE_DIR / 'media'

# Default primary key field type
DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'

# Redis Configuration
REDIS_URL = env('REDIS_URL', default='redis://redis:6379/0')

# Cache Configuration (Dummy cache - Redis yok)
CACHES = {
    'default': {
        'BACKEND': 'django.core.cache.backends.dummy.DummyCache',
    }
}

# Redis olsaydı:
# CACHES = {
#     'default': {
#         'BACKEND': 'django_redis.cache.RedisCache',
#         'LOCATION': REDIS_URL,
#         'OPTIONS': {
#             'CLIENT_CLASS': 'django_redis.client.DefaultClient',
#         },
#         'KEY_PREFIX': 'saas2026',
#         'TIMEOUT': 300,
#     }
# }

# Session Configuration (Database-based - Redis yok)
SESSION_ENGINE = 'django.contrib.sessions.backends.db'
# SESSION_CACHE_ALIAS = 'default'

# Celery Configuration
CELERY_BROKER_URL = env('CELERY_BROKER_URL', default=REDIS_URL)
CELERY_RESULT_BACKEND = env('CELERY_RESULT_BACKEND', default=REDIS_URL)
CELERY_ACCEPT_CONTENT = ['json']
CELERY_TASK_SERIALIZER = 'json'
CELERY_RESULT_SERIALIZER = 'json'
CELERY_TIMEZONE = TIME_ZONE
CELERY_BEAT_SCHEDULER = 'django_celery_beat.schedulers:DatabaseScheduler'

# Email Configuration
EMAIL_BACKEND = env('EMAIL_BACKEND', default='django.core.mail.backends.console.EmailBackend')
EMAIL_HOST = env('EMAIL_HOST', default='localhost')
EMAIL_PORT = env.int('EMAIL_PORT', default=587)
EMAIL_USE_TLS = env.bool('EMAIL_USE_TLS', default=True)
EMAIL_HOST_USER = env('EMAIL_HOST_USER', default='')
EMAIL_HOST_PASSWORD = env('EMAIL_HOST_PASSWORD', default='')
DEFAULT_FROM_EMAIL = env('DEFAULT_FROM_EMAIL', default='noreply@saas2026.com')

# Django REST Framework
REST_FRAMEWORK = {
    'DEFAULT_PERMISSION_CLASSES': [
        'rest_framework.permissions.IsAuthenticated',
    ],
    'DEFAULT_AUTHENTICATION_CLASSES': [
        'rest_framework.authentication.SessionAuthentication',
        'rest_framework.authentication.TokenAuthentication',
    ],
    'DEFAULT_PAGINATION_CLASS': 'rest_framework.pagination.PageNumberPagination',
    'PAGE_SIZE': 50,
    'DEFAULT_FILTER_BACKENDS': [
        'django_filters.rest_framework.DjangoFilterBackend',
        'rest_framework.filters.SearchFilter',
        'rest_framework.filters.OrderingFilter',
    ],
    'DEFAULT_SCHEMA_CLASS': 'drf_spectacular.openapi.AutoSchema',
}

# DRF Spectacular (API Documentation)
SPECTACULAR_SETTINGS = {
    'TITLE': 'SaaS 2026 API',
    'DESCRIPTION': 'Multi-tenant Otel/Tur Yönetim Sistemi API',
    'VERSION': '1.0.0',
    'SERVE_INCLUDE_SCHEMA': False,
}

# Django Guardian (Object Permissions)
AUTHENTICATION_BACKENDS = (
    'django.contrib.auth.backends.ModelBackend',
    'guardian.backends.ObjectPermissionBackend',
)

# Custom Admin Panel - VB Desktop Application Style with Tailwind CSS
# Admin template override: templates/admin/base.html

# Crispy Forms
CRISPY_ALLOWED_TEMPLATE_PACKS = "bootstrap4"
CRISPY_TEMPLATE_PACK = "bootstrap4"

# Ensure log directory exists before Django configures file based handlers
LOG_DIR = BASE_DIR / "logs"
LOG_DIR.mkdir(parents=True, exist_ok=True)
LOG_FILE = str(LOG_DIR / "django.log")  # String olarak sakla - Path nesnesi kaynaklı uyumsuzluğu giderir

# Logging
LOGGING = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'verbose': {
            'format': '{levelname} {asctime} {module} {process:d} {thread:d} {message}',
            'style': '{',
        },
    },
    'handlers': {
        'console': {
            'class': 'logging.StreamHandler',
            'formatter': 'verbose',
        },
        'file': {
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': LOG_FILE,
            'maxBytes': 1024 * 1024 * 10,  # 10 MB
            'backupCount': 5,
            'formatter': 'verbose',
        },
    },
    'root': {
        'handlers': ['console', 'file'],
        'level': 'INFO',
    },
    'loggers': {
        'django': {
            'handlers': ['console', 'file'],
            'level': 'INFO',
            'propagate': False,
        },
    },
}

# Security Settings (Production)
if not DEBUG:
    SECURE_SSL_REDIRECT = True
    SESSION_COOKIE_SECURE = True
    CSRF_COOKIE_SECURE = True
    SECURE_BROWSER_XSS_FILTER = True
    SECURE_CONTENT_TYPE_NOSNIFF = True
    X_FRAME_OPTIONS = 'DENY'

# Custom Settings
SITE_NAME = env('SITE_NAME', default='SaaS 2026')
SITE_URL = env('SITE_URL', default='http://localhost:8000')
ADMIN_URL = env('ADMIN_URL', default='admin/')

# Subscription Settings
TRIAL_PERIOD_DAYS = env.int('TRIAL_PERIOD_DAYS', default=14)
SUBSCRIPTION_GRACE_PERIOD_DAYS = env.int('SUBSCRIPTION_GRACE_PERIOD_DAYS', default=3)

# Payment Settings
STRIPE_PUBLIC_KEY = env('STRIPE_PUBLIC_KEY', default='')
STRIPE_SECRET_KEY = env('STRIPE_SECRET_KEY', default='')
STRIPE_WEBHOOK_SECRET = env('STRIPE_WEBHOOK_SECRET', default='')


