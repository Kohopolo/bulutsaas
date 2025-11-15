"""
Kasa Yönetim Formları
"""
from django import forms
from .models import CashAccount, CashTransaction, CashFlow


class CashAccountForm(forms.ModelForm):
    """Kasa Hesabı Formu"""
    hotel = forms.ModelChoiceField(
        queryset=None,
        required=False,
        empty_label='--- Genel Kasa Hesabı (Tüm Oteller) ---',
        widget=forms.Select(attrs={'class': 'form-control'}),
        help_text='Boş bırakılırsa tüm oteller için genel kasa hesabı olur'
    )
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        try:
            from apps.tenant_apps.hotels.models import Hotel
            self.fields['hotel'].queryset = Hotel.objects.filter(is_deleted=False).order_by('name')
        except:
            self.fields['hotel'].queryset = Hotel.objects.none()
    
    class Meta:
        model = CashAccount
        fields = ['name', 'code', 'hotel', 'account_type', 'currency', 'bank_name', 'branch_name',
                 'account_number', 'iban', 'initial_balance', 'description', 'is_active', 
                 'is_default', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'form-control'}),
            'code': forms.TextInput(attrs={'class': 'form-control'}),
            'hotel': forms.Select(attrs={'class': 'form-control'}),
            'account_type': forms.Select(attrs={'class': 'form-control'}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'bank_name': forms.TextInput(attrs={'class': 'form-control'}),
            'branch_name': forms.TextInput(attrs={'class': 'form-control'}),
            'account_number': forms.TextInput(attrs={'class': 'form-control'}),
            'iban': forms.TextInput(attrs={'class': 'form-control'}),
            'initial_balance': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'is_default': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'sort_order': forms.NumberInput(attrs={'class': 'form-control'}),
        }
        labels = {
            'name': 'Hesap Adı',
            'code': 'Hesap Kodu',
            'hotel': 'Otel',
            'account_type': 'Hesap Tipi',
            'currency': 'Para Birimi',
            'bank_name': 'Banka Adı',
            'branch_name': 'Şube Adı',
            'account_number': 'Hesap No',
            'iban': 'IBAN',
            'initial_balance': 'Başlangıç Bakiyesi',
            'description': 'Açıklama',
            'is_active': 'Aktif mi?',
            'is_default': 'Varsayılan Hesap mı?',
            'sort_order': 'Sıralama',
        }
        help_texts = {
            'name': 'Kasa hesabının adı (örn: Ana Kasa, İş Bankası TL)',
            'code': 'Benzersiz hesap kodu (otomatik oluşturulur)',
            'hotel': 'Boş bırakılırsa tüm oteller için genel kasa hesabı olur',
            'account_type': 'Hesap tipini seçin (Nakit, Banka, Kredi Kartı vb.)',
            'currency': 'Hesabın para birimi',
            'bank_name': 'Banka hesabı ise banka adı',
            'branch_name': 'Banka şubesi adı',
            'account_number': 'Banka hesap numarası',
            'iban': 'IBAN numarası (34 karakter)',
            'initial_balance': 'Hesabın başlangıç bakiyesi',
            'description': 'Hesap hakkında açıklama',
            'is_active': 'Bu hesap aktif mi?',
            'is_default': 'Varsayılan hesap olarak kullanılsın mı?',
            'sort_order': 'Sıralama numarası (küçükten büyüğe)',
        }


class CashTransactionForm(forms.ModelForm):
    """Kasa İşlemi Formu"""
    hotel = forms.ModelChoiceField(
        queryset=None,
        required=False,
        empty_label='--- Genel İşlem ---',
        widget=forms.Select(attrs={'class': 'form-control'}),
        help_text='Boş bırakılırsa genel kasa işlemi olur'
    )
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        try:
            from apps.tenant_apps.hotels.models import Hotel
            self.fields['hotel'].queryset = Hotel.objects.filter(is_deleted=False).order_by('name')
        except:
            self.fields['hotel'].queryset = Hotel.objects.none()
    
    class Meta:
        model = CashTransaction
        fields = ['account', 'hotel', 'transaction_type', 'amount', 'currency', 'to_account',
                 'source_module', 'source_id', 'source_reference', 'payment_method',
                 'payment_date', 'due_date', 'description', 'notes', 'status']
        widgets = {
            'account': forms.Select(attrs={'class': 'form-control'}),
            'hotel': forms.Select(attrs={'class': 'form-control'}),
            'transaction_type': forms.Select(attrs={'class': 'form-control'}),
            'amount': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': '0'}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'to_account': forms.Select(attrs={'class': 'form-control'}),
            'source_module': forms.TextInput(attrs={'class': 'form-control'}),
            'source_id': forms.NumberInput(attrs={'class': 'form-control'}),
            'source_reference': forms.TextInput(attrs={'class': 'form-control'}),
            'payment_method': forms.Select(attrs={'class': 'form-control'}),
            'payment_date': forms.DateTimeInput(attrs={'class': 'form-control', 'type': 'datetime-local'}),
            'due_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'description': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'status': forms.Select(attrs={'class': 'form-control'}),
        }
        labels = {
            'account': 'Kasa Hesabı',
            'hotel': 'Otel',
            'transaction_type': 'İşlem Tipi',
            'amount': 'Tutar',
            'currency': 'Para Birimi',
            'to_account': 'Hedef Hesap (Transfer için)',
            'source_module': 'Kaynak Modül',
            'source_id': 'Kaynak ID',
            'source_reference': 'Kaynak Referans',
            'payment_method': 'Ödeme Yöntemi',
            'payment_date': 'Ödeme Tarihi',
            'due_date': 'Vade Tarihi',
            'description': 'Açıklama',
            'notes': 'Notlar',
            'status': 'Durum',
        }
        help_texts = {
            'account': 'İşlemin yapılacağı kasa hesabı',
            'transaction_type': 'İşlem tipi (Gelir, Gider, Transfer, Düzeltme)',
            'amount': 'İşlem tutarı',
            'currency': 'Para birimi',
            'to_account': 'Transfer işlemleri için hedef hesap',
            'source_module': 'İşlemin kaynağı (tours, reservations vb.)',
            'source_id': 'Kaynak modülün kayıt ID\'si',
            'source_reference': 'Kaynak referans (Rezervasyon no, Tur adı vb.)',
            'payment_method': 'Ödeme yöntemi',
            'payment_date': 'Ödeme tarihi',
            'due_date': 'Vadeli ödemeler için vade tarihi',
            'description': 'İşlem açıklaması',
            'notes': 'Ek notlar',
            'status': 'İşlem durumu',
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        # Transfer işlemi değilse to_account'u gizle
        if 'transaction_type' in self.data:
            if self.data['transaction_type'] != 'transfer':
                self.fields['to_account'].widget = forms.HiddenInput()
        elif self.instance.pk and self.instance.transaction_type != 'transfer':
            self.fields['to_account'].widget = forms.HiddenInput()


class CashFlowForm(forms.ModelForm):
    """Nakit Akışı Formu"""
    class Meta:
        model = CashFlow
        fields = ['account', 'period_type', 'period_start', 'period_end']
        widgets = {
            'account': forms.Select(attrs={'class': 'form-control'}),
            'period_type': forms.Select(attrs={'class': 'form-control'}),
            'period_start': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'period_end': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
        }
        labels = {
            'account': 'Kasa Hesabı',
            'period_type': 'Dönem Tipi',
            'period_start': 'Dönem Başlangıcı',
            'period_end': 'Dönem Bitişi',
        }
        help_texts = {
            'account': 'Nakit akışı hesaplanacak kasa hesabı',
            'period_type': 'Dönem tipi (Günlük, Haftalık, Aylık, Yıllık)',
            'period_start': 'Dönem başlangıç tarihi',
            'period_end': 'Dönem bitiş tarihi',
        }

