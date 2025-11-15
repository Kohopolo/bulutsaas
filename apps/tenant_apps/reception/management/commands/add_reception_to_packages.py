"""
Reception Modülünü Tüm Paketlere Ekleme Command
"""
from django.core.management.base import BaseCommand
from apps.packages.models import Package, PackageModule
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Reception modülünü tüm aktif paketlere ekler'

    def add_arguments(self, parser):
        parser.add_argument(
            '--package',
            type=str,
            help='Belirli bir pakete ekle (paket kodu)',
        )
        parser.add_argument(
            '--enabled',
            action='store_true',
            help='Modülü aktif olarak ekle (varsayılan: True)',
            default=True,
        )
        parser.add_argument(
            '--required',
            action='store_true',
            help='Modülü zorunlu olarak işaretle',
        )

    def handle(self, *args, **options):
        try:
            module = Module.objects.get(code='reception')
        except Module.DoesNotExist:
            self.stdout.write(
                self.style.ERROR('[HATA] Reception modülü bulunamadı. Önce create_reception_module komutunu çalıştırın.')
            )
            return
        
        package_code = options.get('package')
        is_enabled = options.get('enabled', True)
        is_required = options.get('required', False)
        
        if package_code:
            packages = Package.objects.filter(code=package_code, is_active=True, is_deleted=False)
        else:
            packages = Package.objects.filter(is_active=True, is_deleted=False)
        
        if not packages.exists():
            self.stdout.write(
                self.style.WARNING('[UYARI] Aktif paket bulunamadı.')
            )
            return
        
        added_count = 0
        skipped_count = 0
        
        for package in packages:
            package_module, created = PackageModule.objects.get_or_create(
                package=package,
                module=module,
                defaults={
                    'is_enabled': is_enabled,
                    'is_required': is_required,
                    'permissions': {
                        'view': True,
                        'add': True,
                        'edit': True,
                        'delete': False,
                        'checkin': True,
                        'checkout': True,
                    },
                    'limits': {
                        'max_reservations': 1000,
                        'max_reservations_per_month': 100,
                    }
                }
            )
            
            if created:
                added_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'  [OK] {package.name} paketine eklendi')
                )
            else:
                # Mevcut ise güncelle
                package_module.is_enabled = is_enabled
                package_module.is_required = is_required
                package_module.save()
                skipped_count += 1
                self.stdout.write(
                    self.style.WARNING(f'  [SKIP] {package.name} paketinde zaten mevcut (güncellendi)')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n[OK] {added_count} pakete eklendi, {skipped_count} paket güncellendi')
        )





