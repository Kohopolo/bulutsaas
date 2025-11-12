"""
Tenant Core Models
Kiracı üye paneli için kullanıcı, rol ve yetki yönetimi
Merkezi müşteri yönetimi (CRM)
"""
from django.db import models
from django.contrib.auth.models import User, AbstractUser
from django.conf import settings
from django.core.validators import MinValueValidator
from decimal import Decimal
from apps.core.models import TimeStampedModel, SoftDeleteModel
from apps.modules.models import Module


class TenantUser(TimeStampedModel):
    """
    Tenant'a özel kullanıcı modeli
    Django User'ı extend eder, tenant bazlı kullanıcı bilgilerini tutar
    """
    user = models.OneToOneField(
        User,
        on_delete=models.CASCADE,
        related_name='tenant_profile',
        verbose_name='Django User'
    )
    
    # Tenant bilgisi (django-tenants ile otomatik)
    # tenant = models.ForeignKey('tenants.Tenant', ...) - django-tenants otomatik ekler
    
    # Kullanıcı Tipi
    user_type = models.ForeignKey(
        'UserType',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='users',
        verbose_name='Kullanıcı Tipi'
    )
    
    # Ek Bilgiler
    phone = models.CharField('Telefon', max_length=20, blank=True)
    department = models.CharField('Departman', max_length=100, blank=True)
    position = models.CharField('Pozisyon', max_length=100, blank=True)
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    last_login_at = models.DateTimeField('Son Giriş', null=True, blank=True)
    
    # Ayarlar
    settings = models.JSONField('Kullanıcı Ayarları', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Tenant Kullanıcı'
        verbose_name_plural = 'Tenant Kullanıcıları'
        ordering = ['user__last_name', 'user__first_name']
    
    def __str__(self):
        return f"{self.user.get_full_name() or self.user.username} ({self.user_type.name if self.user_type else 'Tip Yok'})"
    
    def get_roles(self):
        """Kullanıcının rollerini döndür"""
        return self.user_roles.filter(is_active=True).select_related('role')
    
    def has_module_permission(self, module_code, permission_code):
        """Kullanıcının belirli bir modülde yetkisi var mı kontrol et"""
        # Önce direkt kullanıcı yetkilerini kontrol et
        if self.user_permissions.filter(
            permission__module__code=module_code,
            permission__code=permission_code,
            is_active=True
        ).exists():
            return True
        
        # Sonra rol bazlı yetkileri kontrol et
        roles = self.get_roles()
        for user_role in roles:
            if user_role.role.has_module_permission(module_code, permission_code):
                return True
        return False
    
    def get_direct_permissions(self):
        """Kullanıcının direkt atanmış yetkilerini döndür"""
        return self.user_permissions.filter(is_active=True).select_related('permission', 'permission__module')


class UserType(TimeStampedModel, SoftDeleteModel):
    """
    Kullanıcı Tipi Modeli
    Örnek: Resepsiyon, Satış, Housekeeping, Yönetici vb.
    Dinamik olarak eklenip silinebilir
    """
    name = models.CharField('Kullanıcı Tipi Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, default='👤', help_text='Emoji veya Font Awesome class')
    
    # Panel Yönlendirme
    dashboard_url = models.CharField('Dashboard URL', max_length=200, blank=True, 
                                     help_text='Bu kullanıcı tipi için özel dashboard URL\'i')
    panel_template = models.CharField('Panel Template', max_length=200, blank=True,
                                      help_text='Bu kullanıcı tipi için özel template')
    
    # Varsayılan Rol
    default_role = models.ForeignKey(
        'Role',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='default_for_user_types',
        verbose_name='Varsayılan Rol'
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar
    settings = models.JSONField('Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Kullanıcı Tipi'
        verbose_name_plural = 'Kullanıcı Tipleri'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return f"{self.icon} {self.name}"


class Role(TimeStampedModel, SoftDeleteModel):
    """
    Rol Modeli
    Örnek: Admin, Manager, Staff, Resepsiyonist, Satış Temsilcisi vb.
    Dinamik olarak eklenip silinebilir
    """
    name = models.CharField('Rol Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50, unique=True)
    description = models.TextField('Açıklama', blank=True)
    icon = models.CharField('İkon', max_length=50, default='🛡️', help_text='Emoji veya Font Awesome class')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_system = models.BooleanField('Sistem Rolü mü?', default=False, 
                                     help_text='Sistem rolleri silinemez')
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar
    settings = models.JSONField('Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Rol'
        verbose_name_plural = 'Roller'
        ordering = ['sort_order', 'name']
    
    def __str__(self):
        return f"{self.icon} {self.name}"
    
    def has_module_permission(self, module_code, permission_code):
        """Rolün belirli bir modülde yetkisi var mı kontrol et"""
        return self.role_permissions.filter(
            permission__module__code=module_code,
            permission__code=permission_code,
            is_active=True
        ).exists()
    
    def get_module_permissions(self, module_code):
        """Rolün belirli bir modüldeki tüm yetkilerini döndür"""
        return self.role_permissions.filter(
            permission__module__code=module_code,
            is_active=True
        ).select_related('permission')


class Permission(TimeStampedModel, SoftDeleteModel):
    """
    Yetki Modeli
    Modül bazında yetkiler: view, add, edit, delete, export, import vb.
    Dinamik olarak eklenip silinebilir
    """
    module = models.ForeignKey(
        Module,
        on_delete=models.CASCADE,
        related_name='module_permissions',
        verbose_name='Modül'
    )
    
    name = models.CharField('Yetki Adı', max_length=100)
    code = models.SlugField('Kod', max_length=50)
    description = models.TextField('Açıklama', blank=True)
    
    # Yetki Tipi
    PERMISSION_TYPE_CHOICES = [
        ('view', 'Görüntüleme'),
        ('add', 'Ekleme'),
        ('edit', 'Düzenleme'),
        ('delete', 'Silme'),
        ('export', 'Dışa Aktarma'),
        ('import', 'İçe Aktarma'),
        ('approve', 'Onaylama'),
        ('cancel', 'İptal Etme'),
        ('other', 'Diğer'),
    ]
    permission_type = models.CharField('Yetki Tipi', max_length=20, choices=PERMISSION_TYPE_CHOICES, default='other')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    is_system = models.BooleanField('Sistem Yetkisi mi?', default=False,
                                     help_text='Sistem yetkileri silinemez')
    sort_order = models.IntegerField('Sıralama', default=0)
    
    # Ayarlar
    settings = models.JSONField('Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Yetki'
        verbose_name_plural = 'Yetkiler'
        unique_together = ('module', 'code')
        ordering = ['module', 'sort_order', 'name']
    
    def __str__(self):
        return f"{self.module.name} - {self.name} ({self.code})"


class UserRole(TimeStampedModel):
    """
    Kullanıcı-Rol İlişkisi
    Bir kullanıcıya birden fazla rol atanabilir
    """
    tenant_user = models.ForeignKey(
        TenantUser,
        on_delete=models.CASCADE,
        related_name='user_roles',
        verbose_name='Kullanıcı'
    )
    role = models.ForeignKey(
        Role,
        on_delete=models.CASCADE,
        related_name='user_roles',
        verbose_name='Rol'
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    assigned_at = models.DateTimeField('Atanma Tarihi', auto_now_add=True)
    assigned_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='assigned_roles',
        verbose_name='Atayan Kullanıcı'
    )
    
    # Ayarlar
    settings = models.JSONField('Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Kullanıcı Rolü'
        verbose_name_plural = 'Kullanıcı Rolleri'
        unique_together = ('tenant_user', 'role')
        ordering = ['tenant_user', 'role']
    
    def __str__(self):
        return f"{self.tenant_user} - {self.role.name}"


class RolePermission(TimeStampedModel):
    """
    Rol-Yetki İlişkisi
    Bir role birden fazla yetki atanabilir
    """
    role = models.ForeignKey(
        Role,
        on_delete=models.CASCADE,
        related_name='role_permissions',
        verbose_name='Rol'
    )
    permission = models.ForeignKey(
        Permission,
        on_delete=models.CASCADE,
        related_name='role_permissions',
        verbose_name='Yetki'
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    assigned_at = models.DateTimeField('Atanma Tarihi', auto_now_add=True)
    
    # Özel Ayarlar (JSON)
    # Örnek: {"limit": 100, "restrictions": ["own_records_only"]}
    settings = models.JSONField('Özel Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Rol Yetkisi'
        verbose_name_plural = 'Rol Yetkileri'
        unique_together = ('role', 'permission')
        ordering = ['role', 'permission']
    
    def __str__(self):
        return f"{self.role.name} - {self.permission.name}"


class UserPermission(TimeStampedModel):
    """
    Kullanıcı-Yetki İlişkisi
    Bir kullanıcıya direkt olarak yetki atanabilir (rol bazlı değil)
    """
    tenant_user = models.ForeignKey(
        TenantUser,
        on_delete=models.CASCADE,
        related_name='user_permissions',
        verbose_name='Kullanıcı'
    )
    permission = models.ForeignKey(
        Permission,
        on_delete=models.CASCADE,
        related_name='user_permissions',
        verbose_name='Yetki'
    )
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    assigned_at = models.DateTimeField('Atanma Tarihi', auto_now_add=True)
    assigned_by = models.ForeignKey(
        User,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='assigned_permissions',
        verbose_name='Atayan Kullanıcı'
    )
    
    # Özel Ayarlar (JSON)
    # Örnek: {"limit": 100, "restrictions": ["own_records_only"]}
    settings = models.JSONField('Özel Ayarlar', default=dict, blank=True)
    
    class Meta:
        verbose_name = 'Kullanıcı Yetkisi'
        verbose_name_plural = 'Kullanıcı Yetkileri'
        unique_together = ('tenant_user', 'permission')
        ordering = ['tenant_user', 'permission']
    
    def __str__(self):
        return f"{self.tenant_user} - {self.permission.name}"


# ==================== MERKEZİ MÜŞTERİ YÖNETİMİ (CRM) ====================

class Customer(TimeStampedModel, SoftDeleteModel):
    """
    Merkezi Müşteri Modeli - CRM
    Tüm modüllerde kullanılacak merkezi müşteri kaydı
    TC No, Email ve Telefon ile otomatik eşleştirme yapılabilir
    """
    
    # Temel Bilgiler
    customer_code = models.CharField('Müşteri Kodu', max_length=50, unique=True, db_index=True)
    first_name = models.CharField('Ad', max_length=100)
    last_name = models.CharField('Soyad', max_length=100)
    
    # Eşleştirme Alanları (TC No, Email, Telefon)
    email = models.EmailField('E-posta', db_index=True, help_text='Müşteri eşleştirme için kullanılır')
    phone = models.CharField('Telefon', max_length=20, db_index=True, help_text='Müşteri eşleştirme için kullanılır')
    tc_no = models.CharField('TC Kimlik No', max_length=11, blank=True, db_index=True, 
                            help_text='Müşteri eşleştirme için kullanılır')
    
    # İletişim Bilgileri
    address = models.TextField('Adres', blank=True)
    city = models.CharField('Şehir', max_length=100, blank=True)
    country = models.CharField('Ülke', max_length=100, default='Türkiye')
    postal_code = models.CharField('Posta Kodu', max_length=10, blank=True)
    
    # Doğum Tarihi ve Özel Günler
    birth_date = models.DateField('Doğum Tarihi', null=True, blank=True)
    special_dates = models.JSONField('Özel Günler', default=list, blank=True,
                                     help_text='Örn: [{"date": "2024-12-25", "name": "Evlilik Yıldönümü"}]')
    
    # Sadakat Sistemi (Genel - Tüm Modüller İçin)
    loyalty_points = models.IntegerField('Sadakat Puanı', default=0, validators=[MinValueValidator(0)])
    total_reservations = models.IntegerField('Toplam Rezervasyon', default=0, 
                                            help_text='Tüm modüllerden toplam rezervasyon sayısı')
    total_spent = models.DecimalField('Toplam Harcama', max_digits=12, decimal_places=2, default=0,
                                     help_text='Tüm modüllerden toplam harcama')
    
    # VIP Statüsü (Genel)
    VIP_LEVEL_CHOICES = [
        ('regular', 'Normal'),
        ('silver', 'Gümüş (5+ rezervasyon)'),
        ('gold', 'Altın (10+ rezervasyon)'),
        ('platinum', 'Platin (20+ rezervasyon)'),
        ('diamond', 'Elmas (50+ rezervasyon)'),
    ]
    vip_level = models.CharField('VIP Seviyesi', max_length=20, choices=VIP_LEVEL_CHOICES, default='regular')
    is_vip = models.BooleanField('VIP Müşteri mi?', default=False)
    
    # Notlar ve İstekler
    notes = models.TextField('Notlar', blank=True, help_text='Müşteri hakkında özel notlar')
    special_requests = models.TextField('Özel İstekler', blank=True, help_text='Müşterinin özel istekleri')
    
    # Durum
    is_active = models.BooleanField('Aktif mi?', default=True)
    last_reservation_date = models.DateField('Son Rezervasyon Tarihi', null=True, blank=True)
    
    # İletişim Tercihleri
    preferred_contact_method = models.CharField('Tercih Edilen İletişim Yöntemi', max_length=20, 
                                                choices=[('email', 'E-posta'), ('phone', 'Telefon'), ('sms', 'SMS'), ('whatsapp', 'WhatsApp')],
                                                default='email', blank=True)
    allow_marketing = models.BooleanField('Pazarlama İletişimine İzin Ver', default=True)
    
    class Meta:
        verbose_name = 'Müşteri'
        verbose_name_plural = 'Müşteriler'
        ordering = ['-total_spent', '-created_at']
        indexes = [
            models.Index(fields=['email']),
            models.Index(fields=['phone']),
            models.Index(fields=['tc_no']),
            models.Index(fields=['customer_code']),
            models.Index(fields=['vip_level', 'is_active']),
            models.Index(fields=['email', 'phone', 'tc_no']),  # Eşleştirme için composite index
        ]
        constraints = [
            models.UniqueConstraint(
                fields=['email'],
                condition=models.Q(is_deleted=False),
                name='unique_active_customer_email'
            ),
        ]
    
    def __str__(self):
        return f"{self.first_name} {self.last_name} ({self.customer_code})"
    
    def get_full_name(self):
        """Müşterinin tam adını döndür"""
        return f"{self.first_name} {self.last_name}".strip()
    
    def save(self, *args, **kwargs):
        """Müşteri kaydedilirken otomatik işlemler"""
        # Müşteri kodu oluştur
        if not self.customer_code:
            import random
            import string
            self.customer_code = f"CUST{''.join(random.choices(string.ascii_uppercase + string.digits, k=8))}"
        
        # VIP seviyesini güncelle
        if self.total_reservations >= 50:
            self.vip_level = 'diamond'
            self.is_vip = True
        elif self.total_reservations >= 20:
            self.vip_level = 'platinum'
            self.is_vip = True
        elif self.total_reservations >= 10:
            self.vip_level = 'gold'
            self.is_vip = True
        elif self.total_reservations >= 5:
            self.vip_level = 'silver'
            self.is_vip = True
        else:
            self.vip_level = 'regular'
            self.is_vip = False
        
        super().save(*args, **kwargs)
    
    @classmethod
    def find_by_identifier(cls, email=None, phone=None, tc_no=None):
        """
        TC No, Email veya Telefon ile müşteri bul
        Öncelik sırası: TC No > Email > Telefon
        """
        if tc_no and tc_no.strip():
            customer = cls.objects.filter(tc_no=tc_no, is_deleted=False).first()
            if customer:
                return customer
        
        if email and email.strip():
            customer = cls.objects.filter(email__iexact=email.strip(), is_deleted=False).first()
            if customer:
                return customer
        
        if phone and phone.strip():
            # Telefon numarasını normalize et (boşluk, tire vb. kaldır)
            normalized_phone = ''.join(filter(str.isdigit, phone))
            customer = cls.objects.filter(phone__contains=normalized_phone, is_deleted=False).first()
            if customer:
                return customer
        
        return None
    
    @classmethod
    def get_or_create_by_identifier(cls, email=None, phone=None, tc_no=None, defaults=None):
        """
        TC No, Email veya Telefon ile müşteri bul veya oluştur
        """
        customer = cls.find_by_identifier(email=email, phone=phone, tc_no=tc_no)
        
        if customer:
            # Mevcut müşteriyi güncelle (varsayılan değerlerle)
            if defaults:
                for key, value in defaults.items():
                    if not getattr(customer, key, None) or getattr(customer, key) == '':
                        setattr(customer, key, value)
                customer.save()
            return customer, False
        
        # Yeni müşteri oluştur
        create_data = defaults or {}
        if email:
            create_data['email'] = email
        if phone:
            create_data['phone'] = phone
        if tc_no:
            create_data['tc_no'] = tc_no
        
        customer = cls.objects.create(**create_data)
        return customer, True
    
    def add_loyalty_points(self, points, reason='', module=''):
        """Sadakat puanı ekle"""
        self.loyalty_points += points
        self.save()
        
        # Puan geçmişi kaydet
        CustomerLoyaltyHistory.objects.create(
            customer=self,
            points=points,
            reason=reason or 'Rezervasyon',
            module=module,
        )
    
    def use_loyalty_points(self, points):
        """Sadakat puanı kullan"""
        if self.loyalty_points >= points:
            self.loyalty_points -= points
            self.save()
            
            # Puan geçmişi kaydet
            CustomerLoyaltyHistory.objects.create(
                customer=self,
                points=-points,
                reason='Puan kullanımı',
            )
            return True
        return False
    
    def get_loyalty_discount(self):
        """Sadakat puanına göre indirim hesapla (100 puan = %1 indirim, max %10)"""
        discount_percentage = min(10, self.loyalty_points // 100)
        return discount_percentage
    
    def update_statistics(self):
        """Müşteri istatistiklerini güncelle (tüm modüllerden)"""
        # Bu metod modül bazlı istatistikleri toplayacak
        # Şimdilik placeholder, modül entegrasyonlarından sonra doldurulacak
        pass


class CustomerLoyaltyHistory(TimeStampedModel):
    """Sadakat Puanı Geçmişi"""
    customer = models.ForeignKey(Customer, on_delete=models.CASCADE, related_name='loyalty_history', verbose_name='Müşteri')
    points = models.IntegerField('Puan', help_text='Pozitif = Ekleme, Negatif = Kullanım')
    reason = models.CharField('Sebep', max_length=200, blank=True)
    module = models.CharField('Modül', max_length=50, blank=True, help_text='Hangi modülden geldi (tours, hotels, vb.)')
    reference_id = models.IntegerField('Referans ID', null=True, blank=True, 
                                      help_text='Rezervasyon, işlem vb. ID')
    reference_type = models.CharField('Referans Tipi', max_length=50, blank=True,
                                      help_text='reservation, payment, refund vb.')
    
    class Meta:
        verbose_name = 'Sadakat Puanı Geçmişi'
        verbose_name_plural = 'Sadakat Puanı Geçmişleri'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.customer} - {self.points} puan ({self.reason})"


class CustomerNote(TimeStampedModel):
    """Müşteri Notları"""
    customer = models.ForeignKey(Customer, on_delete=models.CASCADE, related_name='notes_history', verbose_name='Müşteri')
    note = models.TextField('Not')
    note_type = models.CharField('Not Tipi', max_length=50, default='general',
                                 choices=[('general', 'Genel'), ('complaint', 'Şikayet'), 
                                         ('request', 'İstek'), ('important', 'Önemli')])
    created_by = models.ForeignKey(
        'auth.User',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='customer_notes',
        verbose_name='Oluşturan'
    )
    is_important = models.BooleanField('Önemli mi?', default=False)
    
    class Meta:
        verbose_name = 'Müşteri Notu'
        verbose_name_plural = 'Müşteri Notları'
        ordering = ['-created_at']
    
    def __str__(self):
        return f"{self.customer} - {self.note[:50]}..."
