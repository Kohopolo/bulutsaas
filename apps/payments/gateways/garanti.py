"""
Garanti Sanal Pos Gateway Integration
"""
import hashlib
import base64
import requests
from decimal import Decimal
from typing import Dict, Any, Optional
from .base import BasePaymentGateway


class GarantiGateway(BasePaymentGateway):
    """Garanti Bankası Sanal Pos Gateway"""
    
    API_URL = "https://sanalposprov.garanti.com.tr"
    TEST_API_URL = "https://sanalposprovtest.garanti.com.tr"
    
    def __init__(self, config: Dict[str, Any]):
        super().__init__(config)
        self.base_url = self.TEST_API_URL if self.is_test_mode else self.API_URL
    
    def _generate_hash(self, data: Dict[str, Any]) -> str:
        """Generate hash for Garanti"""
        hash_data = f"{self.store_key}{data.get('oid', '')}{data.get('amount', '')}{data.get('email', '')}"
        return hashlib.sha256(hash_data.encode()).hexdigest().upper()
    
    def create_payment(self, amount: Decimal, currency: str, order_id: str,
                      customer_info: Dict[str, Any], **kwargs) -> Dict[str, Any]:
        """Create payment with Garanti"""
        data = {
            'clientid': self.merchant_id,
            'storetype': '3d_pay',
            'amount': str(amount),
            'currency': currency,
            'oid': order_id,
            'okUrl': kwargs.get('success_url', ''),
            'failUrl': kwargs.get('fail_url', ''),
            'email': customer_info.get('email', ''),
            'fname': customer_info.get('name', ''),
            'lname': customer_info.get('surname', ''),
            'tel': customer_info.get('phone', ''),
            'rnd': kwargs.get('rnd', ''),
            'hash': '',
        }
        
        # Hash oluştur
        data['hash'] = self._generate_hash(data)
        
        # 3D Secure form URL
        payment_url = f"{self.base_url}/fim/est3Dgate"
        
        return {
            'success': True,
            'payment_url': payment_url,
            'form_data': data,
            'transaction_id': order_id,
        }
    
    def verify_payment(self, transaction_id: str, **kwargs) -> Dict[str, Any]:
        """Verify payment status"""
        hash_str = f"{kwargs.get('oid')}{kwargs.get('Response')}{kwargs.get('ProcReturnCode')}{kwargs.get('amount')}"
        calculated_hash = hashlib.sha256(
            (hash_str + self.store_key).encode()
        ).hexdigest().upper()
        
        if calculated_hash == kwargs.get('HASH'):
            status = 'completed' if kwargs.get('Response') == 'Approved' else 'failed'
            return {
                'success': True,
                'status': status,
                'amount': Decimal(kwargs.get('amount', 0)),
                'transaction_id': kwargs.get('oid', ''),
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
            'error': 'Garanti iade API entegrasyonu yapılacak',
        }
    
    def handle_webhook(self, payload: Dict[str, Any], headers: Dict[str, Any]) -> Dict[str, Any]:
        """Handle Garanti webhook (callback)"""
        return self.verify_payment(
            payload.get('oid', ''),
            **payload
        )

