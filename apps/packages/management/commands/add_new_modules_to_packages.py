"""
Tüm paketlere yeni modülleri ekle (Housekeeping, Technical Service, Quality Control, Sales, Staff)
"""
from django.core.management.base import BaseCommand
from apps.packages.models import Package, PackageModule
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Tüm paketlere yeni modülleri ekler (housekeeping, technical_service, quality_control, sales, staff)'

    def handle(self, *args, **options):
        modules_to_add = [
            'housekeeping',
            'technical_service',
            'quality_control',
            'sales',
            'staff',
            'payment_management',
            'channel_management',
        ]
        
        packages = Package.objects.filter(is_active=True)
        total_added = 0
        
        for module_code in modules_to_add:
            try:
                module = Module.objects.get(code=module_code)
            except Module.DoesNotExist:
                self.stdout.write(
                    self.style.WARNING(f'[SKIP] {module_code} modülü bulunamadı. Önce create_{module_code}_module komutunu çalıştırın.')
                )
                continue
            
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
                            'manage': True,
                            'admin': True,
                        },
                        'limits': {},
                    }
                )
                if created:
                    added_count += 1
                elif not package_module.is_enabled:
                    # Eğer modül pasifse aktif yap
                    package_module.is_enabled = True
                    package_module.save()
                    added_count += 1
            
            if added_count > 0:
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] {module.name} modülü {added_count} pakete eklendi/aktifleştirildi.')
                )
                total_added += added_count
        
        self.stdout.write(
            self.style.SUCCESS(f'\n[OK] Toplam: {total_added} paket-modül ilişkisi oluşturuldu/güncellendi.')
        )

