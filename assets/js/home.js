document.addEventListener('DOMContentLoaded', function () {
    // === 1. Toggle dos comentários ===
    document.querySelectorAll('.toggle-comments').forEach(button => {
        button.addEventListener('click', function () {
            const container = this.closest('.post-container');
            const commentSection = container.querySelector('.comments-section');
            commentSection?.classList.toggle('hidden');
        });
    });

    // === 2. Gestion du like ===
    document.querySelectorAll('.like-button').forEach(button => {
        button.addEventListener('click', function () {
            const postId = this.getAttribute('data-id');
            const container = this.closest('.post-container');

            fetch('/post/' + postId + '/like', {
                method: 'POST', headers: {
                    'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    const statsContainer = container.querySelector('.stats-container');
                    const likeCountEl = container.querySelector('.like-count');

                    if (likeCountEl) {
                        const countSpan = likeCountEl.querySelector('span');
                        if (countSpan) {
                            countSpan.textContent = data.likesCount + ' j\'aime';
                        }
                        likeCountEl.classList.toggle('hidden', data.likesCount === 0);
                    }

                    // Mettre à jour le style du bouton
                    if (data.status === 'liked') {
                        this.classList.remove('text-gray-600', 'hover:bg-red-50', 'hover:text-red-500');
                        this.classList.add('text-red-500', 'bg-red-50');
                    } else {
                        this.classList.remove('text-red-500', 'bg-red-50');
                        this.classList.add('text-gray-600', 'hover:bg-red-50', 'hover:text-red-500');
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
        const container = button.closest('.post-container');
        const postId = button.getAttribute('data-post-id');
        const input = container.querySelector('.comment-input');
        const content = input.value.trim();

        if (!content) return;

        fetch('/post/' + postId + '/comment', {
            method: 'POST', headers: {
                'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'
            }, body: new URLSearchParams({content})
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'commented') {
                    const commentsSection = container.querySelector('.comments-section');
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
                    commentsSection.insertBefore(newComment, commentsSection.querySelector('.add-comment'));
                    input.value = '';

                    // Mettre à jour le compteur de commentaires
                    const statsContainer = container.querySelector('.stats-container');
                    let commentCountDisplay = container.querySelector('.comment-count-display');

                    if (commentCountDisplay) {
                        commentCountDisplay.textContent = `${data.commentsCount} commentaire${data.commentsCount > 1 ? 's' : ''}`;
                    } else if (statsContainer && data.commentsCount > 0) {
                        // Créer le compteur s'il n'existe pas
                        const leftDiv = statsContainer.querySelector('.flex.items-center.space-x-4');
                        if (leftDiv) {
                            const commentCountSpan = document.createElement('span');
                            commentCountSpan.className = 'comment-count-display';
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

    document.querySelectorAll('.send-comment').forEach(button => {
        button.addEventListener('click', () => sendComment(button));
    });

    document.querySelectorAll('.comment-input').forEach(input => {
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendComment(this.closest('.post-container').querySelector('.send-comment'));
            }
        });
    });

    // === 4. Suppression et édition d'un commentaire ===
    document.addEventListener('click', function (e) {
        const el = e.target;
        const container = el.closest('.post-container');

        // Suppression
        if (el.classList.contains('delete-comment')) {
            e.preventDefault();
            const commentId = el.dataset.commentId;

            if (confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
                fetch(`/comment/${commentId}/delete`, {
                    method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'deleted') {
                            el.closest('.border-t').remove();

                            // Mettre à jour le compteur
                            const commentCountDisplay = container.querySelector('.comment-count-display');
                            if (commentCountDisplay) {
                                if (data.commentsCount > 0) {
                                    commentCountDisplay.textContent = `${data.commentsCount} commentaire${data.commentsCount > 1 ? 's' : ''}`;
                                } else {
                                    commentCountDisplay.remove();

                                    // Vérifier si on doit masquer stats-container
                                    const statsContainer = container.querySelector('.stats-container');
                                    const likeCount = container.querySelector('.like-count span')?.textContent.match(/\d+/)?.[0] || 0;
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
                    method: 'POST', headers: {
                        'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'
                    }, body: new URLSearchParams({content: newContent})
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

    // === 5. Prévisualisation d'image - CORRIGIDO ===
    const imageInput = document.querySelector('.post-image-input');
    if (imageInput) {
        imageInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                // Créer preview
                let preview = document.querySelector('.image-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'image-preview mt-3 relative';
                    this.parentNode.appendChild(preview);
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.innerHTML = `
                        <div class="relative inline-block">
                            <img src="${e.target.result}" alt="Aperçu" class="max-h-40 rounded-lg border border-gray-200 shadow-sm">
                            <button type="button" class="remove-image absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 transition-colors">×</button>
                        </div>
                    `;

                    // Bouton pour supprimer l'image
                    preview.querySelector('.remove-image').addEventListener('click', function () {
                        imageInput.value = '';
                        preview.remove();
                    });
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // === 6. Animation d'apparition des posts ===
    const observerOptions = {
        threshold: 0.1, rootMargin: '0px 0px -50px 0px'
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
    document.querySelectorAll('.post-container').forEach((post, index) => {
        post.style.opacity = '0';
        post.style.transform = 'translateY(20px)';
        post.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        observer.observe(post);
    });
});