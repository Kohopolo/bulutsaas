"""
Fiyat Hesaplama modülünü tüm paketlere ekler
"""
from django.core.management.base import BaseCommand
from django_tenants.utils import schema_context, get_public_schema_name
from apps.packages.models import Package, PackageModule
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Fiyat Hesaplama modülünü tüm paketlere ekler'
    
    def handle(self, *args, **options):
        with schema_context(get_public_schema_name()):
            # Fiyat Hesaplama modülünü bul
            try:
                price_calculator_module = Module.objects.get(code='price_calculator', is_active=True)
            except Module.DoesNotExist:
                self.stdout.write(
                    self.style.ERROR('Fiyat Hesaplama modülü bulunamadı! Önce create_price_calculator_module komutunu çalıştırın.')
                )
                return
            
            # Tüm aktif paketleri al
            packages = Package.objects.filter(is_active=True, is_deleted=False)
            
            if not packages.exists():
                self.stdout.write(self.style.WARNING('Aktif paket bulunamadı.'))
                return
            
            self.stdout.write(f'Fiyat Hesaplama modülü {packages.count()} pakete ekleniyor...\n')
            
            added_count = 0
            updated_count = 0
            
            for package in packages:
                package_module, created = PackageModule.objects.get_or_create(
                    package=package,
                    module=price_calculator_module,
                    defaults={
                        'is_enabled': True,
                        'is_required': False,
                        'permissions': {
                            'view': True,
                            'use': True,
                        },
                        'limits': {},
                    }
                )
                
                if created:
                    added_count += 1
                    self.stdout.write(
                        self.style.SUCCESS(f'[OK] {package.name} paketine eklendi')
                    )
                else:
                    # Mevcut modülü güncelle (aktif değilse aktif yap)
                    if not package_module.is_enabled:
                        package_module.is_enabled = True
                        package_module.permissions = {
                            'view': True,
                            'use': True,
                        }
                        package_module.save()
                        updated_count += 1
                        self.stdout.write(
                            self.style.WARNING(f'[GÜNCELLENDİ] {package.name} paketinde aktifleştirildi')
                        )
                    else:
                        self.stdout.write(f'  {package.name} paketinde zaten mevcut')
            
            self.stdout.write('')
            self.stdout.write(self.style.SUCCESS('=' * 60))
            self.stdout.write(self.style.SUCCESS(f'Toplam {packages.count()} paket işlendi:'))
            self.stdout.write(f'  - Yeni eklenen: {added_count}')
            self.stdout.write(f'  - Güncellenen: {updated_count}')
            self.stdout.write(self.style.SUCCESS('=' * 60))

