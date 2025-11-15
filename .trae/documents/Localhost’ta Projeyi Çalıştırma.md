## Önkoşullar
- Python 3.11+ ve pip
- PostgreSQL 15+ (yerel kurulum) veya Docker Desktop ile `db` servisini çalıştırma
- Redis (opsiyonel; geliştirici ortamında gerekmez, cache Dummy çalışır)
- Çalışma dizini: `c:\xampp\htdocs\bulutacente`

## Seçenek 1: Yerel Python (hızlı başlangıç)
- Sanal ortam ve bağımlılıklar:
  - `py -m venv .venv`
  - `.venv\Scripts\Activate.ps1`
  - `pip install -r requirements.txt`
- `.env` dosyası (yerel DB için örnek):
  - `DEBUG=True`
  - `SECRET_KEY=django-insecure-development-key`
  - `POSTGRES_HOST=localhost`
  - `POSTGRES_PORT=5432`
  - `POSTGRES_DB=saas_db`
  - `POSTGRES_USER=saas_user`
  - `POSTGRES_PASSWORD=saas_password_2026`
  - `ALLOWED_HOSTS=localhost,127.0.0.1,test-otel.localhost`
  - Referans: `env.example:1-18`, DB motoru: `config/settings.py:166-172`
- PostgreSQL’i başlatın (yerel veya Docker):
  - Docker ile: `docker compose up -d db` (isteğe bağlı: `redis`)
- Çoklu-şema migrasyonları:
  - `python manage.py migrate_schemas --shared`
  - `python manage.py migrate_schemas`
- Örnek tenant ve domain oluşturma:
  - `python manage.py create_test_package_tenant`
  - Komut çıktısı tenant URL’i verir; domain: `test-otel.localhost` (bkz. `apps/core/management/commands/create_test_package_tenant.py:121-164`)
- Tenant içinde admin kullanıcı oluşturma:
  - `python manage.py create_tenant_user --tenant-slug test-otel --username admin --email admin@example.com --password Admin123!`
  - Referans: `apps/core/management/commands/create_tenant_user.py:13-22,41-63`
- Geliştirme sunucusunu başlatın:
  - `python manage.py runserver 0.0.0.0:8000`

## Seçenek 2: Docker Compose (tam stack)
- Servisleri kademeli başlatın:
  - `docker compose up -d db redis`
- Şema migrasyonlarını konteyner içinde çalıştırın:
  - `docker compose run --rm web python manage.py migrate_schemas --shared`
  - `docker compose run --rm web python manage.py migrate_schemas`
  - `docker compose run --rm web python manage.py create_test_package_tenant`
- Not: `docker-compose.yml` içinde `python manage.py wait_for_db` çağrısı var ancak repo’da bu komut tanımlı değil (bkz. `docker-compose.yml:50-57`). Çözüm seçenekleri:
  - Web servis komutundan `wait_for_db` adımını kaldırmak
  - Basit bir `wait_for_db` management komutu eklemek (PostgreSQL hazır olana dek bekleyen)
- Uygulama ve Nginx’i başlatın:
  - `docker compose up -d web nginx`
- İsteğe bağlı: Celery worker/beat
  - `docker compose up -d celery celery-beat`

## Erişim ve URL’ler
- Public (landing ve admin): `http://localhost:8000/`, `http://localhost:8000/admin/` (bkz. `config/urls_public.py:15,21`)
- Tenant URL: `http://test-otel.localhost:8000/` (ALLOWED_HOSTS’a ekli, bkz. `config/settings.py:31-36`)
- API dokümantasyon: `http://localhost:8000/api/docs/` (bkz. `config/urls.py:21-23`)

## Notlar ve Sorun Giderme
- Proje PostgreSQL gerektirir; MySQL/MariaDB uyumlu değildir (`config/settings.py:166-172`)
- Redis yoksa cache Dummy çalışır; Celery dev için opsiyoneldir (`config/settings.py:218-223,243-250`)
- Windows’ta `test-otel.localhost` bazı ortamlarda otomatik 127.0.0.1’e çözülmezse `C:\Windows\System32\drivers\etc\hosts` dosyasına satır ekleyin:
  - `127.0.0.1 test-otel.localhost`

## Onay Sonrası Uygulanacak Adımlar
- Gerekirse `wait_for_db` komutunu eklemek veya Compose komutunu sadeleştirmek
- `.env` dosyasını oluşturup Postgres bağlantısını doğrulamak
- Migrasyonları ve tenant kurulumunu çalıştırmak
- Sunucuyu başlatıp erişim URL’lerini paylaşmak