"""
Tüm paketlere hotels modülünü ekle
"""
from django.core.management.base import BaseCommand
from apps.packages.models import Package, PackageModule
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Tüm paketlere hotels modülünü ekler'

    def handle(self, *args, **options):
        try:
            module = Module.objects.get(code='hotels')
        except Module.DoesNotExist:
            self.stdout.write(self.style.ERROR('[HATA] Hotels modülü bulunamadı. Önce create_hotel_module komutunu çalıştırın.'))
            return
        
        packages = Package.objects.filter(is_active=True)
        added_count = 0
        
        for package in packages:
            package_module, created = PackageModule.objects.get_or_create(
                package=package,
                module=module,
                defaults={
                    'is_enabled': True,
                    'permissions': {
                        'view': True,
                        'add': True,
                        'edit': True,
                        'delete': True,
                        'admin': True,
                    },
                    'limits': {
                        'max_hotels': package.max_hotels,
                        'max_room_numbers': package.max_rooms * 10,  # Oda sayısının 10 katı
                        'max_users': package.max_users,
                        'max_reservations': package.max_reservations_per_month,
                        'max_ai_credits': 1000,  # Varsayılan AI kredi limiti
                    },
                }
            )
            if created:
                added_count += 1
        
        self.stdout.write(self.style.SUCCESS(f'[OK] {added_count} pakete hotels modülü eklendi.'))

