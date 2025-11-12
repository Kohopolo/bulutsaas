"""
Muhasebe Yönetim Formları
"""
from django import forms
from .models import Account, JournalEntry, JournalEntryLine, Invoice, InvoiceLine, Payment


class AccountForm(forms.ModelForm):
    """Hesap Formu"""
    class Meta:
        model = Account
        fields = ['code', 'name', 'account_type', 'currency', 'parent', 'level',
                 'opening_balance', 'description', 'is_active', 'is_system', 'sort_order']
        widgets = {
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'account_type': forms.Select(attrs={'class': 'form-control'}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'parent': forms.Select(attrs={'class': 'form-control'}),
            'level': forms.NumberInput(attrs={'class': 'form-control'}),
            'opening_balance': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_system': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control'}),
        }
        labels = {
            'code': 'Hesap Kodu',
            'name': 'Hesap Adı',
            'account_type': 'Hesap Tipi',
            'currency': 'Para Birimi',
            'parent': 'Üst Hesap',
            'level': 'Seviye',
            'opening_balance': 'Açılış Bakiyesi',
            'description': 'Açıklama',
            'is_active': 'Aktif mi?',
            'is_system': 'Sistem Hesabı mı?',
            'sort_order': 'Sıralama',
        }
        help_texts = {
            'code': 'Hesap planı kodu (örn: 100, 120, 600)',
            'name': 'Hesap adı',
            'account_type': 'Hesap tipi (Aktif, Pasif, Gelir, Gider)',
            'currency': 'Para birimi',
            'parent': 'Üst hesap (hiyerarşi için)',
            'level': 'Hesap hiyerarşi seviyesi',
            'opening_balance': 'Açılış bakiyesi',
            'description': 'Hesap açıklaması',
            'is_active': 'Bu hesap aktif mi?',
            'is_system': 'Sistem hesabı mı? (Silinemez)',
            'sort_order': 'Sıralama numarası',
        }


class JournalEntryForm(forms.ModelForm):
    """Yevmiye Kaydı Formu"""
    class Meta:
        model = JournalEntry
        fields = ['entry_date', 'description', 'source_module', 'source_id', 
                 'source_reference', 'notes', 'status']
        widgets = {
            'entry_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'source_module': forms.TextInput(attrs={'class': 'form-control'}),
            'source_id': forms.NumberInput(attrs={'class': 'form-control'}),
            'source_reference': forms.TextInput(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'status': forms.Select(attrs={'class': 'form-control'}),
        }
        labels = {
            'entry_date': 'Kayıt Tarihi',
            'description': 'Açıklama',
            'source_module': 'Kaynak Modül',
            'source_id': 'Kaynak ID',
            'source_reference': 'Kaynak Referans',
            'notes': 'Notlar',
            'status': 'Durum',
        }


class JournalEntryLineForm(forms.ModelForm):
    """Yevmiye Kayıt Satırı Formu"""
    class Meta:
        model = JournalEntryLine
        fields = ['account', 'debit', 'credit', 'description']
        widgets = {
            'account': forms.Select(attrs={'class': 'form-control'}),
            'debit': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0'}),
            'credit': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0'}),
            'description': forms.TextInput(attrs={'class': 'form-control'}),
        }
        labels = {
            'account': 'Hesap',
            'debit': 'Borç',
            'credit': 'Alacak',
            'description': 'Açıklama',
        }


class InvoiceForm(forms.ModelForm):
    """Fatura Formu"""
    class Meta:
        model = Invoice
        fields = ['invoice_type', 'invoice_date', 'due_date', 'customer_name', 
                 'customer_tax_id', 'customer_address', 'customer_email', 'customer_phone',
                 'subtotal', 'discount_amount', 'tax_rate', 'currency', 'status',
                 'source_module', 'source_id', 'source_reference', 'description', 'notes']
        widgets = {
            'invoice_type': forms.Select(attrs={'class': 'form-control'}),
            'invoice_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'due_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'customer_name': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_tax_id': forms.TextInput(attrs={'class': 'form-control'}),
            'customer_address': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'customer_email': forms.EmailInput(attrs={'class': 'form-control'}),
            'customer_phone': forms.TextInput(attrs={'class': 'form-control'}),
            'subtotal': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'discount_amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'tax_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'source_module': forms.TextInput(attrs={'class': 'form-control'}),
            'source_id': forms.NumberInput(attrs={'class': 'form-control'}),
            'source_reference': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
        labels = {
            'invoice_type': 'Fatura Tipi',
            'invoice_date': 'Fatura Tarihi',
            'due_date': 'Vade Tarihi',
            'customer_name': 'Müşteri/Tedarikçi Adı',
            'customer_tax_id': 'Vergi No/TC',
            'customer_address': 'Adres',
            'customer_email': 'E-posta',
            'customer_phone': 'Telefon',
            'subtotal': 'Ara Toplam',
            'discount_amount': 'İndirim Tutarı',
            'tax_rate': 'KDV Oranı (%)',
            'currency': 'Para Birimi',
            'status': 'Durum',
            'source_module': 'Kaynak Modül',
            'source_id': 'Kaynak ID',
            'source_reference': 'Kaynak Referans',
            'description': 'Açıklama',
            'notes': 'Notlar',
        }


class InvoiceLineForm(forms.ModelForm):
    """Fatura Satırı Formu"""
    class Meta:
        model = InvoiceLine
        fields = ['item_name', 'item_code', 'quantity', 'unit_price', 'discount_rate', 'description']
        widgets = {
            'item_name': forms.TextInput(attrs={'class': 'form-control'}),
            'item_code': forms.TextInput(attrs={'class': 'form-control'}),
            'quantity': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'unit_price': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'discount_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 2}),
        }
        labels = {
            'item_name': 'Ürün/Hizmet Adı',
            'item_code': 'Ürün/Hizmet Kodu',
            'quantity': 'Miktar',
            'unit_price': 'Birim Fiyat',
            'discount_rate': 'İndirim Oranı (%)',
            'description': 'Açıklama',
        }


class PaymentForm(forms.ModelForm):
    """Ödeme Formu"""
    class Meta:
        model = Payment
        fields = ['payment_date', 'amount', 'currency', 'payment_method', 'invoice',
                 'cash_account_id', 'source_module', 'source_id', 'source_reference',
                 'description', 'notes', 'status']
        widgets = {
            'payment_date': forms.DateTimeInput(attrs={'class': 'form-control', 'type': 'datetime-local'}),
            'amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0'}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'payment_method': forms.Select(attrs={'class': 'form-control'}),
            'invoice': forms.Select(attrs={'class': 'form-control'}),
            'cash_account_id': forms.NumberInput(attrs={'class': 'form-control'}),
            'source_module': forms.TextInput(attrs={'class': 'form-control'}),
            'source_id': forms.NumberInput(attrs={'class': 'form-control'}),
            'source_reference': forms.TextInput(attrs={'class': 'form-control'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'status': forms.Select(attrs={'class': 'form-control'}),
        }
        labels = {
            'payment_date': 'Ödeme Tarihi',
            'amount': 'Tutar',
            'currency': 'Para Birimi',
            'payment_method': 'Ödeme Yöntemi',
            'invoice': 'Fatura',
            'cash_account_id': 'Kasa Hesabı ID',
            'source_module': 'Kaynak Modül',
            'source_id': 'Kaynak ID',
            'source_reference': 'Kaynak Referans',
            'description': 'Açıklama',
            'notes': 'Notlar',
            'status': 'Durum',
        }

