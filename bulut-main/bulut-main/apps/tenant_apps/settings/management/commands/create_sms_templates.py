"""
SMS Şablonları Oluşturma Komutu
Varsayılan SMS şablonlarını oluşturur
Tenant schema'da çalışmalı
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import schema_context, get_public_schema_name
from apps.tenant_apps.settings.models import SMSTemplate


class Command(BaseCommand):
    help = 'Varsayılan SMS şablonlarını oluşturur'

    def handle(self, *args, **options):
        # Schema kontrolü - schema_context içinde çalışıyorsa kontrol etme
        # Çünkü schema_context zaten doğru schema'ya geçiş yapıyor
        templates_data = [
            {
                'name': 'Rezervasyon Onayı',
                'code': 'reservation_confirmation',
                'category': 'reservation',
                'template_text': 'Sayın {{guest_name}}, rezervasyonunuz {{check_in_date}} tarihinde onaylanmıştır. Rezervasyon No: {{reservation_number}}. Teşekkürler.',
                'available_variables': {
                    'guest_name': 'Misafir Adı',
                    'check_in_date': 'Check-in Tarihi',
                    'reservation_number': 'Rezervasyon Numarası'
                },
                'module_usage': 'reception',
                'description': 'Rezervasyon onaylandığında gönderilir',
                'is_active': True,
                'is_system_template': True,
            },
            {
                'name': 'Check-in Hatırlatma',
                'code': 'checkin_reminder',
                'category': 'checkin',
                'template_text': 'Sayın {{guest_name}}, {{check_in_date}} tarihinde check-in yapacağınızı hatırlatırız. Rezervasyon No: {{reservation_number}}. Görüşmek üzere!',
                'available_variables': {
                    'guest_name': 'Misafir Adı',
                    'check_in_date': 'Check-in Tarihi',
                    'reservation_number': 'Rezervasyon Numarası'
                },
                'module_usage': 'reception',
                'description': 'Check-in tarihinden 1 gün önce gönderilir',
                'is_active': True,
                'is_system_template': True,
            },
            {
                'name': 'Check-out Hatırlatma',
                'code': 'checkout_reminder',
                'category': 'checkout',
                'template_text': 'Sayın {{guest_name}}, {{check_out_date}} tarihinde check-out yapacağınızı hatırlatırız. Bizi tercih ettiğiniz için teşekkürler!',
                'available_variables': {
                    'guest_name': 'Misafir Adı',
                    'check_out_date': 'Check-out Tarihi'
                },
                'module_usage': 'reception',
                'description': 'Check-out tarihinden 1 gün önce gönderilir',
                'is_active': True,
                'is_system_template': True,
            },
            {
                'name': 'Ödeme Onayı',
                'code': 'payment_confirmation',
                'category': 'payment',
                'template_text': 'Sayın {{guest_name}}, {{amount}} {{currency}} tutarındaki ödemeniz alınmıştır. İşlem No: {{payment_number}}. Teşekkürler.',
                'available_variables': {
                    'guest_name': 'Misafir Adı',
                    'amount': 'Ödeme Tutarı',
                    'currency': 'Para Birimi',
                    'payment_number': 'İşlem Numarası'
                },
                'module_usage': 'payment_management',
                'description': 'Ödeme alındığında gönderilir',
                'is_active': True,
                'is_system_template': True,
            },
            {
                'name': 'Feribot Bileti Onayı',
                'code': 'ferry_ticket_confirmation',
                'category': 'reservation',
                'template_text': 'Sayın {{passenger_name}}, feribot biletiniz onaylanmıştır. Sefer: {{route_name}}, Tarih: {{departure_date}}, Saat: {{departure_time}}. Bilet No: {{ticket_number}}',
                'available_variables': {
                    'passenger_name': 'Yolcu Adı',
                    'route_name': 'Güzergah',
                    'departure_date': 'Kalkış Tarihi',
                    'departure_time': 'Kalkış Saati',
                    'ticket_number': 'Bilet Numarası'
                },
                'module_usage': 'ferry_tickets',
                'description': 'Feribot bileti satın alındığında gönderilir',
                'is_active': True,
                'is_system_template': True,
            },
        ]
        
        created_count = 0
        updated_count = 0
        
        for template_data in templates_data:
            code = template_data.pop('code')
            template, created = SMSTemplate.objects.update_or_create(
                code=code,
                defaults=template_data
            )
            
            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] Sablon olusturuldu: {template.name}')
                )
            else:
                updated_count += 1
                self.stdout.write(
                    self.style.WARNING(f'[GUNCELLENDI] Sablon guncellendi: {template.name}')
                )
        
        self.stdout.write(
            self.style.SUCCESS(
                f'\n[OK] Islem tamamlandi! {created_count} yeni sablon olusturuldu, {updated_count} sablon guncellendi.'
            )
        )

