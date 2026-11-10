// Request notification permission
if ('Notification' in window) {
    Notification.requestPermission();
}

// Browser Notification
function showBrowserNotification(title, message, url = null) {
    if (Notification.permission === 'granted') {
        const notification = new Notification(title, {
            body: message,
            icon: SITE_URL + 'assets/img/bell-icon.png',
            vibrate: [200, 100, 200]
        });
        
        notification.onclick = function() {
            window.focus();
            if (url) window.location.href = url;
        };
        
        setTimeout(function() {
            notification.close();
        }, 8000);
    }
}

// Play sound
function playNotificationSound() {
    const audio = new Audio(SITE_URL + 'assets/sounds/notification.mp3');
    audio.play().catch(function(e) {
        console.log('Audio not supported');
    });
}

// Start token monitoring
let tokenMonitorInterval = null;

function startTokenMonitoring(tokenId, userId) {
    if (tokenMonitorInterval) {
        clearInterval(tokenMonitorInterval);
        tokenMonitorInterval = null;
    }
    
    tokenMonitorInterval = setInterval(function() {
        $.ajax({
            url: SITE_URL + 'api/check_token_status.php',
            method: 'GET',
            data: { token_id: tokenId },
            success: function(response) {
                if (response.status === 'called') {
                    showBrowserNotification(
                        'Token Called! 🏥',
                        'Your token #' + response.token_number + ' is ready. Please proceed to doctor.',
                        SITE_URL + 'patient/my_token.php'
                    );
                    showToast('Token #' + response.token_number + ' is now being called!', 'warning');
                    playNotificationSound();
                    clearInterval(tokenMonitorInterval);
                    tokenMonitorInterval = null;
                } else if (response.status === 'waiting' && response.position <= 3) {
                    if (!response.warning_shown) {
                        showToast('⚠️ Your token #' + response.token_number + ' is next in line!', 'warning');
                    }
                }
            }
        });
    }, 30000);
}

// Stop token monitoring
function stopTokenMonitoring() {
    if (tokenMonitorInterval) {
        clearInterval(tokenMonitorInterval);
        tokenMonitorInterval = null;
    }
}

// Check for new notifications
function checkNotifications(userId) {
    $.ajax({
        url: SITE_URL + 'api/get_notifications.php',
        method: 'GET',
        data: { user_id: userId },
        success: function(notifications) {
            if (notifications && notifications.length) {
                for (var i = 0; i < notifications.length; i++) {
                    var notif = notifications[i];
                    showBrowserNotification(notif.title, notif.message);
                    showToast(notif.message, notif.type);
                    if (notif.type === 'warning') {
                        playNotificationSound();
                    }
                }
            }
        }
    });
}