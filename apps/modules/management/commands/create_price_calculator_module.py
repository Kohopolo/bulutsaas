"""
Fiyat Hesaplama modülünü oluşturur
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Fiyat Hesaplama modülünü oluşturur'
    
    def handle(self, *args, **kwargs):
        self.stdout.write('Fiyat Hesaplama modülü oluşturuluyor...')
        
        # Price Calculator (Fiyat Hesaplama) Modülü
        price_calculator_module, created = Module.objects.get_or_create(
            code='price_calculator',
            defaults={
                'name': 'Fiyat Hesaplama',
                'description': 'Rezervasyon öncesi fiyat hesaplama ve günlük fiyat görünümü modülü',
                'icon': 'fas fa-calculator',
                'category': 'reservation',
                'app_name': 'apps.tenant_apps.reception',
                'url_prefix': 'reception/price-calculator',
                'available_permissions': {
                    'view': 'Fiyat hesaplama görüntüleme',
                    'use': 'Fiyat hesaplama kullanma',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 15,
            }
        )
        if created:
            self.stdout.write(self.style.SUCCESS(f'[OK] {price_calculator_module.name} modülü oluşturuldu'))
        else:
            # Mevcut modülü güncelle
            price_calculator_module.name = 'Fiyat Hesaplama'
            price_calculator_module.description = 'Rezervasyon öncesi fiyat hesaplama ve günlük fiyat görünümü modülü'
            price_calculator_module.icon = 'fas fa-calculator'
            price_calculator_module.category = 'reservation'
            price_calculator_module.app_name = 'apps.tenant_apps.reception'
            price_calculator_module.url_prefix = 'reception/price-calculator'
            price_calculator_module.available_permissions = {
                'view': 'Fiyat hesaplama görüntüleme',
                'use': 'Fiyat hesaplama kullanma',
            }
            price_calculator_module.is_active = True
            price_calculator_module.save()
            self.stdout.write(self.style.SUCCESS(f'[OK] {price_calculator_module.name} modülü güncellendi'))
        
        self.stdout.write(self.style.SUCCESS('\n[OK] Fiyat Hesaplama modülü hazır!'))

