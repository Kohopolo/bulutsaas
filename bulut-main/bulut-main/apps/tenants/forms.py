"""
Tenant Yönetim Formları
"""
from django import forms
from .models import Tenant, Domain


class TenantForm(forms.ModelForm):
    """Tenant Formu"""
    class Meta:
        model = Tenant
        fields = ['name', 'slug', 'schema_name', 'owner_name', 'owner_email', 'phone',
                 'address', 'city', 'country', 'is_active', 'is_trial', 'trial_end_date',
                 'package', 'settings']
        labels = {
            'name': 'İşletme Adı',
            'slug': 'Slug',
            'schema_name': 'Schema Adı',
            'owner_name': 'Sahip Adı',
            'owner_email': 'Sahip E-posta',
            'phone': 'Telefon',
            'address': 'Adres',
            'city': 'Şehir',
            'country': 'Ülke',
            'is_active': 'Aktif mi?',
            'is_trial': 'Deneme mi?',
            'trial_end_date': 'Deneme Bitiş Tarihi',
            'package': 'Paket',
            'settings': 'Ayarlar (JSON)',
        }
        help_texts = {
            'name': 'İşletme/şirket adı',
            'slug': 'URL slug (otomatik oluşturulur)',
            'schema_name': 'PostgreSQL schema adı (otomatik oluşturulur)',
            'owner_name': 'İşletme sahibinin adı',
            'owner_email': 'İşletme sahibinin e-posta adresi',
            'phone': 'İletişim telefonu',
            'address': 'İşletme adresi',
            'city': 'Şehir',
            'country': 'Ülke',
            'is_active': 'Bu tenant aktif mi?',
            'is_trial': 'Bu tenant deneme sürecinde mi?',
            'trial_end_date': 'Deneme süresinin bitiş tarihi',
            'package': 'Tenant\'ın kullandığı paket',
            'settings': 'Tenant özel ayarları JSON formatında',
        }


class DomainForm(forms.ModelForm):
    """Domain Formu"""
    class Meta:
        model = Domain
        fields = ['domain', 'tenant', 'domain_type', 'is_primary', 'ssl_enabled', 'ssl_certificate']
        labels = {
            'domain': 'Domain',
            'tenant': 'Tenant',
            'domain_type': 'Domain Tipi',
            'is_primary': 'Birincil Domain mi?',
            'ssl_enabled': 'SSL Aktif mi?',
            'ssl_certificate': 'SSL Sertifikası',
        }
        help_texts = {
            'domain': 'Domain adı (örn: test-otel.localhost)',
            'tenant': 'Bu domain\'in ait olduğu tenant',
            'domain_type': 'Domain tipi (Primary, Alias)',
            'is_primary': 'Bu domain birincil domain mi?',
            'ssl_enabled': 'SSL sertifikası aktif mi?',
            'ssl_certificate': 'SSL sertifika bilgileri (opsiyonel)',
        }

