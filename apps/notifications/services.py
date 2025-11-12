"""
Bildirim Servisleri
Bildirim gönderme işlemlerini yönetir
"""
import logging
from typing import Dict, Any, List, Optional
from django.utils import timezone
from .models import NotificationProvider, NotificationProviderConfig, NotificationTemplate, NotificationLog
from .providers import EmailProvider, NetGSMProvider, VerimorProvider, WhatsAppProvider

logger = logging.getLogger(__name__)


def get_provider_instance(provider_code: str, config_id: Optional[int] = None):
    """
    Bildirim sağlayıcı instance'ı al
    
    Args:
        provider_code: Sağlayıcı kodu (email, sms_netgsm, sms_verimor, whatsapp)
        config_id: Yapılandırma ID (opsiyonel, aktif olanı kullanır)
    
    Returns:
        Provider instance veya None
    """
    try:
        provider = NotificationProvider.objects.get(code=provider_code, is_active=True)
        
        # Yapılandırmayı al
        if config_id:
            config_obj = NotificationProviderConfig.objects.get(id=config_id, provider=provider, is_active=True)
        else:
            config_obj = NotificationProviderConfig.objects.filter(provider=provider, is_active=True).first()
        
        if not config_obj:
            logger.warning(f"No active config found for provider: {provider_code}")
            return None
        
        # Config dict oluştur
        config_dict = {
            'is_test_mode': config_obj.is_test_mode,
            'api_key': config_obj.api_key,
            'api_secret': config_obj.api_secret,
            'username': config_obj.username,
            'password': config_obj.password,
            'sender_id': config_obj.sender_id,
            # WhatsApp
            'whatsapp_business_id': config_obj.whatsapp_business_id,
            'whatsapp_phone_number_id': config_obj.whatsapp_phone_number_id,
            'whatsapp_access_token': config_obj.whatsapp_access_token,
            'whatsapp_verify_token': config_obj.whatsapp_verify_token,
            # SMS
            'sms_username': config_obj.sms_username,
            'sms_password': config_obj.sms_password,
            'sms_header': config_obj.sms_header,
            # Email
            'email_host': config_obj.email_host,
            'email_port': config_obj.email_port,
            'email_use_tls': config_obj.email_use_tls,
            'email_use_ssl': config_obj.email_use_ssl,
            'email_from': config_obj.email_from,
            'email_from_name': config_obj.email_from_name,
        }
        
        # Provider instance oluştur
        if provider_code == 'email':
            return EmailProvider(config_dict)
        elif provider_code == 'sms_netgsm':
            return NetGSMProvider(config_dict)
        elif provider_code == 'sms_verimor':
            return VerimorProvider(config_dict)
        elif provider_code == 'whatsapp':
            return WhatsAppProvider(config_dict)
        else:
            logger.error(f"Unknown provider code: {provider_code}")
            return None
            
    except NotificationProvider.DoesNotExist:
        logger.error(f"Provider not found: {provider_code}")
        return None
    except Exception as e:
        logger.error(f"Error getting provider instance: {str(e)}")
        return None


def send_notification(
    provider_code: str,
    recipient: str,
    template_code: Optional[str] = None,
    subject: str = '',
    content: str = '',
    variables: Optional[Dict[str, Any]] = None,
    **kwargs
) -> Dict[str, Any]:
    """
    Bildirim gönder
    
    Args:
        provider_code: Sağlayıcı kodu
        recipient: Alıcı (email veya telefon)
        template_code: Şablon kodu (opsiyonel)
        subject: Konu
        content: İçerik
        variables: Şablon değişkenleri
        **kwargs: Ek parametreler
    
    Returns:
        {
            'success': bool,
            'log_id': int,
            'message_id': str,
            'error': str (varsa)
        }
    """
    try:
        # Provider instance al
        provider_instance = get_provider_instance(provider_code)
        if not provider_instance:
            return {
                'success': False,
                'error': 'Provider instance oluşturulamadı',
            }
        
        # Şablon varsa içeriği al
        if template_code:
            try:
                template = NotificationTemplate.objects.get(code=template_code, is_active=True)
                subject = template.subject or subject
                content = template.content
                content_html = template.content_html
                
                # Değişkenleri doldur
                if variables:
                    content = provider_instance.format_content(content, variables)
                    if content_html:
                        content_html = provider_instance.format_content(content_html, variables)
            except NotificationTemplate.DoesNotExist:
                logger.warning(f"Template not found: {template_code}")
        
        # Provider ve template bilgilerini al
        provider = NotificationProvider.objects.get(code=provider_code)
        template_obj = NotificationTemplate.objects.get(code=template_code) if template_code else None
        
        # Log kaydı oluştur
        log = NotificationLog.objects.create(
            provider=provider,
            template=template_obj,
            recipient_type='email' if '@' in recipient else 'phone',
            recipient_email=recipient if '@' in recipient else '',
            recipient_phone=recipient if '@' not in recipient else '',
            subject=subject,
            content=content,
            content_html=kwargs.get('content_html', ''),
            status='pending',
        )
        
        # Bildirim gönder
        result = provider_instance.send(
            recipient=recipient,
            subject=subject,
            content=content,
            content_html=kwargs.get('content_html', ''),
            **kwargs
        )
        
        # Log'u güncelle
        if result.get('success'):
            log.status = 'sent'
            log.sent_at = timezone.now()
            log.provider_message_id = result.get('message_id', '')
            log.provider_response = result
            log.save()
            
            # Config istatistiklerini güncelle
            config = NotificationProviderConfig.objects.filter(provider=provider, is_active=True).first()
            if config:
                config.total_sent += 1
                config.last_used_at = timezone.now()
                config.save()
        else:
            log.status = 'failed'
            log.error_message = result.get('error', '')
            log.provider_response = result
            log.save()
            
            # Config istatistiklerini güncelle
            config = NotificationProviderConfig.objects.filter(provider=provider, is_active=True).first()
            if config:
                config.total_failed += 1
                config.save()
        
        return {
            'success': result.get('success', False),
            'log_id': log.id,
            'message_id': result.get('message_id', ''),
            'error': result.get('error', ''),
        }
        
    except Exception as e:
        logger.error(f"Error sending notification: {str(e)}", exc_info=True)
        return {
            'success': False,
            'error': str(e),
        }

