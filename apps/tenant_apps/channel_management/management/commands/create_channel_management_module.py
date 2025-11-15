"""
Kanal Yönetimi Modülü Oluşturma Komutu
SaaS superadmin panelinde modülü tanımlar
"""
from django.core.management.base import BaseCommand
from apps.modules.models import Module


class Command(BaseCommand):
    help = 'Kanal Yönetimi modülünü oluşturur'

    def handle(self, *args, **options):
        module, created = Module.objects.get_or_create(
            code='channel_management',
            defaults={
                'name': 'Kanal Yönetimi',
                'description': 'OTA (Online Travel Agency) entegrasyonları için kapsamlı kanal yönetim sistemi. Booking.com, ETS, Tatilbudur, Tatilsepeti, Hotels.com, Trivago ve diğer kanallar için iki yönlü veri senkronizasyonu, fiyat yönetimi, rezervasyon yönetimi ve komisyon takibi.',
                'icon': 'fas fa-network-wired',
                'category': 'channel',
                'app_name': 'apps.tenant_apps.channel_management',
                'url_prefix': '/channel-management/',
                'available_permissions': {
                    'view': 'Kanal Yönetimi Görüntüleme',
                    'add': 'Kanal Konfigürasyonu Ekleme',
                    'edit': 'Kanal Konfigürasyonu Düzenleme',
                    'delete': 'Kanal Konfigürasyonu Silme',
                    'sync': 'Senkronizasyon Başlatma',
                    'pricing': 'Fiyat Yönetimi',
                    'reservation': 'Rezervasyon Yönetimi',
                    'commission': 'Komisyon Yönetimi',
                },
                'is_active': True,
                'is_core': False,
                'sort_order': 20,
            }
        )

        if created:
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Kanal Yönetimi modülü oluşturuldu: {module.name}')
            )
        else:
            # Mevcut modülü güncelle
            module.name = 'Kanal Yönetimi'
            module.description = 'OTA (Online Travel Agency) entegrasyonları için kapsamlı kanal yönetim sistemi. Booking.com, ETS, Tatilbudur, Tatilsepeti, Hotels.com, Trivago ve diğer kanallar için iki yönlü veri senkronizasyonu, fiyat yönetimi, rezervasyon yönetimi ve komisyon takibi.'
            module.icon = 'fas fa-network-wired'
            module.category = 'channel'
            module.app_name = 'apps.tenant_apps.channel_management'
            module.url_prefix = '/channel-management/'
            module.available_permissions = {
                'view': 'Kanal Yönetimi Görüntüleme',
                'add': 'Kanal Konfigürasyonu Ekleme',
                'edit': 'Kanal Konfigürasyonu Düzenleme',
                'delete': 'Kanal Konfigürasyonu Silme',
                'sync': 'Senkronizasyon Başlatma',
                'pricing': 'Fiyat Yönetimi',
                'reservation': 'Rezervasyon Yönetimi',
                'commission': 'Komisyon Yönetimi',
            }
            module.is_active = True
            module.save()
            self.stdout.write(
                self.style.WARNING(f'[UPDATE] Kanal Yönetimi modülü güncellendi: {module.name}')
            )





