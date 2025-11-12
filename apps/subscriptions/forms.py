"""
Abonelik Yönetim Formları
"""
from django import forms
from .models import Subscription, Payment


class SubscriptionForm(forms.ModelForm):
    """Abonelik Formu"""
    class Meta:
        model = Subscription
        fields = ['tenant', 'package', 'period', 'start_date', 'end_date', 'next_billing_date',
                 'amount', 'currency', 'status', 'auto_renew', 'stripe_subscription_id',
                 'stripe_customer_id', 'notes']
        labels = {
            'tenant': 'Tenant (Üye)',
            'package': 'Paket',
            'period': 'Dönem',
            'start_date': 'Başlangıç Tarihi',
            'end_date': 'Bitiş Tarihi',
            'next_billing_date': 'Sonraki Fatura Tarihi',
            'amount': 'Tutar',
            'currency': 'Para Birimi',
            'status': 'Durum',
            'auto_renew': 'Otomatik Yenileme',
            'stripe_subscription_id': 'Stripe Subscription ID',
            'stripe_customer_id': 'Stripe Customer ID',
            'notes': 'Notlar',
        }
        help_texts = {
            'tenant': 'Aboneliğin ait olduğu tenant (üye)',
            'package': 'Abonelik paketi',
            'period': 'Abonelik dönemi (Aylık/Yıllık/Deneme)',
            'start_date': 'Aboneliğin başlangıç tarihi',
            'end_date': 'Aboneliğin bitiş tarihi',
            'next_billing_date': 'Sonraki fatura tarihi (otomatik yenileme için)',
            'amount': 'Abonelik tutarı',
            'currency': 'Para birimi (TRY, USD, EUR)',
            'status': 'Abonelik durumu',
            'auto_renew': 'Abonelik otomatik yenilensin mi?',
            'stripe_subscription_id': 'Stripe abonelik ID (opsiyonel)',
            'stripe_customer_id': 'Stripe müşteri ID (opsiyonel)',
            'notes': 'Abonelik hakkında notlar',
        }


class PaymentForm(forms.ModelForm):
    """Ödeme Formu"""
    class Meta:
        model = Payment
        fields = ['subscription', 'amount', 'currency', 'payment_method', 'status', 'payment_date',
                 'invoice_number', 'invoice_url', 'stripe_payment_intent_id', 'stripe_charge_id', 'notes']
        labels = {
            'subscription': 'Abonelik',
            'amount': 'Tutar',
            'currency': 'Para Birimi',
            'payment_method': 'Ödeme Yöntemi',
            'status': 'Durum',
            'payment_date': 'Ödeme Tarihi',
            'invoice_number': 'Fatura Numarası',
            'invoice_url': 'Fatura URL',
            'stripe_payment_intent_id': 'Stripe Payment Intent ID',
            'stripe_charge_id': 'Stripe Charge ID',
            'notes': 'Notlar',
        }
        help_texts = {
            'subscription': 'Ödemenin ait olduğu abonelik',
            'amount': 'Ödeme tutarı',
            'currency': 'Para birimi (TRY, USD, EUR)',
            'payment_method': 'Ödeme yöntemi (Kredi Kartı, Havale, Nakit vb.)',
            'status': 'Ödeme durumu',
            'payment_date': 'Ödeme tarihi',
            'invoice_number': 'Fatura numarası',
            'invoice_url': 'Fatura URL (PDF linki)',
            'stripe_payment_intent_id': 'Stripe Payment Intent ID (opsiyonel)',
            'stripe_charge_id': 'Stripe Charge ID (opsiyonel)',
            'notes': 'Ödeme hakkında notlar',
        }


class PaymentInlineForm(forms.ModelForm):
    """Ödeme Inline Formu"""
    class Meta:
        model = Payment
        fields = ['amount', 'currency', 'status', 'payment_method', 'payment_date']
        labels = {
            'amount': 'Tutar',
            'currency': 'Para Birimi',
            'status': 'Durum',
            'payment_method': 'Ödeme Yöntemi',
            'payment_date': 'Ödeme Tarihi',
        }
        help_texts = {
            'amount': 'Ödeme tutarı',
            'currency': 'Para birimi (TRY, USD, EUR)',
            'status': 'Ödeme durumu',
            'payment_method': 'Ödeme yöntemi',
            'payment_date': 'Ödeme tarihi',
        }
        widgets = {
            'amount': forms.NumberInput(attrs={'step': '0.01', 'min': '0'}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'payment_method': forms.Select(attrs={'class': 'form-control'}),
            'payment_date': forms.DateTimeInput(attrs={'type': 'datetime-local', 'class': 'form-control'}),
        }
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        # Eğer payment_date boşsa ve status completed ise, şu anki tarihi ata
        if not instance.payment_date and instance.status == 'completed':
            from django.utils import timezone
            instance.payment_date = timezone.now()
        if commit:
            instance.save()
        return instance
