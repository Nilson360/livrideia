import { ChatConversation } from './chat/conversation';

document.addEventListener('DOMContentLoaded', function() {
    const chatContainer = document.getElementById('chat-conversation-container');
    if (!chatContainer) return;

    const currentUserId = parseInt(chatContainer.dataset.currentUserId);
    const friendId = parseInt(chatContainer.dataset.friendId);

    if (currentUserId && friendId) {
        const chatConversation = new ChatConversation(currentUserId, friendId);

        // Nettoyer lors du déchargement de la page
        window.addEventListener('beforeunload', () => {
            chatConversation.destroy();
        });
    }
});