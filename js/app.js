// Point d'entrée principal de l'application client

// Système global de notification Toast
function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Détermination de l'icône selon le type
    let iconClass = 'fa-circle-check';
    if (type === 'error') iconClass = 'fa-circle-xmark';
    if (type === 'warning') iconClass = 'fa-triangle-exclamation';
    if (type === 'info') iconClass = 'fa-circle-info';
    
    toast.innerHTML = `
        <i class="fa-solid ${iconClass} toast-icon"></i>
        <div class="toast-content">${message}</div>
        <button class="toast-close-btn">&times;</button>
    `;
    
    container.appendChild(toast);
    
    // Animation d'entrée
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Bouton de fermeture manuelle
    const closeBtn = toast.querySelector('.toast-close-btn');
    closeBtn.addEventListener('click', () => {
        dismissToast(toast);
    });
    
    // Fermeture automatique après délai
    const autoDismiss = setTimeout(() => {
        dismissToast(toast);
    }, duration);
    
    function dismissToast(el) {
        clearTimeout(autoDismiss);
        el.classList.remove('show');
        el.classList.add('hide');
        el.addEventListener('transitionend', () => {
            el.remove();
        });
    }
}

// Initialisation globale
document.addEventListener('DOMContentLoaded', () => {
    // Écoute des changements d'ancre (navigation)
    window.addEventListener('hashchange', () => {
        Router.loadRoute();
    });
    
    // Gestion du bouton de déconnexion
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Appel API pour fermer la session backend (optionnel mais recommandé)
            API.post('/auth/logout.php').catch(() => {});
            
            // Nettoyage local et redirection
            API.setToken(null);
            sessionStorage.removeItem('sc_user');
            
            showToast("Déconnexion réussie. À bientôt !", "success");
            Router.navigate('#auth');
        });
    }
    
    // Chargement de la route initiale
    Router.loadRoute();
});
