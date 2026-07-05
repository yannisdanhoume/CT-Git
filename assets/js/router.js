// Routeur SPA (Single Page Application)
const Router = {
    // Définition des routes, gabarits et fonctions d'initialisation associées
    routes: {
        '#auth': {
            view: 'vues/clients/auth.html',
            init: () => typeof initAuthView === 'function' && initAuthView(),
            requiresAuth: false
        },
        '#confirm': {
            view: 'vues/clients/confirm.html',
            init: () => typeof initConfirmView === 'function' && initConfirmView(),
            requiresAuth: false
        },
        '#/confirm': {
            view: 'vues/clients/confirm.html',
            init: () => typeof initConfirmView === 'function' && initConfirmView(),
            requiresAuth: false
        },
        '#feed': {
            view: 'vues/clients/feed.html',
            init: () => typeof initFeedView === 'function' && initFeedView(),
            requiresAuth: true
        },
        '#friends': {
            view: 'vues/clients/friends.html',
            init: () => typeof initFriendsView === 'function' && initFriendsView(),
            requiresAuth: true
        },
        '#profile': {
            view: 'vues/clients/profile.html',
            init: () => typeof initProfileView === 'function' && initProfileView(),
            requiresAuth: true
        },
        '#chat': {
            view: 'vues/clients/chat.html',
            init: () => typeof initChatView === 'function' && initChatView(),
            requiresAuth: true
        },
        '#admin-login': {
            view: 'vues/back-office/login.html',
            init: () => typeof initAdminLoginView === 'function' && initAdminLoginView(),
            requiresAuth: false
        },
        '#admin': {
            view: 'vues/back-office/dashboard.html',
            init: () => typeof initAdminDashboardView === 'function' && initAdminDashboardView(),
            requiresAuth: true,
            adminOnly: true
        }
    },

    defaultRoute: '#auth',
    authenticatedDefaultRoute: '#feed',

    /**
     * Navigue vers une route spécifique
     */
    navigate(hash) {
        window.location.hash = hash;
    },

    /**
     * Charge dynamiquement la route actuelle
     */
    async loadRoute() {
        let hash = window.location.hash || this.defaultRoute;
        
        // Nettoyage en cas de paramètres de requête dans le hash (ex: #profile?id=3)
        const cleanHash = hash.split('?')[0];
        let route = this.routes[cleanHash];
        
        // Si la route n'existe pas, on redirige vers l'accueil par défaut
        if (!route) {
            hash = API.getToken() ? this.authenticatedDefaultRoute : this.defaultRoute;
            this.navigate(hash);
            return;
        }

        // Vérification de l'authentification
        const isLoggedIn = !!API.getToken();
        const userInfo = sessionStorage.getItem('sc_user');
        let userRole = 'client';

        if (userInfo) {
            try { userRole = JSON.parse(userInfo).role; } catch(e) {}
        }

        if (route.requiresAuth && !isLoggedIn) {
            showToast("Veuillez vous connecter pour accéder à cette page.", "error");
            this.navigate(this.defaultRoute);
            return;
        }

        // Bloquer l'accès au tableau de bord si l'utilisateur n'est ni admin ni modérateur
        if (route.adminOnly && userRole === 'client') {
            showToast("Accès interdit : Zone réservée au personnel administratif.", "error");
            this.navigate(this.authenticatedDefaultRoute);
            return;
        }

        if (!route.requiresAuth && isLoggedIn && cleanHash === '#auth') {
            this.navigate(this.authenticatedDefaultRoute);
            return;
        }

        // Gérer l'affichage du header principal
        const header = document.getElementById('main-header');
        if (cleanHash === '#auth' || cleanHash === '#confirm' || cleanHash === '#/confirm' || cleanHash === '#admin-login') {
            header.classList.add('hidden');
        } else {
            header.classList.remove('hidden');
            this.updateHeaderUserInfo();
        }

        // Chargement du gabarit HTML partiel via Fetch
        const container = document.getElementById('app-container');
        container.innerHTML = `
            <div class="app-loader">
                <div class="spinner"></div>
                <p>Chargement...</p>
            </div>
        `;

        try {
            const response = await fetch(route.view);
            if (!response.ok) {
                throw new Error(`Impossible de charger la vue : ${route.view}`);
            }
            const htmlContent = await response.text();
            container.innerHTML = htmlContent;
            
            // Mise à jour de l'état actif dans la navigation
            this.updateActiveNavItem(cleanHash);
            
            // Lancement du script d'initialisation de la vue
            route.init();
        } catch (error) {
            console.error("Erreur de routage :", error);
            container.innerHTML = `
                <div class="error-container">
                    <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                    <h2>Une erreur est survenue lors du chargement de la page</h2>
                    <p>${error.message}</p>
                    <button class="btn btn-primary" onclick="Router.loadRoute()">Réessayer</button>
                </div>
            `;
        }
    },

    /**
     * Met à jour la classe active sur les icônes du header principal
     */
    updateActiveNavItem(hash) {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const navId = `nav-${hash.replace('#', '')}`;
        const activeNav = document.getElementById(navId);
        if (activeNav) {
            activeNav.classList.add('active');
        }
    },

    /**
     * Affiche les informations de l'utilisateur connecté dans l'en-tête
     */
    updateHeaderUserInfo() {
        const userInfo = sessionStorage.getItem('sc_user');
        if (userInfo) {
            try {
                const user = JSON.parse(userInfo);
                
                // Mettre à jour le nom
                const nameEl = document.getElementById('header-username');
                if (nameEl) nameEl.textContent = `${user.prenom} ${user.nom}`;
                
                // Mettre à jour l'avatar
                const avatarEl = document.getElementById('header-user-avatar');
                if (avatarEl && user.avatar) {
                    avatarEl.src = `./assets/images/${user.avatar}`;
                }

                if (typeof window.refreshFriendsNotifications === 'function') {
                    window.refreshFriendsNotifications();
                }
                
                // Afficher le lien Admin si modérateur/administrateur
                const adminNav = document.getElementById('nav-admin');
                if (adminNav) {
                    if (user.role === 'administrator' || user.role === 'moderator') {
                        adminNav.classList.remove('hidden');
                    } else {
                        adminNav.classList.add('hidden');
                    }
                }
            } catch (e) {
                console.error("Erreur parsing user session info", e);
            }
        }
    }
};
