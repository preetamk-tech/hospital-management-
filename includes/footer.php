</main>
    
    <footer style="background: white; padding: 2rem; text-align: center; margin-top: 3rem; border-top: 1px solid #e2e8f0; transition: all 0.3s ease;">
        <p>&copy; <?php echo date('Y'); ?> MediQueue - Hospital Queue Management System</p>
        <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #718096;">
            <i class="fas fa-heart" style="color: #e53e3e;"></i> By <b>PREETAM KUMAR </b>and <b>ALI KHAN </b>from BCA AI&DS 
        </p>
    </footer>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo SITE_URL; ?>assets/js/script.js"></script>
    <script src="<?php echo SITE_URL; ?>assets/js/notifications.js"></script>
    <?php if(isLoggedIn()): ?>
    <script src="<?php echo SITE_URL; ?>assets/js/chatbot.js"></script>
    <?php endif; ?>
    
    <script>
        // Dark Mode Toggle - Fixed working implementation
        const darkModeToggle = document.getElementById('darkModeToggle');
        if(darkModeToggle) {
            darkModeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                const isDark = document.body.classList.contains('dark-mode');
                // Set cookie with 1 year expiry
                const expires = new Date();
                expires.setFullYear(expires.getFullYear() + 1);
                document.cookie = "dark_mode=" + (isDark ? 'enabled' : 'disabled') + "; path=/; expires=" + expires.toUTCString();
                // Update icon
                const icon = this.querySelector('i');
                if(icon) {
                    icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
                }
                // Reload to apply dark mode CSS properly
                location.reload();
            });
        }
        
        // Check for dark mode on load and apply class
        if(document.cookie.indexOf('dark_mode=enabled') > -1) {
            document.body.classList.add('dark-mode');
        }
        
        // Toast function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            const icons = { success: '✓', error: '✗', warning: '⚠', info: 'ℹ' };
            toast.innerHTML = `
                <div class="toast-icon">${icons[type] || 'ℹ'}</div>
                <div class="toast-message">${message}</div>
                <div class="toast-close">&times;</div>
            `;
            document.getElementById('toastContainer').appendChild(toast);
            
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.onclick = () => toast.remove();
            
            setTimeout(() => toast.remove(), 5000);
        }
        
        window.showToast = showToast;
        
        // CSRF Token for AJAX
        $.ajaxSetup({
            headers: {
                'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
            }
        });
        
        // Form validation helper
        function validateForm(form) {
            let isValid = true;
            $(form).find('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            const email = $(form).find('input[type="email"]').val();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showToast('Please enter a valid email address', 'error');
                isValid = false;
            }
            
            return isValid;
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            $('.alert').fadeOut(500, function() { $(this).remove(); });
        }, 5000);
        
        // Add loading spinner on form submit
        $('form').on('submit', function() {
            let btn = $(this).find('button[type="submit"]');
            if(btn.length && !btn.data('no-spinner')) {
                btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...');
                btn.prop('disabled', true);
                setTimeout(() => {
                    btn.html(btn.data('original-text') || btn.text());
                    btn.prop('disabled', false);
                }, 3000);
            }
        });
    </script>
</body>
</html>
