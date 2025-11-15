"""
Booking.com API Entegrasyonu
"""
import requests
import xml.etree.ElementTree as ET
from typing import Dict, List, Optional, Any
from decimal import Decimal
from datetime import date
from .base import BaseChannelIntegration


class BookingIntegration(BaseChannelIntegration):
    """Booking.com XML API Entegrasyonu"""
    
    def authenticate(self) -> bool:
        """Booking.com kimlik doğrulaması"""
        try:
            username = self.credentials.get('username')
            password = self.credentials.get('password')
            
            if not username or not password:
                self.log_error("Booking.com: Kullanıcı adı veya şifre eksik")
                return False
            
            # Test endpoint
            test_url = f"{self.endpoint}/test"
            response = requests.get(
                test_url,
                auth=(username, password),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                self.log_info("Booking.com: Kimlik doğrulama başarılı")
                return True
            else:
                self.log_error(f"Booking.com: Kimlik doğrulama başarısız - {response.status_code}")
                return False
                
        except Exception as e:
            self.log_error("Booking.com: Kimlik doğrulama hatası", e)
            return False
    
    def push_pricing(self, room_id: int, start_date: date, end_date: date,
                    base_price: Decimal, availability: int) -> Dict[str, Any]:
        """Fiyat ve müsaitlik bilgisini Booking.com'a gönder"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            channel_price = self.calculate_channel_price(base_price)
            
            # XML formatında fiyat gönderimi
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <request>
                <hotel_id>{self.credentials.get('hotel_id')}</hotel_id>
                <room_id>{room_id}</room_id>
                <date_from>{start_date.isoformat()}</date_from>
                <date_to>{end_date.isoformat()}</date_to>
                <price>{channel_price}</price>
                <availability>{availability}</availability>
            </request>"""
            
            url = f"{self.endpoint}/update_availability"
            response = requests.post(
                url,
                data=xml_data,
                headers={'Content-Type': 'application/xml'},
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                # XML response parse et
                root = ET.fromstring(response.content)
                status = root.find('status').text if root.find('status') is not None else 'unknown'
                
                if status == 'success':
                    self.log_info(f"Booking.com: Fiyat güncellendi - {room_id}")
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
            self.log_error("Booking.com: Fiyat gönderme hatası", e)
            return {'success': False, 'message': str(e)}
    
    def pull_reservations(self, start_date: Optional[date] = None,
                         end_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """Booking.com'dan rezervasyonları çek"""
        try:
            if not self.authenticate():
                return []
            
            url = f"{self.endpoint}/get_reservations"
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
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                # XML response parse et
                root = ET.fromstring(response.content)
                reservations = []
                
                for reservation_elem in root.findall('reservation'):
                    reservation = {
                        'channel_reservation_id': reservation_elem.find('reservation_id').text,
                        'channel_reservation_code': reservation_elem.find('confirmation_code').text,
                        'guest_name': f"{reservation_elem.find('guest_first_name').text} {reservation_elem.find('guest_last_name').text}",
                        'guest_email': reservation_elem.find('guest_email').text if reservation_elem.find('guest_email') is not None else '',
                        'guest_phone': reservation_elem.find('guest_phone').text if reservation_elem.find('guest_phone') is not None else '',
                        'check_in_date': date.fromisoformat(reservation_elem.find('check_in').text),
                        'check_out_date': date.fromisoformat(reservation_elem.find('check_out').text),
                        'adult_count': int(reservation_elem.find('adults').text),
                        'child_count': int(reservation_elem.find('children').text) if reservation_elem.find('children') is not None else 0,
                        'room_type_name': reservation_elem.find('room_type').text,
                        'total_amount': Decimal(reservation_elem.find('total_amount').text),
                        'currency': reservation_elem.find('currency').text,
                        'status': reservation_elem.find('status').text.lower(),
                        'channel_data': {
                            'raw_xml': ET.tostring(reservation_elem).decode(),
                        },
                    }
                    reservations.append(reservation)
                
                self.log_info(f"Booking.com: {len(reservations)} rezervasyon çekildi")
                return reservations
            else:
                self.log_error(f"Booking.com: Rezervasyon çekme hatası - {response.status_code}")
                return []
                
        except Exception as e:
            self.log_error("Booking.com: Rezervasyon çekme hatası", e)
            return []
    
    def confirm_reservation(self, channel_reservation_id: str) -> Dict[str, Any]:
        """Rezervasyonu onayla"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/confirm_reservation"
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <request>
                <reservation_id>{channel_reservation_id}</reservation_id>
            </request>"""
            
            response = requests.post(
                url,
                data=xml_data,
                headers={'Content-Type': 'application/xml'},
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                status = root.find('status').text
                
                if status == 'success':
                    return {'success': True, 'message': 'Rezervasyon onaylandı'}
                else:
                    error_msg = root.find('error').text if root.find('error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Booking.com: Rezervasyon onaylama hatası", e)
            return {'success': False, 'message': str(e)}
    
    def cancel_reservation(self, channel_reservation_id: str, reason: Optional[str] = None) -> Dict[str, Any]:
        """Rezervasyonu iptal et"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/cancel_reservation"
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <request>
                <reservation_id>{channel_reservation_id}</reservation_id>
                {f'<reason>{reason}</reason>' if reason else ''}
            </request>"""
            
            response = requests.post(
                url,
                data=xml_data,
                headers={'Content-Type': 'application/xml'},
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                status = root.find('status').text
                
                if status == 'success':
                    return {'success': True, 'message': 'Rezervasyon iptal edildi'}
                else:
                    error_msg = root.find('error').text if root.find('error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Booking.com: Rezervasyon iptal hatası", e)
            return {'success': False, 'message': str(e)}
    
    def modify_reservation(self, channel_reservation_id: str, changes: Dict[str, Any]) -> Dict[str, Any]:
        """Rezervasyonu değiştir"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/modify_reservation"
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <request>
                <reservation_id>{channel_reservation_id}</reservation_id>
                {f'<check_in>{changes.get("check_in_date")}</check_in>' if changes.get('check_in_date') else ''}
                {f'<check_out>{changes.get("check_out_date")}</check_out>' if changes.get('check_out_date') else ''}
                {f'<adults>{changes.get("adult_count")}</adults>' if changes.get('adult_count') else ''}
            </request>"""
            
            response = requests.post(
                url,
                data=xml_data,
                headers={'Content-Type': 'application/xml'},
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                status = root.find('status').text
                
                if status == 'success':
                    return {'success': True, 'message': 'Rezervasyon değiştirildi'}
                else:
                    error_msg = root.find('error').text if root.find('error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Booking.com: Rezervasyon değiştirme hatası", e)
            return {'success': False, 'message': str(e)}





