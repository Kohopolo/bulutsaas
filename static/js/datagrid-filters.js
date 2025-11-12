/**
 * Otomatik Tablo Filtreleme Sistemi
 * Tüm .datagrid tablolarına otomatik olarak filtre input'ları ekler
 */

document.addEventListener('DOMContentLoaded', function() {
    // Tüm .datagrid tablolarını bul
    const datagrids = document.querySelectorAll('.datagrid table');
    
    datagrids.forEach(table => {
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        
        if (!thead || !tbody) return;
        
        // Eğer zaten filtre satırı varsa, atla
        if (thead.querySelector('.filter-row')) return;
        
        // İlk satırı (header) al
        const headerRow = thead.querySelector('tr:first-child');
        if (!headerRow) return;
        
        // Filtre satırı oluştur
        const filterRow = document.createElement('tr');
        filterRow.className = 'filter-row';
        
        // Her sütun için filtre input'u oluştur
        headerRow.querySelectorAll('th').forEach((th, index) => {
            const filterTh = document.createElement('th');
            
            // Eğer sütun "İşlemler" veya boşsa, filtre ekleme
            const headerText = th.textContent.trim().toLowerCase();
            if (headerText.includes('işlem') || headerText.includes('action') || headerText === '') {
                filterTh.innerHTML = '';
            } else {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'filter-input';
                input.placeholder = 'Filtrele...';
                input.setAttribute('data-column', index);
                filterTh.appendChild(input);
            }
            
            filterRow.appendChild(filterTh);
        });
        
        // Filtre satırını header'a ekle
        thead.appendChild(filterRow);
        
        // Filtreleme fonksiyonunu ekle
        const filterInputs = filterRow.querySelectorAll('.filter-input');
        filterInputs.forEach(input => {
            input.addEventListener('input', function() {
                const filterValue = this.value.toLowerCase();
                const columnIndex = parseInt(this.getAttribute('data-column'));
                const rows = tbody.querySelectorAll('tr');
                
                rows.forEach(row => {
                    const cell = row.children[columnIndex];
                    if (cell) {
                        const cellText = cell.textContent.toLowerCase();
                        if (cellText.includes(filterValue)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            });
        });
    });
    
    // Standart tablolar için de filtre ekle (class="datagrid" olmayan tablolar)
    const standardTables = document.querySelectorAll('table:not(.datagrid table)');
    standardTables.forEach(table => {
        // Eğer tablo zaten .datagrid içindeyse atla
        if (table.closest('.datagrid')) return;
        
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        
        if (!thead || !tbody) return;
        
        // Eğer zaten filtre satırı varsa, atla
        if (thead.querySelector('.filter-row')) return;
        
        // İlk satırı (header) al
        const headerRow = thead.querySelector('tr:first-child');
        if (!headerRow) return;
        
        // Tabloyu .datagrid container'a sar
        if (!table.closest('.datagrid')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'datagrid';
            const container = document.createElement('div');
            container.className = 'vb-datagrid-container';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(container);
            container.appendChild(table);
        }
        
        // Filtre satırı oluştur
        const filterRow = document.createElement('tr');
        filterRow.className = 'filter-row';
        
        // Her sütun için filtre input'u oluştur
        headerRow.querySelectorAll('th').forEach((th, index) => {
            const filterTh = document.createElement('th');
            
            // Eğer sütun "İşlemler" veya boşsa, filtre ekleme
            const headerText = th.textContent.trim().toLowerCase();
            if (headerText.includes('işlem') || headerText.includes('action') || headerText === '') {
                filterTh.innerHTML = '';
            } else {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'filter-input';
                input.placeholder = 'Filtrele...';
                input.setAttribute('data-column', index);
                filterTh.appendChild(input);
            }
            
            filterRow.appendChild(filterTh);
        });
        
        // Filtre satırını header'a ekle
        thead.appendChild(filterRow);
        
        // Filtreleme fonksiyonunu ekle
        const filterInputs = filterRow.querySelectorAll('.filter-input');
        filterInputs.forEach(input => {
            input.addEventListener('input', function() {
                const filterValue = this.value.toLowerCase();
                const columnIndex = parseInt(this.getAttribute('data-column'));
                const rows = tbody.querySelectorAll('tr');
                
                rows.forEach(row => {
                    const cell = row.children[columnIndex];
                    if (cell) {
                        const cellText = cell.textContent.toLowerCase();
                        if (cellText.includes(filterValue)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            });
        });
    });
});

