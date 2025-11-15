"""
Outlook / Office 365 SMTP Email Gateway Integration
"""
from typing import Dict, Optional, List
from django.core.mail import EmailMultiAlternatives
from .email_base import BaseEmailGateway
import logging

logger = logging.getLogger(__name__)


class OutlookEmailGateway(BaseEmailGateway):
    """
    Outlook / Office 365 SMTP Email Gateway
    """
    
    def __init__(self, gateway):
        super().__init__(gateway)
        # Outlook için varsayılan ayarlar
        if not self.host:
            self.host = 'smtp.office365.com'
        if self.port == 587 and not self.use_tls:
            self.use_tls = True
    
    def send_email(
        self,
        to_email: str,
        subject: str,
        html_content: str = '',
        text_content: str = '',
        to_name: Optional[str] = None,
        cc: Optional[List[str]] = None,
        bcc: Optional[List[str]] = None,
        attachments: Optional[List[Dict]] = None
    ) -> Dict:
        """
        Outlook üzerinden email gönder
        """
        try:
            # Email doğrulama
            is_valid, error_msg = self.validate_email(to_email)
            if not is_valid:
                return {
                    'success': False,
                    'error': error_msg,
                    'message': 'Geçersiz email adresi'
                }
            
            # Test modu kontrolü
            if self.is_test_mode:
                self.log_info(f"TEST MODU: Email gönderilmedi - {to_email}")
                return {
                    'success': True,
                    'message_id': 'test_mode',
                    'message': 'Test modu aktif - email gönderilmedi',
                    'smtp_response': {'test_mode': True}
                }
            
            # Gönderen bilgileri
            from_email = self.from_email
            if self.from_name:
                from_email = f"{self.from_name} <{self.from_email}>"
            
            # Email oluştur
            email = EmailMultiAlternatives(
                subject=subject,
                body=text_content or html_content,
                from_email=from_email,
                to=[to_email],
                cc=cc or [],
                bcc=bcc or [],
                reply_to=[self.reply_to] if self.reply_to else None
            )
            
            # HTML içerik ekle
            if html_content:
                email.attach_alternative(html_content, "text/html")
            
            # Ekler ekle
            if attachments:
                for attachment in attachments:
                    email.attach(
                        filename=attachment.get('filename', 'attachment'),
                        content=attachment.get('content', b''),
                        mimetype=attachment.get('content_type', 'application/octet-stream')
                    )
            
            # Özel SMTP bağlantısı oluştur ve kullan
            connection = self._get_smtp_connection()
            email.connection = connection
            
            # Email gönder
            email.send()
            
            self.log_info(f"Email başarıyla gönderildi: {to_email}")
            
            return {
                'success': True,
                'message_id': str(email.message_id) if hasattr(email, 'message_id') else '',
                'message': 'Email başarıyla gönderildi',
                'smtp_response': {'sent': True}
            }
        
        except Exception as e:
            self.log_error(f"Email gönderim hatası: {str(e)}", e)
            return {
                'success': False,
                'error': str(e),
                'message': 'Email gönderilirken hata oluştu',
                'smtp_response': {}
            }
    
    def _get_smtp_connection(self):
        """
        SMTP bağlantısı oluştur
        """
        from django.core.mail import get_connection
        
        # SMTP ayarları
        smtp_settings = {
            'host': self.host,
            'port': self.port,
            'username': self.credentials.get('username') or self.from_email,
            'password': self.credentials.get('password'),
            'use_tls': self.use_tls,
            'use_ssl': self.use_ssl,
            'timeout': self.timeout,
        }
        
        return get_connection(
            backend='django.core.mail.backends.smtp.EmailBackend',
            **smtp_settings
        )

