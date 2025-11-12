"""
Celery tasks - Subscription management
Otomatik abonelik takibi ve hatırlatma e-postaları
"""
from celery import shared_task
from django.utils import timezone
from datetime import timedelta
from django.core.mail import send_mail
from django.conf import settings
from .models import Subscription


@shared_task
def check_expired_subscriptions():
    """
    Süresi dolan abonelikleri kontrol et ve pasif yap
    Her gece saat 02:00'de çalışır (celery.py'de tanımlı)
    """
    today = timezone.now().date()
    expired_count = 0
    
    # Aktif ama süresi dolmuş abonelikler
    expired_subscriptions = Subscription.objects.filter(
        status='active',
        end_date__lt=today
    )
    
    for subscription in expired_subscriptions:
        # Aboneliği pasif yap
        subscription.status = 'expired'
        subscription.save()
        
        # Tenant'ı pasif yap
        subscription.tenant.is_active = False
        subscription.tenant.save()
        
        # E-posta gönder
        send_subscription_expired_email(subscription)
        
        expired_count += 1
    
    return f"{expired_count} abonelik pasif yapıldı"


@shared_task
def send_subscription_reminders():
    """
    Abonelik bitişine yakın olan tenantlara hatırlatma e-postası gönder
    Her gün saat 09:00'da çalışır (celery.py'de tanımlı)
    """
    today = timezone.now().date()
    reminder_days = [7, 3, 1]  # 7, 3 ve 1 gün önce hatırlat
    sent_count = 0
    
    for days in reminder_days:
        target_date = today + timedelta(days=days)
        
        # Bitiş tarihi target_date olan aktif abonelikler
        subscriptions = Subscription.objects.filter(
            status='active',
            end_date=target_date,
            auto_renew=False  # Otomatik yenileme kapalıysa hatırlat
        )
        
        for subscription in subscriptions:
            send_subscription_reminder_email(subscription, days)
            sent_count += 1
    
    return f"{sent_count} hatırlatma e-postası gönderildi"


def send_subscription_expired_email(subscription):
    """
    Abonelik süresi dolunca e-posta gönder
    """
    subject = f"[{settings.SITE_NAME}] Aboneliğinizin Süresi Doldu"
    message = f"""
Merhaba {subscription.tenant.owner_name},

{subscription.tenant.name} için {subscription.package.name} paketine ait aboneliğinizin süresi {subscription.end_date} tarihinde dolmuştur.

Hizmetlerimizden kesintisiz yararlanmaya devam etmek için aboneliğinizi yenileyebilirsiniz.

Aboneliğinizi yenilemek için: {settings.SITE_URL}/dashboard/subscription/

Teşekkürler,
{settings.SITE_NAME} Ekibi
    """
    
    send_mail(
        subject,
        message,
        settings.DEFAULT_FROM_EMAIL,
        [subscription.tenant.owner_email],
        fail_silently=True,
    )


def send_subscription_reminder_email(subscription, days_remaining):
    """
    Abonelik bitişinden önce hatırlatma e-postası gönder
    """
    subject = f"[{settings.SITE_NAME}] Aboneliğiniz {days_remaining} Gün İçinde Sona Erecek"
    message = f"""
Merhaba {subscription.tenant.owner_name},

{subscription.tenant.name} için {subscription.package.name} paketine ait aboneliğiniz {days_remaining} gün içinde sona erecektir.

Bitiş Tarihi: {subscription.end_date}

Hizmetlerimizden kesintisiz yararlanmaya devam etmek için aboneliğinizi şimdi yenileyebilirsiniz.

Aboneliğinizi yenilemek için: {settings.SITE_URL}/dashboard/subscription/

Teşekkürler,
{settings.SITE_NAME} Ekibi
    """
    
    send_mail(
        subject,
        message,
        settings.DEFAULT_FROM_EMAIL,
        [subscription.tenant.owner_email],
        fail_silently=True,
    )


@shared_task
def auto_renew_subscriptions():
    """
    Otomatik yenileme açık olan abonelikleri yenile
    """
    today = timezone.now().date()
    renewed_count = 0
    
    # Bugün bitecek ve otomatik yenileme açık olan abonelikler
    subscriptions = Subscription.objects.filter(
        status='active',
        end_date=today,
        auto_renew=True
    )
    
    for subscription in subscriptions:
        try:
            # Stripe ile ödeme al (TODO: Implement)
            # process_stripe_payment(subscription)
            
            # Aboneliği yenile
            subscription.renew()
            renewed_count += 1
            
            # Başarı e-postası gönder
            send_renewal_success_email(subscription)
            
        except Exception as e:
            # Hata durumunda e-posta gönder
            send_renewal_failed_email(subscription, str(e))
    
    return f"{renewed_count} abonelik otomatik yenilendi"


def send_renewal_success_email(subscription):
    """Yenileme başarılı olunca e-posta gönder"""
    subject = f"[{settings.SITE_NAME}] Aboneliğiniz Yenilendi"
    message = f"""
Merhaba {subscription.tenant.owner_name},

{subscription.tenant.name} için {subscription.package.name} paketine ait aboneliğiniz başarıyla yenilenmiştir.

Yeni Bitiş Tarihi: {subscription.end_date}
Tutar: {subscription.amount} {subscription.currency}

Teşekkürler,
{settings.SITE_NAME} Ekibi
    """
    
    send_mail(
        subject,
        message,
        settings.DEFAULT_FROM_EMAIL,
        [subscription.tenant.owner_email],
        fail_silently=True,
    )


def send_renewal_failed_email(subscription, error_message):
    """Yenileme başarısız olunca e-posta gönder"""
    subject = f"[{settings.SITE_NAME}] Abonelik Yenileme Başarısız"
    message = f"""
Merhaba {subscription.tenant.owner_name},

{subscription.tenant.name} için {subscription.package.name} paketine ait aboneliğiniz yenilenirken bir hata oluştu.

Hata: {error_message}

Lütfen ödeme bilgilerinizi kontrol edin ve manuel olarak yenileyin.

Aboneliğinizi yenilemek için: {settings.SITE_URL}/dashboard/subscription/

Teşekkürler,
{settings.SITE_NAME} Ekibi
    """
    
    send_mail(
        subject,
        message,
        settings.DEFAULT_FROM_EMAIL,
        [subscription.tenant.owner_email],
        fail_silently=True,
    )



