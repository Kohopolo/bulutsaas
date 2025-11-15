"""
Feribot Bileti Modülünü Tüm Paketlere Ekleme Command
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context
from apps.packages.models import Package, PackageModule


class Command(BaseCommand):
    help = 'Feribot Bileti modülünü tüm paketlere ekler'

    def handle(self, *args, **options):
        with schema_context('public'):
            # Modülü bul
            from apps.modules.models import Module
            try:
                module = Module.objects.get(code='ferry_tickets')
            except Module.DoesNotExist:
                self.stdout.write(
                    self.style.ERROR('[HATA] Feribot Bileti modülü bulunamadı. Önce modülü oluşturun.')
                )
                return
            
            # Tüm paketleri al
            packages = Package.objects.filter(is_active=True)
            
            added_count = 0
            skipped_count = 0
            
            for package in packages:
                # Paket modülü zaten var mı kontrol et
                package_module, created = PackageModule.objects.get_or_create(
                    package=package,
                    module=module,
                    defaults={
                        'is_enabled': True,
                        'is_required': False,
                        'permissions': {
                            'view': True,
                            'add': True,
                            'edit': True,
                            'delete': False,
                            'voucher': True,
                            'payment': True,
                        },
                        'limits': {
                            'max_tickets': 1000,
                            'max_tickets_per_month': 100,
                        }
                    }
                )
                
                if created:
                    added_count += 1
                    self.stdout.write(
                        self.style.SUCCESS(f'[OK] {package.name} paketine eklendi')
                    )
                else:
                    skipped_count += 1
                    self.stdout.write(
                        self.style.WARNING(f'[SKIP] {package.name} paketinde zaten mevcut')
                    )
            
            self.stdout.write(
                self.style.SUCCESS(
                    f'\n[ÖZET] {added_count} pakete eklendi, {skipped_count} pakette zaten mevcuttu.'
                )
            )

