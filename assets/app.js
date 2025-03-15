import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';


const eventSource = new EventSource('http://localhost:3000/.well-known/mercure?topic=notifications');
eventSource.onmessage = function(event) {
const data = JSON.parse(event.data);
alert("🔔 Nouvelle notification : " + data.message);
};

// 1. Toggle d'affichage des commentaires

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-comments').forEach(button => {
        button.addEventListener('click', function() {
            let container = this.closest('.bg-white');
            let commentSection = container.querySelector('.comments-section');
            commentSection.classList.toggle('hidden');
        });
    });
});

// 2. Gestion du like (AJAX)
document.addEventListener('DOMContentLoaded', function() {
document.querySelectorAll('.like-button').forEach(button => {
    button.addEventListener('click', function() {
        let postId = this.getAttribute('data-id');
        fetch('/post/' + postId + '/like', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.likesCount !== undefined) {
                    // Mettre à jour le compteur en haut (si présent)
                    let container = this.closest('.bg-white');
                    let likeCountEl = container.querySelector('.like-count');
                    let statsContainer = container.querySelector('.stats-container');

                    // Mettre à jour le texte du compteur
                    if (likeCountEl) {
                        if (data.likesCount > 0) {
                            likeCountEl.textContent = data.likesCount;
                            likeCountEl.classList.remove('hidden');
                        } else {
                            likeCountEl.textContent = '0';
                            likeCountEl.classList.add('hidden');
                        }
                    }

                    // Gérer la couleur du bouton
                    if (data.status === 'liked') {
                        this.classList.remove('text-gray-600');
                        this.classList.add('text-blue-500');
                    } else {
                        this.classList.remove('text-blue-500');
                        this.classList.add('text-gray-600');
                    }

                    // Vérifier si on doit afficher ou masquer stats-container
                    if (statsContainer) {
                        let commentCountBtn = statsContainer.querySelector('.comment-count-btn');
                        let shareCountEl    = statsContainer.querySelector('.share-count');
                        let commentCount    = commentCountBtn ? parseInt(commentCountBtn.getAttribute('data-count') || '0', 10) : 0;
                        let shareCount      = shareCountEl ? parseInt(shareCountEl.textContent || '0', 10) : 0;

                        // Si tous les compteurs sont 0 => masquer
                        if (data.likesCount === 0 && commentCount === 0 && shareCount === 0) {
                            statsContainer.classList.add('hidden');
                        } else {
                            statsContainer.classList.remove('hidden');
                        }
                    }
                }
            })
            .catch(error => console.error('Erreur:', error));
    });
});
});

//3. Envoi d'un nouveau commentaire (AJAX)
document.addEventListener('DOMContentLoaded', function() {
    function sendComment(button) {
        let postId = button.getAttribute('data-post-id');
        let input  = button.closest('.add-comment').querySelector('.comment-input');
        let content = input.value.trim();
        if (content === '') return;

        fetch('/post/' + postId + '/comment', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ content: content })
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'commented') {
                    let commentsSection = button.closest('.comments-section');
                    let newComment = document.createElement('div');
                    newComment.classList.add('comment', 'p-2', 'border-b', 'flex', 'justify-between', 'items-center');
                    newComment.innerHTML = `
                <div>
                    <div class="flex justify-between">
                        <span class="font-bold">${data.comment.user}</span>
                        <span class="text-gray-500 text-sm">${data.comment.createdAt}</span>
                    </div>
                    <p>${data.comment.content}</p>
                </div>
                <div class="ml-2">
                    <button class="edit-comment text-blue-500 hover:text-blue-700" data-comment-id="${data.comment.id}">Modifier</button>
                    <button class="delete-comment text-red-500 hover:text-red-700 ml-2" data-comment-id="${data.comment.id}">Supprimer</button>
                </div>
            `;
                    commentsSection.insertBefore(newComment, commentsSection.querySelector('.add-comment'));
                    input.value = '';

                    // Mettre à jour le compteur de commentaires
                    let container = button.closest('.bg-white');
                    let statsContainer   = container.querySelector('.stats-container');
                    let commentCountBtn  = container.querySelector('.comment-count-btn');
                    let likeCountEl      = container.querySelector('.like-count');
                    let shareCountEl     = container.querySelector('.share-count');

                    if (commentCountBtn) {
                        // S'il existe déjà, on le met à jour
                        commentCountBtn.setAttribute('data-count', data.commentsCount);
                        commentCountBtn.textContent = data.commentsCount + ' commentaires';
                        commentCountBtn.classList.remove('hidden');
                    } else {
                        // S'il n'existait pas, on doit éventuellement afficher la zone stats-container
                        // et/ou créer un bouton. Simplification :
                        if (statsContainer) {
                            statsContainer.classList.remove('hidden');
                        }
                    }

                    // Vérifier si on doit afficher ou masquer stats-container
                    if (statsContainer) {
                        let likeCount   = likeCountEl ? parseInt(likeCountEl.textContent || '0', 10) : 0;
                        let shareCount  = shareCountEl ? parseInt(shareCountEl.textContent || '0', 10) : 0;

                        if (data.commentsCount === 0 && likeCount === 0 && shareCount === 0) {
                            statsContainer.classList.add('hidden');
                        } else {
                            statsContainer.classList.remove('hidden');
                        }
                    }
                }
            })
            .catch(error => console.error('Erreur:', error));
    }

    document.querySelectorAll('.send-comment').forEach(button => {
        button.addEventListener('click', function() {
            sendComment(this);
        });
    });

    // Possibilité d'envoyer en appuyant sur "Entrée"
    document.querySelectorAll('.comment-input').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let button = this.parentElement.querySelector('.send-comment');
                sendComment(button);
            }
        });
    });
});


// 4. Suppression et édition d'un commentaire (et mise à jour des compteurs)

document.addEventListener('DOMContentLoaded', function() {
// Suppression
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('delete-comment')) {
        e.preventDefault();
        let commentId = e.target.getAttribute('data-comment-id');
        fetch('/comment/' + commentId + '/delete', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'deleted') {
                    e.target.closest('.comment').remove();

                    // Mettre à jour le compteur
                    let container       = e.target.closest('.bg-white');
                    let statsContainer  = container.querySelector('.stats-container');
                    let commentCountBtn = container.querySelector('.comment-count-btn');
                    let likeCountEl     = container.querySelector('.like-count');
                    let shareCountEl    = container.querySelector('.share-count');

                    if (commentCountBtn) {
                        if (data.commentsCount > 0) {
                            commentCountBtn.setAttribute('data-count', data.commentsCount);
                            commentCountBtn.textContent = data.commentsCount + ' commentaires';
                        } else {
                            // 0 commentaire => on cache le bouton
                            commentCountBtn.classList.add('hidden');
                            commentCountBtn.setAttribute('data-count', 0);
                        }
                    }

                    if (statsContainer) {
                        let likeCount  = likeCountEl ? parseInt(likeCountEl.textContent || '0', 10) : 0;
                        let shareCount = shareCountEl ? parseInt(shareCountEl.textContent || '0', 10) : 0;

                        if (data.commentsCount === 0 && likeCount === 0 && shareCount === 0) {
                            statsContainer.classList.add('hidden');
                        }
                    }
                }
            })
            .catch(error => console.error('Erreur:', error));
    }
});

// Modification
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('edit-comment')) {
        e.preventDefault();
        let commentEl = e.target.closest('.comment');
        let commentId = e.target.getAttribute('data-comment-id');

        // Exemple simplifié : on remplace le contenu par un input
        let contentEl = commentEl.querySelector('p');
        let oldContent = contentEl.textContent;

        // Masquer l'affichage normal
        contentEl.style.display = 'none';
        // Boutons
        e.target.style.display = 'none';
        let deleteBtn = commentEl.querySelector('.delete-comment');
        if (deleteBtn) {
            deleteBtn.style.display = 'none';
        }

        // Créer un input pour éditer
        let inputEdit = document.createElement('input');
        inputEdit.type = 'text';
        inputEdit.value = oldContent;
        inputEdit.classList.add('w-full', 'border', 'rounded', 'p-2', 'mt-2');
        commentEl.insertBefore(inputEdit, contentEl.nextSibling);

        // Ajouter un bouton pour valider
        let validateBtn = document.createElement('button');
        validateBtn.textContent = 'Valider';
        validateBtn.classList.add('bg-green-500', 'text-white', 'px-4', 'py-2', 'rounded', 'hover:bg-green-600', 'ml-2');
        commentEl.insertBefore(validateBtn, inputEdit.nextSibling);

        // Bouton annuler
        let cancelBtn = document.createElement('button');
        cancelBtn.textContent = 'Annuler';
        cancelBtn.classList.add('bg-gray-500', 'text-white', 'px-4', 'py-2', 'rounded', 'hover:bg-gray-600', 'ml-2');
        commentEl.insertBefore(cancelBtn, validateBtn.nextSibling);

        // Annuler
        cancelBtn.addEventListener('click', function() {
            contentEl.style.display = '';
            e.target.style.display = '';
            if (deleteBtn) {
                deleteBtn.style.display = '';
            }
            inputEdit.remove();
            validateBtn.remove();
            cancelBtn.remove();
        });

        // Valider
        validateBtn.addEventListener('click', function() {
            let newContent = inputEdit.value.trim();
            if (newContent === '') return;

            fetch('/comment/' + commentId + '/edit', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ content: newContent })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'updated') {
                        contentEl.textContent = data.comment.content;
                        contentEl.style.display = '';
                        e.target.style.display = '';
                        if (deleteBtn) {
                            deleteBtn.style.display = '';
                        }
                        inputEdit.remove();
                        validateBtn.remove();
                        cancelBtn.remove();
                    }
                })
                .catch(error => console.error('Erreur:', error));
        });
    }
});
});
