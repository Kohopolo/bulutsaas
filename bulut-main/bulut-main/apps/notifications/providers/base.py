"""
Bildirim Sağlayıcı Base Sınıfı
"""
from abc import ABC, abstractmethod
from typing import Dict, Any, List, Optional


class BaseNotificationProvider(ABC):
    """
    Bildirim Sağlayıcı Base Sınıfı
    Tüm bildirim sağlayıcıları bu sınıftan türetilir
    """
    
    def __init__(self, config: Dict[str, Any]):
        """
        Args:
            config: Sağlayıcı yapılandırması (NotificationProviderConfig'den)
        """
        self.config = config
        self.is_test_mode = config.get('is_test_mode', False)
    
    @abstractmethod
    def send(self, recipient: str, subject: str, content: str, **kwargs) -> Dict[str, Any]:
        """
        Bildirim gönder
        
        Args:
            recipient: Alıcı (email, telefon vb.)
            subject: Konu
            content: İçerik
            **kwargs: Ek parametreler
        
        Returns:
            {
                'success': bool,
                'message_id': str,
                'error': str (varsa)
            }
        """
        pass
    
    @abstractmethod
    def send_bulk(self, recipients: List[str], subject: str, content: str, **kwargs) -> Dict[str, Any]:
        """
        Toplu bildirim gönder
        
        Args:
            recipients: Alıcı listesi
            subject: Konu
            content: İçerik
            **kwargs: Ek parametreler
        
        Returns:
            {
                'success': bool,
                'sent_count': int,
                'failed_count': int,
                'results': List[Dict]
            }
        """
        pass
    
    @abstractmethod
    def verify_credentials(self) -> Dict[str, Any]:
        """
        API bilgilerini doğrula
        
        Returns:
            {
                'success': bool,
                'error': str (varsa)
            }
        """
        pass
    
    def format_content(self, template: str, variables: Dict[str, Any]) -> str:
        """
        Şablon içeriğini değişkenlerle doldur
        
        Args:
            template: Şablon metni ({{variable}} formatında)
            variables: Değişkenler
        
        Returns:
            Formatlanmış içerik
        """
        content = template
        for key, value in variables.items():
            content = content.replace(f'{{{{{key}}}}}', str(value))
        return content

