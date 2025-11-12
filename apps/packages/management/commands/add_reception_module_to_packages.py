"""
Resepsiyon Modülünü Tüm Paketlere Ekleme Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module
from apps.packages.models import Package, PackageModule


class Command(BaseCommand):
    help = 'Resepsiyon modülünü tüm paketlere ekler'

    def handle(self, *args, **options):
        try:
            module = Module.objects.get(code='reception')
        except Module.DoesNotExist:
            self.stdout.write(
                self.style.ERROR('[HATA] Resepsiyon modülü bulunamadı. Önce create_reception_module komutunu çalıştırın.')
            )
            return
        
        packages = Package.objects.filter(is_active=True, is_deleted=False)
        added_count = 0
        
        for package in packages:
            package_module, created = PackageModule.objects.get_or_create(
                package=package,
                module=module,
                defaults={
                    'is_enabled': True,
                    'limits': {
                        'max_reservations': 100,
                        'max_reservations_per_month': 50,
                        'max_concurrent_reservations': 10,
                    }
                }
            )
            
            if created:
                added_count += 1
                self.stdout.write(
                    f'  [OK] {package.name} paketine eklendi'
                )
            else:
                self.stdout.write(
                    f'  [SKIP] {package.name} paketinde zaten mevcut'
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'[OK] {added_count} pakete resepsiyon modülü eklendi.')
        )

