"""
Yedekleme Modülünü Tüm Paketlere Ekleme Command
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module
from apps.packages.models import Package, PackageModule


class Command(BaseCommand):
    help = 'Yedekleme modülünü tüm aktif paketlere ekler'

    def handle(self, *args, **options):
        try:
            module = Module.objects.get(code='backup')
        except Module.DoesNotExist:
            self.stdout.write(
                self.style.ERROR('[HATA] Yedekleme modülü bulunamadı. Önce create_backup_module komutunu çalıştırın.')
            )
            return
        
        packages = Package.objects.filter(is_active=True)
        added_count = 0
        skipped_count = 0
        
        for package in packages:
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
                        'delete': True,
                        'download': True,
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
                # Mevcut modülü aktifleştir
                if not package_module.is_enabled:
                    package_module.is_enabled = True
                    package_module.save()
                    added_count += 1
                    self.stdout.write(
                        self.style.SUCCESS(f'[OK] {package.name} paketinde aktifleştirildi')
                    )
                else:
                    skipped_count += 1
        
        self.stdout.write(
            self.style.SUCCESS(f'\n[ÖZET] {added_count} pakete eklendi, {skipped_count} pakette zaten mevcuttu.')
        )





