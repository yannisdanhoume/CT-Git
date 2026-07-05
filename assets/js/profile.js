// Logique de modification de Profil

function initProfileView() {
    console.log("Initialisation de la vue Profil...");

    const profileId = getProfileIdFromHash();

    if (!profileId) {
        fillProfileForm();
        showOwnProfileView();
    } else {
        loadProfilePage(profileId);
    }
    
    // Gérer la soumission du formulaire de mise à jour des infos
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const nom = document.getElementById('prof-nom').value;
            const prenom = document.getElementById('prof-prenom').value;
            const fileInput = document.getElementById('prof-avatar');
            
            const formData = new FormData();
            formData.append('nom', nom);
            formData.append('prenom', prenom);
            if (fileInput.files.length > 0) {
                formData.append('avatar', fileInput.files[0]);
            }
            
            try {
                const response = await API.postMultipart('/profile/update.php', formData);
                
                if (response.status === 'success') {
                    sessionStorage.setItem('sc_user', JSON.stringify(response.data.user));
                    Router.updateHeaderUserInfo();
                    showToast("Profil mis à jour avec succès !", "success");
                    fillProfileForm(response.data.user);
                    loadProfilePage(response.data.user.id);
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message, "error");
            }
        });
    }

    // Gérer la soumission du formulaire de modification de mot de passe
    const passwordForm = document.getElementById('password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const currentPassword = document.getElementById('prof-curr-pass').value;
            const newPassword = document.getElementById('prof-new-pass').value;
            const confirmPassword = document.getElementById('prof-conf-pass').value;
            
            if (newPassword !== confirmPassword) {
                showToast("Les nouveaux mots de passe ne correspondent pas.", "warning");
                return;
            }
            
            try {
                const response = await API.post('/profile/change_password.php', {
                    current_password: currentPassword,
                    new_password: newPassword
                });
                
                if (response.status === 'success') {
                    showToast("Mot de passe modifié avec succès !", "success");
                    passwordForm.reset();
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message, "error");
            }
        });
    }
}

function getProfileIdFromHash() {
    const hash = window.location.hash || '';
    const normalizedHash = hash.startsWith('#/') ? hash.slice(2) : hash.slice(1);
    const [routePart, queryString = ''] = normalizedHash.split('?');

    if (routePart !== 'profile') {
        return null;
    }

    const params = new URLSearchParams(queryString);
    const idParam = params.get('id');
    return idParam ? parseInt(idParam, 10) : null;
}

function showOwnProfileView() {
    const editSection = document.getElementById('profile-edit-section');
    const passwordSection = document.getElementById('password-section');
    if (editSection) editSection.classList.remove('hidden');
    if (passwordSection) passwordSection.classList.remove('hidden');
    loadProfilePosts(null);
}

function showOtherProfileView() {
    const editSection = document.getElementById('profile-edit-section');
    const passwordSection = document.getElementById('password-section');
    if (editSection) editSection.classList.add('hidden');
    if (passwordSection) passwordSection.classList.add('hidden');
}

async function loadProfilePage(userId) {
    try {
        const response = await API.get(`/profile/get.php?id=${userId}`);
        if (response.status !== 'success') {
            throw new Error(response.message || 'Impossible de charger le profil');
        }

        const user = response.data.user;
        renderProfileSummary(user);
        if (user.is_owner) {
            fillProfileForm(user);
            showOwnProfileView();
        } else {
            showOtherProfileView();
        }
        loadProfilePosts(user.id);
    } catch (error) {
        showToast(error.message, 'error');
        const summaryName = document.getElementById('profile-summary-name');
        if (summaryName) summaryName.textContent = 'Profil introuvable';
    }
}

function renderProfileSummary(user) {
    const summaryName = document.getElementById('profile-summary-name');
    const summaryMeta = document.getElementById('profile-summary-meta');
    const summaryFriends = document.getElementById('profile-summary-friends');
    const summaryRelation = document.getElementById('profile-summary-relation');
    const summaryAvatar = document.getElementById('profile-summary-avatar');

    if (summaryName) summaryName.textContent = `${user.prenom} ${user.nom}`;
    if (summaryMeta) {
        const roleLabel = user.role === 'administrator' ? 'Administrateur' : user.role === 'moderator' ? 'Modérateur' : 'Membre';
        summaryMeta.textContent = `${roleLabel} • ${user.statut || 'actif'}`;
    }
    if (summaryFriends) summaryFriends.textContent = `${user.friends_count || 0} ami(s)`;
    if (summaryRelation) summaryRelation.textContent = user.relationship_label || 'Aucune relation définie.';
    if (summaryAvatar) summaryAvatar.src = `./assets/images/${user.avatar || 'default-avatar.png'}`;
}

async function loadProfilePosts(userId) {
    const container = document.getElementById('profile-posts-container');
    if (!container) return;

    try {
        const endpoint = userId ? `/articles/list.php?user_id=${userId}` : '/articles/list.php';
        const response = await API.get(endpoint);
        if (response.status === 'success') {
            const posts = response.data.articles || [];
            if (posts.length === 0) {
                container.innerHTML = '<p class="text-muted">Aucune publication à afficher.</p>';
                return;
            }
            container.innerHTML = posts.map(post => renderPostCard(post)).join('');
            setupPostInteractions();
        }
    } catch (error) {
        container.innerHTML = `<p class="text-danger">Erreur lors du chargement des publications : ${error.message}</p>`;
    }
}

/**
 * Pré-remplit les champs du formulaire avec les infos de session
 */
function fillProfileForm(user = null) {
    const userInfo = user || JSON.parse(sessionStorage.getItem('sc_user') || 'null');
    if (!userInfo) return;
    
    try {
        const nomInput = document.getElementById('prof-nom');
        const prenomInput = document.getElementById('prof-prenom');
        const emailInput = document.getElementById('prof-email');
        const avatarPreview = document.getElementById('profile-avatar-preview');
        
        if (nomInput) nomInput.value = userInfo.nom;
        if (prenomInput) prenomInput.value = userInfo.prenom;
        if (emailInput) emailInput.value = userInfo.email; // Non modifiable par défaut
        
        if (avatarPreview && userInfo.avatar) {
            avatarPreview.src = `./assets/images/${userInfo.avatar}`;
        }
    } catch (e) {
        console.error("Erreur de parsing des infos utilisateur", e);
    }
}
