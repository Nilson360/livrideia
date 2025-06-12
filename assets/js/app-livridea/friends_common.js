// public/js/friends-common.js

// Animação de entrada de lista de amigos
function animateFriendList(selector = '.friend-item') {
    const friendItems = document.querySelectorAll(selector);
    friendItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        setTimeout(() => {
            item.style.transition = 'all 0.3s ease-out';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Dropdown dos menus de amigos
function setupDropdownMenus() {
    window.toggleFriendMenu = function (friendId) {
        const menu = document.getElementById(`friend-menu-${friendId}`);
        const allMenus = document.querySelectorAll('[id^="friend-menu-"]');
        allMenus.forEach(otherMenu => {
            if (otherMenu !== menu) otherMenu.classList.add('hidden');
        });
        if (menu) menu.classList.toggle('hidden');
    };

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[id^="friend-menu-btn-"]') && !e.target.closest('[id^="friend-menu-"]')) {
            document.querySelectorAll('[id^="friend-menu-"]').forEach(menu => menu.classList.add('hidden'));
        }
    });
}

// Função para remover amigo (com feedback visual/tátil)
function removeFriend(friendId, afterRemove) {
    const friendElement = document.querySelector(`[data-friend-id="${friendId}"]`);
    if (friendElement) {
        friendElement.style.transform = 'scale(0.9)';
        friendElement.style.opacity = '0.5';
    }
    fetch(`/friend/remove/${friendId}`, {
        method: "POST", headers: {
            "X-Requested-With": "XMLHttpRequest", "Content-Type": "application/json"
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
                        if (afterRemove) afterRemove();
                    }, 300);
                }
                if (navigator.vibrate) navigator.vibrate([50, 100, 50]);
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

// Confirmação visual antes de remover amigo
function setupRemoveFriendConfirm(emptyListCallback) {
    window.confirmRemoveFriend = function (friendId) {
        const menu = document.getElementById(`friend-menu-${friendId}`);
        if (menu) menu.classList.add('hidden');
        if (confirm('Êtes-vous sûr de vouloir retirer cette personne de vos amis ?')) {
            removeFriend(friendId, function () {
                // Se não houver mais amigos na lista, executar callback para exibir mensagem de lista vazia
                if (document.querySelectorAll('.friend-item').length === 0) {
                    if (emptyListCallback) emptyListCallback();
                } else {
                    // Atualiza contadores, se existirem
                    const currentCount = document.querySelectorAll('.friend-item').length;
                    document.querySelectorAll('#friends-count').forEach(el => {
                        if (el) el.textContent = currentCount;
                    });
                }
            });
        }
    }
}

// Filtros (All/Online/Recent)
function setupFriendFilters() {
    window.filterFriends = function (type, event = null) {
        const friends = document.querySelectorAll('.friend-item');
        const filterBtns = document.querySelectorAll('.filter-btn');
        filterBtns.forEach(btn => {
            btn.classList.remove('active');
            btn.classList.add('bg-gray-100', 'text-gray-600');
        });
        if (event && event.target) {
            event.target.classList.add('active');
            event.target.classList.remove('bg-gray-100', 'text-gray-600');
        } else if (filterBtns.length > 0) {
            filterBtns[0].classList.add('active');
            filterBtns[0].classList.remove('bg-gray-100', 'text-gray-600');
        }
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
                    shouldShow = index < 5;
                    break;
            }
            friend.style.display = shouldShow ? 'block' : 'none';
            if (shouldShow) friend.style.animation = 'fadeIn 0.3s ease-in';
        });
    };

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            let type = 'all';
            if (btn.textContent.includes('En ligne')) type = 'online';
            if (btn.textContent.includes('Récents')) type = 'recent';
            window.filterFriends(type, e);
        });
    });
    window.filterFriends('all');
}

// Exporta para uso externo
window.FriendsCommon = {
    animateFriendList, setupDropdownMenus, setupRemoveFriendConfirm, setupFriendFilters
};
