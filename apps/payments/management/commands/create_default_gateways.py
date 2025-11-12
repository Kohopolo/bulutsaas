"""
Varsayılan ödeme gateway'lerini oluştur
"""
from django.core.management.base import BaseCommand
from apps.payments.models import PaymentGateway


class Command(BaseCommand):
    help = 'Varsayılan ödeme gateway\'lerini oluştur'

    def handle(self, *args, **options):
        gateways = [
            {
                'name': 'İyzico',
                'code': 'iyzico',
                'gateway_type': 'iyzico',
                'description': 'İyzico ödeme gateway entegrasyonu',
                'supports_3d_secure': True,
                'supports_installment': True,
                'supports_refund': True,
                'supports_recurring': False,
                'is_active': True,
                'sort_order': 1,
            },
            {
                'name': 'PayTR',
                'code': 'paytr',
                'gateway_type': 'paytr',
                'description': 'PayTR ödeme gateway entegrasyonu',
                'supports_3d_secure': True,
                'supports_installment': True,
                'supports_refund': True,
                'supports_recurring': False,
                'is_active': True,
                'sort_order': 2,
            },
            {
                'name': 'NestPay (İş Bankası)',
                'code': 'nestpay-isbank',
                'gateway_type': 'nestpay',
                'description': 'İş Bankası NestPay entegrasyonu',
                'supports_3d_secure': True,
                'supports_installment': True,
                'supports_refund': True,
                'supports_recurring': False,
                'is_active': True,
                'sort_order': 3,
                'settings': {'bank_code': 'isbank'},
            },
            {
                'name': 'Garanti Sanal Pos',
                'code': 'garanti',
                'gateway_type': 'garanti',
                'description': 'Garanti Bankası sanal pos entegrasyonu',
                'supports_3d_secure': True,
                'supports_installment': True,
                'supports_refund': True,
                'supports_recurring': False,
                'is_active': True,
                'sort_order': 4,
            },
            {
                'name': 'Akbank Sanal Pos',
                'code': 'akbank',
                'gateway_type': 'akbank',
                'description': 'Akbank sanal pos entegrasyonu',
                'supports_3d_secure': True,
                'supports_installment': True,
                'supports_refund': True,
                'supports_recurring': False,
                'is_active': True,
                'sort_order': 5,
            },
        ]
        
        created_count = 0
        for gateway_data in gateways:
            gateway, created = PaymentGateway.objects.get_or_create(
                code=gateway_data['code'],
                defaults=gateway_data
            )
            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] {gateway.name} olusturuldu')
                )
            else:
                self.stdout.write(
                    self.style.WARNING(f'[SKIP] {gateway.name} zaten mevcut')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n{created_count} gateway oluşturuldu')
        )

