"""
Varsayılan kullanıcı tiplerini oluştur
Tenant schema'da çalışır
"""
from django.core.management.base import BaseCommand
from apps.tenant_apps.core.models import UserType


class Command(BaseCommand):
    help = 'Varsayılan kullanıcı tiplerini oluştur'

    def handle(self, *args, **options):
        user_types = [
            {
                'name': 'Yönetici',
                'code': 'admin',
                'description': 'Tam yetkili yönetici',
                'icon': '👔',
                'dashboard_url': '/',
                'is_active': True,
                'sort_order': 1,
            },
            {
                'name': 'Resepsiyon',
                'code': 'reception',
                'description': 'Resepsiyon personeli',
                'icon': '🏨',
                'dashboard_url': '/',
                'is_active': True,
                'sort_order': 2,
            },
            {
                'name': 'Satış',
                'code': 'sales',
                'description': 'Satış personeli',
                'icon': '💼',
                'dashboard_url': '/',
                'is_active': True,
                'sort_order': 3,
            },
            {
                'name': 'Housekeeping',
                'code': 'housekeeping',
                'description': 'Temizlik ve bakım personeli',
                'icon': '🧹',
                'dashboard_url': '/',
                'is_active': True,
                'sort_order': 4,
            },
            {
                'name': 'Muhasebe',
                'code': 'accounting',
                'description': 'Muhasebe personeli',
                'icon': '💰',
                'dashboard_url': '/',
                'is_active': True,
                'sort_order': 5,
            },
        ]
        
        created_count = 0
        for user_type_data in user_types:
            user_type, created = UserType.objects.get_or_create(
                code=user_type_data['code'],
                defaults=user_type_data
            )
            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] {user_type.name} olusturuldu')
                )
            else:
                self.stdout.write(
                    self.style.WARNING(f'[SKIP] {user_type.name} zaten mevcut')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n{created_count} kullanici tipi olusturuldu')
        )

