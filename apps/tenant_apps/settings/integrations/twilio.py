"""
Twilio SMS Gateway Entegrasyonu
"""
import requests
from typing import Dict, Optional
from datetime import datetime
from .base import BaseSMSGateway
import logging

logger = logging.getLogger(__name__)


class TwilioSMSGateway(BaseSMSGateway):
    """
    Twilio SMS Gateway Entegrasyonu
    """
    
    def __init__(self, gateway):
        super().__init__(gateway)
        self.account_sid = self.credentials.get('account_sid', '')
        self.auth_token = self.credentials.get('auth_token', '')
        self.api_url = self.endpoint or f"https://api.twilio.com/2010-04-01/Accounts/{self.account_sid}/Messages.json"
    
    def send_sms(self, phone: str, message: str, sender_id: Optional[str] = None) -> Dict:
        """
        Twilio ile SMS gönder
        """
        try:
            # Telefon numarasını doğrula
            is_valid, formatted_phone = self.validate_phone(phone)
            if not is_valid:
                return {
                    'success': False,
                    'message': 'Geçersiz telefon numarası',
                    'error': f'Telefon numarası formatı hatalı: {phone}'
                }
            
            # Gönderen numarası
            from_number = sender_id or self.sender_id
            if not from_number:
                return {
                    'success': False,
                    'message': 'Gönderen numarası belirtilmemiş',
                    'error': 'Twilio için gönderen numarası (sender_id) gereklidir'
                }
            
            # Test modu kontrolü
            if self.is_test_mode:
                self.log_info(f"TEST MODU: SMS gönderilecek - {formatted_phone}")
                return {
                    'success': True,
                    'message_id': f'test_{datetime.now().timestamp()}',
                    'message': 'Test modunda SMS gönderildi (gerçekte gönderilmedi)',
                    'gateway_response': {'test_mode': True}
                }
            
            # Twilio API isteği
            response = requests.post(
                self.api_url,
                auth=(self.account_sid, self.auth_token),
                data={
                    'From': from_number,
                    'To': formatted_phone,
                    'Body': message
                },
                timeout=self.timeout
            )
            
            if response.status_code == 201:
                data = response.json()
                message_sid = data.get('sid', '')
                
                self.log_info(f"SMS gönderildi: {formatted_phone} - Message SID: {message_sid}")
                
                return {
                    'success': True,
                    'message_id': message_sid,
                    'message': 'SMS başarıyla gönderildi',
                    'gateway_response': data
                }
            else:
                error_data = response.json() if response.content else {}
                error_msg = error_data.get('message', f'HTTP {response.status_code}')
                
                self.log_error(f"SMS gönderilemedi: {error_msg}")
                
                return {
                    'success': False,
                    'message': 'SMS gönderilemedi',
                    'error': error_msg,
                    'gateway_response': error_data
                }
        
        except requests.exceptions.RequestException as e:
            self.log_error(f"Twilio API hatası: {str(e)}", e)
            return {
                'success': False,
                'message': 'SMS gönderilirken hata oluştu',
                'error': str(e)
            }
        except Exception as e:
            self.log_error(f"Beklenmeyen hata: {str(e)}", e)
            return {
                'success': False,
                'message': 'Beklenmeyen bir hata oluştu',
                'error': str(e)
            }
    
    def get_balance(self) -> Dict:
        """
        Twilio bakiyesini sorgula
        """
        try:
            if not self.account_sid or not self.auth_token:
                return {
                    'success': False,
                    'message': 'Twilio kimlik bilgileri eksik'
                }
            
            # Twilio Balance API
            balance_url = f"https://api.twilio.com/2010-04-01/Accounts/{self.account_sid}/Balance.json"
            
            response = requests.get(
                balance_url,
                auth=(self.account_sid, self.auth_token),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                data = response.json()
                balance = float(data.get('balance', 0))
                currency = data.get('currency', 'USD')
                
                return {
                    'success': True,
                    'balance': balance,
                    'currency': currency,
                    'message': f'Bakiye: {balance} {currency}'
                }
            else:
                return {
                    'success': False,
                    'message': f'Bakiye sorgulanamadı: HTTP {response.status_code}'
                }
        
        except Exception as e:
            self.log_error(f"Bakiye sorgulama hatası: {str(e)}", e)
            return {
                'success': False,
                'message': f'Bakiye sorgulanırken hata oluştu: {str(e)}'
            }
    
    def get_delivery_status(self, message_id: str) -> Dict:
        """
        Twilio mesaj teslim durumunu sorgula
        """
        try:
            if not self.account_sid or not self.auth_token:
                return {
                    'success': False,
                    'message': 'Twilio kimlik bilgileri eksik'
                }
            
            # Twilio Message API
            message_url = f"https://api.twilio.com/2010-04-01/Accounts/{self.account_sid}/Messages/{message_id}.json"
            
            response = requests.get(
                message_url,
                auth=(self.account_sid, self.auth_token),
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                data = response.json()
                status = data.get('status', 'unknown')
                
                # Twilio status mapping
                status_map = {
                    'queued': 'pending',
                    'sending': 'pending',
                    'sent': 'pending',
                    'delivered': 'delivered',
                    'undelivered': 'failed',
                    'failed': 'failed'
                }
                
                mapped_status = status_map.get(status, 'pending')
                delivered_at = None
                
                if mapped_status == 'delivered' and data.get('date_sent'):
                    try:
                        delivered_at = datetime.fromisoformat(data['date_sent'].replace('Z', '+00:00'))
                    except:
                        pass
                
                return {
                    'success': True,
                    'status': mapped_status,
                    'delivered_at': delivered_at,
                    'message': f'Durum: {status}'
                }
            else:
                return {
                    'success': False,
                    'message': f'Durum sorgulanamadı: HTTP {response.status_code}'
                }
        
        except Exception as e:
            self.log_error(f"Durum sorgulama hatası: {str(e)}", e)
            return {
                'success': False,
                'message': f'Durum sorgulanırken hata oluştu: {str(e)}'
            }




