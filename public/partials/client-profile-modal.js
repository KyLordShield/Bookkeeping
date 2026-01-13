
//client-profile-modal.js
document.addEventListener('DOMContentLoaded', function () {

const modal          = document.getElementById('profileModal');
const openBtn        = document.getElementById('openProfileModal');
const closeBtn       = document.getElementById('closeProfileModal');
const editBtn        = document.getElementById('editProfileBtn');
const cancelBtn      = document.getElementById('cancelEditBtn');
const viewSection    = document.getElementById('profileView');
const editForm       = document.getElementById('profileEditForm');

if (!modal) return;  // safety

// Open modal
if (openBtn) {
    openBtn.addEventListener('click', () => {
        modal.style.display = 'block';
    });
}

// Close modal
if (closeBtn) {
    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });
}

// Click outside → close
window.addEventListener('click', (event) => {
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Switch to edit mode
if (editBtn) {
    editBtn.addEventListener('click', () => {
        viewSection.style.display = 'none';
        editForm.style.display = 'block';
    });
}

// Cancel edit → back to view
if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
        editForm.style.display = 'none';
        viewSection.style.display = 'block';
    });
}
});