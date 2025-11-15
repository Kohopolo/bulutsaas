"""
Base Channel Integration
Tüm kanal entegrasyonları için temel sınıf
"""
from abc import ABC, abstractmethod
from typing import Dict, List, Optional, Any
from decimal import Decimal
from datetime import date, datetime
import logging

logger = logging.getLogger(__name__)


class BaseChannelIntegration(ABC):
    """
    Kanal Entegrasyonu Temel Sınıfı
    Tüm kanal entegrasyonları bu sınıftan türetilir
    """
    
    def __init__(self, configuration):
        """
        Args:
            configuration: ChannelConfiguration instance
        """
        self.configuration = configuration
        self.credentials = configuration.api_credentials
        self.endpoint = configuration.api_endpoint or configuration.template.api_endpoint_template
        self.timeout = configuration.api_timeout
        self.retry_count = configuration.api_retry_count
        self.is_test_mode = configuration.is_test_mode
    
    @abstractmethod
    def authenticate(self) -> bool:
        """API kimlik doğrulaması yap"""
        pass
    
    @abstractmethod
    def push_pricing(self, room_id: int, start_date: date, end_date: date, 
                    base_price: Decimal, availability: int) -> Dict[str, Any]:
        """
        Fiyat ve müsaitlik bilgisini kanala gönder
        
        Returns:
            {
                'success': bool,
                'message': str,
                'data': dict
            }
        """
        pass
    
    @abstractmethod
    def pull_reservations(self, start_date: Optional[date] = None, 
                         end_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """
        Kanaldan rezervasyonları çek
        
        Returns:
            [
                {
                    'channel_reservation_id': str,
                    'channel_reservation_code': str,
                    'guest_name': str,
                    'guest_email': str,
                    'guest_phone': str,
                    'check_in_date': date,
                    'check_out_date': date,
                    'adult_count': int,
                    'child_count': int,
                    'room_type_name': str,
                    'total_amount': Decimal,
                    'currency': str,
                    'status': str,
                    'channel_data': dict,
                },
                ...
            ]
        """
        pass
    
    @abstractmethod
    def confirm_reservation(self, channel_reservation_id: str) -> Dict[str, Any]:
        """Rezervasyonu onayla"""
        pass
    
    @abstractmethod
    def cancel_reservation(self, channel_reservation_id: str, reason: Optional[str] = None) -> Dict[str, Any]:
        """Rezervasyonu iptal et"""
        pass
    
    @abstractmethod
    def modify_reservation(self, channel_reservation_id: str, changes: Dict[str, Any]) -> Dict[str, Any]:
        """Rezervasyonu değiştir"""
        pass
    
    def calculate_channel_price(self, base_price: Decimal) -> Decimal:
        """Temel fiyattan kanal fiyatını hesapla (markup dahil)"""
        price = base_price
        
        # Yüzde artış
        if self.configuration.price_markup_percent > 0:
            price = price + (price * self.configuration.price_markup_percent / 100)
        
        # Sabit tutar artış
        if self.configuration.price_markup_amount > 0:
            price = price + self.configuration.price_markup_amount
        
        return price.quantize(Decimal('0.01'))
    
    def calculate_commission(self, amount: Decimal) -> Decimal:
        """Komisyon tutarını hesapla"""
        commission_rate = self.configuration.get_effective_commission_rate()
        if commission_rate > 0:
            return (amount * commission_rate / 100).quantize(Decimal('0.01'))
        return Decimal('0.00')
    
    def log_error(self, message: str, error: Exception = None):
        """Hata logla"""
        logger.error(f"[{self.configuration.name}] {message}", exc_info=error)
    
    def log_info(self, message: str):
        """Bilgi logla"""
        logger.info(f"[{self.configuration.name}] {message}")

