"""
Tatilsepeti API Entegrasyonu
"""
import requests
import json
from typing import Dict, List, Optional, Any
from decimal import Decimal
from datetime import date
from .base import BaseChannelIntegration


class TatilsepetiIntegration(BaseChannelIntegration):
    """Tatilsepeti JSON API Entegrasyonu"""
    
    def authenticate(self) -> bool:
        """Tatilsepeti kimlik doğrulaması"""
        try:
            api_key = self.credentials.get('api_key')
            api_secret = self.credentials.get('api_secret')
            
            if not api_key or not api_secret:
                self.log_error("Tatilsepeti: API Key veya Secret eksik")
                return False
            
            # Test endpoint
            test_url = f"{self.endpoint}/auth/validate"
            response = requests.post(
                test_url,
                json={'api_key': api_key, 'api_secret': api_secret},
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                data = response.json()
                if data.get('valid'):
                    self.log_info("Tatilsepeti: Kimlik doğrulama başarılı")
                    return True
                else:
                    self.log_error("Tatilsepeti: Kimlik doğrulama başarısız")
                    return False
            else:
                self.log_error(f"Tatilsepeti: Kimlik doğrulama hatası - {response.status_code}")
                return False
                
        except Exception as e:
            self.log_error("Tatilsepeti: Kimlik doğrulama hatası", e)
            return False
    
    def push_pricing(self, room_id: int, start_date: date, end_date: date,
                    base_price: Decimal, availability: int) -> Dict[str, Any]:
        """Fiyat ve müsaitlik bilgisini Tatilsepeti'ne gönder"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            channel_price = self.calculate_channel_price(base_price)
            
            url = f"{self.endpoint}/pricing/update"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'api_secret': self.credentials.get('api_secret'),
                'room_id': room_id,
                'date_from': start_date.isoformat(),
                'date_to': end_date.isoformat(),
                'price': float(channel_price),
                'availability': availability,
            }
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    self.log_info(f"Tatilsepeti: Fiyat güncellendi - {room_id}")
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
            self.log_error("Tatilsepeti: Fiyat gönderme hatası", e)
            return {'success': False, 'message': str(e)}
    
    def pull_reservations(self, start_date: Optional[date] = None,
                         end_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """Tatilsepeti'nden rezervasyonları çek"""
        try:
            if not self.authenticate():
                return []
            
            url = f"{self.endpoint}/reservations"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'api_secret': self.credentials.get('api_secret'),
            }
            
            if start_date:
                payload['date_from'] = start_date.isoformat()
            if end_date:
                payload['date_to'] = end_date.isoformat()
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    reservations = []
                    for res_data in data.get('reservations', []):
                        reservation = {
                            'channel_reservation_id': res_data.get('reservation_id'),
                            'channel_reservation_code': res_data.get('booking_code'),
                            'guest_name': res_data.get('guest_name'),
                            'guest_email': res_data.get('guest_email', ''),
                            'guest_phone': res_data.get('guest_phone', ''),
                            'check_in_date': date.fromisoformat(res_data.get('check_in_date')),
                            'check_out_date': date.fromisoformat(res_data.get('check_out_date')),
                            'adult_count': res_data.get('adults', 1),
                            'child_count': res_data.get('children', 0),
                            'room_type_name': res_data.get('room_type'),
                            'total_amount': Decimal(str(res_data.get('total', 0))),
                            'currency': res_data.get('currency', 'TRY'),
                            'status': res_data.get('status', 'pending').lower(),
                            'channel_data': res_data,
                        }
                        reservations.append(reservation)
                    
                    self.log_info(f"Tatilsepeti: {len(reservations)} rezervasyon çekildi")
                    return reservations
                else:
                    self.log_error(f"Tatilsepeti: Rezervasyon çekme hatası - {data.get('error')}")
                    return []
            else:
                self.log_error(f"Tatilsepeti: Rezervasyon çekme hatası - {response.status_code}")
                return []
                
        except Exception as e:
            self.log_error("Tatilsepeti: Rezervasyon çekme hatası", e)
            return []
    
    def confirm_reservation(self, channel_reservation_id: str) -> Dict[str, Any]:
        """Rezervasyonu onayla"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/reservations/{channel_reservation_id}/confirm"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'api_secret': self.credentials.get('api_secret'),
            }
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    return {'success': True, 'message': 'Rezervasyon onaylandı'}
                else:
                    return {'success': False, 'message': data.get('error', 'Bilinmeyen hata')}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Tatilsepeti: Rezervasyon onaylama hatası", e)
            return {'success': False, 'message': str(e)}
    
    def cancel_reservation(self, channel_reservation_id: str, reason: Optional[str] = None) -> Dict[str, Any]:
        """Rezervasyonu iptal et"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/reservations/{channel_reservation_id}/cancel"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'api_secret': self.credentials.get('api_secret'),
            }
            
            if reason:
                payload['reason'] = reason
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    return {'success': True, 'message': 'Rezervasyon iptal edildi'}
                else:
                    return {'success': False, 'message': data.get('error', 'Bilinmeyen hata')}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Tatilsepeti: Rezervasyon iptal hatası", e)
            return {'success': False, 'message': str(e)}
    
    def modify_reservation(self, channel_reservation_id: str, changes: Dict[str, Any]) -> Dict[str, Any]:
        """Rezervasyonu değiştir"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/reservations/{channel_reservation_id}/modify"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'api_secret': self.credentials.get('api_secret'),
            }
            
            if changes.get('check_in_date'):
                payload['check_in_date'] = changes['check_in_date'].isoformat()
            if changes.get('check_out_date'):
                payload['check_out_date'] = changes['check_out_date'].isoformat()
            if changes.get('adult_count'):
                payload['adults'] = changes['adult_count']
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('success'):
                    return {'success': True, 'message': 'Rezervasyon değiştirildi'}
                else:
                    return {'success': False, 'message': data.get('error', 'Bilinmeyen hata')}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Tatilsepeti: Rezervasyon değiştirme hatası", e)
            return {'success': False, 'message': str(e)}





