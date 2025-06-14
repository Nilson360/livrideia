export class ChatConversation {
    constructor(currentUserId, friendId) {
        this.currentUserId = currentUserId;
        this.friendId = friendId;
        this.chatMessages = document.getElementById('chat-messages');
        this.messageForm = document.getElementById('message-form');
        this.messageInput = document.getElementById('message-input');
        this.sendButton = document.getElementById('send-button');
        this.eventSource = null;

        this.init();
    }

    init() {
        this.loadMessages();
        this.bindEvents();
        this.connectMercure();
        this.messageInput.focus();
    }

    loadMessages() {
        fetch(`/chat/messages/${this.friendId}`)
            .then(response => response.json())
            .then(data => {
                this.chatMessages.innerHTML = '';

                if (!data.messages || data.messages.length === 0) {
                    this.showEmptyState();
                    return;
                }

                data.messages.forEach(msg => {
                    this.createMessageElement(msg);
                });

                this.scrollToBottom();
            })
            .catch(error => {
                console.error('Erreur chargement messages:', error);
                this.showErrorState();
            });
    }

    createMessageElement(msg) {
        const isCurrentUser = String(msg.sender) === String(this.currentUserId);

        const wrapper = document.createElement('div');
        wrapper.className = `flex ${isCurrentUser ? 'justify-end' : 'justify-start'} mb-3`;

        const bubble = document.createElement('div');
        bubble.className = `max-w-[75%] p-3 rounded-lg text-sm shadow-sm ${
            isCurrentUser
                ? 'bg-[#FF8A00] text-white rounded-br-none ml-auto'
                : 'bg-white border border-gray-200 text-gray-800 rounded-bl-none mr-auto'
        }`;

        bubble.innerHTML = `
            <p class="leading-relaxed">${this.escapeHtml(msg.content)}</p>
            <div class="text-xs text-right mt-1 ${isCurrentUser ? 'text-white/70' : 'text-gray-500'}">
                ${msg.createdAt}
            </div>
        `;

        wrapper.appendChild(bubble);
        this.chatMessages.appendChild(wrapper);
    }

    bindEvents() {
        this.messageForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
    }

    sendMessage() {
        const content = this.messageInput.value.trim();
        if (!content) return;

        this.sendButton.disabled = true;

        fetch('/chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                content: content,
                receiver_id: this.friendId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    this.createMessageElement(data.message);
                    this.messageInput.value = '';
                    this.scrollToBottom();
                }
            })
            .catch(error => {
                console.error('Erreur envoi message:', error);
            })
            .finally(() => {
                this.sendButton.disabled = false;
            });
    }

    connectMercure() {
        if (typeof EventSource === 'undefined') return;

        this.eventSource = new EventSource(`http://localhost:8080/.well-known/mercure?topic=chat_user_${this.currentUserId}`);

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);

                if (parseInt(data.senderId) === parseInt(this.friendId)) {
                    const receivedMessage = {
                        id: Date.now(),
                        content: data.content,
                        sender: parseInt(data.senderId),
                        receiver: parseInt(this.currentUserId),
                        createdAt: data.timestamp
                    };

                    this.createMessageElement(receivedMessage);
                    this.scrollToBottom();

                    if (typeof window.updateChatBadge === 'function' && data.unreadCount) {
                        window.updateChatBadge(data.unreadCount);
                    }
                }
            } catch (error) {
                console.error('Erreur parsing Mercure:', error);
            }
        };

        this.eventSource.onerror = (error) => {
            console.error('Erreur Mercure:', error);
        };
    }

    showEmptyState() {
        this.chatMessages.innerHTML = `
            <div class="flex flex-col items-center justify-center py-10 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                    <svg class="w-8 h-8 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                    </svg>
                </div>
                <p class="text-gray-500">Aucun message. Commencez la conversation !</p>
            </div>
        `;
    }

    showErrorState() {
        this.chatMessages.innerHTML = `
            <div class="text-center py-8">
                <p class="text-red-500">Erreur lors du chargement des messages.</p>
            </div>
        `;
    }

    scrollToBottom() {
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    destroy() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }
}