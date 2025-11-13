"""
Kanal Şablonları Oluşturma Komutu
Türk ve global kanal şablonlarını oluşturur
Public schema'da çalışmalı
"""
from django.core.management.base import BaseCommand
from django.db import connection
from django_tenants.utils import schema_context, get_public_schema_name
from apps.modules.models import ChannelTemplate  # Public schema'dan import
from decimal import Decimal


class Command(BaseCommand):
    help = 'Kanal şablonlarını oluşturur (Booking.com, ETS, Tatilbudur vb.) - Public schema\'da çalışmalı'

    def handle(self, *args, **options):
        # Public schema'da çalıştırılmalı
        if connection.schema_name != get_public_schema_name():
            self.stdout.write(
                self.style.ERROR('Bu komut public schema\'da çalıştırılmalıdır. Komutu public schema\'da çalıştırın.')
            )
            return
        
        # Public schema context'inde çalış
        with schema_context(get_public_schema_name()):
            templates = [
                {
                'name': 'Booking.com',
                'code': 'booking',
                'channel_type': 'ota',
                'description': 'Dünyanın en büyük OTA platformu. İki yönlü senkronizasyon, fiyat ve müsaitlik yönetimi.',
                'api_type': 'xml',
                'api_documentation_url': 'https://distribution-xml.booking.com/',
                'api_endpoint_template': 'https://distribution-xml.booking.com/{endpoint}',
                'required_fields': {
                    'username': 'Kullanıcı Adı',
                    'password': 'Şifre',
                    'hotel_id': 'Otel ID',
                },
                'optional_fields': {
                    'test_mode': 'Test Modu',
                },
                'supports_pricing': True,
                'supports_availability': True,
                'supports_reservations': True,
                'supports_two_way': True,
                'supports_commission': True,
                'default_commission_rate': Decimal('15.00'),
                'is_active': True,
                'is_popular': True,
                'sort_order': 1,
                'icon': 'fas fa-globe',
            },
            {
                'name': 'ETS (Electronic Travel Services)',
                'code': 'ets',
                'channel_type': 'ota',
                'description': 'Türkiye\'nin önde gelen OTA platformu. Türk otelleri için özel entegrasyon.',
                'api_type': 'json',
                'api_documentation_url': 'https://api.ets.com.tr/',
                'api_endpoint_template': 'https://api.ets.com.tr/v1/{endpoint}',
                'required_fields': {
                    'api_key': 'API Key',
                    'api_secret': 'API Secret',
                    'hotel_code': 'Otel Kodu',
                },
                'optional_fields': {
                    'username': 'Kullanıcı Adı',
                },
                'supports_pricing': True,
                'supports_availability': True,
                'supports_reservations': True,
                'supports_two_way': True,
                'supports_commission': True,
                'default_commission_rate': Decimal('12.00'),
                'is_active': True,
                'is_popular': True,
                'sort_order': 2,
                'icon': 'fas fa-plane',
            },
            {
                'name': 'Tatilbudur',
                'code': 'tatilbudur',
                'channel_type': 'ota',
                'description': 'Türkiye\'nin popüler tatil platformu. Otel rezervasyonları için entegrasyon.',
                'api_type': 'json',
                'api_documentation_url': 'https://api.tatilbudur.com/',
                'api_endpoint_template': 'https://api.tatilbudur.com/v2/{endpoint}',
                'required_fields': {
                    'api_key': 'API Key',
                    'partner_id': 'Partner ID',
                },
                'optional_fields': {
                    'test_mode': 'Test Modu',
                },
                'supports_pricing': True,
                'supports_availability': True,
                'supports_reservations': True,
                'supports_two_way': True,
                'supports_commission': True,
                'default_commission_rate': Decimal('10.00'),
                'is_active': True,
                'is_popular': True,
                'sort_order': 3,
                'icon': 'fas fa-umbrella-beach',
            },
            {
                'name': 'Tatilsepeti',
                'code': 'tatilsepeti',
                'channel_type': 'ota',
                'description': 'Türkiye\'nin önde gelen tatil ve otel rezervasyon platformu.',
                'api_type': 'json',
                'api_endpoint_template': 'https://api.tatilsepeti.com/{endpoint}',
                'required_fields': {
                    'api_key': 'API Key',
                    'api_secret': 'API Secret',
                },
                'optional_fields': {
                    'hotel_id': 'Otel ID',
                },
                'supports_pricing': True,
                'supports_availability': True,
                'supports_reservations': True,
                'supports_two_way': True,
                'supports_commission': True,
                'default_commission_rate': Decimal('11.00'),
                'is_active': True,
                'is_popular': True,
                'sort_order': 4,
                'icon': 'fas fa-shopping-bag',
            },
            {
                'name': 'Hotels.com',
                'code': 'hotels',
                'channel_type': 'ota',
                'description': 'Dünya çapında popüler otel rezervasyon platformu.',
                'api_type': 'xml',
                'api_endpoint_template': 'https://api.hotels.com/{endpoint}',
                'required_fields': {
                    'api_key': 'API Key',
                    'hotel_id': 'Otel ID',
                },
                'optional_fields': {
                    'test_mode': 'Test Modu',
                },
                'supports_pricing': True,
                'supports_availability': True,
                'supports_reservations': True,
                'supports_two_way': True,
                'supports_commission': True,
                'default_commission_rate': Decimal('15.00'),
                'is_active': True,
                'is_popular': True,
                'sort_order': 5,
                'icon': 'fas fa-hotel',
            },
            {
                'name': 'Trivago',
                'code': 'trivago',
                'channel_type': 'metasearch',
                'description': 'Meta arama motoru. Fiyat karşılaştırma ve yönlendirme.',
                'api_type': 'json',
                'api_endpoint_template': 'https://api.trivago.com/{endpoint}',
                'required_fields': {
                    'api_key': 'API Key',
                    'partner_id': 'Partner ID',
                },
                'optional_fields': {},
                'supports_pricing': True,
                'supports_availability': True,
                'supports_reservations': False,
                'supports_two_way': False,
                'supports_commission': True,
                'default_commission_rate': Decimal('8.00'),
                'is_active': True,
                'is_popular': True,
                'sort_order': 6,
                'icon': 'fas fa-search',
            },
            {
                'name': 'Expedia',
                'code': 'expedia',
                'channel_type': 'ota',
                'description': 'Dünya çapında büyük OTA platformu.',
                'api_type': 'xml',
                'api_endpoint_template': 'https://api.expedia.com/{endpoint}',
                'required_fields': {
                    'username': 'Kullanıcı Adı',
                    'password': 'Şifre',
                    'hotel_id': 'Otel ID',
                },
                'optional_fields': {
                    'test_mode': 'Test Modu',
                },
                'supports_pricing': True,
                'supports_availability': True,
                'supports_reservations': True,
                'supports_two_way': True,
                'supports_commission': True,
                'default_commission_rate': Decimal('18.00'),
                'is_active': True,
                'is_popular': True,
                'sort_order': 7,
                'icon': 'fas fa-plane-departure',
            },
            {
                'name': 'Agoda',
                'code': 'agoda',
                'channel_type': 'ota',
                'description': 'Asya merkezli popüler OTA platformu.',
                'api_type': 'json',
                'api_endpoint_template': 'https://api.agoda.com/{endpoint}',
                'required_fields': {
                    'api_key': 'API Key',
                    'hotel_id': 'Otel ID',
                },
                'optional_fields': {},
                'supports_pricing': True,
                'supports_availability': True,
                'supports_reservations': True,
                'supports_two_way': True,
                'supports_commission': True,
                'default_commission_rate': Decimal('14.00'),
                'is_active': True,
                'is_popular': False,
                'sort_order': 8,
                'icon': 'fas fa-globe-asia',
            },
        ]
        
            created_count = 0
            updated_count = 0
            
            for template_data in templates:
                template, created = ChannelTemplate.objects.get_or_create(
                    code=template_data['code'],
                    defaults=template_data
                )
                
                if created:
                    created_count += 1
                    self.stdout.write(
                        self.style.SUCCESS(f'[OK] Kanal şablonu oluşturuldu: {template.name}')
                    )
                else:
                    # Mevcut şablonu güncelle
                    for key, value in template_data.items():
                        setattr(template, key, value)
                    template.save()
                    updated_count += 1
                    self.stdout.write(
                        self.style.WARNING(f'[UPDATE] Kanal şablonu güncellendi: {template.name}')
                    )
            
            self.stdout.write(
                self.style.SUCCESS(
                    f'\nToplam: {created_count} yeni şablon oluşturuldu, {updated_count} şablon güncellendi.'
                )
            )

