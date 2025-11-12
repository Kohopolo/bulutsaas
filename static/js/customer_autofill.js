/**
 * Müşteri Otomatik Form Doldurma
 * TC No, Email veya Telefon ile müşteri bilgilerini otomatik doldurur
 */

(function() {
    'use strict';

    // Form alanlarını otomatik doldur
    function fillCustomerForm(customerData, formPrefix = '') {
        const prefix = formPrefix ? formPrefix + '_' : '';
        
        // Temel bilgiler
        const firstNameField = document.getElementById(prefix + 'customer_name') || 
                               document.getElementById(prefix + 'first_name') ||
                               document.querySelector(`[name="${prefix}customer_name"]`) ||
                               document.querySelector(`[name="${prefix}first_name"]`);
        const lastNameField = document.getElementById(prefix + 'customer_surname') || 
                              document.getElementById(prefix + 'last_name') ||
                              document.querySelector(`[name="${prefix}customer_surname"]`) ||
                              document.querySelector(`[name="${prefix}last_name"]`);
        const emailField = document.getElementById(prefix + 'customer_email') || 
                           document.getElementById(prefix + 'email') ||
                           document.querySelector(`[name="${prefix}customer_email"]`) ||
                           document.querySelector(`[name="${prefix}email"]`);
        const phoneField = document.getElementById(prefix + 'customer_phone') || 
                          document.getElementById(prefix + 'phone') ||
                          document.querySelector(`[name="${prefix}customer_phone"]`) ||
                          document.querySelector(`[name="${prefix}phone"]`);
        const tcField = document.getElementById(prefix + 'customer_tc') || 
                       document.getElementById(prefix + 'tc_no') ||
                       document.getElementById(prefix + 'customer_tax_id') ||
                       document.querySelector(`[name="${prefix}customer_tc"]`) ||
                       document.querySelector(`[name="${prefix}tc_no"]`) ||
                       document.querySelector(`[name="${prefix}customer_tax_id"]`);
        const addressField = document.getElementById(prefix + 'customer_address') || 
                            document.getElementById(prefix + 'address') ||
                            document.querySelector(`[name="${prefix}customer_address"]`) ||
                            document.querySelector(`[name="${prefix}address"]`);
        const cityField = document.getElementById(prefix + 'city') ||
                         document.querySelector(`[name="${prefix}city"]`);
        
        // Müşteri ID (hidden field)
        const customerIdField = document.getElementById(prefix + 'customer') ||
                                document.querySelector(`[name="${prefix}customer"]`);

        // Alanları doldur
        if (firstNameField && customerData.first_name) {
            firstNameField.value = customerData.first_name;
            firstNameField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        if (lastNameField && customerData.last_name) {
            lastNameField.value = customerData.last_name;
            lastNameField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        if (emailField && customerData.email) {
            emailField.value = customerData.email;
            emailField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        if (phoneField && customerData.phone) {
            phoneField.value = customerData.phone;
            phoneField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        if (tcField && customerData.tc_no) {
            tcField.value = customerData.tc_no;
            tcField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        if (addressField && customerData.address) {
            addressField.value = customerData.address;
            addressField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        if (cityField && customerData.city) {
            cityField.value = customerData.city;
            cityField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        if (customerIdField) {
            customerIdField.value = customerData.id;
            customerIdField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        // Başarı mesajı göster
        showCustomerFoundMessage(customerData);
    }

    // Müşteri bulundu mesajı göster
    function showCustomerFoundMessage(customerData) {
        // Mevcut mesajı kaldır
        const existingMsg = document.getElementById('customer-autofill-message');
        if (existingMsg) {
            existingMsg.remove();
        }
        
        // Yeni mesaj oluştur
        const messageDiv = document.createElement('div');
        messageDiv.id = 'customer-autofill-message';
        messageDiv.className = 'mt-2 p-3 bg-green-100 border border-green-400 text-green-700 rounded';
        messageDiv.innerHTML = `
            <i class="fas fa-check-circle mr-2"></i>
            <strong>Müşteri bulundu:</strong> ${customerData.first_name} ${customerData.last_name} 
            (${customerData.customer_code})
            ${customerData.vip_level !== 'regular' ? `<span class="ml-2 px-2 py-1 bg-yellow-200 text-yellow-800 rounded text-xs">VIP: ${customerData.vip_level}</span>` : ''}
            ${customerData.loyalty_points > 0 ? `<span class="ml-2 px-2 py-1 bg-blue-200 text-blue-800 rounded text-xs">${customerData.loyalty_points} Puan</span>` : ''}
        `;
        
        // İlk input alanından sonra ekle
        const firstInput = document.querySelector('input[id*="customer"], input[name*="customer"], input[id*="email"], input[name*="email"]');
        if (firstInput && firstInput.parentElement) {
            firstInput.parentElement.appendChild(messageDiv);
        }
        
        // 5 saniye sonra kaldır
        setTimeout(() => {
            if (messageDiv.parentElement) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // AJAX ile müşteri ara
    function searchCustomer(tcNo, email, phone) {
        const url = '/ajax/find-customer/';
        const data = {
            tc_no: tcNo || '',
            email: email || '',
            phone: phone || ''
        };
        
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRFToken': getCookie('csrftoken')
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customer) {
                return data.customer;
            } else {
                throw new Error(data.message || 'Müşteri bulunamadı');
            }
        });
    }

    // CSRF Token al
    function getCookie(name) {
        let cookieValue = null;
        if (document.cookie && document.cookie !== '') {
            const cookies = document.cookie.split(';');
            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                if (cookie.substring(0, name.length + 1) === (name + '=')) {
                    cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                    break;
                }
            }
        }
        return cookieValue;
    }

    // Sayfa yüklendiğinde event listener'ları ekle
    document.addEventListener('DOMContentLoaded', function() {
        // TC No, Email veya Telefon alanlarına blur event'i ekle
        const tcFields = document.querySelectorAll('input[id*="tc"], input[name*="tc"], input[id*="tax_id"], input[name*="tax_id"]');
        const emailFields = document.querySelectorAll('input[type="email"], input[id*="email"], input[name*="email"]');
        const phoneFields = document.querySelectorAll('input[id*="phone"], input[name*="phone"], input[type="tel"]');
        
        let searchTimeout;
        
        function triggerSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Tüm alanlardan değerleri al
                let tcNo = '';
                let email = '';
                let phone = '';
                
                tcFields.forEach(field => {
                    if (field.value && field.value.trim()) {
                        tcNo = field.value.trim();
                    }
                });
                
                emailFields.forEach(field => {
                    if (field.value && field.value.trim()) {
                        email = field.value.trim();
                    }
                });
                
                phoneFields.forEach(field => {
                    if (field.value && field.value.trim()) {
                        phone = field.value.trim();
                    }
                });
                
                // En az bir alan doluysa ara
                if (tcNo || email || phone) {
                    searchCustomer(tcNo, email, phone)
                        .then(customer => {
                            fillCustomerForm(customer);
                        })
                        .catch(error => {
                            // Müşteri bulunamadı - sessizce devam et
                            console.log('Müşteri bulunamadı:', error.message);
                        });
                }
            }, 800); // 800ms bekle (kullanıcı yazmayı bitirsin)
        }
        
        // Event listener'ları ekle
        [...tcFields, ...emailFields, ...phoneFields].forEach(field => {
            field.addEventListener('blur', triggerSearch);
            field.addEventListener('keyup', function(e) {
                // Enter tuşuna basıldığında da ara
                if (e.key === 'Enter') {
                    triggerSearch();
                }
            });
        });
    });

    // Global olarak erişilebilir yap
    window.CustomerAutofill = {
        fillCustomerForm: fillCustomerForm,
        searchCustomer: searchCustomer
    };
})();

