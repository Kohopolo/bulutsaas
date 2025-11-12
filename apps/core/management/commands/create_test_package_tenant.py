"""
Test için paket ve tenant oluştur
"""
from django.core.management.base import BaseCommand
from django.utils import timezone
from datetime import timedelta
from apps.packages.models import Package, PackageModule
from apps.tenants.models import Tenant, Domain
from apps.subscriptions.models import Subscription
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Test için paket ve tenant oluştur'

    def handle(self, *args, **options):
        # 1. Modül oluştur (eğer yoksa)
        module, created = Module.objects.get_or_create(
            code='reservations',
            defaults={
                'name': 'Rezervasyon Yönetimi',
                'description': 'Otel rezervasyon yönetim modülü',
                'icon': '📅',
                'category': 'reservation',
                'app_name': 'apps.tenant_apps.reservations',
                'url_prefix': '/reservations/',
                'available_permissions': {
                    'view': 'Görüntüleme',
                    'add': 'Ekleme',
                    'edit': 'Düzenleme',
                    'delete': 'Silme',
                    'checkin': 'Check-in',
                    'checkout': 'Check-out',
                },
                'is_active': True,
                'is_core': True,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Modul olusturuldu: {module.name}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Modul zaten mevcut: {module.name}'))
        
        # 2. Paket oluştur
        package, created = Package.objects.get_or_create(
            code='starter',
            defaults={
                'name': 'Başlangıç Paketi',
                'description': 'Küçük işletmeler için ideal başlangıç paketi',
                'price_monthly': 299.00,
                'price_yearly': 2990.00,
                'currency': 'TRY',
                'max_hotels': 1,
                'max_rooms': 10,
                'max_users': 3,
                'max_reservations_per_month': 100,
                'max_storage_gb': 1,
                'max_api_calls_per_day': 1000,
                'trial_days': 14,
                'is_featured': True,
                'is_active': True,
                'sort_order': 1,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Paket olusturuldu: {package.name}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Paket zaten mevcut: {package.name}'))
        
        # 3. Pakete modül ekle
        package_module, created = PackageModule.objects.get_or_create(
            package=package,
            module=module,
            defaults={
                'permissions': {
                    'view': True,
                    'add': True,
                    'edit': True,
                    'delete': False,
                    'checkin': True,
                    'checkout': True,
                },
                'is_enabled': True,
                'is_required': True,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Modul pakete eklendi'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Modul zaten pakette'))
        
        # 4. Tenant oluştur
        tenant_slug = 'test-otel'
        tenant, created = Tenant.objects.get_or_create(
            slug=tenant_slug,
            defaults={
                'name': 'Test Otel',
                'schema_name': f'tenant_{tenant_slug}',
                'owner_name': 'Test Sahibi',
                'owner_email': 'test@example.com',
                'phone': '+90 555 123 45 67',
                'address': 'Test Adresi, Test Mahallesi',
                'city': 'İstanbul',
                'country': 'Türkiye',
                'is_active': True,
                'is_trial': True,
                'trial_end_date': timezone.now().date() + timedelta(days=14),
                'package': package,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Tenant olusturuldu: {tenant.name}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Tenant zaten mevcut: {tenant.name}'))
            # Mevcut tenant'a paket atanmamışsa ata
            if not tenant.package:
                tenant.package = package
                tenant.save()
                self.stdout.write(self.style.SUCCESS(f'[OK] Paket tenant\'a atandi'))
        
        # 5. Domain oluştur
        domain_domain = f'{tenant_slug}.localhost'
        domain, created = Domain.objects.get_or_create(
            domain=domain_domain,
            defaults={
                'tenant': tenant,
                'is_primary': True,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Domain olusturuldu: {domain.domain}'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Domain zaten mevcut: {domain.domain}'))
        
        # 6. Subscription oluştur
        start_date = timezone.now().date()
        end_date = start_date + timedelta(days=30)
        subscription, created = Subscription.objects.get_or_create(
            tenant=tenant,
            package=package,
            defaults={
                'period': 'monthly',
                'start_date': start_date,
                'end_date': end_date,
                'next_billing_date': end_date,
                'amount': package.price_monthly,
                'currency': package.currency,
                'status': 'active',
                'auto_renew': True,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] Abonelik olusturuldu'))
        else:
            self.stdout.write(self.style.WARNING(f'[SKIP] Abonelik zaten mevcut'))
        
        # Özet
        self.stdout.write(self.style.SUCCESS('\n=== OLUSTURULAN KAYITLAR ==='))
        self.stdout.write(f'Paket: {package.name} ({package.code})')
        self.stdout.write(f'Tenant: {tenant.name} ({tenant.slug})')
        self.stdout.write(f'Domain: {domain.domain}')
        self.stdout.write(f'Abonelik: {subscription.get_status_display()}')
        self.stdout.write(f'\nTenant URL: http://{domain.domain}:8000/')
        self.stdout.write(f'Tenant Login: http://{domain.domain}:8000/login/')
