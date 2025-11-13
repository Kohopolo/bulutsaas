"""
Expedia API Entegrasyonu
"""
import requests
import xml.etree.ElementTree as ET
from typing import Dict, List, Optional, Any
from decimal import Decimal
from datetime import date
from .base import BaseChannelIntegration


class ExpediaIntegration(BaseChannelIntegration):
    """Expedia XML API Entegrasyonu"""
    
    def authenticate(self) -> bool:
        """Expedia kimlik doğrulaması"""
        try:
            username = self.credentials.get('username')
            password = self.credentials.get('password')
            
            if not username or not password:
                self.log_error("Expedia: Kullanıcı adı veya şifre eksik")
                return False
            
            # Test endpoint
            test_url = f"{self.endpoint}/auth/test"
            response = requests.get(
                test_url,
                auth=(username, password),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                self.log_info("Expedia: Kimlik doğrulama başarılı")
                return True
            else:
                self.log_error(f"Expedia: Kimlik doğrulama hatası - {response.status_code}")
                return False
                
        except Exception as e:
            self.log_error("Expedia: Kimlik doğrulama hatası", e)
            return False
    
    def push_pricing(self, room_id: int, start_date: date, end_date: date,
                    base_price: Decimal, availability: int) -> Dict[str, Any]:
        """Fiyat ve müsaitlik bilgisini Expedia'ya gönder"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            channel_price = self.calculate_channel_price(base_price)
            
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <RateUpdateRequest>
                <HotelId>{self.credentials.get('hotel_id')}</HotelId>
                <RoomId>{room_id}</RoomId>
                <DateRange>
                    <Start>{start_date.isoformat()}</Start>
                    <End>{end_date.isoformat()}</End>
                </DateRange>
                <Rate>{channel_price}</Rate>
                <Availability>{availability}</Availability>
            </RateUpdateRequest>"""
            
            url = f"{self.endpoint}/rates/update"
            response = requests.post(
                url,
                data=xml_data,
                headers={'Content-Type': 'application/xml'},
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                if root.find('Status').text == 'Success':
                    self.log_info(f"Expedia: Fiyat güncellendi - {room_id}")
                    return {
                        'success': True,
                        'message': 'Fiyat başarıyla güncellendi',
                        'data': {'channel_price': channel_price}
                    }
                else:
                    error_msg = root.find('Error').text if root.find('Error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Expedia: Fiyat gönderme hatası", e)
            return {'success': False, 'message': str(e)}
    
    def pull_reservations(self, start_date: Optional[date] = None,
                         end_date: Optional[date] = None) -> List[Dict[str, Any]]:
        """Expedia'dan rezervasyonları çek"""
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
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                reservations = []
                
                for booking in root.findall('Booking'):
                    reservation = {
                        'channel_reservation_id': booking.find('BookingId').text,
                        'channel_reservation_code': booking.find('ConfirmationCode').text,
                        'guest_name': f"{booking.find('GuestFirstName').text} {booking.find('GuestLastName').text}",
                        'guest_email': booking.find('GuestEmail').text if booking.find('GuestEmail') is not None else '',
                        'guest_phone': booking.find('GuestPhone').text if booking.find('GuestPhone') is not None else '',
                        'check_in_date': date.fromisoformat(booking.find('CheckIn').text),
                        'check_out_date': date.fromisoformat(booking.find('CheckOut').text),
                        'adult_count': int(booking.find('Adults').text),
                        'child_count': int(booking.find('Children').text) if booking.find('Children') is not None else 0,
                        'room_type_name': booking.find('RoomType').text,
                        'total_amount': Decimal(booking.find('Total').text),
                        'currency': booking.find('Currency').text,
                        'status': booking.find('Status').text.lower(),
                        'channel_data': {'raw_xml': ET.tostring(booking).decode()},
                    }
                    reservations.append(reservation)
                
                self.log_info(f"Expedia: {len(reservations)} rezervasyon çekildi")
                return reservations
            else:
                self.log_error(f"Expedia: Rezervasyon çekme hatası - {response.status_code}")
                return []
                
        except Exception as e:
            self.log_error("Expedia: Rezervasyon çekme hatası", e)
            return []
    
    def confirm_reservation(self, channel_reservation_id: str) -> Dict[str, Any]:
        """Rezervasyonu onayla"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/bookings/{channel_reservation_id}/confirm"
            response = requests.post(
                url,
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                if root.find('Status').text == 'Success':
                    return {'success': True, 'message': 'Rezervasyon onaylandı'}
                else:
                    error_msg = root.find('Error').text if root.find('Error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Expedia: Rezervasyon onaylama hatası", e)
            return {'success': False, 'message': str(e)}
    
    def cancel_reservation(self, channel_reservation_id: str, reason: Optional[str] = None) -> Dict[str, Any]:
        """Rezervasyonu iptal et"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/bookings/{channel_reservation_id}/cancel"
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <CancelRequest>
                <BookingId>{channel_reservation_id}</BookingId>
                {f'<Reason>{reason}</Reason>' if reason else ''}
            </CancelRequest>"""
            
            response = requests.post(
                url,
                data=xml_data,
                headers={'Content-Type': 'application/xml'},
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                if root.find('Status').text == 'Success':
                    return {'success': True, 'message': 'Rezervasyon iptal edildi'}
                else:
                    error_msg = root.find('Error').text if root.find('Error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Expedia: Rezervasyon iptal hatası", e)
            return {'success': False, 'message': str(e)}
    
    def modify_reservation(self, channel_reservation_id: str, changes: Dict[str, Any]) -> Dict[str, Any]:
        """Rezervasyonu değiştir"""
        try:
            if not self.authenticate():
                return {'success': False, 'message': 'Kimlik doğrulama başarısız'}
            
            url = f"{self.endpoint}/bookings/{channel_reservation_id}/modify"
            xml_data = f"""<?xml version="1.0" encoding="UTF-8"?>
            <ModifyRequest>
                <BookingId>{channel_reservation_id}</BookingId>
                {f'<CheckIn>{changes.get("check_in_date")}</CheckIn>' if changes.get('check_in_date') else ''}
                {f'<CheckOut>{changes.get("check_out_date")}</CheckOut>' if changes.get('check_out_date') else ''}
                {f'<Adults>{changes.get("adult_count")}</Adults>' if changes.get('adult_count') else ''}
            </ModifyRequest>"""
            
            response = requests.post(
                url,
                data=xml_data,
                headers={'Content-Type': 'application/xml'},
                auth=(self.credentials.get('username'), self.credentials.get('password')),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                root = ET.fromstring(response.content)
                if root.find('Status').text == 'Success':
                    return {'success': True, 'message': 'Rezervasyon değiştirildi'}
                else:
                    error_msg = root.find('Error').text if root.find('Error') is not None else 'Bilinmeyen hata'
                    return {'success': False, 'message': error_msg}
            else:
                return {'success': False, 'message': f'HTTP {response.status_code}'}
                
        except Exception as e:
            self.log_error("Expedia: Rezervasyon değiştirme hatası", e)
            return {'success': False, 'message': str(e)}

