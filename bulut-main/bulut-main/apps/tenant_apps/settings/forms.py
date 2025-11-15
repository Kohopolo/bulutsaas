"""
Ayarlar Modülü Forms
SMS Gateway ve SMS Şablon formları
"""
from django import forms
from django.core.validators import MinValueValidator, MaxValueValidator
from .models import SMSGateway, SMSTemplate, EmailGateway, EmailTemplate


class SMSGatewayForm(forms.ModelForm):
    """SMS Gateway Konfigürasyon Formu"""
    
    hotel = forms.ModelChoiceField(
        queryset=None,
        required=False,
        empty_label='--- Genel Gateway (Tüm Oteller) ---',
        widget=forms.Select(attrs={
            'class': 'form-control',
            'id': 'id_hotel'
        }),
        help_text='Boş bırakılırsa tüm oteller için genel gateway olur'
    )
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        try:
            from apps.tenant_apps.hotels.models import Hotel
            self.fields['hotel'].queryset = Hotel.objects.filter(is_deleted=False).order_by('name')
        except:
            self.fields['hotel'].queryset = Hotel.objects.none()
    
    class Meta:
        model = SMSGateway
        fields = [
            'name', 'hotel', 'gateway_type', 'api_credentials', 'api_endpoint',
            'api_timeout', 'api_retry_count', 'sender_id', 'default_country_code',
            'is_active', 'is_default', 'is_test_mode', 'notes'
        ]
        widgets = {
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'gateway_type': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_gateway_type'
            }),
            'api_credentials': forms.HiddenInput(attrs={
                'id': 'id_api_credentials',
            }),
            'api_endpoint': forms.URLInput(attrs={
                'class': 'form-control',
                'id': 'id_api_endpoint',
                'placeholder': 'Varsayılan endpoint kullanılacaksa boş bırakın'
            }),
            'api_timeout': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'max': 300,
                'id': 'id_api_timeout'
            }),
            'api_retry_count': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 0,
                'max': 10,
                'id': 'id_api_retry_count'
            }),
            'sender_id': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_sender_id',
                'placeholder': 'Gönderen numarası veya başlık'
            }),
            'default_country_code': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_default_country_code',
                'placeholder': '+90'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
            'is_default': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_default'
            }),
            'is_test_mode': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_test_mode'
            }),
            'notes': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_notes'
            }),
        }
    
    def clean_api_credentials(self):
        """API credentials JSON formatını kontrol et"""
        import json
        credentials = self.cleaned_data.get('api_credentials')
        if credentials:
            try:
                if isinstance(credentials, str):
                    parsed = json.loads(credentials)
                    return parsed
                elif isinstance(credentials, dict):
                    return credentials
                else:
                    raise forms.ValidationError('API bilgileri geçerli bir JSON formatında olmalıdır.')
            except (json.JSONDecodeError, TypeError) as e:
                raise forms.ValidationError('API bilgileri geçerli bir JSON formatında olmalıdır.')
        return credentials or {}
    
    def clean(self):
        cleaned_data = super().clean()
        gateway_type = cleaned_data.get('gateway_type')
        api_credentials = cleaned_data.get('api_credentials', {})
        
        # Gateway tipine göre gerekli alanları kontrol et
        if gateway_type == 'twilio':
            if not api_credentials.get('account_sid'):
                raise forms.ValidationError({'api_credentials': 'Twilio için Account SID gereklidir.'})
            if not api_credentials.get('auth_token'):
                raise forms.ValidationError({'api_credentials': 'Twilio için Auth Token gereklidir.'})
        elif gateway_type in ['netgsm', 'verimor']:
            if not api_credentials.get('username'):
                raise forms.ValidationError({'api_credentials': f'{gateway_type.upper()} için Kullanıcı Adı gereklidir.'})
            if not api_credentials.get('password'):
                raise forms.ValidationError({'api_credentials': f'{gateway_type.upper()} için Şifre gereklidir.'})
        
        return cleaned_data


class SMSTemplateForm(forms.ModelForm):
    """SMS Şablon Formu"""
    
    class Meta:
        model = SMSTemplate
        fields = [
            'name', 'code', 'category', 'template_text', 'available_variables',
            'module_usage', 'description', 'max_length', 'is_active'
        ]
        widgets = {
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'code': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_code',
                'placeholder': 'reservation_confirmation'
            }),
            'category': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_category'
            }),
            'template_text': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 5,
                'id': 'id_template_text',
                'placeholder': 'Örn: Sayın {{guest_name}}, rezervasyonunuz {{check_in_date}} tarihinde onaylanmıştır.'
            }),
            'available_variables': forms.HiddenInput(attrs={
                'id': 'id_available_variables',
            }),
            'module_usage': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_module_usage',
                'placeholder': 'reception, ferry_tickets, tours'
            }),
            'description': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_description'
            }),
            'max_length': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'max': 1000,
                'id': 'id_max_length'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
        }
    
    def clean_code(self):
        """Code'un benzersiz olduğunu kontrol et"""
        code = self.cleaned_data.get('code')
        if code:
            # Sistem şablonları kontrolü
            existing = SMSTemplate.objects.filter(code=code)
            if self.instance.pk:
                existing = existing.exclude(pk=self.instance.pk)
            if existing.exists():
                raise forms.ValidationError('Bu kod zaten kullanılıyor.')
        return code
    
    def clean_available_variables(self):
        """Available variables JSON formatını kontrol et"""
        import json
        variables = self.cleaned_data.get('available_variables')
        if variables:
            try:
                if isinstance(variables, str):
                    parsed = json.loads(variables)
                    return parsed
                elif isinstance(variables, dict):
                    return variables
                else:
                    raise forms.ValidationError('Değişkenler geçerli bir JSON formatında olmalıdır.')
            except (json.JSONDecodeError, TypeError) as e:
                raise forms.ValidationError('Değişkenler geçerli bir JSON formatında olmalıdır.')
        return variables or {}
    
    def clean_template_text(self):
        """Template metninde kullanılan değişkenleri kontrol et"""
        template_text = self.cleaned_data.get('template_text')
        if template_text:
            # {{variable}} formatındaki değişkenleri bul
            import re
            variables = re.findall(r'\{\{(\w+)\}\}', template_text)
            
            # Available variables'ı güncelle
            available_vars = self.cleaned_data.get('available_variables', {})
            for var in variables:
                if var not in available_vars:
                    # Varsayılan açıklama ekle
                    available_vars[var] = var.replace('_', ' ').title()
            
            self.cleaned_data['available_variables'] = available_vars
        
        return template_text


class EmailGatewayForm(forms.ModelForm):
    """Email Gateway Konfigürasyon Formu"""
    
    hotel = forms.ModelChoiceField(
        queryset=None,
        required=False,
        empty_label='--- Genel Gateway (Tüm Oteller) ---',
        widget=forms.Select(attrs={
            'class': 'form-control',
            'id': 'id_hotel'
        }),
        help_text='Boş bırakılırsa tüm oteller için genel gateway olur'
    )
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        try:
            from apps.tenant_apps.hotels.models import Hotel
            self.fields['hotel'].queryset = Hotel.objects.filter(is_deleted=False).order_by('name')
        except:
            self.fields['hotel'].queryset = Hotel.objects.none()
    
    class Meta:
        model = EmailGateway
        fields = [
            'name', 'hotel', 'gateway_type', 'smtp_credentials', 'smtp_host', 'smtp_port',
            'use_tls', 'use_ssl', 'smtp_timeout', 'from_email', 'from_name',
            'reply_to', 'is_active', 'is_default', 'is_test_mode', 'notes'
        ]
        widgets = {
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'gateway_type': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_gateway_type'
            }),
            'smtp_credentials': forms.HiddenInput(attrs={
                'id': 'id_smtp_credentials',
            }),
            'smtp_host': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_smtp_host',
                'placeholder': 'smtp.gmail.com'
            }),
            'smtp_port': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'max': 65535,
                'id': 'id_smtp_port'
            }),
            'use_tls': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_use_tls'
            }),
            'use_ssl': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_use_ssl'
            }),
            'smtp_timeout': forms.NumberInput(attrs={
                'class': 'form-control',
                'min': 1,
                'max': 300,
                'id': 'id_smtp_timeout'
            }),
            'from_email': forms.EmailInput(attrs={
                'class': 'form-control',
                'id': 'id_from_email',
                'placeholder': 'noreply@example.com'
            }),
            'from_name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_from_name',
                'placeholder': 'Otel Adı'
            }),
            'reply_to': forms.EmailInput(attrs={
                'class': 'form-control',
                'id': 'id_reply_to',
                'placeholder': 'info@example.com'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
            'is_default': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_default'
            }),
            'is_test_mode': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_test_mode'
            }),
            'notes': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_notes'
            }),
        }
    
    def clean_smtp_credentials(self):
        """SMTP credentials JSON formatını kontrol et"""
        import json
        credentials = self.cleaned_data.get('smtp_credentials')
        if credentials:
            try:
                if isinstance(credentials, str):
                    parsed = json.loads(credentials)
                    return parsed
                elif isinstance(credentials, dict):
                    return credentials
                else:
                    raise forms.ValidationError('SMTP bilgileri geçerli bir JSON formatında olmalıdır.')
            except (json.JSONDecodeError, TypeError) as e:
                raise forms.ValidationError('SMTP bilgileri geçerli bir JSON formatında olmalıdır.')
        return credentials or {}
    
    def clean(self):
        cleaned_data = super().clean()
        gateway_type = cleaned_data.get('gateway_type')
        smtp_credentials = cleaned_data.get('smtp_credentials', {})
        smtp_host = cleaned_data.get('smtp_host')
        
        # Gateway tipine göre gerekli alanları kontrol et
        if gateway_type == 'gmail':
            if not smtp_credentials.get('username'):
                raise forms.ValidationError({'smtp_credentials': 'Gmail için kullanıcı adı (email) gereklidir.'})
            if not smtp_credentials.get('password'):
                raise forms.ValidationError({'smtp_credentials': 'Gmail için uygulama şifresi gereklidir.'})
            if not smtp_host:
                cleaned_data['smtp_host'] = 'smtp.gmail.com'
            if cleaned_data.get('smtp_port') == 587:
                cleaned_data['use_tls'] = True
        elif gateway_type == 'outlook':
            if not smtp_credentials.get('username'):
                raise forms.ValidationError({'smtp_credentials': 'Outlook için kullanıcı adı (email) gereklidir.'})
            if not smtp_credentials.get('password'):
                raise forms.ValidationError({'smtp_credentials': 'Outlook için şifre gereklidir.'})
            if not smtp_host:
                cleaned_data['smtp_host'] = 'smtp.office365.com'
            if cleaned_data.get('smtp_port') == 587:
                cleaned_data['use_tls'] = True
        elif gateway_type == 'custom':
            if not smtp_host:
                raise forms.ValidationError({'smtp_host': 'Custom SMTP için host adresi gereklidir.'})
            if not smtp_credentials.get('username'):
                raise forms.ValidationError({'smtp_credentials': 'Custom SMTP için kullanıcı adı gereklidir.'})
            if not smtp_credentials.get('password'):
                raise forms.ValidationError({'smtp_credentials': 'Custom SMTP için şifre gereklidir.'})
        
        return cleaned_data


class EmailTemplateForm(forms.ModelForm):
    """Email Şablon Formu"""
    
    class Meta:
        model = EmailTemplate
        fields = [
            'name', 'code', 'category', 'subject', 'template_html', 'template_text',
            'available_variables', 'module_usage', 'description', 'is_active'
        ]
        widgets = {
            'name': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_name'
            }),
            'code': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_code',
                'placeholder': 'reservation_confirmation'
            }),
            'category': forms.Select(attrs={
                'class': 'form-control',
                'id': 'id_category'
            }),
            'subject': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_subject',
                'placeholder': 'Rezervasyon Onayı - {{guest_name}}'
            }),
            'template_html': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 15,
                'id': 'id_template_html',
                'placeholder': '<html><body>...</body></html>'
            }),
            'template_text': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 10,
                'id': 'id_template_text',
                'placeholder': 'Plain text alternatifi'
            }),
            'available_variables': forms.HiddenInput(attrs={
                'id': 'id_available_variables',
            }),
            'module_usage': forms.TextInput(attrs={
                'class': 'form-control',
                'id': 'id_module_usage',
                'placeholder': 'reception, ferry_tickets, tours'
            }),
            'description': forms.Textarea(attrs={
                'class': 'form-control',
                'rows': 3,
                'id': 'id_description'
            }),
            'is_active': forms.CheckboxInput(attrs={
                'class': 'form-check-input',
                'id': 'id_is_active'
            }),
        }
    
    def clean_code(self):
        """Code'un benzersiz olduğunu kontrol et"""
        code = self.cleaned_data.get('code')
        if code:
            existing = EmailTemplate.objects.filter(code=code)
            if self.instance.pk:
                existing = existing.exclude(pk=self.instance.pk)
            if existing.exists():
                raise forms.ValidationError('Bu kod zaten kullanılıyor.')
        return code
    
    def clean_available_variables(self):
        """Available variables JSON formatını kontrol et"""
        import json
        variables = self.cleaned_data.get('available_variables')
        if variables:
            try:
                if isinstance(variables, str):
                    parsed = json.loads(variables)
                    return parsed
                elif isinstance(variables, dict):
                    return variables
                else:
                    raise forms.ValidationError('Değişkenler geçerli bir JSON formatında olmalıdır.')
            except (json.JSONDecodeError, TypeError) as e:
                raise forms.ValidationError('Değişkenler geçerli bir JSON formatında olmalıdır.')
        return variables or {}
    
    def clean_template_html(self):
        """Template HTML'inde kullanılan değişkenleri kontrol et"""
        template_html = self.cleaned_data.get('template_html', '')
        subject = self.cleaned_data.get('subject', '')
        template_text = self.cleaned_data.get('template_text', '')
        
        # {{variable}} formatındaki değişkenleri bul
        import re
        all_text = f"{subject} {template_html} {template_text}"
        variables = re.findall(r'\{\{(\w+)\}\}', all_text)
        
        # Available variables'ı güncelle
        available_vars = self.cleaned_data.get('available_variables', {})
        for var in variables:
            if var not in available_vars:
                # Varsayılan açıklama ekle
                available_vars[var] = var.replace('_', ' ').title()
        
        self.cleaned_data['available_variables'] = available_vars
        
        return template_html

