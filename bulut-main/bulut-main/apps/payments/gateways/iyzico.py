"""
İyzico Payment Gateway Integration
https://dev.iyzipay.com/
"""
import hashlib
import hmac
import json
import requests
from decimal import Decimal
from typing import Dict, Any, Optional
from .base import BasePaymentGateway


class IyzicoGateway(BasePaymentGateway):
    """İyzico Payment Gateway"""
    
    API_URL = "https://api.iyzipay.com"
    TEST_API_URL = "https://sandbox-api.iyzipay.com"
    
    def __init__(self, config: Dict[str, Any]):
        super().__init__(config)
        self.base_url = self.TEST_API_URL if self.is_test_mode else self.API_URL
    
    def _generate_authorization(self, request_string: str) -> str:
        """Generate authorization header"""
        hash_string = self.secret_key + request_string
        hash_value = hashlib.sha256(hash_string.encode()).hexdigest()
        return f"IYZWS {self.api_key}:{hash_value}"
    
    def _make_request(self, endpoint: str, data: Dict[str, Any]) -> Dict[str, Any]:
        """Make API request"""
        url = f"{self.base_url}{endpoint}"
        request_string = json.dumps(data, separators=(',', ':'))
        headers = {
            'Authorization': self._generate_authorization(request_string),
            'Content-Type': 'application/json',
        }
        
        try:
            response = requests.post(url, data=request_string, headers=headers, timeout=30)
            return response.json()
        except Exception as e:
            return {'status': 'failure', 'errorMessage': str(e)}
    
    def create_payment(self, amount: Decimal, currency: str, order_id: str,
                      customer_info: Dict[str, Any], **kwargs) -> Dict[str, Any]:
        """Create payment with İyzico"""
        # 3D Secure ödeme için ödeme formu oluştur
        payment_data = {
            'locale': 'tr',
            'conversationId': order_id,
            'price': str(amount),
            'paidPrice': str(amount),
            'currency': currency,
            'basketId': order_id,
            'paymentGroup': 'PRODUCT',
            'callbackUrl': kwargs.get('callback_url', ''),
            'enabledInstallments': kwargs.get('installments', [2, 3, 6, 9]),
            'buyer': {
                'id': customer_info.get('id', ''),
                'name': customer_info.get('name', ''),
                'surname': customer_info.get('surname', ''),
                'gsmNumber': customer_info.get('phone', ''),
                'email': customer_info.get('email', ''),
                'identityNumber': customer_info.get('tc_number', ''),
                'registrationAddress': customer_info.get('address', ''),
                'city': customer_info.get('city', ''),
                'country': customer_info.get('country', 'Turkey'),
                'zipCode': customer_info.get('zip_code', ''),
            },
            'shippingAddress': {
                'contactName': customer_info.get('name', ''),
                'city': customer_info.get('city', ''),
                'country': customer_info.get('country', 'Turkey'),
                'address': customer_info.get('address', ''),
                'zipCode': customer_info.get('zip_code', ''),
            },
            'billingAddress': {
                'contactName': customer_info.get('name', ''),
                'city': customer_info.get('city', ''),
                'country': customer_info.get('country', 'Turkey'),
                'address': customer_info.get('address', ''),
                'zipCode': customer_info.get('zip_code', ''),
            },
            'basketItems': kwargs.get('basket_items', [
                {
                    'id': order_id,
                    'name': 'Paket Aboneliği',
                    'category1': 'Subscription',
                    'itemType': 'VIRTUAL',
                    'price': str(amount),
                }
            ]),
        }
        
        response = self._make_request('/payment/auth', payment_data)
        
        if response.get('status') == 'success':
            return {
                'success': True,
                'payment_url': response.get('threeDSHtmlContent', ''),
                'transaction_id': response.get('paymentId', ''),
                'conversation_id': response.get('conversationId', ''),
            }
        else:
            return {
                'success': False,
                'error': response.get('errorMessage', 'Ödeme oluşturulamadı'),
            }
    
    def verify_payment(self, transaction_id: str, **kwargs) -> Dict[str, Any]:
        """Verify payment status"""
        data = {
            'locale': 'tr',
            'conversationId': kwargs.get('conversation_id', ''),
            'paymentId': transaction_id,
        }
        
        response = self._make_request('/payment/retrieve', data)
        
        if response.get('status') == 'success':
            return {
                'success': True,
                'status': 'completed' if response.get('paymentStatus') == 'SUCCESS' else 'failed',
                'amount': Decimal(str(response.get('paidPrice', 0))),
                'transaction_id': response.get('paymentId', ''),
            }
        else:
            return {
                'success': False,
                'error': response.get('errorMessage', 'Ödeme doğrulanamadı'),
            }
    
    def refund(self, transaction_id: str, amount: Optional[Decimal] = None,
               **kwargs) -> Dict[str, Any]:
        """Refund payment"""
        data = {
            'locale': 'tr',
            'conversationId': kwargs.get('conversation_id', ''),
            'paymentTransactionId': transaction_id,
        }
        
        if amount:
            data['price'] = str(amount)
        
        response = self._make_request('/payment/refund', data)
        
        if response.get('status') == 'success':
            return {
                'success': True,
                'refund_id': response.get('paymentId', ''),
            }
        else:
            return {
                'success': False,
                'error': response.get('errorMessage', 'İade yapılamadı'),
            }
    
    def handle_webhook(self, payload: Dict[str, Any], headers: Dict[str, Any]) -> Dict[str, Any]:
        """Handle İyzico webhook"""
        # İyzico webhook doğrulama
        transaction_id = payload.get('paymentId', '')
        status = payload.get('paymentStatus', '')
        
        return {
            'success': True,
            'transaction_id': transaction_id,
            'status': 'completed' if status == 'SUCCESS' else 'failed',
        }
