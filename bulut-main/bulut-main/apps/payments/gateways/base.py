"""
Base Payment Gateway Interface
Tüm ödeme gateway'leri bu sınıftan türetilir
"""
from abc import ABC, abstractmethod
from decimal import Decimal
from typing import Dict, Any, Optional


class BasePaymentGateway(ABC):
    """Base Payment Gateway Abstract Class"""
    
    def __init__(self, config: Dict[str, Any]):
        """
        Initialize gateway with configuration
        
        Args:
            config: Gateway configuration dictionary
                - api_key: API key
                - secret_key: Secret key
                - merchant_id: Merchant ID
                - store_key: Store key (if needed)
                - is_test_mode: Test mode flag
        """
        self.config = config
        self.api_key = config.get('api_key', '')
        self.secret_key = config.get('secret_key', '')
        self.merchant_id = config.get('merchant_id', '')
        self.store_key = config.get('store_key', '')
        self.is_test_mode = config.get('is_test_mode', True)
    
    @abstractmethod
    def create_payment(self, amount: Decimal, currency: str, order_id: str, 
                      customer_info: Dict[str, Any], **kwargs) -> Dict[str, Any]:
        """
        Create payment request
        
        Returns:
            {
                'success': bool,
                'payment_url': str,  # 3D Secure için redirect URL
                'transaction_id': str,
                'error': str (if failed)
            }
        """
        pass
    
    @abstractmethod
    def verify_payment(self, transaction_id: str, **kwargs) -> Dict[str, Any]:
        """
        Verify payment status
        
        Returns:
            {
                'success': bool,
                'status': str,  # completed, failed, pending
                'amount': Decimal,
                'error': str (if failed)
            }
        """
        pass
    
    @abstractmethod
    def refund(self, transaction_id: str, amount: Optional[Decimal] = None, 
               **kwargs) -> Dict[str, Any]:
        """
        Refund payment
        
        Args:
            transaction_id: Original transaction ID
            amount: Refund amount (None for full refund)
        
        Returns:
            {
                'success': bool,
                'refund_id': str,
                'error': str (if failed)
            }
        """
        pass
    
    @abstractmethod
    def handle_webhook(self, payload: Dict[str, Any], headers: Dict[str, Any]) -> Dict[str, Any]:
        """
        Handle webhook from gateway
        
        Returns:
            {
                'success': bool,
                'transaction_id': str,
                'status': str,
                'error': str (if failed)
            }
        """
        pass
