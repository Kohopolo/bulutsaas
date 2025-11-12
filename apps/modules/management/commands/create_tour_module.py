"""
Tur Modülünü oluştur
Module tablosuna Tur modülünü ekler
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Tur modülünü oluştur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='tours',
            defaults={
                'name': 'Tur Modülü',
                'description': 'Tur yönetimi, rezervasyon ve raporlama modülü',
                'icon': 'fa-route',
                'category': 'other',
                'app_name': 'apps.tenant_apps.tours',
                'url_prefix': '/tours/',
                'available_permissions': {
                    'view': 'Tur Görüntüleme',
                    'add': 'Tur Ekleme',
                    'edit': 'Tur Düzenleme',
                    'delete': 'Tur Silme',
                    'report': 'Tur Raporlama',
                    'export': 'Tur Dışa Aktarma',
                    'reservation_view': 'Rezervasyon Görüntüleme',
                    'reservation_add': 'Rezervasyon Ekleme',
                    'reservation_edit': 'Rezervasyon Düzenleme',
                    'reservation_delete': 'Rezervasyon Silme',
                    'reservation_cancel': 'Rezervasyon İptal',
                    'reservation_refund': 'Rezervasyon İade',
                    'reservation_payment': 'Rezervasyon Ödeme',
                    'reservation_voucher': 'Voucher Oluşturma',
                    'dynamic_manage': 'Dinamik Yönetim (Bölge, Şehir, Tür vb.)',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 10,
            }
        )
        
        if created:
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Tur modülü oluşturuldu: {module.name}')
            )
        else:
            # Mevcut modülü güncelle
            module.name = 'Tur Modülü'
            module.description = 'Tur yönetimi, rezervasyon ve raporlama modülü'
            module.icon = 'fa-route'
            module.category = 'other'
            module.app_name = 'apps.tenant_apps.tours'
            module.url_prefix = '/tours/'
            module.available_permissions = {
                'view': 'Tur Görüntüleme',
                'add': 'Tur Ekleme',
                'edit': 'Tur Düzenleme',
                'delete': 'Tur Silme',
                'report': 'Tur Raporlama',
                'export': 'Tur Dışa Aktarma',
                'reservation_view': 'Rezervasyon Görüntüleme',
                'reservation_add': 'Rezervasyon Ekleme',
                'reservation_edit': 'Rezervasyon Düzenleme',
                'reservation_delete': 'Rezervasyon Silme',
                'reservation_cancel': 'Rezervasyon İptal',
                'reservation_refund': 'Rezervasyon İade',
                'reservation_payment': 'Rezervasyon Ödeme',
                'reservation_voucher': 'Voucher Oluşturma',
                'dynamic_manage': 'Dinamik Yönetim (Bölge, Şehir, Tür vb.)',
            }
            module.is_active = True
            module.is_core = False
            module.sort_order = 10
            module.save()
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Tur modülü güncellendi: {module.name}')
            )

