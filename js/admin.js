// Logique d'Administration et Back-Office

function getArrayFromPayload(payload, key) {
    if (Array.isArray(payload)) {
        return payload;
    }
    if (payload && typeof payload === 'object' && Array.isArray(payload[key])) {
        return payload[key];
    }
    return [];
}

function initAdminLoginView() {
    console.log("Initialisation du login Admin...");
    
    const adminLoginForm = document.getElementById('admin-login-form');
    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('admin-email').value;
            const password = document.getElementById('admin-password').value;
            
            try {
                // Même endpoint de login, mais nous vérifierons le rôle côté serveur/routeur après
                const response = await API.post('/auth/login.php', { email, password });
                
                if (response.status === 'success') {
                    const user = response.data.user;
                    
                    if (user.role === 'client') {
                        showToast("Accès refusé. Réservé aux modérateurs et administrateurs.", "error");
                        API.setToken(null);
                        return;
                    }
                    
                    API.setToken(response.data.token);
                    sessionStorage.setItem('sc_user', JSON.stringify(user));
                    
                    showToast("Connexion Back-Office réussie !", "success");
                    Router.navigate('#admin');
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message || "Erreur de connexion", "error");
            }
        });
    }
}

function initAdminDashboardView() {
    console.log("Initialisation du tableau de bord Admin...");
    
    // Charger les statistiques globales
    loadAdminStats();
    
    // Charger la liste des utilisateurs pour modération
    loadUsersList();
    
    // Gérer la navigation dans l'administration (onglets internes)
    setupAdminTabs();
}

async function loadAdminStats() {
    try {
        const response = await API.get('/admin/stats.php');
        if (response.status === 'success') {
            const stats = response.data.stats || response.data || {};
            
            const countUsers = document.getElementById('stat-users-count');
            const countPosts = document.getElementById('stat-posts-count');
            const countMessages = document.getElementById('stat-messages-count');
            const countRecent = document.getElementById('stat-recent-count');
            
            if (countUsers) countUsers.textContent = stats.total_users || 0;
            if (countPosts) countPosts.textContent = stats.total_articles || stats.total_posts || 0;
            if (countMessages) countMessages.textContent = stats.total_messages || 0;
            if (countRecent) countRecent.textContent = stats.recent_registrations || 0;

            // Remplir la liste des comptes avec statuts si fournie
            const accountsTbody = document.getElementById('admin-stats-accounts');
            if (accountsTbody) {
                accountsTbody.innerHTML = '<tr><td colspan="5" class="text-center">Chargement...</td></tr>';
                const accounts = getArrayFromPayload(response.data, 'accounts');
                if (accounts.length === 0) {
                    accountsTbody.innerHTML = '<tr><td colspan="5" class="text-center">Aucun compte.</td></tr>';
                } else {
                    accountsTbody.innerHTML = accounts.map(a => `
                        <tr>
                            <td>${a.id}</td>
                            <td>${a.prenom} ${a.nom}</td>
                            <td>${a.email}</td>
                            <td>${a.role || 'client'}</td>
                            <td>${a.statut || 'actif'}</td>
                        </tr>
                    `).join('');
                }
            }
        }
    } catch (error) {
        showToast("Erreur de récupération des statistiques : " + error.message, "error");
    }
}

async function loadUsersList() {
    const listContainer = document.getElementById('admin-users-list');
    if (!listContainer) return;
    
    listContainer.innerHTML = '<tr><td colspan="5" class="text-center">Chargement...</td></tr>';
    
    try {
        const response = await API.get('/admin/users.php');
        if (response.status === 'success') {
            const users = getArrayFromPayload(response.data, 'users');
            
            if (users.length === 0) {
                listContainer.innerHTML = '<tr><td colspan="5" class="text-center">Aucun utilisateur enregistré.</td></tr>';
                return;
            }
            
            const currentUserRole = JSON.parse(sessionStorage.getItem('sc_user') || '{}').role;
            listContainer.innerHTML = users.map(user => {
                const badgeClass = user.role === 'administrator' ? 'badge-admin' : (user.role === 'moderator' ? 'badge-moderator' : 'badge-client');
                const roleLabel = user.role === 'administrator' ? 'Admin' : (user.role === 'moderator' ? 'Modérateur' : 'Client');
                const statusLabel = user.statut === 'bloque' ? 'Bloqué' : 'Actif';

                // Déterminer si l'utilisateur courant peut supprimer/cibler ce compte
                let canDelete = true;
                let canToggleStatus = true;
                if (currentUserRole === 'moderator') {
                    // Les modérateurs ne peuvent pas agir sur les admins ni sur d'autres modérateurs
                    if (['administrator', 'moderator'].includes(user.role)) {
                        canDelete = false;
                        canToggleStatus = false;
                    }
                }
                if (user.id === JSON.parse(sessionStorage.getItem('sc_user') || '{}').id) {
                    canDelete = false; canToggleStatus = false;
                }

                const deleteBtn = canDelete ? `
                    <button class="btn btn-danger btn-sm btn-delete-user" data-id="${user.id}" style="padding: 4px 8px;">
                        <i class="fa-solid fa-trash-can"></i> Supprimer
                    </button>
                ` : '';

                const toggleStatusBtn = canToggleStatus ? `
                    <button class="btn btn-warning btn-sm btn-toggle-user-status" data-id="${user.id}" data-status="${user.statut || 'actif'}" style="padding: 4px 8px;">
                        <i class="fa-solid fa-ban"></i> ${user.statut === 'bloque' ? 'Débloquer' : 'Bloquer'}
                    </button>
                ` : '';

                const isCurrentUserAdmin = currentUserRole === 'administrator';
                const promoteBtn = (isCurrentUserAdmin && user.role === 'client') ? `
                    <button class="btn btn-primary btn-sm btn-promote-user" data-id="${user.id}" style="padding: 4px 8px; background-color: var(--success-color);">
                        <i class="fa-solid fa-user-shield"></i> Promouvoir Mod
                    </button>
                ` : '';

                return `
                    <tr data-id="${user.id}">
                        <td><strong>${user.id}</strong></td>
                        <td>${user.prenom} ${user.nom}</td>
                        <td>${user.email}</td>
                        <td><span class="badge ${badgeClass}">${roleLabel}</span><br><small class="text-muted">${statusLabel}</small></td>
                        <td>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                ${deleteBtn}
                                ${toggleStatusBtn}
                                ${promoteBtn}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            
            setupAdminUserInteractions();
        }
    } catch (error) {
        listContainer.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erreur : ${error.message}</td></tr>`;
    }
}

function setupAdminUserInteractions() {
    // Suppression d'utilisateur
    document.querySelectorAll('.btn-delete-user').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const tr = e.target.closest('tr');
            const id = e.target.closest('button').dataset.id;
            
            if (confirm("Voulez-vous vraiment supprimer cet utilisateur ? Cette action supprimera tous ses articles, commentaires et messages.")) {
                try {
                    const response = await API.delete(`/admin/users.php?id=${id}`);
                    if (response.status === 'success') {
                        showToast("Utilisateur supprimé !", "success");
                        tr.remove();
                        loadAdminStats(); // Rafraîchir les compteurs
                    } else {
                        showToast(response.message, "error");
                    }
                } catch (error) {
                    showToast(error.message, "error");
                }
            }
        });
    });

    document.querySelectorAll('.btn-toggle-user-status').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.closest('button').dataset.id;
            const status = e.target.closest('button').dataset.status === 'bloque' ? 'unblock' : 'block';
            const confirmMsg = status === 'block' ? 'Bloquer cet utilisateur ?' : 'Débloquer cet utilisateur ?';

            if (confirm(confirmMsg)) {
                try {
                    const response = await API.post('/admin/users.php', { user_id: id, action: status });
                    if (response.status === 'success') {
                        showToast(status === 'block' ? 'Utilisateur bloqué.' : 'Utilisateur débloqué.', 'success');
                        loadUsersList();
                        loadAdminStats();
                    } else {
                        showToast(response.message, 'error');
                    }
                } catch (error) {
                    showToast(error.message, 'error');
                }
            }
        });
    });

    // Promotion d'utilisateur
    document.querySelectorAll('.btn-promote-user').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.closest('button').dataset.id;
            
            if (confirm("Promouvoir cet utilisateur au rôle de Modérateur ?")) {
                try {
                    const response = await API.post('/admin/moderators.php', { user_id: id, role: 'moderator' });
                    if (response.status === 'success') {
                        showToast("Utilisateur promu Modérateur !", "success");
                        loadUsersList();
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

function setupAdminTabs() {
    document.querySelectorAll('.admin-menu-item').forEach(item => {
        item.addEventListener('click', (e) => {
            document.querySelectorAll('.admin-menu-item').forEach(i => i.classList.remove('active'));
            e.currentTarget.classList.add('active');
            
            const target = e.currentTarget.dataset.target;
            
            // Masquer toutes les sections admin
            document.querySelectorAll('.admin-section').forEach(s => s.classList.add('hidden'));
            
            // Afficher la section demandée
            const section = document.getElementById(target);
            if (section) section.classList.remove('hidden');
        });
    });
}
