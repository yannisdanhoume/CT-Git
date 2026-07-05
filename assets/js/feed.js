// Logique du Flux d'actualités (Articles)

function initFeedView() {
    console.log("Initialisation du flux d'actualités...");
    
    // Charger les articles au démarrage de la vue
    loadPosts();
    
    // Annuler tout ancien polling en cours
    if (window.feedIntervalId) {
        clearInterval(window.feedIntervalId);
        window.feedIntervalId = null;
    }
    
    // Démarrer le polling automatique des articles
    window.feedIntervalId = setInterval(() => {
        if (window.location.hash !== '#feed') {
            clearInterval(window.feedIntervalId);
            window.feedIntervalId = null;
            return;
        }
        loadPosts(true);
    }, 4000); // Polling toutes les 4 secondes
    
    // Gérer la création d'une nouvelle publication
    const createPostForm = document.getElementById('create-post-form');
    if (createPostForm) {
        createPostForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const description = document.getElementById('post-desc').value;
            const fileInput = document.getElementById('post-file');
            
            if (!description.trim() && !fileInput.files.length) {
                showToast("Votre publication ne peut pas être vide !", "warning");
                return;
            }
            
            const formData = new FormData();
            formData.append('description', description);
            if (fileInput.files.length > 0) {
                formData.append('image', fileInput.files[0]);
            }
            
            try {
                const response = await API.postMultipart('/articles/create.php', formData);
                
                if (response.status === 'success') {
                    showToast("Publication créée !", "success");
                    createPostForm.reset();
                    // Recharger la liste des posts
                    loadPosts();
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message || "Erreur de publication", "error");
            }
        });
    }
}

/**
 * Récupère les publications du serveur et les affiche
 */
async function loadPosts(isBackground = false) {
    const feedContainer = document.getElementById('feed-posts');
    if (!feedContainer) return;
    
    try {
        const response = await API.get('/articles/list.php');
        
        if (response.status === 'success') {
            const posts = response.data.articles || response.data;
            const oldCount = feedContainer.dataset.postCount || '0';
            const newCount = posts.length;
            
            // Si c'est un polling en arrière-plan et le nombre n'a pas changé, on évite le DOM reflow
            if (isBackground && oldCount == newCount) {
                return;
            }
            
            feedContainer.dataset.postCount = newCount;
            
            if (posts.length === 0) {
                feedContainer.innerHTML = `
                    <div class="card text-center text-muted">
                        <p>Aucune publication pour le moment. Soyez le premier à publier !</p>
                    </div>
                `;
                return;
            }
            
            feedContainer.innerHTML = posts.map(post => renderPostCard(post)).join('');
            setupPostInteractions();
        }
    } catch (error) {
        if (!isBackground) {
            feedContainer.innerHTML = `
                <div class="card text-center text-danger">
                    <p>Erreur lors du chargement des publications : ${error.message}</p>
                </div>
            `;
        }
    }
}

/**
 * Génère le code HTML pour une carte d'article
 */
function renderPostCard(post) {
    const imageHtml = post.image ? `<img src="./assets/images/${post.image}" alt="Image publication" class="post-image">` : '';
    const likedClass = post.user_reaction === 'like' ? 'liked' : '';
    const dislikedClass = post.user_reaction === 'dislike' ? 'disliked' : '';
    
    // Rendu des commentaires existants
    const commentsHtml = post.comments ? post.comments.map(c => `
        <div class="comment-item">
            <img src="./assets/images/${c.author_avatar || 'default-avatar.png'}" alt="Avatar" class="avatar-sm">
            <div class="comment-bubble">
                <div class="comment-author">${c.author_name}</div>
                <div class="comment-text">${c.content}</div>
            </div>
        </div>
    `).join('') : '';

    return `
        <div class="card post-card" data-id="${post.id}">
            <div class="post-header">
                <a href="#profile?id=${post.author_id || ''}" class="post-author-link" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit;">
                    <img src="./assets/images/${post.author_avatar || 'default-avatar.png'}" alt="Avatar" class="avatar-sm">
                    <div>
                        <div class="post-author-name">${post.author_name}</div>
                        <div class="post-time">${post.date}</div>
                    </div>
                </a>
                ${post.can_delete ? `<button class="btn-delete-post icon-btn" title="Supprimer"><i class="fa-solid fa-trash"></i></button>` : ''}
            </div>
            
            <div class="post-body">
                <p>${post.description}</p>
            </div>
            
            ${imageHtml}
            
            <div class="post-stats">
                <span><i class="fa-solid fa-thumbs-up text-primary"></i> <span class="like-count">${post.likes_count || 0}</span></span>
                <span><span class="comment-count">${post.comments_count || 0}</span> commentaires</span>
            </div>
            
            <div class="post-actions">
                <button class="post-action-btn btn-like ${likedClass}">
                    <i class="fa-solid fa-thumbs-up"></i> J'aime
                </button>
                <button class="post-action-btn btn-dislike ${dislikedClass}">
                    <i class="fa-solid fa-thumbs-down"></i> Je n'aime pas
                </button>
                <button class="post-action-btn btn-comment-toggle">
                    <i class="fa-solid fa-comment"></i> Commenter
                </button>
            </div>
            
            <div class="comments-section-wrapper hidden">
                <div class="comments-section">
                    ${commentsHtml}
                </div>
                <form class="comment-input-area" data-post-id="${post.id}">
                    <img src="./assets/images/default-avatar.png" alt="Avatar" class="avatar-sm current-user-avatar">
                    <input type="text" class="comment-control" placeholder="Écrire un commentaire..." required>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    `;
}

/**
 * Configure les écouteurs d'événements pour les boutons d'action des articles
 */
function setupPostInteractions() {
    // Bouton de suppression
    document.querySelectorAll('.btn-delete-post').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const postCard = e.target.closest('.post-card');
            const postId = postCard.dataset.id;
            
            if (confirm("Voulez-vous vraiment supprimer cet article ?")) {
                try {
                    const response = await API.delete(`/articles/delete.php?id=${postId}`);
                    if (response.status === 'success') {
                        showToast("Article supprimé !", "success");
                        postCard.remove();
                    } else {
                        showToast(response.message, "error");
                    }
                } catch (error) {
                    showToast(error.message, "error");
                }
            }
        });
    });

    // Boutons de like
    document.querySelectorAll('.btn-like').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const postCard = e.target.closest('.post-card');
            const postId = postCard.dataset.id;
            
            try {
                const response = await API.post('/articles/like.php', { post_id: postId, type: 'like' });
                if (response.status === 'success') {
                    // Recharger les posts pour rafraîchir les nombres et couleurs
                    loadPosts();
                }
            } catch (error) {
                showToast(error.message, "error");
            }
        });
    });

    // Boutons de dislike
    document.querySelectorAll('.btn-dislike').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const postCard = e.target.closest('.post-card');
            const postId = postCard.dataset.id;
            
            try {
                const response = await API.post('/articles/dislike.php', { post_id: postId, type: 'dislike' });
                if (response.status === 'success') {
                    // Recharger les posts pour rafraîchir les nombres et couleurs
                    loadPosts();
                }
            } catch (error) {
                showToast(error.message, "error");
            }
        });
    });

    // Affichage/Masquage des commentaires
    document.querySelectorAll('.btn-comment-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const postCard = e.target.closest('.post-card');
            const commentsWrapper = postCard.querySelector('.comments-section-wrapper');
            commentsWrapper.classList.toggle('hidden');
        });
    });

    // Envoi de commentaire
    document.querySelectorAll('.comment-input-area').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const postId = form.dataset.postId;
            const input = form.querySelector('.comment-control');
            const content = input.value;
            
            try {
                const response = await API.post('/articles/comment.php', { post_id: postId, content });
                if (response.status === 'success') {
                    showToast("Commentaire ajouté !", "success");
                    input.value = '';
                    loadPosts(); // Recharger pour afficher le nouveau commentaire
                } else {
                    showToast(response.message, "error");
                }
            } catch (error) {
                showToast(error.message, "error");
            }
        });
    });
}
