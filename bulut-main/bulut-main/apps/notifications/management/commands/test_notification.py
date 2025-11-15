"""
Bildirim Sistemi Test Komutu
"""
from django.core.management.base import BaseCommand
from apps.notifications.models import NotificationProvider, NotificationProviderConfig
from apps.notifications.services import send_notification


class Command(BaseCommand):
    help = 'Bildirim sistemini test eder'

    def add_arguments(self, parser):
        parser.add_argument('--provider', type=str, help='Test edilecek sağlayıcı kodu (email, sms_netgsm, sms_verimor, whatsapp)')
        parser.add_argument('--recipient', type=str, help='Alıcı (email veya telefon)')

    def handle(self, *args, **options):
        provider_code = options.get('provider', 'email')
        recipient = options.get('recipient', 'test@example.com')
        
        self.stdout.write(self.style.SUCCESS(f'\n[Bildirim Sistemi Test]'))
        self.stdout.write(f'Sağlayıcı: {provider_code}')
        self.stdout.write(f'Alıcı: {recipient}\n')
        
        # Sağlayıcı kontrolü
        try:
            provider = NotificationProvider.objects.get(code=provider_code, is_active=True)
            self.stdout.write(self.style.SUCCESS(f'[OK] Sağlayıcı bulundu: {provider.name}'))
        except NotificationProvider.DoesNotExist:
            self.stdout.write(self.style.ERROR(f'[HATA] Sağlayıcı bulunamadı: {provider_code}'))
            return
        
        # Yapılandırma kontrolü
        config = NotificationProviderConfig.objects.filter(provider=provider, is_active=True).first()
        if not config:
            self.stdout.write(self.style.WARNING(f'[UYARI] {provider.name} için aktif yapılandırma bulunamadı.'))
            self.stdout.write(self.style.WARNING(f'Lütfen admin panelinden yapılandırma ekleyin: /admin/notifications/notificationproviderconfig/'))
            return
        
        self.stdout.write(self.style.SUCCESS(f'[OK] Yapılandırma bulundu'))
        
        # Test bildirimi gönder
        self.stdout.write(f'\n[TEST] Bildirim gönderiliyor...')
        
        try:
            result = send_notification(
                provider_code=provider_code,
                recipient=recipient,
                subject='Test Bildirimi - Bulut Acente',
                content='Bu bir test bildirimidir. Bildirim sistemi çalışıyor!',
                variables={
                    'test': 'Başarılı',
                    'date': '2025-01-XX',
                }
            )
            
            if result.get('success'):
                self.stdout.write(self.style.SUCCESS(f'[OK] Bildirim başarıyla gönderildi!'))
                self.stdout.write(f'Log ID: {result.get("log_id")}')
                self.stdout.write(f'Message ID: {result.get("message_id")}')
            else:
                self.stdout.write(self.style.ERROR(f'[HATA] Bildirim gönderilemedi: {result.get("error")}'))
                
        except Exception as e:
            self.stdout.write(self.style.ERROR(f'[HATA] Test sırasında hata: {str(e)}'))
            import traceback
            self.stdout.write(traceback.format_exc())

