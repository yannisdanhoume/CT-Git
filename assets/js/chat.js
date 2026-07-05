// Logique du module de Messagerie (Chat)

let activePartnerId = null;
let lastMessageTime = null;

// Cache des informations des partenaires de conversation (nom, avatar)
let partnerCache = {};

function initChatView() {
    console.log("Initialisation du Chat...");
    
    // Annuler tout ancien polling en cours
    if (window.chatIntervalId) {
        clearInterval(window.chatIntervalId);
        window.chatIntervalId = null;
    }
    
    activePartnerId = null;
    lastMessageTime = null;
    
    // Charger la liste des discussions actives
    loadConversations();
    
    // Démarrer le polling global de rafraîchissement des conversations et messages
    window.chatIntervalId = setInterval(() => {
        // Si la route actuelle n'est plus le chat, on coupe le polling
        if (window.location.hash !== '#chat') {
            clearInterval(window.chatIntervalId);
            window.chatIntervalId = null;
            return;
        }
        
        // Rafraîchir les conversations et s'il y a un salon actif, récupérer les nouveaux messages
        loadConversations(true);
        if (activePartnerId) {
            pollNewMessages().catch(err => console.error('Erreur polling chat:', err));
        }
    }, 2500); // Polling toutes les 2.5s pour réactivité immédiate

    // Recherche d'amis pour démarrer une conversation
    const searchInput = document.getElementById('chat-search');
    const searchResults = document.getElementById('chat-search-results');
    if (searchInput) {
        searchInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim().toLowerCase();
            filterConversations(query);

            if (!searchResults) {
                return;
            }

            if (!query) {
                searchResults.classList.add('hidden');
                searchResults.innerHTML = '';
                return;
            }

            searchResults.classList.remove('hidden');
            searchResults.innerHTML = '<div class="app-loader" style="min-height: auto; padding: 12px;"><div class="spinner" style="width: 18px; height: 18px; border-width: 2px;"></div></div>';

            try {
                const resp = await API.get('/friends/list.php');
                if (resp.status === 'success') {
                    const friends = resp.data.friends || [];
                    const matches = friends.filter(f => {
                        const name = `${f.prenom || ''} ${f.nom || ''}`.trim().toLowerCase();
                        const email = (f.email || '').toLowerCase();
                        return name.includes(query) || email.includes(query);
                    });

                    if (matches.length === 0) {
                        searchResults.innerHTML = '<div class="chat-search-empty text-muted">Aucun ami trouvé.</div>';
                        return;
                    }

                    searchResults.innerHTML = matches.map(m => {
                        const displayName = `${m.prenom || ''} ${m.nom || ''}`.trim();
                        return `
                            <div class="conversation-item" data-partner-id="${m.id}">
                                <img src="./assets/images/${m.avatar || 'default-avatar.png'}" alt="Avatar" class="avatar-sm">
                                <div class="conversation-details">
                                    <div class="conversation-name">${displayName}</div>
                                    <div class="conversation-preview">Aucun message</div>
                                </div>
                            </div>
                        `;
                    }).join('');

                    searchResults.querySelectorAll('.conversation-item').forEach(item => {
                        item.addEventListener('click', () => {
                            const partnerId = item.dataset.partnerId;
                            if (partnerId) {
                                selectConversation(partnerId);
                                searchResults.classList.add('hidden');
                                searchResults.innerHTML = '';
                                searchInput.value = '';
                            }
                        });
                    });
                }
            } catch (err) {
                console.error('Erreur recherche amis pour chat :', err);
                searchResults.innerHTML = '<div class="chat-search-empty text-danger">Erreur de recherche.</div>';
            }
        });
    }

    // Gestion de l'envoi de messages
    setupChatSendForm();
}

/**
 * Récupère la liste des conversations actives
 */
async function loadConversations(isBackground = false) {
    const container = document.getElementById('chat-conversations');
    if (!container) return;
    
    try {
        const response = await API.get('/chat/conversations.php');
        if (response.status === 'success') {
            const list = response.data;
            
            // Si c'est en arrière-plan, on évite de réécrire le DOM sauf si changement
            if (isBackground && container.dataset.count == list.length) {
                return; 
            }
            
            container.dataset.count = list.length;
            
            if (list.length === 0) {
                container.innerHTML = '<p class="text-muted text-center" style="padding: 20px;">Aucune conversation en cours. Recherchez un ami pour commencer !</p>';
                return;
            }
            
            // Remplir le cache des partenaires et afficher la liste
            partnerCache = {};
            container.innerHTML = list.map(item => {
                // Mettre en cache les infos du partenaire pour les réutiliser dans l'en-tête
                partnerCache[item.partner_id] = {
                    name: item.partner_name,
                    avatar: item.partner_avatar || 'default-avatar.png'
                };
                const activeClass = item.partner_id == activePartnerId ? 'active' : '';
                return `
                    <div class="conversation-item ${activeClass}" data-partner-id="${item.partner_id}">
                        <img src="./assets/images/${item.partner_avatar || 'default-avatar.png'}" alt="Avatar" class="avatar-sm">
                        <div class="conversation-details">
                            <div class="conversation-name">${item.partner_name}</div>
                            <div class="conversation-preview">${item.last_message || 'Image envoyée'}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            setupConversationSelect();
        }
    } catch (error) {
        console.error("Erreur de chargement des conversations", error);
    }
}

/**
 * Configure le clic sur un élément de conversation
 */
function setupConversationSelect() {
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.addEventListener('click', (e) => {
            const partnerId = e.currentTarget.dataset.partnerId;
            selectConversation(partnerId);
        });
    });
}

/**
 * Sélectionne et charge les messages d'un ami
 */
async function selectConversation(partnerId) {
    activePartnerId = partnerId;
    lastMessageTime = null;
    
    // Marquer l'élément actif dans la liste
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.partnerId == partnerId) {
            item.classList.add('active');
        }
    });
    
    // Rendre visible la fenêtre de chat
    const emptyChat = document.getElementById('chat-window-empty');
    const activeChat = document.getElementById('chat-window-active');
    
    if (emptyChat) emptyChat.classList.add('hidden');
    if (activeChat) activeChat.classList.remove('hidden');
    
    // Charger l'historique complet initial
    const messagesContainer = document.getElementById('chat-messages');
    messagesContainer.innerHTML = '<div class="spinner" style="margin: auto;"></div>';
    
    try {
        const response = await API.get(`/chat/messages.php?contact_id=${partnerId}`);
        if (response.status === 'success') {
            const messages = response.data.messages;
            
            // Mise à jour de l'en-tête du chat via le cache des conversations
            const partnerInfo = partnerCache[partnerId] || { name: 'Contact', avatar: 'default-avatar.png' };
            document.getElementById('chat-partner-name').textContent = partnerInfo.name;
            document.getElementById('chat-partner-avatar').src = `./assets/images/${partnerInfo.avatar}`;
            
            if (messages.length === 0) {
                messagesContainer.innerHTML = '<p class="text-muted text-center" style="margin: auto;">Aucun message. Envoyez le premier message !</p>';
            } else {
                messagesContainer.innerHTML = messages.map(m => renderMessageBubble(m)).join('');
                // Mémoriser l'heure du dernier message pour le polling
                lastMessageTime = messages[messages.length - 1].date;
                scrollChatToBottom();
            }
        }
    } catch (error) {
        messagesContainer.innerHTML = `<p class="text-danger text-center" style="margin: auto;">Erreur : ${error.message}</p>`;
    }
}

/**
 * Polling : Récupère uniquement les nouveaux messages depuis lastMessageTime
 */
async function pollNewMessages() {
    if (!activePartnerId) return;
    
    const messagesContainer = document.getElementById('chat-messages');
    if (!messagesContainer) return;
    
    const url = `/chat/messages.php?contact_id=${activePartnerId}` + (lastMessageTime ? `&since=${encodeURIComponent(lastMessageTime)}` : '');
    
    try {
        const response = await API.get(url);
        if (response.status === 'success') {
            const messages = response.data.messages;
            if (messages.length > 0) {
                // Supprimer le message d'absence de message si présent
                const placeholder = messagesContainer.querySelector('.text-muted');
                if (placeholder) placeholder.remove();
                
                // Ajouter les nouvelles bulles au DOM
                const newHtml = messages.map(m => renderMessageBubble(m)).join('');
                messagesContainer.insertAdjacentHTML('beforeend', newHtml);
                
                // Mettre à jour la date du dernier message
                lastMessageTime = messages[messages.length - 1].date;
                scrollChatToBottom();
            }
        }
    } catch (error) {
        console.error("Erreur de polling des messages :", error);
    }
}

function renderMessageBubble(msg) {
    const isMe = msg.is_me;
    const bubbleClass = isMe ? 'message-sent' : 'message-received';
    const textHtml = msg.content ? `<p>${msg.content}</p>` : '';
    const imageHtml = msg.image ? `<img src="./assets/images/${msg.image}" alt="Image partagée" class="message-image" onclick="window.open(this.src)">` : '';

    return `
        <div class="message-bubble ${bubbleClass}">
            ${textHtml}
            ${imageHtml}
        </div>
    `;
}

function scrollChatToBottom() {
    const container = document.getElementById('chat-messages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

function setupChatSendForm() {
    const sendForm = document.getElementById('chat-send-form');
    if (!sendForm) return;
    
    sendForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!activePartnerId) return;
        
        const input = document.getElementById('chat-message-input');
        const fileInput = document.getElementById('chat-file-input');
        const text = input.value;
        
        if (!text.trim() && !fileInput.files.length) {
            return;
        }
        
        const formData = new FormData();
        formData.append('receiver_id', activePartnerId);
        formData.append('content', text);
        if (fileInput.files.length > 0) {
            formData.append('image', fileInput.files[0]);
        }
        
        try {
            const response = await API.postMultipart('/chat/messages.php', formData);
            if (response.status === 'success') {
                const message = response.data.message;
                const messagesContainer = document.getElementById('chat-messages');
                const placeholder = messagesContainer ? messagesContainer.querySelector('.text-muted') : null;
                if (placeholder) placeholder.remove();

                if (messagesContainer && message) {
                    messagesContainer.insertAdjacentHTML('beforeend', renderMessageBubble(message));
                    scrollChatToBottom();
                }

                input.value = '';
                fileInput.value = '';
                lastMessageTime = message && message.date ? message.date : lastMessageTime;
                refreshConversationPreview(message);
                loadConversations(true);
            } else {
                showToast(response.message, "error");
            }
        } catch (error) {
            showToast(error.message, "error");
        }
    });
}

function refreshConversationPreview(message) {
    if (!message || !activePartnerId) return;

    const previewText = message.content ? message.content : 'Image envoyée';
    const item = document.querySelector(`.conversation-item[data-partner-id="${activePartnerId}"]`);
    if (item) {
        const previewEl = item.querySelector('.conversation-preview');
        if (previewEl) {
            previewEl.textContent = previewText;
        }
    }
}

function filterConversations(query) {
    let visible = 0;
    document.querySelectorAll('.conversation-item').forEach(item => {
        const nameEl = item.querySelector('.conversation-name');
        const name = nameEl ? nameEl.textContent.toLowerCase() : '';
        if (!query || name.includes(query)) {
            item.style.display = 'flex';
            visible++;
        } else {
            item.style.display = 'none';
        }
    });
    return visible;
}
