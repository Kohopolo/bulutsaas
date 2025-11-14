"""
FerryOS API Entegrasyonu
FerryOS feribot API'si için entegrasyon
"""
import requests
from typing import Dict, List, Optional
from decimal import Decimal
from datetime import date, datetime, time
from .base import FerryAPIBase


class FerryOSAPI(FerryAPIBase):
    """
    FerryOS API Entegrasyonu
    """
    
    def authenticate(self) -> Dict:
        """FerryOS API kimlik doğrulama"""
        try:
            response = requests.post(
                f"{self.api_url}/auth/login",
                json={
                    'username': self.username,
                    'password': self.password,
                },
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                return {
                    'success': True,
                    'token': data.get('token'),
                    'expires_at': data.get('expires_at'),
                }
            else:
                return {
                    'success': False,
                    'error': f'Authentication failed: {response.status_code}',
                }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }
    
    def get_routes(self) -> List[Dict]:
        """FerryOS rotalarını çek"""
        try:
            auth_result = self.authenticate()
            if not auth_result['success']:
                return []
            
            headers = {
                'Authorization': f"Bearer {auth_result['token']}",
            }
            
            response = requests.get(
                f"{self.api_url}/routes",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                routes = []
                for route in data.get('routes', []):
                    routes.append({
                        'id': route.get('id'),
                        'name': route.get('name'),
                        'departure_port': route.get('departure_port'),
                        'arrival_port': route.get('arrival_port'),
                        'departure_city': route.get('departure_city'),
                        'arrival_city': route.get('arrival_city'),
                        'distance': route.get('distance'),
                        'duration': route.get('duration'),
                    })
                return routes
            else:
                return []
        except Exception as e:
            return []
    
    def get_schedules(self, route_id: str = None, departure_date: date = None) -> List[Dict]:
        """FerryOS seferlerini çek"""
        try:
            auth_result = self.authenticate()
            if not auth_result['success']:
                return []
            
            headers = {
                'Authorization': f"Bearer {auth_result['token']}",
            }
            
            params = {}
            if route_id:
                params['route_id'] = route_id
            if departure_date:
                params['departure_date'] = departure_date.isoformat()
            
            response = requests.get(
                f"{self.api_url}/schedules",
                headers=headers,
                params=params,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                schedules = []
                for schedule in data.get('schedules', []):
                    schedules.append({
                        'id': schedule.get('id'),
                        'route_id': schedule.get('route_id'),
                        'ferry_id': schedule.get('ferry_id'),
                        'departure_date': datetime.fromisoformat(schedule.get('departure_date')).date() if schedule.get('departure_date') else None,
                        'departure_time': datetime.fromisoformat(schedule.get('departure_time')).time() if schedule.get('departure_time') else None,
                        'arrival_date': datetime.fromisoformat(schedule.get('arrival_date')).date() if schedule.get('arrival_date') else None,
                        'arrival_time': datetime.fromisoformat(schedule.get('arrival_time')).time() if schedule.get('arrival_time') else None,
                        'available_seats': schedule.get('available_seats', 0),
                        'available_vehicles': schedule.get('available_vehicles', 0),
                    })
                return schedules
            else:
                return []
        except Exception as e:
            return []
    
    def get_prices(self, schedule_id: str) -> Dict:
        """FerryOS sefer fiyatlarını çek"""
        try:
            auth_result = self.authenticate()
            if not auth_result['success']:
                return {}
            
            headers = {
                'Authorization': f"Bearer {auth_result['token']}",
            }
            
            response = requests.get(
                f"{self.api_url}/schedules/{schedule_id}/prices",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                return {
                    'adult_price': Decimal(str(data.get('adult_price', 0))),
                    'child_price': Decimal(str(data.get('child_price', 0))),
                    'infant_price': Decimal(str(data.get('infant_price', 0))),
                    'student_price': Decimal(str(data.get('student_price', 0))) if data.get('student_price') else None,
                    'senior_price': Decimal(str(data.get('senior_price', 0))) if data.get('senior_price') else None,
                    'car_price': Decimal(str(data.get('car_price', 0))),
                    'motorcycle_price': Decimal(str(data.get('motorcycle_price', 0))),
                    'van_price': Decimal(str(data.get('van_price', 0))),
                    'truck_price': Decimal(str(data.get('truck_price', 0))),
                    'bus_price': Decimal(str(data.get('bus_price', 0))),
                    'caravan_price': Decimal(str(data.get('caravan_price', 0))),
                }
            else:
                return {}
        except Exception as e:
            return {}
    
    def check_availability(self, schedule_id: str) -> Dict:
        """FerryOS sefer müsaitliğini kontrol et"""
        try:
            auth_result = self.authenticate()
            if not auth_result['success']:
                return {}
            
            headers = {
                'Authorization': f"Bearer {auth_result['token']}",
            }
            
            response = requests.get(
                f"{self.api_url}/schedules/{schedule_id}/availability",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                return {
                    'available_seats': data.get('available_seats', 0),
                    'available_vehicles': data.get('available_vehicles', 0),
                    'total_seats': data.get('total_seats', 0),
                    'total_vehicles': data.get('total_vehicles', 0),
                }
            else:
                return {}
        except Exception as e:
            return {}
    
    def create_booking(self, schedule_id: str, passengers: List[Dict], vehicle: Dict = None) -> Dict:
        """FerryOS rezervasyon oluştur"""
        try:
            auth_result = self.authenticate()
            if not auth_result['success']:
                return {'success': False, 'error': 'Authentication failed'}
            
            headers = {
                'Authorization': f"Bearer {auth_result['token']}",
                'Content-Type': 'application/json',
            }
            
            payload = {
                'schedule_id': schedule_id,
                'passengers': passengers,
            }
            if vehicle:
                payload['vehicle'] = vehicle
            
            response = requests.post(
                f"{self.api_url}/bookings",
                headers=headers,
                json=payload,
                timeout=30
            )
            
            if response.status_code == 200 or response.status_code == 201:
                data = response.json()
                return {
                    'success': True,
                    'booking_id': data.get('booking_id'),
                    'ticket_code': data.get('ticket_code'),
                    'total_amount': Decimal(str(data.get('total_amount', 0))),
                }
            else:
                return {
                    'success': False,
                    'error': f'Booking failed: {response.status_code} - {response.text}',
                }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }
    
    def cancel_booking(self, booking_id: str) -> Dict:
        """FerryOS rezervasyonu iptal et"""
        try:
            auth_result = self.authenticate()
            if not auth_result['success']:
                return {'success': False, 'error': 'Authentication failed'}
            
            headers = {
                'Authorization': f"Bearer {auth_result['token']}",
            }
            
            response = requests.post(
                f"{self.api_url}/bookings/{booking_id}/cancel",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                return {
                    'success': True,
                    'refund_amount': Decimal(str(data.get('refund_amount', 0))),
                }
            else:
                return {
                    'success': False,
                    'error': f'Cancellation failed: {response.status_code}',
                }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }
    
    def get_booking_status(self, booking_id: str) -> Dict:
        """FerryOS rezervasyon durumunu sorgula"""
        try:
            auth_result = self.authenticate()
            if not auth_result['success']:
                return {'success': False, 'error': 'Authentication failed'}
            
            headers = {
                'Authorization': f"Bearer {auth_result['token']}",
            }
            
            response = requests.get(
                f"{self.api_url}/bookings/{booking_id}",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                return {
                    'success': True,
                    'status': data.get('status'),
                    'ticket_code': data.get('ticket_code'),
                }
            else:
                return {
                    'success': False,
                    'error': f'Status check failed: {response.status_code}',
                }
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }

