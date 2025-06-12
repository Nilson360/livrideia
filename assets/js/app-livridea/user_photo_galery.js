function openPhotoModal(imageSrc, title, description, date, type) {
    const modal = document.getElementById('photo-modal');
    const modalImage = document.getElementById('modal-image');
    const modalTitle = document.getElementById('modal-title');
    const modalDescription = document.getElementById('modal-description');
    const modalDate = document.getElementById('modal-date');
    const modalBadge = document.getElementById('modal-badge');

    modalImage.src = imageSrc;
    modalImage.alt = title;
    modalTitle.textContent = title;
    modalDescription.textContent = description;
    modalDate.textContent = date;

    let badgeClass = '';
    let badgeText = '';

    switch(type) {
        case 'post':
            badgeClass = 'bg-red-500';
            badgeText = 'Publication';
            break;
        case 'avatar':
            badgeClass = 'bg-blue-500';
            badgeText = 'Photo de profil';
            break;
        case 'cover':
            badgeClass = 'bg-purple-500';
            badgeText = 'Couverture';
            break;
    }

    modalBadge.innerHTML = `<div class="${badgeClass} text-white text-xs px-3 py-1 rounded-full font-medium">${badgeText}</div>`;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePhotoModal() {
    const modal = document.getElementById('photo-modal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

// Fermer le modal en cliquant en dehors
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('photo-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closePhotoModal();
            }
        });
    }
});

window.openPhotoModal = openPhotoModal;
window.closePhotoModal = closePhotoModal;
