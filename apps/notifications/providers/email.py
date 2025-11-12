"""
Email Bildirim Sağlayıcısı
"""
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from typing import Dict, Any, List
from .base import BaseNotificationProvider


class EmailProvider(BaseNotificationProvider):
    """
    Email Bildirim Sağlayıcısı
    SMTP üzerinden email gönderir
    """
    
    def __init__(self, config: Dict[str, Any]):
        super().__init__(config)
        self.host = config.get('email_host', '')
        self.port = config.get('email_port', 587)
        self.use_tls = config.get('email_use_tls', True)
        self.use_ssl = config.get('email_use_ssl', False)
        self.username = config.get('username', '')
        self.password = config.get('password', '')
        self.from_email = config.get('email_from', '')
        self.from_name = config.get('email_from_name', '')
    
    def send(self, recipient: str, subject: str, content: str, content_html: str = '', **kwargs) -> Dict[str, Any]:
        """Email gönder"""
        try:
            # SMTP bağlantısı
            if self.use_ssl:
                server = smtplib.SMTP_SSL(self.host, self.port)
            else:
                server = smtplib.SMTP(self.host, self.port)
                if self.use_tls:
                    server.starttls()
            
            # Giriş yap
            if self.username and self.password:
                server.login(self.username, self.password)
            
            # Email oluştur
            msg = MIMEMultipart('alternative')
            msg['Subject'] = subject
            msg['From'] = f"{self.from_name} <{self.from_email}>" if self.from_name else self.from_email
            msg['To'] = recipient
            
            # İçerik ekle
            if content_html:
                msg.attach(MIMEText(content, 'plain', 'utf-8'))
                msg.attach(MIMEText(content_html, 'html', 'utf-8'))
            else:
                msg.attach(MIMEText(content, 'plain', 'utf-8'))
            
            # Gönder
            server.send_message(msg)
            server.quit()
            
            return {
                'success': True,
                'message_id': f"email_{recipient}_{subject}",
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }
    
    def send_bulk(self, recipients: List[str], subject: str, content: str, content_html: str = '', **kwargs) -> Dict[str, Any]:
        """Toplu email gönder"""
        results = []
        sent_count = 0
        failed_count = 0
        
        for recipient in recipients:
            result = self.send(recipient, subject, content, content_html, **kwargs)
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
        """SMTP bilgilerini doğrula"""
        try:
            if self.use_ssl:
                server = smtplib.SMTP_SSL(self.host, self.port)
            else:
                server = smtplib.SMTP(self.host, self.port)
                if self.use_tls:
                    server.starttls()
            
            if self.username and self.password:
                server.login(self.username, self.password)
            
            server.quit()
            
            return {'success': True}
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
            }

