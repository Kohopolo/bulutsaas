"""
Tenant Core Forms
"""
from django import forms
import json
from apps.core.widgets.json_widgets import ListWidget
from .models import TenantUser, UserType, Role, Permission, UserRole, RolePermission, UserPermission, Customer, CustomerNote


class TenantUserForm(forms.ModelForm):
    """TenantUser Form"""
    username = forms.CharField(label='Kullanıcı Adı', max_length=150, required=True)
    first_name = forms.CharField(label='Ad', max_length=30, required=False)
    last_name = forms.CharField(label='Soyad', max_length=30, required=False)
    email = forms.EmailField(label='E-posta', required=True)
    password = forms.CharField(label='Şifre', widget=forms.PasswordInput(), required=False)
    
    class Meta:
        model = TenantUser
        fields = ['user_type', 'phone', 'department', 'position', 'is_active']
        widgets = {
            'user_type': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'phone': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'department': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'position': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
        }
    
    def __init__(self, *args, **kwargs):
        self.instance_user = kwargs.pop('instance_user', None)
        super().__init__(*args, **kwargs)
        
        # Yeni kullanıcı oluşturulurken instance.user yok, bu yüzden hasattr kontrolü yapıyoruz
        if self.instance and self.instance.pk and hasattr(self.instance, 'user'):
            try:
                if self.instance.user:
                    self.fields['username'].initial = self.instance.user.username
                    self.fields['first_name'].initial = self.instance.user.first_name
                    self.fields['last_name'].initial = self.instance.user.last_name
                    self.fields['email'].initial = self.instance.user.email
                    self.fields['password'].required = False
                    self.fields['password'].help_text = 'Şifreyi değiştirmek istemiyorsanız boş bırakın'
            except Exception:
                # user yoksa yeni kullanıcı
                self.fields['password'].required = True
        else:
            # Yeni kullanıcı
            self.fields['password'].required = True
    
    def clean_username(self):
        username = self.cleaned_data.get('username')
        from django.contrib.auth.models import User
        
        if self.instance and self.instance.pk and hasattr(self.instance, 'user'):
            try:
                if self.instance.user and self.instance.user.username == username:
                    return username
            except:
                pass
        
        if User.objects.filter(username=username).exists():
            raise forms.ValidationError('Bu kullanıcı adı zaten kullanılıyor.')
        return username
    
    def clean_email(self):
        email = self.cleaned_data.get('email')
        from django.contrib.auth.models import User
        
        if self.instance and self.instance.pk and hasattr(self.instance, 'user'):
            try:
                if self.instance.user and self.instance.user.email == email:
                    return email
            except:
                pass
        
        if User.objects.filter(email=email).exists():
            raise forms.ValidationError('Bu e-posta adresi zaten kullanılıyor.')
        return email
    
    def save(self, commit=True):
        from django.contrib.auth.models import User
        
        tenant_user = super().save(commit=False)
        
        if self.instance and self.instance.pk and hasattr(self.instance, 'user'):
            try:
                user = self.instance.user
                user.username = self.cleaned_data['username']
                user.first_name = self.cleaned_data['first_name']
                user.last_name = self.cleaned_data['last_name']
                user.email = self.cleaned_data['email']
                if self.cleaned_data.get('password'):
                    user.set_password(self.cleaned_data['password'])
                user.save()
            except:
                # user yoksa yeni oluştur
                user = User.objects.create_user(
                    username=self.cleaned_data['username'],
                    first_name=self.cleaned_data['first_name'],
                    last_name=self.cleaned_data['last_name'],
                    email=self.cleaned_data['email'],
                    password=self.cleaned_data['password']
                )
                tenant_user.user = user
        else:
            # Yeni kullanıcı
            user = User.objects.create_user(
                username=self.cleaned_data['username'],
                first_name=self.cleaned_data['first_name'],
                last_name=self.cleaned_data['last_name'],
                email=self.cleaned_data['email'],
                password=self.cleaned_data['password']
            )
            tenant_user.user = user
        
        if commit:
            tenant_user.save()
        return tenant_user


class UserTypeForm(forms.ModelForm):
    class Meta:
        model = UserType
        fields = ['name', 'code', 'description', 'icon', 'dashboard_url', 'panel_template', 'default_role', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'code': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'description': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'dashboard_url': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'panel_template': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'default_role': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
            'sort_order': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
        }


class RoleForm(forms.ModelForm):
    class Meta:
        model = Role
        fields = ['name', 'code', 'description', 'icon', 'is_active', 'sort_order']
        widgets = {
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'code': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'description': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'rows': 3}),
            'icon': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
            'sort_order': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
        }


class PermissionForm(forms.ModelForm):
    class Meta:
        model = Permission
        fields = ['module', 'name', 'code', 'description', 'permission_type', 'is_active', 'sort_order']
        widgets = {
            'module': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'name': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'code': forms.TextInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'description': forms.Textarea(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent', 'rows': 3}),
            'permission_type': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
            'sort_order': forms.NumberInput(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
        }


class UserRoleForm(forms.ModelForm):
    class Meta:
        model = UserRole
        fields = ['role', 'is_active']
        widgets = {
            'role': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
        }


class RolePermissionForm(forms.ModelForm):
    class Meta:
        model = RolePermission
        fields = ['permission', 'is_active']
        widgets = {
            'permission': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
        }


class UserPermissionForm(forms.ModelForm):
    class Meta:
        model = UserPermission
        fields = ['permission', 'is_active']
        widgets = {
            'permission': forms.Select(attrs={'class': 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vb-primary focus:border-transparent'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
        }


# ==================== CUSTOMER FORMS ====================

class CustomerForm(forms.ModelForm):
    """Merkezi Customer Form - JSON Widget'larla"""
    special_dates_json = forms.CharField(
        widget=ListWidget(
            item_label='Özel Gün',
            attrs={'class': 'form-control', 'id': 'id_special_dates_json'}
        ),
        required=False,
        label='Özel Günler',
        help_text='Müşterinin özel günlerini ekleyin'
    )
    
    class Meta:
        model = Customer
        fields = [
            'first_name', 'last_name', 'email', 'phone', 'tc_no',
            'address', 'city', 'country', 'postal_code',
            'birth_date', 'special_dates_json',
            'preferred_contact_method', 'allow_marketing',
            'notes', 'special_requests', 'is_active'
        ]
        widgets = {
            'first_name': forms.TextInput(attrs={'class': 'form-control'}),
            'last_name': forms.TextInput(attrs={'class': 'form-control'}),
            'email': forms.EmailInput(attrs={'class': 'form-control'}),
            'phone': forms.TextInput(attrs={'class': 'form-control'}),
            'tc_no': forms.TextInput(attrs={'class': 'form-control'}),
            'address': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'city': forms.TextInput(attrs={'class': 'form-control'}),
            'country': forms.TextInput(attrs={'class': 'form-control'}),
            'postal_code': forms.TextInput(attrs={'class': 'form-control'}),
            'birth_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'preferred_contact_method': forms.Select(attrs={'class': 'form-control'}),
            'allow_marketing': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'special_requests': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'is_active': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
        }
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        if self.instance and self.instance.pk:
            # Mevcut JSON verisini widget'a yükle
            if self.instance.special_dates:
                self.fields['special_dates_json'].initial = json.dumps(
                    self.instance.special_dates
                )
    
    def save(self, commit=True):
        instance = super().save(commit=False)
        
        # Özel günleri parse et
        special_dates_str = self.cleaned_data.get('special_dates_json', '[]')
        try:
            instance.special_dates = json.loads(special_dates_str)
        except:
            instance.special_dates = []
        
        if commit:
            instance.save()
        return instance
    
    def clean_email(self):
        email = self.cleaned_data.get('email')
        if email:
            # Mevcut müşteriyi kontrol et (kendisi hariç)
            existing = Customer.objects.filter(email=email, is_deleted=False).exclude(pk=self.instance.pk if self.instance.pk else None)
            if existing.exists():
                raise forms.ValidationError('Bu e-posta adresi zaten kullanılıyor.')
        return email


class CustomerNoteForm(forms.ModelForm):
    """Customer Note Form"""
    class Meta:
        model = CustomerNote
        fields = ['note', 'note_type', 'is_important']
        widgets = {
            'note': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
            'note_type': forms.Select(attrs={'class': 'form-control'}),
            'is_important': forms.CheckboxInput(attrs={'class': 'w-4 h-4 text-vb-primary border-gray-300 rounded focus:ring-vb-primary'}),
        }
