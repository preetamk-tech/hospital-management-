$(document).ready(function() {
    // Auto logout after 30 minutes of inactivity
    let inactivityTime = function() {
        let time;
        window.onload = resetTimer;
        document.onmousemove = resetTimer;
        document.onkeypress = resetTimer;
        
        function logout() {
            window.location.href = SITE_URL + 'auth/logout.php';
        }
        
        function resetTimer() {
            clearTimeout(time);
            time = setTimeout(logout, 1800000); // 30 minutes
        }
    };
    inactivityTime();
    
    // CSRF Token for AJAX
    function getCSRFToken() {
        return $('meta[name="csrf-token"]').attr('content');
    }
    
    // Form validation
    $('form').on('submit', function(e) {
        let isValid = true;
        
        // Check required fields
        $(this).find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('error');
                showToast('Please fill all required fields', 'error');
                e.preventDefault();
                return false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Email validation
        let email = $(this).find('input[type="email"]').val();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showToast('Please enter a valid email address', 'error');
            e.preventDefault();
            return false;
        }
        
        // Password confirmation
        let password = $(this).find('input[name="password"]').val();
        let confirm = $(this).find('input[name="confirm_password"]').val();
        if (password && confirm && password !== confirm) {
            showToast('Passwords do not match', 'error');
            e.preventDefault();
            return false;
        }
        
        return isValid;
    });
    
    // Live search - FIXED: proper quotes and syntax
    $('.live-search').on('keyup', function() {
        let search = $(this).val().toLowerCase();
        let target = $(this).data('target');
        $(target + ' tr').each(function() {
            let text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(search) > -1);
        });
    });
    
    // Confirm before dangerous actions - FIXED: added e parameter
    $('.confirm-action').click(function(e) {
        if (!confirm('Are you sure you want to perform this action?')) {
            e.preventDefault();
        }
    });
    
    // Auto-hide alerts - FIXED: proper this reference
    setTimeout(function() {
        $('.alert').fadeOut(500, function() {
            $(this).remove();
        });
    }, 5000);
    
    // Add loading spinner on form submit
    $('form').on('submit', function() {
        let btn = $(this).find('button[type="submit"]');
        if (btn.length) {
            btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            btn.prop('disabled', true);
        }
    });
});

// Site URL variable
var SITE_URL = typeof SITE_URL !== 'undefined' ? SITE_URL : '/';