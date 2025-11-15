"""
Verimor SMS Gateway Entegrasyonu
"""
import requests
import json
from typing import Dict, Optional
from datetime import datetime
from .base import BaseSMSGateway
import logging

logger = logging.getLogger(__name__)


class VerimorSMSGateway(BaseSMSGateway):
    """
    Verimor SMS Gateway Entegrasyonu
    """
    
    def __init__(self, gateway):
        super().__init__(gateway)
        self.username = self.credentials.get('username', '')
        self.password = self.credentials.get('password', '')
        # Verimor API endpoint: https://sms.verimor.com.tr/v2/send.json
        # Dokümantasyon: https://github.com/verimor/SMS-API
        self.api_url = self.endpoint or "https://sms.verimor.com.tr/v2/send.json"
    
    def send_sms(self, phone: str, message: str, sender_id: Optional[str] = None) -> Dict:
        """
        Verimor ile SMS gönder
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
            
            # Türkiye numarası için format (Verimor için)
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
            
            # Verimor API isteği (JSON format)
            # Dokümantasyon: https://github.com/verimor/SMS-API
            payload = {
                'username': self.username,
                'password': self.password,
                'source_addr': header,
                'messages': [
                    {
                        'msg': message,
                        'dest': formatted_phone
                    }
                ]
            }
            
            headers = {
                'Content-Type': 'application/json'
            }
            
            response = requests.post(
                self.api_url,
                json=payload,
                headers=headers,
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                try:
                    data = response.json()
                except ValueError:
                    # JSON parse edilemezse text olarak al
                    response_text = response.text.strip()
                    self.log_error(f"Verimor JSON parse hatası: {response_text}")
                    return {
                        'success': False,
                        'message': 'SMS gönderilemedi',
                        'error': f'Geçersiz yanıt formatı: {response_text}'
                    }
                
                # Verimor başarılı yanıt formatı: Liste döner
                # [{"id": "12345", "status": "ok"}, ...]
                if isinstance(data, list) and len(data) > 0:
                    message_data = data[0]
                    message_id = message_data.get('id', '')
                    status = message_data.get('status', '').lower()
                    
                    if status == 'ok':
                        self.log_info(f"SMS gönderildi: {formatted_phone} - Message ID: {message_id}")
                        
                        return {
                            'success': True,
                            'message_id': message_id,
                            'message': 'SMS başarıyla gönderildi',
                            'gateway_response': data
                        }
                    else:
                        # Hata durumu
                        error_msg = message_data.get('error', f'Durum: {status}')
                        self.log_error(f"SMS gönderilemedi: {error_msg}")
                        
                        return {
                            'success': False,
                            'message': 'SMS gönderilemedi',
                            'error': error_msg,
                            'gateway_response': data
                        }
                elif isinstance(data, dict):
                    # Hata yanıtı (dict formatında)
                    error_msg = data.get('error', data.get('message', 'Bilinmeyen hata'))
                    self.log_error(f"SMS gönderilemedi: {error_msg}")
                    
                    return {
                        'success': False,
                        'message': 'SMS gönderilemedi',
                        'error': error_msg,
                        'gateway_response': data
                    }
                else:
                    # Beklenmeyen format
                    self.log_error(f"Verimor beklenmeyen yanıt formatı: {data}")
                    return {
                        'success': False,
                        'message': 'SMS gönderilemedi',
                        'error': 'Beklenmeyen yanıt formatı',
                        'gateway_response': data
                    }
            else:
                try:
                    error_data = response.json()
                    error_msg = error_data.get('error', f'HTTP {response.status_code}')
                except:
                    error_msg = f'HTTP {response.status_code}'
                
                self.log_error(f"Verimor API hatası: {error_msg}")
                
                return {
                    'success': False,
                    'message': 'SMS gönderilemedi',
                    'error': error_msg
                }
        
        except requests.exceptions.RequestException as e:
            self.log_error(f"Verimor API hatası: {str(e)}", e)
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
        Verimor bakiyesini sorgula
        """
        try:
            if not self.username or not self.password:
                return {
                    'success': False,
                    'message': 'Verimor kimlik bilgileri eksik'
                }
            
            # Verimor Balance API
            # Dokümantasyon: https://github.com/verimor/SMS-API
            balance_url = "https://sms.verimor.com.tr/v2/balance"
            
            payload = {
                'username': self.username,
                'password': self.password
            }
            
            headers = {
                'Content-Type': 'application/json'
            }
            
            response = requests.post(
                balance_url,
                json=payload,
                headers=headers,
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                try:
                    data = response.json()
                except ValueError:
                    response_text = response.text.strip()
                    return {
                        'success': False,
                        'message': f'Bakiye sorgulanamadı: {response_text}'
                    }
                
                # Verimor bakiye yanıtı: {"balance": 1234.56, "currency": "TL"}
                if isinstance(data, dict):
                    if 'balance' in data:
                        balance = float(data.get('balance', 0))
                        currency = data.get('currency', 'TL')
                        
                        return {
                            'success': True,
                            'balance': balance,
                            'currency': currency,
                            'message': f'Bakiye: {balance} {currency}'
                        }
                    elif 'error' in data:
                        return {
                            'success': False,
                            'message': f'Bakiye sorgulanamadı: {data.get("error")}'
                        }
                
                return {
                    'success': False,
                    'message': f'Bakiye sorgulanamadı: {data}'
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
        Verimor mesaj teslim durumunu sorgula
        """
        try:
            if not self.username or not self.password:
                return {
                    'success': False,
                    'message': 'Verimor kimlik bilgileri eksik'
                }
            
            # Verimor Status API (Rapor sorgulama)
            # Dokümantasyon: https://github.com/verimor/SMS-API
            status_url = "https://sms.verimor.com.tr/v2/report"
            
            payload = {
                'username': self.username,
                'password': self.password,
                'id': message_id  # Mesaj ID (gönderim sırasında dönen ID)
            }
            
            headers = {
                'Content-Type': 'application/json'
            }
            
            response = requests.post(
                status_url,
                json=payload,
                headers=headers,
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                try:
                    data = response.json()
                except ValueError:
                    response_text = response.text.strip()
                    return {
                        'success': False,
                        'message': f'Durum sorgulanamadı: {response_text}'
                    }
                
                # Verimor durum yanıtı: {"status": "delivered", "delivered_at": "2025-11-14T10:30:00Z"}
                if isinstance(data, dict):
                    status = data.get('status', 'unknown').lower()
                    
                    # Verimor durum kodları mapping
                    status_map = {
                        'sent': 'pending',        # Gönderildi (beklemede)
                        'delivered': 'delivered', # Teslim edildi
                        'failed': 'failed',      # Başarısız
                        'pending': 'pending',    # Beklemede
                        'rejected': 'failed',    # Reddedildi
                    }
                    
                    mapped_status = status_map.get(status, 'pending')
                    delivered_at = None
                    
                    # Teslim zamanı parse et
                    if mapped_status == 'delivered':
                        if data.get('delivered_at'):
                            try:
                                delivered_at_str = data['delivered_at']
                                # ISO format: "2025-11-14T10:30:00Z" veya "2025-11-14T10:30:00+03:00"
                                if delivered_at_str.endswith('Z'):
                                    delivered_at = datetime.fromisoformat(delivered_at_str.replace('Z', '+00:00'))
                                else:
                                    delivered_at = datetime.fromisoformat(delivered_at_str)
                            except (ValueError, AttributeError) as e:
                                self.log_error(f"Tarih parse hatası: {e}")
                                delivered_at = datetime.now()
                        else:
                            delivered_at = datetime.now()
                    
                    return {
                        'success': True,
                        'status': mapped_status,
                        'delivered_at': delivered_at,
                        'message': f'Durum: {mapped_status} (Orijinal: {status})'
                    }
                else:
                    return {
                        'success': False,
                        'message': f'Durum sorgulanamadı: Beklenmeyen format - {data}'
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

