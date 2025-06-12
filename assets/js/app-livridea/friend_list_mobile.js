document.addEventListener('DOMContentLoaded', function () {
    FriendsCommon.animateFriendList();
    FriendsCommon.setupDropdownMenus();
    FriendsCommon.setupFriendFilters();
    FriendsCommon.setupRemoveFriendConfirm(() => {
        // Lista vazia
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
    });
});
