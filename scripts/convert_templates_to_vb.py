#!/usr/bin/env python
"""
Template'leri VB Tarzına Dönüştürme Scripti
Modern web trendlerini VB tarzına çevirir
"""

import os
import re
from pathlib import Path

# Mapping dictionary - Modern class'ları VB class'larına çevir
CLASS_MAPPINGS = {
    # Butonlar
    r'class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600[^"]*"': 'class="vb-button primary"',
    r'class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600[^"]*"': 'class="vb-button success"',
    r'class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600[^"]*"': 'class="vb-button danger"',
    r'class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300[^"]*"': 'class="vb-button"',
    
    # Form elemanları
    r'class="w-full px-3 py-2 border border-gray-300 rounded-lg[^"]*"': 'class="vb-textbox"',
    r'class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2[^"]*"': 'class="vb-textbox"',
    
    # Layout - Card'ları GroupBox'a çevir
    r'class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm[^"]*"': 'class="groupbox"',
    r'class="bg-white rounded-lg border border-gray-200 shadow-sm[^"]*"': 'class="groupbox"',
}

# Tekil class değişiklikleri
SINGLE_CLASS_REPLACEMENTS = {
    'rounded-lg': 'rounded-vb',
    'rounded-xl': 'rounded-vb',
    'rounded-2xl': 'rounded-vb',
    'shadow-lg': 'shadow-vb-sm',
    'shadow-xl': '',
    'shadow-2xl': '',
}

def convert_template(file_path):
    """Template dosyasını VB tarzına çevir"""
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        original_content = content
        
        # 1. Class mapping'leri uygula
        for old_pattern, new_class in CLASS_MAPPINGS.items():
            content = re.sub(old_pattern, new_class, content, flags=re.IGNORECASE)
        
        # 2. Tekil class değişiklikleri
        for old_class, new_class in SINGLE_CLASS_REPLACEMENTS.items():
            # Class attribute içinde değiştir
            pattern = r'class="([^"]*)\b' + re.escape(old_class) + r'\b([^"]*)"'
            def replace_class(match):
                classes = match.group(1) + match.group(2)
                if new_class:
                    return f'class="{classes} {new_class}"'.replace('  ', ' ').strip()
                else:
                    return f'class="{classes}"'.strip()
            content = re.sub(pattern, replace_class, content)
            
            # Tek başına class
            pattern = r'\b' + re.escape(old_class) + r'\b'
            if new_class:
                content = re.sub(pattern, new_class, content)
            else:
                content = re.sub(pattern, '', content)
        
        # 3. Card yapılarını GroupBox'a çevir
        # <div class="card"> -> <div class="groupbox">
        content = re.sub(r'<div class="card[^"]*">', '<div class="groupbox">', content, flags=re.IGNORECASE)
        content = re.sub(r'<div class="card-body[^"]*">', '<div class="groupbox-body">', content, flags=re.IGNORECASE)
        content = re.sub(r'<div class="card-header[^"]*">', '<div class="groupbox-header">', content, flags=re.IGNORECASE)
        
        # 4. Modern table'ları datagrid'e çevir
        # <table class="w-full border-collapse"> -> <div class="datagrid"><div class="vb-datagrid-container"><table>
        table_pattern = r'<table class="w-full border-collapse[^"]*">'
        if re.search(table_pattern, content, re.IGNORECASE):
            content = re.sub(
                table_pattern,
                '<div class="datagrid"><div class="vb-datagrid-container"><table>',
                content,
                flags=re.IGNORECASE
            )
            # </table> -> </table></div></div>
            content = re.sub(r'</table>', '</table></div></div>', content, count=1)
        
        # 5. Değişiklik varsa dosyayı kaydet
        if content != original_content:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            return True
        
        return False
    
    except Exception as e:
        print(f"Hata ({file_path}): {e}")
        return False

def main():
    """Ana fonksiyon"""
    
    print("🔄 Template'leri VB tarzına dönüştürülüyor...")
    print("⚠️  Bu işlem dosyaları değiştirecektir!")
    print()
    
    # Template dizinleri
    template_dirs = [
        Path('templates'),
        Path('apps') / 'tenant_apps' / 'tours' / 'templates',
        Path('apps') / 'tenant_apps' / 'hotels' / 'templates',
        # Path('apps') / 'tenant_apps' / 'reception' / 'templates',  # KALDIRILDI, YENİDEN İNŞA EDİLECEK
    ]
    
    converted_count = 0
    total_count = 0
    
    for template_dir in template_dirs:
        if not template_dir.exists():
            continue
        
        for html_file in template_dir.rglob('*.html'):
            total_count += 1
            if convert_template(html_file):
                converted_count += 1
                print(f"✅ {html_file}")
    
    print()
    print(f"📊 Özet:")
    print(f"   Toplam dosya: {total_count}")
    print(f"   Dönüştürülen: {converted_count}")
    print()
    print("✅ İşlem tamamlandı!")
    print()
    print("⚠️  ÖNEMLİ:")
    print("   1. Değişiklikleri kontrol edin")
    print("   2. Sayfaları tarayıcıda test edin")
    print("   3. Gerekirse manuel düzeltmeler yapın")

if __name__ == '__main__':
    main()

