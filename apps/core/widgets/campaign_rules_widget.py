"""
Kampanya Kuralları Widget - Kampanya tipine göre dinamik alanlar
"""
from django import forms
from django.utils.safestring import mark_safe
import json


class CampaignRulesWidget(forms.Widget):
    """Kampanya kuralları için widget - Kampanya tipine göre dinamik"""
    template_name = 'widgets/campaign_rules_widget.html'
    
    # Kampanya tipine göre alan tanımları
    CAMPAIGN_FIELDS = {
        'stay_nights': [
            {'key': 'stay_nights', 'label': 'Minimum Gece Sayısı', 'type': 'number', 'min': 1, 'placeholder': 'Örn: 7'},
        ],
        'early_booking': [
            {'key': 'early_booking_days', 'label': 'Erken Rezervasyon Gün Sayısı', 'type': 'number', 'min': 1, 'placeholder': 'Örn: 30'},
        ],
        'last_minute': [
            {'key': 'last_minute_days', 'label': 'Son Dakika Gün Sayısı', 'type': 'number', 'min': 1, 'placeholder': 'Örn: 7'},
        ],
        'group': [
            {'key': 'group_size', 'label': 'Minimum Grup Büyüklüğü', 'type': 'number', 'min': 1, 'placeholder': 'Örn: 10'},
        ],
        'custom': [
            {'key': 'custom_rule', 'label': 'Özel Kural', 'type': 'text', 'placeholder': 'Kural adı'},
            {'key': 'custom_value', 'label': 'Kural Değeri', 'type': 'text', 'placeholder': 'Kural değeri'},
        ],
    }
    
    def format_value(self, value):
        """Model'den gelen değeri formatla"""
        if isinstance(value, str):
            try:
                return json.loads(value)
            except:
                return {}
        return value or {}
    
    def value_from_datadict(self, data, files, name):
        """Form'dan gelen veriyi JSON'a dönüştür"""
        rules = {}
        campaign_type = data.get('campaign_type', 'custom')
        fields_config = self.CAMPAIGN_FIELDS.get(campaign_type, self.CAMPAIGN_FIELDS['custom'])
        
        for field_config in fields_config:
            field_key = field_config['key']
            field_value = data.get(f'{name}_{field_key}', '').strip()
            if field_value:
                # Tip dönüşümü
                if field_config.get('type') == 'number':
                    try:
                        field_value = float(field_value) if '.' in field_value else int(field_value)
                    except:
                        field_value = 0
                rules[field_key] = field_value
        
        return json.dumps(rules) if rules else '{}'
    
    def get_context(self, name, value, attrs):
        context = super().get_context(name, value, attrs)
        import json as json_module
        context['widget']['name'] = name
        context['widget']['campaign_fields'] = json_module.dumps(self.CAMPAIGN_FIELDS)
        # rules'ı JSON string olarak gönder (template'de güvenli kullanım için)
        rules_dict = self.format_value(value)
        context['widget']['rules'] = json_module.dumps(rules_dict) if rules_dict else '{}'
        return context
    
    class Media:
        css = {'all': ('css/json_widgets.css',)}
        js = ('js/campaign_rules_widget.js',)

