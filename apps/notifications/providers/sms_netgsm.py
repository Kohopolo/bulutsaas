"""
NetGSM SMS Sağlayıcısı
"""
import requests
from typing import Dict, Any, List
from .base import BaseNotificationProvider


class NetGSMProvider(BaseNotificationProvider):
    """
    NetGSM SMS Sağlayıcısı
    NetGSM API entegrasyonu
    """
    
    def __init__(self, config: Dict[str, Any]):
        super().__init__(config)
        self.api_url = 'https://api.netgsm.com.tr/sms/send/get' if not self.is_test_mode else 'https://api.netgsm.com.tr/test/sms/send/get'
        self.username = config.get('sms_username', '')
        self.password = config.get('sms_password', '')
        self.header = config.get('sms_header', '')
    
    def send(self, recipient: str, subject: str, content: str, **kwargs) -> Dict[str, Any]:
        """SMS gönder"""
        try:
            # Telefon numarasını temizle (başında 0 olmadan)
            phone = recipient.replace(' ', '').replace('-', '').replace('(', '').replace(')', '')
            if phone.startswith('0'):
                phone = '90' + phone[1:]
            elif not phone.startswith('90'):
                phone = '90' + phone
            
            params = {
                'usercode': self.username,
                'password': self.password,
                'gsmno': phone,
                'message': content,
                'msgheader': self.header,
                'language': 'TR' if kwargs.get('unicode', True) else 'EN',
            }
            
            response = requests.get(self.api_url, params=params, timeout=10)
            
            # NetGSM yanıt formatı: "00 123456789" (başarılı) veya hata kodu
            response_text = response.text.strip()
            
            if response_text.startswith('00'):
                # Başarılı
                message_id = response_text.split()[1] if len(response_text.split()) > 1 else response_text
                return {
                    'success': True,
                    'message_id': message_id,
                }
            else:
                # Hata
                error_messages = {
                    '20': 'Mesaj metninde hata var',
                    '30': 'Geçersiz kullanıcı adı, şifre veya yetkisiz IP',
                    '40': 'Mesaj başlığı (header) kayıtlı değil',
                    '50': 'Abone hesabında yeterli kredi yok',
                    '51': 'Abone hesabında yeterli kredi yok',
                    '70': 'Hatalı sorgulama',
                }
                error_msg = error_messages.get(response_text, f'Bilinmeyen hata: {response_text}')
                
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
        results = []
        sent_count = 0
        failed_count = 0
        
        for recipient in recipients:
            result = self.send(recipient, subject, content, **kwargs)
            results.append({
                'recipient': recipient,
                'success': result.get('success', False),
                'error': result.get('error', ''),
            })
            if result.get('success'):
                sent_count += 1
            else:
                failed_count += 1
        
        return {
            'success': failed_count == 0,
            'sent_count': sent_count,
            'failed_count': failed_count,
            'results': results,
        }
    
    def verify_credentials(self) -> Dict[str, Any]:
        """API bilgilerini doğrula"""
        try:
            # Test SMS gönder (kendine)
            if self.username and self.password:
                # Kredi sorgulama ile test et
                test_url = 'https://api.netgsm.com.tr/balance/list/get'
                params = {
                    'usercode': self.username,
                    'password': self.password,
                }
                response = requests.get(test_url, params=params, timeout=10)
                
                if response.text.strip().startswith('00'):
                    return {'success': True}
                else:
                    return {
                        'success': False,
                        'error': 'Geçersiz kullanıcı adı veya şifre',
                    }
            else:
                return {
                    'success': False,
                    'error': 'Kullanıcı adı ve şifre gerekli',
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }

