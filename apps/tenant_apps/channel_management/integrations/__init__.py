"""
Kanal API Entegrasyonları
Her kanal için özel API entegrasyon sınıfları
"""
from .base import BaseChannelIntegration
from .booking import BookingIntegration
from .ets import ETSIntegration
from .tatilbudur import TatilbudurIntegration
from .tatilsepeti import TatilsepetiIntegration
from .hotels import HotelsIntegration
from .trivago import TrivagoIntegration
from .expedia import ExpediaIntegration
from .agoda import AgodaIntegration

__all__ = [
    'BaseChannelIntegration',
    'BookingIntegration',
    'ETSIntegration',
    'TatilbudurIntegration',
    'TatilsepetiIntegration',
    'HotelsIntegration',
    'TrivagoIntegration',
    'ExpediaIntegration',
    'AgodaIntegration',
]

