document.addEventListener('DOMContentLoaded', function() {
    console.log('Page découverte mobile chargée');

    initializeEventHandlers();
    initializePullToRefresh();
});

/**
 * Initialiser tous les gestionnaires d'événements
 */
function initializeEventHandlers() {
    window.sendFriendRequest = sendFriendRequest;
    window.handleFriendRequest = handleFriendRequest;
}

/**
 * Envoyer une demande d'amitié
 */
function sendFriendRequest(userId) {
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;

    button.disabled = true;
    button.innerHTML = `
        <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline mr-1"></div>
        Envoi...
    `;

    fetch(`/api/discover/friend/add/${userId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            // Debugger temporaire
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Erreur serveur:', text);
                    throw new Error(`Erreur ${response.status}: ${response.statusText}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'pending') {
                // Succès - modifier l'apparence du bouton
                button.className = 'friend-btn bg-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium cursor-not-allowed';
                button.innerHTML = `
                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Envoyé
            `;
                button.disabled = true;

                showToast('Demande d\'amitié envoyée!', 'success');

                // Vibration de succès si supportée
                if (navigator.vibrate) {
                    navigator.vibrate(100);
                }
            } else {
                throw new Error(data.error || data.message || 'Erreur inconnue');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);

            // Restaurer l'état original du bouton
            button.disabled = false;
            button.innerHTML = originalContent;

            showToast(error.message || 'Erreur lors de l\'envoi de la demande', 'error');

            // Vibration d'erreur si supportée
            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100]);
            }
        });
}

/**
 * Gérer les demandes d'amitié (accepter/refuser)
 */
function handleFriendRequest(requestId, action) {
    const requestElement = document.querySelector(`[data-request-id="${requestId}"]`);
    const endpoint = action === 'accept'
        ? `/api/discover/friend/accept/${requestId}`
        : `/api/discover/friend/decline/${requestId}`;

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'accepted' || data.status === 'declined') {
                // Animation de sortie
                requestElement.style.transition = 'all 0.3s ease-out';
                requestElement.style.transform = 'translateX(100%)';
                requestElement.style.opacity = '0';

                setTimeout(() => {
                    requestElement.remove();

                    // Mettre à jour le compteur dans l'en-tête
                    updateRequestCounter();

                    // Masquer la section s'il n'y a plus de demandes
                    const remainingRequests = document.querySelectorAll('[data-request-id]').length;
                    if (remainingRequests === 0) {
                        const requestsSection = document.getElementById('friend-requests-section');
                        if (requestsSection) {
                            requestsSection.style.transition = 'all 0.3s ease-out';
                            requestsSection.style.opacity = '0';
                            setTimeout(() => requestsSection.remove(), 300);
                        }
                    }
                }, 300);

                const message = action === 'accept' ? 'Demande acceptée!' : 'Demande refusée';
                const type = action === 'accept' ? 'success' : 'info';
                showToast(message, type);

                // Vibration selon l'action
                if (navigator.vibrate) {
                    navigator.vibrate(action === 'accept' ? [100, 50, 100] : 75);
                }
            } else {
                throw new Error(data.error || data.message || 'Erreur inconnue');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast(error.message || 'Erreur lors du traitement de la demande', 'error');

            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100, 50, 100]);
            }
        });
}

/**
 * Mettre à jour le compteur de demandes dans l'en-tête
 */
function updateRequestCounter() {
    const remainingRequests = document.querySelectorAll('[data-request-id]').length;
    const counter = document.getElementById('requests-counter');
    if (counter) {
        counter.textContent = remainingRequests;
    }
}

/**
 * Afficher une notification toast
 */
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');

    const bgColor = {
        'success': 'bg-green-500',
        'error': 'bg-red-500',
        'info': 'bg-blue-500'
    }[type] || 'bg-gray-800';

    toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium transform transition-all duration-300 mb-2`;
    toast.textContent = message;
    toast.style.transform = 'translateY(-100px)';

    container.appendChild(toast);

    // Animation d'entrée
    setTimeout(() => {
        toast.style.transform = 'translateY(0)';
    }, 10);

    // Animation de sortie et suppression après 3 secondes
    setTimeout(() => {
        toast.style.transform = 'translateY(-100px)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

/**
 * Initialiser la fonctionnalité pull-to-refresh
 */
function initializePullToRefresh() {
    let startY = 0;
    let pullDistance = 0;
    const refreshThreshold = 80;

    document.addEventListener('touchstart', function(e) {
        startY = e.touches[0].pageY;
    });

    document.addEventListener('touchmove', function(e) {
        if (window.scrollY === 0) {
            pullDistance = e.touches[0].pageY - startY;
            if (pullDistance > 0 && pullDistance < refreshThreshold) {
            }
        }
    });

    document.addEventListener('touchend', function(e) {
        if (pullDistance >= refreshThreshold && window.scrollY === 0) {
            // Actualiser la page
            location.reload();
        }
        pullDistance = 0;
    });
}