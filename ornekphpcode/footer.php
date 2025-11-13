</div>
    </div>

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Tabler JS -->
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta17/dist/js/tabler.min.js"></script>

<!-- Electron Integration JS -->
<script src="/assets/js/electron-integration.js"></script>
    
    <!-- Custom JS -->
    <script>
        // jQuery yüklendikten sonra çalıştır
        $(document).ready(function() {
            // CSRF token'ı tüm AJAX isteklerine ekle
            $.ajaxSetup({
                beforeSend: function(xhr, settings) {
                    if (!/^(GET|HEAD|OPTIONS|TRACE)$/i.test(settings.type) && !this.crossDomain) {
                        xhr.setRequestHeader("X-CSRFToken", $('meta[name=csrf-token]').attr('content'));
                    }
                }
            });

            // Otomatik mesaj gizleme
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);

            // Onay gerektiren işlemler için
            $('.confirm-delete').on('click', function(e) {
                if (!confirm('Bu işlemi gerçekleştirmek istediğinizden emin misiniz?')) {
                    e.preventDefault();
                }
            });

            // Tarih seçiciler için
            $('input[type="date"]').each(function() {
                if (!$(this).val()) {
                    $(this).val(new Date().toISOString().split('T')[0]);
                }
            });

            // Telefon formatı
            $('input[type="tel"]').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length >= 10) {
                    value = value.replace(/(\d{3})(\d{3})(\d{2})(\d{2})/, '($1) $2 $3 $4');
                }
                $(this).val(value);
            });

            // Para formatı
            $('.money-format').on('input', function() {
                let value = $(this).val().replace(/[^\d.,]/g, '');
                $(this).val(value);
            });

            // Aktif sayfa vurgulama
            let currentPage = window.location.pathname.split('/').pop();
            $('.navbar-nav .nav-link').each(function() {
                let href = $(this).attr('href');
                if (href === currentPage) {
                    $(this).addClass('active');
                }
            });
        });
        
        // Connection status indicator
        function updateConnectionStatus() {
            const status = document.getElementById('connection-status');
            if (status) {
                if (navigator.onLine) {
                    status.innerHTML = '<i class="fas fa-wifi me-1"></i>Online';
                    status.className = 'badge bg-success';
                } else {
                    status.innerHTML = '<i class="fas fa-wifi-slash me-1"></i>Offline';
                    status.className = 'badge bg-warning';
                }
            }
        }
        
        // Update connection status on load and events
        document.addEventListener('DOMContentLoaded', updateConnectionStatus);
        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
    </script>
    
    <!-- Offline Manager -->
    <script src="/assets/js/offline-manager.js"></script>
</body>
</html>