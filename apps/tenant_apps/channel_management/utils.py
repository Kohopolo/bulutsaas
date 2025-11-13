"""
Kanal Yönetimi Yardımcı Fonksiyonlar
"""
from .models import ChannelConfiguration
from .integrations import (
    BookingIntegration, ETSIntegration, TatilbudurIntegration,
    TatilsepetiIntegration, HotelsIntegration, TrivagoIntegration,
    ExpediaIntegration, AgodaIntegration
)


def get_channel_integration(configuration: ChannelConfiguration):
    """
    Kanal konfigürasyonuna göre uygun entegrasyon sınıfını döndür
    
    Args:
        configuration: ChannelConfiguration instance
    
    Returns:
        BaseChannelIntegration instance
    """
    template_code = configuration.template.code
    
    integration_map = {
        'booking': BookingIntegration,
        'ets': ETSIntegration,
        'tatilbudur': TatilbudurIntegration,
        'tatilsepeti': TatilsepetiIntegration,
        'hotels': HotelsIntegration,
        'trivago': TrivagoIntegration,
        'expedia': ExpediaIntegration,
        'agoda': AgodaIntegration,
    }
    
    integration_class = integration_map.get(template_code)
    
    if integration_class:
        return integration_class(configuration)
    else:
        # Varsayılan olarak base class kullan (sadece temel işlevler)
        from .integrations.base import BaseChannelIntegration
        return BaseChannelIntegration(configuration)

