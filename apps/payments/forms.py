"""
Ödeme Yönetim Formları
"""
from django import forms
from .models import PaymentGateway, TenantPaymentGateway, PaymentTransaction, PaymentWebhook


class PaymentGatewayForm(forms.ModelForm):
    """Ödeme Gateway Formu"""
    class Meta:
        model = PaymentGateway
        fields = ['name', 'code', 'gateway_type', 'description', 'api_url', 'test_api_url',
                 'supports_3d_secure', 'supports_installment', 'supports_refund', 'supports_recurring',
                 'is_active', 'is_test_mode', 'sort_order', 'settings']
        labels = {
            'name': 'Gateway Adı',
            'code': 'Gateway Kodu',
            'gateway_type': 'Gateway Tipi',
            'description': 'Açıklama',
            'api_url': 'API URL',
            'test_api_url': 'Test API URL',
            'supports_3d_secure': '3D Secure Destekli',
            'supports_installment': 'Taksit Destekli',
            'supports_refund': 'İade Destekli',
            'supports_recurring': 'Otomatik Ödeme Destekli',
            'is_active': 'Aktif mi?',
            'is_test_mode': 'Test Modu',
            'sort_order': 'Sıralama',
            'settings': 'Gateway Ayarları (JSON)',
        }
        help_texts = {
            'name': 'Ödeme gateway adı (örn: İyzico, PayTR)',
            'code': 'Benzersiz gateway kodu (otomatik oluşturulur)',
            'gateway_type': 'Gateway tipini seçin',
            'description': 'Gateway hakkında açıklama',
            'api_url': 'Canlı ortam API URL',
            'test_api_url': 'Test ortamı API URL',
            'supports_3d_secure': 'Bu gateway 3D Secure destekliyor mu?',
            'supports_installment': 'Bu gateway taksitli ödeme destekliyor mu?',
            'supports_refund': 'Bu gateway iade işlemi destekliyor mu?',
            'supports_recurring': 'Bu gateway otomatik ödeme destekliyor mu?',
            'is_active': 'Bu gateway aktif mi?',
            'is_test_mode': 'Bu gateway test modunda mı?',
            'sort_order': 'Sıralama numarası (küçükten büyüğe)',
            'settings': 'Gateway özel ayarları JSON formatında',
        }


class PaymentTransactionForm(forms.ModelForm):
    """Ödeme İşlemi Formu"""
    class Meta:
        model = PaymentTransaction
        fields = ['tenant', 'gateway', 'transaction_id', 'order_id', 'reference_number',
                 'amount', 'currency', 'status', 'payment_method', 'installment_count',
                 'card_bin', 'card_last_four', 'card_type', 'is_3d_secure', 'md_status',
                 'gateway_transaction_id', 'gateway_response', 'error_code', 'error_message',
                 'payment_date', 'notes']
        labels = {
            'tenant': 'Tenant',
            'gateway': 'Gateway',
            'transaction_id': 'İşlem ID',
            'order_id': 'Sipariş ID',
            'reference_number': 'Referans Numarası',
            'amount': 'Tutar',
            'currency': 'Para Birimi',
            'status': 'Durum',
            'payment_method': 'Ödeme Yöntemi',
            'installment_count': 'Taksit Sayısı',
            'card_bin': 'Kart BIN',
            'card_last_four': 'Kart Son 4 Hane',
            'card_type': 'Kart Tipi',
            'is_3d_secure': '3D Secure',
            'md_status': 'MD Status',
            'gateway_transaction_id': 'Gateway İşlem ID',
            'gateway_response': 'Gateway Yanıtı',
            'error_code': 'Hata Kodu',
            'error_message': 'Hata Mesajı',
            'payment_date': 'Ödeme Tarihi',
            'notes': 'Notlar',
        }
        help_texts = {
            'tenant': 'İşlemin ait olduğu tenant',
            'gateway': 'Kullanılan ödeme gateway',
            'transaction_id': 'Benzersiz işlem ID',
            'order_id': 'Sipariş/Rezervasyon ID',
            'reference_number': 'Referans numarası',
            'amount': 'İşlem tutarı',
            'currency': 'Para birimi (TRY, USD, EUR)',
            'status': 'İşlem durumu',
            'payment_method': 'Ödeme yöntemi',
            'installment_count': 'Taksit sayısı (1 = tek çekim)',
            'card_bin': 'Kart BIN numarası',
            'card_last_four': 'Kartın son 4 hanesi',
            'card_type': 'Kart tipi (Visa, Mastercard vb.)',
            'is_3d_secure': '3D Secure kullanıldı mı?',
            'md_status': '3D Secure MD status',
            'gateway_transaction_id': 'Gateway\'den dönen işlem ID',
            'gateway_response': 'Gateway yanıtı (JSON)',
            'error_code': 'Hata durumunda hata kodu',
            'error_message': 'Hata durumunda hata mesajı',
            'payment_date': 'Ödeme tarihi',
            'notes': 'İşlem hakkında notlar',
        }


class PaymentWebhookForm(forms.ModelForm):
    """Ödeme Webhook Formu"""
    class Meta:
        model = PaymentWebhook
        fields = ['gateway', 'transaction', 'event_type', 'is_processed', 'payload', 'headers', 'processing_error']
        labels = {
            'gateway': 'Gateway',
            'transaction': 'İşlem',
            'event_type': 'Olay Tipi',
            'is_processed': 'İşlendi mi?',
            'payload': 'Payload (JSON)',
            'headers': 'Headers (JSON)',
            'processing_error': 'İşleme Hatası',
        }
        help_texts = {
            'gateway': 'Webhook\'un geldiği gateway',
            'transaction': 'İlgili ödeme işlemi',
            'event_type': 'Webhook olay tipi',
            'is_processed': 'Bu webhook işlendi mi?',
            'payload': 'Webhook payload verisi (JSON)',
            'headers': 'Webhook header verileri (JSON)',
            'processing_error': 'İşleme sırasında oluşan hata',
        }

