"""
PayTR Payment Gateway Integration
https://www.paytr.com/
"""
import hashlib
import base64
import json
import requests
from decimal import Decimal
from typing import Dict, Any, Optional
from .base import BasePaymentGateway


class PayTRGateway(BasePaymentGateway):
    """PayTR Payment Gateway"""
    
    API_URL = "https://www.paytr.com/odeme/api/get-token"
    CALLBACK_URL = "https://www.paytr.com/odeme/guvenli"
    
    def create_payment(self, amount: Decimal, currency: str, order_id: str,
                      customer_info: Dict[str, Any], **kwargs) -> Dict[str, Any]:
        """Create payment with PayTR"""
        # PayTR token oluştur
        amount_cents = int(amount * 100)  # Kuruş cinsinden
        
        data = {
            'merchant_id': self.merchant_id,
            'merchant_key': self.secret_key,
            'merchant_salt': self.store_key,
            'email': customer_info.get('email', ''),
            'payment_amount': amount_cents,
            'currency': currency,
            'merchant_oid': order_id,
            'user_name': customer_info.get('name', ''),
            'user_address': customer_info.get('address', ''),
            'user_phone': customer_info.get('phone', ''),
            'merchant_ok_url': kwargs.get('success_url', ''),
            'merchant_fail_url': kwargs.get('fail_url', ''),
            'user_basket': base64.b64encode(
                json.dumps(kwargs.get('basket_items', [])).encode()
            ).decode(),
            'test_mode': '1' if self.is_test_mode else '0',
            'no_installment': '0' if kwargs.get('installment', False) else '1',
            'max_installment': str(kwargs.get('max_installment', 12)),
        }
        
        # Hash oluştur
        hash_str = f"{self.merchant_id}{self.secret_key}{amount_cents}{order_id}"
        hash_value = hashlib.sha256(hash_str.encode()).hexdigest()
        data['hash'] = hash_value
        
        try:
            response = requests.post(self.API_URL, data=data, timeout=30)
            result = response.json()
            
            if result.get('status') == 'success':
                return {
                    'success': True,
                    'payment_url': f"{self.CALLBACK_URL}?token={result.get('token')}",
                    'transaction_id': order_id,
                }
            else:
                return {
                    'success': False,
                    'error': result.get('reason', 'Ödeme oluşturulamadı'),
                }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }
    
    def verify_payment(self, transaction_id: str, **kwargs) -> Dict[str, Any]:
        """Verify payment status"""
        # PayTR callback'ten gelen bilgileri doğrula
        hash_str = f"{kwargs.get('merchant_oid')}{self.merchant_id}{kwargs.get('status')}{kwargs.get('total_amount')}"
        calculated_hash = hashlib.sha256(
            (hash_str + self.store_key).encode()
        ).hexdigest()
        
        if calculated_hash == kwargs.get('hash'):
            return {
                'success': True,
                'status': 'completed' if kwargs.get('status') == 'success' else 'failed',
                'amount': Decimal(kwargs.get('total_amount', 0)) / 100,
                'transaction_id': kwargs.get('merchant_oid', ''),
            }
        else:
            return {
                'success': False,
                'error': 'Hash doğrulama başarısız',
            }
    
    def refund(self, transaction_id: str, amount: Optional[Decimal] = None,
               **kwargs) -> Dict[str, Any]:
        """Refund payment"""
        # PayTR iade API'si
        return {
            'success': False,
            'error': 'PayTR iade API entegrasyonu yapılacak',
        }
    
    def handle_webhook(self, payload: Dict[str, Any], headers: Dict[str, Any]) -> Dict[str, Any]:
        """Handle PayTR webhook (callback)"""
        merchant_oid = payload.get('merchant_oid', '')
        status = payload.get('status', '')
        total_amount = payload.get('total_amount', 0)
        
        # Hash doğrulama
        hash_str = f"{merchant_oid}{self.merchant_id}{status}{total_amount}"
        calculated_hash = hashlib.sha256(
            (hash_str + self.store_key).encode()
        ).hexdigest()
        
        if calculated_hash == payload.get('hash'):
            return {
                'success': True,
                'transaction_id': merchant_oid,
                'status': 'completed' if status == 'success' else 'failed',
            }
        else:
            return {
                'success': False,
                'error': 'Hash doğrulama başarısız',
            }

