document.addEventListener('DOMContentLoaded', function () {
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

    // Gestion des filtres
    window.filterFriends = function (type, event = null) {
        const friends = document.querySelectorAll('.friend-item');
        const filterBtns = document.querySelectorAll('.filter-btn');

        // Mettre à jour l'état actif du bouton
        filterBtns.forEach(btn => {
            btn.classList.remove('active');
            btn.classList.add('bg-gray-100', 'text-gray-600');
        });

        // Ativação do botão clicado ou do primeiro, com verificação de existência
        if (event && event.target) {
            event.target.classList.add('active');
            event.target.classList.remove('bg-gray-100', 'text-gray-600');
        } else if (filterBtns.length > 0) {
            filterBtns[0].classList.add('active');
            filterBtns[0].classList.remove('bg-gray-100', 'text-gray-600');
        }


        // Filtrer les amis
        friends.forEach((friend, index) => {
            let shouldShow = true;
            switch (type) {
                case 'all':
                    shouldShow = true;
                    break;
                case 'online':
                    shouldShow = friend.dataset.status === 'online';
                    break;
                case 'recent':
                    shouldShow = index < 5; // Les 5 premiers
                    break;
            }
            if (shouldShow) {
                friend.style.display = 'block';
                friend.style.animation = 'fadeIn 0.3s ease-in';
            } else {
                friend.style.display = 'none';
            }
        });
    };

    window.resetFilters = function () {
        // Active "Tous"
        window.filterFriends('all');
    };

    // Attacher listeners aux filtres
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            let type = 'all';
            if (btn.textContent.includes('En ligne')) type = 'online';
            if (btn.textContent.includes('Récents')) type = 'recent';
            window.filterFriends(type, e);
        });
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
                            if (document.querySelectorAll('.friend-item').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
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

    window.filterFriends('all');
});
