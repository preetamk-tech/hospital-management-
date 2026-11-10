class MediBot {
    constructor() {
        this.history = [];
        this.isOpen = false;
        this.isTyping = false;
        this.config = window.MediBotConfig || {};
        this.commands = this.getCommands();
        this.init();
    }

    init() {
        this.createChatbotHTML();
        this.bindEvents();
    }

    createChatbotHTML() {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'chatbot-toggle';
        toggleBtn.id = 'chatbotToggle';
        toggleBtn.innerHTML = '<i class="fas fa-comments"></i>';

        const container = document.createElement('div');
        container.className = 'chatbot-container';
        container.id = 'chatbotContainer';
        container.innerHTML = `
            <div class="chatbot-header">
                <div class="chatbot-header-left">
                    <div class="chatbot-header-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="chatbot-header-info">
                        <h3>MediBot</h3>
                        <p>${this.getSubtitle()}</p>
                    </div>
                </div>
                <div class="chatbot-header-right">
                    <button class="chatbot-header-btn" id="chatFullscreenBtn" title="Fullscreen"><i class="fas fa-expand"></i></button>
                </div>
            </div>
            <div class="chatbot-messages" id="chatMessages">
                <div class="chat-message bot">
                    <div class="chat-avatar"><i class="fas fa-robot"></i></div>
                    <div class="chat-bubble">${this.getGreeting()}</div>
                </div>
            </div>
            <div class="chatbot-commands" id="chatCommands">
                ${this.commands.map(command => `
                    <button type="button" class="chat-command" data-command="${this.escapeAttribute(command.prompt)}">
                        <i class="fas ${command.icon}"></i> ${this.escapeHtml(command.label)}
                    </button>
                `).join('')}
            </div>
            <div class="chat-input-wrapper">
                <input type="text" id="chatInput" placeholder="Type your message..." autocomplete="off">
                <button class="chat-send-btn" id="chatSendBtn"><i class="fas fa-paper-plane"></i></button>
            </div>
        `;

        document.body.appendChild(toggleBtn);
        document.body.appendChild(container);
    }

    bindEvents() {
        document.getElementById('chatbotToggle').addEventListener('click', () => this.toggle());
        document.getElementById('chatSendBtn').addEventListener('click', () => this.sendMessage());
        document.getElementById('chatFullscreenBtn').addEventListener('click', () => this.toggleFullscreen());
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
        document.querySelectorAll('.chat-command').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('chatInput').value = button.dataset.command;
                this.sendMessage();
            });
        });
    }

    toggle() {
        this.isOpen = !this.isOpen;
        const container = document.getElementById('chatbotContainer');
        const toggle = document.getElementById('chatbotToggle');

        container.classList.toggle('active', this.isOpen);
        toggle.classList.toggle('active', this.isOpen);

        if (this.isOpen) {
            document.getElementById('chatInput').focus();
        }
    }

    toggleFullscreen() {
        const container = document.getElementById('chatbotContainer');
        const btn = document.getElementById('chatFullscreenBtn');
        container.classList.toggle('fullscreen');

        if (container.classList.contains('fullscreen')) {
            btn.innerHTML = '<i class="fas fa-compress"></i>';
            btn.title = 'Exit Fullscreen';
        } else {
            btn.innerHTML = '<i class="fas fa-expand"></i>';
            btn.title = 'Fullscreen';
        }
    }

    async sendMessage() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();

        if (!message || this.isTyping) return;

        this.addMessage(message, 'user');
        this.history.push({ role: 'user', content: message });
        input.value = '';

        this.isTyping = true;
        this.showTyping();
        this.disableInput(true);

        try {
            const response = await fetch(this.config.apiUrl || 'api/chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message,
                    history: this.history.slice(-10),
                    context: {
                        role: this.config.role || '',
                        page: this.config.page || '',
                        userName: this.config.userName || ''
                    }
                })
            });

            const data = await response.json();
            this.removeTyping();

            if (data.reply) {
                this.addMessage(data.reply, 'bot');
                this.history.push({ role: 'assistant', content: data.reply });
            } else {
                this.addMessage('Sorry, I could not process your request.', 'bot');
            }
        } catch (error) {
            this.removeTyping();
            this.addMessage('Connection error. Please try again.', 'bot');
        }

        this.isTyping = false;
        this.disableInput(false);
        document.getElementById('chatInput').focus();
    }

    addMessage(text, sender) {
        const messagesDiv = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${sender}`;

        const icon = sender === 'user' ? 'fa-user' : 'fa-robot';
        messageDiv.innerHTML = `
            <div class="chat-avatar"><i class="fas ${icon}"></i></div>
            <div class="chat-bubble">${this.escapeHtml(text)}</div>
        `;

        messagesDiv.appendChild(messageDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    showTyping() {
        const messagesDiv = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chat-message bot';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = `
            <div class="chat-avatar"><i class="fas fa-robot"></i></div>
            <div class="chat-bubble">
                <div class="typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `;
        messagesDiv.appendChild(typingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    removeTyping() {
        const typing = document.getElementById('typingIndicator');
        if (typing) typing.remove();
    }

    disableInput(disabled) {
        document.getElementById('chatInput').disabled = disabled;
        document.getElementById('chatSendBtn').disabled = disabled;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    escapeAttribute(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    getSubtitle() {
        const role = this.config.role || 'visitor';
        if (role === 'visitor') return 'Hospital assistant';
        return `${role.charAt(0).toUpperCase() + role.slice(1)} dashboard assistant`;
    }

    getGreeting() {
        const role = this.config.role || 'visitor';
        if (role === 'visitor') {
            return "Hello! I'm MediBot. I can help you find login, registration, appointments, live queue, and hospital services.";
        }
        const name = this.config.userName || 'there';
        return `Hello ${name}! I'm MediBot. I can guide you through your ${role} dashboard. Pick a command below or ask in your own words.`;
    }

    getCommands() {
        const role = this.config.role || 'visitor';
        const common = [
            { label: 'What can I do here?', icon: 'fa-circle-question', prompt: 'What can I do on this dashboard?' },
            { label: 'Show important modules', icon: 'fa-layer-group', prompt: 'List the important modules available in this hospital system.' }
        ];

        const roleCommands = {
            visitor: [
                { label: 'Login', icon: 'fa-right-to-bracket', prompt: 'How do I login to the hospital system?' },
                { label: 'Register', icon: 'fa-user-plus', prompt: 'How do I register as a patient?' },
                { label: 'Book appointment', icon: 'fa-calendar-plus', prompt: 'How do appointments work in this hospital system?' },
                { label: 'Live queue', icon: 'fa-list-ol', prompt: 'How can I see the live queue?' },
                { label: 'Video consult', icon: 'fa-video', prompt: 'Does this system support online video consultation?' },
                { label: 'Hospital services', icon: 'fa-hospital', prompt: 'What services and modules are available here?' }
            ],
            patient: [
                { label: 'Track token', icon: 'fa-ticket-alt', prompt: 'How do I track my current token and queue position?' },
                { label: 'Book appointment', icon: 'fa-calendar-plus', prompt: 'How do I book an appointment?' },
                { label: 'Virtual queue', icon: 'fa-clock', prompt: 'How do I join the virtual queue from home?' },
                { label: 'Lab results', icon: 'fa-vials', prompt: 'How do I book a lab test and view results?' },
                { label: 'Prescription PDF', icon: 'fa-file-pdf', prompt: 'How do I download my prescription PDF?' },
                { label: 'Video consult', icon: 'fa-video', prompt: 'How do I join an online video consultation?' }
            ],
            doctor: [
                { label: 'Call next token', icon: 'fa-bell', prompt: 'How do I call the next token and notify the patient?' },
                { label: 'Add vitals', icon: 'fa-heartbeat', prompt: 'How do I record patient vitals?' },
                { label: 'Prescription', icon: 'fa-file-medical', prompt: 'How do I use e-prescription templates and create a prescription?' },
                { label: 'Follow-up', icon: 'fa-calendar-check', prompt: 'How do I schedule a follow-up?' },
                { label: 'Video consult', icon: 'fa-video', prompt: 'How do I start an online consultation?' },
                { label: 'Leave request', icon: 'fa-calendar-minus', prompt: 'How do I apply for leave?' }
            ],
            receptionist: [
                { label: 'Walk-in token', icon: 'fa-user-plus', prompt: 'How do I create a walk-in appointment and token?' },
                { label: 'Emergency token', icon: 'fa-truck-medical', prompt: 'How do I create an emergency appointment?' },
                { label: 'View queue', icon: 'fa-list-ol', prompt: 'How do I monitor today’s live queue?' },
                { label: 'Ambulance', icon: 'fa-ambulance', prompt: 'How do I manage ambulance requests?' },
                { label: 'Patient history', icon: 'fa-history', prompt: 'How do I search a patient history?' }
            ],
            admin: [
                { label: 'Analytics', icon: 'fa-chart-line', prompt: 'What analytics are available and how do I read them?' },
                { label: 'Billing', icon: 'fa-file-invoice-dollar', prompt: 'How do I create and manage invoices?' },
                { label: 'Inventory', icon: 'fa-pills', prompt: 'How do I manage pharmacy inventory and low stock?' },
                { label: 'Beds', icon: 'fa-bed', prompt: 'How do I manage beds and rooms?' },
                { label: 'Audit logs', icon: 'fa-shield-halved', prompt: 'How do audit logs help with compliance?' },
                { label: 'Staff attendance', icon: 'fa-user-clock', prompt: 'How do I manage staff attendance?' }
            ]
        };

        return [...(roleCommands[role] || roleCommands.visitor), ...common].slice(0, 8);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new MediBot();
});
