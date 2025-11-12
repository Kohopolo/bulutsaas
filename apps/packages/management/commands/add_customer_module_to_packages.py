"""
Customer Modülünü Tüm Paketlere Ekleme Komutu
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module
from apps.packages.models import Package, PackageModule


class Command(BaseCommand):
    help = 'Customer modülünü tüm aktif paketlere ekler'

    def handle(self, *args, **options):
        # Customer modülünü al
        try:
            customer_module = Module.objects.get(code='customers')
        except Module.DoesNotExist:
            self.stdout.write(self.style.ERROR('[ERROR] Customer modulu bulunamadi. Once create_customer_module komutunu calistirin.'))
            return

        # Tüm aktif paketleri al
        packages = Package.objects.filter(is_active=True)
        
        if not packages.exists():
            self.stdout.write(self.style.WARNING('[WARN] Aktif paket bulunamadi.'))
            return

        added_count = 0
        updated_count = 0

        for package in packages:
            # Customer modülü için varsayılan izinler
            customer_permissions = {
                'view': True,
                'add': True,
                'edit': True,
                'delete': True,
                'export': True,
                'view_notes': True,
                'add_notes': True,
                'view_loyalty': True,
                'manage_loyalty': True,
            }

            # Customer modülü için varsayılan limitler (sınırsız - core modül)
            customer_limits = {
                'max_customers': -1,  # -1 = sınırsız
            }

            package_module, created = PackageModule.objects.get_or_create(
                package=package,
                module=customer_module,
                defaults={
                    'is_enabled': True,
                    'permissions': customer_permissions,
                    'limits': customer_limits,
                }
            )

            if created:
                added_count += 1
                self.stdout.write(self.style.SUCCESS(f'[OK] {package.name} paketine Customer modulu eklendi'))
            else:
                # Mevcut modülü güncelle
                package_module.is_enabled = True
                package_module.permissions = customer_permissions
                package_module.limits = customer_limits
                package_module.save()
                updated_count += 1
                self.stdout.write(self.style.SUCCESS(f'[UPDATE] {package.name} paketindeki Customer modulu guncellendi'))

        self.stdout.write(self.style.SUCCESS(f'\n[OK] Toplam: {added_count} pakete eklendi, {updated_count} paket guncellendi'))

