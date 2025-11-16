from django.db import models
from django.utils import timezone
from django.utils.html import format_html


class TimeStampedModel(models.Model):
    """
    Tüm modeller için ortak zaman damgası alanları
    """
    created_at = models.DateTimeField('Oluşturulma Tarihi', auto_now_add=True)
    updated_at = models.DateTimeField('Güncellenme Tarihi', auto_now=True)

    class Meta:
        abstract = True


class SoftDeleteModel(models.Model):
    """
    Soft delete (yumuşak silme) özelliği
    """
    is_deleted = models.BooleanField('Silinmiş mi?', default=False)
    deleted_at = models.DateTimeField('Silinme Tarihi', null=True, blank=True)

    class Meta:
        abstract = True

    def soft_delete(self):
        """Kaydı soft delete yap"""
        self.is_deleted = True
        self.deleted_at = timezone.now()
        self.save()

    def restore(self):
        """Silinen kaydı geri getir"""
        self.is_deleted = False
        self.deleted_at = None
        self.save()


class Announcement(TimeStampedModel, SoftDeleteModel):
    """
    Admin Dashboard için Duyurular/Haberler
    """
    title = models.CharField('Başlık', max_length=200)
    content = models.TextField('İçerik (HTML)', help_text='HTML formatında içerik girebilirsiniz')
    is_active = models.BooleanField('Aktif mi?', default=True)
    priority = models.IntegerField('Öncelik', default=0, help_text='Yüksek sayı önce gösterilir')
    start_date = models.DateTimeField('Başlangıç Tarihi', null=True, blank=True)
    end_date = models.DateTimeField('Bitiş Tarihi', null=True, blank=True)
    created_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        verbose_name='Oluşturan'
    )

    class Meta:
        verbose_name = 'Duyuru'
        verbose_name_plural = 'Duyurular'
        ordering = ['-priority', '-created_at']

    def __str__(self):
        return self.title

    def is_visible(self):
        """Şu anda görünür mü?"""
        if not self.is_active or self.is_deleted:
            return False
        
        now = timezone.now()
        if self.start_date and now < self.start_date:
            return False
        if self.end_date and now > self.end_date:
            return False
        
        return True


class Advertisement(TimeStampedModel, SoftDeleteModel):
    """
    Admin Dashboard için Reklamlar
    """
    title = models.CharField('Başlık', max_length=200)
    content = models.TextField('İçerik (HTML)', help_text='HTML formatında içerik girebilirsiniz')
    image = models.ImageField('Resim', upload_to='advertisements/', null=True, blank=True)
    link_url = models.URLField('Link URL', blank=True, help_text='Tıklanınca gidilecek URL')
    link_text = models.CharField('Link Metni', max_length=100, blank=True)
    is_active = models.BooleanField('Aktif mi?', default=True)
    priority = models.IntegerField('Öncelik', default=0, help_text='Yüksek sayı önce gösterilir')
    start_date = models.DateTimeField('Başlangıç Tarihi', null=True, blank=True)
    end_date = models.DateTimeField('Bitiş Tarihi', null=True, blank=True)
    created_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        verbose_name='Oluşturan'
    )

    class Meta:
        verbose_name = 'Reklam'
        verbose_name_plural = 'Reklamlar'
        ordering = ['-priority', '-created_at']

    def __str__(self):
        return self.title

    def is_visible(self):
        """Şu anda görünür mü?"""
        if not self.is_active or self.is_deleted:
            return False
        
        now = timezone.now()
        if self.start_date and now < self.start_date:
            return False
        if self.end_date and now > self.end_date:
            return False
        
        return True

