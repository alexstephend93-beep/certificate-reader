<style>
    /* AI Chat Widget Styles */
    .ai-chat-widget {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 10000;
        font-family: var(--font-primary);
    }
    
    /* Chat Toggle Button */
    .chat-toggle-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--gradient-primary);
        border: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        position: relative;
        animation: pulse-ring 2s infinite;
    }
    
    .chat-toggle-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }
    
    .chat-toggle-btn .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        font-size: 0.7rem;
        padding: 3px 7px;
        border-radius: 50%;
        font-weight: bold;
    }
    
    /* Pulse Animation */
    @keyframes pulse-ring {
        0% {
            box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4);
        }
        70% {
            box-shadow: 0 0 0 15px rgba(99, 102, 241, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
        }
    }
    
    /* Chat Window */
    .chat-window {
        position: fixed;
        bottom: 100px;
        right: 30px;
        width: 380px;
        height: 600px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: all 0.3s ease;
        animation: floatIn 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
    }
    
    .chat-window.minimized {
        display: none;
    }
    
    /* Maximized Chat Window */
    .chat-window.maximized {
        position: fixed;
        top: 20px;
        left: 20px;
        right: 20px;
        bottom: 20px;
        width: auto;
        height: auto;
        border-radius: 24px;
        z-index: 10001;
    }
    
    .chat-window.maximized .chat-messages {
        height: calc(100vh - 180px);
    }
    
    @keyframes floatIn {
        from {
            opacity: 0;
            transform: translateY(50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Chat Header */
    .chat-header {
        background: var(--gradient-primary);
        padding: 15px 20px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    
    .chat-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        animation: shine 3s infinite;
    }
    
    @keyframes shine {
        to {
            left: 100%;
        }
    }
    
    .chat-header h4 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .chat-header-actions {
        display: flex;
        gap: 10px;
    }
    
    .chat-header-actions button {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        padding: 0;
    }
    
    .chat-header-actions button:hover {
        transform: scale(1.1);
        opacity: 0.8;
    }
    
    .maximize-btn {
        transition: transform 0.3s ease;
    }
    
    .maximize-btn:hover {
        transform: scale(1.1) rotate(90deg);
    }
    
    /* Chat Messages Area */
    .chat-messages {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px;
        background: #f8fafc;
    }
    
    /* Message Bubbles */
    .message {
        margin-bottom: 20px;
        display: flex;
        animation: messageSlideIn 0.3s ease forwards;
    }
    
    @keyframes messageSlideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .message:last-child .message-bubble {
        animation: bounceIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    @keyframes bounceIn {
        0% {
            transform: scale(0.8);
            opacity: 0;
        }
        50% {
            transform: scale(1.05);
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .message.user {
        justify-content: flex-end;
    }
    
    .message.assistant {
        justify-content: flex-start;
    }
    
    .message-bubble {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 18px;
        word-wrap: break-word;
        line-height: 1.5;
        font-size: 0.9rem;
    }
    
    .message.user .message-bubble {
        background: var(--gradient-primary);
        color: white;
        border-bottom-right-radius: 4px;
    }
    
    .message.assistant .message-bubble {
        background: white;
        color: var(--color-dark);
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
    }
    
    .message-bubble pre {
        background: rgba(0, 0, 0, 0.05);
        padding: 10px;
        border-radius: 8px;
        overflow-x: auto;
        font-size: 0.8rem;
        margin: 8px 0;
    }
    
    .message-bubble code {
        background: rgba(0, 0, 0, 0.05);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.85rem;
    }
    
    .message-time {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 5px;
        display: block;
    }
    
    /* Thinking Indicator */
    .thinking-indicator {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background: white;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        width: fit-content;
    }
    
    .thinking-dot {
        width: 8px;
        height: 8px;
        background: var(--color-primary);
        border-radius: 50%;
        animation: typingWave 1.4s infinite ease-in-out;
    }
    
    .thinking-dot:nth-child(1) { animation-delay: -0.32s; }
    .thinking-dot:nth-child(2) { animation-delay: -0.16s; }
    
    @keyframes typingWave {
        0%, 60%, 100% {
            transform: translateY(0);
        }
        30% {
            transform: translateY(-10px);
        }
    }
    
    /* Chat Input Area */
    .chat-input-area {
        padding: 15px;
        background: white;
        border-top: 1px solid #e2e8f0;
    }
    
    .chat-input-wrapper {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    
    .chat-input {
        flex-grow: 1;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 10px 15px;
        font-size: 0.9rem;
        resize: none;
        font-family: inherit;
        max-height: 100px;
        transition: all 0.3s ease;
    }
    
    .chat-input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
    }
    
    .send-btn {
        background: var(--gradient-primary);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    
    .send-btn:hover {
        animation: glowPulse 1s infinite;
    }
    
    @keyframes glowPulse {
        0%, 100% {
            box-shadow: 0 0 5px rgba(99, 102, 241, 0.5);
        }
        50% {
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.8);
        }
    }
    
    .send-btn:hover:not(:disabled) {
        transform: scale(1.05);
    }
    
    .send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Quick Actions */
    .quick-actions {
        padding: 10px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 8px;
        overflow-x: auto;
    }
    
    .quick-action-btn {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 6px 12px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        white-space: nowrap;
    }
    
    .quick-action-btn:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: translateY(-3px) scale(1.05);
    }
    
    /* Chat Actions Menu */
    .chat-actions-menu {
        position: absolute;
        bottom: 80px;
        right: 30px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        z-index: 10001;
        animation: slideUp 0.2s ease;
        min-width: 200px;
    }
    
    .chat-actions-menu button {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        border: none;
        background: white;
        width: 100%;
        text-align: left;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }
    
    .chat-actions-menu button:hover {
        background: #f1f5f9;
    }
    
    .chat-actions-menu button.danger {
        color: #dc2626;
    }
    
    .chat-actions-menu button.danger:hover {
        background: #fee2e2;
    }
    
    .chat-actions-menu hr {
        margin: 5px 0;
        border-color: #e2e8f0;
    }
    
    /* Ripple Effect */
    .ripple-effect {
        position: relative;
        overflow: hidden;
    }
    
    /* Toast Notification */
    .toast-notification {
        position: fixed;
        bottom: 100px;
        right: 30px;
        padding: 12px 20px;
        border-radius: 10px;
        z-index: 10002;
        animation: slideUp 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .toast-notification.success {
        background: #10b981;
        color: white;
    }
    
    .toast-notification.error {
        background: #ef4444;
        color: white;
    }
    
    .toast-notification.info {
        background: #3b82f6;
        color: white;
    }
    
    .toast-notification.warning {
        background: #f59e0b;
        color: white;
    }
    
    /* Confirmation Dialog */
    .confirm-dialog {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        z-index: 10003;
        min-width: 300px;
        max-width: 400px;
        animation: floatIn 0.3s ease;
    }
    
    .confirm-dialog-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10002;
        animation: fadeIn 0.3s ease;
    }
    
    .confirm-dialog h4 {
        margin: 0 0 10px 0;
        color: var(--color-dark);
    }
    
    .confirm-dialog p {
        margin: 0 0 20px 0;
        color: #64748b;
    }
    
    .confirm-dialog-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .confirm-dialog-actions button {
        padding: 8px 16px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .confirm-dialog-actions button.cancel {
        background: #e2e8f0;
        color: #475569;
    }
    
    .confirm-dialog-actions button.confirm {
        background: #dc2626;
        color: white;
    }
    
    .confirm-dialog-actions button.confirm:hover {
        background: #b91c1c;
        transform: scale(1.02);
    }
    
    .confirm-dialog-actions button.cancel:hover {
        background: #cbd5e1;
        transform: scale(1.02);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .chat-window {
            width: calc(100vw - 40px);
            height: calc(100vh - 120px);
            right: 20px;
            bottom: 80px;
        }
        
        .chat-window.maximized {
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border-radius: 20px;
        }
        
        .ai-chat-widget {
            bottom: 20px;
            right: 20px;
        }
        
        .toast-notification {
            bottom: 80px;
            right: 20px;
            left: 20px;
        }
        
        .confirm-dialog {
            margin: 20px;
            min-width: auto;
            width: calc(100% - 40px);
        }
    }
</style>

<div class="ai-chat-widget">
    <!-- Chat Toggle Button -->
    <button class="chat-toggle-btn" id="chatToggleBtn">
        <i class="bi bi-chat-dots-fill"></i>
        <span class="notification-badge" id="chatNotification" style="display: none;">1</span>
    </button>
    
    <!-- Chat Window -->
    <div class="chat-window minimized" id="chatWindow">
        <div class="chat-header" id="chatHeader">
            <h4>
                <i class="bi bi-robot"></i>
                AI Assistant
            </h4>
            <div class="chat-header-actions">
                <button id="chatActionsBtn" class="ripple-effect" title="More options">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <button id="maximizeChatBtn" class="maximize-btn ripple-effect" title="Maximize (Alt+M)">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
                <button id="minimizeChatBtn" class="ripple-effect" title="Minimize">
                    <i class="bi bi-dash"></i>
                </button>
                <button id="closeChatBtn" class="ripple-effect" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        
        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="message assistant">
                <div class="message-bubble">
                    👋 Hi! I'm your AI assistant. I can help you with:
                    <br><br>
                    • 🔐 SSL/TLS certificates
                    • 🔑 JWT token analysis
                    • 🌐 API testing & debugging
                    • 🔒 Encryption & hashing
                    <br><br>
                    <strong>How can I help you today?</strong>
                    <span class="message-time">{{ now()->format('g:i A') }}</span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions" id="quickActions">
            <button class="quick-action-btn" data-prompt="How to generate SSL certificate on Ubuntu?">🔐 SSL Certificate</button>
            <button class="quick-action-btn" data-prompt="How to validate JWT token?">🔑 JWT Help</button>
            <button class="quick-action-btn" data-prompt="API testing best practices">🌐 API Testing</button>
            <button class="quick-action-btn" data-prompt="Difference between SHA-256 and MD5">🔒 Hash Guide</button>
        </div>
        
        <!-- Input Area -->
        <div class="chat-input-area">
            <div class="chat-input-wrapper">
                <textarea class="chat-input" id="chatInput" placeholder="Type your message..." rows="1"></textarea>
                <button class="send-btn ripple-effect" id="sendMessageBtn">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
            <small class="text-muted chat-note" style="display: block; padding: 4px 12px; font-size: 0.75rem;">
                <i class="bi bi-info-circle me-1"></i> AI is text-only. Images and file attachments are not supported.
            </small>
        </div>
    </div>
</div>

<script>
// AI Chat Widget JavaScript with Clear Chat History
class AIChatWidget {
    constructor() {
        this.isOpen = false;
        this.isThinking = false;
        this.isMaximized = false;
        this.currentConversationId = null;
        this.messageCount = 0;
        this.init();
    }
    
    init() {
        // DOM Elements
        this.toggleBtn = document.getElementById('chatToggleBtn');
        this.chatWindow = document.getElementById('chatWindow');
        this.chatMessages = document.getElementById('chatMessages');
        this.chatInput = document.getElementById('chatInput');
        this.sendBtn = document.getElementById('sendMessageBtn');
        this.minimizeBtn = document.getElementById('minimizeChatBtn');
        this.closeBtn = document.getElementById('closeChatBtn');
        this.actionsBtn = document.getElementById('chatActionsBtn');
        this.maximizeBtn = document.getElementById('maximizeChatBtn');
        this.notification = document.getElementById('chatNotification');
        
        // Event Listeners
        this.toggleBtn.addEventListener('click', () => this.toggleChat());
        this.minimizeBtn.addEventListener('click', () => this.minimizeChat());
        this.closeBtn.addEventListener('click', () => this.closeChat());
        
        if (this.maximizeBtn) {
            this.maximizeBtn.addEventListener('click', () => this.maximizeChat());
        }
        
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        
        this.chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Auto-resize textarea
        this.chatInput.addEventListener('input', () => {
            this.chatInput.style.height = 'auto';
            this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 100) + 'px';
        });
        
        // Quick actions
        document.querySelectorAll('.quick-action-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const prompt = btn.dataset.prompt;
                this.chatInput.value = prompt;
                this.sendMessage();
            });
        });
        
        // Actions menu with clear history option
        let actionsMenu = null;
        this.actionsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (actionsMenu) {
                actionsMenu.remove();
                actionsMenu = null;
                return;
            }
            
            actionsMenu = document.createElement('div');
            actionsMenu.className = 'chat-actions-menu';
            actionsMenu.innerHTML = `
                <button id="clearChatBtn" class="danger">
                    <i class="bi bi-trash"></i> Clear Chat History
                </button>
                <hr>
                <button id="exportChatBtn">
                    <i class="bi bi-download"></i> Export Chat
                </button>
                <button id="copyChatBtn">
                    <i class="bi bi-clipboard"></i> Copy Last Response
                </button>
                <hr>
                <button id="deleteLastMsgBtn">
                    <i class="bi bi-arrow-undo"></i> Delete Last Message
                </button>
            `;
            document.body.appendChild(actionsMenu);
            
            // Position menu
            const rect = this.actionsBtn.getBoundingClientRect();
            actionsMenu.style.position = 'fixed';
            actionsMenu.style.bottom = (window.innerHeight - rect.top + 10) + 'px';
            actionsMenu.style.right = (window.innerWidth - rect.right) + 'px';
            
            document.getElementById('clearChatBtn').addEventListener('click', () => {
                this.showClearConfirmDialog();
                actionsMenu.remove();
                actionsMenu = null;
            });
            
            document.getElementById('exportChatBtn').addEventListener('click', () => {
                this.exportConversation();
                actionsMenu.remove();
                actionsMenu = null;
            });
            
            document.getElementById('copyChatBtn').addEventListener('click', () => {
                this.copyLastResponse();
                actionsMenu.remove();
                actionsMenu = null;
            });
            
            document.getElementById('deleteLastMsgBtn').addEventListener('click', () => {
                this.deleteLastMessage();
                actionsMenu.remove();
                actionsMenu = null;
            });
            
            // Close menu on click outside
            setTimeout(() => {
                const closeMenu = (e) => {
                    if (!actionsMenu.contains(e.target) && e.target !== this.actionsBtn) {
                        actionsMenu.remove();
                        document.removeEventListener('click', closeMenu);
                    }
                };
                document.addEventListener('click', closeMenu);
            }, 100);
        });
        
        // Load conversation
        this.loadConversation();
        this.setupKeyboardShortcuts();
    }
    
    showClearConfirmDialog() {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'confirm-dialog-overlay';
        
        // Create dialog
        const dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.innerHTML = `
            <h4>Clear Chat History?</h4>
            <p>This action cannot be undone. All messages will be permanently deleted.</p>
            <div class="confirm-dialog-actions">
                <button class="cancel">Cancel</button>
                <button class="confirm">Clear History</button>
            </div>
        `;
        
        document.body.appendChild(overlay);
        document.body.appendChild(dialog);
        
        // Handle cancel
        dialog.querySelector('.cancel').addEventListener('click', () => {
            overlay.remove();
            dialog.remove();
        });
        
        // Handle confirm
        dialog.querySelector('.confirm').addEventListener('click', () => {
            this.clearConversation();
            overlay.remove();
            dialog.remove();
        });
        
        // Close on overlay click
        overlay.addEventListener('click', () => {
            overlay.remove();
            dialog.remove();
        });
    }
    
    deleteLastMessage() {
        const messages = document.querySelectorAll('#chatMessages .message');
        if (messages.length > 1) {
            // Remove last message (if it's AI response)
            const lastMessage = messages[messages.length - 1];
            lastMessage.remove();
            
            // Also remove previous user message if exists
            const prevMessage = messages[messages.length - 2];
            if (prevMessage && prevMessage.classList.contains('user')) {
                prevMessage.remove();
            }
            
            this.saveConversation();
            this.showToast('Last message deleted', 'warning');
        } else {
            this.showToast('No messages to delete', 'error');
        }
    }
    
    toggleChat() {
        this.isOpen = !this.isOpen;
        if (this.isOpen) {
            this.chatWindow.classList.remove('minimized');
            this.notification.style.display = 'none';
            this.notification.textContent = '0';
            this.chatInput.focus();
            this.scrollToBottom();
            this.addWelcomeBackAnimation();
        } else {
            if (this.isMaximized) {
                this.maximizeChat();
            }
        }
    }
    
    minimizeChat() {
        this.isOpen = false;
        this.chatWindow.classList.add('minimized');
        if (this.isMaximized) {
            this.isMaximized = false;
            this.chatWindow.classList.remove('maximized');
            const maximizeIcon = document.querySelector('#maximizeChatBtn i');
            if (maximizeIcon) {
                maximizeIcon.classList.remove('bi-fullscreen-exit');
                maximizeIcon.classList.add('bi-arrows-fullscreen');
            }
        }
    }
    
    closeChat() {
        this.minimizeChat();
    }
    
    maximizeChat() {
        this.isMaximized = !this.isMaximized;
        const maximizeIcon = document.querySelector('#maximizeChatBtn i');
        
        if (this.isMaximized) {
            this.chatWindow.classList.add('maximized');
            maximizeIcon.classList.remove('bi-arrows-fullscreen');
            maximizeIcon.classList.add('bi-fullscreen-exit');
            this.showToast('Chat maximized! Press Esc to restore', 'info');
        } else {
            this.chatWindow.classList.remove('maximized');
            maximizeIcon.classList.remove('bi-fullscreen-exit');
            maximizeIcon.classList.add('bi-arrows-fullscreen');
            this.showToast('Chat restored', 'info');
        }
        
        setTimeout(() => this.scrollToBottom(), 100);
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Alt + M to maximize/restore
            if (e.altKey && e.key === 'm' && this.isOpen) {
                e.preventDefault();
                this.maximizeChat();
            }
            // Alt + C to open chat
            if (e.altKey && e.key === 'c' && !this.isOpen) {
                e.preventDefault();
                this.toggleChat();
            }
            // Alt + K to clear history
            if (e.altKey && e.key === 'k' && this.isOpen) {
                e.preventDefault();
                this.showClearConfirmDialog();
            }
            // Escape to exit fullscreen
            if (e.key === 'Escape' && this.isMaximized) {
                this.maximizeChat();
            }
        });
    }
    
    addWelcomeBackAnimation() {
        const welcomeMsg = document.querySelector('.message.assistant:first-child');
        if (welcomeMsg) {
            welcomeMsg.style.animation = 'none';
            setTimeout(() => {
                welcomeMsg.style.animation = 'messageSlideIn 0.5s ease';
            }, 10);
        }
    }
    
     async sendMessage() {
         const message = this.chatInput.value.trim();
         if (!message || this.isThinking) return;
         
         // Validate: Detect image/file references that text-only models cannot process
         const forbiddenPatterns = [
             // Image file extensions (case-insensitive, word boundary after)
             /\.(png|jpg|jpeg|gif|bmp|webp|svg|ico|tiff?|heic|avif)(\b|$)/i,
             // Other common file types
             /\.(pdf|docx?|xlsx?|pptx?|zip|rar|7z|tar\.gz|gz|bz2|exe|dmg|iso|apk|epub|mobi|mp3|mp4|avi|mov|wmv|flv|mkv|webm)(\b|$)/i,
             // Phrases indicating attachment
             /(attach|upload|send|include|share|sending|attached).*(file|image|photo|picture|screenshot|scan|document)/i,
             /(file|image|photo|picture|screenshot|scan|document).*(attach|upload|send|include|share)/i,
             /here.*(is|comes|attached|uploaded|included).*(image|file|photo|picture)/i,
             /see.*(attachment|upload|image|file|photo)/i,
             /data:image/i, // base64 image data URIs
             /base64.*(image|file)/i,
             /\[image\]/i, // markdown-style image
             /!\[.*?\]\(.*?\)/i // markdown image syntax
         ];
         
          for (const pattern of forbiddenPatterns) {
              if (pattern.test(message)) {
                  this.showError('⚠️ This AI model only processes text. Image and file attachments are not supported. Please describe what you need without referencing files or images.');
                  return;
              }
          }
          
          // Add user message
         this.addMessage(message, 'user');
         this.chatInput.value = '';
         this.chatInput.style.height = 'auto';
         
         // Show thinking indicator
         this.showThinking();
         this.isThinking = true;
         this.sendBtn.disabled = true;
         
         try {
             const response = await fetch('{{ url("/api/chat/send") }}', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': '{{ csrf_token() }}'
                 },
                 body: JSON.stringify({
                     message: message,
                     conversation_id: this.currentConversationId
                 })
             });
             
             const data = await response.json();
             
             // Remove thinking indicator
             this.hideThinking();
             
             if (data.success) {
                 this.currentConversationId = data.conversation_id;
                 this.addMessage(data.response, 'assistant');
                 this.saveConversation();
             } else {
                 // Show API error message in a user-friendly way
                 const errorMsg = data.message || 'Sorry, I encountered an error. Please try again.';
                 this.addMessage('❌ **Error:** ' + errorMsg, 'assistant', true);
             }
         } catch (error) {
             this.hideThinking();
             this.addMessage('❌ **Network error:** Please check your connection and try again.', 'assistant', true);
         } finally {
             this.isThinking = false;
             this.sendBtn.disabled = false;
             this.scrollToBottom();
         }
     }
    
    addMessage(text, sender, isError = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}${isError ? ' error' : ''}`;
        
        const formattedText = this.formatMessage(text);
        
        messageDiv.innerHTML = `
            <div class="message-bubble">
                ${formattedText}
                <span class="message-time">${this.getCurrentTime()}</span>
            </div>
        `;
        
        this.chatMessages.appendChild(messageDiv);
        this.messageCount++;
        this.scrollToBottom();
        
        // Show notification if chat is minimized (only for non-error messages)
        if (!this.isOpen && !isError) {
            this.notification.style.display = 'flex';
            let count = parseInt(this.notification.textContent || '0');
            this.notification.textContent = count + 1;
        }
    }
    
    showError(message) {
        // Show error as a temporary toast notification
        const toast = document.createElement('div');
        toast.className = 'toast-notification error';
        toast.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${message}`;
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.animation = 'slideUp 0.3s reverse';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
    
    formatMessage(text) {
        // Format code blocks
        text = text.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>');
        
        // Format inline code
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Format bold
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Format line breaks
        text = text.replace(/\n/g, '<br>');
        
        // Format bullet points
        text = text.replace(/•/g, '&nbsp;•');
        
        return text;
    }
    
    showThinking() {
        const thinkingDiv = document.createElement('div');
        thinkingDiv.className = 'message assistant';
        thinkingDiv.id = 'thinkingIndicator';
        thinkingDiv.innerHTML = `
            <div class="thinking-indicator">
                <div class="thinking-dot"></div>
                <div class="thinking-dot"></div>
                <div class="thinking-dot"></div>
                <span style="margin-left: 8px; color: #666;">AI is thinking...</span>
            </div>
        `;
        this.chatMessages.appendChild(thinkingDiv);
        this.scrollToBottom();
    }
    
    hideThinking() {
        const indicator = document.getElementById('thinkingIndicator');
        if (indicator) indicator.remove();
    }
    
    scrollToBottom() {
        this.chatMessages.scrollTo({
            top: this.chatMessages.scrollHeight,
            behavior: 'smooth'
        });
    }
    
    getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }
    
    saveConversation() {
        const messages = [];
        document.querySelectorAll('#chatMessages .message').forEach(msg => {
            const sender = msg.classList.contains('user') ? 'user' : 'assistant';
            const text = msg.querySelector('.message-bubble').innerHTML;
            messages.push({ sender, text, time: this.getCurrentTime() });
        });
        localStorage.setItem('ai_chat_messages', JSON.stringify(messages));
        localStorage.setItem('ai_chat_conversation_id', this.currentConversationId || '');
    }
    
    loadConversation() {
        const saved = localStorage.getItem('ai_chat_messages');
        if (saved) {
            try {
                const messages = JSON.parse(saved);
                this.chatMessages.innerHTML = '';
                messages.forEach(msg => {
                    this.addMessage(msg.text, msg.sender);
                });
            } catch (e) {
                console.error('Failed to load conversation', e);
            }
        }
        this.currentConversationId = localStorage.getItem('ai_chat_conversation_id');
    }
    
    clearConversation() {
        // Clear from server
        fetch('{{ url("/api/chat/clear") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                conversation_id: this.currentConversationId
            })
        }).catch(e => console.error('Failed to clear server conversation', e));
        
        // Clear local storage
        localStorage.removeItem('ai_chat_messages');
        localStorage.removeItem('ai_chat_conversation_id');
        
        // Reload to show fresh chat
        location.reload();
    }
    
    async exportConversation() {
        try {
            const response = await fetch('{{ url("/api/chat/export") }}', {
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await response.json();
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            // Sanitize filename: replace colons with hyphens for Windows compatibility
            const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
            a.download = `chat_export_${timestamp}.json`;
            a.click();
            URL.revokeObjectURL(url);
            
            this.showToast('Conversation exported!', 'success');
        } catch (error) {
            this.showToast('Export failed', 'error');
        }
    }
    
    copyLastResponse() {
        const lastAssistantMsg = [...document.querySelectorAll('#chatMessages .message.assistant')].pop();
        if (lastAssistantMsg) {
            const text = lastAssistantMsg.querySelector('.message-bubble').innerText;
            navigator.clipboard.writeText(text);
            this.showToast('Copied to clipboard!', 'success');
        }
    }
    
    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        const icon = type === 'success' ? 'bi-check-circle' : (type === 'info' ? 'bi-info-circle' : (type === 'warning' ? 'bi-exclamation-triangle' : 'bi-x-circle'));
        toast.innerHTML = `<i class="bi ${icon}"></i> ${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideUp 0.3s reverse';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Initialize chat widget when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.aiChat = new AIChatWidget();
});
</script>