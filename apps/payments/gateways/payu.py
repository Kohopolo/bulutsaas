"""
PayU Payment Gateway Integration
"""
import hashlib
import requests
from decimal import Decimal
from typing import Dict, Any, Optional
from .base import BasePaymentGateway


class PayUGateway(BasePaymentGateway):
    """PayU Payment Gateway"""
    
    API_URL = "https://secure.payu.com.tr"
    TEST_API_URL = "https://secure.payu.com.tr"
    
    def __init__(self, config: Dict[str, Any]):
        super().__init__(config)
        self.base_url = self.TEST_API_URL if self.is_test_mode else self.API_URL
    
    def create_payment(self, amount: Decimal, currency: str, order_id: str,
                      customer_info: Dict[str, Any], **kwargs) -> Dict[str, Any]:
        """Create payment with PayU"""
        # PayU ödeme formu oluştur
        data = {
            'merchant': self.merchant_id,
            'orderRef': order_id,
            'orderDate': kwargs.get('order_date', ''),
            'orderPName': kwargs.get('product_name', 'Ödeme'),
            'orderPGroup': '',
            'orderPCode': order_id,
            'orderPInfo': '',
            'orderPrice': str(amount),
            'orderQty': '1',
            'orderVAT': '0',
            'orderShipping': '0',
            'orderTotal': str(amount),
            'currency': currency,
            'buyerName': customer_info.get('name', ''),
            'buyerEmail': customer_info.get('email', ''),
            'buyerPhone': customer_info.get('phone', ''),
            'buyerAddress': customer_info.get('address', ''),
            'buyerCity': customer_info.get('city', ''),
            'buyerCountry': customer_info.get('country', 'Turkey'),
            'buyerZipCode': customer_info.get('zip_code', ''),
            'returnUrl': kwargs.get('success_url', ''),
            'notifyUrl': kwargs.get('callback_url', ''),
            'testOrder': '1' if self.is_test_mode else '0',
        }
        
        # Hash oluştur
        hash_string = f"{self.merchant_id}{order_id}{str(amount)}{currency}"
        hash_value = hashlib.md5((hash_string + self.secret_key).encode()).hexdigest()
        data['HASH'] = hash_value
        
        payment_url = f"{self.base_url}/order/alu/v3"
        
        return {
            'success': True,
            'payment_url': payment_url,
            'form_data': data,
            'transaction_id': order_id,
        }
    
    def verify_payment(self, transaction_id: str, **kwargs) -> Dict[str, Any]:
        """Verify payment status"""
        # PayU callback doğrulama
        hash_string = f"{kwargs.get('REFNO')}{kwargs.get('STATUS')}{kwargs.get('AMOUNT')}"
        calculated_hash = hashlib.md5(
            (hash_string + self.secret_key).encode()
        ).hexdigest()
        
        if calculated_hash == kwargs.get('HASH'):
            status = 'completed' if kwargs.get('STATUS') == 'SUCCESS' else 'failed'
            return {
                'success': True,
                'status': status,
                'amount': Decimal(kwargs.get('AMOUNT', 0)),
                'transaction_id': kwargs.get('REFNO', ''),
            }
        else:
            return {
                'success': False,
                'error': 'Hash doğrulama başarısız',
            }
    
    def refund(self, transaction_id: str, amount: Optional[Decimal] = None,
               **kwargs) -> Dict[str, Any]:
        """Refund payment"""
        return {
            'success': False,
            'error': 'PayU iade API entegrasyonu yapılacak',
        }
    
    def handle_webhook(self, payload: Dict[str, Any], headers: Dict[str, Any]) -> Dict[str, Any]:
        """Handle PayU webhook"""
        return self.verify_payment(
            payload.get('REFNO', ''),
            **payload
        )





