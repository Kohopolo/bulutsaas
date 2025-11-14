"""
Management Command: Bungalovs Modülünü Paketlere Ekle
Public schema'da tüm aktif paketlere bungalovs modülünü ekler
"""
from django.core.management.base import BaseCommand
from django.db import connection
from apps.modules.models import Module
from apps.packages.models import Package, PackageModule


class Command(BaseCommand):
    help = 'Bungalovs modülünü tüm aktif paketlere ekler'

    def handle(self, *args, **options):
        # Public schema'da çalıştığımızdan emin ol
        if connection.schema_name != 'public':
            self.stdout.write(self.style.WARNING('Bu komut sadece public schema\'da çalıştırılmalıdır.'))
            return

        # Modülü bul
        try:
            module = Module.objects.get(code='bungalovs', is_active=True)
        except Module.DoesNotExist:
            self.stdout.write(self.style.ERROR('Bungalovs modülü bulunamadı. Önce create_bungalovs_module komutunu çalıştırın.'))
            return

        # Tüm aktif paketleri al
        packages = Package.objects.filter(is_active=True, is_deleted=False)

        if not packages.exists():
            self.stdout.write(self.style.WARNING('Aktif paket bulunamadı.'))
            return

        added_count = 0
        skipped_count = 0

        for package in packages:
            # Paket modülünü kontrol et
            package_module, created = PackageModule.objects.get_or_create(
                package=package,
                module=module,
                defaults={
                    'is_enabled': True,
                    'permissions': {
                        'view': True,
                        'add': True,
                        'edit': True,
                        'delete': False,  # Silme yetkisi varsayılan olarak kapalı
                        'voucher': True,
                        'payment': True,
                    },
                    'limits': {
                        'max_bungalovs': 50,
                        'max_reservations_per_month': 200,
                    },
                }
            )

            if created:
                added_count += 1
                self.stdout.write(self.style.SUCCESS(f'  [OK] {package.name} paketine eklendi'))
            else:
                # Mevcut modülü aktifleştir
                if not package_module.is_enabled:
                    package_module.is_enabled = True
                    package_module.save()
                    added_count += 1
                    self.stdout.write(self.style.SUCCESS(f'  [OK] {package.name} paketinde modül aktifleştirildi'))
                else:
                    skipped_count += 1
                    self.stdout.write(self.style.WARNING(f'  [SKIP] {package.name} paketinde zaten mevcut ve aktif'))

        self.stdout.write(self.style.SUCCESS(f'\n[ÖZET] {added_count} pakete eklendi, {skipped_count} pakette zaten mevcuttu.'))

