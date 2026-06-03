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
        sessionId: null,

        init: function() {
            this.container = $('#ai-agent-chat-container');
            this.button = $('#ai-agent-chat-button');
            this.window = $('#ai-agent-chat-window');
            this.messagesContainer = $('#ai-agent-chat-messages');
            this.input = $('#ai-agent-chat-input');
            this.sendButton = $('#ai-agent-send-message');

            if (this.container.length === 0) return;

            try {
                this.sessionId = window.sessionStorage.getItem('ai_agent_session_id');
            } catch (e) {
                this.sessionId = null;
            }

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

            if (aiAgentWidget.streaming && typeof window.EventSource !== 'undefined') {
                this.sendMessageStream(message);
                return;
            }

            const data = {
                action: 'ai_agent_chat',
                nonce: aiAgentWidget.nonce,
                message: message,
                session_id: this.sessionId || ''
            };

            $.ajax({
                url: aiAgentWidget.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    this.setTyping(false);

                    if (response.success && response.data.response) {
                        if (response.data.session_id) {
                            this.sessionId = response.data.session_id;
                            try {
                                window.sessionStorage.setItem('ai_agent_session_id', this.sessionId);
                            } catch (e) {}
                        }
                        this.addMessage(response.data.response, 'bot');
                    } else {
                        const errMsg = (response.data && response.data.error)
                            ? response.data.error
                            : 'Lo siento, ocurrió un error. Por favor intenta de nuevo.';
                        this.addMessage(errMsg, 'bot');
                    }
                }.bind(this),
                error: function(xhr) {
                    this.setTyping(false);
                    if (xhr && xhr.status === 429) {
                        this.addMessage('Demasiadas solicitudes. Espera un momento.', 'bot');
                    } else {
                        this.addMessage('Lo siento, no pude procesar tu mensaje. Verifica tu conexión.', 'bot');
                    }
                }.bind(this)
            });
        },

        sendMessageStream: function(message) {
            const params = new URLSearchParams({
                action: 'ai_agent_stream',
                nonce: aiAgentWidget.nonce,
                message: message,
                session_id: this.sessionId || ''
            });

            const url = aiAgentWidget.ajaxUrl + '?' + params.toString();
            const es = new EventSource(url);

            let currentBubble = null;
            let accumulated = '';

            es.addEventListener('chunk', function(e) {
                this.setTyping(false);
                const data = JSON.parse(e.data);
                accumulated += data.text;

                if (!currentBubble) {
                    currentBubble = this.appendEmptyBotMessage();
                }
                currentBubble.textContent = accumulated;
                this.scrollToBottom();
            }.bind(this));

            es.addEventListener('done', function(e) {
                const data = JSON.parse(e.data);
                if (data.session_id && !this.sessionId) {
                    this.sessionId = data.session_id;
                    try { window.sessionStorage.setItem('ai_agent_session_id', this.sessionId); } catch (err) {}
                }
                es.close();
            }.bind(this));

            es.addEventListener('error', function(e) {
                this.setTyping(false);
                es.close();
                if (!accumulated) {
                    this.addMessage('Lo siento, no pude procesar tu mensaje. Verifica tu conexión.', 'bot');
                }
            }.bind(this));
        },

        appendEmptyBotMessage: function() {
            const wrap = document.createElement('div');
            wrap.className = 'ai-agent-message ai-agent-message-bot';
            const content = document.createElement('div');
            content.className = 'ai-agent-message-content';
            wrap.appendChild(content);
            this.messagesContainer[0].appendChild(wrap);
            this.scrollToBottom();
            return content;
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