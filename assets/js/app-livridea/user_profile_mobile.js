// public/js/user_profile_mobile.js

document.addEventListener('DOMContentLoaded', function () {
    // Tabs (posts, amis, photos)
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Reset all tabs
            tabButtons.forEach(btn => {
                btn.classList.remove('text-[#FF8A00]', 'border-b-2', 'border-[#FF8A00]', 'font-semibold');
                btn.classList.add('text-gray-500', 'font-medium');
                const indicator = btn.querySelector('.absolute');
                if (indicator) indicator.remove();
            });

            tabContents.forEach(content => content.classList.add('hidden'));

            // Activate clicked tab
            this.classList.remove('text-gray-500', 'font-medium');
            this.classList.add('text-[#FF8A00]', 'border-b-2', 'border-[#FF8A00]', 'font-semibold');

            // Add visual indicator
            const indicator = document.createElement('div');
            indicator.className = 'absolute bottom-0 left-0 right-0 h-0.5 bg-[#FF8A00] rounded-full';
            this.appendChild(indicator);

            const tabName = this.getAttribute('data-tab');
            document.getElementById(tabName + '-tab').classList.remove('hidden');

            // AJAX carregar lista de amigos (apenas 1x ou sob demanda)
            if (tabName === 'friends' && !document.querySelector('#friends-list-container .friend-item')) {
                const friendsTab = document.getElementById('friends-tab');
                const friendsUrl = friendsTab ? friendsTab.dataset.urlFriends : null;
                if (friendsUrl) {
                    fetch(friendsUrl, {
                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                    })
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById('friends-list-container').innerHTML = html;

                            // **IMPORTANTE**: Inicia as funções comuns na nova lista!
                            FriendsCommon.animateFriendList();
                            FriendsCommon.setupDropdownMenus();
                            FriendsCommon.setupFriendFilters();
                            FriendsCommon.setupRemoveFriendConfirm(() => {
                                // Callback: lista vazia
                                document.getElementById('friends-list-container').innerHTML = `
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
                            });
                        })
                        .catch(error => {
                            console.error('Erreur lors du chargement des amis:', error);
                            document.getElementById('friends-list-container').innerHTML = '<div class="text-center py-8"><p class="text-gray-500">Erreur lors du chargement des amis.</p></div>';
                        });
                }
            }
        });
    });

    // Animação de entrada dos elementos principais (opcional)
    const elements = document.querySelectorAll('article, .bg-white');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        setTimeout(() => {
            element.style.transition = 'all 0.4s ease-out';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
