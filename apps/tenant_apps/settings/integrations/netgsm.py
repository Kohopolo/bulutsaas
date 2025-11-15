"""
NetGSM SMS Gateway Entegrasyonu
"""
import requests
from typing import Dict, Optional
from datetime import datetime
from .base import BaseSMSGateway
import logging

logger = logging.getLogger(__name__)


class NetGSMSMSGateway(BaseSMSGateway):
    """
    NetGSM SMS Gateway Entegrasyonu
    """
    
    def __init__(self, gateway):
        super().__init__(gateway)
        self.username = self.credentials.get('username', '')
        self.password = self.credentials.get('password', '')
        self.api_url = self.endpoint or "https://api.netgsm.com.tr/sms/send/get"
    
    def send_sms(self, phone: str, message: str, sender_id: Optional[str] = None) -> Dict:
        """
        NetGSM ile SMS gönder
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
            
            # Türkiye numarası için format (NetGSM için)
            # +905551234567 -> 5551234567
            if formatted_phone.startswith('+90'):
                formatted_phone = formatted_phone[3:]
            elif formatted_phone.startswith('90'):
                formatted_phone = formatted_phone[2:]
            
            # Gönderen başlık
            header = sender_id or self.sender_id or self.username
            
            # Test modu kontrolü
            if self.is_test_mode:
                self.log_info(f"TEST MODU: SMS gönderilecek - {formatted_phone}")
                return {
                    'success': True,
                    'message_id': f'test_{datetime.now().timestamp()}',
                    'message': 'Test modunda SMS gönderildi (gerçekte gönderilmedi)',
                    'gateway_response': {'test_mode': True}
                }
            
            # NetGSM API isteği (GET metodu)
            # Dokümantasyon: https://www.netgsm.com.tr/dokuman/#api-dokümanı
            params = {
                'usercode': self.username,
                'password': self.password,
                'gsmno': formatted_phone,
                'message': message,
                'msgheader': header,
                'language': 'TR'  # Türkçe karakter desteği (TR veya EN)
            }
            
            response = requests.get(
                self.api_url,
                params=params,
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                response_text = response.text.strip()
                
                # NetGSM yanıt formatı kontrolü
                # Başarılı: "00 123456789" (00 = başarılı, sonraki mesaj ID/bulk ID)
                # Hata: "20", "30", "40", vb. (hata kodu)
                if response_text.startswith('00'):
                    parts = response_text.split()
                    # İlk kısım "00" (başarılı), ikinci kısım mesaj ID
                    message_id = parts[1] if len(parts) > 1 else parts[0]
                    
                    self.log_info(f"SMS gönderildi: {formatted_phone} - Bulk ID: {message_id}")
                    
                    return {
                        'success': True,
                        'message_id': message_id,
                        'message': 'SMS başarıyla gönderildi',
                        'gateway_response': {'raw_response': response_text}
                    }
                else:
                    # NetGSM hata kodları (dokümantasyondan)
                    error_map = {
                        '20': 'Mesaj metninde hata var. Mesaj metni boş veya 160 karakterden uzun',
                        '30': 'Geçersiz kullanıcı adı, şifre veya yetkisiz IP adresi',
                        '40': 'Mesaj başlığı (msgheader) kayıtlı değil veya aktif değil',
                        '50': 'Abone hesabında yeterli kredi yok',
                        '51': 'Kredi limiti aşıldı',
                        '60': 'Gönderilecek numara bulunamadı',
                        '70': 'Hatalı sorgu. Gönderdiğiniz parametrelerden birisi hatalı veya zorunlu alanlardan birisi eksik',
                        '80': 'Gönderilemedi',
                        '85': 'Mükerrer gönderim. Aynı numaraya aynı mesaj kısa sürede gönderilmiş',
                    }
                    
                    # Hata kodu ilk 2 karakterden alınır
                    error_code = response_text[:2] if len(response_text) >= 2 else response_text
                    error_msg = error_map.get(error_code, f'Bilinmeyen hata kodu: {error_code}')
                    
                    self.log_error(f"SMS gönderilemedi: {error_msg} (Kod: {error_code})")
                    
                    return {
                        'success': False,
                        'message': 'SMS gönderilemedi',
                        'error': error_msg,
                        'gateway_response': {'raw_response': response_text, 'error_code': error_code}
                    }
            else:
                self.log_error(f"NetGSM API hatası: HTTP {response.status_code}")
                return {
                    'success': False,
                    'message': f'SMS gönderilemedi: HTTP {response.status_code}',
                    'error': f'HTTP {response.status_code}'
                }
        
        except requests.exceptions.RequestException as e:
            self.log_error(f"NetGSM API hatası: {str(e)}", e)
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
        NetGSM bakiyesini sorgula
        """
        try:
            if not self.username or not self.password:
                return {
                    'success': False,
                    'message': 'NetGSM kimlik bilgileri eksik'
                }
            
            # NetGSM Balance API
            balance_url = "https://api.netgsm.com.tr/balance/list/get"
            
            params = {
                'usercode': self.username,
                'password': self.password
            }
            
            response = requests.get(
                balance_url,
                params=params,
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                response_text = response.text.strip()
                
                # NetGSM bakiye yanıtı: "00 1234.56" (00 = başarılı, sonraki bakiye)
                # Veya sadece bakiye: "1234.56"
                if response_text.startswith('00'):
                    parts = response_text.split()
                    try:
                        balance = float(parts[1]) if len(parts) > 1 else 0.0
                    except (ValueError, IndexError):
                        balance = 0.0
                    
                    return {
                        'success': True,
                        'balance': balance,
                        'currency': 'TL',
                        'message': f'Bakiye: {balance} TL'
                    }
                else:
                    # Sadece sayısal değer dönebilir
                    try:
                        balance = float(response_text)
                        return {
                            'success': True,
                            'balance': balance,
                            'currency': 'TL',
                            'message': f'Bakiye: {balance} TL'
                        }
                    except ValueError:
                        return {
                            'success': False,
                            'message': f'Bakiye sorgulanamadı: {response_text}'
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
        NetGSM mesaj teslim durumunu sorgula
        """
        try:
            if not self.username or not self.password:
                return {
                    'success': False,
                    'message': 'NetGSM kimlik bilgileri eksik'
                }
            
            # NetGSM Status API (Rapor sorgulama)
            # Dokümantasyon: https://www.netgsm.com.tr/dokuman/#api-dokümanı
            status_url = "https://api.netgsm.com.tr/sms/report/get"
            
            params = {
                'usercode': self.username,
                'password': self.password,
                'bulkid': message_id  # Bulk ID (gönderim sırasında dönen ID)
            }
            
            response = requests.get(
                status_url,
                params=params,
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                response_text = response.text.strip()
                
                # NetGSM durum yanıtı formatı:
                # Başarılı: "00 1" (00 = başarılı, 1 = teslim durumu)
                # Durum kodları: 
                #   0 = Beklemede/Gönderiliyor
                #   1 = Teslim edildi
                #   2 = Teslim edilemedi
                #   3 = Zaman aşımı
                if response_text.startswith('00'):
                    parts = response_text.split()
                    status_code = parts[1] if len(parts) > 1 else '0'
                    
                    status_map = {
                        '0': 'pending',      # Beklemede
                        '1': 'delivered',   # Teslim edildi
                        '2': 'failed',      # Teslim edilemedi
                        '3': 'failed',      # Zaman aşımı
                    }
                    
                    mapped_status = status_map.get(status_code, 'pending')
                    
                    return {
                        'success': True,
                        'status': mapped_status,
                        'delivered_at': datetime.now() if mapped_status == 'delivered' else None,
                        'message': f'Durum: {mapped_status} (Kod: {status_code})'
                    }
                else:
                    # Hata durumu
                    error_code = response_text[:2] if len(response_text) >= 2 else response_text
                    return {
                        'success': False,
                        'message': f'Durum sorgulanamadı: {response_text} (Hata kodu: {error_code})'
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

