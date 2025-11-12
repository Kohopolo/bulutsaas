#!/usr/bin/env python
"""
Güvenli Veritabanı Export Scripti
Hassas verileri temizleyerek export eder (GitHub için)
"""

import os
import sys
import django
from datetime import datetime
import json

# Django setup
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
django.setup()

from django.core.management import call_command
from django.core.serializers import serialize
from django.db import connection

def clean_sensitive_data(data):
    """Hassas verileri temizler"""
    
    # Temizlenecek alanlar
    sensitive_fields = [
        'password',
        'secret_key',
        'api_key',
        'access_token',
        'refresh_token',
        'credit_card',
        'cvv',
        'iban',
        'tc_no',
        'passport_no',
        'phone',  # Opsiyonel: telefon numaralarını da temizlemek isterseniz
        'email',  # Opsiyonel: e-postaları da temizlemek isterseniz
    ]
    
    if isinstance(data, dict):
        cleaned = {}
        for key, value in data.items():
            if any(field in key.lower() for field in sensitive_fields):
                cleaned[key] = '***REDACTED***'
            elif isinstance(value, (dict, list)):
                cleaned[key] = clean_sensitive_data(value)
            else:
                cleaned[key] = value
        return cleaned
    elif isinstance(data, list):
        return [clean_sensitive_data(item) for item in data]
    else:
        return data

def export_safe_database():
    """Güvenli veritabanı export (hassas veriler temizlenmiş)"""
    
    print("🔄 Güvenli veritabanı export ediliyor...")
    print("⚠️  Hassas veriler temizlenecek...")
    
    export_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'database_backups')
    os.makedirs(export_dir, exist_ok=True)
    
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    
    # 1. Public schema (hassas veriler olmadan)
    print("📦 Public schema export ediliyor...")
    public_file = os.path.join(export_dir, f'public_schema_safe_{timestamp}.json')
    
    # Geçici dosya
    temp_file = public_file + '.tmp'
    
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
        output=temp_file,
        indent=2,
        natural_foreign=True,
        natural_primary=True,
        exclude=['auth.user', 'auth.session'],  # Kullanıcılar ve session'lar hariç
    )
    
    # Hassas verileri temizle
    with open(temp_file, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    cleaned_data = clean_sensitive_data(data)
    
    with open(public_file, 'w', encoding='utf-8') as f:
        json.dump(cleaned_data, f, indent=2, ensure_ascii=False)
    
    os.remove(temp_file)
    
    print(f"✅ Public schema kaydedildi: {public_file}")
    
    # 2. Örnek veriler (sadece yapı, veri yok)
    print("📋 Örnek veri yapısı oluşturuluyor...")
    
    sample_data = {
        'export_date': timestamp,
        'export_type': 'safe_export',
        'note': 'Bu dosya GitHub\'a yüklenebilir. Hassas veriler temizlenmiştir.',
        'public_schema': {
            'tenants': [
                {
                    'model': 'tenants.tenant',
                    'pk': 1,
                    'fields': {
                        'name': 'Örnek Otel',
                        'slug': 'ornek-otel',
                        'schema_name': 'tenant_ornek_otel',
                        'domain_url': 'ornek-otel.localhost',
                        'is_active': True,
                    }
                }
            ],
            'packages': [
                {
                    'model': 'packages.package',
                    'pk': 1,
                    'fields': {
                        'name': 'Başlangıç Paketi',
                        'code': 'starter',
                        'monthly_price': 299.00,
                        'is_active': True,
                    }
                }
            ],
            'modules': [
                {
                    'model': 'modules.module',
                    'pk': 1,
                    'fields': {
                        'name': 'Rezervasyon Yönetimi',
                        'code': 'reservation',
                        'category': 'Rezervasyon',
                        'is_active': True,
                    }
                }
            ]
        },
        'tenant_schemas': {
            'note': 'Tenant verileri production\'da hassas bilgiler içerebilir.',
            'sample_structure': {
                'hotels': 'Otel bilgileri',
                'rooms': 'Oda bilgileri',
                'reservations': 'Rezervasyon bilgileri',
                'customers': 'Müşteri bilgileri (hassas veriler temizlenmiş)',
            }
        }
    }
    
    sample_file = os.path.join(export_dir, f'sample_structure_{timestamp}.json')
    with open(sample_file, 'w', encoding='utf-8') as f:
        json.dump(sample_data, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Örnek yapı kaydedildi: {sample_file}")
    
    print(f"\n✅ Güvenli export tamamlandı!")
    print(f"📁 Dosyalar: {export_dir}")
    print(f"\n✅ Bu dosyalar GitHub'a yüklenebilir!")
    print(f"📋 Kullanım:")
    print(f"   git add database_backups/public_schema_safe_{timestamp}.json")
    print(f"   git add database_backups/sample_structure_{timestamp}.json")
    print(f"   git commit -m 'Database structure export (safe)'")
    print(f"   git push")
    
    return export_dir

if __name__ == '__main__':
    export_safe_database()

