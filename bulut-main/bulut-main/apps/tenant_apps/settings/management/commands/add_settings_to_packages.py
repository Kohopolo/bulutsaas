"""
Settings Modülünü Tüm Paketlere Ekleme Komutu
"""
from django.core.management.base import BaseCommand
from apps.packages.models import Package, PackageModule
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Settings modülünü tüm paketlere ekler'

    def handle(self, *args, **options):
        # Settings modülünü al
        try:
            settings_module = Module.objects.get(code='settings', is_active=True)
        except Module.DoesNotExist:
            self.stdout.write(
                self.style.ERROR('[HATA] Settings modülü bulunamadı. Önce create_settings_module komutunu çalıştırın.')
            )
            return
        
        # Tüm paketleri al
        packages = Package.objects.filter(is_active=True)
        
        if not packages.exists():
            self.stdout.write(
                self.style.WARNING('[UYARI] Aktif paket bulunamadı.')
            )
            return
        
        added_count = 0
        updated_count = 0
        
        for package in packages:
            # Paket modülünü kontrol et
            package_module, created = PackageModule.objects.get_or_create(
                package=package,
                module=settings_module,
                defaults={
                    'is_enabled': True,
                }
            )
            
            if created:
                added_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] Settings modülü "{package.name}" paketine eklendi')
                )
            else:
                # Modül varsa aktifleştir
                if not package_module.is_enabled:
                    package_module.is_enabled = True
                    package_module.save()
                    updated_count += 1
                    self.stdout.write(
                        self.style.WARNING(f'[GUNCELLENDI] Settings modülü "{package.name}" paketinde aktifleştirildi')
                    )
                else:
                    self.stdout.write(
                        self.style.SUCCESS(f'[SKIP] Settings modülü "{package.name}" paketinde zaten aktif')
                    )
        
        self.stdout.write(
            self.style.SUCCESS(
                f'\n[OK] İşlem tamamlandı! {added_count} pakete eklendi, {updated_count} paket güncellendi.'
            )
        )

