"""
Raporlar modülünü oluşturur
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Raporlar modülünü oluşturur'
    
    def handle(self, *args, **kwargs):
        self.stdout.write('Raporlar modülü oluşturuluyor...')
        
        # Reports (Raporlar) Modülü
        reports_module, created = Module.objects.get_or_create(
            code='reports',
            defaults={
                'name': 'Raporlar',
                'description': 'Tüm modüllerin raporlarına merkezi erişim sağlayan dashboard',
                'icon': 'fas fa-chart-bar',
                'category': 'reporting',
                'app_name': 'apps.tenant_apps.reports',
                'url_prefix': 'reports',
                'available_permissions': {
                    'view': 'Rapor görüntüleme',
                    'export': 'Rapor export etme',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 50,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {reports_module.name} modülü oluşturuldu'))
        else:
            # Mevcut modülü güncelle
            reports_module.name = 'Raporlar'
            reports_module.description = 'Tüm modüllerin raporlarına merkezi erişim sağlayan dashboard'
            reports_module.icon = 'fas fa-chart-bar'
            reports_module.category = 'reporting'
            reports_module.app_name = 'apps.tenant_apps.reports'
            reports_module.url_prefix = 'reports'
            reports_module.available_permissions = {
                'view': 'Rapor görüntüleme',
                'export': 'Rapor export etme',
            }
            reports_module.is_active = True
            reports_module.save()
            self.stdout.write(self.style.SUCCESS(f'[OK] {reports_module.name} modülü güncellendi'))
        
        self.stdout.write(self.style.SUCCESS('\n[OK] Raporlar modülü hazır!'))

