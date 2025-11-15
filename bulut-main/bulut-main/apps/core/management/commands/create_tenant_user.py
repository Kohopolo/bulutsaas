"""
Tenant için kullanıcı oluştur
"""
from django.core.management.base import BaseCommand
from django.contrib.auth.models import User
from django_tenants.utils import schema_context
from apps.tenant_apps.core.models import TenantUser, UserType, Role, UserRole


class Command(BaseCommand):
    help = 'Tenant için kullanıcı oluştur'

    def add_arguments(self, parser):
        parser.add_argument('--tenant-slug', type=str, required=True, help='Tenant slug')
        parser.add_argument('--username', type=str, required=True, help='Kullanıcı adı')
        parser.add_argument('--email', type=str, required=True, help='E-posta')
        parser.add_argument('--password', type=str, required=True, help='Şifre')
        parser.add_argument('--first-name', type=str, default='', help='Ad')
        parser.add_argument('--last-name', type=str, default='', help='Soyad')
        parser.add_argument('--user-type', type=str, default='admin', help='Kullanıcı tipi kodu')
        parser.add_argument('--role', type=str, default='admin', help='Rol kodu')

    def handle(self, *args, **options):
        tenant_slug = options['tenant_slug']
        username = options['username']
        email = options['email']
        password = options['password']
        first_name = options['first_name']
        last_name = options['last_name']
        user_type_code = options['user_type']
        role_code = options['role']
        
        # Tenant'ı bul
        from apps.tenants.models import Tenant
        try:
            tenant = Tenant.objects.get(slug=tenant_slug)
        except Tenant.DoesNotExist:
            self.stdout.write(self.style.ERROR(f'Tenant bulunamadi: {tenant_slug}'))
            return
        
        # Tenant schema'sında çalış
        with schema_context(tenant.schema_name):
            # Django User oluştur
            user, created = User.objects.get_or_create(
                username=username,
                defaults={
                    'email': email,
                    'first_name': first_name,
                    'last_name': last_name,
                    'is_active': True,
                    'is_staff': True,
                }
            )
            
            if created:
                user.set_password(password)
                user.save()
                self.stdout.write(self.style.SUCCESS(f'[OK] Django User olusturuldu: {username}'))
            else:
                self.stdout.write(self.style.WARNING(f'[SKIP] Django User zaten mevcut: {username}'))
                user.set_password(password)
                user.save()
            
            # UserType bul
            try:
                user_type = UserType.objects.get(code=user_type_code)
            except UserType.DoesNotExist:
                self.stdout.write(self.style.WARNING(f'[SKIP] UserType bulunamadi: {user_type_code}, varsayilan kullanilacak'))
                user_type = None
            
            # TenantUser oluştur
            tenant_user, created = TenantUser.objects.get_or_create(
                user=user,
                defaults={
                    'user_type': user_type,
                    'is_active': True,
                }
            )
            if created:
                self.stdout.write(self.style.SUCCESS(f'[OK] TenantUser olusturuldu'))
            else:
                self.stdout.write(self.style.WARNING(f'[SKIP] TenantUser zaten mevcut'))
                if user_type:
                    tenant_user.user_type = user_type
                    tenant_user.save()
            
            # Role bul ve ata
            try:
                role = Role.objects.get(code=role_code)
                user_role, created = UserRole.objects.get_or_create(
                    tenant_user=tenant_user,
                    role=role,
                    defaults={
                        'is_active': True,
                        'assigned_by': user,
                    }
                )
                if created:
                    self.stdout.write(self.style.SUCCESS(f'[OK] Rol atandi: {role.name}'))
                else:
                    self.stdout.write(self.style.WARNING(f'[SKIP] Rol zaten atanmis: {role.name}'))
            except Role.DoesNotExist:
                self.stdout.write(self.style.WARNING(f'[SKIP] Rol bulunamadi: {role_code}'))
        
        self.stdout.write(self.style.SUCCESS(f'\n=== KULLANICI OLUSTURULDU ==='))
        self.stdout.write(f'Tenant: {tenant.name}')
        self.stdout.write(f'Kullanici: {username}')
        self.stdout.write(f'E-posta: {email}')
        self.stdout.write(f'Kullanici Tipi: {user_type.name if user_type else "Yok"}')
        self.stdout.write(f'Rol: {role.name if "role" in locals() else "Yok"}')
        self.stdout.write(f'\nLogin URL: http://{tenant.domains.first().domain}:8000/login/')
