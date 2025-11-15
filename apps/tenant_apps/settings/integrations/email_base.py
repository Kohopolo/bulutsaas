"""
Base Email Gateway Integration
Tüm email gateway entegrasyonları için temel sınıf
"""

from abc import ABC, abstractmethod
from typing import Dict, Optional, List, Tuple
import logging
import re

logger = logging.getLogger(__name__)


class BaseEmailGateway(ABC):
    """
    Email Gateway Temel Sınıfı
    Tüm email gateway entegrasyonları bu sınıftan türetilir
    """

    def __init__(self, gateway):
        """
        Args:
            gateway: EmailGateway instance
        """
        self.gateway = gateway
        self.credentials = gateway.smtp_credentials
        self.host = gateway.smtp_host
        self.port = gateway.smtp_port
        self.use_tls = gateway.use_tls
        self.use_ssl = gateway.use_ssl
        self.timeout = gateway.smtp_timeout
        self.from_email = gateway.from_email
        self.from_name = gateway.from_name
        self.reply_to = gateway.reply_to
        self.is_test_mode = gateway.is_test_mode

    @abstractmethod
    def send_email(
        self,
        to_email: str,
        subject: str,
        html_content: str = "",
        text_content: str = "",
        to_name: Optional[str] = None,
        cc: Optional[List[str]] = None,
        bcc: Optional[List[str]] = None,
        attachments: Optional[List[Dict]] = None
    ) -> Dict:
        """
        Email gönder

        Returns:
            {
                'success': bool,
                'message_id': str,
                'message': str,
                'error': str,
                'smtp_response': dict
            }
        """
        pass

    def validate_email(self, email: str) -> Tuple[bool, str]:
        """
        Email adresini doğrula
        """
        pattern = r"^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
        if not re.match(pattern, email):
            return False, "Geçersiz email formatı"
        return True, ""

    def log_error(self, message: str, error: Exception = None):
        """Hata logla"""
        logger.error(f"[{self.gateway.name}] {message}", exc_info=error)

    def log_info(self, message: str):
        """Bilgi logla"""
        logger.info(f"[{self.gateway.name}] {message}")
