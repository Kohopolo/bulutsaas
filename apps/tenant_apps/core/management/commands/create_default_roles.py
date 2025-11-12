"""
Varsayılan rollerini oluştur
Tenant schema'da çalışır
Admin rolüne tüm yetkileri otomatik atar
"""
from django.core.management.base import BaseCommand
from apps.tenant_apps.core.models import Role, RolePermission, Permission


class Command(BaseCommand):
    help = 'Varsayılan rollerini oluştur'

    def handle(self, *args, **options):
        # NOT: super_admin rolü sadece sistem tarafından kullanılır, tenant panelinde görünmez
        roles = [
            {
                'name': 'Admin',
                'code': 'admin',
                'description': 'Yönetici rolü',
                'icon': '🛡️',
                'is_active': True,
                'is_system': True,
                'sort_order': 1,
            },
            {
                'name': 'Manager',
                'code': 'manager',
                'description': 'Yönetici rolü',
                'icon': '👔',
                'is_active': True,
                'is_system': False,
                'sort_order': 2,
            },
            {
                'name': 'Staff',
                'code': 'staff',
                'description': 'Personel rolü',
                'icon': '👤',
                'is_active': True,
                'is_system': False,
                'sort_order': 3,
            },
            {
                'name': 'Resepsiyonist',
                'code': 'receptionist',
                'description': 'Resepsiyon personeli',
                'icon': '🏨',
                'is_active': True,
                'is_system': False,
                'sort_order': 4,
            },
            {
                'name': 'Satış Temsilcisi',
                'code': 'sales_rep',
                'description': 'Satış personeli',
                'icon': '💼',
                'is_active': True,
                'is_system': False,
                'sort_order': 5,
            },
        ]
        
        created_count = 0
        for role_data in roles:
            role, created = Role.objects.get_or_create(
                code=role_data['code'],
                defaults=role_data
            )
            if created:
                created_count += 1
                self.stdout.write(
                    self.style.SUCCESS(f'[OK] {role.name} olusturuldu')
                )
            else:
                self.stdout.write(
                    self.style.WARNING(f'[SKIP] {role.name} zaten mevcut')
                )
        
        self.stdout.write(
            self.style.SUCCESS(f'\n{created_count} rol olusturuldu')
        )
        
        # Admin rolüne tüm yetkileri ata
        admin_role = Role.objects.filter(code='admin').first()
        if admin_role:
            permissions = Permission.objects.filter(is_active=True)
            assigned_count = 0
            for permission in permissions:
                role_permission, created = RolePermission.objects.get_or_create(
                    role=admin_role,
                    permission=permission,
                    defaults={'is_active': True}
                )
                if created:
                    assigned_count += 1
            self.stdout.write(
                self.style.SUCCESS(f'[OK] Admin rolune {assigned_count} yetki atandi')
            )

