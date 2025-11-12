"""
Verimor SMS Sağlayıcısı
"""
import requests
import json
from typing import Dict, Any, List
from .base import BaseNotificationProvider


class VerimorProvider(BaseNotificationProvider):
    """
    Verimor SMS Sağlayıcısı
    Verimor API entegrasyonu
    """
    
    def __init__(self, config: Dict[str, Any]):
        super().__init__(config)
        self.api_url = 'https://api.verimor.com.tr/v2/send.json' if not self.is_test_mode else 'https://api.verimor.com.tr/v2/send.json'
        self.username = config.get('sms_username', '')
        self.password = config.get('sms_password', '')
        self.header = config.get('sms_header', '')
    
    def send(self, recipient: str, subject: str, content: str, **kwargs) -> Dict[str, Any]:
        """SMS gönder"""
        try:
            # Telefon numarasını temizle
            phone = recipient.replace(' ', '').replace('-', '').replace('(', '').replace(')', '')
            if phone.startswith('0'):
                phone = '90' + phone[1:]
            elif not phone.startswith('90'):
                phone = '90' + phone
            
            data = {
                'username': self.username,
                'password': self.password,
                'source_addr': self.header,
                'messages': [
                    {
                        'msg': content,
                        'dest': phone,
                    }
                ]
            }
            
            response = requests.post(
                self.api_url,
                json=data,
                headers={'Content-Type': 'application/json'},
                timeout=10
            )
            
            result = response.json()
            
            if response.status_code == 200 and result.get('status') == 'ok':
                return {
                    'success': True,
                    'message_id': result.get('message_id', ''),
                }
            else:
                error_msg = result.get('error', 'Bilinmeyen hata')
                return {
                    'success': False,
                    'error': error_msg,
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }
    
    def send_bulk(self, recipients: List[str], subject: str, content: str, **kwargs) -> Dict[str, Any]:
        """Toplu SMS gönder"""
        try:
            # Telefon numaralarını temizle
            phones = []
            for recipient in recipients:
                phone = recipient.replace(' ', '').replace('-', '').replace('(', '').replace(')', '')
                if phone.startswith('0'):
                    phone = '90' + phone[1:]
                elif not phone.startswith('90'):
                    phone = '90' + phone
                phones.append(phone)
            
            # Mesaj listesi oluştur
            messages = [{'msg': content, 'dest': phone} for phone in phones]
            
            data = {
                'username': self.username,
                'password': self.password,
                'source_addr': self.header,
                'messages': messages,
            }
            
            response = requests.post(
                self.api_url,
                json=data,
                headers={'Content-Type': 'application/json'},
                timeout=30
            )
            
            result = response.json()
            
            if response.status_code == 200 and result.get('status') == 'ok':
                sent_count = len(result.get('messages', []))
                failed_count = len(recipients) - sent_count
                
                return {
                    'success': failed_count == 0,
                    'sent_count': sent_count,
                    'failed_count': failed_count,
                    'results': result.get('messages', []),
                }
            else:
                return {
                    'success': False,
                    'sent_count': 0,
                    'failed_count': len(recipients),
                    'error': result.get('error', 'Bilinmeyen hata'),
                }
                
        except Exception as e:
            return {
                'success': False,
                'sent_count': 0,
                'failed_count': len(recipients),
                'error': str(e),
            }
    
    def verify_credentials(self) -> Dict[str, Any]:
        """API bilgilerini doğrula"""
        try:
            # Kredi sorgulama ile test et
            test_url = 'https://api.verimor.com.tr/v2/balance'
            data = {
                'username': self.username,
                'password': self.password,
            }
            
            response = requests.post(
                test_url,
                json=data,
                headers={'Content-Type': 'application/json'},
                timeout=10
            )
            
            if response.status_code == 200:
                result = response.json()
                if 'balance' in result:
                    return {'success': True}
                else:
                    return {
                        'success': False,
                        'error': 'Geçersiz kullanıcı adı veya şifre',
                    }
            else:
                return {
                    'success': False,
                    'error': 'API bağlantı hatası',
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }

