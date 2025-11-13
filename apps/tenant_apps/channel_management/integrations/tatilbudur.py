"""
Tatilbudur API Entegrasyonu
Türkiye'nin popüler tatil platformu
"""
import requests
import json
from typing import Dict, List, Optional, Any
from decimal import Decimal
from datetime import date
from .base import BaseChannelIntegration


class TatilbudurIntegration(BaseChannelIntegration):
    """Tatilbudur JSON API Entegrasyonu"""
    
    def authenticate(self) -> bool:
        """Tatilbudur kimlik doğrulaması"""
        try:
            api_key = self.credentials.get('api_key')
            partner_id = self.credentials.get('partner_id')
            
            if not api_key or not partner_id:
                self.log_error("Tatilbudur: API Key veya Partner ID eksik")
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
                if data.get('status') == 'success':
                    self.log_info("Tatilbudur: Kimlik doğrulama başarılı")
                    return True
                else:
                    self.log_error(f"Tatilbudur: Kimlik doğrulama başarısız - {data.get('message')}")
                    return False
            else:
                self.log_error(f"Tatilbudur: Kimlik doğrulama hatası - {response.status_code}")
                return False
                
        except Exception as e:
            self.log_error("Tatilbudur: Kimlik doğrulama hatası", e)
            return False
    
    def push_pricing(self, room_id: int, start_date: date, end_date: date,
                    base_price: Decimal, availability: int) -> Dict[str, Any]:
        """Fiyat ve müsaitlik bilgisini Tatilbudur'a gönder"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            channel_price = self.calculate_channel_price(base_price)
            
            url = f"{self.endpoint}/rooms/pricing"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'partner_id': self.credentials.get('partner_id'),
                'room_id': room_id,
                'date_from': start_date.isoformat(),
                'date_to': end_date.isoformat(),
                'price': float(channel_price),
                'available_rooms': availability,
            }
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('status') == 'success':
                    self.log_info(f"Tatilbudur: Fiyat güncellendi - {room_id}")
                    return {
                        'success': True,
                        'message': 'Fiyat başarıyla güncellendi',
                        'data': {'channel_price': channel_price}
                    }
                else:
                    return {'success': False, 'message': data.get('message', 'Bilinmeyen hata')}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Tatilbudur: Fiyat gönderme hatası", e)
            return {'success': False, 'message': str(e)}
    
    def pull_reservations(self, start_date: Optional[date] = None,
                         end_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """Tatilbudur'dan rezervasyonları çek"""
        try:
            if not self.authenticate():
                return []
            
            url = f"{self.endpoint}/reservations"
            params = {
                'api_key': self.credentials.get('api_key'),
                'partner_id': self.credentials.get('partner_id'),
            }
            
            if start_date:
                params['date_from'] = start_date.isoformat()
            if end_date:
                params['date_to'] = end_date.isoformat()
            
            response = requests.get(url, params=params, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('status') == 'success':
                    reservations = []
                    for res_data in data.get('data', []):
                        reservation = {
                            'channel_reservation_id': res_data.get('id'),
                            'channel_reservation_code': res_data.get('booking_code'),
                            'guest_name': res_data.get('customer_name'),
                            'guest_email': res_data.get('customer_email', ''),
                            'guest_phone': res_data.get('customer_phone', ''),
                            'check_in_date': date.fromisoformat(res_data.get('check_in')),
                            'check_out_date': date.fromisoformat(res_data.get('check_out')),
                            'adult_count': res_data.get('adults', 1),
                            'child_count': res_data.get('children', 0),
                            'room_type_name': res_data.get('room_name'),
                            'total_amount': Decimal(str(res_data.get('total_price', 0))),
                            'currency': res_data.get('currency', 'TRY'),
                            'status': res_data.get('status', 'pending').lower(),
                            'channel_data': res_data,
                        }
                        reservations.append(reservation)
                    
                    self.log_info(f"Tatilbudur: {len(reservations)} rezervasyon çekildi")
                    return reservations
                else:
                    self.log_error(f"Tatilbudur: Rezervasyon çekme hatası - {data.get('message')}")
                    return []
            else:
                self.log_error(f"Tatilbudur: Rezervasyon çekme hatası - {response.status_code}")
                return []
                
        except Exception as e:
            self.log_error("Tatilbudur: Rezervasyon çekme hatası", e)
            return []
    
    def confirm_reservation(self, channel_reservation_id: str) -> Dict[str, Any]:
        """Rezervasyonu onayla"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/reservations/{channel_reservation_id}/confirm"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'partner_id': self.credentials.get('partner_id'),
            }
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('status') == 'success':
                    return {'success': True, 'message': 'Rezervasyon onaylandı'}
                else:
                    return {'success': False, 'message': data.get('message', 'Bilinmeyen hata')}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Tatilbudur: Rezervasyon onaylama hatası", e)
            return {'success': False, 'message': str(e)}
    
    def cancel_reservation(self, channel_reservation_id: str, reason: Optional[str] = None) -> Dict[str, Any]:
        """Rezervasyonu iptal et"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/reservations/{channel_reservation_id}/cancel"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'partner_id': self.credentials.get('partner_id'),
            }
            
            if reason:
                payload['reason'] = reason
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('status') == 'success':
                    return {'success': True, 'message': 'Rezervasyon iptal edildi'}
                else:
                    return {'success': False, 'message': data.get('message', 'Bilinmeyen hata')}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Tatilbudur: Rezervasyon iptal hatası", e)
            return {'success': False, 'message': str(e)}
    
    def modify_reservation(self, channel_reservation_id: str, changes: Dict[str, Any]) -> Dict[str, Any]:
        """Rezervasyonu değiştir"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/reservations/{channel_reservation_id}/modify"
            payload = {
                'api_key': self.credentials.get('api_key'),
                'partner_id': self.credentials.get('partner_id'),
            }
            
            if changes.get('check_in_date'):
                payload['check_in'] = changes['check_in_date'].isoformat()
            if changes.get('check_out_date'):
                payload['check_out'] = changes['check_out_date'].isoformat()
            if changes.get('adult_count'):
                payload['adults'] = changes['adult_count']
            
            response = requests.post(url, json=payload, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                if data.get('status') == 'success':
                    return {'success': True, 'message': 'Rezervasyon değiştirildi'}
                else:
                    return {'success': False, 'message': data.get('message', 'Bilinmeyen hata')}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Tatilbudur: Rezervasyon değiştirme hatası", e)
            return {'success': False, 'message': str(e)}

