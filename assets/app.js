import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
// Écouteur des notifications
document.addEventListener('DOMContentLoaded', () => {
    const userId = document.body.dataset.userId;
    if (!userId) return;

    const eventSource = new EventSource(`http://localhost:8080/.well-known/mercure?topic=notifications_user_${userId}`);

    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        console.log("🔔 Nouvelle notification reçue :", data);

        // Mise à jour du badge
        const notifBadge = document.getElementById('notif-badge');
        if (notifBadge) {
            let count = parseInt(notifBadge.textContent) || 0;
            notifBadge.textContent = count + 1;
            notifBadge.classList.remove('hidden');
        }

        // Ajouter à la liste des notifications
        const notificationList = document.getElementById('notifications-list');
        if (notificationList) {
            const li = document.createElement("li");
            li.innerHTML = `<b>${data.message}</b>`;
            notificationList.prepend(li);
        }
    };
});

//Counteur de notifications
document.addEventListener('DOMContentLoaded', function () {
function updateNotificationCount() {
fetch('/notifications/unread-count', {
    method: 'GET',
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
})
    .then(response => response.json())
    .then(data => {
        const badge = document.getElementById('notif-badge');
        if (data.count > 0) {
            badge.textContent = data.count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    })
    .catch(error => console.error('Erreur de récupération des notifications:', error));
}

// Mettre à jour toutes les 10 secondes
updateNotificationCount();
setInterval(updateNotificationCount, 10000);
});
