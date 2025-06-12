document.addEventListener('DOMContentLoaded', function () {
    // Animation d'apparition des amis
    const friendItems = document.querySelectorAll('.friend-item');
    friendItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        setTimeout(() => {
            item.style.transition = 'all 0.3s ease-out';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Gestion des menus déroulants
    window.toggleFriendMenu = function (friendId) {
        const menu = document.getElementById(`friend-menu-${friendId}`);
        const allMenus = document.querySelectorAll('[id^="friend-menu-"]');
        allMenus.forEach(otherMenu => {
            if (otherMenu !== menu) {
                otherMenu.classList.add('hidden');
            }
        });
        menu.classList.toggle('hidden');
    };

    // Fermer les menus en cliquant ailleurs
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[id^="friend-menu-btn-"]') && !e.target.closest('[id^="friend-menu-"]')) {
            document.querySelectorAll('[id^="friend-menu-"]').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });

    // Fonction pour confirmer la suppression d'un ami
    window.confirmRemoveFriend = function (friendId) {
        // Fermer le menu
        document.getElementById(`friend-menu-${friendId}`).classList.add('hidden');
        if (confirm('Êtes-vous sûr de vouloir retirer cette personne de vos amis ?')) {
            removeFriend(friendId);
        }
    };

    function removeFriend(friendId) {
        const friendElement = document.querySelector(`[data-friend-id="${friendId}"]`);
        if (friendElement) {
            friendElement.style.transform = 'scale(0.9)';
            friendElement.style.opacity = '0.5';
        }

        fetch(`/friend/remove/${friendId}`, {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Content-Type": "application/json"
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'removed') {
                    if (friendElement) {
                        friendElement.style.transition = 'all 0.3s ease-out';
                        friendElement.style.transform = 'translateX(-100%)';
                        friendElement.style.opacity = '0';
                        setTimeout(() => {
                            friendElement.remove();

                            // Vérifier s'il reste des amis
                            if (document.querySelectorAll('.friend-item').length === 0) {
                                // Adapte o seletor ao seu HTML: troque 'friends-list-container' pelo id correto do container da lista
                                const listContainer = document.getElementById('friends-list-container') || document.querySelector('.space-y-3');
                                if (listContainer) {
                                    listContainer.innerHTML = `
                                    <div class="flex flex-col items-center justify-center py-16 text-center">
                                        <div class="relative mb-6">
                                            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                <svg class="w-10 h-10 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 9s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-800 mb-2">Aucun ami pour le moment</h3>
                                        <p class="text-gray-500 text-sm">Votre liste d'amis est maintenant vide.</p>
                                    </div>
                                `;
                                }
                            } else {
                                // Atualizar contador de amigos se existir
                                const currentCount = document.querySelectorAll('.friend-item').length;
                                const countElements = document.querySelectorAll('#friends-count');
                                countElements.forEach(el => {
                                    if (el) el.textContent = currentCount;
                                });
                            }
                        }, 300);
                    }
                    // Feedback tactile
                    if (navigator.vibrate) {
                        navigator.vibrate([50, 100, 50]);
                    }
                } else {
                    if (friendElement) {
                        friendElement.style.transform = 'scale(1)';
                        friendElement.style.opacity = '1';
                    }
                    alert(data.error || 'Une erreur est survenue');
                }
            })
            .catch(error => {
                console.error("Erreur lors de la suppression de l'amitié:", error);
                if (friendElement) {
                    friendElement.style.transform = 'scale(1)';
                    friendElement.style.opacity = '1';
                }
                alert("Une erreur est survenue lors de la suppression de l'ami");
            });
    }
});
