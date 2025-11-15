"""
Dictionary template filters
"""
from django import template

register = template.Library()


@register.filter
def get_item(dictionary, key):
    """Dictionary'den key ile değer al"""
    if dictionary is None:
        return None
    if not isinstance(dictionary, dict):
        # Eğer dictionary değilse (string, list vs.), None döndür
        return None
    return dictionary.get(key)
