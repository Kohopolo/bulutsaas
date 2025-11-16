"""
Digital Ocean DNS API Manager
Tenant domain'leri için otomatik DNS kaydı oluşturma
"""
import os
import requests
from django.conf import settings
from django.core.exceptions import ImproperlyConfigured
import logging

logger = logging.getLogger(__name__)


class DigitalOceanDNSManager:
    """Digital Ocean DNS API Manager"""
    
    def __init__(self):
        self.api_token = os.getenv('DO_API_TOKEN')
        self.domain = os.getenv('DO_DOMAIN', 'yourdomain.com')
        self.droplet_ip = os.getenv('DO_DROPLET_IP')
        
        if not self.api_token:
            raise ImproperlyConfigured('DO_API_TOKEN environment variable is required')
        if not self.droplet_ip:
            raise ImproperlyConfigured('DO_DROPLET_IP environment variable is required')
        
        self.base_url = 'https://api.digitalocean.com/v2'
        self.headers = {
            'Authorization': f'Bearer {self.api_token}',
            'Content-Type': 'application/json'
        }
    
    def create_a_record(self, subdomain, ip_address=None, ttl=300):
        """
        A Record oluştur
        
        Args:
            subdomain: Subdomain adı (örn: 'test-otel' veya '@' ana domain için)
            ip_address: IP adresi (None ise droplet IP kullanılır)
            ttl: TTL değeri (saniye)
        
        Returns:
            dict: API response
        """
        if ip_address is None:
            ip_address = self.droplet_ip
        
        # Ana domain için '@' kullan
        name = '@' if subdomain == self.domain or subdomain == '' else subdomain
        
        url = f'{self.base_url}/domains/{self.domain}/records'
        data = {
            'type': 'A',
            'name': name,
            'data': ip_address,
            'ttl': ttl
        }
        
        try:
            response = requests.post(url, headers=self.headers, json=data, timeout=10)
            response.raise_for_status()
            result = response.json()
            logger.info(f"DNS A record created: {name} -> {ip_address}")
            return result
        except requests.exceptions.RequestException as e:
            logger.error(f"DNS A record creation failed: {str(e)}")
            raise
    
    def create_cname_record(self, subdomain, target, ttl=300):
        """
        CNAME Record oluştur
        
        Args:
            subdomain: Subdomain adı
            target: Hedef domain
            ttl: TTL değeri
        
        Returns:
            dict: API response
        """
        url = f'{self.base_url}/domains/{self.domain}/records'
        data = {
            'type': 'CNAME',
            'name': subdomain,
            'data': target,
            'ttl': ttl
        }
        
        try:
            response = requests.post(url, headers=self.headers, json=data, timeout=10)
            response.raise_for_status()
            result = response.json()
            logger.info(f"DNS CNAME record created: {subdomain} -> {target}")
            return result
        except requests.exceptions.RequestException as e:
            logger.error(f"DNS CNAME record creation failed: {str(e)}")
            raise
    
    def delete_record(self, record_id):
        """
        DNS Record sil
        
        Args:
            record_id: Record ID
        
        Returns:
            dict: API response
        """
        url = f'{self.base_url}/domains/{self.domain}/records/{record_id}'
        
        try:
            response = requests.delete(url, headers=self.headers, timeout=10)
            response.raise_for_status()
            logger.info(f"DNS record deleted: {record_id}")
            return {'success': True}
        except requests.exceptions.RequestException as e:
            logger.error(f"DNS record deletion failed: {str(e)}")
            raise
    
    def get_records(self, record_type=None, name=None):
        """
        DNS Record'ları listele
        
        Args:
            record_type: Record tipi (A, CNAME, vb.)
            name: Record adı
        
        Returns:
            list: Record listesi
        """
        url = f'{self.base_url}/domains/{self.domain}/records'
        params = {}
        
        if record_type:
            params['type'] = record_type
        if name:
            params['name'] = name
        
        try:
            response = requests.get(url, headers=self.headers, params=params, timeout=10)
            response.raise_for_status()
            return response.json().get('domain_records', [])
        except requests.exceptions.RequestException as e:
            logger.error(f"DNS records fetch failed: {str(e)}")
            raise
    
    def find_record(self, name, record_type='A'):
        """
        Belirli bir record'u bul
        
        Args:
            name: Record adı
            record_type: Record tipi
        
        Returns:
            dict: Record dict veya None
        """
        records = self.get_records(record_type=record_type, name=name)
        for record in records:
            if record['name'] == name and record['type'] == record_type:
                return record
        return None
    
    def update_record(self, record_id, data=None, ttl=None):
        """
        DNS Record güncelle
        
        Args:
            record_id: Record ID
            data: Yeni IP adresi veya hedef
            ttl: Yeni TTL değeri
        
        Returns:
            dict: API response
        """
        url = f'{self.base_url}/domains/{self.domain}/records/{record_id}'
        payload = {}
        
        if data:
            payload['data'] = data
        if ttl:
            payload['ttl'] = ttl
        
        if not payload:
            return {'success': False, 'error': 'No data to update'}
        
        try:
            response = requests.put(url, headers=self.headers, json=payload, timeout=10)
            response.raise_for_status()
            result = response.json()
            logger.info(f"DNS record updated: {record_id}")
            return result
        except requests.exceptions.RequestException as e:
            logger.error(f"DNS record update failed: {str(e)}")
            raise

