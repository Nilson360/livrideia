document.addEventListener('DOMContentLoaded', function () {

    // === 1. Toggle dos comentários ===
    document.querySelectorAll('.toggle-comments').forEach(button => {
        button.addEventListener('click', function () {
            const article = this.closest('article');
            let commentSection = article.querySelector('.comments-section');

            if (!commentSection) {
                createCommentsSection(article);
                commentSection = article.querySelector('.comments-section');
            }

            commentSection.classList.toggle('hidden');
        });
    });

    // === 2. Gestion du like ===
    document.querySelectorAll('.like-button').forEach(button => {
        button.addEventListener('click', function () {
            const postId = this.getAttribute('data-id');
            const article = this.closest('article');

            fetch('/post/' + postId + '/like', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    const statsContainer = article.querySelector('.stats-container');
                    const likeCountEl = article.querySelector('.like-count');

                    if (likeCountEl) {
                        const countSpan = likeCountEl.querySelector('span');
                        if (countSpan) {
                            countSpan.textContent = data.likesCount + ' j\'aime';
                        }
                        likeCountEl.classList.toggle('hidden', data.likesCount === 0);
                    }

                    // Mettre à jour le style du bouton
                    if (data.status === 'liked') {
                        this.classList.remove('text-gray-500', 'active:bg-red-50');
                        this.classList.add('text-red-500', 'active:bg-red-50');
                        // Changer pour filled heart
                        const svg = this.querySelector('svg');
                        svg.innerHTML = '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>';
                        svg.setAttribute('fill', 'currentColor');
                        svg.removeAttribute('stroke');
                        svg.removeAttribute('stroke-width');
                    } else {
                        this.classList.remove('text-red-500');
                        this.classList.add('text-gray-500', 'active:bg-red-50');
                        // Changer pour outline heart
                        const svg = this.querySelector('svg');
                        svg.innerHTML = '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
                        svg.setAttribute('stroke', 'currentColor');
                        svg.setAttribute('stroke-width', '2');
                        svg.removeAttribute('fill');
                    }

                    if (statsContainer) {
                        const commentCountBtn = statsContainer.querySelector('.comment-count-display');
                        const commentCount = commentCountBtn ? parseInt(commentCountBtn.textContent.match(/\d+/)?.[0] || '0', 10) : 0;

                        // Vérifier si on doit afficher ou masquer stats-container
                        if (data.likesCount === 0 && commentCount === 0) {
                            statsContainer.classList.add('hidden');
                        } else {
                            statsContainer.classList.remove('hidden');
                        }
                    }
                })
                .catch(err => console.error('Erreur like :', err));
        });
    });

    // === 3. Envoi d'un commentaire ===
    function sendComment(button) {
        const article = button.closest('article');
        const postId = button.getAttribute('data-post-id');
        const input = article.querySelector('.comment-input');
        const content = input.value.trim();

        if (!content) return;

        fetch('/post/' + postId + '/comment', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({content})
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'commented') {
                    const commentsSection = article.querySelector('.comments-section');
                    const commentsList = commentsSection.querySelector('.comments-list');

                    const newComment = document.createElement('div');
                    newComment.className = 'border-t border-gray-100 pt-4 text-sm';
                    newComment.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <img src="${data.comment.avatar}" alt="${data.comment.user}" class="w-8 h-8 rounded-full">
                                <div>
                                    <span class="text-gray-800 font-semibold">${data.comment.user}</span>
                                    <p class="text-xs text-gray-500">${data.comment.createdAt}</p>
                                </div>
                            </div>
                            <p class="text-gray-700 ml-11">${data.comment.content}</p>
                        </div>
                        <div class="ml-4 flex gap-2">
                            <button class="edit-comment text-[#FF8A00] hover:text-[#e67300] hover:underline text-xs transition-colors" data-comment-id="${data.comment.id}">Modifier</button>
                            <button class="delete-comment text-red-600 hover:text-red-800 hover:underline text-xs transition-colors" data-comment-id="${data.comment.id}">Supprimer</button>
                        </div>
                    </div>
                `;

                    commentsList.appendChild(newComment);
                    input.value = '';

                    // Mettre à jour le compteur de commentaires
                    const statsContainer = article.querySelector('.stats-container');
                    let commentCountDisplay = article.querySelector('.comment-count-display');

                    if (commentCountDisplay) {
                        commentCountDisplay.textContent = `${data.commentsCount} commentaire${data.commentsCount > 1 ? 's' : ''}`;
                    } else if (statsContainer && data.commentsCount > 0) {
                        // Créer le compteur s'il n'existe pas
                        const leftDiv = statsContainer.querySelector('.flex.items-center.space-x-3');
                        if (leftDiv) {
                            const commentCountSpan = document.createElement('span');
                            commentCountSpan.className = 'comment-count-display text-xs';
                            commentCountSpan.textContent = `${data.commentsCount} commentaire${data.commentsCount > 1 ? 's' : ''}`;
                            leftDiv.appendChild(commentCountSpan);
                        }
                    }

                    if (statsContainer) {
                        statsContainer.classList.remove('hidden');
                    }

                    // Ouvrir automatiquement les commentaires
                    commentsSection.classList.remove('hidden');
                }
            })
            .catch(err => console.error('Erreur commentaire :', err));
    }

    // === 4. Fonction pour créer la section commentaires ===
    function createCommentsSection(article) {
        const postId = article.getAttribute('data-post-id');

        const commentsHTML = `
            <div class="comments-section hidden space-y-4 mt-4 border-t border-gray-100 pt-4">
                <!-- Liste des commentaires existants -->
                <div class="comments-list space-y-3"></div>
                
                <!-- Formulaire d'ajout de commentaire -->
                <div class="add-comment flex gap-2 p-3 bg-gray-50 rounded-lg">
                    <img src="/uploads/avatars/user_default.png" alt="Votre avatar" class="w-8 h-8 rounded-full flex-shrink-0">
                    <div class="flex-1 flex gap-2">
                        <input type="text" 
                               placeholder="Exprimez-vous..." 
                               class="comment-input flex-1 border border-gray-200 rounded-lg p-3 text-sm bg-white focus:ring-2 focus:ring-[#FF8A00] focus:border-[#FF8A00] transition-all"
                               data-post-id="${postId}">
                        <button class="send-comment bg-gradient-to-r from-[#FF8A00] to-[#e67300] text-white px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200"
                                data-post-id="${postId}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;

        article.insertAdjacentHTML('beforeend', commentsHTML);

        // Attacher les événements pour les nouveaux éléments
        const sendButton = article.querySelector('.send-comment');
        const input = article.querySelector('.comment-input');

        sendButton.addEventListener('click', () => sendComment(sendButton));

        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendComment(sendButton);
            }
        });
    }

    // Attacher événements pour commentaires existants
    document.querySelectorAll('.send-comment').forEach(button => {
        button.addEventListener('click', () => sendComment(button));
    });

    document.querySelectorAll('.comment-input').forEach(input => {
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendComment(this.closest('article').querySelector('.send-comment'));
            }
        });
    });

    // === 5. Suppression et édition d'un commentaire ===
    document.addEventListener('click', function (e) {
        const el = e.target;
        const article = el.closest('article');

        // Suppression
        if (el.classList.contains('delete-comment')) {
            e.preventDefault();
            const commentId = el.dataset.commentId;

            if (confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
                fetch(`/comment/${commentId}/delete`, {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'deleted') {
                            el.closest('.border-t').remove();

                            // Mettre à jour le compteur
                            const commentCountDisplay = article.querySelector('.comment-count-display');
                            if (commentCountDisplay) {
                                if (data.commentsCount > 0) {
                                    commentCountDisplay.textContent = `${data.commentsCount} commentaire${data.commentsCount > 1 ? 's' : ''}`;
                                } else {
                                    commentCountDisplay.remove();

                                    // Vérifier si on doit masquer stats-container
                                    const statsContainer = article.querySelector('.stats-container');
                                    const likeCount = article.querySelector('.like-count span')?.textContent.match(/\d+/)?.[0] || 0;
                                    if (statsContainer && parseInt(likeCount) === 0) {
                                        statsContainer.classList.add('hidden');
                                    }
                                }
                            }
                        }
                    });
            }
        }

        // Édition
        if (el.classList.contains('edit-comment')) {
            e.preventDefault();
            const commentBlock = el.closest('.border-t');
            const contentP = commentBlock.querySelector('p.ml-11');
            const oldText = contentP.textContent;

            // Masquer l'affichage normal
            contentP.style.display = 'none';
            el.style.display = 'none';
            const deleteBtn = commentBlock.querySelector('.delete-comment');
            if (deleteBtn) deleteBtn.style.display = 'none';

            // Créer un input pour éditer
            const inputEdit = document.createElement('input');
            inputEdit.value = oldText;
            inputEdit.className = 'border border-gray-200 rounded-lg w-full p-3 mt-2 text-sm focus:ring-2 focus:ring-[#FF8A00] ml-11';
            commentBlock.appendChild(inputEdit);

            // Ajouter boutons valider/annuler
            const buttonsDiv = document.createElement('div');
            buttonsDiv.className = 'flex gap-2 mt-2 ml-11';

            const validateBtn = document.createElement('button');
            validateBtn.className = 'bg-[#FF8A00] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#e67300] transition-colors';
            validateBtn.textContent = 'Valider';

            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'bg-gray-400 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-500 transition-colors';
            cancelBtn.textContent = 'Annuler';

            buttonsDiv.appendChild(validateBtn);
            buttonsDiv.appendChild(cancelBtn);
            commentBlock.appendChild(buttonsDiv);

            // Focus sur l'input
            inputEdit.focus();
            inputEdit.select();

            // Annuler
            cancelBtn.addEventListener('click', () => {
                inputEdit.remove();
                buttonsDiv.remove();
                contentP.style.display = '';
                el.style.display = '';
                if (deleteBtn) deleteBtn.style.display = '';
            });

            // Valider avec Entrée
            inputEdit.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    validateBtn.click();
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelBtn.click();
                }
            });

            // Valider
            validateBtn.addEventListener('click', () => {
                const newContent = inputEdit.value.trim();
                if (!newContent) return;

                fetch(`/comment/${el.dataset.commentId}/edit`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({content: newContent})
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'updated') {
                            contentP.textContent = data.comment.content;
                            inputEdit.remove();
                            buttonsDiv.remove();
                            contentP.style.display = '';
                            el.style.display = '';
                            if (deleteBtn) deleteBtn.style.display = '';
                        }
                    });
            });
        }
    });

    // === 6. Gestion du modal d'image ===
    window.openImageModal = function(imageSrc) {
        let modal = document.getElementById('imageModal');
        if (!modal) {
            const modalHTML = `
                <div id="imageModal" class="fixed inset-0 bg-black/90 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
                    <div class="relative max-w-full max-h-full">
                        <img id="modalImage" src="" alt="Image en plein écran" class="max-w-full max-h-full object-contain rounded-lg">
                        <button onclick="closeImageModal()" class="absolute top-4 right-4 p-2 bg-black/50 backdrop-blur-sm rounded-full text-white active:scale-95">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            modal = document.getElementById('imageModal');
        }

        const modalImage = document.getElementById('modalImage');
        modalImage.src = imageSrc;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };

    window.closeImageModal = function() {
        const modal = document.getElementById('imageModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    };

    // === 7. Animation d'apparition des posts ===
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Appliquer l'animation aux posts
    document.querySelectorAll('article').forEach((post, index) => {
        post.style.opacity = '0';
        post.style.transform = 'translateY(20px)';
        post.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(post);
    });

});