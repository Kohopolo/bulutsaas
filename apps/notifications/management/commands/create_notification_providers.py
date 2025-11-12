"""
Bildirim Sağlayıcılarını Oluşturma Komutu
"""
from django.core.management.base import BaseCommand
from apps.notifications.models import NotificationProvider


class Command(BaseCommand):
    help = 'Bildirim sağlayıcılarını oluşturur'

    def handle(self, *args, **options):
        providers_data = [
            {
                'name': 'E-posta (SMTP)',
                'code': 'email',
                'provider_type': 'email',
                'description': 'SMTP üzerinden e-posta gönderimi',
                'supports_bulk': True,
                'supports_template': True,
                'supports_unicode': True,
                'is_active': True,
                'sort_order': 1,
            },
            {
                'name': 'NetGSM SMS',
                'code': 'sms_netgsm',
                'provider_type': 'sms',
                'description': 'NetGSM SMS servisi entegrasyonu',
                'api_url': 'https://api.netgsm.com.tr/sms/send/get',
                'test_api_url': 'https://api.netgsm.com.tr/test/sms/send/get',
                'supports_bulk': True,
                'supports_template': True,
                'supports_unicode': True,
                'is_active': True,
                'sort_order': 2,
            },
            {
                'name': 'Verimor SMS',
                'code': 'sms_verimor',
                'provider_type': 'sms',
                'description': 'Verimor SMS servisi entegrasyonu',
                'api_url': 'https://api.verimor.com.tr/v2/send.json',
                'supports_bulk': True,
                'supports_template': True,
                'supports_unicode': True,
                'is_active': True,
                'sort_order': 3,
            },
            {
                'name': 'WhatsApp Business API',
                'code': 'whatsapp',
                'provider_type': 'whatsapp',
                'description': 'Meta WhatsApp Business API entegrasyonu',
                'api_url': 'https://graph.facebook.com/v18.0',
                'supports_bulk': False,
                'supports_template': True,
                'supports_unicode': True,
                'is_active': True,
                'sort_order': 4,
            },
        ]
        
        created_count = 0
        updated_count = 0
        
        for provider_data in providers_data:
            provider, created = NotificationProvider.objects.get_or_create(
                code=provider_data['code'],
                defaults=provider_data
            )
            
            if created:
                created_count += 1
                self.stdout.write(self.style.SUCCESS(f'[OK] {provider.name} olusturuldu'))
            else:
                # Mevcut kaydı güncelle
                for key, value in provider_data.items():
                    setattr(provider, key, value)
                provider.save()
                updated_count += 1
                self.stdout.write(self.style.SUCCESS(f'[OK] {provider.name} guncellendi'))
        
        self.stdout.write(self.style.SUCCESS(
            f'\n[OK] Toplam: {created_count} yeni, {updated_count} guncellendi'
        ))

