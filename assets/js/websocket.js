class HospitalWS {
    constructor() {
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnect = 5;
        this.reconnectDelay = 2000;
        this.userId = null;
        this.callbacks = {};
    }

    connect(userId, role) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) return;

        this.userId = userId;
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        this.ws = new WebSocket(`${protocol}//${window.location.hostname}:8080`);

        this.ws.onopen = () => {
            console.log('WebSocket connected');
            this.reconnectAttempts = 0;
            this.send({ action: 'subscribe', user_id: userId, role });
        };

        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleMessage(data);
        };

        this.ws.onclose = () => {
            console.log('WebSocket disconnected');
            this.reconnect();
        };

        this.ws.onerror = (err) => {
            console.error('WebSocket error:', err);
        };
    }

    reconnect() {
        if (this.reconnectAttempts >= this.maxReconnect) return;
        this.reconnectAttempts++;
        setTimeout(() => this.connect(this.userId, 'patient'), this.reconnectDelay * this.reconnectAttempts);
    }

    send(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        }
    }

    handleMessage(data) {
        switch (data.type) {
            case 'subscribed':
                console.log('Subscribed to real-time updates');
                break;
            case 'token_update':
                this.updateTokenUI(data);
                this.triggerCallback('token_update', data);
                break;
            case 'notification':
                this.showNotification(data);
                this.triggerCallback('notification', data);
                break;
            case 'ambulance_update':
                this.triggerCallback('ambulance_update', data);
                break;
            case 'queue_update':
                this.triggerCallback('queue_update', data);
                break;
        }
    }

    updateTokenUI(data) {
        const tokenEl = document.getElementById('live-token-number');
        const positionEl = document.getElementById('live-position');
        const aheadEl = document.getElementById('live-people-ahead');
        const waitEl = document.getElementById('live-wait-time');
        const statusEl = document.getElementById('live-status');

        if (tokenEl) tokenEl.textContent = '#' + data.token_number;
        if (positionEl) positionEl.textContent = data.position;
        if (aheadEl) aheadEl.textContent = data.people_ahead;
        if (waitEl) waitEl.textContent = data.estimated_wait + ' min';
        if (statusEl) {
            statusEl.textContent = data.status.toUpperCase();
            statusEl.className = 'token-status status-' + data.status;
        }

        if (data.status === 'called') {
            this.playAlertSound();
            this.showBrowserNotification('Token Called!', 'Your token ' + data.token_number + ' has been called. Please proceed to the doctor.');
        }
    }

    showNotification(data) {
        this.showBrowserNotification(data.title, data.message);
        this.showToast(data.message, data.notification_type);
    }

    showBrowserNotification(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body, icon: '/hospital/assets/img/logo.png' });
        }
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        
        const icons = { info: 'info-circle', success: 'check-circle', warning: 'exclamation-triangle', error: 'times-circle' };
        const colors = { info: '#4299e1', success: '#48bb78', warning: '#ed8936', error: '#f56565' };
        
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.style.cssText = `background:${colors[type]};color:#fff;padding:12px 20px;border-radius:8px;margin-bottom:10px;display:flex;align-items:center;gap:10px;animation:slideIn 0.3s ease;`;
        toast.innerHTML = `<i class="fas fa-${icons[type]}"></i><span>${message}</span>`;
        
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
    }

    playAlertSound() {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdH2LkpGKgHRpaGx2g42UlIyBesJYZ3J9iZOTjIF1aWhsd4SMk5KMgHRnaGx2hIyTko2BdGdobHaEjJOSjYF0Z2hsdoSMk5KNgXRnaGx2hIyTko2BdGdobHaEjJOSjYF0Z2hsdoSMk5KNgXRnaGx2hIyTko2BdGdobHaEjJOSjYF0Z2hsdoSMk5KNgXRnaGx2hIyTko2BdGdobHaEjJOSjYF0Z2hsdoSMk5KNgXRnaGx2hIyTko2B');
        audio.volume = 0.5;
        audio.play().catch(() => {});
    }

    on(event, callback) {
        if (!this.callbacks[event]) this.callbacks[event] = [];
        this.callbacks[event].push(callback);
    }

    triggerCallback(event, data) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(cb => cb(data));
        }
    }

    disconnect() {
        if (this.ws) this.ws.close();
    }
}

window.HospitalWS = HospitalWS;
