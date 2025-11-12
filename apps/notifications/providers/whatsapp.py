"""
WhatsApp Bildirim Sağlayıcısı
"""
import requests
import json
from typing import Dict, Any, List
from .base import BaseNotificationProvider


class WhatsAppProvider(BaseNotificationProvider):
    """
    WhatsApp Business API Sağlayıcısı
    Meta WhatsApp Business API entegrasyonu
    """
    
    def __init__(self, config: Dict[str, Any]):
        super().__init__(config)
        self.api_url = f"https://graph.facebook.com/v18.0/{config.get('whatsapp_phone_number_id', '')}/messages"
        self.access_token = config.get('whatsapp_access_token', '')
        self.business_id = config.get('whatsapp_business_id', '')
        self.verify_token = config.get('whatsapp_verify_token', '')
    
    def send(self, recipient: str, subject: str, content: str, **kwargs) -> Dict[str, Any]:
        """WhatsApp mesajı gönder"""
        try:
            # Telefon numarasını temizle ve formatla
            phone = recipient.replace(' ', '').replace('-', '').replace('(', '').replace(')', '')
            if phone.startswith('0'):
                phone = '90' + phone[1:]
            elif not phone.startswith('90'):
                phone = '90' + phone
            
            # WhatsApp formatı: 905551234567
            whatsapp_phone = phone
            
            # Mesaj tipi
            message_type = kwargs.get('message_type', 'text')
            
            if message_type == 'text':
                payload = {
                    'messaging_product': 'whatsapp',
                    'to': whatsapp_phone,
                    'type': 'text',
                    'text': {
                        'body': content
                    }
                }
            elif message_type == 'template':
                template_name = kwargs.get('template_name', '')
                template_params = kwargs.get('template_params', [])
                
                payload = {
                    'messaging_product': 'whatsapp',
                    'to': whatsapp_phone,
                    'type': 'template',
                    'template': {
                        'name': template_name,
                        'language': {
                            'code': 'tr'
                        },
                        'components': [
                            {
                                'type': 'body',
                                'parameters': [
                                    {'type': 'text', 'text': param} for param in template_params
                                ]
                            }
                        ] if template_params else []
                    }
                }
            else:
                return {
                    'success': False,
                    'error': f'Desteklenmeyen mesaj tipi: {message_type}',
                }
            
            headers = {
                'Authorization': f'Bearer {self.access_token}',
                'Content-Type': 'application/json',
            }
            
            response = requests.post(
                self.api_url,
                json=payload,
                headers=headers,
                timeout=10
            )
            
            if response.status_code == 200:
                result = response.json()
                return {
                    'success': True,
                    'message_id': result.get('messages', [{}])[0].get('id', ''),
                }
            else:
                error_data = response.json()
                error_msg = error_data.get('error', {}).get('message', 'Bilinmeyen hata')
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
        """Toplu WhatsApp mesajı gönder"""
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
            # WhatsApp Business API bilgilerini kontrol et
            if not self.access_token or not self.business_id:
                return {
                    'success': False,
                    'error': 'Access Token ve Business ID gerekli',
                }
            
            # Test isteği gönder
            test_url = f"https://graph.facebook.com/v18.0/{self.business_id}"
            headers = {
                'Authorization': f'Bearer {self.access_token}',
            }
            
            response = requests.get(test_url, headers=headers, timeout=10)
            
            if response.status_code == 200:
                return {'success': True}
            else:
                error_data = response.json()
                error_msg = error_data.get('error', {}).get('message', 'API bağlantı hatası')
                return {
                    'success': False,
                    'error': error_msg,
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }

