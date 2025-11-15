from django.db import models
from django.utils import timezone


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



