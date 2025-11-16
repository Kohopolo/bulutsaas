"""
Tenant Domain Signals
Domain eklendiğinde/silindiğinde otomatik DNS kaydı oluşturma/silme
"""
from django.db.models.signals import post_save, post_delete
from django.dispatch import receiver
from django.conf import settings
from .models import Domain
import logging

logger = logging.getLogger(__name__)


@receiver(post_save, sender=Domain)
def create_dns_record(sender, instance, created, **kwargs):
    """Domain eklendiğinde DNS kaydı oluştur"""
    if not created:
        return
    
    # Sadece production'da çalıştır
    if settings.DEBUG:
        logger.info(f"DEBUG mode: DNS record creation skipped for {instance.domain}")
        return
    
    # Digital Ocean API token kontrolü
    import os
    if not os.getenv('DO_API_TOKEN'):
        logger.warning("DO_API_TOKEN not set. DNS record creation skipped.")
        return
    
    try:
        from .utils.dns_manager import DigitalOceanDNSManager
        
        dns_manager = DigitalOceanDNSManager()
        domain = instance.domain
        
        # Custom domain kontrolü
        if instance.domain_type == 'custom':
            # Custom domain'ler için domain sahibinin DNS ayarlarını yapması gerekir
            logger.info(
                f"Custom domain detected: {domain}. "
                "DNS configuration should be done by domain owner."
            )
            return
        
        # Subdomain için
        if instance.domain_type == 'subdomain':
            # Subdomain'i ana domain'den ayır
            # Örn: test-otel.yourdomain.com -> test-otel
            if '.' in domain:
                # Ana domain'i bul
                base_domain = dns_manager.domain
                if domain.endswith(base_domain):
                    subdomain = domain.replace(f'.{base_domain}', '').replace(base_domain, '')
                else:
                    # Domain farklıysa, ilk kısmı al
                    subdomain = domain.split('.')[0]
            else:
                subdomain = domain
            
            # Mevcut record kontrolü
            existing_record = dns_manager.find_record(subdomain, 'A')
            if existing_record:
                logger.info(f"DNS A record already exists for {subdomain}")
                # Record ID'yi kaydet
                instance.ssl_certificate = str(existing_record['id'])
                instance.save(update_fields=['ssl_certificate'])
                return
            
            # A record oluştur
            result = dns_manager.create_a_record(subdomain)
            logger.info(f"DNS A record created for {domain}: {result}")
            
            # Domain modeline record ID'yi kaydet (geçici olarak ssl_certificate alanında)
            if 'domain_record' in result:
                instance.ssl_certificate = str(result['domain_record']['id'])
                instance.save(update_fields=['ssl_certificate'])
    
    except ImportError:
        logger.warning("DigitalOceanDNSManager not available. Install python-digitalocean.")
    except Exception as e:
        logger.error(f"DNS record creation failed for {instance.domain}: {str(e)}")
        # Hata durumunda domain oluşturma işlemini durdurma (sadece log)


@receiver(post_delete, sender=Domain)
def delete_dns_record(sender, instance, **kwargs):
    """Domain silindiğinde DNS kaydını sil"""
    if settings.DEBUG:
        return
    
    import os
    if not os.getenv('DO_API_TOKEN'):
        return
    
    try:
        from .utils.dns_manager import DigitalOceanDNSManager
        
        dns_manager = DigitalOceanDNSManager()
        
        # Record ID'yi al (ssl_certificate alanında saklanmışsa)
        if instance.ssl_certificate and instance.ssl_certificate.isdigit():
            record_id = int(instance.ssl_certificate)
            dns_manager.delete_record(record_id)
            logger.info(f"DNS record deleted for {instance.domain}")
        else:
            # Record ID yoksa, domain adından bul
            if instance.domain_type == 'subdomain':
                if '.' in instance.domain:
                    base_domain = dns_manager.domain
                    if instance.domain.endswith(base_domain):
                        subdomain = instance.domain.replace(f'.{base_domain}', '').replace(base_domain, '')
                    else:
                        subdomain = instance.domain.split('.')[0]
                else:
                    subdomain = instance.domain
                
                record = dns_manager.find_record(subdomain, 'A')
                if record:
                    dns_manager.delete_record(record['id'])
                    logger.info(f"DNS record deleted for {instance.domain}")
    
    except ImportError:
        logger.warning("DigitalOceanDNSManager not available.")
    except Exception as e:
        logger.error(f"DNS record deletion failed for {instance.domain}: {str(e)}")

