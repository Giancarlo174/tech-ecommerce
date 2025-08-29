/**
 * Westito Chatbot - JavaScript Frontend
 * Sistema de chat integrado con Westech Ecommerce
 */

class WestitoChatbot {
    constructor() {
        this.isOpen = false;
        this.conversationId = this.generateConversationId();
        this.messageHistory = [];
        this.isTyping = false;
        this.rateLimitCount = 0;
        this.rateLimitTimer = null;
        
        this.init();
    }

    init() {
        this.createChatbotHTML();
        this.bindEvents();
        this.showWelcomeMessage();
    }

    generateConversationId() {
        return 'westito_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    createChatbotHTML() {
        const chatbotHTML = `
            <div class="westito-chatbot" id="westito-chatbot">
                <button class="westito-chatbot-toggle" id="westito-toggle">
                    <span class="westito-chatbot-icon">🤖</span>
                </button>
                
                <div class="westito-chatbot-widget" id="westito-widget">
                    <div class="westito-chatbot-header">
                        <div class="westito-chatbot-header-content">
                            <div class="westito-avatar">🤖</div>
                            <div class="westito-info">
                                <h3>Westito</h3>
                                <p>Asistente de Westech</p>
                            </div>
                        </div>
                        <button class="westito-chatbot-close" id="westito-close">×</button>
                    </div>
                    
                    <div class="westito-welcome-message">
                        ¡Hola! Soy Westito, tu asistente virtual. Pregúntame sobre productos, categorías o tu historial de compras.
                    </div>
                    
                    <div class="westito-chatbot-messages" id="westito-messages"></div>
                    
                    <div class="westito-quick-suggestions" id="westito-suggestions">
                        <div class="westito-suggestion" data-message="¿Qué productos tienen disponibles?">Ver productos</div>
                        <div class="westito-suggestion" data-message="¿Cuáles son las categorías?">Categorías</div>
                        <div class="westito-suggestion" data-message="Muéstrame laptops">Laptops</div>
                        <div class="westito-suggestion" data-message="¿Tienen smartphones baratos?">Smartphones</div>
                    </div>
                    
                    <div class="westito-chatbot-input-container">
                        <form class="westito-chatbot-input-form" id="westito-form">
                            <textarea 
                                class="westito-chatbot-input" 
                                id="westito-input" 
                                placeholder="Escribe tu pregunta aquí..."
                                rows="1"
                                maxlength="500"
                            ></textarea>
                            <button type="submit" class="westito-chatbot-send" id="westito-send">
                                <span>→</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', chatbotHTML);
    }

    bindEvents() {
        const toggle = document.getElementById('westito-toggle');
        const close = document.getElementById('westito-close');
        const form = document.getElementById('westito-form');
        const input = document.getElementById('westito-input');
        const suggestions = document.getElementById('westito-suggestions');

        // Toggle chatbot
        toggle.addEventListener('click', () => this.toggleChatbot());
        close.addEventListener('click', () => this.closeChatbot());

        // Form submission
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });

        // Auto-resize textarea
        input.addEventListener('input', () => this.autoResizeTextarea(input));

        // Enter key to send (Shift+Enter for new line)
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Quick suggestions
        suggestions.addEventListener('click', (e) => {
            if (e.target.classList.contains('westito-suggestion')) {
                const message = e.target.getAttribute('data-message');
                input.value = message;
                this.sendMessage();
            }
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            const chatbot = document.getElementById('westito-chatbot');
            if (this.isOpen && !chatbot.contains(e.target)) {
                this.closeChatbot();
            }
        });

        // Reset rate limit every minute
        setInterval(() => {
            this.rateLimitCount = 0;
        }, 60000);
    }

    toggleChatbot() {
        if (this.isOpen) {
            this.closeChatbot();
        } else {
            this.openChatbot();
        }
    }

    openChatbot() {
        const widget = document.getElementById('westito-widget');
        const toggle = document.getElementById('westito-toggle');
        
        widget.classList.add('open');
        toggle.classList.add('active');
        this.isOpen = true;

        // Focus input
        setTimeout(() => {
            document.getElementById('westito-input').focus();
        }, 300);
    }

    closeChatbot() {
        const widget = document.getElementById('westito-widget');
        const toggle = document.getElementById('westito-toggle');
        
        widget.classList.remove('open');
        toggle.classList.remove('active');
        this.isOpen = false;
    }

    showWelcomeMessage() {
        setTimeout(() => {
            this.addMessage('¡Hola! Soy Westito, tu asistente de Westech Ecommerce. ¿En qué puedo ayudarte hoy?', 'bot');
        }, 1000);
    }

    autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
    }

    async sendMessage() {
        const input = document.getElementById('westito-input');
        const message = input.value.trim();

        if (!message || this.isTyping) return;

        // Rate limiting check
        if (this.rateLimitCount >= 5) {
            this.showNotification('Has enviado muchos mensajes. Espera un momento antes de continuar.', 'warning');
            return;
        }

        this.rateLimitCount++;
        
        // Add user message
        this.addMessage(message, 'user');
        
        // Clear input
        input.value = '';
        input.style.height = 'auto';

        // Show typing indicator
        this.showTyping();

        try {
            const response = await this.callChatbotAPI(message);
            
            this.hideTyping();

            if (response.success) {
                this.addMessage(response.message, 'bot');
                this.conversationId = response.conversation_id || this.conversationId;
            } else {
                this.addMessage(response.message || 'Ocurrió un error. Intenta de nuevo.', 'bot');
            }
        } catch (error) {
            console.error('Chatbot Error:', error);
            this.hideTyping();
            this.addMessage('Lo siento, estoy teniendo problemas técnicos. Intenta de nuevo en unos momentos.', 'bot');
        }
    }

    async callChatbotAPI(message) {
        // Usar BASE_URL si está disponible, sino construir la URL
        let apiUrl;
        
        if (typeof window.WESTECH_BASE_URL !== 'undefined') {
            apiUrl = window.WESTECH_BASE_URL + 'api/chatbot.php';
        } else {
            // Fallback: construir URL manualmente
            const currentPath = window.location.pathname;
            let baseUrl = window.location.origin;
            
            // Detectar si estamos en una subcarpeta (cliente, admin, etc.)
            if (currentPath.includes('/cliente/') || currentPath.includes('/admin/')) {
                // Extraer la ruta base hasta ds6p2
                const pathParts = currentPath.split('/');
                const ds6p2Index = pathParts.findIndex(part => part === 'ds6p2');
                if (ds6p2Index !== -1) {
                    baseUrl += '/' + pathParts.slice(1, ds6p2Index + 1).join('/');
                } else {
                    // Fallback: asumir que estamos en ds6p2
                    baseUrl += currentPath.substring(0, currentPath.lastIndexOf('/cliente/') || currentPath.lastIndexOf('/admin/') || currentPath.length);
                }
            } else {
                // Si estamos en la raíz, usar la ruta actual sin el archivo
                baseUrl += currentPath.replace(/\/[^\/]*$/, '');
            }
            
            apiUrl = baseUrl + '/api/chatbot.php';
        }
        
        console.log('Chatbot API URL:', apiUrl); // Para debug

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: message,
                conversation_id: this.conversationId
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    addMessage(message, sender) {
        const messagesContainer = document.getElementById('westito-messages');
        const messageElement = document.createElement('div');
        messageElement.className = `westito-message ${sender}`;
        messageElement.textContent = message;

        messagesContainer.appendChild(messageElement);
        this.scrollToBottom();

        // Store in history
        this.messageHistory.push({
            message: message,
            sender: sender,
            timestamp: new Date()
        });
    }

    showTyping() {
        if (this.isTyping) return;
        
        this.isTyping = true;
        const messagesContainer = document.getElementById('westito-messages');
        
        const typingElement = document.createElement('div');
        typingElement.className = 'westito-typing';
        typingElement.id = 'westito-typing-indicator';
        typingElement.innerHTML = `
            <span>Westito está escribiendo</span>
            <div class="westito-typing-dots">
                <div class="westito-typing-dot"></div>
                <div class="westito-typing-dot"></div>
                <div class="westito-typing-dot"></div>
            </div>
        `;

        messagesContainer.appendChild(typingElement);
        this.scrollToBottom();
    }

    hideTyping() {
        this.isTyping = false;
        const typingElement = document.getElementById('westito-typing-indicator');
        if (typingElement) {
            typingElement.remove();
        }
    }

    scrollToBottom() {
        const messagesContainer = document.getElementById('westito-messages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'warning' ? '#f59e0b' : '#3b82f6'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            z-index: 10001;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Remove after 4 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 4000);
    }

    // Public methods for external integration
    openChat() {
        this.openChatbot();
    }

    sendQuickMessage(message) {
        if (!this.isOpen) {
            this.openChatbot();
        }
        
        setTimeout(() => {
            const input = document.getElementById('westito-input');
            input.value = message;
            this.sendMessage();
        }, 300);
    }

    getConversationHistory() {
        return this.messageHistory;
    }
}

// Initialize chatbot when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in a page that should have the chatbot
    const excludedPages = ['/login.php', '/registro.php'];
    const currentPath = window.location.pathname;
    
    const shouldLoadChatbot = !excludedPages.some(page => currentPath.includes(page));
    
    if (shouldLoadChatbot) {
        window.westitoChatbot = new WestitoChatbot();
    }
});

// Global functions for external access
window.WestitoAPI = {
    open: () => window.westitoChatbot?.openChat(),
    sendMessage: (message) => window.westitoChatbot?.sendQuickMessage(message),
    getHistory: () => window.westitoChatbot?.getConversationHistory()
};
