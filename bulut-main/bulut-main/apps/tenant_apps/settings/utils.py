"""
SMS Utility Fonksiyonları
SMS gönderme ve şablon yönetimi için yardımcı fonksiyonlar
"""
from typing import Dict, Optional
from django.utils import timezone
from .models import SMSGateway, SMSTemplate, SMSSentLog
from .integrations.twilio import TwilioSMSGateway
from .integrations.netgsm import NetGSMSMSGateway
from .integrations.verimor import VerimorSMSGateway
import logging

logger = logging.getLogger(__name__)


def get_sms_gateway_instance(gateway: SMSGateway):
    """
    Gateway tipine göre uygun SMS gateway instance'ı döndür
    
    Args:
        gateway: SMSGateway instance
    
    Returns:
        BaseSMSGateway instance
    """
    gateway_map = {
        'twilio': TwilioSMSGateway,
        'netgsm': NetGSMSMSGateway,
        'verimor': VerimorSMSGateway,
    }
    
    gateway_class = gateway_map.get(gateway.gateway_type)
    if not gateway_class:
        raise ValueError(f"Desteklenmeyen gateway tipi: {gateway.gateway_type}")
    
    return gateway_class(gateway)


def get_default_gateway(hotel=None):
    """
    Varsayılan aktif SMS gateway'i döndür
    Otel bazlı gateway varsa onu, yoksa genel gateway'i döndürür
    
    Args:
        hotel: Hotel instance (opsiyonel)
    
    Returns:
        SMSGateway instance veya None
    """
    try:
        # Önce otel bazlı varsayılan gateway'i ara
        if hotel:
            gateway = SMSGateway.objects.filter(
                hotel=hotel,
                is_active=True,
                is_default=True
            ).first()
            if gateway:
                return gateway
        
        # Otel bazlı gateway yoksa genel gateway'i ara
        gateway = SMSGateway.objects.filter(
            hotel__isnull=True,
            is_active=True,
            is_default=True
        ).first()
        
        if gateway:
            return gateway
        
        # Varsayılan yoksa aktif olan herhangi bir otel bazlı gateway'i döndür
        if hotel:
            gateway = SMSGateway.objects.filter(
                hotel=hotel,
                is_active=True
            ).first()
            if gateway:
                return gateway
        
        # Son olarak aktif olan herhangi bir genel gateway'i döndür
        return SMSGateway.objects.filter(
            hotel__isnull=True,
            is_active=True
        ).first()
    except Exception as e:
        logger.error(f"Varsayılan gateway bulunamadı: {str(e)}")
        return None


def send_sms(
    phone: str,
    message: str,
    template: Optional[SMSTemplate] = None,
    context: Optional[Dict] = None,
    gateway: Optional[SMSGateway] = None,
    hotel=None,
    sender_id: Optional[str] = None,
    related_module: Optional[str] = None,
    related_object_id: Optional[int] = None,
    related_object_type: Optional[str] = None
) -> Dict:
    """
    SMS gönder
    
    Args:
        phone: Alıcı telefon numarası
        message: Gönderilecek mesaj metni (veya template kullanılacaksa None)
        template: SMSTemplate instance (opsiyonel)
        context: Template için context dict (opsiyonel)
        gateway: SMSGateway instance (opsiyonel, varsayılan kullanılır)
        sender_id: Gönderen ID (opsiyonel)
        related_module: İlişkili modül adı (opsiyonel)
        related_object_id: İlişkili kayıt ID (opsiyonel)
        related_object_type: İlişkili kayıt tipi (opsiyonel)
    
    Returns:
        {
            'success': bool,
            'log_id': int (SMSSentLog ID),
            'message_id': str (gateway mesaj ID),
            'message': str,
            'error': str (hata varsa)
        }
    """
    try:
        # Gateway seçimi
        if not gateway:
            gateway = get_default_gateway(hotel=hotel)
        
        if not gateway:
            return {
                'success': False,
                'message': 'Aktif SMS gateway bulunamadı',
                'error': 'Lütfen bir SMS gateway yapılandırın'
            }
        
        # Template kullanılıyorsa mesajı render et
        if template:
            if context:
                message = template.render(context)
            else:
                message = template.template_text
        
        # Mesaj uzunluğu kontrolü
        message_length = len(message)
        
        # SMS gönderim logu oluştur
        log = SMSSentLog.objects.create(
            gateway=gateway,
            template=template,
            recipient_phone=phone,
            message_text=message,
            message_length=message_length,
            status='pending',
            related_module=related_module or '',
            related_object_id=related_object_id,
            related_object_type=related_object_type or '',
            context_data=context or {}
        )
        
        # Gateway instance oluştur
        gateway_instance = get_sms_gateway_instance(gateway)
        
        # SMS gönder
        result = gateway_instance.send_sms(
            phone=phone,
            message=message,
            sender_id=sender_id
        )
        
        # Log'u güncelle
        if result['success']:
            log.status = 'sent'
            log.gateway_message_id = result.get('message_id', '')
            log.sent_at = timezone.now()
            log.gateway_response = result.get('gateway_response', {})
            
            # Gateway istatistiklerini güncelle
            gateway.total_sent += 1
            gateway.last_sent_at = timezone.now()
            gateway.save(update_fields=['total_sent', 'last_sent_at'])
        else:
            log.status = 'failed'
            log.error_message = result.get('error', 'Bilinmeyen hata')
            log.gateway_response = result.get('gateway_response', {})
            
            # Gateway istatistiklerini güncelle
            gateway.total_failed += 1
            gateway.save(update_fields=['total_failed'])
        
        log.save()
        
        # Template kullanım sayısını güncelle
        if template:
            template.usage_count += 1
            template.last_used_at = timezone.now()
            template.save(update_fields=['usage_count', 'last_used_at'])
        
        return {
            'success': result['success'],
            'log_id': log.id,
            'message_id': result.get('message_id', ''),
            'message': result.get('message', ''),
            'error': result.get('error')
        }
    
    except Exception as e:
        logger.error(f"SMS gönderim hatası: {str(e)}", exc_info=True)
        return {
            'success': False,
            'message': 'SMS gönderilirken beklenmeyen hata oluştu',
            'error': str(e)
        }


def send_sms_by_template(
    template_code: str,
    phone: str,
    context: Dict,
    gateway: Optional[SMSGateway] = None,
    hotel=None,
    **kwargs
) -> Dict:
    """
    Template kodu ile SMS gönder
    
    Args:
        template_code: SMSTemplate code
        phone: Alıcı telefon numarası
        context: Template context dict
        gateway: SMSGateway instance (opsiyonel)
        **kwargs: send_sms fonksiyonuna geçirilecek ek parametreler
    
    Returns:
        send_sms fonksiyonunun döndürdüğü dict
    """
    try:
        template = SMSTemplate.objects.get(code=template_code, is_active=True)
        return send_sms(
            phone=phone,
            message=None,  # Template'ten alınacak
            template=template,
            context=context,
            gateway=gateway,
            hotel=hotel,
            **kwargs
        )
    except SMSTemplate.DoesNotExist:
        return {
            'success': False,
            'message': f'SMS şablonu bulunamadı: {template_code}',
            'error': f'Template code: {template_code}'
        }
    except Exception as e:
        logger.error(f"Template ile SMS gönderim hatası: {str(e)}", exc_info=True)
        return {
            'success': False,
            'message': 'SMS gönderilirken hata oluştu',
            'error': str(e)
        }

