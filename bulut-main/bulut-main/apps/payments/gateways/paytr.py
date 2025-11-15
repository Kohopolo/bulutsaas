"""
PayTR Payment Gateway Integration
https://www.paytr.com/
"""
import hashlib
import hmac
import base64
import json
import html
import requests
import logging
from decimal import Decimal
from typing import Dict, Any, Optional
from .base import BasePaymentGateway

logger = logging.getLogger(__name__)


class PayTRGateway(BasePaymentGateway):
    """PayTR Payment Gateway - Direkt API ve iFrame API desteği"""
    
    API_URL = "https://www.paytr.com/odeme/api/get-token"
    CALLBACK_URL = "https://www.paytr.com/odeme/guvenli"
    IFRAME_URL = "https://www.paytr.com/odeme/guvenli"
    
    def __init__(self, config: Dict[str, Any]):
        super().__init__(config)
        # PayTR API tipi (direct veya iframe)
        self.api_type = config.get('paytr_api_type', 'direct')
        # Debug: API tipini logla
        logger.info(f"PayTRGateway initialized with api_type: {self.api_type} (from config: {config.get('paytr_api_type', 'NOT SET')})")
    
    def create_payment(self, amount: Decimal, currency: str, order_id: str,
                      customer_info: Dict[str, Any], **kwargs) -> Dict[str, Any]:
        """Create payment with PayTR - Direkt API veya iFrame API"""
        
        # Debug: API tipini logla
        logger.info(f"PayTRGateway.create_payment called with api_type: {self.api_type}")
        
        # API tipine göre yöntem seç
        if self.api_type == 'iframe':
            logger.info("Using iFrame API for PayTR payment")
            return self._create_iframe_payment(amount, currency, order_id, customer_info, **kwargs)
        else:
            logger.info("Using Direct API for PayTR payment")
            return self._create_direct_payment(amount, currency, order_id, customer_info, **kwargs)
    
    def _create_iframe_payment(self, amount: Decimal, currency: str, order_id: str,
                               customer_info: Dict[str, Any], **kwargs) -> Dict[str, Any]:
        """Create payment with PayTR iFrame API"""
        amount_cents = int(amount * 100)  # Kuruş cinsinden
        
        # Basket items hazırla (PayTR iFrame API formatı: [['Ürün Adı', 'Fiyat', 'Adet']])
        basket_items = kwargs.get('basket_items', [])
        if not basket_items:
            basket_items = [['Ödeme', str(amount), 1]]
        else:
            # Basket items formatını PayTR formatına çevir
            formatted_basket = []
            for item in basket_items:
                if isinstance(item, dict):
                    # Dict formatından PayTR formatına çevir
                    item_price = item.get('price', amount)
                    # Eğer price Decimal ise string'e çevir
                    if isinstance(item_price, Decimal):
                        item_price = str(item_price)
                    formatted_basket.append([
                        item.get('name', 'Ürün'),
                        item_price,
                        item.get('quantity', 1)
                    ])
                elif isinstance(item, list) and len(item) >= 2:
                    # Zaten PayTR formatında
                    formatted_basket.append(item)
                else:
                    # Diğer formatlar için varsayılan
                    formatted_basket.append(['Ürün', str(amount), 1])
            basket_items = formatted_basket
        
        user_basket = base64.b64encode(
            json.dumps(basket_items).encode()
        ).decode()
        
        # Müşteri IP adresi
        user_ip = kwargs.get('user_ip', '')
        
        # iFrame API için gerekli parametreler
        no_installment = '0' if kwargs.get('installment', True) else '1'
        max_installment = str(kwargs.get('max_installment', 0))
        test_mode = '1' if self.is_test_mode else '0'
        timeout_limit = str(kwargs.get('timeout_limit', 30))
        debug_on = '1' if kwargs.get('debug_on', False) else '0'
        lang = kwargs.get('lang', 'tr')
        
        # PayTR Token oluştur (iFrame API için)
        # Hash: merchant_id + user_ip + merchant_oid + email + payment_amount + user_basket + no_installment + max_installment + currency + test_mode
        hash_str = f"{self.merchant_id}{user_ip}{order_id}{customer_info.get('email', '')}{amount_cents}{user_basket}{no_installment}{max_installment}{currency}{test_mode}"
        paytr_token = base64.b64encode(
            hmac.new(
                self.secret_key.encode() if isinstance(self.secret_key, str) else self.secret_key,
                (hash_str + self.store_key).encode(),
                hashlib.sha256
            ).digest()
        ).decode()
        
        # iFrame API için POST isteği
        data = {
            'merchant_id': self.merchant_id,
            'merchant_key': self.secret_key,
            'merchant_salt': self.store_key,
            'user_ip': user_ip,
            'merchant_oid': order_id,
            'email': customer_info.get('email', ''),
            'payment_amount': str(amount_cents),
            'paytr_token': paytr_token,
            'user_basket': user_basket,
            'debug_on': debug_on,
            'no_installment': no_installment,
            'max_installment': max_installment,
            'user_name': customer_info.get('name', ''),
            'user_address': customer_info.get('address', ''),
            'user_phone': customer_info.get('phone', ''),
            'merchant_ok_url': kwargs.get('success_url', ''),
            'merchant_fail_url': kwargs.get('fail_url', ''),
            'timeout_limit': timeout_limit,
            'currency': currency,
            'test_mode': test_mode,
            'lang': lang,
        }
        
        try:
            logger.info(f"PayTR iFrame API request data: merchant_id={self.merchant_id}, merchant_oid={order_id}, payment_amount={amount_cents}")
            response = requests.post(self.API_URL, data=data, timeout=30)
            result = response.json()
            
            logger.info(f"PayTR iFrame API response: {result}")
            
            if result.get('status') == 'success':
                token = result.get('token')
                iframe_url = f"{self.IFRAME_URL}/{token}"
                
                logger.info(f"PayTR iFrame API token received: {token}")
                
                # iFrame HTML içeriği oluştur
                html_content = f"""
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Ödeme</title>
                    <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
                </head>
                <body style="margin: 0; padding: 0;">
                    <iframe src="{iframe_url}" id="paytriframe" frameborder="0" scrolling="no" style="width: 100%; min-height: 600px;"></iframe>
                    <script>
                        iFrameResize({{}}, '#paytriframe');
                    </script>
                </body>
                </html>
                """
                
                logger.info(f"PayTR iFrame HTML content created, length: {len(html_content)}")
                
                return {
                    'success': True,
                    'payment_url': iframe_url,
                    'html_content': html_content,
                    'iframe_token': token,
                    'transaction_id': order_id,
                }
            else:
                error_msg = result.get('reason', 'Ödeme oluşturulamadı')
                logger.error(f"PayTR iFrame API error: {error_msg}")
                
                # Eğer Direkt API yetkisi hatası varsa ve Direkt API kullanılabilirse, otomatik geçiş yap
                if 'Direkt API yetkisi' in error_msg and self.api_type == 'iframe':
                    logger.warning("PayTR iFrame API yetkisi yok, Direkt API'ye geçiliyor...")
                    # Direkt API'ye geçiş yap
                    return self._create_direct_payment(amount, currency, order_id, customer_info, **kwargs)
                
                return {
                    'success': False,
                    'error': error_msg,
                }
        except Exception as e:
            logger.exception(f"PayTR iFrame API exception: {str(e)}")
            return {
                'success': False,
                'error': str(e),
            }
    
    def _create_direct_payment(self, amount: Decimal, currency: str, order_id: str,
                               customer_info: Dict[str, Any], **kwargs) -> Dict[str, Any]:
        """Create payment with PayTR - Direkt API modu"""
        # PayTR Direkt API için HTML form oluştur
        amount_cents = int(amount * 100)  # Kuruş cinsinden
        
        # Basket items hazırla
        basket_items = kwargs.get('basket_items', [])
        if not basket_items:
            basket_items = [{
                'name': 'Ödeme',
                'price': str(amount),
                'quantity': 1
            }]
        
        user_basket = base64.b64encode(
            json.dumps(basket_items).encode()
        ).decode()
        
        # Form verileri (Direkt API için merchant_key ve merchant_salt gönderilmez, sadece hash gönderilir)
        form_data = {
            'merchant_id': self.merchant_id,
            'email': customer_info.get('email', ''),
            'payment_amount': str(amount_cents),
            'currency': currency,
            'merchant_oid': order_id,
            'user_name': customer_info.get('name', ''),
            'user_address': customer_info.get('address', ''),
            'user_phone': customer_info.get('phone', ''),
            'merchant_ok_url': kwargs.get('success_url', ''),
            'merchant_fail_url': kwargs.get('fail_url', ''),
            'user_basket': user_basket,
            'test_mode': '1' if self.is_test_mode else '0',
            'no_installment': '0' if kwargs.get('installment', False) else '1',
            'max_installment': str(kwargs.get('max_installment', 12)),
        }
        
        # Hash oluştur (Direkt API için)
        # PayTR Direkt API hash: SHA256(merchant_id + merchant_key + payment_amount + merchant_oid + merchant_salt)
        hash_str = f"{self.merchant_id}{self.secret_key}{amount_cents}{order_id}{self.store_key}"
        hash_value = hashlib.sha256(hash_str.encode()).hexdigest()
        form_data['hash'] = hash_value
        
        # Direkt API için HTML form oluştur
        html_form = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Ödeme Yönlendiriliyor...</title>
            <style>
                body {{
                    font-family: Arial, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    background: #f5f7fa;
                }}
                .container {{
                    text-align: center;
                }}
                .spinner {{
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #3498db;
                    border-radius: 50%;
                    width: 50px;
                    height: 50px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 20px;
                }}
                @keyframes spin {{
                    0% {{ transform: rotate(0deg); }}
                    100% {{ transform: rotate(360deg); }}
                }}
            </style>
        </head>
        <body>
            <div class="container">
                <div class="spinner"></div>
                <h2>Ödeme sayfasına yönlendiriliyorsunuz...</h2>
                <p>Lütfen bekleyin</p>
            </div>
            <form id="paytrForm" method="post" action="{self.CALLBACK_URL}">
        """
        
        for key, value in form_data.items():
            # HTML escape yap (XSS koruması için)
            safe_value = html.escape(str(value))
            html_form += f'                <input type="hidden" name="{key}" value="{safe_value}">\n'
        
        html_form += """
            </form>
            <script>
                // Sayfa yüklendiğinde formu otomatik submit et
                window.onload = function() {
                    document.getElementById('paytrForm').submit();
                };
                // Alternatif olarak DOMContentLoaded kullan
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('paytrForm').submit();
                    });
                } else {
                    // DOM zaten yüklüyse direkt submit et
                    document.getElementById('paytrForm').submit();
                }
            </script>
        </body>
        </html>
        """
        
        return {
            'success': True,
            'payment_url': '',  # Direkt API için URL yok, HTML form var
            'html_content': html_form,  # HTML form içeriği
            'form_data': form_data,  # Form verileri (debug için)
            'transaction_id': order_id,
        }
    
    def verify_payment(self, transaction_id: str, **kwargs) -> Dict[str, Any]:
        """Verify payment status"""
        # PayTR callback'ten gelen bilgileri doğrula
        # PayTR callback hash: SHA256(merchant_oid + merchant_salt + status + total_amount)
        merchant_oid = kwargs.get('merchant_oid', transaction_id)
        status = kwargs.get('status', '')
        total_amount = kwargs.get('total_amount', '0')
        hash_str = f"{merchant_oid}{self.store_key}{status}{total_amount}"
        calculated_hash = hashlib.sha256(hash_str.encode()).hexdigest()
        
        if calculated_hash == kwargs.get('hash'):
            return {
                'success': True,
                'status': 'completed' if kwargs.get('status') == 'success' else 'failed',
                'amount': Decimal(kwargs.get('total_amount', 0)) / 100,
                'transaction_id': kwargs.get('merchant_oid', ''),
            }
        else:
            return {
                'success': False,
                'error': 'Hash doğrulama başarısız',
            }
    
    def refund(self, transaction_id: str, amount: Optional[Decimal] = None,
               **kwargs) -> Dict[str, Any]:
        """Refund payment"""
        # PayTR iade API'si
        return {
            'success': False,
            'error': 'PayTR iade API entegrasyonu yapılacak',
        }
    
    def handle_webhook(self, payload: Dict[str, Any], headers: Dict[str, Any]) -> Dict[str, Any]:
        """Handle PayTR webhook (callback)"""
        merchant_oid = payload.get('merchant_oid', '')
        status = payload.get('status', '')
        total_amount = payload.get('total_amount', 0)
        
        # Hash doğrulama
        hash_str = f"{merchant_oid}{self.merchant_id}{status}{total_amount}"
        calculated_hash = hashlib.sha256(
            (hash_str + self.store_key).encode()
        ).hexdigest()
        
        if calculated_hash == payload.get('hash'):
            return {
                'success': True,
                'transaction_id': merchant_oid,
                'status': 'completed' if status == 'success' else 'failed',
            }
        else:
            return {
                'success': False,
                'error': 'Hash doğrulama başarısız',
            }

