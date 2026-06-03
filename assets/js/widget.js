(function($) {
    'use strict';

    const AIAgentWidget = {
        container: null,
        button: null,
        window: null,
        messagesContainer: null,
        input: null,
        sendButton: null,
        isOpen: false,

        init: function() {
            this.container = $('#ai-agent-chat-container');
            this.button = $('#ai-agent-chat-button');
            this.window = $('#ai-agent-chat-window');
            this.messagesContainer = $('#ai-agent-chat-messages');
            this.input = $('#ai-agent-chat-input');
            this.sendButton = $('#ai-agent-send-message');

            if (this.container.length === 0) return;

            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;

            this.button.on('click', function() {
                self.toggleChat();
            });

            $('#ai-agent-close-chat').on('click', function() {
                self.closeChat();
            });

            this.sendButton.on('click', function() {
                self.sendMessage();
            });

            this.input.on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });
        },

        toggleChat: function() {
            this.isOpen ? this.closeChat() : this.openChat();
        },

        openChat: function() {
            this.window.stop(true, true).slideDown(300);
            this.isOpen = true;
            this.input.focus();
        },

        closeChat: function() {
            this.window.stop(true, true).slideUp(300);
            this.isOpen = false;
        },

        sendMessage: function() {
            const message = this.input.val().trim();

            if (!message) return;

            this.addMessage(message, 'user');
            this.input.val('');
            this.setTyping(true);

            const data = {
                action: 'ai_agent_chat',
                nonce: aiAgentWidget.nonce,
                message: message
            };

            $.ajax({
                url: aiAgentWidget.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    this.setTyping(false);

                    if (response.success && response.data.response) {
                        this.addMessage(response.data.response, 'bot');
                    } else {
                        this.addMessage('Lo siento, ocurrió un error. Por favor intenta de nuevo.', 'bot');
                    }
                }.bind(this),
                error: function() {
                    this.setTyping(false);
                    this.addMessage('Lo siento, no pude procesar tu mensaje. Verifica tu conexión.', 'bot');
                }.bind(this)
            });
        },

        addMessage: function(content, type) {
            const messageClass = type === 'user' ? 'ai-agent-message-user' : 'ai-agent-message-bot';
            const messageHtml = `
                <div class="ai-agent-message ${messageClass}">
                    <div class="ai-agent-message-content">${this.escapeHtml(content)}</div>
                </div>
            `;

            this.messagesContainer.append(messageHtml);
            this.scrollToBottom();
        },

        setTyping: function(show) {
            if (show) {
                const typingHtml = `
                    <div class="ai-agent-message ai-agent-message-bot" id="ai-agent-typing">
                        <div class="ai-agent-typing-indicator">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                `;
                this.messagesContainer.append(typingHtml);
                this.scrollToBottom();
            } else {
                $('#ai-agent-typing').remove();
            }
        },

        scrollToBottom: function() {
            this.messagesContainer.stop(true, true).animate({
                scrollTop: this.messagesContainer[0].scrollHeight
            }, 300);
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        AIAgentWidget.init();
    });

    window.AIAgentWidget = AIAgentWidget;

})(jQuery);