"""
Trivago API Entegrasyonu
Meta arama motoru - Sadece fiyat ve müsaitlik gönderimi
"""
import requests
import json
from typing import Dict, List, Optional, Any
from decimal import Decimal
from datetime import date
from .base import BaseChannelIntegration


class TrivagoIntegration(BaseChannelIntegration):
    """Trivago JSON API Entegrasyonu (Sadece fiyat/müsaitlik)"""
    
    def authenticate(self) -> bool:
        """Trivago kimlik doğrulaması"""
        try:
            api_key = self.credentials.get('api_key')
            partner_id = self.credentials.get('partner_id')
            
            if not api_key or not partner_id:
                self.log_error("Trivago: API Key veya Partner ID eksik")
                return False
            
            # Test endpoint
            test_url = f"{self.endpoint}/auth/verify"
            response = requests.post(
                test_url,
                json={'api_key': api_key, 'partner_id': partner_id},
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                data = response.json()
                if data.get('valid'):
                    self.log_info("Trivago: Kimlik doğrulama başarılı")
                    return True
                else:
                    self.log_error("Trivago: Kimlik doğrulama başarısız")
                    return False
            else:
                self.log_error(f"Trivago: Kimlik doğrulama hatası - {response.status_code}")
                return False
                
        except Exception as e:
            self.log_error("Trivago: Kimlik doğrulama hatası", e)
            return False
    
    def push_pricing(self, room_id: int, start_date: date, end_date: date,
                    base_price: Decimal, availability: int) -> Dict[str, Any]:
        """Fiyat ve müsaitlik bilgisini Trivago'ya gönder"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            channel_price = self.calculate_channel_price(base_price)
            
            url = f"{self.endpoint}/rates/update"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'partner_id': self.credentials.get('partner_id'),
                'room_id': room_id,
                'date_from': start_date.isoformat(),
                'date_to': end_date.isoformat(),
                'rate': float(channel_price),
                'available': availability > 0,
            }
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    self.log_info(f"Trivago: Fiyat güncellendi - {room_id}")
                    return {
                        'success': True,
                        'message': 'Fiyat başarıyla güncellendi',
                        'data': {'channel_price': channel_price}
                    }
                else:
                    return {'success': False, 'message': data.get('error', 'Bilinmeyen hata')}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Trivago: Fiyat gönderme hatası", e)
            return {'success': False, 'message': str(e)}
    
    def pull_reservations(self, start_date: Optional[date] = None,
                         end_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """Trivago meta arama motoru - rezervasyon çekme desteklenmiyor"""
        self.log_info("Trivago: Rezervasyon çekme desteklenmiyor (meta arama motoru)")
        return []
    
    def confirm_reservation(self, channel_reservation_id: str) -> Dict[str, Any]:
        """Trivago meta arama motoru - rezervasyon onaylama desteklenmiyor"""
        return {'success': False, 'message': 'Trivago meta arama motoru - rezervasyon işlemleri desteklenmiyor'}
    
    def cancel_reservation(self, channel_reservation_id: str, reason: Optional[str] = None) -> Dict[str, Any]:
        """Trivago meta arama motoru - rezervasyon iptal desteklenmiyor"""
        return {'success': False, 'message': 'Trivago meta arama motoru - rezervasyon işlemleri desteklenmiyor'}
    
    def modify_reservation(self, channel_reservation_id: str, changes: Dict[str, Any]) -> Dict[str, Any]:
        """Trivago meta arama motoru - rezervasyon değiştirme desteklenmiyor"""
        return {'success': False, 'message': 'Trivago meta arama motoru - rezervasyon işlemleri desteklenmiyor'}

