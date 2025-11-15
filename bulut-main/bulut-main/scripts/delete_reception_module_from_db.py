#!/usr/bin/env python
"""
Public Schema'dan Reception Modülünü Silme Scripti
"""
import os
import sys
import django

# Django setup
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'config.settings')
django.setup()

from apps.modules.models import Module
from apps.packages.models import PackageModule


def delete_reception_module():
    """Public schema'dan reception modülünü sil"""
    
    print("=" * 60)
    print("RECEPTION MODULU SILME ISLEMI")
    print("=" * 60)
    
    # 1. PackageModule kayıtlarını sil
    print("\n[1] PackageModule kayitlarini temizleniyor...")
    try:
        reception_module = Module.objects.filter(code='reception').first()
        if reception_module:
            package_modules = PackageModule.objects.filter(module=reception_module)
            count = package_modules.count()
            package_modules.delete()
            print(f"   [OK] {count} PackageModule kaydi silindi")
        else:
            print("   [WARN] Reception modulu bulunamadi")
    except Exception as e:
        print(f"   [HATA] {e}")
    
    # 2. Module kaydını sil
    print("\n[2] Module kaydini siliniyor...")
    try:
        reception_module = Module.objects.filter(code='reception').first()
        if reception_module:
            reception_module.delete()
            print("   [OK] Reception modulu silindi")
        else:
            print("   [WARN] Reception modulu zaten silinmis")
    except Exception as e:
        print(f"   [HATA] {e}")
    
    print("\n" + "=" * 60)
    print("[OK] ISLEM TAMAMLANDI")
    print("=" * 60)


if __name__ == '__main__':
    delete_reception_module()

