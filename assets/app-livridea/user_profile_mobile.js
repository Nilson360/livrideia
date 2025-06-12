document.addEventListener('DOMContentLoaded', function () {
    // Gestion des onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Réinitialiser tous les onglets
            tabButtons.forEach(btn => {
                btn.classList.remove('text-[#FF8A00]', 'border-b-2', 'border-[#FF8A00]', 'font-semibold');
                btn.classList.add('text-gray-500', 'font-medium');
                const indicator = btn.querySelector('.absolute');
                if (indicator) indicator.remove();
            });

            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Activer l'onglet cliqué
            this.classList.remove('text-gray-500', 'font-medium');
            this.classList.add('text-[#FF8A00]', 'border-b-2', 'border-[#FF8A00]', 'font-semibold');

            // Ajouter l'indicateur visuel
            const indicator = document.createElement('div');
            indicator.className = 'absolute bottom-0 left-0 right-0 h-0.5 bg-[#FF8A00] rounded-full';
            this.appendChild(indicator);

            const tabName = this.getAttribute('data-tab');
            document.getElementById(tabName + '-tab').classList.remove('hidden');

            // Charger le contenu des amis si nécessaire
            if (tabName === 'friends' && !document.querySelector('#friends-list-container .friend-item')) {
                fetch('{{ path("app_profile_friends", {"id": user.id}) }}', {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('friends-list-container').innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des amis:', error);
                        document.getElementById('friends-list-container').innerHTML =
                            '<div class="text-center py-8"><p class="text-gray-500">Erreur lors du chargement des amis.</p></div>';
                    });
            }
        });
    });

    // Animation d'apparition
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

// Fonctions pour les actions d'amitié
function sendFriendRequest(userId) {
    fetch(`/app/friend/add/${userId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (response.ok) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
}

function confirmRemoveFriend(userId) {
    if (confirm('Êtes-vous sûr de vouloir retirer cette personne de vos amis ?')) {
        fetch(`/app/friend/remove/${userId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (response.ok) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
    }
}

// Feedback tactile pour mobile
if (navigator.vibrate) {
    document.querySelectorAll('button').forEach(button => {
        button.addEventListener('click', () => {
            navigator.vibrate(50);
        });
    });
}
