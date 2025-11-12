/**
 * Modern Drag & Drop Galeri Yöneticisi
 * Otel ve Oda galerileri için çoklu resim yükleme, silme, düzenleme ve sıralama
 */
class GalleryManager {
    constructor(options) {
        this.container = document.getElementById(options.containerId);
        this.dropzone = document.getElementById(options.dropzoneId);
        this.uploadInput = document.getElementById(options.uploadInputId);
        this.grid = document.getElementById(options.gridId);
        this.progress = document.getElementById(options.progressId);
        this.progressBar = document.getElementById(options.progressBarId);
        this.progressText = document.getElementById(options.progressTextId);
        this.uploadUrl = options.uploadUrl;
        this.deleteUrl = options.deleteUrl;
        this.updateUrl = options.updateUrl;
        this.reorderUrl = options.reorderUrl;
        this.csrfToken = options.csrfToken;
        this.type = options.type || 'hotel';
        this.sortable = null;
    }

    init() {
        if (!this.container) return;

        // Drag & Drop event listeners
        this.setupDragAndDrop();
        
        // File input change
        if (this.uploadInput) {
            this.uploadInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }

        // Delete buttons
        this.setupDeleteButtons();

        // Edit buttons
        this.setupEditButtons();

        // Sortable (drag to reorder)
        this.setupSortable();
    }

    setupDragAndDrop() {
        if (!this.dropzone) return;

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropzone.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
        });

        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropzone.addEventListener(eventName, () => {
                this.dropzone.classList.add('border-vb-primary', 'bg-blue-50');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.dropzone.addEventListener(eventName, () => {
                this.dropzone.classList.remove('border-vb-primary', 'bg-blue-50');
            }, false);
        });

        // Handle dropped files
        this.dropzone.addEventListener('drop', (e) => this.handleDrop(e), false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        this.uploadFiles(files);
    }

    handleFileSelect(e) {
        const files = e.target.files;
        if (files.length > 0) {
            this.uploadFiles(files);
        }
    }

    async uploadFiles(files) {
        const formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            // Dosya boyutu kontrolü (10MB)
            if (files[i].size > 10 * 1024 * 1024) {
                alert(`${files[i].name} dosyası çok büyük. Maksimum 10MB olmalıdır.`);
                continue;
            }
            formData.append('images', files[i]);
        }

        if (formData.getAll('images').length === 0) {
            return;
        }

        // Progress göster
        this.showProgress(0, 'Yükleniyor...');

        try {
            const response = await fetch(this.uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRFToken': this.csrfToken,
                },
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                // Yüklenen resimleri ekle
                data.images.forEach(image => {
                    this.addImageToGrid(image);
                });

                // Hataları göster
                if (data.errors && data.errors.length > 0) {
                    alert('Bazı resimler yüklenemedi:\n' + data.errors.join('\n'));
                }

                this.hideProgress();
            } else {
                alert('Resim yükleme hatası: ' + (data.error || 'Bilinmeyen hata'));
                this.hideProgress();
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Resim yüklenirken bir hata oluştu.');
            this.hideProgress();
        }

        // Input'u temizle
        if (this.uploadInput) {
            this.uploadInput.value = '';
        }
    }

    addImageToGrid(image) {
        const item = document.createElement('div');
        item.className = 'gallery-item relative group';
        item.setAttribute('data-image-id', image.id);
        item.innerHTML = `
            <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden border-2 border-gray-200">
                <img src="${image.url}" alt="${image.title || ''}" class="w-full h-full object-cover">
            </div>
            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100">
                <div class="flex space-x-2">
                    <button type="button" class="gallery-edit-btn px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700" data-image-id="${image.id}" title="Düzenle">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="gallery-delete-btn px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700" data-image-id="${image.id}" title="Sil">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="absolute top-1 left-1 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded cursor-move" title="Sürükle">
                <i class="fas fa-grip-vertical"></i>
            </div>
        `;
        
        this.grid.appendChild(item);
        
        // Event listener'ları ekle
        const deleteBtn = item.querySelector('.gallery-delete-btn');
        const editBtn = item.querySelector('.gallery-edit-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => this.deleteImage(image.id, item));
        }
        if (editBtn) {
            editBtn.addEventListener('click', () => this.editImage(image.id));
        }
    }

    setupDeleteButtons() {
        this.grid.addEventListener('click', (e) => {
            if (e.target.closest('.gallery-delete-btn')) {
                const btn = e.target.closest('.gallery-delete-btn');
                const imageId = btn.getAttribute('data-image-id');
                const item = btn.closest('.gallery-item');
                this.deleteImage(imageId, item);
            }
        });
    }

    setupEditButtons() {
        this.grid.addEventListener('click', (e) => {
            if (e.target.closest('.gallery-edit-btn')) {
                const btn = e.target.closest('.gallery-edit-btn');
                const imageId = btn.getAttribute('data-image-id');
                this.editImage(imageId);
            }
        });
    }

    async deleteImage(imageId, itemElement) {
        if (!confirm('Bu resmi silmek istediğinizden emin misiniz?')) {
            return;
        }

        try {
            const response = await fetch(this.deleteUrl + imageId + '/', {
                method: 'POST',
                headers: {
                    'X-CSRFToken': this.csrfToken,
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();

            if (data.success) {
                if (itemElement && itemElement.remove) {
                    itemElement.remove();
                } else {
                    // Fallback: itemElement bir DOM elementi değilse
                    const item = this.grid.querySelector(`[data-image-id="${imageId}"]`);
                    if (item) item.remove();
                }
            } else {
                alert('Resim silinirken bir hata oluştu.');
            }
        } catch (error) {
            console.error('Delete error:', error);
            alert('Resim silinirken bir hata oluştu.');
        }
    }

    editImage(imageId) {
        // Basit bir modal veya inline düzenleme
        const title = prompt('Resim başlığı:', '');
        if (title === null) return;

        const description = prompt('Resim açıklaması:', '');
        const isActive = confirm('Resim aktif mi?');

        this.updateImage(imageId, {
            title: title,
            description: description || '',
            is_active: isActive,
        });
    }

    async updateImage(imageId, data) {
        const formData = new FormData();
        formData.append('title', data.title);
        formData.append('description', data.description);
        formData.append('is_active', data.is_active ? 'true' : 'false');

        try {
            const response = await fetch(this.updateUrl + imageId + '/', {
                method: 'POST',
                headers: {
                    'X-CSRFToken': this.csrfToken,
                },
                body: formData,
            });

            const result = await response.json();

            if (result.success) {
                // Başarılı - görsel geri bildirim
                const item = this.grid.querySelector(`[data-image-id="${imageId}"]`);
                if (item) {
                    item.style.opacity = '0.5';
                    setTimeout(() => {
                        item.style.opacity = '1';
                    }, 300);
                }
            } else {
                alert('Resim güncellenirken bir hata oluştu.');
            }
        } catch (error) {
            console.error('Update error:', error);
            alert('Resim güncellenirken bir hata oluştu.');
        }
    }

    setupSortable() {
        if (typeof Sortable === 'undefined') {
            console.warn('SortableJS yüklenmedi. Sıralama özelliği çalışmayacak.');
            return;
        }

        this.sortable = new Sortable(this.grid, {
            animation: 150,
            handle: '.cursor-move',
            onEnd: () => {
                this.saveOrder();
            }
        });
    }

    async saveOrder() {
        const items = this.grid.querySelectorAll('.gallery-item');
        const imageIds = Array.from(items).map(item => item.getAttribute('data-image-id'));

        try {
            const response = await fetch(this.reorderUrl, {
                method: 'POST',
                headers: {
                    'X-CSRFToken': this.csrfToken,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ image_ids: imageIds }),
            });

            const data = await response.json();

            if (!data.success) {
                console.error('Sıralama kaydedilemedi');
            }
        } catch (error) {
            console.error('Reorder error:', error);
        }
    }

    showProgress(percent, text) {
        if (this.progress) {
            this.progress.classList.remove('hidden');
            if (this.progressBar) {
                this.progressBar.style.width = percent + '%';
            }
            if (this.progressText) {
                this.progressText.textContent = text;
            }
        }
    }

    hideProgress() {
        if (this.progress) {
            this.progress.classList.add('hidden');
        }
    }
}

