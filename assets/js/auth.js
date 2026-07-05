// Logique d'Authentification (Client)

function initAuthView() {
    console.log("Initialisation de la vue Authentification...");
    
    // Toggle entre les différents formulaires (Connexion, Inscription, Récupération)
    const showRegisterLink = document.getElementById('show-register');
    const showLoginLink = document.getElementById('show-login');
    const showForgotLink = document.getElementById('show-forgot');
    const showLoginFromForgotLink = document.getElementById('show-login-from-forgot');
    
    const loginFormContainer = document.getElementById('login-form-container');
    const registerFormContainer = document.getElementById('register-form-container');
    const forgotFormContainer = document.getElementById('forgot-form-container');
    
    if (showRegisterLink) {
        showRegisterLink.addEventListener('click', (e) => {
            e.preventDefault();
            loginFormContainer.classList.add('hidden');
            registerFormContainer.classList.remove('hidden');
        });
    }
    
    if (showLoginLink) {
        showLoginLink.addEventListener('click', (e) => {
            e.preventDefault();
            registerFormContainer.classList.add('hidden');
            loginFormContainer.classList.remove('hidden');
        });
    }

    if (showForgotLink) {
        showForgotLink.addEventListener('click', (e) => {
            e.preventDefault();
            loginFormContainer.classList.add('hidden');
            forgotFormContainer.classList.remove('hidden');
        });
    }

    if (showLoginFromForgotLink) {
        showLoginFromForgotLink.addEventListener('click', (e) => {
            e.preventDefault();
            forgotFormContainer.classList.add('hidden');
            loginFormContainer.classList.remove('hidden');
        });
    }
    
    // Soumission du formulaire de connexion
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            
            try {
                const response = await API.post('/auth/login.php', { email, password });
                
                if (response.status === 'success') {
                    // Enregistrer le token de session et l'utilisateur
                    API.setToken(response.data.token);
                    sessionStorage.setItem('sc_user', JSON.stringify(response.data.user));
                    
                    showToast("Connexion réussie !", "success");
                    Router.navigate('#feed');
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message || "Erreur de connexion", "error");
            }
        });
    }
    
    // Soumission du formulaire d'inscription
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const nom = document.getElementById('reg-nom').value;
            const prenom = document.getElementById('reg-prenom').value;
            const email = document.getElementById('reg-email').value;
            const password = document.getElementById('reg-password').value;
            
            try {
                const response = await API.post('/auth/register.php', { nom, prenom, email, password });
                
                if (response.status === 'success') {
                    showToast("Inscription réussie ! Un email de confirmation vous a été envoyé.", "success");
                    // Affichage du formulaire de connexion
                    registerFormContainer.classList.add('hidden');
                    loginFormContainer.classList.remove('hidden');
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message || "Erreur lors de l'inscription", "error");
            }
        });
    }

    // Soumission du formulaire de mot de passe oublié
    const forgotForm = document.getElementById('forgot-form');
    if (forgotForm) {
        forgotForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('forgot-email').value;
            
            try {
                const response = await API.post('/auth/forgot_password.php', { email });
                if (response.status === 'success') {
                    showToast("Un email de réinitialisation vous a été envoyé.", "success");
                    forgotFormContainer.classList.add('hidden');
                    loginFormContainer.classList.remove('hidden');
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message || "Erreur de demande", "error");
            }
        });
    }
}

/**
 * Initialise la vue de confirmation d'inscription
 * Lit le paramètre success/error dans l'URL et affiche le résultat approprié
 */
function initConfirmView() {
    console.log("Initialisation de la vue Confirmation...");
    
    const params = new URLSearchParams(window.location.hash.split('?')[1] || '');
    
    const loadingEl = document.getElementById('confirm-loading');
    const successEl = document.getElementById('confirm-success');
    const errorEl = document.getElementById('confirm-error');
    
    if (params.get('success') === '1') {
        // Succès depuis la redirection de confirm.php
        if (loadingEl) loadingEl.classList.add('hidden');
        if (successEl) successEl.classList.remove('hidden');
    } else if (params.get('error') === 'token_invalide') {
        if (loadingEl) loadingEl.classList.add('hidden');
        if (errorEl) errorEl.classList.remove('hidden');
    } else if (params.get('error') === 'technique') {
        if (loadingEl) loadingEl.classList.add('hidden');
        if (errorEl) errorEl.classList.remove('hidden');
    } else {
        // Token présent dans l'URL ? Si oui, on appelle l'API en AJAX
        const token = params.get('token');
        if (token) {
            validateToken(token);
        } else {
            // Ni token, ni résultat connu — on lance la vérification via l'URL actuelle
            // (cas d'un accès direct sans paramètre)
            if (loadingEl) loadingEl.classList.add('hidden');
            if (errorEl) errorEl.classList.remove('hidden');
        }
    }
}

/**
 * Valide un token de confirmation via l'API
 */
async function validateToken(token) {
    const loadingEl = document.getElementById('confirm-loading');
    const successEl = document.getElementById('confirm-success');
    const errorEl = document.getElementById('confirm-error');
    
    try {
        const response = await API.post('/auth/confirm.php', { token });
        
        if (response.status === 'success') {
            if (loadingEl) loadingEl.classList.add('hidden');
            if (successEl) successEl.classList.remove('hidden');
        } else {
            if (loadingEl) loadingEl.classList.add('hidden');
            if (errorEl) errorEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error("Erreur de confirmation :", error);
        if (loadingEl) loadingEl.classList.add('hidden');
        if (errorEl) errorEl.classList.remove('hidden');
    }
}
