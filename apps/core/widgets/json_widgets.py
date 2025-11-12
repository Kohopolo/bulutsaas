"""
JSON Form Widgets - Kullanıcı Dostu JSON Form Girişleri
"""
from django import forms
from django.utils.safestring import mark_safe
import json


class KeyValueWidget(forms.Widget):
    """Key-Value çiftleri için widget (Dictionary)"""
    template_name = 'widgets/key_value_widget.html'
    
    def __init__(self, key_label='Anahtar', value_label='Değer', 
                 key_type='text', value_type='text', max_adults=None, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.key_label = key_label
        self.value_label = value_label
        self.key_type = key_type
        self.value_type = value_type
        self.max_adults = max_adults
    
    def format_value(self, value):
        """Model'den gelen değeri formatla"""
        if value is None or value == '':
            return {}
        if isinstance(value, str):
            try:
                return json.loads(value)
            except:
                return {}
        # Eğer zaten dict ise, olduğu gibi döndür (JSONCharField.prepare_value'den gelebilir)
        if isinstance(value, dict):
            return value
        return {}
    
    def value_from_datadict(self, data, files, name):
        """Form'dan gelen veriyi JSON'a dönüştür"""
        # Önce hidden input'u kontrol et (JavaScript ile güncellenen)
        hidden_value = data.get(name, '').strip()
        if hidden_value:
            try:
                # Geçerli JSON mu kontrol et
                parsed = json.loads(hidden_value)
                if isinstance(parsed, dict):
                    return hidden_value
            except:
                pass
        
        # Hidden input yoksa veya geçersizse, eski pattern'i kullan
        pairs = []
        i = 0
        while f'{name}_key_{i}' in data:
            key = data.get(f'{name}_key_{i}', '').strip()
            value = data.get(f'{name}_value_{i}', '').strip()
            if key:
                # Tip dönüşümü
                if self.value_type == 'number':
                    try:
                        value = float(value)  # Her zaman float kullan (ondalıklı sayılar için)
                    except:
                        value = 0
                pairs.append((key, value))
            i += 1
        return json.dumps(dict(pairs)) if pairs else '{}'
    
    def get_context(self, name, value, attrs):
        context = super().get_context(name, value, attrs)
        context['widget']['key_label'] = self.key_label
        context['widget']['value_label'] = self.value_label
        context['widget']['key_type'] = self.key_type
        context['widget']['value_type'] = self.value_type
        context['widget']['max_adults'] = self.max_adults
        
        # value None, boş string veya None ise kontrol et
        # Django form field'ın initial değeri veya instance değeri value olarak geçer
        if value is not None and value != '':
            formatted = self.format_value(value)
            pairs = list(formatted.items()) if isinstance(formatted, dict) else []
        else:
            pairs = []
        
        # Eğer max_adults varsa ve pairs boşsa, otomatik oluştur
        if self.max_adults and not pairs:
            for i in range(1, self.max_adults + 1):
                pairs.append((str(i), float(i)))
        
        context['widget']['pairs'] = pairs
        return context
    
    class Media:
        css = {'all': ('css/json_widgets.css',)}
        js = ('js/json_form_widgets.js',)


class ObjectListWidget(forms.Widget):
    """Nesne listesi için widget (Array of Objects)"""
    template_name = 'widgets/object_list_widget.html'
    
    def __init__(self, fields_config, *args, **kwargs):
        """
        fields_config: [{'name': 'age_range', 'label': 'Yaş Aralığı', 'type': 'text', 'placeholder': '0-6'}]
        """
        super().__init__(*args, **kwargs)
        self.fields_config = fields_config
    
    def format_value(self, value):
        """Model'den gelen değeri formatla"""
        if value is None or value == '':
            return []
        if isinstance(value, str):
            try:
                return json.loads(value)
            except:
                return []
        # Eğer zaten list ise, olduğu gibi döndür (JSONCharField.prepare_value'den gelebilir)
        if isinstance(value, list):
            return value
        return []
    
    def value_from_datadict(self, data, files, name):
        """Form'dan gelen veriyi JSON'a dönüştür"""
        # Önce hidden input'u kontrol et (JavaScript ile güncellenen)
        hidden_value = data.get(name, '').strip()
        if hidden_value:
            try:
                # Geçerli JSON mu kontrol et
                parsed = json.loads(hidden_value)
                if isinstance(parsed, list):
                    return hidden_value
            except:
                pass
        
        # Hidden input yoksa veya geçersizse, eski pattern'i kullan
        objects = []
        i = 0
        while f'{name}_obj_{i}_field_0' in data:
            obj = {}
            for j, field_config in enumerate(self.fields_config):
                field_name = field_config['name']
                field_value = data.get(f'{name}_obj_{i}_field_{j}', '').strip()
                if field_value:
                    # Tip dönüşümü
                    if field_config.get('type') == 'number':
                        try:
                            field_value = float(field_value)  # Her zaman float kullan (ondalıklı sayılar için)
                        except:
                            field_value = 0
                obj[field_name] = field_value
            if any(obj.values()):  # En az bir değer varsa ekle
                objects.append(obj)
            i += 1
        return json.dumps(objects) if objects else '[]'
    
    def get_context(self, name, value, attrs):
        context = super().get_context(name, value, attrs)
        import json as json_module
        # fields_config'i hem JSON string (JavaScript için) hem de liste (template için) olarak gönder
        context['widget']['fields_config'] = json_module.dumps(self.fields_config)  # JavaScript için
        context['widget']['fields_config_list'] = self.fields_config  # Template için
        
        # value None, boş string veya None ise kontrol et
        # Django form field'ın initial değeri veya instance değeri value olarak geçer
        if value is not None and value != '':
            objects = self.format_value(value)
        else:
            objects = []
        
        # Eğer liste değilse veya elemanlar dict değilse, boş liste döndür
        if not isinstance(objects, list):
            objects = []
        # Her elemanın dict olduğundan emin ol
        objects = [obj for obj in objects if isinstance(obj, dict)]
        context['widget']['objects'] = objects
        return context
    
    class Media:
        css = {'all': ('css/json_widgets.css',)}
        js = ('js/json_form_widgets.js',)


class ListWidget(forms.Widget):
    """Liste için widget (Array)"""
    template_name = 'widgets/list_widget.html'
    
    def __init__(self, item_label='Öğe', *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.item_label = item_label
    
    def format_value(self, value):
        """Model'den gelen değeri formatla"""
        if value is None or value == '':
            return []
        if isinstance(value, str):
            try:
                return json.loads(value)
            except:
                return []
        # Eğer zaten list ise, olduğu gibi döndür (JSONCharField.prepare_value'den gelebilir)
        if isinstance(value, list):
            return value
        return []
    
    def value_from_datadict(self, data, files, name):
        """Form'dan gelen veriyi JSON'a dönüştür"""
        items = []
        i = 0
        while f'{name}_item_{i}' in data:
            item = data.get(f'{name}_item_{i}', '').strip()
            if item:
                items.append(item)
            i += 1
        return json.dumps(items) if items else '[]'
    
    def get_context(self, name, value, attrs):
        context = super().get_context(name, value, attrs)
        context['widget']['item_label'] = self.item_label
        context['widget']['items'] = self.format_value(value)
        return context
    
    class Media:
        css = {'all': ('css/json_widgets.css',)}
        js = ('js/json_form_widgets.js',)


class WeekdayPricesWidget(forms.Widget):
    """Hafta içi günlük fiyatlar için widget"""
    template_name = 'widgets/weekday_prices_widget.html'
    
    weekdays = [
        ('monday', 'Pazartesi'),
        ('tuesday', 'Salı'),
        ('wednesday', 'Çarşamba'),
        ('thursday', 'Perşembe'),
        ('friday', 'Cuma'),
        ('saturday', 'Cumartesi'),
        ('sunday', 'Pazar'),
    ]
    
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
        prices = {}
        for day_key, day_label in self.weekdays:
            price_value = data.get(f'{name}_{day_key}', '').strip()
            if price_value:
                try:
                    prices[day_key] = float(price_value)
                except:
                    pass
        return json.dumps(prices) if prices else '{}'
    
    def get_context(self, name, value, attrs):
        context = super().get_context(name, value, attrs)
        context['widget']['weekdays'] = self.weekdays
        context['widget']['prices'] = self.format_value(value)
        return context
    
    class Media:
        css = {'all': ('css/json_widgets.css',)}
        js = ('js/json_form_widgets.js',)

