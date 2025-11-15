"""
Feribot API Entegrasyonları - Base Class
Tüm feribot API entegrasyonları için temel sınıf
"""
from abc import ABC, abstractmethod
from typing import Dict, List, Optional
from decimal import Decimal
from datetime import date, datetime, time


class FerryAPIBase(ABC):
    """
    Feribot API Entegrasyonları için temel sınıf
    Tüm feribot API'leri bu sınıftan türetilir
    """
    
    def __init__(self, config):
        """
        API konfigürasyonu ile başlat
        
        Args:
            config: FerryAPIConfiguration objesi
        """
        self.config = config
        self.api_url = config.api_url
        self.api_key = config.api_key
        self.api_secret = config.api_secret
        self.username = config.username
        self.password = config.password
        self.api_settings = config.api_settings or {}
    
    @abstractmethod
    def authenticate(self) -> Dict:
        """
        API kimlik doğrulama
        Returns: {'success': bool, 'token': str, 'error': str}
        """
        pass
    
    @abstractmethod
    def get_routes(self) -> List[Dict]:
        """
        Rotaları çek
        Returns: [{'id': str, 'name': str, 'departure_port': str, 'arrival_port': str, ...}, ...]
        """
        pass
    
    @abstractmethod
    def get_schedules(self, route_id: str = None, departure_date: date = None) -> List[Dict]:
        """
        Seferleri çek
        Args:
            route_id: Rota ID (opsiyonel)
            departure_date: Kalkış tarihi (opsiyonel)
        Returns: [{'id': str, 'route_id': str, 'departure_date': date, 'departure_time': time, ...}, ...]
        """
        pass
    
    @abstractmethod
    def get_prices(self, schedule_id: str) -> Dict:
        """
        Sefer fiyatlarını çek
        Args:
            schedule_id: Sefer ID
        Returns: {'adult_price': Decimal, 'child_price': Decimal, 'infant_price': Decimal, ...}
        """
        pass
    
    @abstractmethod
    def check_availability(self, schedule_id: str) -> Dict:
        """
        Sefer müsaitliğini kontrol et
        Args:
            schedule_id: Sefer ID
        Returns: {'available_seats': int, 'available_vehicles': int, ...}
        """
        pass
    
    @abstractmethod
    def create_booking(self, schedule_id: str, passengers: List[Dict], vehicle: Dict = None) -> Dict:
        """
        Rezervasyon oluştur
        Args:
            schedule_id: Sefer ID
            passengers: Yolcu listesi [{'first_name': str, 'last_name': str, 'ticket_type': str, ...}, ...]
            vehicle: Araç bilgileri {'type': str, 'plate': str, ...} (opsiyonel)
        Returns: {'success': bool, 'booking_id': str, 'ticket_code': str, 'error': str}
        """
        pass
    
    @abstractmethod
    def cancel_booking(self, booking_id: str) -> Dict:
        """
        Rezervasyonu iptal et
        Args:
            booking_id: Rezervasyon ID
        Returns: {'success': bool, 'refund_amount': Decimal, 'error': str}
        """
        pass
    
    @abstractmethod
    def get_booking_status(self, booking_id: str) -> Dict:
        """
        Rezervasyon durumunu sorgula
        Args:
            booking_id: Rezervasyon ID
        Returns: {'status': str, 'ticket_code': str, 'error': str}
        """
        pass

