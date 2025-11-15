"""
Email Utility Fonksiyonları
Email gönderme ve şablon yönetimi için yardımcı fonksiyonlar
"""
from typing import Dict, Optional, List
from django.utils import timezone
from .models import EmailGateway, EmailTemplate, EmailSentLog
from .integrations.email_gmail import GmailEmailGateway
from .integrations.email_outlook import OutlookEmailGateway
from .integrations.email_custom import CustomSMTPEmailGateway
import logging

logger = logging.getLogger(__name__)


def get_email_gateway_instance(gateway: EmailGateway):
    """
    Gateway tipine göre uygun email gateway instance'ı döndür
    
    Args:
        gateway: EmailGateway instance
    
    Returns:
        BaseEmailGateway instance
    """
    gateway_map = {
        'gmail': GmailEmailGateway,
        'outlook': OutlookEmailGateway,
        'custom': CustomSMTPEmailGateway,
    }
    
    gateway_class = gateway_map.get(gateway.gateway_type)
    if not gateway_class:
        raise ValueError(f"Desteklenmeyen gateway tipi: {gateway.gateway_type}")
    
    return gateway_class(gateway)


def get_default_email_gateway(hotel=None):
    """
    Varsayılan aktif email gateway'i döndür
    Otel bazlı gateway varsa onu, yoksa genel gateway'i döndürür
    
    Args:
        hotel: Hotel instance (opsiyonel)
    
    Returns:
        EmailGateway instance veya None
    """
    try:
        # Önce otel bazlı varsayılan gateway'i ara
        if hotel:
            gateway = EmailGateway.objects.filter(
                hotel=hotel,
                is_active=True,
                is_default=True
            ).first()
            if gateway:
                return gateway
        
        # Otel bazlı gateway yoksa genel gateway'i ara
        gateway = EmailGateway.objects.filter(
            hotel__isnull=True,
            is_active=True,
            is_default=True
        ).first()
        
        if gateway:
            return gateway
        
        # Varsayılan yoksa aktif olan herhangi bir otel bazlı gateway'i döndür
        if hotel:
            gateway = EmailGateway.objects.filter(
                hotel=hotel,
                is_active=True
            ).first()
            if gateway:
                return gateway
        
        # Son olarak aktif olan herhangi bir genel gateway'i döndür
        return EmailGateway.objects.filter(
            hotel__isnull=True,
            is_active=True
        ).first()
    except Exception as e:
        logger.error(f"Varsayılan email gateway bulunamadı: {str(e)}")
        return None


def send_email(
    to_email: str,
    subject: str,
    html_content: str = '',
    text_content: str = '',
    template: Optional[EmailTemplate] = None,
    context: Optional[Dict] = None,
    gateway: Optional[EmailGateway] = None,
    hotel=None,
    to_name: Optional[str] = None,
    cc: Optional[List[str]] = None,
    bcc: Optional[List[str]] = None,
    attachments: Optional[List[Dict]] = None,
    related_module: Optional[str] = None,
    related_object_id: Optional[int] = None,
    related_object_type: Optional[str] = None
) -> Dict:
    """
    Email gönder
    
    Args:
        to_email: Alıcı email adresi
        subject: Email konusu (veya template kullanılacaksa None)
        html_content: HTML içerik (veya template kullanılacaksa None)
        text_content: Plain text içerik (veya template kullanılacaksa None)
        template: EmailTemplate instance (opsiyonel)
        context: Template için context dict (opsiyonel)
        gateway: EmailGateway instance (opsiyonel, varsayılan kullanılır)
        to_name: Alıcı adı (opsiyonel)
        cc: CC alıcıları (opsiyonel)
        bcc: BCC alıcıları (opsiyonel)
        attachments: Ekler (opsiyonel)
        related_module: İlişkili modül adı (opsiyonel)
        related_object_id: İlişkili kayıt ID (opsiyonel)
        related_object_type: İlişkili kayıt tipi (opsiyonel)
    
    Returns:
        {
            'success': bool,
            'log_id': int (EmailSentLog ID),
            'message_id': str (SMTP mesaj ID),
            'message': str,
            'error': str (hata varsa)
        }
    """
    try:
        # Gateway seçimi
        if not gateway:
            gateway = get_default_email_gateway(hotel=hotel)
        
        if not gateway:
            return {
                'success': False,
                'message': 'Aktif email gateway bulunamadı',
                'error': 'Lütfen bir email gateway yapılandırın'
            }
        
        # Template kullanılıyorsa içeriği render et
        if template:
            if context:
                rendered_subject, rendered_html, rendered_text = template.render(context)
                subject = rendered_subject
                html_content = rendered_html
                text_content = rendered_text
            else:
                subject = template.subject
                html_content = template.template_html
                text_content = template.template_text
        
        # Email gönderim logu oluştur
        log = EmailSentLog.objects.create(
            gateway=gateway,
            template=template,
            recipient_email=to_email,
            recipient_name=to_name or '',
            subject=subject,
            message_html=html_content,
            message_text=text_content,
            status='pending',
            related_module=related_module or '',
            related_object_id=related_object_id,
            related_object_type=related_object_type or '',
            context_data=context or {}
        )
        
        # Gateway instance oluştur
        gateway_instance = get_email_gateway_instance(gateway)
        
        # Email gönder
        result = gateway_instance.send_email(
            to_email=to_email,
            subject=subject,
            html_content=html_content,
            text_content=text_content,
            to_name=to_name,
            cc=cc,
            bcc=bcc,
            attachments=attachments
        )
        
        # Log'u güncelle
        if result['success']:
            log.status = 'sent'
            log.message_id = result.get('message_id', '')
            log.sent_at = timezone.now()
            log.smtp_response = result.get('smtp_response', {})
            
            # Gateway istatistiklerini güncelle
            gateway.total_sent += 1
            gateway.last_sent_at = timezone.now()
            gateway.save(update_fields=['total_sent', 'last_sent_at'])
        else:
            log.status = 'failed'
            log.error_message = result.get('error', 'Bilinmeyen hata')
            log.smtp_response = result.get('smtp_response', {})
            
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
        logger.error(f"Email gönderim hatası: {str(e)}", exc_info=True)
        return {
            'success': False,
            'message': 'Email gönderilirken beklenmeyen hata oluştu',
            'error': str(e)
        }


def send_email_by_template(
    template_code: str,
    to_email: str,
    context: Dict,
    gateway: Optional[EmailGateway] = None,
    hotel=None,
    to_name: Optional[str] = None,
    **kwargs
) -> Dict:
    """
    Template kodu ile email gönder
    
    Args:
        template_code: EmailTemplate code
        to_email: Alıcı email adresi
        context: Template context dict
        gateway: EmailGateway instance (opsiyonel)
        to_name: Alıcı adı (opsiyonel)
        **kwargs: send_email fonksiyonuna geçirilecek ek parametreler
    
    Returns:
        send_email fonksiyonunun döndürdüğü dict
    """
    try:
        template = EmailTemplate.objects.get(code=template_code, is_active=True)
        return send_email(
            to_email=to_email,
            subject=None,  # Template'ten alınacak
            html_content=None,  # Template'ten alınacak
            text_content=None,  # Template'ten alınacak
            template=template,
            context=context,
            gateway=gateway,
            hotel=hotel,
            to_name=to_name,
            **kwargs
        )
    except EmailTemplate.DoesNotExist:
        return {
            'success': False,
            'message': f'Email şablonu bulunamadı: {template_code}',
            'error': f'Template code: {template_code}'
        }
    except Exception as e:
        logger.error(f"Template ile email gönderim hatası: {str(e)}", exc_info=True)
        return {
            'success': False,
            'message': 'Email gönderilirken hata oluştu',
            'error': str(e)
        }

