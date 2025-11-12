/**
 * JSON Form Widgets - Kullanıcı Dostu JSON Form Yönetimi
 */

// Key-Value Widget Class
class KeyValueWidget {
    constructor(containerId, fieldName, keyType = 'text', valueType = 'text') {
        this.container = document.getElementById(containerId);
        this.fieldName = fieldName;
        this.keyType = keyType;
        this.valueType = valueType;
        this.hiddenInput = document.getElementById(`${fieldName}_json`);
        this.pairsContainer = this.container.querySelector('.pairs-container');
        this.addBtn = this.container.querySelector('.add-pair-btn');
        
        if (this.container) {
            this.init();
        }
    }
    
    init() {
        // Mevcut veriyi yükle
        if (this.hiddenInput && this.hiddenInput.value) {
            try {
                const data = JSON.parse(this.hiddenInput.value);
                this.pairs = Object.entries(data);
            } catch (e) {
                this.pairs = [];
            }
        } else {
            this.pairs = [];
        }
        
        // Event listener'ları ekle
        this.addBtn.addEventListener('click', () => this.addPair());
        
        // Mevcut pair'ler için event listener'lar
        this.pairsContainer.querySelectorAll('.pair-item').forEach((item, index) => {
            if (item.style.display !== 'none') {
                this.attachPairEvents(item);
            }
        });
    }
    
    attachPairEvents(item) {
        const removeBtn = item.querySelector('.remove-pair-btn');
        const inputs = item.querySelectorAll('input');
        
        removeBtn.addEventListener('click', () => {
            item.remove();
            this.updateHiddenInput();
        });
        
        inputs.forEach(input => {
            input.addEventListener('input', () => this.updateHiddenInput());
        });
    }
    
    addPair(key = '', value = '') {
        const template = this.pairsContainer.querySelector('.pair-item[style*="display: none"]') || 
                        this.pairsContainer.querySelector('.pair-item').cloneNode(true);
        const newPair = template.cloneNode(true);
        newPair.style.display = 'flex';
        newPair.querySelector('.pair-key').value = key;
        newPair.querySelector('.pair-value').value = value;
        this.pairsContainer.appendChild(newPair);
        this.attachPairEvents(newPair);
        this.updateHiddenInput();
    }
    
    updateHiddenInput() {
        const pairs = [];
        this.pairsContainer.querySelectorAll('.pair-item:not([style*="display: none"])').forEach(item => {
            const key = item.querySelector('.pair-key').value.trim();
            const value = item.querySelector('.pair-value').value.trim();
            if (key) {
                let finalValue = value;
                if (this.valueType === 'number') {
                    try {
                        finalValue = value.includes('.') ? parseFloat(value) : parseInt(value);
                    } catch {
                        finalValue = 0;
                    }
                }
                pairs.push([key, finalValue]);
            }
        });
        this.hiddenInput.value = JSON.stringify(Object.fromEntries(pairs));
    }
}

// Object List Widget Class
class ObjectListWidget {
    constructor(containerId, fieldName, fieldsConfig) {
        this.container = document.getElementById(containerId);
        this.fieldName = fieldName;
        this.fieldsConfig = fieldsConfig;
        this.hiddenInput = document.getElementById(`${fieldName}_json`);
        this.objectsContainer = this.container.querySelector('.objects-container');
        this.addBtn = this.container.querySelector('.add-object-btn');
        
        if (this.container) {
            this.init();
        }
    }
    
    init() {
        // Mevcut veriyi yükle
        if (this.hiddenInput && this.hiddenInput.value) {
            try {
                this.objects = JSON.parse(this.hiddenInput.value);
            } catch (e) {
                this.objects = [];
            }
        } else {
            this.objects = [];
        }
        
        // Event listener'ları ekle
        this.addBtn.addEventListener('click', () => this.addObject());
        
        // Mevcut object'ler için event listener'lar
        this.objectsContainer.querySelectorAll('.object-item').forEach((item, index) => {
            if (item.style.display !== 'none') {
                this.attachObjectEvents(item, index);
            }
        });
    }
    
    attachObjectEvents(item, objIndex) {
        const removeBtn = item.querySelector('.remove-object-btn');
        const inputs = item.querySelectorAll('.object-field');
        
        removeBtn.addEventListener('click', () => {
            item.remove();
            this.updateHiddenInput();
        });
        
        inputs.forEach((input, fieldIndex) => {
            input.name = `${this.fieldName}_obj_${objIndex}_field_${fieldIndex}`;
            input.addEventListener('input', () => this.updateHiddenInput());
        });
    }
    
    addObject() {
        const template = this.objectsContainer.querySelector('.object-item[style*="display: none"]') || 
                        this.objectsContainer.querySelector('.object-item').cloneNode(true);
        const newObject = template.cloneNode(true);
        newObject.style.display = 'block';
        const objIndex = this.objectsContainer.querySelectorAll('.object-item:not([style*="display: none"])').length;
        newObject.querySelector('h4').textContent = 'Kural ' + (objIndex + 1);
        
        // Input name'lerini güncelle
        newObject.querySelectorAll('.object-field').forEach((input, fieldIndex) => {
            input.name = `${this.fieldName}_obj_${objIndex}_field_${fieldIndex}`;
            input.value = '';
        });
        
        this.objectsContainer.appendChild(newObject);
        this.attachObjectEvents(newObject, objIndex);
        this.updateHiddenInput();
    }
    
    updateHiddenInput() {
        const objects = [];
        let objIndex = 0;
        this.objectsContainer.querySelectorAll('.object-item:not([style*="display: none"])').forEach(item => {
            const obj = {};
            let fieldIndex = 0;
            item.querySelectorAll('.object-field').forEach(input => {
                const fieldConfig = this.fieldsConfig[fieldIndex];
                const value = input.value.trim();
                if (value) {
                    let finalValue = value;
                    if (fieldConfig.type === 'number') {
                        try {
                            finalValue = value.includes('.') ? parseFloat(value) : parseInt(value);
                        } catch {
                            finalValue = 0;
                        }
                    }
                    obj[fieldConfig.name] = finalValue;
                }
                fieldIndex++;
            });
            if (Object.keys(obj).length > 0) {
                objects.push(obj);
            }
            objIndex++;
        });
        this.hiddenInput.value = JSON.stringify(objects);
    }
}

// Weekday Prices Widget Class
class WeekdayPricesWidget {
    constructor(containerId, fieldName) {
        this.container = document.getElementById(containerId);
        this.fieldName = fieldName;
        this.hiddenInput = document.getElementById(`${fieldName}_json`);
        this.priceInputs = this.container.querySelectorAll('.weekday-price');
        
        if (this.container) {
            this.init();
        }
    }
    
    init() {
        this.priceInputs.forEach(input => {
            input.addEventListener('input', () => this.updateHiddenInput());
        });
        this.updateHiddenInput();
    }
    
    updateHiddenInput() {
        const prices = {};
        this.priceInputs.forEach(input => {
            const dayKey = input.name.replace(`${this.fieldName}_`, '');
            const value = input.value.trim();
            if (value) {
                try {
                    prices[dayKey] = parseFloat(value);
                } catch {
                    // Hata durumunda atla
                }
            }
        });
        this.hiddenInput.value = JSON.stringify(prices);
    }
}

// Auto-initialize widgets on page load
document.addEventListener('DOMContentLoaded', function() {
    // Key-Value widgets
    document.querySelectorAll('.key-value-widget').forEach(widget => {
        const hiddenInput = widget.querySelector('input[type="hidden"]');
        if (hiddenInput) {
            const fieldName = hiddenInput.id.replace('_json', '');
            new KeyValueWidget(widget.id, fieldName);
        }
    });
    
    // Object List widgets
    document.querySelectorAll('.object-list-widget').forEach(widget => {
        const hiddenInput = widget.querySelector('input[type="hidden"]');
        if (hiddenInput) {
            const fieldName = hiddenInput.id.replace('_json', '');
            // Fields config template'den alınacak
            new ObjectListWidget(widget.id, fieldName, []);
        }
    });
    
    // Weekday Prices widgets
    document.querySelectorAll('.weekday-prices-widget').forEach(widget => {
        const hiddenInput = widget.querySelector('input[type="hidden"]');
        if (hiddenInput) {
            const fieldName = hiddenInput.id.replace('_json', '');
            new WeekdayPricesWidget(widget.id, fieldName);
        }
    });
});
