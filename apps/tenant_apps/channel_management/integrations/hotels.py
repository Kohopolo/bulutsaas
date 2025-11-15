"""
Hotels.com API Entegrasyonu
"""
import requests
import xml.etree.ElementTree as ET
from typing import Dict, List, Optional, Any
from decimal import Decimal
from datetime import date
from .base import BaseChannelIntegration


class HotelsIntegration(BaseChannelIntegration):
    """Hotels.com XML API Entegrasyonu"""
    
    def authenticate(self) -> bool:
        """Hotels.com kimlik doğrulaması"""
        try:
            api_key = self.credentials.get('api_key')
            hotel_id = self.credentials.get('hotel_id')
            
            if not api_key or not hotel_id:
                self.log_error("Hotels.com: API Key veya Hotel ID eksik")
                return False
            
            # Test endpoint
            test_url = f"{self.endpoint}/auth"
            response = requests.get(
                test_url,
                headers={'X-API-Key': api_key},
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                self.log_info("Hotels.com: Kimlik doğrulama başarılı")
                return True
            else:
                self.log_error(f"Hotels.com: Kimlik doğrulama hatası - {response.status_code}")
                return False
                
        except Exception as e:
            self.log_error("Hotels.com: Kimlik doğrulama hatası", e)
            return False
    
    def push_pricing(self, room_id: int, start_date: date, end_date: date,
                    base_price: Decimal, availability: int) -> Dict[str, Any]:
        """Fiyat ve müsaitlik bilgisini Hotels.com'a gönder"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            channel_price = self.calculate_channel_price(base_price)
            
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <inventory>
                <hotel_id>{self.credentials.get('hotel_id')}</hotel_id>
                <room_id>{room_id}</room_id>
                <date_from>{start_date.isoformat()}</date_from>
                <date_to>{end_date.isoformat()}</date_to>
                <rate>{channel_price}</rate>
                <available>{availability}</available>
            </inventory>"""
            
            url = f"{self.endpoint}/inventory/update"
            response = requests.post(
                url,
                data=xml_data,
                headers={
                    'Content-Type': 'application/xml',
                    'X-API-Key': self.credentials.get('api_key')
                },
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                if root.find('status').text == 'success':
                    self.log_info(f"Hotels.com: Fiyat güncellendi - {room_id}")
                    return {
                        'success': True,
                        'message': 'Fiyat başarıyla güncellendi',
                        'data': {'channel_price': channel_price}
                    }
                else:
                    error_msg = root.find('error').text if root.find('error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Hotels.com: Fiyat gönderme hatası", e)
            return {'success': False, 'message': str(e)}
    
    def pull_reservations(self, start_date: Optional[date] = None,
                         end_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """Hotels.com'dan rezervasyonları çek"""
        try:
            if not self.authenticate():
                return []
            
            url = f"{self.endpoint}/bookings"
            params = {
                'hotel_id': self.credentials.get('hotel_id'),
            }
            
            if start_date:
                params['date_from'] = start_date.isoformat()
            if end_date:
                params['date_to'] = end_date.isoformat()
            
            response = requests.get(
                url,
                params=params,
                headers={'X-API-Key': self.credentials.get('api_key')},
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                reservations = []
                
                for booking in root.findall('booking'):
                    reservation = {
                        'channel_reservation_id': booking.find('booking_id').text,
                        'channel_reservation_code': booking.find('confirmation').text,
                        'guest_name': booking.find('guest_name').text,
                        'guest_email': booking.find('guest_email').text if booking.find('guest_email') is not None else '',
                        'guest_phone': booking.find('guest_phone').text if booking.find('guest_phone') is not None else '',
                        'check_in_date': date.fromisoformat(booking.find('check_in').text),
                        'check_out_date': date.fromisoformat(booking.find('check_out').text),
                        'adult_count': int(booking.find('adults').text),
                        'child_count': int(booking.find('children').text) if booking.find('children') is not None else 0,
                        'room_type_name': booking.find('room_type').text,
                        'total_amount': Decimal(booking.find('total').text),
                        'currency': booking.find('currency').text,
                        'status': booking.find('status').text.lower(),
                        'channel_data': {'raw_xml': ET.tostring(booking).decode()},
                    }
                    reservations.append(reservation)
                
                self.log_info(f"Hotels.com: {len(reservations)} rezervasyon çekildi")
                return reservations
            else:
                self.log_error(f"Hotels.com: Rezervasyon çekme hatası - {response.status_code}")
                return []
                
        except Exception as e:
            self.log_error("Hotels.com: Rezervasyon çekme hatası", e)
            return []
    
    def confirm_reservation(self, channel_reservation_id: str) -> Dict[str, Any]:
        """Rezervasyonu onayla"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/bookings/{channel_reservation_id}/confirm"
            response = requests.post(
                url,
                headers={'X-API-Key': self.credentials.get('api_key')},
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                if root.find('status').text == 'success':
                    return {'success': True, 'message': 'Rezervasyon onaylandı'}
                else:
                    error_msg = root.find('error').text if root.find('error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Hotels.com: Rezervasyon onaylama hatası", e)
            return {'success': False, 'message': str(e)}
    
    def cancel_reservation(self, channel_reservation_id: str, reason: Optional[str] = None) -> Dict[str, Any]:
        """Rezervasyonu iptal et"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/bookings/{channel_reservation_id}/cancel"
            response = requests.post(
                url,
                headers={'X-API-Key': self.credentials.get('api_key')},
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                if root.find('status').text == 'success':
                    return {'success': True, 'message': 'Rezervasyon iptal edildi'}
                else:
                    error_msg = root.find('error').text if root.find('error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Hotels.com: Rezervasyon iptal hatası", e)
            return {'success': False, 'message': str(e)}
    
    def modify_reservation(self, channel_reservation_id: str, changes: Dict[str, Any]) -> Dict[str, Any]:
        """Rezervasyonu değiştir"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/bookings/{channel_reservation_id}/modify"
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <modification>
                {f'<check_in>{changes.get("check_in_date")}</check_in>' if changes.get('check_in_date') else ''}
                {f'<check_out>{changes.get("check_out_date")}</check_out>' if changes.get('check_out_date') else ''}
                {f'<adults>{changes.get("adult_count")}</adults>' if changes.get('adult_count') else ''}
            </modification>"""
            
            response = requests.post(
                url,
                data=xml_data,
                headers={
                    'Content-Type': 'application/xml',
                    'X-API-Key': self.credentials.get('api_key')
                },
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                if root.find('status').text == 'success':
                    return {'success': True, 'message': 'Rezervasyon değiştirildi'}
                else:
                    error_msg = root.find('error').text if root.find('error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Hotels.com: Rezervasyon değiştirme hatası", e)
            return {'success': False, 'message': str(e)}





