// Service API - Client HTTP AJAX Unifié
const API = {
    baseUrl: './api',

    /**
     * Récupère le jeton d'authentification stocké dans sessionStorage
     */
    getToken() {
        return sessionStorage.getItem('sc_session_token');
    },

    /**
     * Enregistre le jeton de session
     */
    setToken(token) {
        if (token) {
            sessionStorage.setItem('sc_session_token', token);
        } else {
            sessionStorage.removeItem('sc_session_token');
        }
    },

    /**
     * Récupère les en-têtes par défaut avec l'autorisation si disponible
     */
    getHeaders(contentType = 'application/json') {
        const headers = {};
        if (contentType) {
            headers['Content-Type'] = contentType;
        }

        const token = this.getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        return headers;
    },

    /**
     * Effectue une requête HTTP et traite la réponse JSON de manière sécurisée
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}/${endpoint.replace(/^\//, '')}`;

        try {
            const response = await fetch(url, options);

            // 1. On vérifie si la réponse est bien du JSON avant de la lire
            const contentType = response.headers.get("content-type");
            let data;

            if (contentType && contentType.includes("application/json")) {
                data = await response.json();
            } else {
                // Si le serveur a planté (ex: erreur 500 PHP), on récupère le texte brut
                const textData = await response.text();
                data = { message: textData || `Erreur serveur inconnue (Code ${response.status})` };
            }

            // 2. On traite les erreurs HTTP
            if (!response.ok) {
                // Redirection automatique si la session/token a expiré (401)
                if (response.status === 401) {
                    this.setToken(null);
                    window.location.hash = '#auth';
                }
                throw new Error(data.message || `Erreur serveur (code ${response.status})`);
            }

            return data;

        } catch (error) {
            console.error(`Erreur d'appel API sur ${endpoint}:`, error);
            throw error; // Permet aux scripts appelants (comme auth.js ou feed.js) d'attraper l'erreur et de l'afficher en Toast
        }
    },

    /**
     * Requête HTTP GET
     */
    async get(endpoint) {
        return this.request(endpoint, {
            method: 'GET',
            headers: this.getHeaders()
        });
    },

    /**
     * Requête HTTP POST (JSON)
     */
    async post(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'POST',
            headers: this.getHeaders('application/json'),
            body: JSON.stringify(body)
        });
    },

    /**
     * Requête HTTP POST avec fichiers (FormData)
     */
    async postMultipart(endpoint, formData) {
        return this.request(endpoint, {
            method: 'POST',
            // On omet volontairement le Content-Type pour que le navigateur définisse la frontière (boundary)
            headers: this.getHeaders(null),
            body: formData
        });
    },

    /**
     * Requête HTTP DELETE
     */
    async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE',
            headers: this.getHeaders()
        });
    }
};
