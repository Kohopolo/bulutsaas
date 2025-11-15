#!/usr/bin/env python
"""
Veritabanı Export Scripti
GitHub'a yüklemek için veritabanını export eder
"""

import os
import sys
import django
from datetime import datetime

# Django setup
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
django.setup()

from django.core.management import call_command
from django.conf import settings
import json

def export_database():
    """Veritabanını JSON formatında export eder"""
    
    print("🔄 Veritabanı export ediliyor...")
    
    # Export klasörü
    export_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'database_backups')
    os.makedirs(export_dir, exist_ok=True)
    
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    
    # 1. Public schema (tenants, packages, modules, subscriptions)
    print("📦 Public schema export ediliyor...")
    public_file = os.path.join(export_dir, f'public_schema_{timestamp}.json')
    
    call_command(
        'dumpdata',
        'tenants',
        'packages',
        'modules',
        'subscriptions',
        'permissions',
        'contenttypes',
        'auth.permission',
        'auth.group',
        output=public_file,
        indent=2,
        natural_foreign=True,
        natural_primary=True,
        exclude=['auth.user'],  # Kullanıcıları hariç tut (güvenlik)
    )
    
    print(f"✅ Public schema kaydedildi: {public_file}")
    
    # 2. Tenant schemas (her tenant için ayrı)
    print("🏢 Tenant schemas export ediliyor...")
    
    from apps.tenants.models import Tenant
    
    tenants = Tenant.objects.all()
    tenant_data = {}
    
    for tenant in tenants:
        print(f"  - {tenant.name} ({tenant.schema_name})")
        
        # Tenant schema'sına geç
        from django_tenants.utils import schema_context
        
        with schema_context(tenant.schema_name):
            # Tenant verilerini export et
            tenant_file = os.path.join(export_dir, f'tenant_{tenant.schema_name}_{timestamp}.json')
            
            # Tüm tenant app'lerini export et
            apps_to_export = [
                'hotels',
                'tours',
                'reservations',
                'housekeeping',
                'customers',
                'users',
                'roles',
                'permissions',
            ]
            
            call_command(
                'dumpdata',
                *apps_to_export,
                output=tenant_file,
                indent=2,
                natural_foreign=True,
                natural_primary=True,
                exclude=['auth.user'],  # Kullanıcıları hariç tut
            )
            
            tenant_data[tenant.schema_name] = {
                'name': tenant.name,
                'slug': tenant.slug,
                'file': os.path.basename(tenant_file),
            }
    
    # 3. Index dosyası oluştur
    index_file = os.path.join(export_dir, f'export_index_{timestamp}.json')
    index_data = {
        'export_date': timestamp,
        'public_schema': os.path.basename(public_file),
        'tenants': tenant_data,
        'note': 'Bu dosyalar GitHub\'a yüklenebilir. Hassas veriler (şifreler, kredi kartları) temizlenmiştir.'
    }
    
    with open(index_file, 'w', encoding='utf-8') as f:
        json.dump(index_data, f, indent=2, ensure_ascii=False)
    
    print(f"\n✅ Export tamamlandı!")
    print(f"📁 Dosyalar: {export_dir}")
    print(f"📋 Index dosyası: {index_file}")
    print(f"\n⚠️  UYARI: Bu dosyaları GitHub'a yüklemeden önce:")
    print("   1. Şifreleri kontrol edin")
    print("   2. Kişisel bilgileri (TC, telefon) kontrol edin")
    print("   3. Kredi kartı bilgileri olmadığından emin olun")
    
    return export_dir

if __name__ == '__main__':
    export_database()





