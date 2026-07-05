// Logique de Gestion des Relations (Amis)

function initFriendsView() {
    console.log("Initialisation de la vue Amis...");
    loadFriendsData();
}

async function loadFriendsData() {
    const friendsContainer = document.getElementById('friends-list-container');
    const requestsContainer = document.getElementById('requests-list-container');
    const sentRequestsContainer = document.getElementById('sent-requests-list-container');
    const suggestionsContainer = document.getElementById('suggestions-list-container');
    
    if (!friendsContainer || !requestsContainer || !suggestionsContainer) return;
    
    try {
        const response = await API.get('/friends/list.php');
        
        if (response.status === 'success') {
            const { friends = [], requests = [], sent_requests = [], suggestions = [] } = response.data;
            
            // 1. Rendu des Amis Confirmés
            if (friends.length === 0) {
                friendsContainer.innerHTML = '<p class="text-muted">Vous n\'avez pas encore d\'amis.</p>';
            } else {
                friendsContainer.innerHTML = friends.map(user => renderUserCard(user, 'friend')).join('');
            }
            
            // 2. Rendu des Demandes Reçues
            if (requests.length === 0) {
                requestsContainer.innerHTML = '<p class="text-muted">Aucune demande en attente.</p>';
            } else {
                requestsContainer.innerHTML = requests.map(user => renderUserCard(user, 'request')).join('');
            }

            // 3. Rendu des Invitations Envoyées
            if (sentRequestsContainer) {
                if (sent_requests.length === 0) {
                    sentRequestsContainer.innerHTML = '<p class="text-muted">Aucune invitation envoyée en attente.</p>';
                } else {
                    sentRequestsContainer.innerHTML = sent_requests.map(user => renderUserCard(user, 'sent-request')).join('');
                }
            }
            
            // 4. Rendu des Suggestions de Contacts
            if (suggestions.length === 0) {
                suggestionsContainer.innerHTML = '<p class="text-muted">Aucune suggestion disponible.</p>';
            } else {
                suggestionsContainer.innerHTML = suggestions.map(user => renderUserCard(user, 'suggestion')).join('');
            }
            
            refreshFriendsNotifications(requests.length);
            setupFriendsInteractions();
        }
    } catch (error) {
        showToast("Erreur lors du chargement des amis : " + error.message, "error");
    }
}

function renderUserCard(user, type) {
    let actionButtons = '';
    
    if (type === 'friend') {
        actionButtons = `<button class="btn btn-secondary btn-remove-friend" data-id="${user.id}"><i class="fa-solid fa-user-minus"></i> Retirer</button>`;
    } else if (type === 'request') {
        actionButtons = `
            <button class="btn btn-primary btn-accept-request" data-id="${user.id}"><i class="fa-solid fa-user-check"></i> Accepter</button>
            <button class="btn btn-secondary btn-decline-request" data-id="${user.id}"><i class="fa-solid fa-user-xmark"></i> Refuser</button>
        `;
    } else if (type === 'suggestion') {
        actionButtons = `<button class="btn btn-primary btn-invite-friend" data-id="${user.id}"><i class="fa-solid fa-user-plus"></i> Ajouter</button>`;
    } else if (type === 'sent-request') {
        actionButtons = `<span class="text-muted"><i class="fa-solid fa-clock"></i> En attente</span>`;
    }

    return `
        <div class="card friend-card" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px;">
            <a href="#profile?id=${user.id}" style="display: flex; align-items: center; gap: 16px; text-decoration: none; color: inherit;">
                <img src="./assets/images/${user.avatar || 'default-avatar.png'}" alt="Avatar" class="avatar-md" style="width: 50px; height: 50px; border-radius: 50%;">
                <div>
                    <h4 style="margin: 0;">${user.prenom} ${user.nom}</h4>
                    <span style="font-size: 12px; color: var(--text-muted);">${user.email}</span>
                </div>
            </a>
            <div style="display: flex; gap: 8px;">
                ${actionButtons}
            </div>
        </div>
    `;
}

async function refreshFriendsNotifications(count = null) {
    const badge = document.getElementById('friends-nav-badge');
    if (!badge) return;

    if (count === null) {
        try {
            const response = await API.get('/friends/list.php');
            if (response.status === 'success') {
                count = Array.isArray(response.data.requests) ? response.data.requests.length : 0;
            } else {
                count = 0;
            }
        } catch (error) {
            count = 0;
        }
    }

    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.classList.remove('hidden');
    } else {
        badge.textContent = '';
        badge.classList.add('hidden');
    }
}

function setupFriendsInteractions() {
    // Bouton pour Envoyer une invitation
    document.querySelectorAll('.btn-invite-friend').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.closest('button').dataset.id;
            try {
                const response = await API.post('/friends/request.php', { receiver_id: id });
                if (response.status === 'success') {
                    showToast("Invitation envoyée !", "success");
                    loadFriendsData();
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message, "error");
            }
        });
    });

    // Bouton pour Accepter une invitation
    document.querySelectorAll('.btn-accept-request').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.closest('button').dataset.id;
            try {
                const response = await API.post('/friends/respond.php', { sender_id: id, action: 'accept' });
                if (response.status === 'success') {
                    showToast("Demande acceptée. Vous êtes désormais amis !", "success");
                    loadFriendsData();
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message, "error");
            }
        });
    });

    // Bouton pour Refuser ou Retirer un ami
    document.querySelectorAll('.btn-decline-request, .btn-remove-friend').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.closest('button').dataset.id;
            const isRemove = e.target.closest('button').classList.contains('btn-remove-friend');
            const confirmMsg = isRemove ? "Retirer cet ami ?" : "Refuser cette demande ?";
            
            if (!isRemove || confirm(confirmMsg)) {
                try {
                    const response = await API.post('/friends/respond.php', { sender_id: id, action: 'decline' });
                    if (response.status === 'success') {
                        showToast(isRemove ? "Ami retiré" : "Invitation déclinée", "info");
                        loadFriendsData();
                    } else {
                        showToast(response.message, "error");
                    }
                } catch (error) {
                    showToast(error.message, "error");
                }
            }
        });
    });
}

window.refreshFriendsNotifications = refreshFriendsNotifications;
