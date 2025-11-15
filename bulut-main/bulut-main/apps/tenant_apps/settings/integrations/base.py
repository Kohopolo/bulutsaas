"""
Base SMS Gateway Integration
Tüm SMS gateway entegrasyonları için temel sınıf
"""
from abc import ABC, abstractmethod
from typing import Dict, Optional, List
import logging

logger = logging.getLogger(__name__)


class BaseSMSGateway(ABC):
    """
    SMS Gateway Temel Sınıfı
    Tüm SMS gateway entegrasyonları bu sınıftan türetilir
    """
    
    def __init__(self, gateway):
        """
        Args:
            gateway: SMSGateway instance
        """
        self.gateway = gateway
        self.credentials = gateway.api_credentials
        self.endpoint = gateway.api_endpoint
        self.timeout = gateway.api_timeout
        self.retry_count = gateway.api_retry_count
        self.sender_id = gateway.sender_id
        self.is_test_mode = gateway.is_test_mode
    
    @abstractmethod
    def send_sms(self, phone: str, message: str, sender_id: Optional[str] = None) -> Dict:
        """
        SMS gönder
        
        Args:
            phone: Alıcı telefon numarası (ülke kodu ile birlikte)
            message: Gönderilecek mesaj metni
            sender_id: Gönderen ID (opsiyonel, gateway'in sender_id'si kullanılır)
        
        Returns:
            {
                'success': bool,
                'message_id': str (gateway'den dönen mesaj ID),
                'message': str,
                'error': str (hata varsa),
                'gateway_response': dict (gateway'den dönen ham yanıt)
            }
        """
        pass
    
    @abstractmethod
    def get_balance(self) -> Dict:
        """
        Gateway bakiyesini sorgula
        
        Returns:
            {
                'success': bool,
                'balance': float,
                'currency': str,
                'message': str
            }
        """
        pass
    
    @abstractmethod
    def get_delivery_status(self, message_id: str) -> Dict:
        """
        Mesaj teslim durumunu sorgula
        
        Args:
            message_id: Gateway'den dönen mesaj ID
        
        Returns:
            {
                'success': bool,
                'status': str ('delivered', 'pending', 'failed'),
                'delivered_at': datetime (varsa),
                'message': str
            }
        """
        pass
    
    def validate_phone(self, phone: str) -> tuple[bool, str]:
        """
        Telefon numarasını doğrula ve formatla
        
        Args:
            phone: Telefon numarası
        
        Returns:
            (is_valid, formatted_phone)
        """
        # Temel temizleme
        phone = phone.strip().replace(' ', '').replace('-', '').replace('(', '').replace(')', '')
        
        # Ülke kodu kontrolü
        if not phone.startswith('+'):
            if phone.startswith('0'):
                # Türkiye için 0 ile başlayan numaraları +90'a çevir
                phone = '+90' + phone[1:]
            else:
                # Ülke kodu yoksa varsayılan ekle
                phone = self.gateway.default_country_code + phone
        
        # Minimum uzunluk kontrolü
        if len(phone) < 10:
            return False, phone
        
        return True, phone
    
    def log_error(self, message: str, error: Exception = None):
        """Hata logla"""
        logger.error(f"[{self.gateway.name}] {message}", exc_info=error)
    
    def log_info(self, message: str):
        """Bilgi logla"""
        logger.info(f"[{self.gateway.name}] {message}")

